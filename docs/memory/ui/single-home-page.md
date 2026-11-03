---
name: single-home-page
description: The site keeps one home page: Edit Page and the visual designer take the flag from every other page when they set it (pg_page_home_claim), page_home is written 'yes' or 'no', never ''
module: page_home
---

# One home page

## What is the rule?

A site has one home page. Saving a page as the home page takes
`page_home = 'yes'` from every other page. Rotating among several home
pages is not a feature: `get_page.php` still reads the root with
`ORDER BY RAND() LIMIT 1`, but it reaches one row (decided in #5266).

## Who writes the flag?

All through `includes/fn/page_home.php`:

- `edit_page.php`: staff (role < 3) write `pg_page_home_value($_POST['home'])`;
  on `yes`, `pg_page_home_claim()` runs after the page's own UPDATE and the
  names it returns become a notice ("No longer the home page: …") through
  `pg_page_rename_notice()`, so a save bound for a site page comes back to
  Edit Page to show it.
- `pg_designer_save_page()` (visual designer, API, importers): `$home_str`
  is `yes` or `no`; on `yes` the claim runs after the UPDATE/INSERT.
- The template apply in `designer.php` claims for the page marked home.
- `add_page.php` writes the literal `no`; the recycle bin
  (`includes/explorer/recycle_names.php`) clears the flag with `'no'`.

## Why never ''?

`page_home` is `ENUM('no','yes')`: a strict server refuses `''`, a loose one
stores the invalid empty member. Before #5266 Edit Page wrote the unchecked
box as `''` and the designer wrote `''` for a non-home page and for the
other pages it cleared. A side effect of `''`: the designer's carried-page
check compared `''` with the stored `no`, so a non-home page sent only for a
shared component always counted as changed.

## What about running sites with several home pages?

No migration: they keep them until someone saves a page as the home page.

## Where is it tested?

`tests/page_home_test.php`: the value, the claim statement, and a source
check that both writers call the claim and the designer writes no `''`.
