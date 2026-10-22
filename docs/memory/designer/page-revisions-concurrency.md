---
name: page-revisions-concurrency
description: Two writes of one page's revision at the same moment: which errors they meet, which are tried again and how often, why a revision was lost silently before #827
---

## What happens when two saves of one page write a revision at once?
- `pg_page_revision_record()` takes the number in the INSERT (`INSERT … SELECT COALESCE(MAX(revision_no),0)+1 FROM page_revisions WHERE page_id = …`), so the two never read the same `MAX()` unseen. The second fails instead: on the unique key `(page_id, revision_no)` (1062) or, more often, on the first one's locks, since the number is read from the table being written: InnoDB deadlock (1213), lock wait timeout (1205), MariaDB "Failed to read auto-increment value" (1467).
- The realistic trigger is the external API or automation writing one page in parallel; two editors on one page are kept apart by the designer's lock.

## Which failures are tried again?
- `pg_page_revision_retry_delay($errno, $attempt, $duplicate_ends)` (pure, tested): 1062 at once (the next try reads the next number); 1213 and 1467 after 20, 40, 60 ms plus a random share of the same size (two writers that collided do not meet again in step); 1205 once only, as it has already waited `innodb_lock_wait_timeout`; four statements at most; any other error ends it.
- `pg_page_revision_seed()` (revision 1 of a page with none) asks the same rule with `$duplicate_ends = true`: a 1062 there means the other save wrote revision 1, not an error, and is not logged.
- The number of the written row is read back by `mysqli_insert_id()` (#899), not `MAX()`.

## What was wrong before (#827)?
- Only 1062 was tried again, once. Lock errors ended the loop at the first statement: the page was saved but its revision was not, only a "Page revisions: writing the revision of page … failed" line in the error log showed it. Measured on MariaDB 10.11: 11 of 200 revisions lost with two writers, 39 of 240 with six.
- An atomic counter (`UPDATE … SET n = LAST_INSERT_ID(n + 1)`) would need a column, i.e. a schema step; not done.

Related: docs/memory/designer/page-revisions.md
