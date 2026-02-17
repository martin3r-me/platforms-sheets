<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Sheets\Models\SheetsSpreadsheet;

class ListSpreadsheetsTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'sheets.spreadsheets.GET';
    }

    public function getDescription(): string
    {
        return 'GET /spreadsheets - Listet Spreadsheets auf. REST-Parameter: folder_id (optional), filters, search, sort, limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->mergeSchemas(
            $this->getStandardGetSchema(),
            [
                'properties' => [
                    'folder_id' => ['type' => 'integer', 'description' => 'Optional: Filter nach Ordner-ID. Null = alle.'],
                ],
            ]
        );
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user || !$context->team) {
                return ToolResult::error('AUTH_ERROR', 'User und Team erforderlich');
            }

            $query = SheetsSpreadsheet::where('team_id', $context->team->id)
                ->with(['folder', 'worksheets']);

            if (isset($arguments['folder_id'])) {
                $query->where('folder_id', $arguments['folder_id']);
            }

            $this->applyStandardFilters($query, $arguments, ['name', 'folder_id', 'created_at']);
            $this->applyStandardSearch($query, $arguments, ['name', 'description']);
            $this->applyStandardSort($query, $arguments, ['name', 'created_at', 'updated_at'], 'name', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $spreadsheets = $query->get()->map(function ($s) {
                return [
                    'id' => $s->id,
                    'uuid' => $s->uuid,
                    'name' => $s->name,
                    'description' => $s->description,
                    'folder' => $s->folder ? ['id' => $s->folder->id, 'name' => $s->folder->name] : null,
                    'worksheets_count' => $s->worksheets->count(),
                    'created_at' => $s->created_at->toIso8601String(),
                ];
            })->toArray();

            return ToolResult::success([
                'spreadsheets' => $spreadsheets,
                'count' => count($spreadsheets),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['sheets', 'spreadsheet', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
