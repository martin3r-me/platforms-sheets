<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsCell;
use Platform\Sheets\Models\SheetsWorksheet;

class GetCellsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.cells.GET';
    }

    public function getDescription(): string
    {
        return 'GET /cells - Liest Zellen eines Worksheets. REST-Parameter: worksheet_id (required), range (optional, z.B. "A1:C10" - wenn nicht angegeben, werden alle belegten Zellen zurückgegeben). limit (optional, default 500).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'worksheet_id' => ['type' => 'integer', 'description' => 'ID des Worksheets'],
                'range' => ['type' => 'string', 'description' => 'Zell-Bereich, z.B. "A1:C10", "A1", "A:C" (ganze Spalten). Wenn leer, alle belegten Zellen.'],
                'limit' => ['type' => 'integer', 'description' => 'Max. Anzahl Zellen (Standard: 500, Max: 5000)'],
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

            $query = SheetsCell::where('worksheet_id', $worksheet->id)
                ->with('cellType')
                ->orderBy('row')
                ->orderBy('col');

            // Parse range if provided
            if (!empty($arguments['range'])) {
                $this->applyRange($query, $arguments['range']);
            }

            $limit = min($arguments['limit'] ?? 500, 5000);
            $cells = $query->limit($limit)->get();

            $cellData = $cells->map(function ($cell) {
                return [
                    'id' => $cell->id,
                    'ref' => $cell->cell_ref,
                    'row' => $cell->row,
                    'col' => $cell->col,
                    'col_letter' => $cell->col_letter,
                    'raw_value' => $cell->raw_value,
                    'computed_value' => $cell->computed_value,
                    'cell_type' => $cell->cellType->key ?? 'unknown',
                    'format' => $cell->format,
                    'is_locked' => $cell->is_locked,
                ];
            })->toArray();

            return ToolResult::success([
                'worksheet_id' => $worksheet->id,
                'worksheet_name' => $worksheet->name,
                'is_protected' => $worksheet->is_protected,
                'cells' => $cellData,
                'count' => count($cellData),
                'limit' => $limit,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    protected function applyRange($query, string $range): void
    {
        // Single cell: A1
        if (preg_match('/^([A-Z]{1,3})(\d+)$/i', $range, $m)) {
            $col = SheetsCell::letterToNumber(strtoupper($m[1]));
            $row = (int) $m[2];
            $query->where('row', $row)->where('col', $col);
            return;
        }

        // Range: A1:C10
        if (preg_match('/^([A-Z]{1,3})(\d+):([A-Z]{1,3})(\d+)$/i', $range, $m)) {
            $startCol = SheetsCell::letterToNumber(strtoupper($m[1]));
            $startRow = (int) $m[2];
            $endCol = SheetsCell::letterToNumber(strtoupper($m[3]));
            $endRow = (int) $m[4];

            $query->whereBetween('row', [$startRow, $endRow])
                  ->whereBetween('col', [$startCol, $endCol]);
            return;
        }

        // Column range: A:C
        if (preg_match('/^([A-Z]{1,3}):([A-Z]{1,3})$/i', $range, $m)) {
            $startCol = SheetsCell::letterToNumber(strtoupper($m[1]));
            $endCol = SheetsCell::letterToNumber(strtoupper($m[2]));
            $query->whereBetween('col', [$startCol, $endCol]);
            return;
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['sheets', 'cell', 'read'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
