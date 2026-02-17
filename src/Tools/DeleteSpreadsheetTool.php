<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsSpreadsheet;
use Illuminate\Support\Facades\Gate;

class DeleteSpreadsheetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.spreadsheets.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /spreadsheets/{id} - Löscht ein Spreadsheet (soft delete).';
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
            $spreadsheet = SheetsSpreadsheet::find($arguments['spreadsheet_id']);
            if (!$spreadsheet) {
                return ToolResult::error('NOT_FOUND', 'Spreadsheet nicht gefunden');
            }

            Gate::forUser($context->user)->authorize('delete', $spreadsheet);

            $name = $spreadsheet->name;
            $spreadsheet->delete();

            return ToolResult::success(['message' => "Spreadsheet '{$name}' gelöscht."]);
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
            'tags' => ['sheets', 'spreadsheet', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'destructive',
            'idempotent' => false,
        ];
    }
}
