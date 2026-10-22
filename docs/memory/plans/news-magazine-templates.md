---
name: news-magazine-templates
description: Plan (owner decisions 2026-10-22, in progress: steps 1, 1b, 2, 3, 4, 5, 6, 7a, 7p, 7q done): News and Magazine design templates with author submissions, after the platform steps they need first
---

## Why?
- No template serves a newsroom or a magazine. A news site needs staff and outside authors writing articles, an editor review before publishing, reader accounts, a staff area and e-mail pages. Building it showed platform gaps a template cannot work around.

## What is decided?
- Two built-in templates: News (portal, modelled on the owner's reference, fictional US content: US cities and sections, canon people, every text through `lang()`), then Magazine (long reads, calm editorial look, the same authoring pages).
- No fake live data: no weather, market, score or prayer-time boxes; sidebars come from the site's own records (most read, columnists, newsletter, breaking strip from flagged records).
- Articles are custom-form records with an office-use-only status; outside authors submit from a front-end page, follow their pieces under My Articles and talk to editors in the record's comments; editors publish in the panel.
- Template CSS ships in the template's `assets` (User Styles), with the #1169 block rules (`pg-marquee`, `pg-shade-bottom`) reused by class name.
- Platform steps come first, one small PR each, in the owner's order.

## What remains?
1. Done (#1192): welcome and password-reset e-mails as designable pages (`account_email` widget, chosen in the member widgets' settings).
1b. Done (#1250): the 7 built-in templates carry a welcome and a reset e-mail page.
2. Done (#1194): record visibility: a record page shows only records its filters allow; its RSS feed and SEO output follow the same filters (drafts leaked).
3. Done (#1212): rich text in designer form textareas (the designer reset the flag; designed pages never loaded the editor).
4. Done (#1197): site search finds designer form records (verify after an index update; fix if needed).
5. Done (#1272): list filter value read from a URL parameter (author, tag, section archives); 5b (link with parameter) stays open.
6. Done (#1241): designed record pages count views; lists order by views, comments or views in recent days (most read).
7a. Done (#1199): templates ship User Styles (block rules read from the editor); the installer preview prints template CSS.
7p. Done (#1275): record-page privacy guards before the news template: the BlogPosting author never falls back to the login username; a list never redirects into a closed record page.
7q. Done (#1287): the 7 built-in templates printed `submitter` (the login username) as the visible author of a blog post; a `submitter_name` field and those bylines.
7r. Follow-up (not started): a member whose comment form gives no name choice is stored and shown under the login username (`add_comment.php`); every site's comments, so a product decision first.
7s. Designed lists fail closed when a filter's field is gone, its operator is unknown or it is past the fifty-row cap (a renamed Status field listed drafts).
7. News template (`pinegrap-news`), thumbnail.
8. Magazine template (`pinegrap-magazine`).

## Open questions?
- None for the owner (2026-10-22: decide from the code and past decisions).

## Where to start?
- The first step not marked done above; claims and PRs carry `[plan:news-magazine-templates]`.
