---
name: migration-carries-designs
description: Migration screen Visual Designs switch (LD4): get_designs via the template export, managed files, install with target ids, skipped bin/drafts/Workspace folders, what stays behind
module: site_migration
---

## How does a visual design move between sites?
- `migration.php` has a "Visual Designs" switch. The target asks the source's `api.php` for `get_designs` (role 0-1, read-only, `includes/panel/actions.php`); the source answers every `style_layout = 'visual_designer'` style built by `pg_design_template_build()`, the same export behind "turn into a template" (`design-templates-from-design.md`): trees with `{{page:}}`, `{{tab:}}`, `{{folder:}}`, `{{contact_group:}}` placeholders, the shared components and widgets the pages place, folders, forms, comment settings, assets, look and palette. One serializer, no second one.
- The target checks each entry (`pg_migration_design_entry()`), writes it as a `design_template` row, runs `pg_design_template_install()` (no home page, look and palette as given) and deletes the row again. The install gives every id this site's value or takes back what it wrote; a failed design is named in a notice, the others go on.
- `pg_design_template()` falls back to `pg_design_template_custom_get()`: the cached list (`pg_design_templates()` static) does not hold a row written in the same request, and the Migration writes one per design.
- `explicit_trees`: a widget still holding the empty placeholder keeps it as its tree instead of `'starter'`, because the server cannot ask the editor for a starter layout.

## What about the design's files?
- Managed entries (`_file`, `designer_file/create`) of the CSS/JS lists travel with their content (`pg_migration_design_files()`): design files only (`files.design = 1`, not a theme file), not withheld, up to 2 MB. The target writes the ones whose name is free (file, page or folder) into the top folder as design files; a taken name keeps this site's file and is listed in the notice.
- The install writes every `css`/`js`/`json` entry of the template's asset lists under its own name into the files directory (`pg_designer_sync_asset_files()`; it skips a name with a folder itself), so `pg_migration_design_entry()` drops from the received lists an entry whose `.css`/`.js`/`.json` name has a folder, a leading dot or a refused extension (`pg_migration_design_asset_list()`): a source answering `../../…/backend.min.js` wrote outside the files directory (#7155).
- Pictures and other uploads a tree links to (`/files/…` addresses) are not carried; the links point at names the target may not have.

## Which rows does the classic copy leave out?
- `get_folders` and `get_pages` never send the Recycle Bin, Drafts or Workspace folders or anything below them (`pg_migration_skipped_folder_ids()`, #6006 bug part); the design export skips pages in them too.
- A classic style that is a visual design and a page marked `visual_design` (field of `get_pages`) are never copied as classic rows, switch on or off: their classic columns are empty. With the switch they come with their design; without it a notice counts the styles and pages left out and points at the switch.
- With the switch on, a source folder whose subtree holds design pages and no classic page is left out of the classic copy too (`pg_migration_design_only_folder_ids()`), with the folders below it: the design install makes its own folders, so no empty duplicates sit under "Migration".
- An older source answers `get_designs` with an error and the run stops before writing, with the reason.

## What is not carried?
- Only what the template shape holds: a page's meta tags, custom JSON-LD, canonical, schedule and search keywords stay behind; shared components no page places stay behind; shop pages and widgets are dropped on a target without the shop (`requires`).

## Tests?
- `tests/site_migration_test.php`: folder subtrees, managed file names, the entry check, classic rows and design-only folders left out, the shared builder and gates (source scan).
