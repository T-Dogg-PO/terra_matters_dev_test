# Decisions

Keep this short — bullet points are fine. We care about the reasoning, not the
prose.

---

## 1. The bookmark list

What was actually wrong with it, and what did you change?

> The first problem to fix was with populating the database. I was running into an `SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails` error. The fix was to make sure the bookmarks table is populated before attempting to populate the bookmark_tag table.
> The problem with loading the bookmarks list was that we started by loading the entire bookmarks table with get(), then filtering the list in PHP. This approach is slow and memory heavy.
> I removed the get() call, instead doing the bookmarks filtering and pagination with Laravel's query methods. This resulted in a bookmarks list that loaded, is sorted correctly and is paginated.
> Finally, I fixed the tests by removing the non-existent extra test suite.

Was there anything you noticed but deliberately did not fix? Why?

> I didn't like the use of whereRaw, orWhereRaw and orderByRaw. This was a quick and easy way for me to implement the SQL queries, but is potentially vulnerable to injection attacks. I make sure to parameterize the expression, but I would like to replace it. However, I was running into issues with the query builder (an experience issue on my part) and ran out of time to fix it.

---

## 2. Keeping the tag counts correct

`tags.bookmarks_count` is a denormalised counter: the real answer lives in the
`bookmark_tag` pivot table, but we store a copy on `tags` so the sidebar does not
have to aggregate on every page load. Once bookmarks can be archived, that copy
can drift from the truth.

**Which approach did you take, and why?**

> I did not have time for this part of the task. However, this is how I'd approach it:
1. Add an `archived_at` column to the bookmarks table that can be either a timestamp or null
2. Update the logic in `BookmarkController` to display only archived or non-archived results dependent on the archived flag
3. Add an endpoint to `api.php` for archiving/un-archiving a bookmark, and archive/unarchive functions in `BookmarkController.php`. Archiving adds a timestamp to the `archived_at` column, un-archiving resets the column to null
4. Add logic for updating the sidebar immediately. While I'm not 100% sure on the implementation here, I'd suggest adding a `refreshTagCounts` function in `Bookmark.php` that will return only bookmarks without an archive timestamp. Then `refreshTagCounts` gets called in the controller functions written above.
5. Update the UI to include an archive/unarchive button on each bookmark, as well as a button on the sidebar to see the archived bookmarks.

**Name at least one approach you considered and rejected, and what made you
reject it.**

> I did not consider another approach here.

**We have 25,000 bookmarks today. At 2.5 million, what breaks first in the
approach you picked?**

> Because we are doing a new database query each time we archive/unarchive a bookmark that recounts the number of unarchived bookmarks for that tag, at a large number of bookmarks this process would take a long time and would eventually break.

---

## 3. Anything you interpreted, assumed or skipped

Ambiguities you resolved yourself, corners you cut, things you would do first if
you had another hour.

> Nothing other than the issues with the database population and the tests that I described above.

---

## 4. Tools

Which AI tools did you use, and what for? There is no wrong answer here — we ask
because it helps us have a better conversation about the code, not because we
are scoring it.

> I used Copilot to help me quickly narrow down the root cause of the issues, particularly helpful in the database population issue that I had.

Was there anywhere it steered you wrong, or suggested something you rejected?

> The first suggestion to fix the database population issue was overly complex and the AI tried to basically rewrite LinkStashSeeder. I thought this was overkill and so used the AI explinations to narrow down one specific change to make that fixed the problem.
