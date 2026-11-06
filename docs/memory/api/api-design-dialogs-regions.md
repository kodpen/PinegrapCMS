---
name: api-design-dialogs-regions
description: Auto dialogs and common regions over the external API (/design/auto-dialogs, /design/common-regions): the write path the four screens share, routes, refusals, the converted region that keeps its row
module: auto_dialogs, common_regions
---

## Where is an auto dialog or a common region written?
- `includes/fn/auto_dialogs.php`: `pg_auto_dialog_save()` (the eight values of `pg_auto_dialog_form_values()`, in its order), `pg_auto_dialog_delete()`, `pg_auto_dialog_name_in_use()` (null on a failed query; `pg_auto_dialog_form_validate()` asks it too).
- `includes/fn/common_regions.php`: `pg_cregion_save()` (content null keeps the stored one, as for a converted region; type null keeps the stored one) and `pg_cregion_delete()` (takes `users_common_regions_xref` with it).
- `add_auto_dialog.php`, `edit_auto_dialog.php`, `add_common_region.php`, `edit_common_region.php` and the API handlers call them; the screens keep no SQL write and save or delete the `$id` checked at the top, not a posted `id`.

## Which routes are there?
- `/design/auto-dialogs` and `/design/common-regions`: list (oldest first, cursor paged by id; auto dialogs filter by `enabled`), GET one (`design:read`), create (201), update (POST, PATCH also accepted), DELETE and its `POST …/delete` twin (`design:write`, owner ceiling designer, narrower than the manager-gated auto dialog screens). Ids `design.auto_dialogs.*`, `design.common_regions.*`; handlers in `includes/api/resources/design_dialogs_regions.php`, which reuses the paging, `{path}` and log helpers of `design_ads.php`. No events.
- Auto dialog: name and URL required (422), within their columns; numbers 0 to `pg_auto_dialog_form_number_limits()`, null or 0 is the screen's blank; page loses a leading slash; a create starts disabled as on the screen; name unique (409 `name_taken`).
- Common region: only `cregion_designer_type = 'no'` rows; a designer region is 404 (it is a designer's code and shares the table). Name by `pg_region_form_name_refusal()` (422) and `pg_cregion_name_taken()` across both kinds (409 `name_taken`). `content` in through `prepare_rich_text_editor_content_for_input()`, out with `{path}` expanded. `shared_component_id` names the component drawing it, null for none.

## What does a converted region refuse?
- Delete: 409 `region_is_shared_component` while its component exists, before the dry run stop (#5516, as `edit_common_region.php`): the row is the tag's link to the component and its undo.
- Update with `content`: 409 `region_is_shared_component` (the screen shows no content field; the component is edited in the designer). A rename is taken, as on the screen.

## Tested?
- `tests/api_design_dialogs_regions_test.php` (routes, scopes, limits, partial updates, check/stop/write order, screens without SQL writes), `tests/auto_dialog_form_test.php`, `tests/common_region_form_test.php`. Not run against a database.
