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

class ImportDataTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.worksheet.importData';
    }

    public function getDescription(): string
    {
        return 'POST /worksheet/importData - Importiert tabellarische Daten zeilen-weise in ein Worksheet. '
            . 'Ideal für das Befüllen ganzer Tabellen in einem Aufruf. '
            . 'REST-Parameter: worksheet_id (required), rows (required, Array von Arrays – jede Inner-Array = eine Zeile), '
            . 'start (optional, default "A1" – Start-Referenz), '
            . 'has_header (optional, bool – erste Row als Header-Zeile behandeln und fett formatieren).';
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
                'start' => [
                    'type' => 'string',
                    'description' => 'Start-Zell-Referenz für den Import (z.B. "A1", "B3"). Default: "A1"',
                ],
                'rows' => [
                    'type' => 'array',
                    'description' => 'Array von Arrays – jede Inner-Array ist eine Zeile mit Werten',
                    'items' => [
                        'type' => 'array',
                        'description' => 'Eine Zeile mit Zellwerten',
                        'items' => [
                            'description' => 'Zellwert (String, Zahl, Boolean). Formeln mit = Prefix.',
                        ],
                    ],
                ],
                'has_header' => [
                    'type' => 'boolean',
                    'description' => 'Wenn true, wird die erste Row als Header behandelt und fett formatiert',
                ],
            ],
            'required' => ['worksheet_id', 'rows'],
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
            if (empty($arguments['rows']) || !is_array($arguments['rows'])) {
                return ToolResult::error('VALIDATION_ERROR', 'rows Array ist erforderlich');
            }

            $rows = $arguments['rows'];
            $hasHeader = $arguments['has_header'] ?? false;
            $startRef = strtoupper($arguments['start'] ?? 'A1');

            // Parse start reference
            if (!preg_match('/^([A-Z]{1,3})(\d+)$/i', $startRef, $startMatch)) {
                return ToolResult::error('VALIDATION_ERROR', 'Ungültige Start-Referenz: ' . $startRef);
            }

            $startCol = SheetsCell::letterToNumber(strtoupper($startMatch[1]));
            $startRow = (int) $startMatch[2];

            // Calculate total cells and validate limit
            $totalCells = 0;
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $totalCells += count($row);
                }
            }
            if ($totalCells > 10000) {
                return ToolResult::error('VALIDATION_ERROR', 'Maximal 10.000 Zellen pro Import (aktuell: ' . $totalCells . ')');
            }
            if ($totalCells === 0) {
                return ToolResult::error('VALIDATION_ERROR', 'Keine Daten zum Importieren');
            }

            $formulaService = new FormulaService();
            $protectionService = new CellProtectionService();
            $errors = [];
            $formulaCells = [];
            $writtenCells = 0;
            $writtenRefs = [];
            $affectedRows = [];

            foreach ($rows as $rowIndex => $rowData) {
                if (!is_array($rowData)) {
                    $errors[] = "Zeile " . ($rowIndex + 1) . " ist kein Array";
                    continue;
                }

                $currentRow = $startRow + $rowIndex;
                $affectedRows[] = $currentRow;
                $isHeaderRow = $hasHeader && $rowIndex === 0;

                foreach ($rowData as $colIndex => $value) {
                    $currentCol = $startCol + $colIndex;
                    $cellRef = SheetsCell::numberToLetter($currentCol) . $currentRow;

                    if (!$protectionService->canEditPosition($worksheet, $currentRow, $currentCol, $context->user)) {
                        $errors[] = "Zelle {$cellRef} ist geschützt";
                        continue;
                    }

                    // Convert value to string for storage
                    $rawValue = $this->normalizeValue($value);
                    $cellTypeKey = $formulaService->determineCellType($rawValue);
                    $cellType = SheetsCellType::where('key', $cellTypeKey)->first()
                        ?? SheetsCellType::where('key', 'text')->first();

                    $computedValue = $rawValue;

                    // Apply header formatting if has_header and first row
                    $format = null;
                    if ($isHeaderRow) {
                        $format = ['bold' => true];
                    }

                    $cell = SheetsCell::updateOrCreate(
                        ['worksheet_id' => $worksheet->id, 'row' => $currentRow, 'col' => $currentCol],
                        [
                            'raw_value' => $rawValue,
                            'computed_value' => $computedValue,
                            'cell_type_id' => $cellType->id,
                            'format' => $format,
                            'is_locked' => false,
                            'user_id' => $context->user->id,
                        ]
                    );

                    if ($cellTypeKey === 'formula') {
                        $formulaCells[] = $cell;
                    }

                    $writtenCells++;
                    $writtenRefs[] = $cellRef;
                }
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
            if (!empty($affectedRows)) {
                $allCells = SheetsCell::where('worksheet_id', $worksheet->id)
                    ->whereIn('row', $affectedRows)
                    ->get();
                foreach ($allCells as $cell) {
                    $formulaService->recalculateDependents($cell);
                }
            }

            // Determine the range that was written
            $endRow = $startRow + count($rows) - 1;
            $maxCols = max(array_map(fn($r) => is_array($r) ? count($r) : 0, $rows));
            $endCol = $startCol + $maxCols - 1;
            $range = $startRef . ':' . SheetsCell::numberToLetter($endCol) . $endRow;

            return ToolResult::success([
                'imported' => $writtenCells,
                'rows' => count($rows),
                'columns' => $maxCols,
                'range' => $range,
                'errors' => $errors,
                'message' => $writtenCells . ' Zelle(n) in ' . count($rows) . ' Zeile(n) importiert (Bereich: ' . $range . ').'
                    . ($hasHeader ? ' Erste Zeile als Header formatiert.' : '')
                    . (count($errors) > 0 ? ' ' . count($errors) . ' Fehler.' : ''),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    protected function normalizeValue(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        return (string) $value;
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
            'tags' => ['sheets', 'cell', 'import', 'bulk', 'write'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
