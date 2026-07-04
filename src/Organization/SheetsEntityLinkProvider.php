<?php

namespace Platform\Sheets\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;
use Platform\Sheets\Models\SheetsSpreadsheet;

class SheetsEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions
{
    public function morphAliases(): array
    {
        return ['sheets_spreadsheet'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'sheets_spreadsheet' => ['label' => 'Spreadsheets', 'singular' => 'Spreadsheet', 'icon' => 'table-cells', 'route' => null],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        $query->withCount('worksheets');
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return [
            'worksheet_count' => (int) ($model->worksheets_count ?? 0),
        ];
    }

    public function metadataDisplayRules(): array
    {
        return [
            'sheets_spreadsheet' => [
                ['field' => 'worksheet_count', 'format' => 'count', 'suffix' => 'Blätter'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [];
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        if ($morphAlias !== 'sheets_spreadsheet') {
            return [];
        }

        $allIds = [];
        foreach ($linksByEntity as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));

        if (empty($allIds)) {
            return [];
        }

        $sheets = SheetsSpreadsheet::whereIn('id', $allIds)
            ->withCount('worksheets')
            ->select('id')
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $total = 0;
            $worksheets = 0;

            foreach ($ids as $id) {
                $s = $sheets[$id] ?? null;
                if (! $s) {
                    continue;
                }
                $total++;
                $worksheets += (int) ($s->worksheets_count ?? 0);
            }

            $result[$entityId] = [
                'sheets_spreadsheets_total' => $total,
                'sheets_worksheets_total' => $worksheets,
            ];
        }

        return $result;
    }

    public function metricDefinitions(): array
    {
        return [
            'sheets_spreadsheets_total' => ['label' => 'Spreadsheets (gesamt)', 'group' => 'sheets', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'org_capital', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
            'sheets_worksheets_total'   => ['label' => 'Arbeitsblätter (gesamt)', 'group' => 'sheets', 'direction' => 'neutral', 'unit' => 'count', 'dimension' => 'complexity', 'type' => 'stock', 'aggregation_mode' => 'rolled_up', 'basis' => 'stichtag'],
        ];
    }
}
