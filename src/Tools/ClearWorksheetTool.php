<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsCell;
use Platform\Sheets\Models\SheetsCellDependency;
use Platform\Sheets\Models\SheetsCellType;
use Platform\Sheets\Models\SheetsWorksheet;
use Platform\Sheets\Services\FormulaService;

class ClearWorksheetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.worksheet.clear';
    }

    public function getDescription(): string
    {
        return 'POST /worksheet/clear - Leert ein Worksheet komplett oder einen bestimmten Bereich. '
            . 'Ideal zum Zurücksetzen vor einem Re-Import oder zum gezielten Löschen von Daten. '
            . 'Parameter: worksheet_id (required), '
            . 'range (optional, z.B. "A2:J999" – nur diesen Bereich leeren, z.B. Header behalten), '
            . 'clear_type (optional, default "all" – "all" löscht Werte+Formate, "values" nur Werte/Formeln, "formats" nur Formatierungen).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'worksheet_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Worksheets',
                ],
                'range' => [
                    'type' => 'string',
                    'description' => 'Optionaler Zell-Bereich, z.B. "A2:J999". Wenn nicht angegeben, wird das gesamte Worksheet geleert.',
                ],
                'clear_type' => [
                    'type' => 'string',
                    'enum' => ['all', 'values', 'formats'],
                    'description' => 'Was geleert wird: "all" (Werte + Formate, Standard), "values" (nur Werte/Formeln), "formats" (nur Formatierungen)',
                ],
            ],
            'required' => ['worksheet_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $worksheet = SheetsWorksheet::find($arguments['worksheet_id']);
            if (!$worksheet) {
                return ToolResult::error('NOT_FOUND', 'Worksheet nicht gefunden');
            }

            $clearType = $arguments['clear_type'] ?? 'all';
            $range = isset($arguments['range']) ? trim($arguments['range']) : null;

            // Build base query for affected cells
            $query = SheetsCell::where('worksheet_id', $worksheet->id);

            $rangeLabel = 'gesamtes Worksheet';

            if ($range !== null) {
                // Parse range
                if (!preg_match('/^([A-Z]{1,3})(\d+):([A-Z]{1,3})(\d+)$/i', $range, $m)) {
                    return ToolResult::error('INVALID_RANGE', 'Ungültiger Bereich. Erwartet: z.B. "A2:J999", "B2:D10"');
                }

                $startCol = SheetsCell::letterToNumber(strtoupper($m[1]));
                $startRow = (int) $m[2];
                $endCol = SheetsCell::letterToNumber(strtoupper($m[3]));
                $endRow = (int) $m[4];

                if ($startRow > $endRow || $startCol > $endCol) {
                    return ToolResult::error('INVALID_RANGE', 'Start muss vor End liegen (z.B. "A2:J999", nicht "J999:A2")');
                }

                $query->whereBetween('row', [$startRow, $endRow])
                      ->whereBetween('col', [$startCol, $endCol]);

                $rangeLabel = strtoupper($m[1]) . $m[2] . ':' . strtoupper($m[3]) . $m[4];
            }

            // Get affected cells
            $cells = $query->get();
            $affectedCount = $cells->count();

            if ($affectedCount === 0) {
                return ToolResult::success([
                    'cleared' => 0,
                    'range' => $rangeLabel,
                    'clear_type' => $clearType,
                    'message' => 'Keine Zellen im Bereich gefunden – nichts zu leeren.',
                ]);
            }

            $formulaService = new FormulaService();

            switch ($clearType) {
                case 'values':
                    $this->clearValues($cells, $formulaService);
                    break;

                case 'formats':
                    $this->clearFormats($cells);
                    break;

                case 'all':
                default:
                    $this->clearAll($cells, $worksheet, $range, $formulaService);
                    break;
            }

            // Recalculate dependents outside the cleared range
            $this->recalculateExternalDependents($cells, $formulaService);

            $typeLabel = match ($clearType) {
                'values' => 'Werte/Formeln',
                'formats' => 'Formatierungen',
                default => 'Werte + Formate',
            };

            return ToolResult::success([
                'cleared' => $affectedCount,
                'range' => $rangeLabel,
                'clear_type' => $clearType,
                'message' => "{$affectedCount} Zelle(n) geleert ({$typeLabel}) im Bereich: {$rangeLabel}.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    /**
     * Clear only values – reset to empty cells, keep format.
     */
    protected function clearValues(\Illuminate\Database\Eloquent\Collection $cells, FormulaService $formulaService): void
    {
        $emptyType = SheetsCellType::where('key', 'empty')->first();
        $emptyTypeId = $emptyType ? $emptyType->id : null;

        $cellIds = $cells->pluck('id')->toArray();

        // Remove dependencies for formula cells being cleared
        SheetsCellDependency::whereIn('cell_id', $cellIds)->delete();

        // Bulk update: clear values, keep format
        SheetsCell::whereIn('id', $cellIds)->update([
            'raw_value' => null,
            'computed_value' => null,
            'cell_type_id' => $emptyTypeId,
        ]);
    }

    /**
     * Clear only formats – keep values and formulas.
     */
    protected function clearFormats(\Illuminate\Database\Eloquent\Collection $cells): void
    {
        $cellIds = $cells->pluck('id')->toArray();

        SheetsCell::whereIn('id', $cellIds)->update([
            'format' => null,
        ]);
    }

    /**
     * Clear everything – delete cell rows from the database.
     */
    protected function clearAll(\Illuminate\Database\Eloquent\Collection $cells, SheetsWorksheet $worksheet, ?string $range, FormulaService $formulaService): void
    {
        $cellIds = $cells->pluck('id')->toArray();

        // Remove all dependencies (both directions)
        SheetsCellDependency::whereIn('cell_id', $cellIds)->delete();
        SheetsCellDependency::whereIn('depends_on_cell_id', $cellIds)->delete();

        // Delete cells from database
        SheetsCell::whereIn('id', $cellIds)->delete();
    }

    /**
     * Recalculate formula cells outside the cleared range that depended on cleared cells.
     */
    protected function recalculateExternalDependents(\Illuminate\Database\Eloquent\Collection $clearedCells, FormulaService $formulaService): void
    {
        $clearedCellIds = $clearedCells->pluck('id')->toArray();

        // Find cells that depended on cleared cells but are NOT themselves cleared
        $externalDependentIds = SheetsCellDependency::whereIn('depends_on_cell_id', $clearedCellIds)
            ->whereNotIn('cell_id', $clearedCellIds)
            ->pluck('cell_id')
            ->unique()
            ->toArray();

        if (empty($externalDependentIds)) {
            return;
        }

        foreach ($externalDependentIds as $cellId) {
            $cell = SheetsCell::find($cellId);
            if (!$cell || !str_starts_with($cell->raw_value ?? '', '=')) {
                continue;
            }

            $cellValues = $this->getCellValuesForFormula($cell);
            $computed = $formulaService->evaluate($cell->raw_value, $cellValues);
            $cell->update(['computed_value' => (string) $computed]);

            $formulaService->recalculateDependents($cell);
        }
    }

    protected function getCellValuesForFormula(SheetsCell $cell): array
    {
        $formulaService = new FormulaService();
        $references = $formulaService->parseReferences($cell->raw_value);
        $values = [];

        foreach ($references as $ref) {
            $refCell = SheetsCell::where('worksheet_id', $cell->worksheet_id)
                ->where('row', $ref['row'])
                ->where('col', $ref['col'])
                ->first();

            $cellRef = SheetsCell::numberToLetter($ref['col']) . $ref['row'];
            $values[$cellRef] = $refCell ? ($refCell->computed_value ?? $refCell->raw_value ?? '0') : '0';
        }

        return $values;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'worksheet', 'clear', 'reset', 'delete', 'bulk'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'destructive',
            'idempotent' => true,
        ];
    }
}
