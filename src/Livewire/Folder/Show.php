<?php

namespace Platform\Sheets\Livewire\Folder;

use Livewire\Component;
use Platform\Sheets\Models\SheetsFolder;

class Show extends Component
{
    public SheetsFolder $folder;

    public function mount(SheetsFolder $folder)
    {
        $this->folder = $folder;
    }

    public function render()
    {
        $folder = $this->folder->load([
            'children' => fn ($q) => $q->orderBy('order'),
            'spreadsheets' => fn ($q) => $q->with('worksheets')->orderBy('name'),
            'folderUsers.user',
            'folderUsers.folderRole',
            'parent',
        ]);

        $stats = [
            'subfolders' => $folder->children->count(),
            'spreadsheets' => $folder->spreadsheets->count(),
            'worksheets' => $folder->spreadsheets->sum(fn ($s) => $s->worksheets->count()),
            'members' => $folder->folderUsers->count(),
        ];

        return view('sheets::livewire.folder.show', [
            'folder' => $folder,
            'stats' => $stats,
        ])->layout('platform::layouts.app');
    }
}
