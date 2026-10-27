---
name: panel-screenshots
description: tools/screenshots/shoot.js shoots panel screens (light/dark, wide/phone) on a setup_sandbox.sh instance with sample records: running it, adding a screen, the private page
---

## What is it for?
- A screen's look is a path no unit test reaches; Bootstrap 6 PRs show screens before and after (`plans/bootstrap-6-panel.md`).
- Images are never committed: the script refuses an output folder inside the repository. They are shown on a private page the PR links.

## How do I run it?
1. `bash tools/setup_sandbox.sh` (a few minutes; MariaDB, installer, `127.0.0.1:8000`). It rewrites `pinegrap/data/config.php`: never commit it (leave it out of `git add`).
2. `node tools/screenshots/shoot.js --out=<scratch dir>/shots --label="$(git rev-parse --short HEAD)"`: seeds, signs in as `admin` / `sandbox-admin`, writes `<screen>-<light|dark>-<wide|tablet|phone>.png` (1440, 834, 390 px) and `index.html`, a contact sheet. About 3 minutes for the whole list.
- Narrow a run: `--only=edit_offer,view_offers`, `--theme=dark`, `--viewport=tablet`. `--no-seed` for an instance that is not the sandbox; `--base`, `--user`, `--password` for another address or account.
- Exit 1 on an HTTP error, a redirect to sign-in, a script error or a crop off screen; listed on the sheet. Chromium and Playwright are preinstalled in a cloud container.

## Before and after?
- Either switch the checkout (`git checkout --detach origin/development`, shoot `--label=before`, back, shoot `--label=after`; never `git stash` or `reset --hard`: they take the sandbox's `config.php`), or serve the base commit from a `git worktree` on port 8001 with `config.php` copied and `data/files`, `data/cache` and the files of `data/layouts` linked, shooting with `--base=http://127.0.0.1:8001`.
- Open states (v5 or v6), each writing `results.json` and pictures to compare before/after: `open_toggles.js <out> [base] [screens]` clicks every dialog, drawer and menu toggle; `open_dialogs.js` opens every dialog and drawer from code, and `pgConfirm()`; `settings_panes.js` loads each settings category. All take `--width=834|390` and `--theme`; the last two list v5 classes left in the open box.
- Every screen: `sweep.js <out> [base]` loads each root file calling `pg_page_shell()` (no `delete_`/`duplicate_`/`remove_`, no repair screen): status, landing page, script errors, classes Bootstrap 5 defines that no loaded sheet does.

## How are the images shown?
- Publish the output folder as a private Artifact: `index.html` as the page, the PNGs as its files (relative paths already match). Link it in the PR; never attach or commit images.

## What do seed.php and screens.json hold?
- `tools/screenshots/seed.php` adds what the `turkish_default` starter lacks: a second user (`ayse.yilmaz`, role 3), Workspace on, a channel `urun-lansmani` with four messages and tasks. Found by fixed name (reruns change nothing); any database but `pinegrap_sandbox` is refused (`PG_SCREENSHOT_SEED_ANY_DB=1` overrides). Last line: JSON ids (`user_id`, `channel_id`, `product_id`, `offer_id`).
- `tools/screenshots/screens.json`: `name`, `path` under `/pinegrap/` with `{id}` placeholders from the seed, optional `crop` (one element), `click` (string, or per viewport), `cookies`, `viewports`, `kept` (screens the owner wants kept as they are), `signed_out` (sign-in, forgot password). The visual editor is shot through `add_system_style.php`. `tests/screenshot_screens_test.php` checks files exist, placeholders are seeded and the kept list is complete.
- product_builder is reached through `edit_product.php` / `add_product.php` (it is included, not called). The main menu is shot mini and expanded (cookie `softwaremenustatus`) on wide, and as the open drawer on phone.

## What keeps the pictures stable, and what does not?
- Animations and transitions off, caret hidden, fonts and images awaited; an init script makes `PgTour.autoStart` a no-op (no guided tours).
- Not stable: the dashboard greeting, clock, relative times, dates (compare layout). Full-page shots show the sticky menu one viewport high.
