---
name: decisions-made-schema-42
description: Decisions list, schema part 42 (2026-11-20 on): the visitor journey's four tables (lead_browsers, lead_visits, contact_journey, contact_scores), marketing consent, 13 months, tying back (poll 194)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-41.md`.

## Does the visitor journey get tables? (owner, 2026-11-20, agent channel poll 194, channel message 10747)
- "Evet, dört tablo eklensin": `lead_browsers`, `lead_visits`, `contact_journey`, `contact_scores`. No column is added to `visitors` or `contacts`.
- Asked with it and so binding: a visit is tied only in a browser that said yes to the cookie notice's marketing category; the journey is kept 13 months by default (a setting); the person's export and erase cover it.
- With `lead_visits`, the visits after the consent are tied back when the person fills a form, so the first touch (page, campaign) is known.
- Not chosen: three tables without `lead_visits` (only visits after the tie, the first touch limited to that day's visit).
- The plan is the owner's yes to poll 183 (visitor journey); step LJ1b.

## Which columns does lead_browsers have? (architect, 2026-11-20)
- `id` BIGINT UNSIGNED AUTO_INCREMENT; `key_hash` CHAR(64) ascii, sha256 of the cookie's random key, which is kept nowhere else; `contact_id` INT UNSIGNED, 0 until tied.
- `consent_at`, `tied_at`, `last_seen_at`, `created_at` INT UNSIGNED.
- Keys: unique (`key_hash`); `contact_id`; `last_seen_at`.
- Consent taken back: the row goes with its visits not tied to a contact; tied visits stay with their contact until the keep period or the person's erase.

## Which columns does lead_visits have? (architect, 2026-11-20)
- `id` BIGINT UNSIGNED AUTO_INCREMENT; `browser_id` BIGINT UNSIGNED; `contact_id` INT UNSIGNED, 0 until tied (a key that moves to another contact leaves earlier visits with theirs); `visitor_id` BIGINT UNSIGNED, the `visitors` row while it lasts.
- Its own copy of what the journey needs, since `visitors` is trimmed by month: `started_at` INT UNSIGNED; `channel` VARCHAR(20); `campaign` VARCHAR(100); `referrer_host` VARCHAR(150); `landing_page` VARCHAR(255); `pages` TEXT NULL, the pages seen with their seconds as JSON, capped in code.
- Keys: (`browser_id`, `started_at`); (`contact_id`, `started_at`); `started_at`; `visitor_id`.

## Which columns does contact_journey have? (architect, 2026-11-20)
- The journey's own rows; forms, orders and bookings are read from their tables and merged by time. `id` BIGINT UNSIGNED AUTO_INCREMENT; `contact_id` INT UNSIGNED; `kind` TINYINT UNSIGNED (kinds in code: tie, download, campaign click, became a lead, became a customer); `at` INT UNSIGNED.
- `visit_id` BIGINT UNSIGNED, 0 for none; `ref_id` INT UNSIGNED, the record by kind; `detail` VARCHAR(255), a page or file name, never what a form held; `first_visit_id`, `last_visit_id` BIGINT UNSIGNED, the touches on a lead or customer row.
- Keys: (`contact_id`, `at`); (`kind`, `at`); `at`.

## Which columns does contact_scores have? (architect, 2026-11-20)
- One row per contact. `contact_id` INT UNSIGNED, the primary key; `interest`, `fit` TINYINT UNSIGNED, 0 to 100; `reasons` TEXT NULL (top three each), `topics` TEXT NULL (top five), `points` TEXT NULL (each kind's fading points and their time), all JSON.
- `hot` TINYINT UNSIGNED, 1 while over the threshold; `hot_at`, `returned_at`, `last_visit_at`, `touched_at`, `scored_at` INT UNSIGNED.
- Keys: (`hot`, `interest`); `touched_at`.

## What else holds?
- Every column NOT NULL with its default but the TEXT ones. InnoDB, utf8mb4_unicode_ci. No IP, user agent or form content in any of the four.
- A user with a panel role gets no row. The keep period trims `lead_visits` and `contact_journey` by `started_at` and `at`.
- The step's worker writes the columns into its PR body and asks the architect before it is marked ready.
