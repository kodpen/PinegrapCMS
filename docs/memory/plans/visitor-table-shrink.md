---
name: visitor-table-shrink
description: Plan (owner report 2026-11-13, was issue #9130): the visitor table that never gets smaller by itself where PHP cannot read the database disk, and the faults of the Visitor records card
---

## What is asked?
- Owner (2026-11-13), with a picture of the Visitor records card (about 4.96 million records, a file of about 1.3 GB, free space 8 MB, every record inside the 90 days kept): "sitelerin birçoğunda ziyaretçi kayıtlarını temizleyi de denedim, küçültü de tarih ayarlamayıda. ilk baştan beri tablo hiçbir seçenekte küçülmedi. bu motorda bir sorun var".
- Today's rules: `visitors/visitor-retention-and-cleanup.md`, `visitors/visitor-table-size-figures.md`.

## What was found? (sandbox of v2026.4.10, MariaDB 10.11, 5,000,000 visits)
- Not reproduced: a table no option makes smaller; in every state tried one button did ("Shrink now" 784 to 664 MB; a shorter period, then a dropped month, 949 to 785 MB).
- A table that is not split: deleting rows never changes the file; only the split or a rebuild gives the room back.
- Where PHP cannot read the free space of the database's disk (`disk_free_space` off, the data directory outside `open_basedir`, the database on another host) neither the split nor the rebuild starts by itself: `waiting`, reason `disk_unknown` (`pg_visitor_move_hold_reason()`, `pg_visitor_shrink_hold_reason()`). Most shared hosting is set up so, which fits "on many sites". Pressed by hand it worked (240 to 30 MB).
- Every visit inside the kept period: "Clean" has nothing to do; the file follows a shorter period only when a whole month goes or by "Shrink now".
- While the rollup backfill is behind, a run removes only what it passed (20,000 to 50,000 rows).
- Not known: which of these states his sites are in; the card's own text on one of them names it.

## Which steps?
- VT1 (M, done #9396) The automatic path does not hang on a disk it cannot read: with the free space unknown, the split and the rebuild still go ahead when the rows left are small against the file (the copy needs only their room), under a bound the step sets and tries at 5 million rows; after a failed try it stops and the card says why, leaving the button.
  - The room asked for (three times the rows plus 1 GB today, "about 1.2 GB" for rows that fit in 30 MB) is brought down to what the copy needs.
- VT2 (S) The card says what is true: the months named right (they read one too late), "being split" not "moved to InnoDB" for an InnoDB table, why "Clean visitor records now" is disabled, no "shrank" line when the size did not change, and no "Shrink now" where there is nothing to give back. With every visit inside the kept period it says so and what a shorter period does.
- VT3 (S) Close-out: the rules into `visitors/`, this plan deleted.

## What does every step keep?
- No visit inside the kept period is deleted, no table or column is added, nothing runs longer than the general job's share. A rebuild never starts where the bound cannot show there is room.
- VT2 shows real screenshots of the card in the agent channel before its PR goes ready. Claim as `[plan:visitor-table-shrink] <step> — <topic>`.
