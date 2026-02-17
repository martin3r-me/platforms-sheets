<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsWorksheet;

class ListWorksheetsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.worksheets.GET';
    }

    public function getDescription(): string
    {
        return 'GET /worksheets - Listet Worksheets eines Spreadsheets auf. REST-Parameter: spreadsheet_id (required).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'spreadsheet_id' => ['type' => 'integer', 'description' => 'ID des Spreadsheets'],
            ],
            'required' => ['spreadsheet_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $worksheets = SheetsWorksheet::where('spreadsheet_id', $arguments['spreadsheet_id'])
                ->orderBy('order')
                ->get()
                ->map(function ($ws) {
                    return [
                        'id' => $ws->id,
                        'uuid' => $ws->uuid,
                        'name' => $ws->name,
                        'order' => $ws->order,
                        'row_count' => $ws->row_count,
                        'col_count' => $ws->col_count,
                        'is_protected' => $ws->is_protected,
                        'cells_used' => $ws->cells()->count(),
                    ];
                })->toArray();

            return ToolResult::success([
                'worksheets' => $worksheets,
                'count' => count($worksheets),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['sheets', 'worksheet', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
