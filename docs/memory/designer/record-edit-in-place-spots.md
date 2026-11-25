---
name: record-edit-in-place-spots
description: Which bound elements of a page the Preview lets a person correct in place for one collection item, and with which control (RE2, #11583): PGRecordEditSpots, scopes, tokens, properties
---

## Where is it?
- `assets/js/pg_record_edit_spots.js`, `window.PGRecordEditSpots`: pure, reads a page tree and the item's fields, touches no element. Not in a bundle yet: RE3 adds it as a source of its designer part, as `pg_corner_box.js` is of `spacing`.
- `find(tree, fields, opts)` answers each spot in drawing order as `{node, prop, field, control, token, widget}`: `widget` is the `_id` of the outermost `shared_ref` the node is drawn through, '' on the page itself.
- `opts`: `formId` (the item's form), `lookup(node)` giving a `shared_ref`'s `{kind, formId, tree}` (kind '' for a shared component; in the editor from `_sharedCache`), `scope: 'widget'` for a detail widget's own tree, `pageRecord` to say whether the page opens the item.

## Which fields?
- The `fields` of `record_edit_read` (`record-edit-in-place-server.md`): only fields with a control. The read names each field's RSS role as `rss` (added in RE2).

## Which bindings name a field?
- Inside a `form_item_view` of the item's form: the field's name, the name stripped to `[a-z0-9_]`, its label, `field_<id>`, case aside, as `pg_sw_index_form_data()` answers them; `__post_title` and `__post_image` the fields of the RSS title and media roles. The first field wins a name two share.
- Outside widgets, only when the page's first record widget (form, product or event view) is a `form_item_view` of the item's form, as `pg_page_record_publish()` keeps the first: `__record__field_<name>` (lowercased, stripped to `[a-z0-9_]`), `__record__post_title`, `__record__post_image`.
- A shared component is walked in the scope it stands in. A list, a form, another form's detail widget or a product view is not walked.

## Which property takes which control?
- `text`: text, rich, number, date. `html`: rich. `src`: picture. `alt`: text (`accepts(prop, control)`).
- Not in place: an address (`href`), a summary (`__post_summary`, cut from the description), a field whose control does not fit the property, a binding of another record.

## Which test guards it?
- `tests/record_edit_spots_test.php` runs it in Node; `test_record_edit_fields_out` in `tests/record_edit_test.php` keeps `rss` in the read.

Related: docs/memory/designer/record-edit-in-place-server.md, docs/memory/designer/page-record-values.md
