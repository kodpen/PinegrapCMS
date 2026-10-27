---
name: widget-designability-decisions-2
description: Owner's 2026-10-27 widget designability answers: OR conditions, hide-if-empty, words beside a value, converter (W22), page facts, placement settings, form parts, express lists, W12, W19.
---

## What did the owner answer on 2026-10-27?
- Every proposal of [[widget-designability]] accepted ("önerilerin hepsi evet"). The first answers are in [[widget-designability-decisions]].

## OR in conditions?
- Up to three conditions per element with All/Any; today's single condition stays the first row.
- Why: the classic OR cases tie two different values; one element, its words translated once, running sites unchanged.

## pg-hide-if-empty everywhere? (W23)
- Every widget kind, with a panel switch; hand-written marks elsewhere start working; starter templates unchanged.

## Words beside a value?
- The value is an inline element inside one translatable sentence; the translator places it where the language needs it.
- Why: keeps the old words, translatable and designable; the translation screen already refuses a translation that drops the value.

## Code areas and the classic bridge? (W22)
- A converter turns a classic form layout into design elements on request: a copy, values bound, if/else rules become show conditions, words translatable. No PHP code area in blocks.
- Why: the starter site's form layouts hold no PHP; no new code-execution door, pages stay cacheable, hosted sites work.
- W22b (2026-10-27, given by the coordinator under the owner's delegation: "bekleyen işçilere yanıtı sen ver benim yerime", "en makul ve işlevsel seçimle"): rows the layout does not draw go at the end of the form in their order, so no field is lost; the widget goes beside the classic region (opt-in, as Y5; the designer deletes the region); the comment notification box and Save for Later are not carried over, the convert step names each one left out.

## Page-level facts? (W17, W18)
- The page's own record (allow-listed fields) plus three visit facts: signed in, cart has items, cart item count. They only choose which element shows; no personal data.
- Why: covers the most wanted visitor-dependent bands with translatable elements; caching stays correct; the list starts narrow.
- W17/W18b (2026-10-27, by the coordinator under the same delegation): the record is the one the address opens (product, event, form record), never the page row; its values show outside the widget, escaped as the widget prints them, empty with no record; the form record's public fields (title, date, summary, image, author name) join W8, nothing private.

## Per-placement settings? (W21)
- The look is shared. Each placement may only narrow its content: records filter, product group, calendar, menu or ad region, count, order. Texts stay in the widget.
- Why: look edited once, translations keep working, a page can only narrow so nothing hidden leaks, placements without values unchanged.

## Parts with controls? (W16 form blocks)
- All designable. The system owns what a control sends and whether it is required; a deleted required control comes back.
- Order: fixed forms first (gift card, recurring schedule, cancel, returns), the product order form after.

## Express order lists? (W24)
- Display lists first: applied offers, offer suggestions, applied gift cards (masked code, balance, remove link), items grouped by recipient. Offer texts enter Translations.
- Recipient address, shipping method and delivery date controls and the checkout flow stay as they are.

## Search result count grouping? (W12)
- Grouped by the page's language (1.284 / 1,284), like form lists and the heading ([[form-list-result-count]]).

## Canvas default state? (W19)
- The canvas opens on the page's first state ("Completed" in the order view); "All elements" stays one click away ([[widget-condition-canvas]]).
