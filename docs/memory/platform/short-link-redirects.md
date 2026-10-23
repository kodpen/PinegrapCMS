---
name: short-link-redirects
description: Redirects are short link options (2026.11.0, 11.0.12): answer 0/301/302, * patterns tried only at 404, loop checks on every entry point; names in short-link-names.md.
---

## Why are redirects short links and not a manager of their own?
- Owner decision 2026-10-19: a separate redirect table and screen (PR #654) was closed; the options moved onto short links, which already own the names at the root of the site (`check_name_availability()`).

## How does a short link answer?
- `short_links.redirect_code` 0: as always. A page, product group, product or file is shown at the short address; an address elsewhere is sent on (301 when permanent, 302 when timed or one-time, without the visitor's query string).
- 301 or 302: `Location` to the destination's own address (`pg_short_link_location()`, language prefix kept), the visitor's query string after the destination's own, the tracking code as `t=` first.
- A timed or one-time link answers 302 even when saved with 301 (`pg_short_link_status()`): a browser keeps a 301 for good.
- `router.php` answers an exact name itself, after the firewall like any page (`router_short_link_redirect()`), loading `includes/fn/short_link_redirects.php` alone: the router has no `functions.php` (`router-no-functions-php.md`) and the address builders call no `lang()`.

## What can a name be, and how does a request find its link?
- `short-link-names.md`: the name rule, a pasted old address as a name, the lookup as it came and in the stored form, IIS name recovery, how two names compare.

## How do * patterns work?
- `is_pattern = 1` for a name with `*`; the router never takes it as an exact name. It is tried only when the page is about to answer 404: `output_error()` and `error()` with 404 and `pg_sw_item_missing()` call `pg_short_link_not_found()`, so a live page always wins. GET and HEAD only, not while the SEO pass renders, not after output started, at most 5000 patterns.
- Most characters outside the stars first, then the oldest. `$1` to `$9` in a URL destination take what each `*` matched, encoded per segment; in an exact name's URL `$n` is plain text.
- A pattern always redirects: 0 is saved as 301.

## What do the screens show?
- `view_short_links.php`: a badge with the answer visitors get (`pg_short_link_status()`) and Wildcard for a pattern; a link to a file shows File and the file name.
- Names go into links and QR codes percent-encoded (`encode_url_path()`); a pattern has no Visit link, no QR code, no address in the File Manager and is left out of the editor's link picker.
- File Manager window (`view_folders.php`): How it answers from `explorer_short_link_options` `answers` (none before the step: no field, stored answer kept, `pg_short_link_answer_posted()`); name and address notes and lengths as on the classic screens; the details pane shows the answer.

## What is refused on save?
- `pg_short_link_redirect_columns()` -> `pg_short_link_redirect_problem()` on every entry point: a `$n` beyond the stars (`to_references`), a `$n` in the host of a full address (`to_host`, an open redirect), an address that leads to the link itself (`to_self`), back to it through other links (`to_loop`) or on through more than 10 (`to_chain`). A page, file or the home page ends a chain.
- A pattern is walked from a sample request (a path it answers again is a loop) and from every stored link's name (a pattern's with its stars as a sample): there a loop is a path that comes back after the pattern took part, so a chain passing the pattern twice with other captures that then ends is kept. Before #1235 (issue #833) whether a pattern loop was refused depended on save order.
- Restore from the File Manager's bin runs the same check (#1235); a link that would loop stays in the bin and the restore names it and why.
- Before the step nothing is written or checked and names stay at 100 characters.

CSV files: `short-link-csv.md`. Renamed pages: `page-rename-redirect.md`. Tests: `tests/short_link_redirects_test.php`, `short_link_requests_test.php`. PRs: #699, #799, #1235.
