---
name: workspace-flows-template-saved
description: Flow templates saved on the site (G3a #3146, G3b #3242): ws_flow_templates, what a save keeps, settings, rights, panel actions, the editor's Save as template, the gallery's saved cards
---

## Where is it?
- Table `ws_flow_templates` (2026.11.0, step 11.0.115, decision #3120, `workflow/decisions-made-schema-4.md`); code `includes/workspace/flow_templates_saved.php`, tests at the end of `tests/workspace_flow_templates_test.php`.
- Key `site_<id>` (`ws_flow_template_saved_key()` / `_saved_id()`): the row goes in with a passing `new_<hex>` key, then gets its own, so the UNIQUE index never meets ''. No built-in key starts with `site_` (tested).

## What does a save keep?
- `ws_flow_template_save($viewer, $flow, $data)`: the draft of a flow the person may edit (`ws_flow_for()` at edit), title 1-160, help up to 255 (one line each), category one of team, site, sales (`ws_flow_template_fields_check()`); a flow without boxes is refused; at most 200 rows not binned per site. Site Log line on save and delete.
- Secrets dropped (`ws_flow_template_secrets_dropped()`): a webhook box's address goes whole (a chat hook carries its secret in the path, as `ws_flow_url_masked()` says); any other address loses its `user:password@`. `{{node…}}` values stay, so threads stay.
- Settings (`ws_flow_template_settings_of()`, the built-ins' shape, one per type): the box keys of `ws_flow_template_setting_keys()`; the targets are the boxes holding the same value as the first box of that type, so one answer replaces that value and a box with its own keeps it. Not asked: channel 0 (any) or -1 (joined), nobody, a `{{…}}` value, an empty one, but a webhook's dropped address is asked.
- The values in the boxes are the defaults: the setup panel fills each field from the first target's current value.

## How is it read?
- `ws_flow_templates_for()` appends `ws_flow_templates_saved($viewer)`: to everyone who may build flows (`ws_can_schedule()`), latest changed first. So the gallery, the setup action and the API's template list and Use take them with no more code.
- Entry (`ws_flow_template_saved_entry()`): category `saved` ("Saved on this site", last in `ws_flow_template_categories()`), the row's own group in `saved.category`; `start`, `boxes`, `threads`, `motion` computed from the graph; settings of unknown types or on gone boxes dropped; `saved` also has id, created_by, source flow, updated_at, `edit`.

## Who may change or delete one?
- `ws_flow_template_may_change()`: its saver, or a staff member at role 2 (Manager) or above (0, 1). Builders are role < 3 today, so all of them may; the saver rule is for a wider builder set later. Delete sets `binned_at`; flows made from it keep their boxes.
- Erasure: as `ws_flows`, nothing rewritten; `created_by`/`updated_by` keep the number.

## Which panel actions?
- `ws_flow_template_save` (flow_id, title, help, category), `ws_flow_template_update` (key, any of title, help, category), `ws_flow_template_bin` (key): `includes/workspace/actions.php`, rows in `pg_panel_actions()`.

## Where does the panel use them? (G3b, #3242)
- Editor ⋯ menu: "Save as template" when `ws_flow_js_config()`'s `template_save` (builders, table there), the flow editable (one without boxes is refused in the dialog). One dialog (`templateDialog()`, `workspace_flow.js`): title (the flow's name), one line, group (the categories but saved); saving `flush()`es first, as the server reads the draft.
- Gallery: saved cards under "Saved on this site" with a badge of their own group (`savedMark()`); edit (same dialog) and delete (`K.ask`) only where `saved.edit`. `FL.templates` is kept in step by `templatesKept()` (pure, Node test in the canvas test); no reload.
- Focus: the gallery is drawn again only once every dialog is closed (`whenDialogsClosed()`: the page behind a modal dialog takes no focus; `K.ask` answers before its fade ends); `showGallery()` keeps `state.refocus` past `showNone()`, which would spend it on the flow list. Harness scenario "Save as template".
