<?php

namespace Platform\Sheets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SheetsFolderUser extends Model
{
    protected $table = 'sheets_folder_users';

    protected $fillable = ['folder_id', 'user_id', 'folder_role_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(SheetsFolder::class, 'folder_id');
    }

    public function folderRole(): BelongsTo
    {
        return $this->belongsTo(SheetsFolderRole::class, 'folder_role_id');
    }
}
