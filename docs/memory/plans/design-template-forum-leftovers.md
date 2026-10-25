---
name: design-template-forum-leftovers
description: Forum template plan (1-3 #1473 #1489 #1474 #1501, 5 #1526, 4A-4B #1619, index #1638, D #1658, widget fixes #1545 #1668 done): record filters (4C) left
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
5. The template (#1526): what it ships is in [[design-template-forum]], [[design-template-forum-moderation]] and [[design-template-forum-layout]].
- The watcher fix (#1545): the form list's watcher filter accepts a designed page whose form_item_view shows the list's form ([[form-list-viewer-filter]]); My topics lists Topics you follow, the "How following works" card says they appear there; the staff note and the FAQ say a reply is signed with the profile's name (#1490); template 1.0.1.

## What is left?
- 4A-4B, done (#1619): the comments widget's recent mode ([[comments-widget-recent]]): newest comments of up to five chosen pages, `published` for everyone, `mine` for the member, `pending` for editors; thread title and link tokens; canvas states and samples.
- D, done (#1658): template 1.1.0 on the comments widget: topic replies and guide feedback as threads, Latest replies (home), Your replies (My topics), Replies awaiting approval (moderator dashboard), the comment e-mail card ([[design-template-forum-replies]]). Sites on 1.0.x keep their pages.
- 4C. Record filters for a list (category pages: latest replies of one board): stored under their own key `recent_filters`, the form list's filter editor shown whenever that key is not empty, one record source only. Then the board pages gain their Latest replies list.
- Done (#1638, schema 11.0.36, owner-approved): index `idx_author (created_user_id, created_timestamp)` on `comments` for slow `mine` reads ([[comments-widget-recent-reads]]).

- Two comments widget fixes found while building D, done (#1668): a hidden Replying to box also gets `d-none` ([[comments-widget-replies]]); watcher e-mails leave out the comment's own writer ([[comments-watcher-mail]]).

## Where to start?
- 4C, its own draft PR.
