<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsWorksheet;

class GetWorksheetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.worksheet.GET';
    }

    public function getDescription(): string
    {
        return 'GET /worksheet/{id} - Zeigt ein Worksheet mit Zell-Statistik und Schutz-Status.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'worksheet_id' => ['type' => 'integer', 'description' => 'ID des Worksheets'],
            ],
            'required' => ['worksheet_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $worksheet = SheetsWorksheet::with('spreadsheet')->find($arguments['worksheet_id']);
            if (!$worksheet) {
                return ToolResult::error('NOT_FOUND', 'Worksheet nicht gefunden');
            }

            $cellStats = [
                'total' => $worksheet->cells()->count(),
                'formulas' => $worksheet->cells()->whereHas('cellType', fn ($q) => $q->where('key', 'formula'))->count(),
                'locked' => $worksheet->cells()->where('is_locked', true)->count(),
            ];

            return ToolResult::success([
                'id' => $worksheet->id,
                'uuid' => $worksheet->uuid,
                'name' => $worksheet->name,
                'order' => $worksheet->order,
                'spreadsheet' => [
                    'id' => $worksheet->spreadsheet->id,
                    'name' => $worksheet->spreadsheet->name,
                ],
                'row_count' => $worksheet->row_count,
                'col_count' => $worksheet->col_count,
                'is_protected' => $worksheet->is_protected,
                'cell_stats' => $cellStats,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['sheets', 'worksheet', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
