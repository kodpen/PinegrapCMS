---
name: design-template-forum-leftovers
description: Forum template plan (2026-10-23; 1a 1c #1473, 1b #1489, 2 #1474, 3 #1501 done, 5 the template built on feature/forum-5-template): step 4, the comments widget move and the watcher fix left
---

The plan was claimed as `[plan:design-template-forum]` (#1431, steps 1-5); the template's own memory is [[design-template-forum]].

## What did the owner decide (2026-10-23)?
- A forum-first design template with documentation sharing, full account pages and staff pages with tools to follow the forum.
- No forum-only widget kind or table: every missing piece is a general feature of an existing widget (comments, form lists, links, forms), usable by other designs.
- Replies are comments drawn by the comments widget ([[designable-comments]]), shown under the member's display name; nested replies and quotes come from `parent_id`.
- Not built: votes or likes, public member profiles, accepted answers beyond a featured comment shown first.
- 2026-10-24: the template is built now on the existing comments block (`comments_block` region and page `comments` arrays); it moves to the comments widget in its own PR.

## Which general steps are done, one PR each?
1. Form lists: 1a sort by last activity (`newest_activity_date_and_time`, #1473); 1b `^^__result_count^^` with `has_records` / `no_records` (#1489); 1c no code: unanswered lists filter "number of comments is less than 1" (#1473).
2. Links with parameters: an href to another page with query parameters valued from row tokens (#1474, [[record-value-page-links]]).
3. A designed form control filled from a named address parameter (#1501): the Report form's post field.
5. The template (branch `feature/forum-5-template`): what it ships is in [[design-template-forum]], [[design-template-forum-moderation]] and [[design-template-forum-layout]].

## What is left?
4. Recent comments mode of the comments widget: newest comments across the records a detail page shows, published for everyone, awaiting approval for staff, the viewer's own; record title and link tokens. After designable-comments 18.
- Then the template moves to the comments widget in its own PR, and gains My replies (members) and replies awaiting approval (dashboard).
- A small platform PR: the form list's watcher filter accepts a designed page whose form_item_view shows the list's form (today it wants `page_type = 'form item view'`, so the template ships no "Topics you follow" list). Then a test, the list on My topics (`viewer_filter_watcher`, the "How following works" card saying it appears there), and the memory notes.
- When the display name for comments lands (#1490), the staff note's sentence on user names and the FAQ change with it.

## Where to start?
- Step 4 after designable-comments E; the watcher fix any time, from `widgets.php` (the `viewer_filter_watcher` clause) and `add_or_remove_watcher.php`, which already accepts designed pages.
