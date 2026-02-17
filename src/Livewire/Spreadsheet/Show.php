<?php

namespace Platform\Sheets\Livewire\Spreadsheet;

use Livewire\Component;
use Platform\Sheets\Models\SheetsSpreadsheet;
use Platform\Sheets\Models\SheetsWorksheet;
use Platform\Sheets\Models\SheetsCell;

class Show extends Component
{
    public SheetsSpreadsheet $spreadsheet;
    public ?int $activeWorksheetId = null;

    public function mount(SheetsSpreadsheet $spreadsheet)
    {
        $this->spreadsheet = $spreadsheet;
        $firstWorksheet = $spreadsheet->worksheets()->orderBy('order')->first();
        $this->activeWorksheetId = $firstWorksheet?->id;
    }

    public function selectWorksheet(int $id)
    {
        $this->activeWorksheetId = $id;
    }

    public function render()
    {
        $worksheets = $this->spreadsheet->worksheets()->orderBy('order')->get();
        $activeWorksheet = $this->activeWorksheetId
            ? SheetsWorksheet::find($this->activeWorksheetId)
            : $worksheets->first();

        $cells = collect();
        $maxRow = 20;
        $maxCol = 10;

        if ($activeWorksheet) {
            $cells = SheetsCell::where('worksheet_id', $activeWorksheet->id)
                ->orderBy('row')
                ->orderBy('col')
                ->limit(5000)
                ->get()
                ->keyBy(fn ($cell) => $cell->row . ':' . $cell->col);

            $maxRow = max($maxRow, $cells->max('row') ?? 20);
            $maxCol = max($maxCol, $cells->max('col') ?? 10);
            $maxRow = min($maxRow, 100); // Cap display
            $maxCol = min($maxCol, 26);
        }

        return view('sheets::livewire.spreadsheet.show', [
            'worksheets' => $worksheets,
            'activeWorksheet' => $activeWorksheet,
            'cells' => $cells,
            'maxRow' => $maxRow,
            'maxCol' => $maxCol,
        ])->layout('platform::layouts.app');
    }
}
