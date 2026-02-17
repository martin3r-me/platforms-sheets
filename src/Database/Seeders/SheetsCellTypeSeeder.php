<?php

namespace Platform\Sheets\Database\Seeders;

use Platform\Sheets\Models\SheetsCellType;

class SheetsCellTypeSeeder
{
    public static function seedIfEmpty(): void
    {
        if (SheetsCellType::count() > 0) {
            return;
        }

        $types = [
            ['key' => 'text', 'label' => 'Text'],
            ['key' => 'number', 'label' => 'Number'],
            ['key' => 'date', 'label' => 'Date'],
            ['key' => 'boolean', 'label' => 'Boolean'],
            ['key' => 'formula', 'label' => 'Formula'],
            ['key' => 'empty', 'label' => 'Empty'],
        ];

        foreach ($types as $type) {
            SheetsCellType::create($type);
        }
    }
}
