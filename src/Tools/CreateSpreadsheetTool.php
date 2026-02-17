<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsSpreadsheet;
use Platform\Sheets\Models\SheetsWorksheet;

class CreateSpreadsheetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.spreadsheets.POST';
    }

    public function getDescription(): string
    {
        return 'POST /spreadsheets - Erstellt ein neues Spreadsheet (mit einem leeren Worksheet "Sheet 1"). REST-Parameter: name (required), description (optional), folder_id (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Name des Spreadsheets (erforderlich)'],
                'description' => ['type' => 'string', 'description' => 'Beschreibung (optional)'],
                'folder_id' => ['type' => 'integer', 'description' => 'Ordner-ID (optional)'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['name'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Name ist erforderlich');
            }
            if (!$context->user || !$context->team) {
                return ToolResult::error('AUTH_ERROR', 'User und Team erforderlich');
            }

            $spreadsheet = SheetsSpreadsheet::create([
                'name' => $arguments['name'],
                'description' => $arguments['description'] ?? null,
                'folder_id' => $arguments['folder_id'] ?? null,
                'user_id' => $context->user->id,
                'team_id' => $context->team->id,
            ]);

            // Auto-create first worksheet
            $worksheet = SheetsWorksheet::create([
                'name' => 'Sheet 1',
                'order' => 1,
                'spreadsheet_id' => $spreadsheet->id,
                'user_id' => $context->user->id,
                'team_id' => $context->team->id,
            ]);

            return ToolResult::success([
                'id' => $spreadsheet->id,
                'uuid' => $spreadsheet->uuid,
                'name' => $spreadsheet->name,
                'worksheet' => [
                    'id' => $worksheet->id,
                    'uuid' => $worksheet->uuid,
                    'name' => $worksheet->name,
                ],
                'message' => "Spreadsheet '{$spreadsheet->name}' mit Worksheet 'Sheet 1' erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'spreadsheet', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
