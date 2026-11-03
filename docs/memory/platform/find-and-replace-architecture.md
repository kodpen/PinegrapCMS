---
name: find-and-replace-architecture
description: find_and_replace.php table definition shape ($all_table_defs fields), url_raw URL rules, ECOMMERCE/ADS gating, and the refusals that keep a replace from writing where it should not.
module: text_search
---

## How are searchable tables defined in find_and_replace.php?
Each entry of the `$all_table_defs` array has these fields:
```php
[
    'label'        => lang('...'),       // display name
    'table'        => 'tablo_adi',       // DB table name
    'id_column'    => 'id',              // PK column
    'name_column'  => 'name',            // display-name column
    'extra_select' => [],                // extra SELECT columns
    'extra_join'   => '',                // JOIN expression
    'base_where'   => '',                // fixed WHERE condition (optional)
    'columns'      => ['content'],       // columns to search
    'edit_url'     => 'edit_x.php?id={id}', // {column_name} placeholder
    'url_raw'      => false,             // true = OUTPUT_PATH . url (frontend), false = backend
    'touch'        => ['x_timestamp', 'x_user'], // last-modified columns a replace sets
    'max_length'   => ['short_description' => 100], // columns shorter than LONGTEXT
]
```

## How is the result URL built?
- `url_raw = true` → `OUTPUT_PATH . url` (frontend page, used for pregion).
- `url_raw = false` → `OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . url`.

## Which tables are feature-gated?
- ECOMMERCE check applies to the `products` and `product_groups` tables.
- ADS check applies to the `ads` table.

## What does the screen refuse before it writes?
- No Scope box ticked: `pg_find_replace_scope()` reads a missing `scope[]` as no table. Before, it meant every table, so "Deselect all" then Find & Replace wrote everywhere (#5876).
- A regular expression PCRE cannot compile, one that matches empty text (`x*`, `^`, `a?`: `pg_find_replace_regex_error()`), one with a zero-length match in a row (`\b`, a lookahead), or one the database refuses (`[[:<:]]` on MySQL 8: the query goes through `db_q()` and its `false` comes back as "Invalid regular expression."). Before, an empty match put the replacement between every two characters (#5880) and a refused query ended on the panel-less error page (#5877).
- A value longer than its `max_length`: the row is left as it was and named in a notice, as `mass_edit.php` does; the session is not strict and MySQL would cut it (#5878). All new values are worked out before the first UPDATE.

## How is a regular expression matched?
- `pg_find_replace_regex()` wraps it in `/…/u` and escapes every `/` the pattern does not escape, so `</p>` and `a/b` work.
- SQL only fetches candidates: `col REGEXP ?` in the column's case-insensitive collation even when Case sensitive is on (MySQL 8 refuses `REGEXP BINARY` on utf8mb4); the case-sensitive PCRE count drops the other rows.

## What keeps the form?
- The Scope boxes are liveform fields (ticked by default only on a first draw), so a refused search comes back with the boxes the user ticked (#5879).
- The results screen does not clear the liveform session (the back arrow opens the same search) and has its own Find & Replace button posting the same values. Both Find & Replace buttons ask first (`data-confirm-content`).
- A replace sets each table's `touch` columns, so the lists' Last Modified shows the changed rows (#5883). Submitted form rows have none.
