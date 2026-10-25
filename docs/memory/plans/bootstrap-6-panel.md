---
name: bootstrap-6-panel
description: Plan (owner decisions 2026-10-25; status in bootstrap-6-panel-steps.md): panel UI from Bootstrap 5.3.8 to 6.0.0-alpha.1 by pre-landing adapters on development, then one generated switch
---

## What is the goal and the scope?
- Owner request 2026-10-25: move the panel to Bootstrap 6 quickly by the official migration rules (skill `bootstrap-v5-v6-migration`), swap own widgets for native v6 components in the same stage (`bootstrap-6-components.md`), then redesign legacy form screens and tables (`bootstrap-6-redesign.md`). Scope and references: `ui/bootstrap-6.md`.
- Screens the owner likes must not look worse: workspace, view_files, product_builder, edit_user, welcome, view_product_groups, view_offers, edit_offer, header, footer, main menu. The visual editor keeps its look.

## What did the owner decide (2026-10-25)?
- Start now on npm 6.0.0-alpha.1 (in `assets/lib/bootstrap-6` since B0b).
- Old browsers get a non-blocking "update your browser" notice. A classic `<head>` script tests `CSS.supports` for `light-dark()`, `selector(:has(*))` and `oklch(from red l c h)`, which fail below Chrome/Edge 123, Firefox 128, Safari 17.5. One `lang()` text, printed by `output_control_panel_header_includes()` and the 4 standalone screens, closable for the session.
- The switch keeps today's look; the redesign comes after. The component swaps he named belong to the first stage.
- A separate branch is allowed; the model was left to this plan.
- jquery-multiselect-checkbox stays. edit_offer and the installer move to Stepper. Avatars move to v6 Avatar with theme colours. The password meter follows "Require Strong Password" (`config.strong_password`).
- Parallel steps are allowed: steps without a dependency on each other (`bootstrap-6-panel-steps.md`) may be claimed by different sessions at once, one draft PR per step; two sessions never hold the same files.
- Screenshot rounds may run an instance (`tools/setup_sandbox.sh`): B1b, the rehearsals of B8 and the form-adorn pilot (`workflow/sandbox-and-environment.md`).

## What is the model?
- No long-lived branch: measured conflict rates rule it out (`bootstrap-6-findings.md`).
1. Pre-land on `development` in small PRs that work on 5.3.8 and change nothing visible: the `pgUI` adapter, widget wrappers with a switchable engine, server changes, a `--pg-*` token layer, site-output markers in the editor, colliding class renames, the tools. Each step adds its key rule when it lands, or the daily PRs undo it.
2. Generated diff: class renames and regular markup rewrites come only from `tools/bs6_migrate.php`, run on the latest `development`, never edited by hand.
3. Switch: `feature/bootstrap-6` lives 1-2 days after days of rehearsal (`bootstrap-6-switch.md`). The merging session (`workflow/merge-train.md`) merges it and orders the queue around it.

## In which order?
- Cold areas first: panel templates, dashboard, users and contacts, products and offers, ERP (not Paraşüt files under open claims). Then forms and content, orders, workspace, the rest. Last: `output.php`, `backend.src.css`/`.js`, settings, `install/index.php`, the visual editor.
- Claim with a draft PR titled `[plan:bootstrap-6-panel] B<n> — <topic>`; steps and status: `bootstrap-6-panel-steps.md`.

## What is still open?
1. Moving to a later 6.0 prerelease: when, and who decides.
