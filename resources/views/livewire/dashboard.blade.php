<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="Sheets" icon="heroicon-o-table-cells" />
    </x-slot>

    {{-- Hauptinhalt --}}
    <x-ui-page-container>
        <div class="space-y-6">

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <x-ui-dashboard-tile
                    title="Ordner"
                    :count="$stats['folders']"
                    subtitle="Gesamt"
                    icon="folder"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Spreadsheets"
                    :count="$stats['spreadsheets']"
                    subtitle="Gesamt"
                    icon="table-cells"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Worksheets"
                    :count="$stats['worksheets']"
                    subtitle="Gesamt"
                    icon="document"
                    variant="secondary"
                    size="lg"
                />
                <x-ui-dashboard-tile
                    title="Zellen"
                    :count="$stats['cells']"
                    subtitle="Belegt"
                    icon="hashtag"
                    variant="secondary"
                    size="lg"
                />
            </div>

            {{-- Ordner --}}
            @if($folders->isNotEmpty())
            <x-ui-panel title="Ordner" subtitle="{{ $folders->count() }} Ordner in diesem Team">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                    @foreach($folders as $folder)
                    <a href="{{ route('sheets.folder.show', $folder) }}" wire:navigate
                       class="p-4 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]/50 hover:border-[var(--ui-primary)]/40 hover:bg-[var(--ui-muted-5)] transition-all group">
                        <div class="d-flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-[var(--ui-primary)]/10 group-hover:bg-[var(--ui-primary)]/20 transition-colors">
                                @svg('heroicon-o-folder', 'w-6 h-6 text-[var(--ui-primary)]')
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="font-semibold text-[var(--ui-secondary)]">{{ $folder->name }}</div>
                                @if($folder->description)
                                <div class="text-xs text-[var(--ui-muted)] truncate mt-0.5">{{ $folder->description }}</div>
                                @endif
                                <div class="text-xs text-[var(--ui-muted)] mt-1">
                                    {{ $folder->spreadsheets->count() }} Spreadsheet(s)
                                </div>
                            </div>
                            @svg('heroicon-o-chevron-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                        </div>
                    </a>
                    @endforeach
                </div>
            </x-ui-panel>
            @endif

            {{-- Spreadsheets ohne Ordner --}}
            @if($spreadsheets->isNotEmpty())
            <x-ui-panel title="Spreadsheets" subtitle="Spreadsheets ohne Ordner">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @foreach($spreadsheets as $spreadsheet)
                    <a href="{{ route('sheets.spreadsheet.show', $spreadsheet) }}" wire:navigate
                       class="d-flex items-center gap-4 p-4 hover:bg-[var(--ui-muted-5)]/50 transition-colors group">
                        <div class="p-2 rounded-lg bg-[var(--ui-primary)]/10 group-hover:bg-[var(--ui-primary)]/20 transition-colors">
                            @svg('heroicon-o-table-cells', 'w-5 h-5 text-[var(--ui-primary)]')
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="font-medium text-[var(--ui-secondary)]">{{ $spreadsheet->name }}</div>
                            @if($spreadsheet->description)
                            <div class="text-xs text-[var(--ui-muted)] mt-0.5">{{ $spreadsheet->description }}</div>
                            @endif
                        </div>
                        <div class="d-flex items-center gap-3 text-xs text-[var(--ui-muted)]">
                            <span class="d-flex items-center gap-1">
                                @svg('heroicon-o-document', 'w-3.5 h-3.5')
                                {{ $spreadsheet->worksheets->count() }} Sheet(s)
                            </span>
                            @svg('heroicon-o-chevron-right', 'w-4 h-4 group-hover:text-[var(--ui-primary)] transition-colors')
                        </div>
                    </a>
                    @endforeach
                </div>
            </x-ui-panel>
            @endif

            {{-- Empty State --}}
            @if($folders->isEmpty() && $spreadsheets->isEmpty())
            <x-ui-panel>
                <div class="p-12 text-center">
                    @svg('heroicon-o-table-cells', 'w-16 h-16 text-[var(--ui-muted)] mx-auto mb-4')
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-2">Noch keine Spreadsheets</h3>
                    <p class="text-[var(--ui-muted)]">Erstelle dein erstes Spreadsheet per Chat oder direkt hier.</p>
                </div>
            </x-ui-panel>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Linke Sidebar --}}
    <x-slot name="sidebar">
        <x-ui-page-sidebar title="Navigation" width="w-72" :defaultOpen="true">
            <div class="p-5 space-y-5">
                {{-- Quick Actions --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Allgemein</h3>
                    <div class="space-y-1">
                        <div class="d-flex items-center gap-2 p-2 rounded-md text-xs bg-[var(--ui-primary)]/10 text-[var(--ui-primary)] font-medium">
                            @svg('heroicon-o-home', 'w-3.5 h-3.5')
                            Dashboard
                        </div>
                    </div>
                </div>

                {{-- Ordner --}}
                @if($folders->isNotEmpty())
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">
                        Ordner ({{ $folders->count() }})
                    </h3>
                    <div class="space-y-1">
                        @foreach($folders as $folder)
                        <a href="{{ route('sheets.folder.show', $folder) }}" wire:navigate
                           class="d-flex items-center gap-2 p-2 rounded-md text-xs text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)] hover:text-[var(--ui-secondary)] transition-colors">
                            @svg('heroicon-o-folder', 'w-3.5 h-3.5')
                            <span class="truncate">{{ $folder->name }}</span>
                            <span class="ml-auto text-[10px] opacity-60">{{ $folder->spreadsheets->count() }}</span>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Spreadsheets ohne Ordner --}}
                @if($spreadsheets->isNotEmpty())
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">
                        Spreadsheets ({{ $spreadsheets->count() }})
                    </h3>
                    <div class="space-y-1">
                        @foreach($spreadsheets as $ss)
                        <a href="{{ route('sheets.spreadsheet.show', $ss) }}" wire:navigate
                           class="d-flex items-center gap-2 p-2 rounded-md text-xs text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)] hover:text-[var(--ui-secondary)] transition-colors">
                            @svg('heroicon-o-table-cells', 'w-3.5 h-3.5')
                            <span class="truncate">{{ $ss->name }}</span>
                        </a>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    {{-- Rechte Sidebar --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Schnellzugriff" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        <div class="d-flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Ordner</span>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $stats['folders'] }}</span>
                        </div>
                        <div class="d-flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Spreadsheets</span>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $stats['spreadsheets'] }}</span>
                        </div>
                        <div class="d-flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Worksheets</span>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $stats['worksheets'] }}</span>
                        </div>
                        <div class="d-flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Zellen belegt</span>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ number_format($stats['cells']) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>

