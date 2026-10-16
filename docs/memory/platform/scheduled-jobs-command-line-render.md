---
name: scheduled-jobs-command-line-render
description: Why the search index and SEO structure jobs render pages from the command line (crontab or general job) without PHP warnings, and what a command line render lacks.
---

## What does a command line run of a page-rendering job lack?
`update_search_index.php` and `seo_analyze_job.php` render every page in
process through `get_page_content()`. Run by crontab or by the general job
(`php job.php`), the request has no Host header, no route and no signed-in
user. Rules, all keeping the output a web run produces:
- Host: use `HOSTNAME`, never `$_SERVER['HTTP_HOST']`. `init.php` sets it to
  the Host header on the web and to the hostname setting otherwise.
  `output_header_secure()` (the indexer's progress page title) reads it with a
  `defined()` check for errors printed before `init.php` got that far.
- `REQUEST_URL`: `init.php` defines it only when there is a Host header. Both
  jobs define `PATH` in its place. In PHP 8 an undefined constant is a thrown
  Error: the first custom form page (its hidden `send_to`) ended the indexer.
- No session user: screens that read `$_SESSION['sessionusername']` read it
  with `?? ''` and start a missing user row as `null` columns
  (`get_my_account_profile.php`, `get_affiliate_sign_up_form_screen_content.php`).
- No page name: `get_catalog.php` checks `isset($_GET['page'])` before looking
  for a product group in it (the SEO pass sets it, the indexer does not).
- A form view directory linking a private form list view sends a visitor who
  is not signed in to login/register with `go()`, which exits. While
  `UPDATE_SEARCH_INDEX` is set the list is left out instead, as for a visitor
  without view access; before, the run stopped there and left every later
  page unindexed and every old item undeleted.

## What happens to a <system> region naming a page that is not there?
The page was deleted, or it is in the Recycle Bin, which parks a binned
page's name (`pg_recycle_park_name()`). The region is replaced with nothing,
as it always was, but `get_page_content()` now skips it before reading the
missing page's properties. Before, each such region wrote 13 "Trying to access
array offset on null" lines (`get_page_content.php` 2201, 2209, 2220-2230) on
every render, web or command line.

## Why does a dispatched indexer run print nothing?
The general job files whatever a dispatched script printed in the site log
("... printed a message while running with the general job"), once a day per
job, because that is how a job reports its own missing settings. The
indexer's progress page landed there after every run. With `PG_CRON_DISPATCH`
set the page is buffered and discarded after `pg_cron_ran()`; an error screen
ends the run inside the buffer and still reaches the site log. A direct
command line run still prints the page.

## What still differs between a web and a command line index run?
The web run unsets `$_SESSION`, but `USER_LOGGED_IN` and the role were already
defined from the manager's session by `init.php`. Menus and regions shown
only to signed-in users (for example a "my conversations" link) and the files
they link reach the index, and `includes/fn/mail.php` and
`includes/fn/content.php` warn about the missing session user. The command
line run indexes what a visitor sees. Not changed here.

Test: `tests/cli_job_warnings_test.php`. PR #493.
