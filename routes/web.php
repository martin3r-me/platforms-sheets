<?php

use Platform\Sheets\Livewire\Dashboard;
use Platform\Sheets\Livewire\Spreadsheet\Show as SpreadsheetShow;

Route::get('/', Dashboard::class)->name('sheets.dashboard');
Route::get('/spreadsheet/{spreadsheet}', SpreadsheetShow::class)->name('sheets.spreadsheet.show');
