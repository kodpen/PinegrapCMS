---
name: dev-machine-testing
description: Testing on dev.pinegrap.com (the owner's PC): what may be written, the shared sign-in and page lock, the shared checkout, asking for a page as a visitor, logs and the job's heartbeat, browser traps
---

## What may a session write on dev?
- Nothing in the database and no e-mail without the owner's go-ahead in his own words ("yaz ve sil" for test records): list such steps as waiting. Never delete visitor rows or press "Clean up visitor records now", "Optimize table" or "Shrink now".
- Test data is named `pg-test …` and listed for him at the end. Dev has no mail server: a send fails into `mail_outbox` as a queued retry and would go out once mail is set up, so never address a real person.
- A schema step dev lacks is run from Settings › Jobs › Repair Software (`release/rerun-upgrade-steps.md`). It writes the database, so his go-ahead comes first.

## Who else is on dev?
- The owner and every session sign in to the panel with one account. Before editing a page in the Visual Page Editor read `designer_page_lock`: a `last_seen` under a minute old is somebody editing, and "Take over the design" would make their tab read-only. Ask the owner first.
- The site runs from one checkout on `development`. Before switching it, look at `git branch --show-current` and for a running `git`: an `index.lock` held by a live process is the owner's own pull, so wait and never kill it. A local change there is the owner's: never commit, revert or stash it.
- Dev is as fresh as its last pull: nobody keeps it in step since 2026-11-03, the owner pulls it himself. A session brings it up only with `git merge --ff-only origin/development`, while it is on `development` and not ahead; new commits under `includes/migrations` or `install` need the re-run above.
- To try a branch: a `tmp/<topic>` branch at the checkout's own `development`, `git cherry-pick` of the PR's commits (`-c core.hooksPath=/dev/null`), look, `git switch development`, delete the branch. A conflict only under `docs/` may be dropped: the site reads no docs. Wait while another `tmp/` branch is checked out.
- Never `git worktree prune` in that clone: it reaches every session's worktree.

## How is a page asked for as a visitor?
- The firewall's bot filter refuses a generic client on public pages (`waf_generic_clients()`: curl, wget, headless Chrome, Playwright and others), never on the API addresses (`waf_is_api_request()`): send a browser User-Agent for a page check.
- A request from Turkey without a language choice is redirected to `/tr/…` (`platform/language-redirect-matching.md`): send `Accept-Language: en-US` and `Cookie: pg_language=en` when checking a status code.
- Dev trusts the loopback proxy in front of it, so a request to `http://[::1]/` with the site's `Host` and `CF-IPCountry` / `CF-Connecting-IP` headers counts as that visitor: try the country restriction with a country nobody visits from (`AQ`).
- A site form sent while signed in writes its contact fields onto the signed-in person's own contact: send test forms signed out, or put the contact back.

## Where are the facts read?
- The site's PHP errors: `pinegrap/data/temp/php_errors.log`, JSON lines with the address and the trace.
- The database: a script that reads `data/config.php` and allows only `SELECT` and `SHOW`; it never prints the credentials. The general job runs every minute; its heartbeat is the `cron_runs` row with `job_name` `job`.

## Which browser traps cost time?
- A button with `data-confirm-content` opens the browser's own `confirm()`, which freezes an automated tab: submit the button's form from script instead.
- A click on a download button saves into the owner's Downloads folder: read an export with `fetch()`. Opening the bell marks his notifications read, which cannot be undone.
- The panel's light or dark theme is `localStorage['pinegrap backend color scheme']`: set it, reload, and put the old value back.
