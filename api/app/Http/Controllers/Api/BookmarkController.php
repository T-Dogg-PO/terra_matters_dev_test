<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookmarkResource;
use App\Models\Bookmark;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    private const PER_PAGE = 20;

    /**
     * Paginated bookmark list.
     *
     * Query string:
     *   ?search=  free text, matches the title or the url
     *   ?tag=     tag slug
     *   ?page=    1-based page number
     */
    public function index(Request $request): JsonResponse
    {
        $bookmarks = Bookmark::query()->with('tags')->get();

        if ($request->filled('search')) {
            $needle = mb_strtolower((string) $request->input('search'));

            $bookmarks = $bookmarks->filter(function (Bookmark $bookmark) use ($needle) {
                return str_contains(mb_strtolower($bookmark->title), $needle)
                    || str_contains(mb_strtolower($bookmark->url), $needle);
            });
        }

        if ($request->filled('tag')) {
            $slug = (string) $request->input('tag');

            $bookmarks = $bookmarks->filter(
                fn (Bookmark $bookmark) => $bookmark->tags->contains('slug', $slug)
            );
        }

        $total = $bookmarks->count();
        $page = max(1, (int) $request->input('page', 1));

        $items = $bookmarks
            ->forPage($page, self::PER_PAGE)
            ->sortByDesc('created_at')
            ->values();

        return response()->json([
            'data' => BookmarkResource::collection($items),
            'meta' => [
                'current_page' => $page,
                'per_page' => self::PER_PAGE,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / self::PER_PAGE)),
            ],
        ]);
    }
}
