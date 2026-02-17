<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Core\Tools\Concerns\HasStandardGetOperations;
use Platform\Sheets\Models\SheetsFolder;

class ListFoldersTool implements ToolContract, ToolMetadataContract
{
    use HasStandardGetOperations;

    public function getName(): string
    {
        return 'sheets.folders.GET';
    }

    public function getDescription(): string
    {
        return 'GET /folders - Listet Ordner auf. REST-Parameter: filters, search, sort, limit/offset.';
    }

    public function getSchema(): array
    {
        return $this->getStandardGetSchema();
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (!$context->user || !$context->team) {
                return ToolResult::error('AUTH_ERROR', 'User und Team erforderlich');
            }

            $query = SheetsFolder::where('team_id', $context->team->id)
                ->with(['user', 'children', 'spreadsheets']);

            $this->applyStandardFilters($query, $arguments, ['name', 'parent_id', 'created_at']);
            $this->applyStandardSearch($query, $arguments, ['name', 'description']);
            $this->applyStandardSort($query, $arguments, ['name', 'order', 'created_at'], 'order', 'asc');
            $this->applyStandardPagination($query, $arguments);

            $folders = $query->get()->map(function ($folder) {
                return [
                    'id' => $folder->id,
                    'uuid' => $folder->uuid,
                    'name' => $folder->name,
                    'description' => $folder->description,
                    'parent_id' => $folder->parent_id,
                    'children_count' => $folder->children->count(),
                    'spreadsheets_count' => $folder->spreadsheets->count(),
                    'created_at' => $folder->created_at->toIso8601String(),
                ];
            })->toArray();

            return ToolResult::success([
                'folders' => $folders,
                'count' => count($folders),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['sheets', 'folder', 'list'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
