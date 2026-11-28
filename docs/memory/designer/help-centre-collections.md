---
name: help-centre-collections
description: Knowledge base (help centre): articles stay a collection; the ready "Help centre" set (Help Categories, Help Articles) with tab keys, field keys, pages and widgets for templates
---

## What holds an article?
- Owner (2026-11-27, agent channel poll 246, `workflow/decisions-made-product-117.md`): a knowledge base. Architect (2026-11-27): articles stay a collection (`collections-decisions.md`); no article table. The requests side: `workspace/support-requests.md`.
- A collection's records are in the site search with their record page's folder, ordered by a number field (`collection-lists.md`), related by a list filtered on "Shown record", and a reference field may point to its own form.

## Which parts did the plan add?
- "Search only in" of the Search Results widget and the article suggestions a form field offers while the visitor types: `search-results-folder-and-article-suggest.md` (HC2).
- "Was this helpful?" Yes and No on a record's page, `kb_votes`, the two counts on the record list: `kb-votes-helpful.md` (HC8). `kb_votes` holds no personal data, so it has no export or erasure.
- A category tree and breadcrumbs: list sources "Records that point at the record" and "Path to the record", `record-tree-lists.md` (HC9).

## What is the ready "Help centre" set?
- Two collection forms, each on a staff page and with a record page, in one folder of their own (say `help`) so "Search only in" can name it. A template that ships a help centre uses these tab keys and field keys, so lists, filters and the docs read the same on every site.
- Help Categories, tab `help_category_new`, record page `help_category`: `name` (text, Title role), `slug` (slug), `description` (text area), `parent` (reference to `{{tab:help_category_new}}`, empty for a top category), `order` (number).
- Help Articles, tab `help_article_new`, record page `help_article`: `title` (text, Title role), `slug` (slug), `summary` (text area, the list's line), `body` (rich text), `category` (reference to `{{tab:help_category_new}}`), `related` (reference to `{{tab:help_article_new}}`, several), `order` (number).
- Write order and sample references by label: `collection-template-refs.md` (categories first, a self reference needs nothing).

## Which pages and widgets make it?
- Help home: a list of Help Categories whose `parent` is empty, `order` ascending; in each row a "Records that point at the record" list on `parent` (sub-categories) and one on `category` (articles), nested as `record-tree-lists.md` says. A Search box sending to the search page.
- Search page `help_search`: Search Results with "Search only in" the help folder.
- Category page: "Path to the record" on `parent` as breadcrumbs, then the name; sub-categories and articles as on the home.
- Article page: "Path to the record" on `category`, the body, "Was this helpful?" Yes and No buttons, "Related articles": the `related` titles by a `__ref` binding (`collection-references.md`), or a "Records that point at the record" list on `related` for the articles naming this one.
- Contact form: "Offer articles from" the help folder on its subject field, and "Support Request" on with a support box (`workspace/support-form-request.md`).

## What is not in it?
- No design template ships the set yet: design template plans are paused (`workflow/coordinator-no-plans.md`); the first template line the owner opens for it builds it as named here.
- The tree lists do not order by `order` (record order or by title, `record-tree-lists.md`); a ranked "related by shared words" was not built.
- The visitor AI assistant answering from the articles is its own plan (`plans/visitor-ai-assistant.md`).
