---
name: user-docs-pages
description: User documentation on pinegrap.com: the 31 pages by address, the PR each is written up to (#500 plus the 2026.11.0 round), how a mark moves, the site pages, where coverage is listed
---

## Where is the user documentation, and who can reach it?
- On pinegrap.com, English at the root (`/erp`), other languages under a prefix (`/tr/erp`); never in the repository (`owner-rules.md`). The panel's help buttons open these pages (`ui/panel-help-links.md`).
- Cloud sessions cannot open pinegrap.com. Documentation work, its change list included, belongs to a session on the owner's machine (`user-docs-writing.md`); this file tells the others which pages exist and how far they are written.
- Read on 2026-11-10 from the live pages and `sitemap.xml`: 28 documentation pages of about 240,000 words (31 pages since 2026-11-23), text and tables only, and 5 site pages. The site runs release 2026.4.10.

## Which pages are there, and up to which PR is each written?
- A page's mark is the highest merged PR whose user-visible changes the page holds, with nothing older left out.
- #500 (release 2026.4.10, merged 2026-10-17 02:37 +03), all 28: `/introduction`, `/installation`, `/system`, `/site-settings`, `/folders`, `/files`, `/pages`, `/pages-cont`, `/calendars`, `/forms`, `/contacts`, `/users`, `/campaigns`, `/ads`, `/chat`, `/visitors`, `/seo`, `/design`, `/visual-page-editor`, `/multilingual`, `/commerce`, `/commerce-cont`, `/erp`, `/erp-cont`, `/workspace`, `/workspace-cont`, `/api`, `/security`.
- How it is known: the rounds of 2026-10-13 to 2026-10-17 went through the merged PRs in order, and the pages hold #484, #486, #487, #489, #495, #499 and #500.
- Three pages were added in the 2026.11.0 round (2026-11-23) and hold only 2026.11.0 subjects: `/artifact-canvas` (under Files in the Docs menu), `/visual-page-editor-cont` and `/templates` (under Visual Page Editor).
- Everything merged after #500 belongs to the open version 2026.11.0 (`release/release-status.md`); its round is plan `plans/user-docs-2026-11.md`. Its `[NEW]` features are on the pages since 2026-11-23 (noted "Available from 2026.11.0"); a page's mark stays #500 until its smaller changes are checked.
- Application Access was redrawn in 2026.11.0 (#10745 to #11203): `/api`'s section on it is written from `api/application-access-overview.md`, and its mark stays #500 until then.
- What each page covers and the memory it is written from: `user-docs-coverage-content.md`, `user-docs-coverage-modules.md`. A page's sections that a screen links to are its rows in `pinegrap/get_help_url.php`; the ones a page lacks: `user-docs-help-anchors.md`.

## How does a mark move?
- The session that publishes a page moves its address to the line of its new mark here, in the same round. One line per mark, no dated history.

## How is the change list since a mark made?
- From the merges, in a full clone (the owner's machine has one; a cloud clone is shallow): `git log --first-parent --since='2026-10-17 02:40' --format=%s origin/development -- pinegrap/includes/erp 'pinegrap/*erp*'` gave 340 titles for the ERP on 2026-11-10, each with its PR number. The number finds the topic file (`grep -rl '#<n>' docs/memory`), which is read for what a person sees.
- Nothing in memory alone separates what is new since a mark: the `[NEW]` lines of `pinegrap/changelog.txt` name only major features, and a number in a topic file may be an issue or a later fix.
- A page is written from the feature's overview file where one exists (`memory-writing.md`), else from its topic files.

## Which pages are not documentation?
- `/` (home, with a Recent releases box), `/features`, `/download`, `/contact`.
- `/changelog`: the release notes, one article per version, 2026.4.6 to 2026.4.10 (`user-docs-writing.md`).
