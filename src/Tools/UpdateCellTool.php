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

class UpdateCellTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.cells.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /cells - Schreibt eine einzelne Zelle. Unterstützt Formeln (=SUM(A1:A10)), Text, Zahlen. Prüft Zellschutz. REST-Parameter: worksheet_id (required), ref (required, z.B. "A1") ODER row+col, value (required), format (optional, JSON), is_locked (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'worksheet_id' => ['type' => 'integer', 'description' => 'ID des Worksheets'],
                'ref' => ['type' => 'string', 'description' => 'Zell-Referenz, z.B. "A1", "B5"'],
                'row' => ['type' => 'integer', 'description' => 'Zeile (alternativ zu ref)'],
                'col' => ['type' => 'integer', 'description' => 'Spalte als Zahl (alternativ zu ref)'],
                'value' => ['type' => 'string', 'description' => 'Wert der Zelle. Formeln beginnen mit = (z.B. "=SUM(A1:A10)")'],
                'format' => ['type' => 'object', 'description' => 'Formatierung: {font, color, alignment, number_format, borders, bg_color}'],
                'is_locked' => ['type' => 'boolean', 'description' => 'Zelle sperren (wirkt nur bei geschütztem Worksheet)'],
            ],
            'required' => ['worksheet_id', 'value'],
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

            // Resolve row/col from ref or direct values
            if (!empty($arguments['ref'])) {
                if (preg_match('/^([A-Z]{1,3})(\d+)$/i', $arguments['ref'], $m)) {
                    $col = SheetsCell::letterToNumber(strtoupper($m[1]));
                    $row = (int) $m[2];
                } else {
                    return ToolResult::error('VALIDATION_ERROR', 'Ungültige Zell-Referenz: ' . $arguments['ref']);
                }
            } elseif (isset($arguments['row']) && isset($arguments['col'])) {
                $row = (int) $arguments['row'];
                $col = (int) $arguments['col'];
            } else {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder ref (z.B. "A1") oder row+col angeben');
            }

            // Protection check
            $protectionService = new CellProtectionService();
            if (!$protectionService->canEditPosition($worksheet, $row, $col, $context->user)) {
                return ToolResult::error('CELL_PROTECTED', 'Zelle ist geschützt (Worksheet geschützt + Zelle gesperrt)');
            }

            $rawValue = $arguments['value'];
            $formulaService = new FormulaService();
            $cellTypeKey = $formulaService->determineCellType($rawValue);
            $cellType = SheetsCellType::where('key', $cellTypeKey)->first();

            if (!$cellType) {
                $cellType = SheetsCellType::where('key', 'text')->first();
            }

            // Compute value for formulas
            $computedValue = $rawValue;
            if ($cellTypeKey === 'formula') {
                $cellValues = $this->getWorksheetCellValues($worksheet->id);
                $computedValue = (string) $formulaService->evaluate($rawValue, $cellValues);
            }

            // Upsert cell
            $cell = SheetsCell::updateOrCreate(
                ['worksheet_id' => $worksheet->id, 'row' => $row, 'col' => $col],
                [
                    'raw_value' => $rawValue,
                    'computed_value' => $computedValue,
                    'cell_type_id' => $cellType->id,
                    'format' => $arguments['format'] ?? null,
                    'is_locked' => $arguments['is_locked'] ?? false,
                    'user_id' => $context->user->id,
                ]
            );

            // Update dependencies and recalculate
            if ($cellTypeKey === 'formula') {
                $formulaService->updateDependencies($cell);

                if ($formulaService->detectCircularReferences($cell)) {
                    $cell->update(['computed_value' => '#CIRCULAR_REF']);
                    return ToolResult::success([
                        'ref' => $cell->cell_ref,
                        'warning' => 'Zirkuläre Referenz erkannt!',
                        'computed_value' => '#CIRCULAR_REF',
                    ]);
                }
            }

            $formulaService->recalculateDependents($cell);

            return ToolResult::success([
                'id' => $cell->id,
                'ref' => $cell->cell_ref,
                'raw_value' => $cell->raw_value,
                'computed_value' => $cell->computed_value,
                'cell_type' => $cellTypeKey,
                'message' => "Zelle {$cell->cell_ref} aktualisiert.",
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

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'cell', 'write'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
