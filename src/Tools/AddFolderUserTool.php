<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsFolder;
use Platform\Sheets\Models\SheetsFolderUser;
use Platform\Sheets\Models\SheetsFolderRole;
use Illuminate\Support\Facades\Gate;

class AddFolderUserTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.folder_users.POST';
    }

    public function getDescription(): string
    {
        return 'POST /folder_users - Fügt einen User zu einem Ordner hinzu. REST-Parameter: folder_id (required), user_id (required), role (optional: owner/admin/member/viewer, default: viewer).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'folder_id' => ['type' => 'integer', 'description' => 'ID des Ordners'],
                'user_id' => ['type' => 'integer', 'description' => 'ID des Users'],
                'role' => ['type' => 'string', 'description' => 'Rolle: owner, admin, member, viewer', 'enum' => ['owner', 'admin', 'member', 'viewer']],
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

            Gate::forUser($context->user)->authorize('invite', $folder);

            $roleKey = $arguments['role'] ?? 'viewer';
            $role = SheetsFolderRole::where('key', $roleKey)->first();
            if (!$role) {
                return ToolResult::error('VALIDATION_ERROR', "Rolle '{$roleKey}' nicht gefunden");
            }

            $existing = SheetsFolderUser::where('folder_id', $folder->id)
                ->where('user_id', $arguments['user_id'])
                ->first();

            if ($existing) {
                $existing->update(['folder_role_id' => $role->id]);
                return ToolResult::success(['message' => 'User-Rolle aktualisiert.']);
            }

            SheetsFolderUser::create([
                'folder_id' => $folder->id,
                'user_id' => $arguments['user_id'],
                'folder_role_id' => $role->id,
            ]);

            return ToolResult::success(['message' => 'User zum Ordner hinzugefügt.']);
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
            'tags' => ['sheets', 'folder', 'user', 'invite'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
