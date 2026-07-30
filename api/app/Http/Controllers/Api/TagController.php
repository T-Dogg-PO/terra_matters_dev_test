<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;

class TagController extends Controller
{
    /**
     * The sidebar tag list.
     *
     * `bookmarks_count` is read straight off the tags table -- it is a
     * denormalised counter maintained by whoever writes to the pivot table.
     */
    public function index(): JsonResponse
    {
        $tags = Tag::query()->orderBy('name')->get();

        return response()->json([
            'data' => TagResource::collection($tags),
        ]);
    }
}
