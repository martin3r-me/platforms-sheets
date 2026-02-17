<?php

namespace Platform\Sheets\Livewire;

use Livewire\Component;
use Platform\Sheets\Models\SheetsFolder;
use Platform\Sheets\Models\SheetsSpreadsheet;

class Sidebar extends Component
{
    public function render()
    {
        $user = auth()->user();

        if (!$user) {
            return view('sheets::livewire.sidebar', ['folders' => collect(), 'spreadsheets' => collect()]);
        }

        $team = $user->currentTeam;
        $folders = collect();
        $spreadsheets = collect();

        if ($team) {
            $folders = SheetsFolder::where('team_id', $team->id)
                ->whereNull('parent_id')
                ->orderBy('order')
                ->limit(20)
                ->get();

            $spreadsheets = SheetsSpreadsheet::where('team_id', $team->id)
                ->whereNull('folder_id')
                ->orderBy('name')
                ->limit(10)
                ->get();
        }

        return view('sheets::livewire.sidebar', [
            'folders' => $folders,
            'spreadsheets' => $spreadsheets,
        ]);
    }
}
