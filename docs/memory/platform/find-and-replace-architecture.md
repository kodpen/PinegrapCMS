---
name: find-and-replace-architecture
description: find_and_replace.php table definition shape ($all_table_defs fields), url_raw URL rules, and ECOMMERCE/ADS gating of tables.
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
]
```

## How is the result URL built?
- `url_raw = true` → `OUTPUT_PATH . url` (frontend page, used for pregion).
- `url_raw = false` → `OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/' . url`.

## Which tables are feature-gated?
- ECOMMERCE check applies to the `products` and `product_groups` tables.
- ADS check applies to the `ads` table.
