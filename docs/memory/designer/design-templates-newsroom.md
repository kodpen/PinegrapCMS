---
name: design-templates-newsroom
description: News Portal and Magazine templates as one effort: the owner's decisions (2026-10-22), the platform steps built for them, the follow-ups still open (5b, 7r, 7t, search, caching).
---

## What did the owner decide?
- Two built-in templates on one authoring model: News Portal (#1324, [[design-template-news]]) and the Long-Read Magazine (#1358, [[design-template-magazine]]). Content is fictional and US-based; every text goes through `lang()`.
- No fake live data: no weather, market, score or prayer-time boxes. Sidebars come from the site's own records (most read, columnists, newsletter, flagged records).
- Pieces are custom-form records with office-use-only status and publish time; outside authors send them from a front-end page, follow them under My Articles / My Stories and talk to editors in the record's comments; editors publish in the panel.
- Template CSS ships in the template's `assets` (User Styles); text never sits on a photo without the template's own contrast-safe rule.
- Platform gaps were fixed first, one small PR each, and decided from the code and earlier decisions without asking the owner.

## Which platform steps were built for them?
- Designable welcome and reset e-mails (#1192, #1250); record views and their feeds follow their filters (#1194); rich text in designer forms (#1212); site search finds designer records (#1197); URL-parameter list filters (#1272, [[form-list-url-filters]]); view counts and most read (#1241, [[record-view-counts]]); template User Styles (#1199); record-page privacy guards (#1275, #1287); lists fail closed (#1299, [[form-list-filters-fail-closed]]); designed-form uploads open for readers (#1320, [[designer-form-uploads-for-readers]]).

## Which follow-ups are still open?
- 5b. A link that carries a parameter (a byline linking to the writer's or contributor's archive): today names link from the static directory and the author box.
- 7r. Comment names: a member whose comment form offers no name field is stored and shown under the login username (`add_comment.php`), on every site. Editors' inbox replies are signed the same way. The templates hold letters and comments for review and tell editors to fix the name. A product decision first.
- 7t. Approving a held comment on a designed record page mails its watchers, never the piece's author (`edit_comment.php` lacks `add_comment.php`'s designed-page mapping).
- Search noise: Update Search Index takes each page's whole text, so a headline shown in Most read or a "more" list also finds every page that listed it.
- Feeds date and order items by `submitted_timestamp`, not the publish time.
- `get_file.php` sends an attachment opened through edit access or Manage Forms with `Cache-Control: public`: behind a shared cache an editor's view of a draft photo could be kept for visitors. Designed-page grants already send `private`.
