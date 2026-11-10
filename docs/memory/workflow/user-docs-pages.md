---
name: user-docs-pages
description: User documentation on pinegrap.com: the 28 documentation pages by address, the PR each is written up to (all #500, release 2026.4.10), how a mark moves, the site pages, where coverage is listed
---

## Where is the user documentation, and who can reach it?
- On pinegrap.com, English at the root (`/erp`), other languages under a prefix (`/tr/erp`); never in the repository (`owner-rules.md`). The panel's help buttons open these pages (`ui/panel-help-links.md`).
- Cloud sessions cannot open pinegrap.com: only a session on the owner's machine reads or writes the pages (`user-docs-writing.md`). This file tells the others which pages exist and how far they are written.
- Read on 2026-11-10 from the live pages and `sitemap.xml`: 28 documentation pages of about 240,000 words, text only, and 5 site pages. The site runs release 2026.4.10.

## Which pages are there, and up to which PR is each written?
- A page's mark is the highest merged PR whose user-visible changes the page holds, with nothing older left out.
- #500 (release 2026.4.10, 2026-10-17), all 28: `/introduction`, `/installation`, `/system`, `/site-settings`, `/folders`, `/files`, `/pages`, `/pages-cont`, `/calendars`, `/forms`, `/contacts`, `/users`, `/campaigns`, `/ads`, `/chat`, `/visitors`, `/seo`, `/design`, `/visual-page-editor`, `/multilingual`, `/commerce`, `/commerce-cont`, `/erp`, `/erp-cont`, `/workspace`, `/workspace-cont`, `/api`, `/security`.
- How it is known: the documentation rounds of 2026-10-13 to 2026-10-17 went through the merged PRs in order (#350 to #469 in one round), and the pages hold #484, #486, #487, #489, #495, #499 and #500.
- Last change by the sitemap (UTC): 15 pages on 2026-10-16, `/commerce-cont` and `/multilingual` on 2026-10-13, the other 11 on 2026-10-12.
- Nothing merged after #500 is on any page: no section on passkeys, Flows, workflow tables, customer returns, Trendyol, SMS, the new dashboard or another 2026.11.0 feature.
- What each page covers and the memory it is written from: `user-docs-coverage-content.md`, `user-docs-coverage-modules.md`. Sections the panel links to and a page lacks: `user-docs-help-anchors.md`.

## How does a mark move?
- The session that publishes a page moves its address to the line of its new mark here, in the same round. One line per mark, no dated history.
- What changed since a mark: the open version's `[NEW]` lines in `pinegrap/changelog.txt` and the topic files that name a later PR (`grep -rlE '#([5-9][0-9]{2}|[0-9]{4,})\b' docs/memory/erp`). A cloud clone is shallow, so `git log --since` misses older work.
- A page is written from the feature's overview file where one exists (`memory-writing.md`), else from its topic files.

## Which pages are not documentation?
- `/` (home, with a Recent releases box), `/features`, `/download`, `/contact`.
- `/changelog`: the release notes, one article per version, 2026.4.6 to 2026.4.10 (`user-docs-writing.md`).
