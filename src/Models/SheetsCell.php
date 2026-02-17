<?php

namespace Platform\Sheets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SheetsCell extends Model
{
    protected $table = 'sheets_cells';

    protected $fillable = [
        'worksheet_id', 'row', 'col',
        'raw_value', 'computed_value',
        'cell_type_id', 'format', 'is_locked', 'user_id',
    ];

    protected $casts = [
        'format' => 'array',
        'is_locked' => 'boolean',
        'row' => 'integer',
        'col' => 'integer',
    ];

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(SheetsWorksheet::class, 'worksheet_id');
    }

    public function cellType(): BelongsTo
    {
        return $this->belongsTo(SheetsCellType::class, 'cell_type_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    public function dependencies(): HasMany
    {
        return $this->hasMany(SheetsCellDependency::class, 'cell_id');
    }

    public function dependents(): HasMany
    {
        return $this->hasMany(SheetsCellDependency::class, 'depends_on_cell_id');
    }

    /**
     * Convert column number to letter (1=A, 2=B, ... 26=Z, 27=AA)
     */
    public function getColLetterAttribute(): string
    {
        return self::numberToLetter($this->col);
    }

    /**
     * Get cell reference like "A1", "B2"
     */
    public function getCellRefAttribute(): string
    {
        return $this->col_letter . $this->row;
    }

    public static function numberToLetter(int $col): string
    {
        $letter = '';
        while ($col > 0) {
            $col--;
            $letter = chr(65 + ($col % 26)) . $letter;
            $col = intdiv($col, 26);
        }
        return $letter;
    }

    public static function letterToNumber(string $letter): int
    {
        $letter = strtoupper($letter);
        $number = 0;
        for ($i = 0; $i < strlen($letter); $i++) {
            $number = $number * 26 + (ord($letter[$i]) - 64);
        }
        return $number;
    }
}
