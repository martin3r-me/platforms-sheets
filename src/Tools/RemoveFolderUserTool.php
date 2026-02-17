<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsFolder;
use Platform\Sheets\Models\SheetsFolderUser;
use Illuminate\Support\Facades\Gate;

class RemoveFolderUserTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.folder_users.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /folder_users - Entfernt einen User aus einem Ordner. REST-Parameter: folder_id (required), user_id (required).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'folder_id' => ['type' => 'integer', 'description' => 'ID des Ordners'],
                'user_id' => ['type' => 'integer', 'description' => 'ID des Users'],
            ],
            'required' => ['folder_id', 'user_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $folder = SheetsFolder::find($arguments['folder_id']);
            if (!$folder) {
                return ToolResult::error('NOT_FOUND', 'Ordner nicht gefunden');
            }

            Gate::forUser($context->user)->authorize('removeMember', $folder);

            $deleted = SheetsFolderUser::where('folder_id', $folder->id)
                ->where('user_id', $arguments['user_id'])
                ->delete();

            return ToolResult::success([
                'message' => $deleted ? 'User aus Ordner entfernt.' : 'User war nicht im Ordner.',
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
            'tags' => ['sheets', 'folder', 'user', 'remove'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
