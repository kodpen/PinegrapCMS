---
name: panel-screenshots
description: tools/screenshots/shoot.js shoots panel screens (light/dark, wide/phone) on a setup_sandbox.sh instance with sample records: running it, adding a screen, the private page
---

## What is it for?
- A screen's look is a path no unit test reaches. The Bootstrap 6 plan (`plans/bootstrap-6-panel.md`) asks PRs to show their screens before and after (B1b, #1690).
- Images are never committed: the script refuses an output folder inside the repository. They are shown on a private page the PR links.

## How do I run it?
1. `bash tools/setup_sandbox.sh` (a few minutes; MariaDB, installer, `127.0.0.1:8000`). It rewrites `pinegrap/data/config.php`: never commit that file afterwards (`git checkout pinegrap/data/config.php` before you commit, or leave it out of `git add`).
2. `node tools/screenshots/shoot.js --out=<scratch dir>/shots --label="$(git rev-parse --short HEAD)"`: seeds, signs in as `admin` / `sandbox-admin`, writes `<screen>-<light|dark>-<wide|tablet|phone>.png` (1440, 834, 390 px) and `index.html`, a contact sheet. About 3 minutes for the whole list.
- Narrow a run: `--only=edit_offer,view_offers`, `--theme=dark`, `--viewport=tablet`. `--no-seed` for an instance that is not the sandbox; `--base`, `--user`, `--password` for another address or account.
- Exit 1 on an HTTP error, a redirect to sign-in, a script error or a crop off screen; listed on the sheet.
- Chromium and Playwright are preinstalled in a cloud container; do not install another.

## Before and after?
- Either switch the checkout (`git checkout --detach origin/development`, shoot `--label=before`, `git checkout -`, shoot `--label=after`; never `git stash`, it takes the sandbox's `config.php`), or serve the "before" commit from a `git worktree` on port 8001 with the same `config.php` and `data/files` linked, and shoot with `--base=http://127.0.0.1:8001`; file names match.
- Dialogs, drawers, menus: `node tools/screenshots/open_toggles.js <out> [base] [screens]` clicks every dialog, drawer and menu toggle (v5 or v6) and writes `results.json` (open boxes) and pictures; compare a before and an after run.

## How are the images shown?
- Publish the output folder as a private Artifact: `index.html` as the page, the PNGs as its files (relative paths already match). Link the page in the PR body. Never attach images to the PR or commit them.

## What do seed.php and screens.json hold?
- `tools/screenshots/seed.php` adds what the `turkish_default` starter site lacks: a second panel user (`ayse.yilmaz`, role 3), the Workspace module on, and a channel `urun-lansmani` with four messages and four tasks. It finds each record by its fixed name, so a rerun changes nothing; it refuses any database but `pinegrap_sandbox` (`PG_SCREENSHOT_SEED_ANY_DB=1` overrides). Last line: JSON ids (`user_id`, `channel_id`, `product_id`, `offer_id`).
- `tools/screenshots/screens.json`: `name`, `path` under `/pinegrap/` with `{id}` placeholders from the seed, optional `crop` (one element), `click` (string, or per viewport), `cookies`, `viewports`, `kept` for the screens the owner wants kept as they are, and `signed_out` for a screen shot before signing in (sign-in, forgot password: the site's own pages). The visual editor is shot through `add_system_style.php` (no visual design in the starter). `tests/screenshot_screens_test.php` checks files exist, placeholders are seeded and the kept list is complete.
- product_builder is reached through `edit_product.php` / `add_product.php` (it is included, not called). The main menu is shot mini and expanded (cookie `softwaremenustatus`) on wide, and as the open drawer on phone.

## What keeps the pictures stable, and what does not?
- Animations and transitions are off, the caret hidden, fonts and images awaited; the guided tours (`pg_tour.js`) are kept from starting by an init script that turns `PgTour.autoStart` into a no-op.
- Not stable: the dashboard greeting and clock, relative times and dates follow the run's clock. Compare layout, not those texts.
- Full-page shots show the sticky menu one viewport high.
