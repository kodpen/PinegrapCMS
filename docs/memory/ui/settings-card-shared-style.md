---
name: settings-card-shared-style
description: What every Site Settings card shares since the scan of 2026-11-21: text sizes, one 32px field height, the select column and chevron, tag fields, swatch edge, quiet head buttons
---

## Why was it changed?
- Owner (2026-11-21, agent channel request 2359): check every settings pane and modernize what was not; no card inside a card (request 2361).
- The architect's conditions (requests 2363 and 2372): no card in a card, one field height, a unit inside its field, no cut select; checked at 1440 and 390 px, light and dark.

## What size is the text?
- A pane reads at 14px (`--pg-text-base` on `.pg-settings-body` and `#pg_settings_sections`). Before, a list row's title or a table cell with no size of its own took the body's 16px and stood larger than its row.
- Every field name (`.form-label` in a card) is 14px at weight 550, also over a picker, a text area, a tag field or a group of choices; a drawer's form keeps its own. List row titles are 14/550 as well.
- A note or warning box (`.alert`) reads at the help size, 13px.

## How tall is a field?
- 32px everywhere in a card: `.form-control`, `.pg-input` (a drawer keeps 36px), a tag field (`.pg-chips`, growing a line at a time), a picker (`ui/settings-picker-field.md`), a field with a unit (`ui/form-adorn-settings-cards.md`).
- `select.pg-input` draws the settings chevron (light and dark), not the browser's arrow. A bare select inside a `.pg-input` wrapper (Claude's row) is 30px inside the wrapper's 32px border: a measure of the select alone reads 30, which is right.

## How wide is a select?
- Its column is at least 19rem and otherwise as wide as its longest option, so the chosen value is read whole (a time zone, a mail connection, an offer by name); the name keeps 12rem; the `pg-f-*` cap still holds it. Rule: the select column rule in the settings grid of `backend.src.css`.
- Selects whose options are offers or number styles are `pg-f-lg` (Accounting `erp_number_style`, E-Commerce `affiliate_group_offer_id`, `referral_offer_id`).
- Under 576 px a select is a full row under its name. Architect (2026-11-21, agent channel message 11746): when its chosen option still does not fit, the pane's step shortens the option, the word that carries the meaning first, and moves the explanation to the help line under it; no sideways scroll, no select of two lines.
- Still cut at 390 px on 2026-11-21, each for its pane's step: System e-mail's sending path, the 2FA role, Accounting's document numbers, the affiliate group offer.
- Mail rules' Add rule form (Mailboxes): a flex row; a select is as wide as its longest choice, the address field takes the rest, all full width under 576 px.

## What else do the cards share?
- A colour field's swatch has a 1px inset line in `--pg-field-line`, so a white colour still shows its edge (SEO's theme colour).
- A card head's add button is quiet and small with `bi-plus-lg`: New department, Add a server, New template (Channels), Add an agent.
- Fields a switch reveals: `ui/settings-revealed-groups.md`.

## What was left as it was?
- The range value bubble (`ui/range-value-bubble.md`).
- A list whose rows are framed one by one (Mailboxes, Mail accounts, Agents, MCP servers) keeps one frame per row: the architect, request 2369.
- A field's own border inside a card (picker, code editor, tag field, a note box) is a field, not a card.
- Bookings' framed service blocks wait for the Bookings settings plan.

Related: docs/memory/ui/settings-card-row-layout.md, docs/memory/ui/settings-card-field-widths.md, docs/memory/ui/settings-picker-field.md
