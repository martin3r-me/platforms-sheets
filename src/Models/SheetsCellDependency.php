<?php

namespace Platform\Sheets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SheetsCellDependency extends Model
{
    protected $table = 'sheets_cell_dependencies';

    protected $fillable = ['cell_id', 'depends_on_cell_id'];

    public function cell(): BelongsTo
    {
        return $this->belongsTo(SheetsCell::class, 'cell_id');
    }

    public function dependsOnCell(): BelongsTo
    {
        return $this->belongsTo(SheetsCell::class, 'depends_on_cell_id');
    }
}
