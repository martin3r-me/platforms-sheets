<?php

namespace Platform\Sheets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class SheetsFolder extends Model
{
    use SoftDeletes;

    protected $table = 'sheets_folders';

    protected $fillable = [
        'uuid', 'name', 'description', 'order',
        'parent_id', 'user_id', 'team_id',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(SheetsFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(SheetsFolder::class, 'parent_id')->orderBy('order');
    }

    public function spreadsheets(): HasMany
    {
        return $this->hasMany(SheetsSpreadsheet::class, 'folder_id')->orderBy('name');
    }

    public function folderUsers(): HasMany
    {
        return $this->hasMany(SheetsFolderUser::class, 'folder_id');
    }

    public function getEffectiveRoleForUser($userId): ?string
    {
        // 1. Direct permission
        $folderUser = $this->folderUsers()->where('user_id', $userId)->first();
        if ($folderUser && $folderUser->folderRole) {
            return $folderUser->folderRole->key;
        }

        // 2. Owner always has access
        if ($this->user_id === $userId) {
            return 'owner';
        }

        // 3. Inherit from parent
        if ($this->parent_id) {
            $parent = $this->parent;
            if ($parent) {
                return $parent->getEffectiveRoleForUser($userId);
            }
        }

        return null;
    }
}
