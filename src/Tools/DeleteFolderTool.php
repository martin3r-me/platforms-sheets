<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsFolder;
use Illuminate\Support\Facades\Gate;

class DeleteFolderTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.folders.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /folders/{id} - Löscht einen Ordner (soft delete).';
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
            $folder = SheetsFolder::find($arguments['folder_id']);
            if (!$folder) {
                return ToolResult::error('NOT_FOUND', 'Ordner nicht gefunden');
            }

            Gate::forUser($context->user)->authorize('delete', $folder);

            $name = $folder->name;
            $folder->delete();

            return ToolResult::success(['message' => "Ordner '{$name}' gelöscht."]);
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
            'tags' => ['sheets', 'folder', 'delete'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'destructive',
            'idempotent' => false,
        ];
    }
}
