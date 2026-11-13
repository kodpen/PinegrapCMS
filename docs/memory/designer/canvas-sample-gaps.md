---
name: canvas-sample-gaps
description: CS0 census (#9179): what the designer canvas draws for each bound value (sample, ‹label›, empty, grey box, wrong sample); shared causes; false warning frames
---

## What is this list and how was it made?
- CS0 of `plans/designer-canvas-real-samples.md`; CS2-CS4 take their families from it; deleted at the plan's close-out. Families: `canvas-sample-gaps-orders.md`, `-checkout.md` (CS2), `-forms.md` (CS3), `-catalog.md`, `-account.md`, `-other.md` (CS4).
- Read from code (2026-11-13), no browser run: every token of the Bind Data palettes (`SW_*_TOKEN_GROUPS`, entity lists) and `SW_LIST_SOURCES` put through `_displayValueForBinding()`'s order for a text binding. About 1,190 offered values (one offered in two places counted twice): 430 draw a sample, 140 are addresses (`#`), 340 a ‹label›, 240 a generic, 28 a grey box, 8 empty flags.

## What does each kind of drawing mean?
- **sample**: a value of its own from `_SD_SHORT_LABEL_TOKENS`, `_SD_CURRENCY_TOKENS`, `_SD_NUMERIC_TOKENS`, `_SD_HTML_PREVIEW_TOKENS`, `_SD_STANDARD_FIELD_PREVIEWS`, `_SD_LIST_ROW_PREVIEWS`, a widget setting, or a comment or list sample row.
- **‹label›**: no sample; `_sdTokenPlaceholder()` draws the Bind Data label in angle quotes.
- **generic**: the name heuristic at the end of `_displayValueForBinding()`: "Example" for a name, city, status or title; "1" for a count or number; the site's money sample for price, total, tax, shipping, discount, fee, amount, cost, balance (wrong when the guess misses: the family files name them); a stock blurb for a description.
- **grey box**: an `src` or `poster` binding draws `_sdImgPlaceholderForToken()`, a grey box with "Image" and `[token]`; only `__user_avatar_url` and `avatar_url` draw a sample (initials).
- **empty**: a flag of `_SD_FLAG_TOKENS` (on purpose), or a `^^token^^` typed in a node's text with no binding: `_sdResolveAttrTokens()` fills only the HTML, number, short label and money maps (and `__form_id`, `__item_id`) and drops every other token, so the element is an empty box.

## Which causes cut across every family?
- The heuristic reads parts of a name: "shipping" and "tax" make an address line, a tax number or a yes/no money; "count" makes `__account_title` and `__watcher_count_text` "1"; "number" and "country" make phone numbers, PO numbers and countries "1"; "fee" makes `__a11y_feedback` money.
- A yes/no value not in `_SD_FLAG_TOKENS` (`__is_new`, `__featured`, `__has_variants`, `__loop_is_first`, `__field_is_info` …) draws its ‹label›, which is not empty: a "Show only when … is empty" on it hides its node in the sample state, a "not empty" shows it.
- HTML slots outside `_SD_HTML_PREVIEW_TOKENS` (forms, buttons, option lists, filter blocks, `…_html`) draw a ‹label› line where the page draws a control.
- Every bound picture is the grey `[token]` box (CF4 of `plans/designer-canvas-faults.md` takes the product picture).
- Dates outside the short label map draw ‹label›.

## Which warning frames sit on an element with no fault?
- `_sdListBandIn()` (amber list band, `sd-list-off`) decides by the widget kind of `_sdValueSidOf(node)` and by `_sdInMainLoopAt()`: an e-mail card built by a template tree, a list in a shared part, or an items loop that is a sourced list rather than the main loop can read as "this widget draws no such list" or "outside an item of the order" though the page draws it. CS1 confirms it on the canvas.
- The red dashed section hint ("The child elements here are replaced with BACKEND HTML at render time", `sd-section-binding-hint`) shows on every section binding with children, a correct starter too: it states a fact, not a fault.
- The owner's picture (#9076): three ‹Information (HTML)› rows are the product form's fields in the order e-mail; `__field_is_info` draws its ‹label›, so every field takes the information branch (`canvas-sample-gaps-orders.md`). The two empty framed boxes after "Order Number:" were not found by reading; CS1 checks them on the picture's card.
