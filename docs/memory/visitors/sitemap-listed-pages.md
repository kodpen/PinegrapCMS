---
name: sitemap-listed-pages
description: Which pages sitemap.xml lists bare: the page types (pg_sitemap_page_types()), designed pages whose system widget stands in for an unlisted type, the home page exception, detail pages vs their records
module: seo
---

## Which page types does sitemap.xml list?
- `pg_sitemap_page_types()` (`includes/fn/seo.php`): standard, folder view, photo gallery, custom form, form list view, form view directory, calendar view, catalog, express order, order form, shopping cart, search results. `get_sitemap_info()` reads them with `db_q()` placeholders. Every other type (error, login, logout, registration, account and checkout steps, form item view, catalog detail, calendar event view) is never listed bare, whatever its site map switch says.

## And a page built in the visual editor?
- It is `standard`, so the type list alone listed it. Since #2178 a designed page carrying a system widget that stands in for an unlisted type is left out too: `pg_sitemap_unlisted_widget_types(pg_sw_page_type_widgets())` = `error_page`, `form_item_view`, `catalog_item_view`, plus the widget of each legacy type in `pg_sw_page_type_widgets()` that is not listed (logout, login_form, registration, forgot_password, account and checkout steps, calendar_event_view...). Pages found with `pg_sw_widget_pages()`.
- Symptom it fixed: `/404`, `/giris-yap`, `/kayit-ol`, `/cikis-yap` and the bare record page (`/blog-detay`) in sitemap.xml.
- The home page is always listed, even with such a widget on it.
- Widgets standing in for a listed type (search_results, shopping_cart, express_order, order_form, calendar_view) keep their page listed.

## Do the records of a detail page still get listed?
- Yes, under their own addresses: designed lists list their records when the detail page is public and not noindex (its site map switch is not read); the legacy form list view lists them when the form item view page has the switch on (`page-noindex-switch.md`). So one switch no longer governs both the bare page and its records on designed pages.
- Not a list whose filters compare a field with the viewer (no fixed value, dynamic value `viewer` / `viewers email address`): designed lists by `pg_sw_filters_compare_viewer()` over the list's and its record view's filters, legacy ones by `pg_sitemap_legacy_filters_compare_viewer()` (`includes/fn/seo.php`). The kept sitemap is drawn by whoever asks first and served to everyone: a crawler's draw listed every record sent while signed out, a panel save's (`update_sitemap_and_ping()`) the saving person's own records. Same rule as the search index (`pg_sw_search_index_eligible()`).

## Does the Pages screen count the same pages?
- Its SEO summary (`view_pages.php`, `pg_seo_pages_summary()`, #2042) counts each number under its list's condition: archived pages out everywhere, the site map counts public pages only. Before, it summed the whole page table (private, archived and binned-folder pages too), so "N in the site map" and "N missing a title" disagreed with the lists they open.
- It still counts pages with the switch on, not sitemap.xml's `<loc>` lines (records, products, unlisted types are not page rows).

Related: docs/memory/visitors/page-noindex-switch.md, docs/memory/visitors/ai-crawlers-llms-txt.md
