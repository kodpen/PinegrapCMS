---
name: design-template-forum
description: Community Forum & Docs template (pinegrap-forum): what it ships, its three forms, the live rules, archives and feeds, replies on the comments block, Turkish words, platform limits.
---

## What does it ship?
- 55 pages: home, forum, board, topic, tag, member, unanswered, docs, docs_section, doc, search, guidelines, FAQ, about, contact, legal, error, sign-in; members' new_topic, doc_new, report, my_topics, my_docs, doc_submission, account, inbox; moderators' pages ([[design-template-forum-moderation]]); 11 e-mails.
- Version 1.0.1 (1.0.0 had no followed topics). Order 90, `minimal` + `aurora` (a pair of its own), `_pg_design_thumb_forum()`. Look: [[design-template-forum-layout]]. Tests: `tests/design_template_forum_test.php`. General features only.

## What do the forms hold?
- Forum Topics (`new_topic`): board (category role), kind (Question or Discussion), title, body (rich), tags, attachment (`public` folder), guidelines box. Office: status Open/Solved/Closed/Hidden, pinned, announcement (No/Yes), staff_note.
- Community Docs (`doc_new`): section, title, summary, body, version, tags, attachment, byline, rights. Office: the magazine's six desk statuses and words (Live on the site is the fourth; Withdrawn takes a guide out), featured, doc_order.
- Forum Reports (`report`): post (`url_param` post), reason, details. Office: status Open/Resolved/Dismissed, staff_note (labelled Moderator note).
- Each office field sits alone, its first answer selected. `pretty_urls 0`.

## What keeps a record public?
- A topic: `status is not equal to Hidden` on every open list and both topic views; live once sent. A guide: `status is equal to Live on the site`. A report: no view outside `private`.
- Viewer filters and `submitter_only` views sit on `registration` pages and show the member's own (editor off). My topics: started (submitter) and Topics you follow (watcher alone, detail `{{tab:topic}}`, live rule, newest activity, 20 without paging; [[form-list-viewer-filter]]).
- Unanswered is `number_of_comments is less than 1`, never `is equal to 0` ([[form-list-filters-fail-closed]]).

## How do the archives and feeds work?
- Board, member and section: `is equal to` the address value, a `filter_title`, the name in a `has_filter_value` block. Tag: `tags contains ?tag=`; noindex, out of search and sitemap.
- Popular tags are written in the design and must occur in the sample tag lines in the site's language (a plural key such as `notebooks`, not the workspace's singular `tasks`).
- A page's feed is its first list, so count strips ([[form-list-result-count]]) come first; the footer feed is `board?rss=true`.
- Value links ([[record-value-page-links]]): board badge `?board=board`, byline `?member=submitter_name`, Report a post `?post=form_item_view`, guide breadcrumb `?section=section`.

## How are replies built?
- On the existing comments block: page `comments` arrays and a `comments_block` region right under the record view. Labels: `reply` (topic; published at once; no staff e-mail), Feedback (doc; rating; held), Message (doc_submission, conversation), Note (staff_report). All need sign-in.
- `reply` is a lower-case key of its own (`yanıt`): the shared `Reply` is the workspace's verb (`Yanıtla`) and printed "Yanıtla ekle".
- The topic page: `topic_view` (with `title_field`), the replies, `topic_aside`.
- A topic's author gets every reply by e-mail, others when they tick the box. Moves to the comments widget later ([[designable-comments]]).

## What can the platform not do yet?
- A reply is signed with the user name until #1490 lands; then update the staff note and the FAQ.
- My replies and replies awaiting approval wait for the comments widget.

## Which Turkish words does it keep apart?
- A report is a `şikâyet`; `bildirim` is the panel's notification, `geri bildirim` feedback.
- No `haber`; a view is `görüntülenen`, not `açılan` (a started topic); activity is `hareket`; the team list is no bare `rehber` (a guide). `test_forum_turkish` checks these.
