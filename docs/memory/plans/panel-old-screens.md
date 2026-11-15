---
name: panel-old-screens
description: Plan (owner 2026-11-15): the panel screens still drawn the old way are moved to look A; LM1a and LM1b the census, then one screen group per step
---

## What is asked?
- Owner (2026-11-15, in the architect's window), naming work for idle workers: "eski sayfaları yeni modern tasarıma geçirme olur."
- Look A is decided and its recipes are built (`panel-design-language-steps.md`: type, page widths, the list, add and edit, settings and report families). What is left is each screen that still draws its own way.

## Which steps are there?
- LM1a (S, no product code) Census of commerce and ERP screens (orders, products, customers, shipping, payments, marketplaces, ERP), into `plans/panel-old-screens-census-commerce.md`.
- LM1b (S, no product code) Census of content, users and settings screens (pages, files, forms, comments, users, visitors, tools, Site Settings), into `plans/panel-old-screens-census-site.md`.
- A census line per screen: what it still draws its own way (an old table or toolbar, no cards, its own font sizes and colours, inline styles), how large it is, and its group. One group is one step of about 1,000 product lines, the most used first.
- LM2 and on (M each): one group per step, written by the architect from the census.
- Close-out: `ui/look-overview.md` names what moved; this plan and its census files are deleted.

## What does every step keep?
- A step copies the recipes and the sheet, value for value; it does not design, and it changes no behaviour, field or text of the screen. Where the sheet is silent it asks the architect.
- A screen's look is approved from a real panel's pictures (light and dark, 1440 and 390) posted in the agent channel: the architect's look, delegated by the owner's 2645. A new design goes to the owner.
- The counts of `tools/check_look.php` only fall.
- The Workspace is left out while `channel-profile-cards` and `poll-agent-questions` are in it (DL5f of `panel-design-language-steps.md`).
- Claim a census as `[iş] Old panel screens: census of <part>`, a step as `[plan:panel-old-screens] <step> — <topic>`.
