---
name: design-template-gallery
description: Plan: the Art Gallery design template (pinegrap-gallery): artists, exhibitions, works, artwork and private-viewing enquiries, editions in the shop, the journal; small steps
---

## What is it?
- A design template `pinegrap-gallery` for a contemporary art gallery: home, the represented artists (a page per artist with their works), exhibitions (current, upcoming, past; a page per exhibition with its works and its opening night), the works, an enquiry about a work (`?artwork=`), a private viewing request (`?exhibition=`), art advisory, visit, about, FAQ, the Journal (the blog), newsletter, contact, sign-in and a staff area. With the shop: signed editions, small works and exhibition catalogues.
- Built like Pharmacy and Museum: `pinegrap-gallery.php` plus the folder `pinegrap-gallery/` (`parts.php`, `cards.php`, `pages_public.php`, `artists.php`, `exhibitions.php`, `mail.php`, `requests.php`, `shop.php`, `standard.php`, `account.php`), each page file returns `pages` and `widgets`, kept as `$…_part` in the main file ([[design-template-museum]]).
- Parts are cut in two steps (data, then `cards.php`) to keep steps small.

## Design
- What it reuses, what it does not build, the look and the Turkish words: [[design-template-gallery-design]].

## Steps (claim as `[plan:design-template-gallery] <n> — <topic>`; each waits for the one before it, Working mode 1)
- 1 (done, #7926): `parts.php`, data and helpers. 2 (done, #7966): `cards.php`. 3 (done, #7992): `pages_public.php` (home places `home_exhibitions`, `home_artists`, `home_posts`).

- 4 (done, #8017): `artists.php`.

- 5 (done, #8034): `exhibitions.php`.

- 6 (done, #8057): `mail.php` and `requests.php`.

- 7 (done, #8092): `shop.php`.

- 8 (done, #8110): `standard.php`.

- 9 (done, #8128): `account.php`.

- 10: assembly: `pinegrap-gallery.php` (order, search sections, look, fonts, `pgl-` rules, folders), `…_assembly_test.php`, the template in the shared test lists (collection-form, account-email, submitter-name, consent-privacy, faded-text, custom-form-rules), `designer/design-template-gallery.md`; the PR body names the `[NEW]` line; this plan and its design file are deleted.

## Where to start?
- The lowest step not marked done. Model: `pinegrap-pharmacy/` and `pinegrap-museum/` (same shape). The enquiry's lists: `?artwork=` `$work_choices`, `?kind=` `$enquiry_choices`; the viewing's `?exhibition=` `$exhibition_choices`. `_test_gl_page_keys()` (step 1 test) lists every page key; later tests `require_once` it. Each page file is included after `parts.php` and `cards.php`; `artists.php` holds the list pieces, `mail.php` the e-mail frame. The cards bind these field names, so the forms use them: Artists `name`, `medium`, `based`, `summary`, `photo`; Exhibitions `title`, `status`, `dates`, `opening`, `summary`, `photo_1`; Works `title`, `artist` (reference), `technique`, `size`, `price`, `availability`, `photo`.

- Step 10 include order: parts, cards, mail, pages_public, artists, exhibitions, requests, shop, standard, account (standard uses artists' list pieces, account the requests' list). The account test already checks the whole template.

## Open questions
- None: all reuses shipped features; new behaviour goes to the architect (Working mode 10).
