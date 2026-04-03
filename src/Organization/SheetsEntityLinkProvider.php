<?php

namespace Platform\Sheets\Organization;

use Illuminate\Database\Eloquent\Builder;
use Platform\Organization\Contracts\EntityLinkProvider;

class SheetsEntityLinkProvider implements EntityLinkProvider
{
    public function morphAliases(): array
    {
        return ['sheets_spreadsheet'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'sheets_spreadsheet' => ['label' => 'Spreadsheets', 'icon' => 'table-cells', 'route' => null],
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
}
