<?php

use Platform\Sheets\Livewire\Dashboard;
use Platform\Sheets\Livewire\Folder\Show as FolderShow;
use Platform\Sheets\Livewire\Spreadsheet\Show as SpreadsheetShow;

Route::get('/', Dashboard::class)->name('sheets.dashboard');
Route::get('/folder/{folder}', FolderShow::class)->name('sheets.folder.show');
Route::get('/spreadsheet/{spreadsheet}', SpreadsheetShow::class)->name('sheets.spreadsheet.show');
