# LinkStash — technical exercise

Hello, and thanks for taking the time.

LinkStash is a small internal tool: a shared pile of bookmarks that a team saves,
tags and searches. It works, mostly. Your job is to fix one thing that is wrong
with it and add one thing that is missing.

**Please spend no more than 60 minutes on this.** We mean that — we would rather
see 45 minutes of clear thinking than three hours of polish. If the clock runs
out, stop where you are and write down what you did not get to.

---

## Setup

You need Docker with Compose. Nothing else.

```bash
docker compose up --build
```

The first run pulls images, installs dependencies and seeds ~25,000 bookmarks,
so give it a few minutes. When it settles you have:

| | |
|---|---|
| Web UI | <http://localhost:3000> |
| API | <http://localhost:8000/api> |
| MySQL | `localhost:33061`, database `linkstash`, user `linkstash` / `secret` |

Handy commands:

```bash
docker compose exec api php artisan test      # run the backend test suite
docker compose exec api php artisan tinker    # poke at the database
docker compose exec db mysql -ulinkstash -psecret linkstash
docker compose down -v                        # nuke everything, including data
```

Both `api/` and `web/` are mounted into their containers, so edit files on your
machine with your normal editor and the changes apply live.

---

## The codebase

```
api/                              Laravel 12
  app/Http/Controllers/Api/       BookmarkController, TagController
  app/Models/                     Bookmark, Tag
  database/migrations/            schema
  database/seeders/               LinkStashSeeder — the 25k rows
  routes/api.php
  tests/Feature/BookmarkListTest.php
web/                              Nuxt 4
  app/pages/index.vue             the whole UI, one file
  app/composables/useApi.ts       typed wrapper around $fetch
```

Three tables: `bookmarks`, `tags`, and a `bookmark_tag` pivot. Note that `tags`
carries a `bookmarks_count` column — the sidebar reads that column directly
rather than counting the pivot table on every request.

The list endpoint answers in this shape, and the frontend depends on it:

```json
{
  "data": [ { "id": 1, "title": "…", "url": "…", "is_pinned": false, "tags": [] } ],
  "meta": { "current_page": 1, "per_page": 20, "total": 25000, "last_page": 1250 }
}
```

---

## Task 1 — The bookmark list is wrong

Open <http://localhost:3000> and use it for a minute before reading on.

The list is supposed to show **pinned bookmarks first, then the most recently
saved first**, twenty per page, with search and the tag filter applied together.
It does not do that, and it is slower than it has any right to be.

Fix `GET /api/bookmarks`.

`api/tests/Feature/BookmarkListTest.php` is the definition of done. Some of
those tests already pass — treat those as a safety net rather than as noise, and
do not weaken an assertion to get a green tick.

```bash
docker compose exec api php artisan test
```

---

## Task 2 — Bookmarks cannot be archived

People want to get old links out of the way without deleting them. There are no
tests for this one; the spec below is the spec.

1. A bookmark can be **archived** and **unarchived**. Archiving records *when* it
   happened — we want to be able to see that later.
2. Archived bookmarks do not show up in the normal list. `GET /api/bookmarks`
   with `?archived=1` returns only the archived ones.
3. **The tag counts in the sidebar must not include archived bookmarks.** They
   are expected to be correct immediately after an archive or unarchive — not on
   the next deploy, not after a nightly job.
4. In the UI: an archive control on each bookmark, and a way to see the archived
   ones. The tag counts should visibly react.

If you are running short, do points 1–3 properly and leave the UI half-done
rather than the other way round. Say so in `DECISIONS.md`.

---

## Task 3 — `DECISIONS.md`

Fill in `DECISIONS.md`. It is short on purpose. It matters as much as the code:
we will sit down with you and talk through it.

---

## Ground rules

- **Use AI if that is how you work.** Copilot, Claude, ChatGPT, whatever. We are
  not testing whether you can write a `where` clause from memory. We *will* ask
  you to walk us through your changes line by line, explain why you chose what
  you chose, and talk about what you would do differently — so make sure you
  understand and agree with everything you submit.
- Commit as you go, with real messages. We read the history.
- Do not restructure things that are not in your way. Small diff, clear intent.
- If something in this brief is ambiguous, pick an interpretation, note it in
  `DECISIONS.md` and move on. Do not wait to ask.

## How to submit

Push to a branch or send us a zip / patch — whatever is easiest. Include your
`DECISIONS.md`.

## What we are looking at

Roughly, in order: does it work; did you put the work in the right layer; can
you explain your reasoning; is the diff something we would be happy to review on
a normal Tuesday. Not: cleverness, coverage percentage, or how much you managed
to cram into an hour.
