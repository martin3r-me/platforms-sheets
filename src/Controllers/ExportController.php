<?php

namespace Platform\Sheets\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Platform\Sheets\Models\SheetsSpreadsheet;
use Platform\Sheets\Services\SpreadsheetExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Download a spreadsheet export.
     */
    public function download(Request $request, SheetsSpreadsheet $spreadsheet): BinaryFileResponse|StreamedResponse
    {
        Gate::authorize('view', $spreadsheet);

        $format = $request->input('format', 'xlsx');
        $worksheetId = $request->input('worksheet_id') ? (int) $request->input('worksheet_id') : null;

        $exportService = new SpreadsheetExportService();

        if ($format === 'xlsx') {
            $formulasAsFormulas = $request->boolean('formulas_as_formulas', true);
            $result = $exportService->exportXlsx($spreadsheet, $worksheetId, $formulasAsFormulas);

            return response()
                ->download($result['path'], $result['filename'], [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ])
                ->deleteFileAfterSend(true);
        }

        if ($format === 'csv') {
            $delimiter = $request->input('delimiter', ';');
            if ($delimiter === '\\t') {
                $delimiter = "\t";
            }
            $result = $exportService->exportCsv($spreadsheet, $worksheetId, $delimiter);

            $files = $result['files'];

            // Single worksheet → direct download
            if (count($files) === 1) {
                return response()
                    ->download($files[0]['path'], $files[0]['filename'], [
                        'Content-Type' => 'text/csv; charset=UTF-8',
                    ])
                    ->deleteFileAfterSend(true);
            }

            // Multiple worksheets → ZIP archive
            $datum = now()->format('Y-m-d');
            $safeName = preg_replace('/[^\w\-äöüÄÖÜß\s]/u', '', $spreadsheet->name);
            $safeName = preg_replace('/\s+/', '_', $safeName);
            $safeName = mb_substr($safeName, 0, 100);
            $zipFilename = $safeName . '_' . $datum . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFilename);

            $zip = new \ZipArchive();
            $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            foreach ($files as $file) {
                $zip->addFile($file['path'], $file['filename']);
            }

            $zip->close();

            // Clean up individual CSV files
            foreach ($files as $file) {
                $exportService->cleanup($file['path']);
            }

            return response()
                ->download($zipPath, $zipFilename, [
                    'Content-Type' => 'application/zip',
                ])
                ->deleteFileAfterSend(true);
        }

        abort(422, 'Ungültiges Format. Erlaubt: xlsx, csv');
    }
}
