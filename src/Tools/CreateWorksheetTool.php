<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsSpreadsheet;
use Platform\Sheets\Models\SheetsWorksheet;

class CreateWorksheetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.worksheets.POST';
    }

    public function getDescription(): string
    {
        return 'POST /worksheets - Erstellt ein neues Worksheet in einem Spreadsheet. REST-Parameter: spreadsheet_id (required), name (optional, default: "Sheet N"), row_count (optional, default: 1000), col_count (optional, default: 26).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'spreadsheet_id' => ['type' => 'integer', 'description' => 'ID des Spreadsheets'],
                'name' => ['type' => 'string', 'description' => 'Name des Worksheets (optional)'],
                'row_count' => ['type' => 'integer', 'description' => 'Anzahl Zeilen (Standard: 1000)'],
                'col_count' => ['type' => 'integer', 'description' => 'Anzahl Spalten (Standard: 26)'],
            ],
            'required' => ['spreadsheet_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $spreadsheet = SheetsSpreadsheet::find($arguments['spreadsheet_id']);
            if (!$spreadsheet) {
                return ToolResult::error('NOT_FOUND', 'Spreadsheet nicht gefunden');
            }
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'User erforderlich');
            }

            $maxOrder = SheetsWorksheet::where('spreadsheet_id', $spreadsheet->id)->max('order') ?? 0;
            $count = SheetsWorksheet::where('spreadsheet_id', $spreadsheet->id)->count();

            $worksheet = SheetsWorksheet::create([
                'name' => $arguments['name'] ?? ('Sheet ' . ($count + 1)),
                'order' => $maxOrder + 1,
                'spreadsheet_id' => $spreadsheet->id,
                'user_id' => $context->user->id,
                'team_id' => $spreadsheet->team_id,
                'row_count' => $arguments['row_count'] ?? 1000,
                'col_count' => $arguments['col_count'] ?? 26,
            ]);

            return ToolResult::success([
                'id' => $worksheet->id,
                'uuid' => $worksheet->uuid,
                'name' => $worksheet->name,
                'message' => "Worksheet '{$worksheet->name}' erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'worksheet', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
