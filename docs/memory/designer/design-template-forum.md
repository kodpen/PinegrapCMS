---
name: design-template-forum
description: Community Forum & Docs template (pinegrap-forum): what it ships, its three forms, the live rules, archives and feeds, replies on the comments block, Turkish caveats, platform limits.
---

## What does it ship?
- 55 pages in `public`, `registration` and `private`: home, forum, board, topic, tag, member, unanswered, docs, docs_section, doc, search, guidelines, FAQ, about, contact, legal, error, sign-in; members' new_topic, doc_new, report, my_topics, my_docs, doc_submission, account, inbox; moderators' pages ([[design-template-forum-moderation]]); 11 e-mails.
- Order 90, `minimal` + `aurora` (no other template's pair), `_pg_design_thumb_forum()`. Look: [[design-template-forum-layout]]. Tests: `tests/design_template_forum_test.php`. General features only: no forum widget kind or table.

## What do the forms hold?
- Forum Topics (`new_topic`): board (category role), kind (Question or Discussion), title, body (rich), tags, attachment (`public` folder), guidelines box. Office: status Open/Solved/Closed/Hidden, pinned, announcement (No/Yes), staff_note.
- Community Docs (`doc_new`): section, title, summary, body, version, tags, attachment, byline, rights. Office: six desk statuses (Published is the fourth), featured, doc_order.
- Forum Reports (`report`): post (`url_param` post), reason, details. Office: status Open/Resolved/Dismissed, staff_note.
- Each office field sits alone with its label, its first answer selected (stored on a new record). `pretty_urls 0`.

## What keeps a record public?
- A topic: `status is not equal to Hidden` on every open list and both topic views; it is live once sent. A guide: `status is equal to Published`. A report: no view outside `private`.
- Open pages are every folder but `private`. Viewer filters and `submitter_only` views sit on `registration` pages and show the member's own (editor off); the inbox alone lets moderators see all.
- Unanswered is `number_of_comments is less than 1`, never `is equal to 0` ([[form-list-filters-fail-closed]]).

## How do the archives and feeds work?
- Board, member and section: `is equal to` the address value, a `filter_title`, the name in a `has_filter_value` block, a static h1. Tag: `tags contains ?tag=`, so no word is printed or titled; the page is noindex, out of search and sitemap.
- Popular tags are written in the design: each must occur in the stored tag lines in the site's language (Turkish `görev` finds `görevler`).
- A page's feed is its first list, so count strips ([[form-list-result-count]]) come first on home, forum, board and docs; the footer feed is `board?rss=true`.
- Value links ([[record-value-page-links]]): board badge `?board=board`, byline `?member=submitter_name`, Report a post `?post=form_item_view`, guide breadcrumb `?section=section`.

## How are replies built?
- On the existing comments block: page `comments` arrays and a `comments_block` region right under the record view in the page tree. Labels: Reply (topic; published at once; no staff e-mail), Feedback (doc; rating; held; staff notified), Message (doc_submission, conversation), Note (staff_report; never mailed). All need sign-in.
- The topic page has two record views of one topic: `topic_view` (with `title_field`, so it names the page) and `topic_aside`, the replies between them.
- A topic's author gets every reply by e-mail; others when they tick the box.
- It moves to the designable comments widget in its own PR ([[designable-comments]]).

## What can the platform not do yet?
- "Topics you follow" on My topics stays empty: the watcher clause in `widgets.php` wants `page_type = 'form item view'`, and a designed topic page is `standard`. Fix: accept a designed page whose form_item_view shows the list's form.
- A reply is signed with the user name until #1490 lands; then update the staff note and the FAQ.
- My replies and replies awaiting approval wait for the comments widget's recent mode.
- Development's Turkish: `Archived` is `arşivlendi` (lower case), `Published` `Yayınlanan`, `Reply` `Yanıtla` (a verb, as in every template).
