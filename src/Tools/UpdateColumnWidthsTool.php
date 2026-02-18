<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsCell;
use Platform\Sheets\Models\SheetsWorksheet;

class UpdateColumnWidthsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.columns.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /columns - Setzt Spaltenbreiten eines Worksheets. Zwei Modi: '
            . '(1) Manuell: columns-Array mit ref + width angeben. '
            . '(2) Auto-Fit: auto_fit=true setzt optimale Breiten basierend auf Zellinhalten. '
            . 'Beide Modi kombinierbar. Standard-Spaltenbreite: 90px. '
            . 'REST-Parameter: worksheet_id (required), columns (optional, Array mit {ref, width}), '
            . 'auto_fit (optional, bool), auto_fit_columns (optional, Array von Spalten-Refs für selektives Auto-Fit), '
            . 'min_width (optional, Standard: 50), max_width (optional, Standard: 500).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'worksheet_id' => ['type' => 'integer', 'description' => 'ID des Worksheets'],
                'columns' => [
                    'type' => 'array',
                    'description' => 'Manuelle Spaltenbreiten. Jeder Eintrag: {ref: "A", width: 200}',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'ref' => ['type' => 'string', 'description' => 'Spalten-Buchstabe, z.B. "A", "B", "AA"'],
                            'width' => ['type' => 'integer', 'description' => 'Breite in Pixel (50-800)'],
                        ],
                        'required' => ['ref', 'width'],
                    ],
                ],
                'auto_fit' => ['type' => 'boolean', 'description' => 'Automatische Breitenanpassung basierend auf Zellinhalten (Standard: false)'],
                'auto_fit_columns' => [
                    'type' => 'array',
                    'description' => 'Nur diese Spalten auto-fitten (z.B. ["A", "B", "D"]). Wenn leer, werden alle belegten Spalten angepasst.',
                    'items' => ['type' => 'string'],
                ],
                'min_width' => ['type' => 'integer', 'description' => 'Minimale Breite bei Auto-Fit in Pixel (Standard: 50)'],
                'max_width' => ['type' => 'integer', 'description' => 'Maximale Breite bei Auto-Fit in Pixel (Standard: 500)'],
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
            if (!$context->user) {
                return ToolResult::error('AUTH_ERROR', 'User erforderlich');
            }

            $columns = $arguments['columns'] ?? [];
            $autoFit = $arguments['auto_fit'] ?? false;
            $autoFitColumns = $arguments['auto_fit_columns'] ?? [];
            $minWidth = max(20, min(200, $arguments['min_width'] ?? 50));
            $maxWidth = max(100, min(800, $arguments['max_width'] ?? 500));

            if (empty($columns) && !$autoFit) {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder columns oder auto_fit=true angeben.');
            }

            // Current widths (sparse map: col_number => width)
            $widths = $worksheet->column_widths ?? [];

            // Auto-fit: calculate widths from cell content
            if ($autoFit) {
                $autoWidths = $this->calculateAutoFitWidths($worksheet, $autoFitColumns, $minWidth, $maxWidth);
                foreach ($autoWidths as $colNum => $width) {
                    $widths[(string) $colNum] = $width;
                }
            }

            // Manual overrides (applied after auto-fit so they take precedence)
            $manualApplied = [];
            foreach ($columns as $entry) {
                $ref = strtoupper(trim($entry['ref'] ?? ''));
                $width = (int) ($entry['width'] ?? 90);

                if (!preg_match('/^[A-Z]{1,3}$/', $ref)) {
                    return ToolResult::error('VALIDATION_ERROR', "Ungültige Spalten-Referenz: '{$ref}'. Erwartet: A, B, AA etc.");
                }

                $width = max(20, min(800, $width));
                $colNum = SheetsCell::letterToNumber($ref);
                $widths[(string) $colNum] = $width;
                $manualApplied[] = $ref . '=' . $width . 'px';
            }

            $worksheet->update(['column_widths' => $widths]);

            // Build readable response
            $summary = [];
            foreach ($widths as $colNum => $width) {
                $summary[] = SheetsCell::numberToLetter((int) $colNum) . ': ' . $width . 'px';
            }

            return ToolResult::success([
                'worksheet_id' => $worksheet->id,
                'column_widths' => $widths,
                'summary' => $summary,
                'auto_fit_applied' => $autoFit,
                'manual_applied' => $manualApplied,
                'message' => 'Spaltenbreiten aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    protected function calculateAutoFitWidths(SheetsWorksheet $worksheet, array $autoFitColumns, int $minWidth, int $maxWidth): array
    {
        $query = SheetsCell::where('worksheet_id', $worksheet->id)
            ->whereNotNull('raw_value')
            ->where('raw_value', '!=', '');

        // Filter to specific columns if requested
        if (!empty($autoFitColumns)) {
            $colNumbers = [];
            foreach ($autoFitColumns as $ref) {
                $ref = strtoupper(trim($ref));
                if (preg_match('/^[A-Z]{1,3}$/', $ref)) {
                    $colNumbers[] = SheetsCell::letterToNumber($ref);
                }
            }
            if (!empty($colNumbers)) {
                $query->whereIn('col', $colNumbers);
            }
        }

        $cells = $query->get();

        // Group cells by column and find max content length per column
        $colMaxLengths = [];
        foreach ($cells as $cell) {
            $displayValue = $cell->computed_value ?? $cell->raw_value ?? '';
            $length = mb_strlen((string) $displayValue);

            $colNum = $cell->col;
            if (!isset($colMaxLengths[$colNum]) || $length > $colMaxLengths[$colNum]) {
                $colMaxLengths[$colNum] = $length;
            }
        }

        // Also consider column header length (A, B, ..., AA)
        $widths = [];
        foreach ($colMaxLengths as $colNum => $maxLen) {
            $headerLen = mb_strlen(SheetsCell::numberToLetter($colNum));
            $effectiveLen = max($maxLen, $headerLen);

            // Approximate width: ~8px per character + 16px padding
            $calculatedWidth = ($effectiveLen * 8) + 16;
            $widths[$colNum] = max($minWidth, min($maxWidth, $calculatedWidth));
        }

        return $widths;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'column', 'width', 'layout', 'auto-fit'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
