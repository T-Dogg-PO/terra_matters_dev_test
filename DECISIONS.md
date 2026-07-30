# Decisions

Keep this short — bullet points are fine. We care about the reasoning, not the
prose.

---

## 1. The bookmark list

What was actually wrong with it, and what did you change?

>

Was there anything you noticed but deliberately did not fix? Why?

>

---

## 2. Keeping the tag counts correct

`tags.bookmarks_count` is a denormalised counter: the real answer lives in the
`bookmark_tag` pivot table, but we store a copy on `tags` so the sidebar does not
have to aggregate on every page load. Once bookmarks can be archived, that copy
can drift from the truth.

**Which approach did you take, and why?**

>

**Name at least one approach you considered and rejected, and what made you
reject it.**

>

**We have 25,000 bookmarks today. At 2.5 million, what breaks first in the
approach you picked?**

>

---

## 3. Anything you interpreted, assumed or skipped

Ambiguities you resolved yourself, corners you cut, things you would do first if
you had another hour.

>

---

## 4. Tools

Which AI tools did you use, and what for? There is no wrong answer here — we ask
because it helps us have a better conversation about the code, not because we
are scoring it.

>

Was there anywhere it steered you wrong, or suggested something you rejected?

>
