<?php

namespace Platform\Sheets\Services;

use Platform\Sheets\Models\SheetsSpreadsheet;
use Platform\Sheets\Models\SheetsWorksheet;
use Platform\Sheets\Models\SheetsCell;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class SpreadsheetExportService
{
    /**
     * Export a spreadsheet as .xlsx file.
     *
     * @param SheetsSpreadsheet $spreadsheet
     * @param int|null $worksheetId Optional: export only this worksheet
     * @param bool $formulasAsFormulas If true, export formulas as Excel formulas; if false, export computed values only
     * @return array{filename: string, path: string}
     */
    public function exportXlsx(
        SheetsSpreadsheet $spreadsheet,
        ?int $worksheetId = null,
        bool $formulasAsFormulas = true,
    ): array {
        $excel = new Spreadsheet();
        $excel->removeSheetByIndex(0); // Remove default sheet

        $worksheets = $this->getWorksheets($spreadsheet, $worksheetId);

        foreach ($worksheets as $index => $worksheet) {
            $excelSheet = $excel->createSheet($index);
            $excelSheet->setTitle($this->sanitizeSheetTitle($worksheet->name));

            $cells = SheetsCell::where('worksheet_id', $worksheet->id)
                ->with('cellType')
                ->orderBy('row')
                ->orderBy('col')
                ->get();

            foreach ($cells as $cell) {
                $colLetter = Coordinate::stringFromColumnIndex($cell->col);
                $cellRef = $colLetter . $cell->row;

                $isFormula = str_starts_with($cell->raw_value ?? '', '=');

                if ($isFormula && $formulasAsFormulas) {
                    $excelSheet->setCellValue($cellRef, $cell->raw_value);
                } else {
                    $value = $cell->computed_value ?? $cell->raw_value ?? '';
                    if (is_numeric($value)) {
                        $excelSheet->setCellValue($cellRef, (float) $value);
                    } else {
                        $excelSheet->setCellValue($cellRef, $value);
                    }
                }

                // Apply number format from cell format metadata
                if (!empty($cell->format) && isset($cell->format['number_format'])) {
                    $excelSheet->getStyle($cellRef)
                        ->getNumberFormat()
                        ->setFormatCode($cell->format['number_format']);
                }
            }
        }

        $excel->setActiveSheetIndex(0);

        $datum = now()->format('Y-m-d');
        $worksheetSuffix = $worksheetId && $worksheets->count() === 1
            ? '_' . $this->sanitizeFilename($worksheets->first()->name)
            : '';
        $filename = $this->sanitizeFilename($spreadsheet->name) . $worksheetSuffix . '_' . $datum . '.xlsx';

        $path = storage_path('app/temp/' . $filename);
        $this->ensureDirectory(dirname($path));

        $writer = new Xlsx($excel);
        $writer->save($path);

        return [
            'filename' => $filename,
            'path' => $path,
        ];
    }

    /**
     * Export a spreadsheet as .csv file(s).
     * Since CSV doesn't support multiple sheets, each worksheet becomes a separate file.
     *
     * @param SheetsSpreadsheet $spreadsheet
     * @param int|null $worksheetId Optional: export only this worksheet
     * @param string $delimiter Delimiter character (default: semicolon for German standard)
     * @return array{files: array<array{filename: string, path: string}>}
     */
    public function exportCsv(
        SheetsSpreadsheet $spreadsheet,
        ?int $worksheetId = null,
        string $delimiter = ';',
    ): array {
        $worksheets = $this->getWorksheets($spreadsheet, $worksheetId);
        $datum = now()->format('Y-m-d');
        $files = [];

        foreach ($worksheets as $worksheet) {
            $filename = $this->sanitizeFilename($spreadsheet->name)
                . '_' . $this->sanitizeFilename($worksheet->name)
                . '_' . $datum . '.csv';

            $path = storage_path('app/temp/' . $filename);
            $this->ensureDirectory(dirname($path));

            $cells = SheetsCell::where('worksheet_id', $worksheet->id)
                ->orderBy('row')
                ->orderBy('col')
                ->get();

            // Determine grid dimensions
            $maxRow = $cells->max('row') ?? 0;
            $maxCol = $cells->max('col') ?? 0;

            if ($maxRow === 0 || $maxCol === 0) {
                // Empty worksheet - create empty file with BOM
                file_put_contents($path, "\xEF\xBB\xBF");
                $files[] = ['filename' => $filename, 'path' => $path];
                continue;
            }

            // Index cells by row:col for fast lookup
            $cellMap = $cells->keyBy(fn($c) => $c->row . ':' . $c->col);

            $handle = fopen($path, 'w');
            // UTF-8 BOM for Excel compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            for ($row = 1; $row <= $maxRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $maxCol; $col++) {
                    $cell = $cellMap->get($row . ':' . $col);
                    $value = $cell ? ($cell->computed_value ?? $cell->raw_value ?? '') : '';
                    $rowData[] = $value;
                }
                fputcsv($handle, $rowData, $delimiter, '"', '\\');
            }

            fclose($handle);

            $files[] = [
                'filename' => $filename,
                'path' => $path,
            ];
        }

        return ['files' => $files];
    }

    /**
     * Get worksheets for export, optionally filtered by worksheet ID.
     */
    protected function getWorksheets(SheetsSpreadsheet $spreadsheet, ?int $worksheetId = null)
    {
        if ($worksheetId) {
            $worksheet = SheetsWorksheet::where('id', $worksheetId)
                ->where('spreadsheet_id', $spreadsheet->id)
                ->first();

            if (!$worksheet) {
                throw new \InvalidArgumentException('Worksheet nicht gefunden oder gehört nicht zu diesem Spreadsheet.');
            }

            return collect([$worksheet]);
        }

        return $spreadsheet->worksheets()->orderBy('order')->get();
    }

    /**
     * Sanitize a string for use as a filename.
     */
    protected function sanitizeFilename(string $name): string
    {
        $name = preg_replace('/[^\w\-äöüÄÖÜß\s]/u', '', $name);
        $name = preg_replace('/\s+/', '_', $name);
        return mb_substr($name, 0, 100);
    }

    /**
     * Sanitize worksheet title for Excel (max 31 chars, no special chars).
     */
    protected function sanitizeSheetTitle(string $name): string
    {
        $name = preg_replace('/[\\\\\/\*\?\[\]:]+/', '_', $name);
        return mb_substr($name, 0, 31);
    }

    /**
     * Ensure directory exists.
     */
    protected function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Clean up temporary export file.
     */
    public function cleanup(string $path): void
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
