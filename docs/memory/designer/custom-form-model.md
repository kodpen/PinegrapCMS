---
name: custom-form-model
description: custom_form widget model: controls are the fields (no field list), column sources, props._cf record, form_source page vs existing.
---

## Is there a field list in `custom_form`?
- **No separate field list.** Every `input` / `select` / `textarea` inside the widget (hidden input included; submit/reset/button/image excluded) is a field of the page's form, and its definition sits on the control. On every save the server derives field rows from the widget tree; the editor sends only form-level settings.

## Where does each field column come from?
| Column | Source |
|---|---|
| `name` | `name` attribute (panel "Field Name") |
| `type` | tag + `type` (`_pg_cf_type_by_input()` / `CF_TYPE_BY_INPUT`; any `type` not in the list is a text box) |
| `required` | `required` attribute |
| `label` | `<label for>`; in a radio/checkbox group the enclosing `<fieldset>`'s `<legend>`; else `_cf.label`; else placeholder / aria-label; else name |
| options | `<option>`s; same-named boxes in a group |
| `default_value` | `value` attribute / textarea text |
| `validation_regex` | `pattern` attribute |
| `multiple` | `select[multiple]` or checkbox |

- An office-use-only pick list needs a real selected option as its default: an option with value `''` is not stored (`_pg_cf_controls()`), and the panel's submitted-form edit screen (`edit_submitted_form.php`), which prints only stored options, then saves the first option ([[design-template-magazine-issues]]).
- What markup cannot say lives in the node's **`props._cf`**: `ids` (`{page_id: form_fields.id}`), `label` (if the label element was deleted), `rss_field`, `contact_field`, `office_use_only`, `url_param` (address parameter the field starts with, never in a row, `custom-form-url-prefill.md`), `validation_message`, `upload_folder_id`, `quiz_question` / `quiz_answer`, `wysiwyg` (text area with the rich-text editor; absent = the row keeps its flag, `custom-form-rich-text.md`).

## What does `form_source` mean?
- `page` (default): the form belongs to the page the widget **stands on**; controls are its fields. Template: one input + CAPTCHA + Submit (`_cfStarterTree`).
- `existing`: another page's form is shown here. On selection the widget's inside is rebuilt from that form's fields (`_cfBuildFromFields`); fields are **read-only**, edited on that form's own page. An orphan form (`pg_cf_form_is_orphaned`) can be moved to this page.
- If `form_source` is missing: `form_id` set => `existing`, empty => `page`; an `existing` pointing at the page itself counts as `page` (`_cfWidgetOf().owns` / `_pg_cf_page_form_widgets()`).

Related: docs/memory/designer/custom-form-sync.md
