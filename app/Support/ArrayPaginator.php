<?php

namespace App\Support;

class ArrayPaginator
{
    /** @return array{items: array<int, mixed>, pagination: array<string, int|null>} */
    public static function paginate(array $items, int $page = 1, int $perPage = 10, int $maxPerPage = 50): array
    {
        $total = count($items);
        $perPage = max(1, min($maxPerPage, $perPage));
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }
}
