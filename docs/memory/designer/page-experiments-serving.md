---
name: page-experiments-serving
description: Serving a page experiment variant: body swap, the bucket at render and in the router, one cache copy per variant, private headers, views into experiment_hourly
module: experiments
---

## Where is the variant chosen?
- `get_page.php`, just before `get_page_content()` of a page by its address: `pg_experiment_serve($page_id, $view_page_mode, $email_mode)`. No running experiment, an excluded request (`pg_experiment_request_excluded()`) or an e-mail body: null, the live page. Otherwise `pg_experiment_pick()` keeps the cookie's key while its variant has a weight, else draws, and a draw sends `pg_exp`. The result is kept for the request in `pg_experiment_served()` (experiment, page, key, variant id, draft, `control_key`, weights).

## What does a variant change?
- The body only: `get_page_content()` asks `pg_experiment_body_page_id($page_id)` and reads the draft's `page_tree_code` (`pg_experiment_variant_code()`); a draft gone or empty falls back to the live body. Title, meta tags, structured data, canonical, style and address are read from the live row as before; a translated body is drawn from the draft's id.
- Another page drawn in the same request keeps its own body (`pg_experiment_body_page_for()` matches the page id).
- The draft's own address answers nobody but staff: it stays in the private Drafts folder (`page-drafts.md`).

## How does the page cache keep it?
- One copy per variant: the control under the address's key, every other variant under `pg_page_cache_variant_key($key, $experiment_id, $variant_key)` (`pg_page_cache_experiment_store()`). Each copy's meta line carries `exp` = id, control key, weights (`pg_page_cache_experiment_meta()`).
- A page with a running experiment drawn for a request that joined none (a crawler, staff) is not stored, or it would answer for every variant.
- Router (`pg_page_cache_lookup()`): the address's copy names the experiment at any age; `pg_page_cache_experiment_route()` reads the bucket or draws from the copy's weights (no query; `router_page_cache_module()` loads `experiments.php`), sends `pg_exp` (Secure from the request's scheme), and looks up the variant's copy, with the usual fresh/stale/lock rules. A crawler (`pg_page_cache_request_automated()`, the firewall's verdict) gets the control copy. No control copy on disk yet: a normal miss, and the render draws.
- `pg_page_cache_apply()` strips Set-Cookie, then `pg_experiment_bucket_resend()` sends a bucket this request wrote (router or render) again (`pg_experiment_bucket_sent()`).
- An experiment page answers `Cache-Control: private, max-age=0` drawn and from a copy (`pg_page_cache_headers($ttl, true)`): a CDN would keep one variant for all.

## How are views counted?
- A drawn page: `get_page.php` calls `pg_experiment_count_view()` (not for HEAD): one `INSERT … ON DUPLICATE KEY UPDATE views = views + 1` on `pg_experiment_hourly_key(day, hour, experiment, variant_id)`.
- A cached copy: the beacon payload has `exp_id`, `variant_id`; it is written into every experiment copy, also with visitor tracking off. `page_view.php` counts it only while `pg_experiment_beacon_view()` finds that variant in the page's running experiment, before the tracking switch; automated requests are dropped as before.

## What drops the variant copies?
- Nothing of its own: every copy is in the generation folder, so the designer's save of the live page or the draft raises it (`platform/page-cache-invalidation.md`). The experiment's own writes (start, pause, end, weights; PX4's sheet) must be write rows of `pg_page_cache_panel_dirties()` too.

Related: docs/memory/designer/page-experiments-model.md, docs/memory/platform/page-file-cache.md, docs/memory/visitors/page-view-beacon.md
