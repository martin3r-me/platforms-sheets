<?php

namespace Platform\Sheets\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Core\Contracts\ToolMetadataContract;
use Platform\Sheets\Models\SheetsSpreadsheet;
use Platform\Sheets\Services\SpreadsheetExportService;

class ExportSpreadsheetTool implements ToolContract, ToolMetadataContract
{
    public function getName(): string
    {
        return 'sheets.export';
    }

    public function getDescription(): string
    {
        return 'Exportiert ein Spreadsheet als .xlsx oder .csv Datei. '
            . 'Bei xlsx werden alle Worksheets in einer Datei zusammengefasst (inkl. Formeln). '
            . 'Bei csv wird pro Worksheet eine separate Datei erzeugt (Semikolon-Trennzeichen, UTF-8 mit BOM). '
            . 'Optional kann ein einzelnes Worksheet per worksheet_id exportiert werden.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'spreadsheet_id' => [
                    'type' => 'integer',
                    'description' => 'ID des Spreadsheets',
                ],
                'format' => [
                    'type' => 'string',
                    'enum' => ['xlsx', 'csv'],
                    'description' => 'Export-Format: "xlsx" (Excel) oder "csv"',
                ],
                'worksheet_id' => [
                    'type' => 'integer',
                    'description' => 'Optional: ID eines einzelnen Worksheets für gezielten Export. Wenn nicht angegeben, werden alle Worksheets exportiert.',
                ],
                'delimiter' => [
                    'type' => 'string',
                    'description' => 'Nur für CSV: Trennzeichen (Standard: ";"). Alternativen: "," oder "\\t" (Tab).',
                ],
                'formulas_as_formulas' => [
                    'type' => 'boolean',
                    'description' => 'Nur für XLSX: Formeln als Excel-Formeln exportieren (Standard: true). Wenn false, werden nur berechnete Werte exportiert.',
                ],
            ],
            'required' => ['spreadsheet_id', 'format'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $spreadsheet = SheetsSpreadsheet::with('worksheets')
                ->find($arguments['spreadsheet_id']);

            if (!$spreadsheet) {
                return ToolResult::error('NOT_FOUND', 'Spreadsheet nicht gefunden');
            }

            $format = $arguments['format'];
            $worksheetId = $arguments['worksheet_id'] ?? null;
            $exportService = new SpreadsheetExportService();

            if ($format === 'xlsx') {
                $formulasAsFormulas = $arguments['formulas_as_formulas'] ?? true;
                $result = $exportService->exportXlsx($spreadsheet, $worksheetId, $formulasAsFormulas);

                $downloadUrl = route('sheets.export.download', [
                    'spreadsheet' => $spreadsheet->id,
                    'format' => 'xlsx',
                    'worksheet_id' => $worksheetId,
                ]);

                return ToolResult::success([
                    'format' => 'xlsx',
                    'filename' => $result['filename'],
                    'download_url' => $downloadUrl,
                    'worksheets_exported' => $worksheetId
                        ? 1
                        : $spreadsheet->worksheets->count(),
                    'message' => 'Export erfolgreich. Download-Link: ' . $downloadUrl,
                ]);
            }

            if ($format === 'csv') {
                $delimiter = $arguments['delimiter'] ?? ';';
                if ($delimiter === '\\t') {
                    $delimiter = "\t";
                }

                $result = $exportService->exportCsv($spreadsheet, $worksheetId, $delimiter);

                $files = collect($result['files'])->map(function ($file) use ($spreadsheet, $worksheetId, $delimiter) {
                    return [
                        'filename' => $file['filename'],
                        'download_url' => route('sheets.export.download', [
                            'spreadsheet' => $spreadsheet->id,
                            'format' => 'csv',
                            'worksheet_id' => $worksheetId,
                            'delimiter' => $delimiter === ';' ? null : $delimiter,
                        ]),
                    ];
                })->toArray();

                return ToolResult::success([
                    'format' => 'csv',
                    'files' => $files,
                    'worksheets_exported' => count($files),
                    'delimiter' => $delimiter,
                    'message' => 'CSV-Export erfolgreich. ' . count($files) . ' Datei(en) erstellt.',
                ]);
            }

            return ToolResult::error('INVALID_FORMAT', 'Ungültiges Format. Erlaubt: xlsx, csv');
        } catch (\InvalidArgumentException $e) {
            return ToolResult::error('VALIDATION_ERROR', $e->getMessage());
        } catch (\Throwable $e) {
            return ToolResult::error('EXECUTION_ERROR', $e->getMessage());
        }
    }

    public function getMetadata(): array
    {
        return [
            'category' => 'action',
            'tags' => ['sheets', 'export', 'xlsx', 'csv', 'download'],
            'read_only' => true,
            'requires_auth' => true,
            'requires_team' => true,
            'risk_level' => 'safe',
            'idempotent' => true,
        ];
    }
}
