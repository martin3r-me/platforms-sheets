<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsCell;
use Platform\Sheets\Models\SheetsWorksheet;

class GetCellsRangeTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.cells.range.GET';
    }

    public function getDescription(): string
    {
        return 'GET /cells/range - Liest einen Zellbereich als Row-basiertes 2D-Array. Ideal für gezielte Datenabfragen und Validierung nach Bulk-Writes. Parameter: worksheet_id (required), range (required, z.B. "A1:J60", "B2:D10"), include_formulas (optional, bool, default false – gibt Rohe Formeln statt berechneter Werte zurück).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'worksheet_id' => ['type' => 'integer', 'description' => 'ID des Worksheets'],
                'range' => ['type' => 'string', 'description' => 'Zell-Bereich, z.B. "A1:J60", "B2:D10"'],
                'include_formulas' => ['type' => 'boolean', 'description' => 'Rohe Formeln statt berechneter Werte zurückgeben (Standard: false)'],
            ],
            'required' => ['worksheet_id', 'range'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $worksheet = SheetsWorksheet::find($arguments['worksheet_id']);
            if (!$worksheet) {
                return ToolResult::error('NOT_FOUND', 'Worksheet nicht gefunden');
            }

            $range = trim($arguments['range']);
            $includeFormulas = $arguments['include_formulas'] ?? false;

            // Parse range – only rectangular ranges (e.g. "A1:J60") are supported
            if (!preg_match('/^([A-Z]{1,3})(\d+):([A-Z]{1,3})(\d+)$/i', $range, $m)) {
                return ToolResult::error('INVALID_RANGE', 'Ungültiger Bereich. Erwartet: z.B. "A1:J60", "B2:D10"');
            }

            $startCol = SheetsCell::letterToNumber(strtoupper($m[1]));
            $startRow = (int) $m[2];
            $endCol = SheetsCell::letterToNumber(strtoupper($m[3]));
            $endRow = (int) $m[4];

            // Validate ordering
            if ($startRow > $endRow || $startCol > $endCol) {
                return ToolResult::error('INVALID_RANGE', 'Start muss vor End liegen (z.B. "A1:C10", nicht "C10:A1")');
            }

            // Safety limit: max 10000 cells
            $totalCells = ($endRow - $startRow + 1) * ($endCol - $startCol + 1);
            if ($totalCells > 10000) {
                return ToolResult::error('RANGE_TOO_LARGE', "Bereich zu groß: {$totalCells} Zellen (max. 10.000). Bitte kleineren Bereich wählen.");
            }

            // Fetch cells in range
            $cells = SheetsCell::where('worksheet_id', $worksheet->id)
                ->whereBetween('row', [$startRow, $endRow])
                ->whereBetween('col', [$startCol, $endCol])
                ->get()
                ->keyBy(fn ($cell) => $cell->row . ':' . $cell->col);

            // Build row-based 2D array
            $rows = [];
            for ($r = $startRow; $r <= $endRow; $r++) {
                $row = [];
                for ($c = $startCol; $c <= $endCol; $c++) {
                    $cell = $cells->get($r . ':' . $c);
                    if ($cell) {
                        if ($includeFormulas && $cell->raw_value !== null && str_starts_with($cell->raw_value, '=')) {
                            $row[] = $cell->raw_value;
                        } else {
                            $row[] = $cell->computed_value ?? $cell->raw_value;
                        }
                    } else {
                        $row[] = null;
                    }
                }
                $rows[] = $row;
            }

            // Normalize range string for response
            $normalizedRange = strtoupper($m[1]) . $m[2] . ':' . strtoupper($m[3]) . $m[4];

            return ToolResult::success([
                'range' => $normalizedRange,
                'rows' => $rows,
                'total_cells' => $totalCells,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['sheets', 'cell', 'range', 'read'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
