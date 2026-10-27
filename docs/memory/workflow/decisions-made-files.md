---
name: decisions-made-files
description: Decisions list, files part: a submitted-form file whose record no view shows stays refused in a public folder too (no fallback to the folder's permission).
---

## Where do these come from?
- Owner answers on file access; the list's rules: `decisions-made.md`.

## Which file refusals stay as they are?
- Submitted-form files (owner, 2026-10-27, #1989): a file with `files.attachment = 1` whose record no view shows (the views deleted or in the recycle bin) stays refused in a public folder too: 403 for a sub-resource fetch, the sign-in screen for a page opening (`get_file.php`, `waf/waf-subresource-sign-in-fetches.md`). No fallback to the folder's permission, because a form upload may be a CV or an identity scan in the default public upload folder. A site that uses such files as page images turns `attachment` off for them. A "missing fallback" finding here is not a finding.
