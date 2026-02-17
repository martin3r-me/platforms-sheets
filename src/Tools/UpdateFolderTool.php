<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsFolder;
use Illuminate\Support\Facades\Gate;

class UpdateFolderTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.folders.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /folders/{id} - Aktualisiert einen Ordner. REST-Parameter: folder_id (required), name, description, parent_id.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'folder_id' => ['type' => 'integer', 'description' => 'ID des Ordners'],
                'name' => ['type' => 'string', 'description' => 'Neuer Name'],
                'description' => ['type' => 'string', 'description' => 'Neue Beschreibung'],
                'parent_id' => ['type' => 'integer', 'description' => 'Neuer Parent-Ordner'],
            ],
            'required' => ['folder_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $folder = SheetsFolder::find($arguments['folder_id']);
            if (!$folder) {
                return ToolResult::error('NOT_FOUND', 'Ordner nicht gefunden');
            }

            Gate::forUser($context->user)->authorize('update', $folder);

            $data = array_filter([
                'name' => $arguments['name'] ?? null,
                'description' => $arguments['description'] ?? null,
                'parent_id' => $arguments['parent_id'] ?? null,
            ], fn ($v) => $v !== null);

            $folder->update($data);

            return ToolResult::success([
                'id' => $folder->id,
                'name' => $folder->name,
                'message' => "Ordner '{$folder->name}' aktualisiert.",
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
            'tags' => ['sheets', 'folder', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
