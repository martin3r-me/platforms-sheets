<?php

namespace Platform\Sheets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class SheetsWorksheet extends Model
{
    use SoftDeletes;

    protected $table = 'sheets_worksheets';

    protected $fillable = [
        'uuid', 'name', 'order',
        'spreadsheet_id', 'user_id', 'team_id',
        'row_count', 'col_count', 'is_protected',
        'column_widths', 'row_heights', 'frozen_rows', 'frozen_cols',
    ];

    protected $casts = [
        'uuid' => 'string',
        'is_protected' => 'boolean',
        'row_count' => 'integer',
        'col_count' => 'integer',
        'column_widths' => 'array',
        'row_heights' => 'array',
        'frozen_rows' => 'integer',
        'frozen_cols' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            do {
                $uuid = UuidV7::generate();
            } while (self::where('uuid', $uuid)->exists());
            $model->uuid = $uuid;
        });
    }

    public function spreadsheet(): BelongsTo
    {
        return $this->belongsTo(SheetsSpreadsheet::class, 'spreadsheet_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(SheetsCell::class, 'worksheet_id');
    }
}
