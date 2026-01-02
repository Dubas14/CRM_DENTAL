<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

class QuerySearch
{
    /**
     * Apply case-insensitive search (ILIKE) to a query builder.
     * Escapes special SQL LIKE characters (% and _) to prevent SQL injection.
     *
     * @param Builder|QueryBuilder $query
     * @param string $search The search term
     * @param array $columns Column names to search in (e.g., ['full_name', 'email'])
     * @param array $rawExpressions Raw SQL expressions with placeholders (e.g., ["concat_ws(' ', first_name, last_name) ILIKE ?"])
     * @return Builder|QueryBuilder
     */
    public static function applyIlike($query, string $search, array $columns = [], array $rawExpressions = [])
    {
        $search = trim($search);
        if (empty($search)) {
            return $query;
        }

        // Escape special LIKE characters: % and _
        $like = '%' . addcslashes($search, '%_') . '%';

        return $query->where(function ($q) use ($like, $columns, $rawExpressions) {
            // Apply ILIKE to regular columns
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $q->where($column, 'ilike', $like);
                } else {
                    $q->orWhere($column, 'ilike', $like);
                }
            }

            // Apply raw expressions (for concatenated fields, etc.)
            foreach ($rawExpressions as $expression) {
                if (is_string($expression)) {
                    // If it's a string, assume it's a raw SQL with ? placeholder
                    $q->orWhereRaw($expression, [$like]);
                } elseif (is_array($expression) && isset($expression['sql'])) {
                    // If it's an array with 'sql' key, use it as raw SQL
                    $bindings = $expression['bindings'] ?? [$like];
                    $q->orWhereRaw($expression['sql'], $bindings);
                }
            }
        });
    }
}

