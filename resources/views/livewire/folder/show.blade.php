<x-ui-page>
    {{-- Navbar --}}
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $folder->name }}" icon="heroicon-o-folder">
            <x-slot name="actions">
                @if($folder->parent)
                <x-ui-button variant="secondary-outline" size="sm" :href="route('sheets.folder.show', $folder->parent)" wire:navigate>
                    @svg('heroicon-o-arrow-left', 'w-4 h-4 mr-1')
                    {{ $folder->parent->name }}
                </x-ui-button>
                @else
                <x-ui-button variant="secondary-outline" size="sm" :href="route('sheets.dashboard')" wire:navigate>
                    @svg('heroicon-o-arrow-left', 'w-4 h-4 mr-1')
                    Dashboard
                </x-ui-button>
                @endif
            </x-slot>
        </x-ui-page-navbar>
    </x-slot>

    {{-- Hauptinhalt --}}
    <x-ui-page-container>
        <div class="space-y-6">

            {{-- Folder Info Header --}}
            <div class="p-5 rounded-xl border border-[var(--ui-border)]/60 bg-gradient-to-r from-[var(--ui-muted-5)] to-transparent">
                <div class="d-flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-[var(--ui-primary)]/10">
                        @svg('heroicon-o-folder', 'w-8 h-8 text-[var(--ui-primary)]')
                    </div>
                    <div class="flex-grow-1">
                        <h2 class="text-xl font-bold text-[var(--ui-secondary)]">{{ $folder->name }}</h2>
                        @if($folder->description)
                        <p class="text-sm text-[var(--ui-muted)] mt-1">{{ $folder->description }}</p>
                        @endif
                        <div class="d-flex items-center gap-4 mt-3 text-xs text-[var(--ui-muted)]">
                            <span class="d-flex items-center gap-1">
                                @svg('heroicon-o-folder', 'w-3.5 h-3.5')
                                {{ $stats['subfolders'] }} Unterordner
                            </span>
                            <span class="d-flex items-center gap-1">
                                @svg('heroicon-o-table-cells', 'w-3.5 h-3.5')
                                {{ $stats['spreadsheets'] }} Spreadsheets
                            </span>
                            <span class="d-flex items-center gap-1">
                                @svg('heroicon-o-document', 'w-3.5 h-3.5')
                                {{ $stats['worksheets'] }} Worksheets
                            </span>
                            <span class="d-flex items-center gap-1">
                                @svg('heroicon-o-users', 'w-3.5 h-3.5')
                                {{ $stats['members'] }} Mitglieder
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Unterordner --}}
            @if($folder->children->isNotEmpty())
            <x-ui-panel title="Unterordner" subtitle="{{ $folder->children->count() }} Ordner">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                    @foreach($folder->children as $child)
                    <a href="{{ route('sheets.folder.show', $child) }}" wire:navigate
                       class="p-4 rounded-lg border border-[var(--ui-border)]/60 bg-[var(--ui-muted-5)]/50 hover:border-[var(--ui-primary)]/40 hover:bg-[var(--ui-muted-5)] transition-all group">
                        <div class="d-flex items-center gap-3">
                            <div class="p-2 rounded-lg bg-[var(--ui-primary)]/10 group-hover:bg-[var(--ui-primary)]/20 transition-colors">
                                @svg('heroicon-o-folder', 'w-5 h-5 text-[var(--ui-primary)]')
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="font-semibold text-[var(--ui-secondary)] truncate">{{ $child->name }}</div>
                                @if($child->description)
                                <div class="text-xs text-[var(--ui-muted)] truncate mt-0.5">{{ $child->description }}</div>
                                @endif
                            </div>
                            @svg('heroicon-o-chevron-right', 'w-4 h-4 text-[var(--ui-muted)] group-hover:text-[var(--ui-primary)] transition-colors')
                        </div>
                    </a>
                    @endforeach
                </div>
            </x-ui-panel>
            @endif

            {{-- Spreadsheets --}}
            @if($folder->spreadsheets->isNotEmpty())
            <x-ui-panel title="Spreadsheets" subtitle="{{ $folder->spreadsheets->count() }} Spreadsheets in diesem Ordner">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @foreach($folder->spreadsheets as $spreadsheet)
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
                                {{ $spreadsheet->worksheets->count() }}
                            </span>
                            @svg('heroicon-o-chevron-right', 'w-4 h-4 group-hover:text-[var(--ui-primary)] transition-colors')
                        </div>
                    </a>
                    @endforeach
                </div>
            </x-ui-panel>
            @endif

            {{-- Mitglieder --}}
            @if($folder->folderUsers->isNotEmpty())
            <x-ui-panel title="Mitglieder" subtitle="{{ $folder->folderUsers->count() }} Benutzer mit Zugriff">
                <div class="divide-y divide-[var(--ui-border)]/40">
                    @foreach($folder->folderUsers as $fu)
                    <div class="d-flex items-center gap-4 p-4">
                        <div class="w-9 h-9 rounded-full bg-[var(--ui-primary)]/10 d-flex items-center justify-center text-sm font-bold text-[var(--ui-primary)]">
                            {{ strtoupper(substr($fu->user->name ?? '?', 0, 1)) }}
                        </div>
                        <div class="flex-grow-1">
                            <div class="font-medium text-[var(--ui-secondary)] text-sm">{{ $fu->user->name ?? 'Unbekannt' }}</div>
                            <div class="text-xs text-[var(--ui-muted)]">{{ $fu->user->email ?? '' }}</div>
                        </div>
                        <x-ui-badge variant="{{ match($fu->folderRole->key ?? '') {
                            'owner' => 'primary',
                            'admin' => 'warning',
                            'member' => 'secondary',
                            default => 'muted'
                        } }}" size="sm">
                            {{ ucfirst($fu->folderRole->label ?? $fu->folderRole->key ?? 'viewer') }}
                        </x-ui-badge>
                    </div>
                    @endforeach
                </div>
            </x-ui-panel>
            @endif

            {{-- Empty State --}}
            @if($folder->children->isEmpty() && $folder->spreadsheets->isEmpty())
            <x-ui-panel>
                <div class="p-12 text-center">
                    @svg('heroicon-o-folder-open', 'w-16 h-16 text-[var(--ui-muted)] mx-auto mb-4')
                    <h3 class="text-lg font-semibold text-[var(--ui-secondary)] mb-2">Ordner ist leer</h3>
                    <p class="text-[var(--ui-muted)]">Dieser Ordner enthält noch keine Unterordner oder Spreadsheets.</p>
                </div>
            </x-ui-panel>
            @endif
        </div>
    </x-ui-page-container>

    {{-- Rechte Sidebar --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="Ordner-Details" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                {{-- Pfad / Breadcrumb --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Pfad</h3>
                    <div class="space-y-1">
                        <a href="{{ route('sheets.dashboard') }}" wire:navigate
                           class="d-flex items-center gap-2 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors">
                            @svg('heroicon-o-home', 'w-3.5 h-3.5')
                            Sheets
                        </a>
                        @if($folder->parent)
                        <a href="{{ route('sheets.folder.show', $folder->parent) }}" wire:navigate
                           class="d-flex items-center gap-2 text-xs text-[var(--ui-muted)] hover:text-[var(--ui-primary)] transition-colors pl-2">
                            @svg('heroicon-o-chevron-right', 'w-3 h-3')
                            {{ $folder->parent->name }}
                        </a>
                        @endif
                        <div class="d-flex items-center gap-2 text-xs text-[var(--ui-primary)] font-medium pl-{{ $folder->parent ? '4' : '2' }}">
                            @svg('heroicon-o-chevron-right', 'w-3 h-3')
                            {{ $folder->name }}
                        </div>
                    </div>
                </div>

                {{-- Statistiken --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Statistiken</h3>
                    <div class="space-y-2">
                        <div class="d-flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Unterordner</span>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $stats['subfolders'] }}</span>
                        </div>
                        <div class="d-flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Spreadsheets</span>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $stats['spreadsheets'] }}</span>
                        </div>
                        <div class="d-flex items-center justify-between p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
                            <span class="text-xs text-[var(--ui-muted)]">Worksheets</span>
                            <span class="text-sm font-bold text-[var(--ui-secondary)]">{{ $stats['worksheets'] }}</span>
                        </div>
                    </div>
                </div>

                {{-- Erstellt --}}
                <div>
                    <h3 class="text-sm font-bold text-[var(--ui-secondary)] uppercase tracking-wider mb-3">Meta</h3>
                    <div class="space-y-2 text-xs text-[var(--ui-muted)]">
                        <div class="d-flex justify-between">
                            <span>Erstellt</span>
                            <span>{{ $folder->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <div class="d-flex justify-between">
                            <span>Aktualisiert</span>
                            <span>{{ $folder->updated_at->format('d.m.Y H:i') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
