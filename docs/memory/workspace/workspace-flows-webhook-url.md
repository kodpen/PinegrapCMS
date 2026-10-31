---
name: workspace-flows-webhook-url
description: Numbers in a webhook box's address (decision #3410, PR #3471): path or query only, digits URL-encoded, refused in host or scheme and at run when not digits; canvas lights it for numbers
---

## Which address takes a value?
- Only the webhook box's `url`: `ws_step_kinds()` gives it `urls => ['url']`. Every other address or recipient (web check URL, e-mail `to`, report `to`, people) is never filled. Test `tests/workspace_flow_webhook_url_test.php`.
- Before (#2414-1b decided, unbuilt) a `{{node.*}}` in the URL was sent as written.

## How is it filled?
- `ws_step_url_fill($url, $values)` (pure): each `{{node.<id>.<field>}}` / `{{trigger.<field>}}` after the host must be digits only (`^[0-9]+$`) and goes in `rawurlencode()`d. A text, negative, decimal or empty value (a skipped box) is the error and fills ''. `ws_step_fill_action()` fills the `urls` keys with it after the texts.
- `ws_step_url_refusal($kind, $action, $values)`: the run (`ws_step_run`) settles the box failed with that reason before `ws_scheduled_do()`, without a retry (the value will not change). The box try refuses it only with a run picked; without a run the value is empty, as the texts'.

## Where may it not go?
- Host and scheme: `ws_step_url_host_end()` ends the authority at the first `/`, `?` or `#` after `://`; `ws_step_url_host_refs()` lists references starting before it (`https://example.com{{…}}` and `:port` included). `ws_step_config_problems()` makes that a save/publish problem; the fill refuses it at run too.
- A non-number output: `ws_flow_reference_problems()` gives `bad_reference` for a URL reference whose type (`ws_flow_node_field_type()`: text/outcome/status are text; step, constants, derived, loop and start outputs by their declared type) is not number. `ws_flow_node_texts()` adds the `urls` keys, so missing and skippable references are checked as in texts.

## What does the canvas do?
- `VALUE_FIELDS.webhook` has `url`, so every reference draws its thread; `fieldTakes()` lights the URL field for number values only. A dropped thread is put at the URL's end with no space, after a `/` when there is only a host (`urlWithValue()`).
- The side panel's address is a single-line value field whose "Add value" offers numbers only; help `fl_hook_url_help`. The web check keeps a plain input.
