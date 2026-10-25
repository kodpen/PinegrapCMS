---
name: design-template-forum-replies
description: Forum template on the Comments widget (1.1.0, board list 1.2.0): seven comments widgets, why explicit trees, pages that keep the block, e-mails, page settings, older sites, quirks, checks
---

## Which comments widgets does it ship?
- Threads (`$reply_thread()`), under their record view: `topic_replies` (topic: threaded, max_depth 2, oldest, 20 a page) and `doc_feedback` (doc: max_depth 1, newest, 10, stars). Featured `first`: a featured reply is the best answer, shown first with its badge. `.pf-reply` cards with Reply, Share and Edit links; the form; Follow this topic; Stop or Allow new replies. The quote is the post a reply answers where the thread stops going deeper (`comment_has_quote`): the contract has no quote action.
- Lists (`mode 'recent'`, `$recent_list()`), reading `{{tab:topic}}` and `{{tab:doc}}`: `home_latest_replies` (home side card, published, 5), `my_replies` (My topics, mine, newest, 10), `staff_replies_waiting` (dashboard `#replies-waiting`, pending, oldest, 10, Publish or edit opens edit_comment.php).
- 1.2.0 (#1679): `board_latest_replies` (board page under its topics, published, 5): `{{tab:topic}}` alone, `recent_filters` = the board topics' `?board=` filter ([[comments-widget-recent]]); no `?board=`, every board's.
- `email_comment` (`$mail_comment_tree`: flat, featured none) on email_reply and email_doc_message.

## Why explicit trees, not starters?
- A `'tree' => 'starter'` widget keeps its page out of `pg_design_template_preview_pages()`: home and the 11 e-mails would leave the installer preview. Explicit trees carry the `pf-` look and need no starter at install.
- They do not follow later changes of `pg_comments_default_tree()`: `tests/design_template_forum_test.php` pins bindings, places and flags (each one its mode sets), so a platform rename fails there.

## Which pages keep the fixed block?
- doc_submission, conversation, staff_report. Make designable there picks the design's first thread, `topic_replies`, with its topic words: use Make a copy for this page and reword.

## How do the e-mails draw a comment?
- The card's View or Reply link is bound to `__comment_url` with no flag above it, so `pg_comments_email_drew()` takes the draw; the System region after it keeps the fixed markup for an e-mail the widget does not draw. A comment of a block page (conversation, the desk) gets the card, never a reply link.

## Which page settings?
- Each page keeps its `comments` array; every one has attachments and select_name false (names come from the profile); topic and doc also a message and a closed_message.

## What happens to sites made from an older version?
- Nothing: `style_template_version` is for reference and rows are never reused: a 1.0.x site keeps the block, a 1.1.0 site has no board list. Make designable gives the editor's starter, not this layout. Opening the template again makes a second design with numbered page names and new folders (moderators need them ticked); comments stay on the old pages.

## What looks odd but is expected?
- A first publish (setup or editor) warns once per e-mail page "Comments are off ... draws nothing" ([[comments-widget-save-warnings]]); that widget's panel says comments are off, yet it draws the e-mail's comment.
- The preview repeats the e-mail card and Latest replies' row three times. Visitors' home page may show Latest replies stale up to PAGE_CACHE_TTL. `mine` can take ~300 ms for an old account with no comments ([[comments-widget-recent-reads]]).
- The feedback stars start at 5: a moderator's answer under feedback is stored with 5 unless changed.

## How was it checked?
- The tests above, with pure draws of both threads and the e-mail card.
- Sandbox, MariaDB 10.11, PHP 8.3, Chromium (2026-10-25): fresh install with the template; a topic, a Reply chain to the cap and its quote, Follow, feedback held, listed on the dashboard, published, then in Latest replies and My replies; Featured first; ten reply and feedback e-mails queued, each the card with one View or Reply; phone width, dark theme.
