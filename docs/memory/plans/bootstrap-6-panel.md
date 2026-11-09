---
name: bootstrap-6-panel
description: Plan (owner decisions 2026-10-25; status in bootstrap-6-panel-steps.md): panel UI from Bootstrap 5.3.8 to 6.0.0-alpha.1 by pre-landing adapters on development, then one generated switch
---

## What is the goal and the scope?
- Owner request 2026-10-25: move the panel to Bootstrap 6 quickly by the official migration rules (skill `bootstrap-v5-v6-migration`), swap own widgets for native v6 components in the same stage (`bootstrap-6-components.md`), then redesign legacy form screens and tables (`bootstrap-6-redesign.md`). Scope and references: `ui/bootstrap-6.md`.
- Screens the owner likes must not look worse: workspace, product_builder, edit_user, welcome, view_product_groups, view_offers, edit_offer, header, footer, main menu. The visual editor keeps its look.

## What did the owner decide (2026-10-25)?
- Start now on npm 6.0.0-alpha.1 (in `assets/lib/bootstrap-6` since B0b).
- Old browsers get a non-blocking "update your browser" notice. A classic `<head>` script tests `CSS.supports` for `light-dark()`, `selector(:has(*))` and `oklch(from red l c h)`, which fail below Chrome/Edge 123, Firefox 128, Safari 17.5. One `lang()` text, printed by `output_control_panel_header_includes()` and the 4 standalone screens, closable for the session. Built: `ui/panel-old-browser-notice.md`.
- The switch keeps today's look; the redesign comes after. The component swaps he named belong to the first stage.
- jquery-multiselect-checkbox stays. edit_offer and the installer move to Stepper. Avatars move to v6 Avatar with theme colours. The password meter follows "Require Strong Password" (`config.strong_password`).
- Parallel steps are allowed: steps without a dependency on each other (`bootstrap-6-panel-steps.md`) may be claimed by different sessions at once, one draft PR per step; two sessions never hold the same files.
- Screenshot rounds may run an instance (`tools/setup_sandbox.sh`): B1b, the rehearsals of B8 and the form-adorn pilot (`workflow/sandbox-and-environment.md`).

## How is a step claimed?
- Claim with a draft PR titled `[plan:bootstrap-6-panel] B<n> — <topic>`; steps and status: `bootstrap-6-panel-steps.md`.

## What is still open?
1. Moving to a later 6.0 prerelease: when, and who decides.
