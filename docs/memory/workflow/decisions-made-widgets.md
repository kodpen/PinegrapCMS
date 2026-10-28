---
name: decisions-made-widgets
description: Decisions list, system widget part: conditions and required controls, translated compared text, old blocks equal in look (W16), gallery sources (W5), entity values (W8), the 2026-10-27 answers.
---

## How do system widget conditions treat forms and languages?
- Owner decision (2026-10-25): a value condition never hides a required form control; the panel offers none there and the renderer ignores one. Reason: the server still asks for that value, so the form could never be sent. Narrowed (#2450, W16 cart product form): a control whose `required` is a lone row token (`required="{{token}}"`) does not count as required for this rule, on the control or on any element holding it; a plainly `required` control keeps the protection. Reason: the row decides whether the field is required, and the visible control is the one that sends the field.
- Owner decision (2026-10-25): a condition's compared text with words is translated like the page's texts. Reason: the value it is compared with is drawn in the page's language, so an untranslated text would not match on other languages (`designer/widget-condition-panel.md`).

## What do the widget designability answers of 2026-10-26 bind?
- Owner decision (2026-10-26, W16, option B): old blocks drawn by parts are equal in look and behaviour, not byte-identical: same blocks, place and words in the site's language; field names, ids and the markers page scripts look for kept; hidden source details (an inline `<style>`) may move. Reason: running sites keep working through equal look and behaviour; byte equality would keep a second renderer and untranslatable words.
- Owner decision (2026-10-26, W5, option 1): the layout gallery offers Default, From templates and On this site, only layouts that look and work right in this design; a layout from another widget is copied, never linked; nothing changes until Keep. Reason: a layout offered must look and work right where it lands, and a copy leaves the other widget's page as it was.
- Owner decision (2026-10-26, W8, option A): a widget shows an entity's values only from the allow-list (`designer/widget-entity-fields.md`); account, order and address data only on the person's own pages and e-mails; secrets never; yes/no as conditions, choices as translated words; a listed field is never removed. Reason: sites that bound a field would go empty, and nothing a visitor could use against the shop or another customer may show (`designer/widget-designability-decisions.md`).

## What do the answers of 2026-10-27 bind?
- Owner decision (2026-10-27, all proposals accepted): OR conditions (up to three, All/Any), hide-if-empty in every kind, value inside one translatable sentence, a classic form converter and no PHP code area, page facts (record plus signed in, cart has items, cart count) that only choose what shows, placements that only narrow content, form parts with system-owned sending and required controls, express display lists, W12 grouping by the page's language, the canvas opening on the first state. Reasons per answer in `designer/widget-designability-decisions-2.md`.
- Reference price window (2026-10-27, answered for the owner under "en makul ve işlevsel seçimle"): while a reduced price runs the window ends where it began, else now; not a missing "now" (`commerce/product-reference-price-decisions.md`).
