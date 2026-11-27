---
name: software-languages
description: The panel's languages: pg_software_languages() (English, Turkish, German; shipped list cut to wording on disk), the 17 codes config.software_language's ENUM holds, its readers, the 'tr' tests kept.
module: software_languages
---

## Where is the list of the panel's languages?
- `includes/fn/software_languages.php` (2026.11.0, plan panel-language-packs PL1). `pg_software_language_catalog()` is the shipped list: code => own name (`English`, `Türkçe`, `Deutsch`), direction (`ltr`/`rtl`), first day of the week (0 Sunday, 1 Monday).
- `pg_software_languages()` gives the offered ones: the catalogue cut to codes the ENUM can store and whose wording is on disk (`includes/local/<code>/*.json` or `<code>.json`; English needs none). A folder outside the catalogue is not offered.
- A new panel language is one catalogue line plus its wording; nothing else names a language one by one.
- German joined in 2026.11.0 (plan panel-language-german DE9): `de`, "Deutsch", `ltr`, week from Monday. Its wording, figures and the top-up: [[lang-pack-german]].

## Which codes can config.software_language store?
- `config.software_language` stays an ENUM, never a VARCHAR: the `config` row has no room (`data/config-table-no-varchar.md`); an ENUM of up to 255 members is one byte.
- 11.0.281 (`upgrade_2026_11_0_software_language_codes()`) widened it with `install_modify_column()` to `en`, `tr`, `de`, `fr`, `es`, `it`, `pt`, `nl`, `pl`, `ru`, `uk`, `ar`, `fa`, `he`, `ja`, `ko`, `zh`, default `en`; stored values stay. A column already listing them is skipped.
- `pg_software_language_codes()` returns that list; `tests/software_languages_test.php` compares it with the step's literal. A further code needs a new step and a new line there: a released step never changes.
- Fresh installs get the wide ENUM from the step too (the starter dumps are older and every install runs the migrations); the installer's fallback `ALTER` builds its ENUM from `pg_software_language_codes()`.

## Who reads the list?
- Site Settings > General, Software Language select (`includes/settings/prep.php`, `pg_software_language_options_html()`), each language under its own name. Its save (`general.save.php`) refuses a code that is not offered with "Choose a software language from the list." and never writes an empty value.
- The installer's select, `?local=`, browser language and designer words (`install/index.php`, which loads the module before `functions.php`), `PG_INSTALL_LANGUAGE` (`includes/install_cli.php`), `router.php`'s `?local=` and `dedect_user_language()`.
- The browser language is the first Accept-Language tag that is offered (`pg_software_language_from_header()`), else English; weights are not read.
- The panel's `<html lang>` prints the code (`output.php`, escaped with `h()`).

## Which comparisons with 'tr' were kept, and why?
- Date pickers' week start (`get_date_picker_format()`, `get_date_time_picker_format()` in `fn/core.php`) is the language's `first_day`, no longer a `'tr'` test.
- Kept: `erp_percent_text()` (`%20` is a Turkish way of writing a percentage), `pg_pb_google_taxonomy_locale()` (only the English and Turkish taxonomy files ship), `toLocaleLowerCase('tr')` in `view_folders.php` (folds the dotless i for search), `firstWeekday()` in `pg_fields.js` and the booking calendar in `style_designer.src.js` (fallbacks when no picker options are on the page), the image editor model's number `format` `en`/`tr` (a stored data format, not the panel language).
