<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $spreadsheet->name }}" icon="heroicon-o-table-cells">
            <x-slot name="actions">
                <x-ui-button variant="secondary-outline" size="sm" :href="route('sheets.dashboard')" wire:navigate>
                    @svg('heroicon-o-arrow-left', 'w-4 h-4 mr-1')
                    Zurück
                </x-ui-button>
            </x-slot>
        </x-ui-page-navbar>
    </x-slot>

    {{-- Hauptinhalt --}}
    <x-ui-page-container>
        <div class="space-y-4">
            {{-- Worksheet Tabs --}}
            <div class="d-flex items-center gap-1 border-b border-[var(--ui-border)]/60 pb-0">
                @foreach($worksheets as $ws)
                <button
                    wire:click="selectWorksheet({{ $ws->id }})"
                    class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors
                        {{ $activeWorksheet && $activeWorksheet->id === $ws->id
                            ? 'bg-[var(--ui-muted-5)] text-[var(--ui-primary)] border border-b-0 border-[var(--ui-border)]/60'
                            : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]/50' }}"
                >
                    {{ $ws->name }}
                    @if($ws->is_protected)
                        @svg('heroicon-o-lock-closed', 'w-3 h-3 ml-1 inline')
                    @endif
                </button>
                @endforeach
            </div>

            {{-- Spreadsheet Grid --}}
            @if($activeWorksheet)
            <div class="overflow-auto border border-[var(--ui-border)]/60 rounded-lg bg-[var(--ui-bg)]">
                <table class="w-full border-collapse text-sm">
                    {{-- Column Headers --}}
                    <thead>
                        <tr>
                            <th class="sticky left-0 top-0 z-20 w-12 min-w-[3rem] bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-center text-xs text-[var(--ui-muted)] font-medium p-1">
                                &nbsp;
                            </th>
                            @for($c = 1; $c <= $maxCol; $c++)
                            <th class="sticky top-0 z-10 min-w-[100px] bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-center text-xs text-[var(--ui-muted)] font-medium p-1">
                                {{ \Platform\Sheets\Models\SheetsCell::numberToLetter($c) }}
                            </th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody>
                        @for($r = 1; $r <= $maxRow; $r++)
                        <tr>
                            {{-- Row Number --}}
                            <td class="sticky left-0 z-10 bg-[var(--ui-muted-5)] border border-[var(--ui-border)]/40 text-center text-xs text-[var(--ui-muted)] font-medium p-1">
                                {{ $r }}
                            </td>
                            @for($c = 1; $c <= $maxCol; $c++)
                            @php
                                $cell = $cells->get($r . ':' . $c);
                                $displayValue = $cell ? ($cell->computed_value ?? $cell->raw_value ?? '') : '';
                            @endphp
                            <td class="border border-[var(--ui-border)]/20 p-1 text-[var(--ui-secondary)] text-xs
                                {{ $cell && $cell->is_locked ? 'bg-[var(--ui-muted-5)]/50' : '' }}"
                                title="{{ $cell && $cell->raw_value !== $cell->computed_value ? $cell->raw_value : '' }}"
                            >
                                {{ $displayValue }}
                            </td>
                            @endfor
                        </tr>
                        @endfor
                    </tbody>
                </table>
            </div>

            {{-- Info Bar --}}
            <div class="d-flex items-center justify-between text-xs text-[var(--ui-muted)] px-2">
                <span>{{ $activeWorksheet->row_count }} Zeilen &times; {{ $activeWorksheet->col_count }} Spalten</span>
                <span>{{ $cells->count() }} Zelle(n) belegt</span>
                @if($activeWorksheet->is_protected)
                <span class="d-flex items-center gap-1">
                    @svg('heroicon-o-lock-closed', 'w-3 h-3')
                    Blattschutz aktiv
                </span>
                @endif
            </div>
            @else
            <div class="p-12 text-center text-[var(--ui-muted)]">
                Kein Worksheet vorhanden.
            </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
