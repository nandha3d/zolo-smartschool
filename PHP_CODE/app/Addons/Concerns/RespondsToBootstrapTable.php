<?php

namespace App\Addons\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Server-side paging for the admin panel's bootstrap-table lists ({total, rows}),
 * with search over the given columns and a whitelist of sortable columns.
 */
trait RespondsToBootstrapTable
{
    /**
     * @param string[] $searchColumns columns (or relation.column) matched with LIKE
     * @param string[] $sortable columns the client may sort by
     */
    protected function tableResponse(Builder $query, callable $map, array $searchColumns = [], array $sortable = ['id']): JsonResponse
    {
        $search = trim((string) request('search', ''));
        if ($search !== '' && $searchColumns) {
            $query->where(function (Builder $q) use ($search, $searchColumns) {
                foreach ($searchColumns as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $field] = explode('.', $column, 2);
                        $q->orWhereHas($relation, fn($r) => $r->where($field, 'LIKE', "%{$search}%"));
                    } else {
                        $q->orWhere($query->getModel()->getTable() . '.' . $column, 'LIKE', "%{$search}%");
                    }
                }
            });
        }

        $total = (clone $query)->count();
        $limit = max(1, min(500, (int) request('limit', 10)));
        $offset = max(0, (int) request('offset', 0));
        if ($offset >= $total && $total > 0) {
            $offset = (int) (floor(($total - 1) / $limit) * $limit);
        }

        $sort = in_array(request('sort'), $sortable, true) ? request('sort') : $sortable[0];
        $order = strtolower((string) request('order', 'desc')) === 'asc' ? 'asc' : 'desc';

        $rows = $query->orderBy($query->getModel()->getTable() . '.' . $sort, $order)
            ->skip($offset)->take($limit)->get()
            ->values()
            ->map(fn($row, $i) => ['no' => $offset + $i + 1] + $map($row));

        return response()->json(['total' => $total, 'rows' => $rows]);
    }
}
