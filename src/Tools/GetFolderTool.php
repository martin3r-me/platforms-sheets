<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsFolder;

class GetFolderTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.folder.GET';
    }

    public function getDescription(): string
    {
        return 'GET /folder/{id} - Zeigt einen einzelnen Ordner mit Inhalt (Unterordner + Spreadsheets).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'folder_id' => ['type' => 'integer', 'description' => 'ID des Ordners'],
            ],
            'required' => ['folder_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $folder = SheetsFolder::with(['children', 'spreadsheets', 'folderUsers.user', 'folderUsers.folderRole', 'parent'])
                ->find($arguments['folder_id']);

            if (!$folder) {
                return ToolResult::error('NOT_FOUND', 'Ordner nicht gefunden');
            }

            return ToolResult::success([
                'id' => $folder->id,
                'uuid' => $folder->uuid,
                'name' => $folder->name,
                'description' => $folder->description,
                'parent' => $folder->parent ? ['id' => $folder->parent->id, 'name' => $folder->parent->name] : null,
                'children' => $folder->children->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->toArray(),
                'spreadsheets' => $folder->spreadsheets->map(fn ($s) => [
                    'id' => $s->id, 'uuid' => $s->uuid, 'name' => $s->name,
                ])->toArray(),
                'members' => $folder->folderUsers->map(fn ($fu) => [
                    'user_id' => $fu->user_id,
                    'user_name' => $fu->user->name ?? 'Unknown',
                    'role' => $fu->folderRole->key ?? 'unknown',
                ])->toArray(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'query',
            'tags' => ['sheets', 'folder', 'detail'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
