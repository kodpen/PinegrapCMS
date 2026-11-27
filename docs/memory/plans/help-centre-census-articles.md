---
name: help-centre-census-articles
description: Help centre census: what collections give a knowledge base and lack: categories in order, a search of the articles alone, "Was this helpful?", related articles, an article offered while typing
---

## What holds an article today?
- A collection: a designer form with the collection flag, its records `forms` / `form_data` rows with their own page (`designer/collections-decisions.md`). An "Articles" and a "Categories" collection need no new code.
- Records are in the site search: `update_search_index.php:832-855` reads each form item view page, and `:1260-1276` writes its records into `search_items` with that page's folder.

## Categories in order?
- Have: a number field orders a list, empty values last (`pg_coll_number_order_sql()`, `includes/fn/collections.php:924`), so an "Order" field orders categories and articles.
- Have: a reference field may point to its own form (`designer/collection-fields.md`), so a category names its parent; a list filtered "Parent is equal to" Shown record (`pg_coll_shown_record_code()`, `collections.php:957`) lists its children.
- Lack: one widget drawing the tree (categories, sub-categories and their articles) and breadcrumbs from the parent chain; each level is a list of its own today.

## A search of the articles alone?
- Lack. The Search Results widget searches every `search_items` row a visitor may view; its only narrowing is the visitor's folders (`includes/fn/widgets.php:6797-6806`), and its settings have no folder, page or collection (`_render_system_widget_search_results()`, `widgets.php:7171`).
- Near: a form list's own search box (`search_enabled`, `search_fields`, `widgets.php:2017-2018`, `:2168-2186`) searches chosen fields of one form, by field match, not the full-text index, so with no ranking.
- Needed: a "Search only in" setting of the Search Results widget (the article view page's folder), no schema.

## "Was this helpful?"
- Lack. Satisfaction surveys are sent after a touchpoint (`pg_feedback_event_record()`, `includes/fn/feedback_sends.php:68-77`); "surveys inside site pages" were left out and need a new yes (`platform/feedback-loop.md`).
- `feedback_responses` (`includes/migrations/2026.11.0.php:8769`) is a sent survey's answer with recipient and token, not an anonymous click.
- Comments carry stars (`comments_rating`, `includes/fn/comments.php:141-148`): a review, not a yes or no.
- Needed: Yes and No buttons on the article's page and a place for the counts (a table, asked: `help-centre-census-proposal.md`).

## Related articles?
- Have: a list filtered "Category is equal to" the shown record's category, with `reference_code` "is not equal to" Shown record, lists the other articles of the category (`designer/collection-lists.md`).
- Have: a reference field keeps several records joined by `, ` (`designer/collection-references.md`), so a hand-picked "Related articles" field works.
- Lack: relatedness found by shared words.

## An article offered while a request is typed?
- Lack. No public endpoint searches `search_items` for a form; the Workspace's address suggestions suggest addresses only (`workspace/mail-suggest.md`).
- Needed: a read endpoint over `search_items` narrowed like "Search only in", called by a request form's subject field as the visitor types; no schema.
- The visitor AI assistant (`plans/visitor-ai-assistant.md`) could answer from the same articles; not part of this plan.
