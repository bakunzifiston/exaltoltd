<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Resolves how many rows a table should show.
 *
 * One place decides this so every table in the admin offers the same choices and reads the
 * same query parameter, and so "all" can never turn into an unbounded query that loads a
 * table of any size into memory.
 */
class TablePageSize
{
    /** The query parameter every table listens on. */
    public const PARAM = 'per_page';

    public const ALL = 'all';

    /** Selectable sizes, in the order they appear in the picker. */
    public const OPTIONS = [10, 25, 50, 100];

    /**
     * Upper bound applied when "all" is chosen.
     *
     * "All" still runs one query and renders one page, so it needs a ceiling: without one a
     * table that grows past memory would take the page down rather than just being slow.
     * Anything beyond this is paginated normally and the view says so.
     */
    public const ALL_CAP = 2000;

    /**
     * Rows per page for this request.
     *
     * Falls back to the caller's default when the parameter is absent or not one of the
     * offered values, so a hand-edited URL cannot ask for an arbitrary page size.
     */
    public static function resolve(Request $request, int $default = 15): int
    {
        $value = $request->query(self::PARAM);

        if (is_string($value) && strtolower($value) === self::ALL) {
            return self::ALL_CAP;
        }

        $size = (int) $value;

        return in_array($size, self::OPTIONS, true) ? $size : $default;
    }

    /** Whether the viewer asked for every row. */
    public static function wantsAll(Request $request): bool
    {
        return is_string($value = $request->query(self::PARAM)) && strtolower($value) === self::ALL;
    }

    /**
     * Paginate an already-built collection.
     *
     * Some screens assemble their rows in PHP - merging sources, grouping, deriving totals -
     * so there is no query to paginate. This gives those the same paginator the component
     * expects, so every table behaves identically regardless of how its rows were produced.
     *
     * @template TValue
     *
     * @param  \Illuminate\Support\Collection<int, TValue>  $items
     * @return \Illuminate\Pagination\LengthAwarePaginator<int, TValue>
     */
    public static function paginate(
        \Illuminate\Support\Collection $items,
        Request $request,
        int $default = 15,
        string $pageName = 'page',
    ): \Illuminate\Pagination\LengthAwarePaginator {
        $perPage = self::resolve($request, $default);
        $page = max(1, (int) $request->query($pageName, 1));

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'pageName' => $pageName],
        );

        return $paginator->withQueryString();
    }

    /** The picker's current value, for rendering the selected option. */
    public static function current(Request $request, int $default = 15): string
    {
        if (self::wantsAll($request)) {
            return self::ALL;
        }

        return (string) self::resolve($request, $default);
    }
}
