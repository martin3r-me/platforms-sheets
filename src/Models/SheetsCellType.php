<?php

namespace Platform\Sheets\Models;

use Illuminate\Database\Eloquent\Model;

class SheetsCellType extends Model
{
    protected $table = 'sheets_cell_types';

    protected $fillable = ['key', 'label'];
}
