<?php

namespace Database\Seeders;

use App\Models\Bookmark;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds a realistic-sized LinkStash database.
 *
 * The data is generated from a fixed random seed, so every candidate and
 * every reviewer gets byte-identical rows.
 */
class LinkStashSeeder extends Seeder
{
    private const BOOKMARK_COUNT = 25000;

    private const PINNED_COUNT = 25;

    /** Unix timestamp for 2026-01-05 12:00:00 UTC -- the "newest" bookmark. */
    private const NEWEST_AT = 1767614400;

    private const TAGS = [
        'Architecture', 'Career', 'CSS', 'Databases', 'DevOps', 'Frontend',
        'Laravel', 'Performance', 'Security', 'Testing', 'Tooling', 'Vue',
    ];

    private const ADJECTIVES = [
        'A Practical Guide to', 'Understanding', 'Debugging', 'Rethinking',
        'Getting Started with', 'Advanced', 'Notes on', 'Why We Moved Off',
        'Benchmarking', 'A Deep Dive into', 'Scaling', 'Migrating to',
        'The Case Against', 'Everything I Know About', 'Refactoring',
        'Profiling', 'Hardening', 'Automating', 'Simplifying', 'Taming',
    ];

    private const TOPICS = [
        'Postgres', 'MySQL', 'Redis', 'Kubernetes', 'Docker', 'Laravel Queues',
        'Eloquent', 'Nuxt', 'Vue Reactivity', 'Vite', 'TypeScript', 'Tailwind',
        'CSS Grid', 'Web Components', 'HTTP Caching', 'OAuth', 'JWT', 'CSRF',
        'Rate Limiting', 'Feature Flags', 'CI Pipelines', 'Terraform',
        'Observability', 'Structured Logging', 'N+1 Queries', 'Database Indexes',
        'Full Text Search', 'Event Sourcing', 'Message Queues', 'Cron Jobs',
        'PHPUnit', 'Playwright', 'Static Analysis', 'Code Review',
        'Technical Debt', 'On-Call Rotations', 'Postmortems', 'Pair Programming',
        'Monorepos', 'Semantic Versioning',
    ];

    private const SUFFIXES = [
        'in Production', 'the Hard Way', '— Part 1', '— Part 2', 'for Beginners',
        'at Scale', 'without the Hype', 'in 2026', ': Lessons Learned',
        'for Small Teams', '', '', '',
    ];

    private const DOMAINS = [
        'github.com', 'stackoverflow.com', 'laravel-news.com', 'nuxt.com',
        'developer.mozilla.org', 'martinfowler.com', 'planetscale.com',
        'blog.cloudflare.com', 'css-tricks.com', 'dev.to', 'news.ycombinator.com',
        'smashingmagazine.com', 'web.dev', 'engineering.fb.com',
    ];

    public function run(): void
    {
        if (Bookmark::query()->exists()) {
            $this->command?->info('LinkStash data already present — skipping seed.');

            return;
        }

        mt_srand(20260105);

        $this->command?->info('Seeding tags...');
        $tagIds = $this->seedTags();

        $this->command?->info('Seeding '.number_format(self::BOOKMARK_COUNT).' bookmarks...');
        $this->seedBookmarks($tagIds);

        $this->command?->info('Recomputing denormalised tag counters...');
        $this->recountTags();

        $this->summarise();
    }

    /**
     * @return array<int, int>
     */
    private function seedTags(): array
    {
        $now = date('Y-m-d H:i:s', self::NEWEST_AT);

        $rows = [];

        foreach (self::TAGS as $name) {
            $rows[] = [
                'name' => $name,
                'slug' => strtolower(str_replace([' ', '+'], ['-', 'plus'], $name)),
                'bookmarks_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('tags')->insert($rows);

        return DB::table('tags')->orderBy('id')->pluck('id')->all();
    }

    /**
     * @param  array<int, int>  $tagIds
     */
    private function seedBookmarks(array $tagIds): void
    {
        // Which rows get pinned. Spread across the whole set so that pinning
        // and recency genuinely disagree.
        $pinned = [];
        while (count($pinned) < self::PINNED_COUNT) {
            $pinned[mt_rand(1, self::BOOKMARK_COUNT)] = true;
        }

        $bookmarkRows = [];
        $pivotRows = [];
        $tagCount = count($tagIds);

        for ($id = 1; $id <= self::BOOKMARK_COUNT; $id++) {
            $adjective = self::ADJECTIVES[mt_rand(0, count(self::ADJECTIVES) - 1)];
            $topic = self::TOPICS[mt_rand(0, count(self::TOPICS) - 1)];
            $suffix = self::SUFFIXES[mt_rand(0, count(self::SUFFIXES) - 1)];
            $domain = self::DOMAINS[mt_rand(0, count(self::DOMAINS) - 1)];

            $title = trim($adjective.' '.$topic.' '.$suffix);
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
            $slug = trim($slug, '-');

            // Newest bookmark first: id 1 is the oldest, id 25000 the newest.
            $createdAt = self::NEWEST_AT - ((self::BOOKMARK_COUNT - $id) * 3600);

            $bookmarkRows[] = [
                'id' => $id,
                'title' => $title,
                'url' => 'https://'.$domain.'/'.$slug.'-'.$id,
                'domain' => $domain,
                'description' => 'Saved from '.$domain.'. '.$topic.' notes for the team.',
                'is_pinned' => isset($pinned[$id]) ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s', $createdAt),
                'updated_at' => date('Y-m-d H:i:s', $createdAt),
            ];

            $howMany = mt_rand(1, 3);
            $chosen = [];

            while (count($chosen) < $howMany) {
                $chosen[$tagIds[mt_rand(0, $tagCount - 1)]] = true;
            }

            foreach (array_keys($chosen) as $tagId) {
                $pivotRows[] = ['bookmark_id' => $id, 'tag_id' => $tagId];
            }

            if (count($bookmarkRows) >= 500) {
                DB::table('bookmarks')->insert($bookmarkRows);
                $bookmarkRows = [];
            }

            if (count($pivotRows) >= 1000) {
                DB::table('bookmark_tag')->insert($pivotRows);
                $pivotRows = [];
            }
        }

        if ($bookmarkRows !== []) {
            DB::table('bookmarks')->insert($bookmarkRows);
        }

        if ($pivotRows !== []) {
            DB::table('bookmark_tag')->insert($pivotRows);
        }
    }

    private function recountTags(): void
    {
        $counts = DB::table('bookmark_tag')
            ->select('tag_id', DB::raw('count(*) as aggregate'))
            ->groupBy('tag_id')
            ->pluck('aggregate', 'tag_id');

        foreach ($counts as $tagId => $count) {
            DB::table('tags')->where('id', $tagId)->update(['bookmarks_count' => $count]);
        }
    }

    private function summarise(): void
    {
        $bookmarks = DB::table('bookmarks')->count();
        $pinned = DB::table('bookmarks')->where('is_pinned', true)->count();
        $pivot = DB::table('bookmark_tag')->count();

        $this->command?->info(sprintf(
            'Done. %s bookmarks (%d pinned), %s tag links across %d tags.',
            number_format($bookmarks),
            $pinned,
            number_format($pivot),
            DB::table('tags')->count(),
        ));
    }
}
