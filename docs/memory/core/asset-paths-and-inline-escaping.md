---
name: asset-paths-and-inline-escaping
description: Local asset paths decoded before the parent-folder check, design asset files written under a plain name only, the hero's typed style escaped, the variant payload script with \u-escaped tags
---

## How does pg_asset_path() keep to its roots?
- Symptom (#7673): the `..` check ran before `rawurldecode()`, so `/%2e%2e/%2e%2e/etc/x.css` passed and `is_file()`/`filemtime()` ran outside the asset roots; the `?v=` version leaked whether a file existed and its time.
- Now `pg_asset_decoded_path()` (`includes/fn/output.php`, pure) decodes first, then refuses an empty path, a `..`, a backslash or a NUL byte. `pg_asset_path()` also takes a file only when its `realpath()` lies under the `realpath()` of its root (`FILE_DIRECTORY_PATH`, then the web root `dirname(PG_FUNCTIONS_DIR)`), so a symbolic link out of the root gets no version either. Test: `tests/asset_paths_test.php`.

## Which design asset names are written to disk?
- Symptom (#7705): `pg_designer_sync_asset_files()` wrote `FILE_DIRECTORY_PATH . '/' . name` for every `css`/`js`/`json` entry of a design save's `style_custom_css` / `style_custom_js`, so a name like `../../assets/js/backend.min.js` (any user with design write) overwrote files outside `data/files/` and added a `files` row with that name.
- Now an entry whose name `pg_migration_design_file_name_valid()` refuses (a folder, a backslash, a NUL, a leading dot, an extension other than css/js/json) is skipped: no file, no row. The rule is the one the Migration screen already applies to received designs (`designer/migration-carries-designs.md`); both modules are always loaded by `functions.php`. A name like `x.php.js` passes this rule; only the Migration's list check adds `pg_upload_name_blocked()`.

## Is the hero component's typed style escaped?
- Symptom (#7706): the `style` typed into the Attributes panel was joined into the hero's `style="background:…;…"` raw, so `x" onmouseover="…"` added an event handler to the published page. It goes through `h()` now, like navbar, card, carousel, alert, accordion and tabs. Test: `tests/designer_hero_style_test.php`.

## How does the variant chooser's payload stay inside its script?
- Symptom (#7606): `window.pgCivVariants.push(…)` was encoded without `JSON_HEX_TAG`, so a rich variant description holding `</script>` ended the inline script: the chooser broke and the rest of the JSON showed as text.
- `pg_civ_variants_script()` (`includes/fn/widgets_catalog.php`) builds the inline script with `JSON_HEX_TAG | JSON_HEX_AMP`; the translation pass (`pg_tr_render_json_strings()`) re-encodes with `JSON_HEX_TAG` too. Test: `tests/civ_variants_script_test.php`.
