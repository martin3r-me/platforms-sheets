<?php

namespace Platform\Sheets\Services;

use Platform\Sheets\Models\SheetsCell;
use Platform\Sheets\Models\SheetsWorksheet;

class CellProtectionService
{
    /**
     * Check if a cell can be edited.
     * Cell is protected only if worksheet.is_protected=true AND cell.is_locked=true
     */
    public function canEditCell(SheetsCell $cell, $user): bool
    {
        $worksheet = $cell->worksheet;

        if (!$worksheet->is_protected) {
            return true;
        }

        if (!$cell->is_locked) {
            return true;
        }

        // Owner can always edit
        if ($worksheet->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Check if a cell at a given position can be edited (cell may not exist yet).
     */
    public function canEditPosition(SheetsWorksheet $worksheet, int $row, int $col, $user): bool
    {
        if (!$worksheet->is_protected) {
            return true;
        }

        $cell = SheetsCell::where('worksheet_id', $worksheet->id)
            ->where('row', $row)
            ->where('col', $col)
            ->first();

        if (!$cell) {
            return true; // New cells are not locked by default
        }

        return $this->canEditCell($cell, $user);
    }

    public function protectWorksheet(SheetsWorksheet $worksheet): void
    {
        $worksheet->update(['is_protected' => true]);
    }

    public function unprotectWorksheet(SheetsWorksheet $worksheet): void
    {
        $worksheet->update(['is_protected' => false]);
    }

    public function lockCells(array $cellIds): void
    {
        SheetsCell::whereIn('id', $cellIds)->update(['is_locked' => true]);
    }

    public function unlockCells(array $cellIds): void
    {
        SheetsCell::whereIn('id', $cellIds)->update(['is_locked' => false]);
    }
}
