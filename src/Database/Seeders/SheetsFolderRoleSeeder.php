<?php

namespace Platform\Sheets\Database\Seeders;

use Platform\Sheets\Models\SheetsFolderRole;

class SheetsFolderRoleSeeder
{
    public static function seedIfEmpty(): void
    {
        if (SheetsFolderRole::count() > 0) {
            return;
        }

        $roles = [
            ['key' => 'owner', 'label' => 'Owner', 'level' => 4],
            ['key' => 'admin', 'label' => 'Admin', 'level' => 3],
            ['key' => 'member', 'label' => 'Member', 'level' => 2],
            ['key' => 'viewer', 'label' => 'Viewer', 'level' => 1],
        ];

        foreach ($roles as $role) {
            SheetsFolderRole::create($role);
        }
    }
}
