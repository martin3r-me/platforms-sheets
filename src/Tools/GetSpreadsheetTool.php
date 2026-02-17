<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsSpreadsheet;

class GetSpreadsheetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.spreadsheet.GET';
    }

    public function getDescription(): string
    {
        return 'GET /spreadsheet/{id} - Zeigt ein Spreadsheet mit allen Worksheets.';
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
            $spreadsheet = SheetsSpreadsheet::with(['worksheets', 'folder', 'user'])
                ->find($arguments['spreadsheet_id']);

            if (!$spreadsheet) {
                return ToolResult::error('NOT_FOUND', 'Spreadsheet nicht gefunden');
            }

            return ToolResult::success([
                'id' => $spreadsheet->id,
                'uuid' => $spreadsheet->uuid,
                'name' => $spreadsheet->name,
                'description' => $spreadsheet->description,
                'folder' => $spreadsheet->folder ? ['id' => $spreadsheet->folder->id, 'name' => $spreadsheet->folder->name] : null,
                'owner' => $spreadsheet->user ? ['id' => $spreadsheet->user->id, 'name' => $spreadsheet->user->name] : null,
                'worksheets' => $spreadsheet->worksheets->map(function ($ws) {
                    $cellCount = $ws->cells()->count();
                    return [
                        'id' => $ws->id,
                        'uuid' => $ws->uuid,
                        'name' => $ws->name,
                        'order' => $ws->order,
                        'row_count' => $ws->row_count,
                        'col_count' => $ws->col_count,
                        'is_protected' => $ws->is_protected,
                        'cells_used' => $cellCount,
                    ];
                })->toArray(),
                'created_at' => $spreadsheet->created_at->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['sheets', 'spreadsheet', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
