<?php

namespace Platform\Sheets;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use Platform\Sheets\Models\SheetsFolder;
use Platform\Sheets\Models\SheetsSpreadsheet;
use Platform\Sheets\Policies\FolderPolicy;
use Platform\Sheets\Policies\SpreadsheetPolicy;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SheetsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sheets.php', 'sheets');
    }

    public function boot(): void
    {
        Relation::morphMap([
            'sheets_spreadsheet' => \Platform\Sheets\Models\SheetsSpreadsheet::class,
        ]);

        // EntityLinkProvider registrieren (loose Kopplung mit Organization-Modul)
        try {
            resolve(\Platform\Organization\Services\EntityLinkRegistry::class)
                ->register(new \Platform\Sheets\Organization\SheetsEntityLinkProvider());
        } catch (\Throwable $e) {
            // Organization-Modul nicht geladen
        }

        if (
            config()->has('sheets.routing') &&
            config()->has('sheets.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'sheets',
                'title'      => 'Sheets',
                'routing'    => config('sheets.routing'),
                'guard'      => config('sheets.guard'),
                'navigation' => config('sheets.navigation'),
                'sidebar'    => config('sheets.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('sheets')) {
            ModuleRouter::group('sheets', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        $this->publishes([
            __DIR__.'/../config/sheets.php' => config_path('sheets.php'),
        ], 'config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'sheets');
        $this->registerLivewireComponents();
        $this->registerPolicies();
        $this->registerTools();
        $this->runSeeders();
    }

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Overview
            $registry->register(new \Platform\Sheets\Tools\SheetsOverviewTool());

            // Folder-Tools
            $registry->register(new \Platform\Sheets\Tools\CreateFolderTool());
            $registry->register(new \Platform\Sheets\Tools\ListFoldersTool());
            $registry->register(new \Platform\Sheets\Tools\GetFolderTool());
            $registry->register(new \Platform\Sheets\Tools\UpdateFolderTool());
            $registry->register(new \Platform\Sheets\Tools\DeleteFolderTool());
            $registry->register(new \Platform\Sheets\Tools\AddFolderUserTool());
            $registry->register(new \Platform\Sheets\Tools\RemoveFolderUserTool());

            // Spreadsheet-Tools
            $registry->register(new \Platform\Sheets\Tools\CreateSpreadsheetTool());
            $registry->register(new \Platform\Sheets\Tools\ListSpreadsheetsTool());
            $registry->register(new \Platform\Sheets\Tools\GetSpreadsheetTool());
            $registry->register(new \Platform\Sheets\Tools\UpdateSpreadsheetTool());
            $registry->register(new \Platform\Sheets\Tools\DeleteSpreadsheetTool());

            // Worksheet-Tools
            $registry->register(new \Platform\Sheets\Tools\CreateWorksheetTool());
            $registry->register(new \Platform\Sheets\Tools\ListWorksheetsTool());
            $registry->register(new \Platform\Sheets\Tools\GetWorksheetTool());
            $registry->register(new \Platform\Sheets\Tools\UpdateWorksheetTool());
            $registry->register(new \Platform\Sheets\Tools\DeleteWorksheetTool());

            // Cell-Tools
            $registry->register(new \Platform\Sheets\Tools\GetCellsTool());
            $registry->register(new \Platform\Sheets\Tools\GetCellsRangeTool());
            $registry->register(new \Platform\Sheets\Tools\UpdateCellTool());
            $registry->register(new \Platform\Sheets\Tools\BulkUpdateCellsTool());
            $registry->register(new \Platform\Sheets\Tools\ImportDataTool());
            $registry->register(new \Platform\Sheets\Tools\ClearWorksheetTool());

            // Layout-Tools (Spaltenbreite, Zeilenhöhe)
            $registry->register(new \Platform\Sheets\Tools\UpdateColumnWidthsTool());
            $registry->register(new \Platform\Sheets\Tools\UpdateRowHeightsTool());

            // Export-Tools
            $registry->register(new \Platform\Sheets\Tools\ExportSpreadsheetTool());
        } catch (\Throwable $e) {
            \Log::warning('Sheets: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Sheets\\Livewire';
        $prefix = 'sheets';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }

    protected function registerPolicies(): void
    {
        $policies = [
            SheetsFolder::class => FolderPolicy::class,
            SheetsSpreadsheet::class => SpreadsheetPolicy::class,
        ];

        foreach ($policies as $model => $policy) {
            if (class_exists($model) && class_exists($policy)) {
                Gate::policy($model, $policy);
            }
        }
    }

    protected function runSeeders(): void
    {
        try {
            if (Schema::hasTable('sheets_cell_types')) {
                \Platform\Sheets\Database\Seeders\SheetsCellTypeSeeder::seedIfEmpty();
            }
            if (Schema::hasTable('sheets_folder_roles')) {
                \Platform\Sheets\Database\Seeders\SheetsFolderRoleSeeder::seedIfEmpty();
            }
        } catch (\Throwable $e) {
            // Silent fail during migration
        }
    }
}
