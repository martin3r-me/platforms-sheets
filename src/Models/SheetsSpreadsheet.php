<?php

namespace Platform\Sheets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class SheetsSpreadsheet extends Model
{
    use SoftDeletes;

    protected $table = 'sheets_spreadsheets';

    protected $fillable = [
        'uuid', 'name', 'description',
        'folder_id', 'user_id', 'team_id',
    ];

    protected $casts = [
        'uuid' => 'string',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(SheetsFolder::class, 'folder_id');
    }

    public function worksheets(): HasMany
    {
        return $this->hasMany(SheetsWorksheet::class, 'spreadsheet_id')->orderBy('order');
    }
}
