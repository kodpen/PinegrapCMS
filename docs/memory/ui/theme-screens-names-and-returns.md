---
name: theme-screens-names-and-returns
description: Theme screens: no ".." in stored names, the design-file gate, the new name said, upload errors on the form, Edit CSS's way back, System refused on the server
module: files
---

## Why can a theme with ".." in its name be opened again?
- `prepare_file_name()` turns any run of dots into one (after the length limit, which can cut right after a dot), so `../x.css` is stored as `file._x.css`.
- The design file gates (`edit_theme_file`, `edit_theme_css`, `edit_design_file`, `edit_javascript`, `editor_edit_file`) refuse a name with `pg_design_file_name_unsafe()`: empty, `.`, `..`, a slash, a backslash or NUL. They used to refuse any `..` inside the name, so a record stored earlier as `file.._x.css` opened 404 and could be neither renamed nor deleted (#5795). Saving it again stores it with one dot.

## When is the user told the stored name differs?
- `pg_file_name_saved_as_notice($typed, $stored)` adds "Saved as …: a file name is its address…" to the notice of Edit Theme's Save and Create Theme's upload, as the File Manager does (#5694, #5798).

## What does Create Theme answer for a bad upload?
- No file: `pg_upload_file_missing()` is read before `prepare_file_name()` (which makes "" into "file"), so the box says "File is required." (#5797).
- A taken name is the file box's error; folder and description come back with the form (it was a site error page).
- `theme_type` other than `custom` is "The Theme Type is not valid.": System is closed on the form and now on the server too (#5799). The system branch stays in the file, unreachable.

## Where does Edit CSS go back to?
- `$edit_theme_css_back`: `send_to` checked by `pg_safe_back_url()`, else the themes list, made a root path. Cancel and Save and Return (through `reload_theme.php`) both use it; Save and Return without `send_to` used to land on the site's home page.
- Save reloads the screen with `send_to` and `from`, so the breadcrumb and Duplicate's target survive (#5796).

## Why does generate_system_theme_css() log no warnings on sparse rules?
- Stored rules hold only what was set and the builder reads every key unguarded. The public function runs the builder (`generate_system_theme_css_build()`) under an error handler that swallows only undefined key/variable/offset-on-null warnings; anything else reaches the previous handler.
