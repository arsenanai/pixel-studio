<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Database\Query\Builder;

final class Search
{
    private const ESCAPE = '!';

    /** @param list<string> $columns */
    public static function containsAny(Builder $query, array $columns, string $text): void
    {
        $pattern = '%'.mb_strtolower(str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $text,
        )).'%';

        $query->where(function (Builder $where) use ($columns, $pattern): void {
            foreach ($columns as $column) {
                $where->orWhereRaw('LOWER('.$column.") LIKE ? ESCAPE '".self::ESCAPE."'", [$pattern]);
            }
        });
    }
}
