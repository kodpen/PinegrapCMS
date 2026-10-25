---
name: bootstrap-6-panel
description: Plan (approved 2026-10-25, step B0 in progress): panel UI from Bootstrap 5.3.8 to 6.0.0-alpha.1 by pre-landing adapters on development, then one generated switch; owner decisions, model, order
---

## What is the goal and the scope?
- Owner request 2026-10-25: move the panel to Bootstrap 6 quickly by the official migration rules (skill `bootstrap-v5-v6-migration`). Own widgets are swapped for native v6 components in the same stage (`bootstrap-6-components.md`). A redesign of legacy form screens and tables follows; tokens are tuned after the screenshot round.
- Panel only; sites stay on Bootstrap 5 (`ui/bootstrap-6.md`). jQuery UI later leaves the panel, never the site.
- Screens the owner likes must not look worse: workspace, view_files, product_builder, edit_user, welcome, view_product_groups, view_offers, edit_offer, header, footer, main menu. The visual editor keeps its look.

## What did the owner decide (2026-10-25)?
- Start now on the pinned 6.0.0-alpha.1 in `assets/lib/bootstrap-6`.
- Old browsers get a non-blocking "update your browser" notice. A classic `<head>` script tests `CSS.supports` for `light-dark()`, `selector(:has(*))` and `oklch(from red l c h)`, which fail below Chrome/Edge 123, Firefox 128 and Safari 17.5.
- The switch keeps today's look; the redesign comes after. The component swaps are part of the first stage.
- A separate branch is allowed; the owner left the model to this plan.
- jquery-multiselect-checkbox stays. edit_offer and the installer move to Stepper. Avatars move to v6 Avatar with theme colours. The password meter follows the "Require Strong Password" setting (`config.strong_password`).

## Why not a long-lived branch?
- Measured on 2026-10-25: about 85-100 PRs a day reach `development`, and 250 of the 488 files with v5 patterns changed within 7 days. Replaying a 5-day branch gave 34-41 files with real hunk conflicts. One panel-wide sweep PR (like #327, 213 files) pushes that past 230.

## What is the model?
1. Pre-land on `development` in normal small PRs that work on 5.3.8 and change nothing visible: the `pgUI` adapter, widget wrappers with a switchable engine, server changes, a `--pg-*` token layer between panel CSS and Bootstrap variables, site-output markers in the visual editor, renamed colliding classes, the tools.
2. Generated diff: class renames and regular markup rewrites come only from `tools/bs6_migrate.php`, run on the latest `development`, never edited by hand.
3. Switch: `feature/bootstrap-6` lives 1-2 days. It holds the latest `development`, the codemod output, the loader and engine flip and the rehearsed hand-fix list. Rehearse it automatically for a few days first. On switch day only panel-wide sweep PRs wait (say so on the claim). Afterwards CI rejects v5 patterns and open PRs convert their own files with the codemod.

## In which order?
- Cold areas first: panel templates, dashboard, users and contacts, products and offers, ERP (not the Paraşüt files under open claims). Then forms and content, orders, workspace, the rest. Last: `output.php`, `backend.src.css`/`.js`, settings, the visual editor.
- Steps and status: `bootstrap-6-panel-steps.md`. Research numbers: `bootstrap-6-findings.md`.
