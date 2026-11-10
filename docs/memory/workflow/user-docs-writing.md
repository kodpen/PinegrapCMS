---
name: user-docs-writing
description: How a pinegrap.com documentation page is written and published: English only, the page's structure (sections, anchors, tables, notes), the editor route, Publish is live, release notes on /changelog
---

## Who writes a page, and in which language?
- A session on the owner's machine, through his signed-in browser: the documentation is the pages of one design in the site's Visual Page Editor. The design and page ids and the panel addresses are in that machine's notes, never here (`owner-rules.md`).
- Owner (2026-10-13): English only. The other languages come from the site's Translations screen, which he runs in bulk; a session never enters a translation and never edits a `/tr/…` page, the release notes included (2026-10-16).
- Say which pages will change before publishing. A placeholder such as `^^name^^` never goes into page content (`owner-rules.md`).

## How is a page built?
- `article#docs-<page>`: a header with the `h1` and a lead paragraph, an "On this page" list, then one `section#<anchor>.mb-12` per subject. The section's id is the help anchor (`ui/panel-help-links.md`); a subsection is `h3#<anchor>-<part>`.
- Classes: `h2.text-3xl.mb-5`, `h3.text-xl.mt-9.mb-3`, `h4.text-lg.mt-7.mb-2`. A table is `div.table-responsive.my-6 > table.table.table-sm.md:table-stacked.align-middle`, every `td` with `data-cell="<column heading>"`. A note is `div.alert.theme-info.my-6[role=note] > p.mb-0`, a warning `theme-warning`.
- A screen's section opens with an Access line naming the roles. Labels are written as on screen, in bold. The pages hold text and tables only, no pictures.
- A section on a feature the released version lacks says which version brings it.
- The resource tables of `/api` have four columns: Endpoint, Permission, What it does, Dry run (`Yes` or `—`).

## How is it edited and published?
- Read the page's tree first. `StyleDesigner.aiBridge()` gives `treeJSON()` (a JSON string), `apply(tree)`, which puts a changed tree in place as one undo step, and `post('import_fragment', {html})`, which turns HTML into nodes without saving (`designer/html-import-and-paste.md`).
- Publish (`#sd-ajax-save`) saves, and a live page is public at once. It can refuse while shared components load: wait and press again. Leave the editor afterwards, so the page lock is released.
- In a node, `props._attrs` is a list of `{name, value}`: an object there makes `apply()` fail after the tree is already replaced. A `td` or `li` prints its own text escaped, so inline HTML (`<code>`, `<strong>`) goes into a content span child; one span per sentence keeps it one translation string. After publishing, search the live page for `&lt;code`.

## How is a release note published?
- `/changelog` gets `article#v<version>` (dots as dashes) on top, with a heading per area and its count; "Latest release" moves to it, the sidebar and the filter counts follow, and the home page's Recent releases box gets the version and its date.
