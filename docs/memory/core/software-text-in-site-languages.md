---
name: software-text-in-site-languages
description: Which language the software's own texts take on a site page in a language with no language file: English, except the cookie window (owner decision 2026-10-09), and how that exception is fed.
---

## Which language files ship?
- Only Turkish (`includes/local/tr/`, sorted shards) and `en.json`. A language with a file or folder always uses it. No `includes/local/<code>` file or folder is added for a language a site is trying out.

## What does a page in another site language show?
- Owner decision (2026-10-09): the software's interface language comes from the settings and is not part of the site's translation. On a page drawn in a language with no file (ko, de...) the software's texts (toolbar, dynamic code block, widget labels) stay English.
- The one exception is the cookie window, which every visitor reads first: only the keys listed in `pg_consent_ui_keys()` (`includes/fn/consent.php`) are read from the translation store, `lang()` -> `pg_tr_ui_text()` -> `pg_tr_ui_translate()` (`includes/translate/content.php`), and are translated on the Translations screen in the "Cookie window" group (stored as `owner_type 'ui'`; the name is historical). Until a translation exists the English wording stays.
- Widening that list, or taking the toolbar or the dynamic code block into translation, breaks the decision: open a decision issue first (`workflow/architect.md`).

## How is the Cookie window group kept in step?
- `pg_tr_ui_sync()` runs whenever the Translations screen extracts the group (`pg_tr_extract_group('ui')`): it records the window's texts before any visitor has seen them, and deletes the `ui` uses that are not on the list, with the translations only they had (what an earlier release took in).
- A new text in the notice goes into `pg_consent_ui_keys()` too; `tests/consent_test.php` compares the list with the notice's `lang()` calls.

## What does not go this way?
- Database text passed through `lang()` (a label, a product name) is content and belongs to the page's translation; `pg_tr_ui_source_template()` takes only keys the Turkish file knows.
- Placeholder Latin (`pg_tr_placeholder_latin()`) is never sent for translation and is shown as written; sample content is not written in Lorem ipsum anyway (`designer/designer-sdt-translation.md`).

Related: docs/memory/platform/cookie-consent-notice.md, docs/memory/core/translation-rules.md
