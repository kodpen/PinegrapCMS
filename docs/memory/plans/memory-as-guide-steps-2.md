---
name: memory-as-guide-steps-2
description: Steps MG5-MG10 of plans/memory-as-guide.md: an overview each for flows, list table, Paraşüt, designer blocks, custom forms and order totals; claim as [plan:memory-as-guide] MG<n>
---

## How is an overview step taken?
- Read the whole cluster, then write the overview: what the feature is, the decisions that bind it, its parts by the label a person sees, which file holds what, how a new part is added. At most 4 KB; the topic files stay.
- What a trial reported is checked against the code before it is written down. A contradiction between two files is settled from the code; a product choice is asked (`workflow/architect-asking.md`).
- These steps touch different folders and run side by side; none changes the product.

## Steps
- MG5 Flows: `workspace/workspace-flows-overview.md` for the 107 `workspace-flow*` files.
  - The box kinds of `ws_flow_kinds()` by their label, so "is it there already?" is answered first: the trial was asked for a Wait box, which is kind `delay`.
  - What drives a run: who calls `ws_scheduled_run()` and how late a wake can be.
  - `workspace-flows-adding.md` becomes the full recipe. It does not name the case in `ws_flow_graph_normalize()`, `ws_flow_node_texts()`, the dispatch in `ws_flow_step_run()`, `ws_flow_member_kinds()` or the bundle build.
  - Corrects "`WS_FLOW_DELAY_MAX` is a week" in `workspace-flows-template-events.md` (90 days).

- MG6 List table: `ui/list-table-overview.md` for the 44 `list-table-*` files, which tell which batch moved which screen.
  - The recipe for a new list on the driver: the screen to copy and what not to copy from it (the old file header, `mysqli_query()`), bulk and row buttons, the empty list.
  - Where a new panel screen is registered: `panel-help-links.md`, `main-menu-entry-gates.md`, `main-menu-panels.md`, `command-palette.md`.
  - Says which filter control a new list takes (`page-button-bar.md` asks for a select, `list-table-driver-sort.md` has filter buttons) and when a list is named in `pg_list_table_printed_lists()` (`list-table-printed.md`, `list-table-commerce-lists.md`).
  - Takes the row checkbox sentence out of `list-and-table-screens.md`: its next line says there are none.

- MG7 Paraşüt: `erp/erp-parasut-overview.md` for the 29 `erp-parasut-*` files.

- MG8 Designer blocks: `designer/designer-blocks-overview.md` for the 26 `designer-blocks-*` files.

- MG9 Custom forms: `designer/custom-form-overview.md` for the 17 `custom-form-*` files.

- MG10 Order totals: `commerce/order-totals-overview.md`, a subject spread over two folders.
  - Which surfaces work totals out and which read the stored header; where a kurus may differ between the lines and the total, and which of those are decided: `commerce/offer-percent-rounding.md`, `offer-discounted-units.md`, `product-tax-rate.md`, `erp/erp-discounted-order-vat.md`, `erp-vat-per-rate-group.md`, `erp-order-charges-vat.md`.
  - A rounding key rule in the commerce README. Fixed rule 7 of `CLAUDE.md` says "round with `round()`" and `offer-percent-rounding.md` says never a float `round()` for a percentage: the rule names `pg_percent_of()` beside it.
