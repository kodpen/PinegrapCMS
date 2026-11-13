---
name: settings-card-markup
description: Markup that keeps a Site Settings card in look A: switch descriptions, radio legends and descriptions, alerts, sub-headings, tables, group headings, link rows, two-column blocks, hidden inputs (#9046)
---

## Where does a switch's description go?
- Inside its `.form-field`, after the label: the row rules put it in the note area, full width under the name on a desktop and between name and switch on a phone. After the field (or inside the label) it ended under the switch on a phone, or squeezed into the left half (SEO › Structured data, Commerce › Address autocomplete, fixed in #9046).

## How are radio groups written?
- `fieldset` with `legend.form-label.pg-field-label` (13 / 550); `fs-md` made legends 16 px. Tested in `tests/settings_card_names_test.php`.
- A description of one option goes after that option's `.form-field` as `div.form-text.mb-3`; inside the field it sat 0–2 px above the next option and read as its.

## What size are alerts and tables in a card?
- An alert in a card is `alert … py-3 small` (14 px). Inside a popover body, already 14 px, only `py-3`: `small` there gives 12.25 px.
- A table in a card is `table table-sm align-middle small`; its cells take no second `small`. The header's dark band and bold are the stylesheet's (not done in #9046).

## How are headings inside a card written?
- A sub-heading that starts a group of rows: `h6.fg-secondary` (no `text-uppercase`) first in its cell; the cell gets the line above and the next row drops its own (`settings-card-row-layout.md`).
- Workspace cards name their parts with `pg-group-label` (12 / 550), not `h3.h6` or `h6 fw-bold`; summaries and step titles are `small fw-semibold`; empty states `ws-empty small`.

## How do rows stay inside a named group?
- When a heading must name several switches for a screen reader (`settings-group-names.md`), the `col-12 pb-0` group cell holds the `h6` heading, its note, then a nested `.row mt-5` of `col-12` row cells: each switch keeps its row and line (Firewall › Always let in).

## How is a list of links drawn?
- `card-body p-0` with `a.pg-card-inner-row` rows: icon, name (`fs-sm fw-medium fg-primary`), chevron (Integrations › Related screens). Document template rows are `li.pg-card-inner-row px-0` with a `pg-pill` and a quiet small `pg-button` grouped `ms-auto`.

## What breaks a two-column block in a card?
- A cell whose direct child is `.form-field > .switch` is turned into a full-width row, so in `lg:col-7` + `lg:col-5` the second column wraps under the first. The form column wraps its fields in a plain `div` (Workspace › Voice transcripts, the Claude card).

## What draws a line above a card's first row?
- A hidden input set in the `.row` before the first cell: the divider rule (`* + cell`) counts it as a sibling. Put it inside the first cell (Maintenance › Storage, Chat notifications).
