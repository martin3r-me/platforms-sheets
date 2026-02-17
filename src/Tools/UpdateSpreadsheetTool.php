<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsSpreadsheet;
use Illuminate\Support\Facades\Gate;

class UpdateSpreadsheetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.spreadsheets.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /spreadsheets/{id} - Aktualisiert ein Spreadsheet. REST-Parameter: spreadsheet_id (required), name, description, folder_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'spreadsheet_id' => ['type' => 'integer', 'description' => 'ID des Spreadsheets'],
                'name' => ['type' => 'string', 'description' => 'Neuer Name'],
                'description' => ['type' => 'string', 'description' => 'Neue Beschreibung'],
                'folder_id' => ['type' => 'integer', 'description' => 'Neuer Ordner (null = kein Ordner)'],
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

            Gate::forUser($context->user)->authorize('update', $spreadsheet);

            $data = [];
            if (isset($arguments['name'])) $data['name'] = $arguments['name'];
            if (isset($arguments['description'])) $data['description'] = $arguments['description'];
            if (array_key_exists('folder_id', $arguments)) $data['folder_id'] = $arguments['folder_id'];

            $spreadsheet->update($data);

            return ToolResult::success([
                'id' => $spreadsheet->id,
                'name' => $spreadsheet->name,
                'message' => "Spreadsheet '{$spreadsheet->name}' aktualisiert.",
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return ToolResult::error('ACCESS_DENIED', 'Keine Berechtigung');
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'spreadsheet', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
