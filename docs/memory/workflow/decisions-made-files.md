---
name: decisions-made-files
description: Decisions list, files part: a form file no view shows stays refused in a public folder; RSS media counts as shown with its record; folders and files are managed in the File Manager
---

## Where do these come from?
- Owner answers on file access; the list's rules: `decisions-made.md`.

## Which file refusals stay as they are?
- Submitted-form files (owner, 2026-10-27, #1989): a file with `files.attachment = 1` whose record no view shows (the views deleted or in the recycle bin) stays refused in a public folder too: 403 for a sub-resource fetch, the sign-in screen for a page opening (`get_file.php`, `waf/waf-subresource-sign-in-fetches.md`). No fallback to the folder's permission, because a form upload may be a CV or an identity scan in the default public upload folder. A site that uses such files as page images turns `attachment` off for them. A "missing fallback" finding here is not a finding.
- RSS media uploads (#2483, 2026-10-27, owner-confirmed 2026-10-28; issue #2191): when a public widget or classic view shows a record, the form's `rss_field = 'media'` upload counts as shown too (`pg_record_file_view_grants()`, `get_file.php`), so the feed enclosure and `og:image` of a public record open to anonymous visitors. The fallback: og:image and the RSS enclosure skip any upload an anonymous visitor cannot open, so a feed never links a 403. The rule above (a record no view shows) is unchanged.

## Why are there no folder screens and no classic files list? (owner, 2026-11-01, #4889)
- The File Manager does their work: the folder screens (`add_folder.php`, `edit_folder.php`) and the classic files list (`view_files.php`) are retired and in `clean_up.php`, and its menu panel carries no Create Folder, Upload or Create File links, only the tree. The root folder's name and the site's default page styles are set in the explorer. Not a lost screen or a missing menu entry; what the explorer does not show is listed in `ui/file-manager-replaces-folder-and-file-screens.md`.
