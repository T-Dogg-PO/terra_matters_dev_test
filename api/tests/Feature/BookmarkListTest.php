<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The behaviour the bookmark list endpoint is supposed to have.
 *
 * Some of these pass already — treat those as a safety net, not as noise.
 * Do not weaken an assertion to make it go green.
 */
class BookmarkListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedFixture();
    }

    /**
     * A small, fully predictable fixture.
     *
     * 45 bookmarks, created one hour apart. #45 is the newest.
     * Bookmarks 3 and 40 are pinned.
     * Every third bookmark carries the "laravel" tag.
     */
    private function seedFixture(): void
    {
        $laravel = Tag::create(['name' => 'Laravel', 'slug' => 'laravel', 'bookmarks_count' => 0]);
        $vue = Tag::create(['name' => 'Vue', 'slug' => 'vue', 'bookmarks_count' => 0]);

        $base = strtotime('2026-01-01 00:00:00');

        for ($i = 1; $i <= 45; $i++) {
            $bookmark = Bookmark::create([
                'title' => "Bookmark {$i}",
                'url' => "https://example.test/article-{$i}",
                'domain' => 'example.test',
                'description' => null,
                'is_pinned' => in_array($i, [3, 40], true),
            ]);

            $timestamp = date('Y-m-d H:i:s', $base + ($i * 3600));
            DB::table('bookmarks')->where('id', $bookmark->id)->update([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $bookmark->tags()->attach($i % 3 === 0 ? $laravel->id : $vue->id);
        }

        // One extra row used by the search tests: the needle lives in the URL,
        // not in the title. Backdated so it sorts last.
        $hidden = Bookmark::create([
            'title' => 'Nothing to see here',
            'url' => 'https://needle.test/found-me',
            'domain' => 'needle.test',
            'is_pinned' => false,
        ]);
        $hidden->tags()->attach($vue->id);

        DB::table('bookmarks')->where('id', $hidden->id)->update([
            'created_at' => date('Y-m-d H:i:s', $base),
            'updated_at' => date('Y-m-d H:i:s', $base),
        ]);

        DB::table('tags')->where('id', $laravel->id)->update(['bookmarks_count' => 15]);
        DB::table('tags')->where('id', $vue->id)->update(['bookmarks_count' => 31]);
    }

    /**
     * @return array<int, string>
     */
    private function titles(array $payload): array
    {
        return array_column($payload['data'], 'title');
    }

    public function test_it_lists_pinned_bookmarks_first_then_newest_first(): void
    {
        $payload = $this->getJson('/api/bookmarks')->assertOk()->json();

        $titles = $this->titles($payload);

        $this->assertSame(
            ['Bookmark 40', 'Bookmark 3'],
            array_slice($titles, 0, 2),
            'Pinned bookmarks must come first, and pinned rows are ordered by recency between themselves.'
        );

        $this->assertSame(
            ['Bookmark 45', 'Bookmark 44', 'Bookmark 43'],
            array_slice($titles, 2, 3),
            'After the pinned rows, the newest bookmarks come first.'
        );
    }

    public function test_pagination_slices_the_globally_ordered_list(): void
    {
        $first = $this->getJson('/api/bookmarks?page=1')->assertOk()->json();
        $second = $this->getJson('/api/bookmarks?page=2')->assertOk()->json();

        $this->assertCount(20, $first['data']);
        $this->assertCount(20, $second['data']);

        // Page 1 = 2 pinned (40, 3) + the 18 newest unpinned, which runs
        // 45..27 skipping the already-shown pinned rows.
        // Page 2 therefore starts at 26 and runs down to 7.
        $this->assertSame('Bookmark 26', $this->titles($second)[0]);
        $this->assertSame('Bookmark 7', $this->titles($second)[19]);

        $this->assertEmpty(
            array_intersect($this->titles($first), $this->titles($second)),
            'The same bookmark must not appear on two pages.'
        );
    }

    public function test_search_matches_the_title_or_the_url(): void
    {
        $payload = $this->getJson('/api/bookmarks?search=needle')->assertOk()->json();

        $this->assertSame(1, $payload['meta']['total']);
        $this->assertSame('Nothing to see here', $this->titles($payload)[0]);
    }

    public function test_search_is_case_insensitive(): void
    {
        $payload = $this->getJson('/api/bookmarks?search=NEEDLE')->assertOk()->json();

        $this->assertSame(1, $payload['meta']['total']);
    }

    public function test_search_and_tag_filters_are_combined_with_and(): void
    {
        // "Bookmark 1" matches titles 1 and 10-19 by substring. Of those,
        // only the multiples of 3 carry the laravel tag: 12, 15 and 18.
        $payload = $this->getJson('/api/bookmarks?search=Bookmark+1&tag=laravel')->assertOk()->json();

        $this->assertSame(
            3,
            $payload['meta']['total'],
            'Both filters must apply at the same time, not either/or.'
        );

        $this->assertSame(
            ['Bookmark 18', 'Bookmark 15', 'Bookmark 12'],
            $this->titles($payload)
        );
    }

    public function test_meta_total_is_the_filtered_count_not_the_page_size(): void
    {
        $payload = $this->getJson('/api/bookmarks?tag=laravel')->assertOk()->json();

        $this->assertSame(15, $payload['meta']['total']);
        $this->assertSame(1, $payload['meta']['last_page']);
        $this->assertCount(15, $payload['data']);
    }

    public function test_it_never_reads_the_whole_bookmarks_table_into_memory(): void
    {
        $statements = [];

        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        $this->getJson('/api/bookmarks')->assertOk();

        $reads = array_filter($statements, function (string $sql): bool {
            $touchesBookmarks = str_contains($sql, 'from "bookmarks"')
                || str_contains($sql, 'from `bookmarks`');

            // Aggregates are allowed to scan; they do not hydrate models.
            return $touchesBookmarks && ! str_contains($sql, 'count(');
        });

        $this->assertNotEmpty($reads, 'Expected the endpoint to query the bookmarks table.');

        foreach ($reads as $sql) {
            $this->assertStringContainsString(
                'limit',
                $sql,
                "This query loads every bookmark row before filtering:\n\n{$sql}\n\n".
                'Filtering, ordering and pagination all belong in the database.'
            );
        }
    }
}
