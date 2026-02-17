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

class CreateFolderTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.folders.POST';
    }

    public function getDescription(): string
    {
        return 'POST /folders - Erstellt einen neuen Ordner. REST-Parameter: name (required), description (optional), parent_id (optional).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Name des Ordners (erforderlich)'],
                'description' => ['type' => 'string', 'description' => 'Beschreibung (optional)'],
                'parent_id' => ['type' => 'integer', 'description' => 'Parent-Ordner-ID für Verschachtelung (optional)'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['name'])) {
                return ToolResult::error('VALIDATION_ERROR', 'Ordnername ist erforderlich');
            }
            if (!$context->user || !$context->team) {
                return ToolResult::error('AUTH_ERROR', 'User und Team erforderlich');
            }

            $maxOrder = SheetsFolder::where('team_id', $context->team->id)
                ->where('parent_id', $arguments['parent_id'] ?? null)
                ->max('order') ?? 0;

            $folder = SheetsFolder::create([
                'name' => $arguments['name'],
                'description' => $arguments['description'] ?? null,
                'parent_id' => $arguments['parent_id'] ?? null,
                'user_id' => $context->user->id,
                'team_id' => $context->team->id,
                'order' => $maxOrder + 1,
            ]);

            // Add creator as owner
            $ownerRole = SheetsFolderRole::where('key', 'owner')->first();
            if ($ownerRole) {
                SheetsFolderUser::create([
                    'folder_id' => $folder->id,
                    'user_id' => $context->user->id,
                    'folder_role_id' => $ownerRole->id,
                ]);
            }

            return ToolResult::success([
                'id' => $folder->id,
                'uuid' => $folder->uuid,
                'name' => $folder->name,
                'message' => "Ordner '{$folder->name}' erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'folder', 'create'],
            'read_only' => false,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'write',
            'idempotent' => false,
        ];
    }
}
