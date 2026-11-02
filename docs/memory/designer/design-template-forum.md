---
name: design-template-forum
description: Community Forum & Docs template (pinegrap-forum): what it ships, its three forms, the live rules, archives and feeds, replies on the Comments widget and their names, Turkish words.
---

## What does it ship?
- 58 pages: home, forum, board, topic, tag, member, unanswered, docs, docs_section, doc, blog, blog_post ([[design-template-forum-blog]]), search, guidelines, FAQ, about, contact, legal, error, sign-in; members' new_topic, doc_new, report, my_topics, my_docs, doc_submission, account, inbox; moderators' pages; 11 e-mails.
- Version 1.3.4 (1.2.x: no blog, block on 3 private pages; 1.1.0: no board replies list; 1.0.1: replies on the block). Order 90, `minimal` + `aurora`, `_pg_design_thumb_forum()`. Look: [[design-template-forum-layout]]; staff: [[design-template-forum-moderation]]. Tests: `tests/design_template_forum_test.php`.

## What do the forms hold?
- Forum Topics (`new_topic`): board (category role), kind (Question or Discussion), title, body (rich), tags, attachment (`public` folder), guidelines box. Office: status Open/Solved/Closed/Hidden, pinned, announcement (No/Yes), staff_note.
- Community Docs (`doc_new`): section, title, summary, body, version, tags, attachment, byline (JSON-LD author, #4873), rights. Office: the magazine's six desk statuses (Live on the site is the fourth; Withdrawn takes a guide out), featured, doc_order.
- Forum Reports (`report`): post (`url_param` post), reason, details. Office: status Open/Resolved/Dismissed, staff_note (labelled Moderator note).
- Office fields alone, first answer selected; `pretty_urls 0`.

## What keeps a record public?
- A topic: `status is not equal to Hidden` on every open list and both topic views; live once sent. A guide: `status is equal to Live on the site`. A report: no view outside `private`.
- Viewer filters and `submitter_only` views sit on `registration` pages (the member's own, editor off). My topics: started (submitter) and Topics you follow (watcher alone, detail `{{tab:topic}}`, live rule, newest activity, 20 without paging; [[form-list-viewer-filter]]), then Your replies (`mine`).
- Unanswered: `number_of_comments is less than 1`, not `is equal to 0` ([[form-list-filters-fail-closed]]).

## How do the archives and feeds work?
- Board, member and section: `is equal to` the address value, a `filter_title`, the name in a `has_filter_value` block. Tag: `tags contains ?tag=`; noindex, out of search/sitemap.
- Popular tags are written in the design and must occur in the sample tag lines in the site's language (`notebooks`, not `tasks`).
- A page's feed is its first list, so count strips ([[form-list-result-count]]) come first; the footer feed is `board?rss=true`.
- Value links ([[record-value-page-links]]): board badge `?board=board`, byline `?member=submitter_name`, Report a post `?post=form_item_view`, guide breadcrumb `?section=section`.

## How are replies built?
- Topic and guide pages: the Comments widget under the record view (topic: `topic_view`, the replies, `topic_aside`); desk, inbox, report: [[design-template-forum-private-threads]]; lists, e-mails: [[design-template-forum-replies]]. Labels: `reply` (topic; published at once; no staff e-mail), Feedback (doc; rating; held), Message, Note. All need sign-in.
- `reply` is a lower-case key of its own (`yanıt`): the shared `Reply` is a verb (`Yanıtla`), only on the Reply link.
- The author gets every reply by e-mail, others after Follow this topic or the form's box. Signed with the profile's name, else Member; staff type theirs, prefilled ([[designed-page-comment-names]]).

## Which Turkish words does it keep apart?
- A report is a `şikâyet`; `bildirim` is a notification, `geri bildirim` feedback.
- No `haber`; a view is `görüntülenen`, not `açılan` (started); activity is `hareket`; a guide is `Rehber` (#3640); the team list is no bare `rehber`. `test_forum_turkish` checks these.
- Sign page slugs (#3640, read at install only): `parolami-unuttum`, `parola-belirle`, `parola-degistir`; installed sites keep theirs ([[page-rename-redirect]]).
