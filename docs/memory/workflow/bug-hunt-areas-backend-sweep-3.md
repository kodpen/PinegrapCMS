---
name: bug-hunt-areas-backend-sweep-3
description: Third pass of the owner's whole-backend sweep of 2026-11-22: scheduled jobs, site pages, dark theme, API answers, untranslated texts; what it fixed or reported, what read clean
---

## What did the third pass add?
- On the sandbox of the first two passes (#11051, #11053): every scheduled job run from the command line, the starter site's visitor pages, the 334 crawled panel screens in the dark theme, the API answer check, and the Turkish panel's visible texts against the shards.

## How was each read?
- Jobs: `job.php` and each root `*_job.php`, 28 in all, run with `php`; exit code, output and `data/temp/php_errors.log` read.
- Site pages: 181 pages of the starter site at 390 and 1440 px signed out, and at 1440 px as `admin`: PHP text, script errors, failed requests, sideways scroll.
- Dark theme: Playwright with `colorScheme: 'dark'`; per screen, text under 3:1 contrast against the background composited from its ancestors (skipped under a background image), and opaque light boxes of 60×60 px or more.
- API: a local server app with every read scope from `api_app_create()` (a CLI script defines `PG_API_PANEL`, or the keys module exits), `config.api_require_https` 0 for the run and 1 again after, then `tools/check_api_answers.php` against `http://127.0.0.1:8000/pinegrap/integration.php` (`api/api-answer-check.md`).
- Untranslated texts: every visible text of the 334 screens matched against the shard keys whose Turkish differs, also with a trailing `:`, `.` or `…` dropped.

## What did it find?
- The code fields of Maintenance, Appearance and Features opened with English editor texts: `ui/settings-code-field-texts.md` (#11057).
- The Calendar subscription help named "Google Calendar" in English under the "Google Takvim'e ekle" button; it goes through `lang()` now (#11057).
- The starter sites' three Twitter timelines stay empty and log script errors and CSP reports on every page: issue #11055 (the starter dumps' content needs a decision first).

## What read clean, or is no fault?
- Jobs: every exit code 0, no PHP message. Site pages: no PHP message, no sideways scroll; an item page without an item answers 404; the editor's `^^photo^^` preview requests are by design.
- The starter `home-5` page's `ytplayer.min.js` calls the retired gdata.youtube.com API: console noise of starter content.
- Dark theme: only the CKEditor toolbar and white page (`ui/look-open-work.md`), Design examples (light samples by design) and the designer's site preview.
- API: 193 answers matched, 0 differences; 74 skipped (no record in the sandbox, a required query parameter, or refused as the route describes).
- Untranslated: the other hits are brand and product names, sandbox data, texts a user typed, or the API reference, English by design (`api/api-docs-conventions.md`).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/ui/panel-screenshots.md
