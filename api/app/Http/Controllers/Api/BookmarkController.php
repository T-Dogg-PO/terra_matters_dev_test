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
        $query = Bookmark::query()->with('tags');

        if ($request->filled('search')) {
            $needle = mb_strtolower((string) $request->input('search'));

            $query->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(title) LIKE ?', ['%' . $needle . '%'])
                    ->orWhereRaw('LOWER(url) LIKE ?', ['%' . $needle . '%']);
            });
        }

        if ($request->filled('tag')) {
            $slug = (string) $request->input('tag');

            $query->whereHas('tags', function ($tagQuery) use ($slug) {
                $tagQuery->where('slug', $slug);
            });
        }

        $page = max(1, (int) $request->input('page', 1));

        $paginator = $query
            ->orderByRaw('is_pinned DESC, created_at DESC')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        return response()->json([
            'data' => BookmarkResource::collection($paginator->items()),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
