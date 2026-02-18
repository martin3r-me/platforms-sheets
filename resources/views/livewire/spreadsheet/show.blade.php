<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $spreadsheet->name }}" icon="heroicon-o-table-cells">
            <x-slot name="actions">
                @if($spreadsheet->folder)
                <x-ui-button variant="secondary-outline" size="sm" :href="route('sheets.folder.show', $spreadsheet->folder)" wire:navigate>
                    @svg('heroicon-o-arrow-left', 'w-4 h-4 mr-1')
                    {{ $spreadsheet->folder->name }}
                </x-ui-button>
                @else
                <x-ui-button variant="secondary-outline" size="sm" :href="route('sheets.dashboard')" wire:navigate>
                    @svg('heroicon-o-arrow-left', 'w-4 h-4 mr-1')
                    Dashboard
                </x-ui-button>
                @endif

                {{-- Export Dropdown --}}
                <div x-data="{ open: false }" class="relative">
                    <x-ui-button variant="primary-outline" size="sm" @click="open = !open">
                        @svg('heroicon-o-arrow-down-tray', 'w-4 h-4 mr-1')
                        Export
                        @svg('heroicon-o-chevron-down', 'w-3 h-3 ml-1')
                    </x-ui-button>
                    <div x-show="open" @click.outside="open = false" x-transition
                         class="absolute right-0 mt-1 w-48 bg-[var(--ui-bg)] border border-[var(--ui-border)]/60 rounded-lg shadow-lg z-50 py-1">
                        <a href="{{ route('sheets.export.download', ['spreadsheet' => $spreadsheet->id, 'format' => 'xlsx']) }}"
                           class="d-flex items-center gap-2 px-4 py-2 text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors">
                            @svg('heroicon-o-document-arrow-down', 'w-4 h-4 text-green-600')
                            Als Excel (.xlsx)
                        </a>
                        <a href="{{ route('sheets.export.download', ['spreadsheet' => $spreadsheet->id, 'format' => 'csv']) }}"
                           class="d-flex items-center gap-2 px-4 py-2 text-sm text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition-colors">
                            @svg('heroicon-o-document-text', 'w-4 h-4 text-blue-600')
                            Als CSV (.csv)
                        </a>
                    </div>
                </div>
            </x-slot>
        </x-ui-page-navbar>
    </x-slot>

    {{-- Hauptinhalt --}}
    <x-ui-page-container>
        <div class="space-y-0">

            {{-- Worksheet Tabs --}}
            <div class="d-flex items-end gap-0.5 px-1">
                @foreach($worksheets as $ws)
                <button
                    wire:click="selectWorksheet({{ $ws->id }})"
                    class="px-4 py-2 text-sm font-medium transition-all relative
                        {{ $activeWorksheet && $activeWorksheet->id === $ws->id
                            ? 'bg-[var(--ui-bg)] text-[var(--ui-primary)] border border-b-0 border-[var(--ui-border)]/60 rounded-t-lg -mb-px z-10'
                            : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]/50 rounded-t-lg' }}"
                >
                    <span class="d-flex items-center gap-1.5">
                        {{ $ws->name }}
                        @if($ws->is_protected)
                            @svg('heroicon-o-lock-closed', 'w-3 h-3 opacity-60')
                        @endif
                    </span>
                </button>
                @endforeach
            </div>

            {{-- Spreadsheet Grid --}}
            @if($activeWorksheet)
            <div class="border border-[var(--ui-border)]/60 rounded-b-lg rounded-tr-lg bg-[var(--ui-bg)] overflow-hidden">

                {{-- Formel-Leiste --}}
                <div class="d-flex items-center border-b border-[var(--ui-border)]/40 bg-[var(--ui-muted-5)]/30">
                    <div class="px-3 py-1.5 border-r border-[var(--ui-border)]/40 min-w-[4rem] text-center">
                        <span class="text-xs font-mono font-bold text-[var(--ui-secondary)]">fx</span>
                    </div>
                    <div class="px-3 py-1.5 text-xs text-[var(--ui-muted)] italic flex-grow-1">
                        Read-Only Ansicht
                    </div>
                    <div class="px-3 py-1.5 d-flex items-center gap-2 text-xs text-[var(--ui-muted)]">
                        @if($activeWorksheet->is_protected)
                        <span class="d-flex items-center gap-1 text-amber-500">
                            @svg('heroicon-o-lock-closed', 'w-3 h-3')
                            Geschützt
                        </span>
                        @endif
                    </div>
                </div>

                {{-- Grid --}}
                <div class="overflow-auto max-h-[calc(100vh-280px)]">
                    <table class="w-full border-collapse text-sm" style="table-layout: fixed;">
                        {{-- Column Headers --}}
                        @php
                            $colWidths = $activeWorksheet->column_widths ?? [];
                            $rowHeightsMap = $activeWorksheet->row_heights ?? [];
                            $frozenRows = $activeWorksheet->frozen_rows ?? 0;
                            $frozenCols = $activeWorksheet->frozen_cols ?? 0;
                            // Calculate cumulative left offset for frozen columns (row-number col = 3rem = 48px)
                            $frozenColOffsets = [];
                            $cumulativeLeft = 48; // row-number column width
                            for ($fc = 1; $fc <= $frozenCols; $fc++) {
                                $frozenColOffsets[$fc] = $cumulativeLeft;
                                $cumulativeLeft += (int) ($colWidths[(string) $fc] ?? 90);
                            }
                        @endphp
                        <thead>
                            <tr>
                                <th class="sticky left-0 top-0 z-30 w-12 min-w-[3rem] bg-[var(--ui-muted-5)] border-r border-b border-[var(--ui-border)]/40 text-center text-[10px] text-[var(--ui-muted)] font-semibold p-0 h-7">
                                </th>
                                @for($c = 1; $c <= $maxCol; $c++)
                                @php $cw = $colWidths[(string) $c] ?? 90; @endphp
                                <th class="sticky top-0 {{ $c <= $frozenCols ? 'left-0 z-30' : 'z-20' }} bg-[var(--ui-muted-5)] border-r border-b border-[var(--ui-border)]/40 text-center text-[10px] text-[var(--ui-muted)] font-semibold p-0 h-7"
                                    style="min-width:{{ $cw }}px;width:{{ $cw }}px;{{ $c <= $frozenCols ? 'left:' . ($frozenColOffsets[$c] ?? 0) . 'px;' : '' }}">
                                    {{ \Platform\Sheets\Models\SheetsCell::numberToLetter($c) }}
                                </th>
                                @endfor
                            </tr>
                        </thead>
                        @php
                            // Calculate cumulative top offset for frozen rows (header row = 28px)
                            $frozenRowOffsets = [];
                            $cumulativeTop = 28; // header row height
                            for ($fr = 1; $fr <= $frozenRows; $fr++) {
                                $frozenRowOffsets[$fr] = $cumulativeTop;
                                $cumulativeTop += (int) ($rowHeightsMap[(string) $fr] ?? 24);
                            }
                        @endphp
                        <tbody>
                            @for($r = 1; $r <= $maxRow; $r++)
                            @php $rh = $rowHeightsMap[(string) $r] ?? 24; @endphp
                            <tr class="group">
                                {{-- Row Number --}}
                                <td class="sticky left-0 {{ $r <= $frozenRows ? 'sticky-top z-20' : 'z-10' }} bg-[var(--ui-muted-5)] border-r border-b border-[var(--ui-border)]/40 text-center text-[10px] text-[var(--ui-muted)] font-medium p-0 group-hover:bg-[var(--ui-primary)]/5"
                                    style="height:{{ $rh }}px;{{ $r <= $frozenRows ? 'position:sticky;top:' . ($frozenRowOffsets[$r] ?? 0) . 'px;z-index:20;' : '' }}">
                                    {{ $r }}
                                </td>
                                @for($c = 1; $c <= $maxCol; $c++)
                                @php
                                    $cell = $cells->get($r . ':' . $c);
                                    $displayValue = $cell ? ($cell->computed_value ?? $cell->raw_value ?? '') : '';
                                    $isFormula = $cell && str_starts_with($cell->raw_value ?? '', '=');
                                    $isLocked = $cell && $cell->is_locked;
                                    $hasValue = $cell && ($cell->raw_value !== null && $cell->raw_value !== '');
                                    $fmt = $cell?->format;

                                    // Build inline styles from format
                                    $cellStyles = '';
                                    if ($fmt) {
                                        if (!empty($fmt['background_color'])) {
                                            $cellStyles .= 'background-color:' . e($fmt['background_color']) . ';';
                                        }
                                        if (!empty($fmt['font_color'])) {
                                            $cellStyles .= 'color:' . e($fmt['font_color']) . ';';
                                        }
                                        if (!empty($fmt['bold'])) {
                                            $cellStyles .= 'font-weight:700;';
                                        }
                                        if (!empty($fmt['italic'])) {
                                            $cellStyles .= 'font-style:italic;';
                                        }
                                        if (!empty($fmt['align'])) {
                                            $cellStyles .= 'text-align:' . e($fmt['align']) . ';';
                                        }
                                    }

                                    // Apply number_format display transforms
                                    if ($fmt && !empty($fmt['number_format']) && is_numeric($displayValue)) {
                                        $nf = $fmt['number_format'];
                                        if ($nf === 'percent') {
                                            $displayValue = number_format((float) $displayValue * 100, 1) . ' %';
                                        } elseif ($nf === 'currency') {
                                            $displayValue = number_format((float) $displayValue, 2, ',', '.') . ' €';
                                        } elseif ($nf === 'number') {
                                            $displayValue = number_format((float) $displayValue, 2, ',', '.');
                                        }
                                    }
                                @endphp
                                @php
                                    $isFrozenRow = $r <= $frozenRows;
                                    $isFrozenCol = $c <= $frozenCols;
                                    $frozenStyle = '';
                                    $frozenZ = '';
                                    if ($isFrozenRow && $isFrozenCol) {
                                        $frozenStyle = 'position:sticky;top:' . ($frozenRowOffsets[$r] ?? 0) . 'px;left:' . ($frozenColOffsets[$c] ?? 0) . 'px;';
                                        $frozenZ = 'z-20';
                                    } elseif ($isFrozenRow) {
                                        $frozenStyle = 'position:sticky;top:' . ($frozenRowOffsets[$r] ?? 0) . 'px;';
                                        $frozenZ = 'z-15';
                                    } elseif ($isFrozenCol) {
                                        $frozenStyle = 'position:sticky;left:' . ($frozenColOffsets[$c] ?? 0) . 'px;';
                                        $frozenZ = 'z-15';
                                    }
                                @endphp
                                <td class="border-r border-b border-[var(--ui-border)]/15 px-1.5 py-0 text-xs overflow-hidden {{ $frozenZ }}
                                        {{ $isFrozenRow || $isFrozenCol ? 'bg-[var(--ui-bg)]' : '' }}
                                        {{ $isLocked && $activeWorksheet->is_protected ? 'bg-amber-500/5' : '' }}
                                        {{ !$fmt || empty($fmt['background_color']) ? ($hasValue ? 'bg-[var(--ui-bg)]' : '') : '' }}
                                        {{ !$fmt || empty($fmt['font_color']) ? 'text-[var(--ui-secondary)]' : '' }}
                                        group-hover:bg-[var(--ui-primary)]/[0.02]"
                                    style="height:{{ $rh }}px;{{ $frozenStyle }}{{ $cellStyles }}"
                                    @if($isFormula) title="Formel: {{ $cell->raw_value }}" @endif
                                >
                                    @if($isFormula && (!$fmt || empty($fmt['font_color'])))
                                    <span class="text-[var(--ui-primary)]">{{ $displayValue }}</span>
                                    @else
                                    {{ $displayValue }}
                                    @endif
                                </td>
                                @endfor
                            </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Info Bar --}}
            <div class="d-flex items-center justify-between text-[11px] text-[var(--ui-muted)] px-3 py-2 mt-1">
                <div class="d-flex items-center gap-4">
                    <span class="d-flex items-center gap-1">
                        @svg('heroicon-o-view-columns', 'w-3 h-3')
                        {{ $activeWorksheet->col_count }} Spalten
                    </span>
                    <span class="d-flex items-center gap-1">
                        @svg('heroicon-o-bars-3', 'w-3 h-3')
                        {{ $activeWorksheet->row_count }} Zeilen
                    </span>
                    <span class="d-flex items-center gap-1">
                        @svg('heroicon-o-hashtag', 'w-3 h-3')
                        {{ $cells->count() }} Zelle(n) belegt
                    </span>
                </div>
                <div class="d-flex items-center gap-3">
                    @if($activeWorksheet->is_protected)
                    <span class="d-flex items-center gap-1 text-amber-500">
                        @svg('heroicon-o-lock-closed', 'w-3 h-3')
                        Blattschutz aktiv
                    </span>
                    @endif
                    <span>
                        Anzeige: {{ $maxRow }} &times; {{ $maxCol }}
                    </span>
                </div>
            </div>

            @else
            <div class="border border-[var(--ui-border)]/60 rounded-lg">
                <div class="p-12 text-center">
                    @svg('heroicon-o-document-plus', 'w-16 h-16 text-[var(--ui-muted)] mx-auto mb-4')
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-2">Kein Worksheet vorhanden</h3>
                    <p class="text-[var(--ui-muted)]">Erstelle ein Worksheet per Chat.</p>
                </div>
            </div>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Rechte Sidebar --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Spreadsheet-Info" width="w-72" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-5 space-y-5">
                {{-- Spreadsheet Details --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Details</h3>
                    <div class="space-y-2 text-xs">
                        <div class="d-flex justify-between text-[var(--ui-muted)]">
                            <span>Name</span>
                            <span class="font-medium text-[var(--ui-secondary)]">{{ $spreadsheet->name }}</span>
                        </div>
                        @if($spreadsheet->description)
                        <div class="text-[var(--ui-muted)] mt-1">{{ $spreadsheet->description }}</div>
                        @endif
                        @if($spreadsheet->folder)
                        <div class="d-flex justify-between text-[var(--ui-muted)]">
                            <span>Ordner</span>
                            <a href="{{ route('sheets.folder.show', $spreadsheet->folder) }}" wire:navigate
                               class="font-medium text-[var(--ui-primary)] hover:underline">{{ $spreadsheet->folder->name }}</a>
                        </div>
                        @endif
                        <div class="d-flex justify-between text-[var(--ui-muted)]">
                            <span>Erstellt</span>
                            <span>{{ $spreadsheet->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Worksheets Liste --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">
                        Worksheets ({{ $worksheets->count() }})
                    </h3>
                    <div class="space-y-1">
                        @foreach($worksheets as $ws)
                        <button wire:click="selectWorksheet({{ $ws->id }})"
                            class="w-full d-flex items-center gap-2 p-2 rounded-md text-xs transition-colors
                                {{ $activeWorksheet && $activeWorksheet->id === $ws->id
                                    ? 'bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-medium'
                                    : 'text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)] hover:text-[var(--ui-secondary)]' }}"
                        >
                            @svg('heroicon-o-document', 'w-3.5 h-3.5')
                            <span class="flex-grow-1 text-left truncate">{{ $ws->name }}</span>
                            @if($ws->is_protected)
                                @svg('heroicon-o-lock-closed', 'w-3 h-3 opacity-50')
                            @endif
                        </button>
                        @endforeach
                    </div>
                </div>

                {{-- Aktives Worksheet Stats --}}
                @if($activeWorksheet)
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Aktives Worksheet</h3>
                    <div class="space-y-2">
                        <div class="d-flex items-center justify-between p-2.5 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-[11px] text-[var(--ui-muted)]">Zellen belegt</span>
                            <span class="text-xs font-bold text-[var(--ui-secondary)]">{{ $cells->count() }}</span>
                        </div>
                        <div class="d-flex items-center justify-between p-2.5 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-[11px] text-[var(--ui-muted)]">Formeln</span>
                            <span class="text-xs font-bold text-[var(--ui-secondary)]">{{ $cells->filter(fn($c) => str_starts_with($c->raw_value ?? '', '='))->count() }}</span>
                        </div>
                        <div class="d-flex items-center justify-between p-2.5 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-[11px] text-[var(--ui-muted)]">Gesperrt</span>
                            <span class="text-xs font-bold text-[var(--ui-secondary)]">{{ $cells->where('is_locked', true)->count() }}</span>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
