<?php

namespace Platform\Sheets\Models;

use Illuminate\Database\Eloquent\Model;

class SheetsFolderRole extends Model
{
    protected $table = 'sheets_folder_roles';

    protected $fillable = ['key', 'label', 'level'];

    protected $casts = [
        'level' => 'integer',
    ];
}
