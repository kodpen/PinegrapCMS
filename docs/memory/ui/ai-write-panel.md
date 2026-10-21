---
name: ai-write-panel
description: The "Write with AI" button and draft panel (pg_ai_write_button(), pg_ai_write.js): where it is, adding it to a field, what the panel does, what the server adds
---

## Where is it?
- Product screen (`product_builder.php`, create and edit; PR #583): Short Description, Full Description, Web Browser Title, Web Browser Description. Not for a product in the Recycle Bin.
- Page properties (`edit_page.php`): Web Browser Title and Description.
- Visual designer settings, Only this page › Search Engines (`pg_designer_settings_modal()` in `includes/designer_screen.php`, PR #586): Page Title and Meta Description. The button reaches the row through `pg_designer_setting()`'s `'action'` option, which puts it beside the setting's name with `pg_ai_write_label_row()`, so the panel opens under the name. `record_from: 'designer'` makes the script take the page id of the open tab at click time (`StyleDesigner.aiBridge().page()`); the facts come from the saved page, so unsaved canvas edits are not read. `theme: 'dark'` gives the panel `data-bs-theme="dark"` on the designer's dark modal.
- Custom form records (`add_submitted_form.php`, `edit_submitted_form.php`, `pg_ai_write_form_button()`, PR #585): every text area, and a text box the form feeds as an item's title or description (`rss_field`); never a field mapped to a contact field (`pg_ai_write_form_writable()`). The draft reads the form's text fields by id, labelled from `form_fields`. Text boxes there carry their field id as element id and the labels a `for`.
- The endpoint and its rules: docs/memory/ui/ai-write.md.

## How do I add it to a field?
- `pg_ai_write_button($user, array('target', 'record', 'record_id', 'field' => element id, 'context' => key => element id))` returns '' when the site or the person cannot ask (`pg_ai_write_available()`). Put it beside the label with `pg_ai_write_label_row($label_html, $button)` (label alone when there is no button).
- Print `pg_ai_write_assets()` once after the form: the words (`window.PinegrapAiWrite`, from `pg_ai_write_js_config()`) and `assets/js/pg_ai_write.js`, only when a button was drawn. The script is served directly (no bundle, no `.min.js`).

## What does the panel do?
- The button opens a panel under its label row, above the field, and asks at once. Clicking it again, the close button or Escape closes it; an answer still on its way is then ignored.
- Tone select (Informative, Friendly, Formal, Persuasive; a change asks again and is remembered for the next panel on the page), an optional request (Enter asks again when no request runs, #746), "Use this text", "Try again". Buttons are disabled only while a request runs.
- Context is read at click time from the button's element ids, through the rich text editor running on the id: unsaved edits count.
- "Use this text": a one-line draft is set as `.value` with `input` and `change` events (SEO counters, unsaved-changes checks); the html format goes into the editor (CKEditor `setData()`; TinyMCE: ai-write-tinymce.md), else the textarea. Nothing is saved until the form is. Then `pg:ai-write-used` is sent: docs/memory/ui/ai-write-marks.md.
- The html draft is shown with `innerHTML` only because the server built it from escaped words; other drafts (`white-space: pre-wrap`: lines kept) and errors are `textContent`. When 20 or fewer drafts are left today the panel says so.
- On the browser's model (docs/memory/ui/ai-write-browser.md) the title says so and the body shows the download and the translation as they run.

## What does the server add?
- A stored product: its group names and attribute choices ("Renk: Kırmızı"). A stored page: its folder name, its h1–h3 headings and its text, from `page_tree_code` for a visual design page or the live regions (`pregion`, collection `a`) of a classic one (`pg_ai_write_facts()`, `pg_ai_write_html_digest()`). Only fields the form left empty are filled.
- The price gets the store's currency symbol; a zero price is left out (`pg_ai_write_price_text()`).
- Settings › Languages says the style note is read here too.
