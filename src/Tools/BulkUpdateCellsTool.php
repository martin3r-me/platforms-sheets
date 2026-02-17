<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsCell;
use Platform\Sheets\Models\SheetsCellType;
use Platform\Sheets\Models\SheetsWorksheet;
use Platform\Sheets\Services\FormulaService;
use Platform\Sheets\Services\CellProtectionService;

class BulkUpdateCellsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.cells.bulk.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /cells/bulk - Schreibt mehrere Zellen auf einmal. Effizient für das Befüllen ganzer Bereiche. REST-Parameter: worksheet_id (required), cells (required, array of {ref, value, format?, is_locked?}).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'worksheet_id' => ['type' => 'integer', 'description' => 'ID des Worksheets'],
                'cells' => [
                    'type' => 'array',
                    'description' => 'Array von Zellen zum Schreiben',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'ref' => ['type' => 'string', 'description' => 'Zell-Referenz (z.B. "A1")'],
                            'value' => ['type' => 'string', 'description' => 'Wert (Formeln mit = Prefix)'],
                            'format' => ['type' => 'object', 'description' => 'Formatierung (optional)'],
                            'is_locked' => ['type' => 'boolean', 'description' => 'Zelle sperren (optional)'],
                        ],
                        'required' => ['ref', 'value'],
                    ],
                ],
            ],
            'required' => ['worksheet_id', 'cells'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $worksheet = SheetsWorksheet::find($arguments['worksheet_id']);
            if (!$worksheet) {
                return ToolResult::error('NOT_FOUND', 'Worksheet nicht gefunden');
            }
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'User erforderlich');
            }
            if (empty($arguments['cells']) || !is_array($arguments['cells'])) {
                return ToolResult::error('VALIDATION_ERROR', 'cells Array ist erforderlich');
            }
            if (count($arguments['cells']) > 1000) {
                return ToolResult::error('VALIDATION_ERROR', 'Maximal 1000 Zellen pro Bulk-Operation');
            }

            $formulaService = new FormulaService();
            $protectionService = new CellProtectionService();
            $results = [];
            $errors = [];
            $formulaCells = [];

            foreach ($arguments['cells'] as $cellData) {
                if (!preg_match('/^([A-Z]{1,3})(\d+)$/i', $cellData['ref'], $m)) {
                    $errors[] = "Ungültige Referenz: {$cellData['ref']}";
                    continue;
                }

                $col = SheetsCell::letterToNumber(strtoupper($m[1]));
                $row = (int) $m[2];

                if (!$protectionService->canEditPosition($worksheet, $row, $col, $context->user)) {
                    $errors[] = "Zelle {$cellData['ref']} ist geschützt";
                    continue;
                }

                $rawValue = $cellData['value'] ?? '';
                $cellTypeKey = $formulaService->determineCellType($rawValue);
                $cellType = SheetsCellType::where('key', $cellTypeKey)->first()
                    ?? SheetsCellType::where('key', 'text')->first();

                $computedValue = $rawValue;

                $cell = SheetsCell::updateOrCreate(
                    ['worksheet_id' => $worksheet->id, 'row' => $row, 'col' => $col],
                    [
                        'raw_value' => $rawValue,
                        'computed_value' => $computedValue,
                        'cell_type_id' => $cellType->id,
                        'format' => $cellData['format'] ?? null,
                        'is_locked' => $cellData['is_locked'] ?? false,
                        'user_id' => $context->user->id,
                    ]
                );

                if ($cellTypeKey === 'formula') {
                    $formulaCells[] = $cell;
                }

                $results[] = $cellData['ref'];
            }

            // Evaluate formulas after all cells are written
            if (!empty($formulaCells)) {
                $cellValues = $this->getWorksheetCellValues($worksheet->id);
                foreach ($formulaCells as $cell) {
                    $computed = (string) $formulaService->evaluate($cell->raw_value, $cellValues);
                    $cell->update(['computed_value' => $computed]);
                    $formulaService->updateDependencies($cell);
                }
            }

            // Recalculate all dependents
            $allCells = SheetsCell::where('worksheet_id', $worksheet->id)
                ->whereIn('row', array_map(fn ($r) => $this->parseRow($r), $results))
                ->get();
            foreach ($allCells as $cell) {
                $formulaService->recalculateDependents($cell);
            }

            return ToolResult::success([
                'updated' => count($results),
                'errors' => $errors,
                'cells' => $results,
                'message' => count($results) . ' Zelle(n) aktualisiert.' . (count($errors) > 0 ? ' ' . count($errors) . ' Fehler.' : ''),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    protected function getWorksheetCellValues(int $worksheetId): array
    {
        $values = [];
        $cells = SheetsCell::where('worksheet_id', $worksheetId)->get();
        foreach ($cells as $cell) {
            $ref = $cell->cell_ref;
            $values[$ref] = $cell->computed_value ?? $cell->raw_value ?? '0';
        }
        return $values;
    }

    protected function parseRow(string $ref): int
    {
        preg_match('/(\d+)$/', $ref, $m);
        return (int) ($m[1] ?? 0);
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'cell', 'bulk', 'write'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
