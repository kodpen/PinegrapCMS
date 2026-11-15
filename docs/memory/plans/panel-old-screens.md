---
name: panel-old-screens
description: Plan (owner 2026-11-15): the panel screens still drawn the old way move to look A; censuses done, LM2 the shared buttons, then one census group per step (C1 to C5, E1 to E5, S1 to S6)
---

## What is asked?
- Owner (2026-11-15, in the architect's window), naming work for idle workers: "eski sayfaları yeni modern tasarıma geçirme olur."
- Look A is decided and its recipes are built (`panel-design-language-steps.md`: type, page widths, the list, add and edit, settings and report families). What is left is each screen that still draws its own way.

## Which steps are there?
- LM1a (S, no product code) Census of commerce and ERP screens (orders, products, customers, shipping, payments, marketplaces, ERP), into `plans/panel-old-screens-census-commerce.md`.
- LM1b (S, no product code) Census of content, users and settings screens (pages, files, forms, comments, users, visitors, tools, Site Settings), into `plans/panel-old-screens-census-site.md`.
- A census line per screen: what it still draws its own way (an old table or toolbar, no cards, its own font sizes and colours, inline styles), how large it is, and its group. One group is one step of about 1,000 product lines, the most used first.
- Both censuses are done (#9655, #9656). LM2 (S) The shared parts, once, before the groups lean on them: the save bar's buttons drawn by their role in the add-and-edit block of `backend.src.css` (the save action the look's fill, the rest quiet, delete ghost danger; the bar's markup stays); the toolbar's primary pill and text buttons as `.pg-button` in `pg_screen_toolbar()` and the 44 files that write them. Pictures of two screens.
- Then every census group is a step, claimed by its code. Commerce and ERP in the order of `panel-old-screens-census-commerce.md`: C1, C2, E1, C3, C4a, C4b, E2, E3, E4, E2b, E5, C5. Content, people and system in the order of `panel-old-screens-census-site.md`: S1 to S6.
- A group step: card titles to the look's title, the measure class where it is missing, `<style>` blocks and inline sizes and colours to the tokens, and the fields on the 12-column grid by the widths of `ui/look-edit-screens.md`. A screen is passed once: DL5c ends with its first group (#9658) and the field widths of every other screen go with its group here.
- Not in these steps: a list's title with its count and segmented filters, the icon tile of settings rows; the architect cuts them as shared steps after the groups.
- Groups share no file once LM2 has landed, so they run side by side: the commerce and ERP groups with one coordinator, the site groups with the other. Before LM2 lands a group leaves the buttons as they are.
- Close-out: `ui/look-overview.md` names what moved; this plan and its census files are deleted.

## What does every step keep?
- A step copies the recipes and the sheet, value for value; it does not design, and it changes no behaviour, field or text of the screen. Where the sheet is silent it asks the architect.
- A screen's look is approved from a real panel's pictures (light and dark, 1440 and 390) posted in the agent channel: the architect's look, delegated by the owner's 2645. A new design goes to the owner.
- The counts of `tools/check_look.php` only fall.
- The Workspace is left out while `channel-profile-cards` and `poll-agent-questions` are in it (DL5f of `panel-design-language-steps.md`).
- Claim a census as `[iş] Old panel screens: census of <part>`, a step as `[plan:panel-old-screens] <step> — <topic>`.
- What the looks decided beyond the sheet, and the owner's kept screens: `panel-old-screens-rules.md`. The shared steps after the groups: `panel-old-screens-shared-steps.md`.
