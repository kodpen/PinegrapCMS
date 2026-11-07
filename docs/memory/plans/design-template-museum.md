---
name: design-template-museum
description: Plan: the Museum / Art Gallery design template (pinegrap-museum): exhibitions, highlights, visit, tickets and membership, events, school visits; ~1,000-line steps
---

## What is it?
- A design template `pinegrap-museum` for a museum or art gallery: home, visit (opening hours, admission as words, getting there, access, facilities), exhibitions (current, upcoming, past) with a page each, collection highlights by department with a page per object, events and talks with a page each and a place request, learning and school visit requests followed from the teacher's account, support and membership as words, about, FAQ, news (the blog), contact, privacy, sign-in and a staff area; admission tickets and membership in the shop; a timed entry slot on `book_online`. Started by a coordinator (`workflow/coordinator-no-plans.md`).
- Built like School, Photo and Travel: `pinegrap-museum.php` plus the folder `pinegrap-museum/` (`parts.php`, `mail.php`, `pages_public.php`, `exhibitions.php`, `objects.php`, `events.php`, `learning.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-hotel]]).

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-museum-design]].

## Steps (claim as `[plan:design-template-museum] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7626): `parts.php` (data, pick lists, shared parts, cards); `tests/design_template_museum_test.php`.

- 2 (done, #7657): `pages_public.php`: home, `visit`, `support`, `about`, `faq`; test.

- 3 (done, #7684): `exhibitions.php`: Exhibitions collection, `exhibitions` by `?status=`, `exhibition`, `exhibition_new`, `home_exhibitions`; test.

- 4 (done, #7713): `objects.php`: the Collection Highlights collection (`collection` by `?department=`, `object`, `object_new`); test.

- 5 (done, #7757): `mail.php`, `events.php`: Events collection, place requests, `privacy`, `email_reply`; test.

- 6 (done, #7772): `learning.php`: `learning`, school visit requests, `book_online`; test.

- 7 (done, #7788): `shop.php`: `tickets`, `membership`, the shop pages, `email_order`, the catalog; test.

- 8 (done, #7805): `standard.php`: contact, inbox, newsletter, News, help search, error, the message e-mails; test.

- 9 (done, #7816): `account.php`: sign-in, My Account (with the order history), the staff area, `blog_new`, the directory, the account e-mails; test.

- 10: assembly: `pinegrap-museum.php` (order, search sections, look, fonts, `pmz-` rules, folders, contact group, shared parts), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, booking-templates, url-params), `designer/design-template-museum.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The next free step is the lowest one not marked done. Models: `pinegrap-school/`, `pinegrap-photo/`. `exhibitions.php` defines `$mz_filter`, `$mz_page_link`, `$mz_record_list`, `$mz_filter_bar` and `$mz_featured_choices` for the later collections; their tests and the main file include it first. `events.php` defines the request pieces (`$request_page`, `$request_detail`, `$request_list`, ...) and `learning.php` reuses them. The assembly includes the parts in the order of `tests/design_template_museum_account_test.php` (`pages_public`, `exhibitions`, `objects`, `events`, `learning`, `shop`, `standard`, `account`); that test already checks the whole template and the page list. Later tests share `_test_mz_page_keys()` from `tests/design_template_museum_test.php`; the pick lists the lists filter on are `$exhibition_statuses` (`?status=`), `$department_titles` (`?department=`) and `$event_kind_titles` (`?kind=`).

## Open questions
- None: everything above reuses shipped features. A step that finds it needs new behaviour asks the architect (Working mode 10).
