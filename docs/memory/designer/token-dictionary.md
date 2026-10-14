---
name: token-dictionary
description: System widget token namespaces (bare names vs __ prefix), shared cart vocabulary, raw-HTML key escaping, row id uniquify, and tools/check_bindings.php.
---

## How are token namespaces split?
The boundary is a rule, not an accident.
- **Bare name** (`^^reference_code^^`, `^^submitted_date_and_time^^`, `^^email_address^^`): submitted form **system fields**, resolved by classic screens with exactly those names; record is `get_standard_fields_for_view()` (`includes/fn/forms.php`). A system widget does not invent its own name.
- **`__` prefixed** (`^^__cart_subtotal^^`, `^^__item_price^^`): state the widget computes (prices, counts, addresses, renderer-built blocks). The prefix prevents competing with a name a visitor may give a form field (`^^form_id^^` once did).

## Do widgets share names?
- The same value has the same name in every widget. Cart, express order and order view show the same cart, so they share one vocabulary: `__cart_subtotal`, `__item_price`, `__item_total`, `__item_url`, `__item_description`.
- Two empty states are two names, and the setting key equals the token name: `empty_message` / `^^__empty_message^^` (list empty), `not_found_message` / `^^__not_found_message^^` (requested record missing).

## Where is escaping done?
- One place: `pg_sw_escape_token_values()` escapes the whole map; keys that are **raw HTML are listed by name in `pg_sw_raw_token_keys()`** (operator's rich text, prices open to markup on discount, renderer-built blocks). The alternative, each renderer remembering what to wrap, broke layouts when forgotten.
- `products.full_description` is raw, `short_description` escaped: all classic templates print them this way.

## How are loop row ids kept unique?
- `pg_sw_uniquify_row_ids()` makes ids unique and carries seven id-referencing attributes together: `id`, `for`, `data-bs-target`, `aria-controls`, `aria-labelledby`, `href="#…"`, `data-bs-parent`. If one is left behind, an accordion closes another row.

## What does tools/check_bindings.php check?
- Compares both ends: is every `__token` the designer offers produced by a renderer, and is every produced token in a dropdown. The second matters: a token missing from the list must be typed by hand under "Özel", and invented token spellings entered the repo that way.
- Exempt shapes: `*_inner_html` (section-binding pass writes the tree itself) and `__link_label` (renderer expands it per node to `__link_label_N`).
