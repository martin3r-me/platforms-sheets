<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsCell;
use Platform\Sheets\Models\SheetsWorksheet;

class UpdateRowHeightsTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.rows.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /rows - Setzt Zeilenhöhen eines Worksheets. Zwei Modi: '
            . '(1) Manuell: rows-Array mit row-Nummer + height angeben. '
            . '(2) Auto-Fit: auto_fit=true setzt optimale Höhen basierend auf Zellinhalten (Mehrzeiler). '
            . 'Standard-Zeilenhöhe: 24px. '
            . 'REST-Parameter: worksheet_id (required), rows (optional, Array mit {row, height}), '
            . 'auto_fit (optional, bool), auto_fit_rows (optional, Array von Row-Nummern für selektives Auto-Fit), '
            . 'min_height (optional, Standard: 24), max_height (optional, Standard: 200).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'worksheet_id' => ['type' => 'integer', 'description' => 'ID des Worksheets'],
                'rows' => [
                    'type' => 'array',
                    'description' => 'Manuelle Zeilenhöhen. Jeder Eintrag: {row: 1, height: 48}',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'row' => ['type' => 'integer', 'description' => 'Zeilennummer (1-basiert)'],
                            'height' => ['type' => 'integer', 'description' => 'Höhe in Pixel (16-400)'],
                        ],
                        'required' => ['row', 'height'],
                    ],
                ],
                'auto_fit' => ['type' => 'boolean', 'description' => 'Automatische Höhenanpassung basierend auf Zellinhalten / Mehrzeilern (Standard: false)'],
                'auto_fit_rows' => [
                    'type' => 'array',
                    'description' => 'Nur diese Zeilen auto-fitten. Wenn leer, werden alle belegten Zeilen angepasst.',
                    'items' => ['type' => 'integer'],
                ],
                'min_height' => ['type' => 'integer', 'description' => 'Minimale Höhe bei Auto-Fit in Pixel (Standard: 24)'],
                'max_height' => ['type' => 'integer', 'description' => 'Maximale Höhe bei Auto-Fit in Pixel (Standard: 200)'],
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

            $rows = $arguments['rows'] ?? [];
            $autoFit = $arguments['auto_fit'] ?? false;
            $autoFitRows = $arguments['auto_fit_rows'] ?? [];
            $minHeight = max(16, min(100, $arguments['min_height'] ?? 24));
            $maxHeight = max(24, min(400, $arguments['max_height'] ?? 200));

            if (empty($rows) && !$autoFit) {
                return ToolResult::error('VALIDATION_ERROR', 'Entweder rows oder auto_fit=true angeben.');
            }

            // Current heights (sparse map: row_number => height)
            $heights = $worksheet->row_heights ?? [];

            // Auto-fit: calculate heights from cell content (line breaks)
            if ($autoFit) {
                $autoHeights = $this->calculateAutoFitHeights($worksheet, $autoFitRows, $minHeight, $maxHeight);
                foreach ($autoHeights as $rowNum => $height) {
                    $heights[(string) $rowNum] = $height;
                }
            }

            // Manual overrides
            $manualApplied = [];
            foreach ($rows as $entry) {
                $rowNum = (int) ($entry['row'] ?? 0);
                $height = (int) ($entry['height'] ?? 24);

                if ($rowNum < 1) {
                    return ToolResult::error('VALIDATION_ERROR', "Ungültige Zeilennummer: {$rowNum}. Muss >= 1 sein.");
                }

                $height = max(16, min(400, $height));
                $heights[(string) $rowNum] = $height;
                $manualApplied[] = 'Zeile ' . $rowNum . '=' . $height . 'px';
            }

            $worksheet->update(['row_heights' => $heights]);

            // Build readable response
            $summary = [];
            foreach ($heights as $rowNum => $height) {
                $summary[] = 'Zeile ' . $rowNum . ': ' . $height . 'px';
            }

            return ToolResult::success([
                'worksheet_id' => $worksheet->id,
                'row_heights' => $heights,
                'summary' => $summary,
                'auto_fit_applied' => $autoFit,
                'manual_applied' => $manualApplied,
                'message' => 'Zeilenhöhen aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    protected function calculateAutoFitHeights(SheetsWorksheet $worksheet, array $autoFitRows, int $minHeight, int $maxHeight): array
    {
        $query = SheetsCell::where('worksheet_id', $worksheet->id)
            ->whereNotNull('raw_value')
            ->where('raw_value', '!=', '');

        // Filter to specific rows if requested
        if (!empty($autoFitRows)) {
            $query->whereIn('row', $autoFitRows);
        }

        $cells = $query->get();

        // Group cells by row and find max line count per row
        $rowMaxLines = [];
        foreach ($cells as $cell) {
            $displayValue = $cell->computed_value ?? $cell->raw_value ?? '';
            $lineCount = substr_count((string) $displayValue, "\n") + 1;

            $rowNum = $cell->row;
            if (!isset($rowMaxLines[$rowNum]) || $lineCount > $rowMaxLines[$rowNum]) {
                $rowMaxLines[$rowNum] = $lineCount;
            }
        }

        // Calculate height per row: ~18px per line + 6px padding
        $heights = [];
        foreach ($rowMaxLines as $rowNum => $maxLines) {
            $calculatedHeight = ($maxLines * 18) + 6;
            $heights[$rowNum] = max($minHeight, min($maxHeight, $calculatedHeight));
        }

        return $heights;
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'row', 'height', 'layout', 'auto-fit'],
            'read_only' => false,
            'requires_auth' => true,
            'risk_level' => 'write',
            'idempotent' => true,
        ];
    }
}
