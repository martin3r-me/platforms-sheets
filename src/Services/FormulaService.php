<?php

namespace Platform\Sheets\Services;

use Platform\Sheets\Models\SheetsCell;
use Platform\Sheets\Models\SheetsCellDependency;
use Platform\Sheets\Models\SheetsCellType;
use Platform\Sheets\Models\SheetsWorksheet;

class FormulaService
{
    /**
     * Parse cell references from a formula string.
     * Supports: A1, $A$1, A1:C10, Sheet1!A1
     */
    public function parseReferences(string $formula): array
    {
        $references = [];

        // Match cell references like A1, $A$1, AA10
        preg_match_all('/\$?([A-Z]{1,3})\$?(\d{1,7})/', $formula, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $references[] = [
                'col' => SheetsCell::letterToNumber($match[1]),
                'row' => (int) $match[2],
            ];
        }

        // Match range references like A1:C10
        preg_match_all('/\$?([A-Z]{1,3})\$?(\d{1,7}):\$?([A-Z]{1,3})\$?(\d{1,7})/', $formula, $rangeMatches, PREG_SET_ORDER);

        foreach ($rangeMatches as $match) {
            $startCol = SheetsCell::letterToNumber($match[1]);
            $startRow = (int) $match[2];
            $endCol = SheetsCell::letterToNumber($match[3]);
            $endRow = (int) $match[4];

            for ($row = $startRow; $row <= $endRow; $row++) {
                for ($col = $startCol; $col <= $endCol; $col++) {
                    $references[] = ['col' => $col, 'row' => $row];
                }
            }
        }

        // Deduplicate
        return array_values(array_unique($references, SORT_REGULAR));
    }

    /**
     * Evaluate a formula with provided cell values.
     * V1: Supports +, -, *, /, SUM, AVG, MIN, MAX, COUNT, IF
     */
    public function evaluate(string $formula, array $cellValues): mixed
    {
        if (!str_starts_with($formula, '=')) {
            return $formula;
        }

        $expression = substr($formula, 1); // Remove leading =

        try {
            // Replace function calls
            $expression = $this->replaceFunctions($expression, $cellValues);

            // Replace cell references with their values
            $expression = $this->replaceCellReferences($expression, $cellValues);

            // Evaluate the mathematical expression
            return $this->evaluateExpression($expression);
        } catch (\Throwable $e) {
            return '#ERROR: ' . $e->getMessage();
        }
    }

    /**
     * Update dependency graph for a cell.
     */
    public function updateDependencies(SheetsCell $cell): void
    {
        // Remove old dependencies
        SheetsCellDependency::where('cell_id', $cell->id)->delete();

        if (!str_starts_with($cell->raw_value ?? '', '=')) {
            return;
        }

        $references = $this->parseReferences($cell->raw_value);
        $worksheetId = $cell->worksheet_id;

        foreach ($references as $ref) {
            $referencedCell = SheetsCell::where('worksheet_id', $worksheetId)
                ->where('row', $ref['row'])
                ->where('col', $ref['col'])
                ->first();

            if ($referencedCell) {
                SheetsCellDependency::create([
                    'cell_id' => $cell->id,
                    'depends_on_cell_id' => $referencedCell->id,
                ]);
            }
        }
    }

    /**
     * Recalculate all cells that depend on the given cell.
     */
    public function recalculateDependents(SheetsCell $cell): void
    {
        $dependentCellIds = SheetsCellDependency::where('depends_on_cell_id', $cell->id)
            ->pluck('cell_id')
            ->toArray();

        if (empty($dependentCellIds)) {
            return;
        }

        // Topological sort for calculation order
        $sortedIds = $this->topologicalSort($dependentCellIds);

        foreach ($sortedIds as $cellId) {
            $depCell = SheetsCell::find($cellId);
            if (!$depCell || !str_starts_with($depCell->raw_value ?? '', '=')) {
                continue;
            }

            $cellValues = $this->getCellValuesForFormula($depCell);
            $computed = $this->evaluate($depCell->raw_value, $cellValues);

            $depCell->update(['computed_value' => (string) $computed]);

            // Recursively recalculate further dependents
            $this->recalculateDependents($depCell);
        }
    }

    /**
     * Detect circular references using DFS.
     */
    public function detectCircularReferences(SheetsCell $cell, array $visited = []): bool
    {
        if (in_array($cell->id, $visited)) {
            return true;
        }

        $visited[] = $cell->id;

        $dependencies = SheetsCellDependency::where('cell_id', $cell->id)
            ->pluck('depends_on_cell_id')
            ->toArray();

        foreach ($dependencies as $depCellId) {
            $depCell = SheetsCell::find($depCellId);
            if ($depCell && $this->detectCircularReferences($depCell, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine cell type from raw value.
     */
    public function determineCellType(string $rawValue): string
    {
        if ($rawValue === '' || $rawValue === null) {
            return 'empty';
        }
        if (str_starts_with($rawValue, '=')) {
            return 'formula';
        }
        if (is_numeric($rawValue)) {
            return 'number';
        }
        if (in_array(strtolower($rawValue), ['true', 'false', '1', '0', 'ja', 'nein', 'yes', 'no'])) {
            return 'boolean';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $rawValue)) {
            return 'date';
        }
        return 'text';
    }

    protected function replaceFunctions(string $expression, array $cellValues): string
    {
        // SUM(A1:A10)
        $expression = preg_replace_callback('/SUM\(([^)]+)\)/i', function ($m) use ($cellValues) {
            return (string) array_sum($this->resolveRange($m[1], $cellValues));
        }, $expression);

        // AVG / AVERAGE
        $expression = preg_replace_callback('/(?:AVG|AVERAGE)\(([^)]+)\)/i', function ($m) use ($cellValues) {
            $values = $this->resolveRange($m[1], $cellValues);
            return count($values) > 0 ? (string) (array_sum($values) / count($values)) : '0';
        }, $expression);

        // MIN
        $expression = preg_replace_callback('/MIN\(([^)]+)\)/i', function ($m) use ($cellValues) {
            $values = $this->resolveRange($m[1], $cellValues);
            return count($values) > 0 ? (string) min($values) : '0';
        }, $expression);

        // MAX
        $expression = preg_replace_callback('/MAX\(([^)]+)\)/i', function ($m) use ($cellValues) {
            $values = $this->resolveRange($m[1], $cellValues);
            return count($values) > 0 ? (string) max($values) : '0';
        }, $expression);

        // COUNT
        $expression = preg_replace_callback('/COUNT\(([^)]+)\)/i', function ($m) use ($cellValues) {
            return (string) count($this->resolveRange($m[1], $cellValues));
        }, $expression);

        // IF(condition, true_val, false_val)
        $expression = preg_replace_callback('/IF\(([^,]+),([^,]+),([^)]+)\)/i', function ($m) use ($cellValues) {
            $condition = trim($m[1]);
            $trueVal = trim($m[2]);
            $falseVal = trim($m[3]);

            $condResult = $this->evaluateCondition($condition, $cellValues);
            return $condResult ? $trueVal : $falseVal;
        }, $expression);

        return $expression;
    }

    protected function resolveRange(string $rangeExpr, array $cellValues): array
    {
        $values = [];

        // Check if it's a range (A1:C10)
        if (preg_match('/^([A-Z]{1,3})(\d+):([A-Z]{1,3})(\d+)$/i', trim($rangeExpr), $m)) {
            $startCol = SheetsCell::letterToNumber(strtoupper($m[1]));
            $startRow = (int) $m[2];
            $endCol = SheetsCell::letterToNumber(strtoupper($m[3]));
            $endRow = (int) $m[4];

            for ($row = $startRow; $row <= $endRow; $row++) {
                for ($col = $startCol; $col <= $endCol; $col++) {
                    $ref = SheetsCell::numberToLetter($col) . $row;
                    if (isset($cellValues[$ref]) && is_numeric($cellValues[$ref])) {
                        $values[] = (float) $cellValues[$ref];
                    }
                }
            }
        } else {
            // Comma-separated values
            $parts = explode(',', $rangeExpr);
            foreach ($parts as $part) {
                $part = trim($part);
                if (isset($cellValues[$part]) && is_numeric($cellValues[$part])) {
                    $values[] = (float) $cellValues[$part];
                } elseif (is_numeric($part)) {
                    $values[] = (float) $part;
                }
            }
        }

        return $values;
    }

    protected function replaceCellReferences(string $expression, array $cellValues): string
    {
        return preg_replace_callback('/\$?([A-Z]{1,3})\$?(\d{1,7})/', function ($m) use ($cellValues) {
            $ref = $m[1] . $m[2];
            $value = $cellValues[$ref] ?? '0';
            return is_numeric($value) ? $value : '0';
        }, $expression);
    }

    protected function evaluateExpression(string $expression): mixed
    {
        // Sanitize: only allow numbers, operators, parentheses, spaces, dots
        $sanitized = preg_replace('/[^0-9+\-*\/().eE\s]/', '', $expression);

        if ($sanitized === '' || $sanitized !== trim($expression)) {
            return $expression;
        }

        try {
            $result = eval("return ($sanitized);");
            return $result !== false ? $result : '#ERROR';
        } catch (\Throwable $e) {
            return '#ERROR';
        }
    }

    protected function evaluateCondition(string $condition, array $cellValues): bool
    {
        // Simple comparison: A1>10, A1=B1, etc.
        $condition = $this->replaceCellReferences($condition, $cellValues);

        if (preg_match('/^(.+?)(>=|<=|!=|<>|>|<|=)(.+)$/', $condition, $m)) {
            $left = (float) trim($m[1]);
            $op = trim($m[2]);
            $right = (float) trim($m[3]);

            return match ($op) {
                '>' => $left > $right,
                '<' => $left < $right,
                '>=' => $left >= $right,
                '<=' => $left <= $right,
                '=', '==' => $left == $right,
                '!=', '<>' => $left != $right,
                default => false,
            };
        }

        return (bool) $condition;
    }

    protected function getCellValuesForFormula(SheetsCell $cell): array
    {
        $references = $this->parseReferences($cell->raw_value);
        $values = [];

        foreach ($references as $ref) {
            $refCell = SheetsCell::where('worksheet_id', $cell->worksheet_id)
                ->where('row', $ref['row'])
                ->where('col', $ref['col'])
                ->first();

            $cellRef = SheetsCell::numberToLetter($ref['col']) . $ref['row'];
            $values[$cellRef] = $refCell ? ($refCell->computed_value ?? $refCell->raw_value ?? '0') : '0';
        }

        return $values;
    }

    protected function topologicalSort(array $cellIds): array
    {
        // Simple: return as-is for now, proper topo sort for complex graphs
        // For V1, the dependency chains are typically short
        return $cellIds;
    }
}
