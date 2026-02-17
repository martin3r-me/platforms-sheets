<?php

namespace Platform\Sheets\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Platform\Sheets\Models\SheetsFolder;
use Platform\Sheets\Models\SheetsSpreadsheet;

class Dashboard extends Component
{
    public function rendered()
    {
        $this->dispatch('comms', [
            'model' => null,
            'modelId' => null,
            'subject' => 'Sheets Dashboard',
            'description' => 'Spreadsheet-Übersicht',
            'url' => route('sheets.dashboard'),
            'source' => 'sheets.dashboard',
            'recipients' => [],
            'meta' => ['view_type' => 'dashboard'],
        ]);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $folders = collect();
        $spreadsheets = collect();
        $stats = ['folders' => 0, 'spreadsheets' => 0, 'worksheets' => 0, 'cells' => 0];

        if ($team) {
            $folders = SheetsFolder::where('team_id', $team->id)
                ->whereNull('parent_id')
                ->orderBy('order')
                ->get();

            $spreadsheets = SheetsSpreadsheet::where('team_id', $team->id)
                ->whereNull('folder_id')
                ->orderBy('name')
                ->get();

            $stats = [
                'folders' => SheetsFolder::where('team_id', $team->id)->count(),
                'spreadsheets' => SheetsSpreadsheet::where('team_id', $team->id)->count(),
                'worksheets' => \Platform\Sheets\Models\SheetsWorksheet::where('team_id', $team->id)->count(),
                'cells' => \Platform\Sheets\Models\SheetsCell::whereHas('worksheet', fn ($q) => $q->where('team_id', $team->id))->count(),
            ];
        }

        return view('sheets::livewire.dashboard', [
            'folders' => $folders,
            'spreadsheets' => $spreadsheets,
            'stats' => $stats,
        ])->layout('platform::layouts.app');
    }
}
