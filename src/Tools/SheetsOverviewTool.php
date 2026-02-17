<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;

class SheetsOverviewTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.overview.GET';
    }

    public function getDescription(): string
    {
        return 'GET /sheets/overview - Zeigt Übersicht über Sheets-Konzepte und Beziehungen. EMPFOHLEN: Nutze dieses Tool zuerst, um die Struktur des Sheets-Moduls zu verstehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            return ToolResult::success([
                'module' => 'sheets',
                'description' => 'Excel-Klon: Spreadsheets mit Ordnern, Arbeitsblättern, Zellen und Formeln',
                'concepts' => [
                    'folders' => [
                        'description' => 'Ordner für die Organisation von Spreadsheets. Hierarchisch (parent/children). Team-basiert mit Rollen.',
                        'roles' => ['owner (level 4)', 'admin (3)', 'member (2)', 'viewer (1)'],
                    ],
                    'spreadsheets' => [
                        'description' => 'Tabellen-Dokumente (wie eine Excel-Datei). Können in Ordnern organisiert werden.',
                        'attributes' => ['name', 'description', 'folder_id (optional)'],
                    ],
                    'worksheets' => [
                        'description' => 'Arbeitsblätter (Tabs) innerhalb eines Spreadsheets. Standard: 1000 Zeilen, 26 Spalten.',
                        'attributes' => ['name', 'order', 'row_count', 'col_count', 'is_protected'],
                    ],
                    'cells' => [
                        'description' => 'Sparse Storage - nur Zellen mit Inhalt werden gespeichert.',
                        'attributes' => ['row', 'col', 'raw_value', 'computed_value', 'cell_type', 'format', 'is_locked'],
                        'cell_types' => ['text', 'number', 'date', 'boolean', 'formula', 'empty'],
                        'formulas' => [
                            'description' => 'Formeln beginnen mit = und werden serverseitig berechnet.',
                            'supported' => ['Arithmetik (+,-,*,/)', 'SUM', 'AVG/AVERAGE', 'MIN', 'MAX', 'COUNT', 'IF'],
                            'references' => 'A1, $A$1, A1:C10 (Ranges)',
                        ],
                    ],
                    'cell_protection' => [
                        'description' => 'Wie Excel: Worksheet is_protected + Cell is_locked. Zelle geschützt nur wenn BEIDES true.',
                        'default' => 'Neue Zellen sind NICHT gesperrt',
                    ],
                ],
                'workflows' => [
                    'create_spreadsheet' => [
                        'step_1' => 'Optional: Ordner erstellen (sheets.folders.POST)',
                        'step_2' => 'Spreadsheet erstellen (sheets.spreadsheets.POST)',
                        'step_3' => 'Worksheet wird automatisch erstellt',
                        'step_4' => 'Zellen befüllen (sheets.cells.PUT oder sheets.cells.bulk.PUT)',
                    ],
                ],
                'related_tools' => [
                    'folders' => ['sheets.folders.POST', 'sheets.folders.GET', 'sheets.folder.GET', 'sheets.folders.PUT', 'sheets.folders.DELETE', 'sheets.folder_users.POST', 'sheets.folder_users.DELETE'],
                    'spreadsheets' => ['sheets.spreadsheets.POST', 'sheets.spreadsheets.GET', 'sheets.spreadsheet.GET', 'sheets.spreadsheets.PUT', 'sheets.spreadsheets.DELETE'],
                    'worksheets' => ['sheets.worksheets.POST', 'sheets.worksheets.GET', 'sheets.worksheet.GET', 'sheets.worksheets.PUT', 'sheets.worksheets.DELETE'],
                    'cells' => ['sheets.cells.GET', 'sheets.cells.PUT', 'sheets.cells.bulk.PUT'],
                ],
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', 'Fehler: ' . $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'overview',
            'tags' => ['sheets', 'overview', 'help'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => false,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
