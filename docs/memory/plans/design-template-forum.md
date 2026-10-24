---
name: design-template-forum
description: Plan (approved 2026-10-23, not started): Community Forum & Docs design template built only from general features; list, link and form steps first, then the template; waits for designable-comments 1-18
---

## What did the owner decide (2026-10-23)?
- A forum-first design template with documentation sharing, full account pages and staff pages with tools to follow the forum.
- No forum-only widget kind or table: every missing piece is a general feature of an existing widget (comments, form lists, links, forms), usable by other designs.
- Replies are comments drawn by the comments widget ([[designable-comments]]), shown under the member's display name; nested replies and quotes come from `parent_id`.
- Not built: votes or likes, public member profiles, accepted answers beyond a featured comment shown first.

## Which general steps come first? (one PR each)
1. Form lists: 1a sort by last activity (`newest_activity_date_and_time`); 1b `^^__result_count^^` with `has_records` / `no_records` (capped at max_results like the classic list); 1c no code (owner, 2026-10-23): unanswered lists filter "number of comments is less than 1", since "is equal to 0" misses records without a counter row in the classic list too.
2. Links with parameters (5b): an href to another page plus query parameters valued from row tokens (board badge to `?board=`, byline to `?member=`, Report to `?post=`).
3. A designed form control filled from a named address parameter (Report form's post field).
4. Recent comments mode of the comments widget: newest comments across the records a detail page shows, published for everyone, awaiting approval for staff, the viewer's own; record title and link tokens. After designable-comments 18.

## What does the template ship? (step 5)
- File `includes/design_templates/pinegrap-forum.php`, order 90, own `pf-` User Styles, thumbnail `_pg_design_thumb_forum()`, fictional software community, every text through `lang()`.
- Forms: Forum Topics (board, kind Question or Discussion, title, body rich, tags, attachment; office: status Open / Solved / Closed / Hidden, pinned, announcement, staff note), Docs (section, title, summary, body rich, version, tags, attachment; office: the desk statuses, featured), Reports (post, reason, details; office: status, note), contact and staff directory.
- Public: home, forum index, board (`?board=`), topic (replies, watchers, attachments), tag and member archives, docs hub, docs section, doc page (feedback), search, guidelines, FAQ, about, contact, legal, error, sign pages.
- Members (registration): standard account pages, inbox, new topic, My topics (started and followed), My replies, contribute a guide, My guides, guide desk thread, report.
- Staff (private): dashboard with counts, replies awaiting approval, open reports, unanswered, newest, most viewed this week, hidden topics, guides awaiting review; all topics; docs desk; reports desk and report thread; staff directory and entry; shared staff bar.
- E-mails: replies to watchers, new topic and new report to staff, guide received and desk messages, contact, account welcome and password reset.
- Tests `tests/design_template_forum_test.php` on the news and magazine pattern (placeholders, live rules, private views, office fields, CSS, e-mails, CAPTCHA, landmarks); memory files `designer/design-template-forum*.md`; one `[NEW]` changelog line.

## Where to start?
- Built so far (branches, PRs pending): 1a `feature/forum-1a-activity-sort`, 2 `feature/forum-2-page-links`; 1b stacks on 1a.
- Step 3 starts after #1347 merged (same `custom_form.php` and panel lines). Step 4 and the template wait for designable-comments 18.
