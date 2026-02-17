<div>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Sheets
    </div>

    {{-- Allgemein --}}
    <x-ui-sidebar-list label="Allgemein">
        <x-ui-sidebar-item :href="route('sheets.dashboard')">
            @svg('heroicon-o-home', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Ordner --}}
    @if($folders->isNotEmpty())
    <x-ui-sidebar-list label="Ordner">
        @foreach($folders as $folder)
        <x-ui-sidebar-item :href="route('sheets.dashboard')">
            @svg('heroicon-o-folder', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm truncate">{{ $folder->name }}</span>
        </x-ui-sidebar-item>
        @endforeach
    </x-ui-sidebar-list>
    @endif

    {{-- Spreadsheets ohne Ordner --}}
    @if($spreadsheets->isNotEmpty())
    <x-ui-sidebar-list label="Spreadsheets">
        @foreach($spreadsheets as $spreadsheet)
        <x-ui-sidebar-item :href="route('sheets.spreadsheet.show', $spreadsheet)">
            @svg('heroicon-o-table-cells', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm truncate">{{ $spreadsheet->name }}</span>
        </x-ui-sidebar-item>
        @endforeach
    </x-ui-sidebar-list>
    @endif

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('sheets.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]">
                @svg('heroicon-o-home', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
