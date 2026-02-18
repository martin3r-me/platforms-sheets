<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsWorksheet;

class UpdateWorksheetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.worksheets.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /worksheets/{id} - Aktualisiert ein Worksheet. REST-Parameter: worksheet_id (required), name, order, row_count, col_count, is_protected, '
            . 'frozen_rows (Anzahl fixierter Zeilen oben, 0=keine), frozen_cols (Anzahl fixierter Spalten links, 0=keine).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'worksheet_id' => ['type' => 'integer', 'description' => 'ID des Worksheets'],
                'name' => ['type' => 'string', 'description' => 'Neuer Name'],
                'order' => ['type' => 'integer', 'description' => 'Neue Reihenfolge'],
                'row_count' => ['type' => 'integer', 'description' => 'Neue Zeilenanzahl'],
                'col_count' => ['type' => 'integer', 'description' => 'Neue Spaltenanzahl'],
                'is_protected' => ['type' => 'boolean', 'description' => 'Blattschutz aktivieren/deaktivieren'],
                'frozen_rows' => ['type' => 'integer', 'description' => 'Anzahl fixierter Zeilen oben (0 = keine, z.B. 1 für Header-Zeile)'],
                'frozen_cols' => ['type' => 'integer', 'description' => 'Anzahl fixierter Spalten links (0 = keine, z.B. 1 für Beschriftungsspalte)'],
            ],
            'required' => ['worksheet_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $worksheet = SheetsWorksheet::find($arguments['worksheet_id']);
            if (!$worksheet) {
                return ToolResult::error('NOT_FOUND', 'Worksheet nicht gefunden');
            }

            $data = [];
            if (isset($arguments['name'])) $data['name'] = $arguments['name'];
            if (isset($arguments['order'])) $data['order'] = $arguments['order'];
            if (isset($arguments['row_count'])) $data['row_count'] = $arguments['row_count'];
            if (isset($arguments['col_count'])) $data['col_count'] = $arguments['col_count'];
            if (isset($arguments['is_protected'])) $data['is_protected'] = $arguments['is_protected'];
            if (isset($arguments['frozen_rows'])) $data['frozen_rows'] = max(0, (int) $arguments['frozen_rows']);
            if (isset($arguments['frozen_cols'])) $data['frozen_cols'] = max(0, (int) $arguments['frozen_cols']);

            $worksheet->update($data);

            return ToolResult::success([
                'id' => $worksheet->id,
                'name' => $worksheet->name,
                'is_protected' => $worksheet->is_protected,
                'message' => "Worksheet '{$worksheet->name}' aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'worksheet', 'update'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
