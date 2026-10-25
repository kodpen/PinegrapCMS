---
name: design-template-forum-decisions
description: Owner decisions behind the forum template (2026-10-23): general features only, replies as comments, what is not built, and the general features it was built on, with their PRs
---

## What did the owner decide (2026-10-23)?
- A forum-first design template with documentation sharing, full account pages and staff pages with tools to follow the forum ([[design-template-forum]]).
- No forum-only widget kind or table: every missing piece is a general feature of an existing widget (comments, form lists, links, forms), usable by other designs.
- Replies are comments drawn by the Comments widget ([[design-template-forum-replies]]), shown under the member's display name; nested replies and quotes come from `parent_id` ([[comment-replies]]).
- Not built: votes or likes, public member profiles, accepted answers beyond a featured comment shown first.

## Which general features was it built on?
- Form lists: sort by last activity (`newest_activity_date_and_time`, #1473), `^^__result_count^^` with `has_records` / `no_records` (#1489, [[form-list-result-count]]); unanswered lists filter "number of comments is less than 1" (no code).
- Links with parameters valued from row tokens (#1474, [[record-value-page-links]]); a designed form control filled from a named address parameter (#1501).
- The watcher filter on a designed record page (#1545, [[form-list-viewer-filter]]).
- The Comments widget: threads (#1461-#1620, plan `designable-comments`), its recent mode (#1619, [[comments-widget-recent]]), the author index (#1638), record filters for a list (#1679). The template moved onto it in 1.1.0 (#1658); the board list came in 1.2.0 (#1679).
- Two comments widget fixes found while moving it: the hidden Replying to box (#1668, [[comments-widget-replies]]) and watcher e-mails to the writer (#1668, [[comments-watcher-mail]]).
