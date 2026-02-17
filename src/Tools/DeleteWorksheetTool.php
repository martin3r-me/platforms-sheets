<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsWorksheet;

class DeleteWorksheetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.worksheets.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /worksheets/{id} - Löscht ein Worksheet (soft delete).';
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
            $worksheet = SheetsWorksheet::find($arguments['worksheet_id']);
            if (!$worksheet) {
                return ToolResult::error('NOT_FOUND', 'Worksheet nicht gefunden');
            }

            $name = $worksheet->name;
            $worksheet->delete();

            return ToolResult::success(['message' => "Worksheet '{$name}' gelöscht."]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'worksheet', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'destructive',
            'idempotent' => false,
        ];
    }
}
