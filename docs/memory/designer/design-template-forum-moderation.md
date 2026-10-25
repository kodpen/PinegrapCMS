---
name: design-template-forum-moderation
description: Forum template moderators: dashboard counts and lists (replies awaiting approval too), desks, reports, the folders a moderator needs, hiding, solving, best answers, e-mails, renaming.
---

## What do the moderators' pages hold?
- `staff` (private, staff bar): six count strips (open, unanswered, solved and hidden topics, open reports, guides to review); lists of ten: open reports, guides awaiting review, replies awaiting approval (`pending`, oldest first), new topics (live, `days ago` 7), unanswered (live, oldest first), most viewed this week (`recent_views` 7 days, views > 0), recently solved, hidden; shortcuts to Comments, Submitted Forms and the panel; "How moderation works".
- `staff_topics` (Topics desk): every topic, `?status=` with status links, a search that includes `staff_note`. `staff_docs`: Awaiting review, In review, Changes requested, Live on the site, every guide. `staff_reports`: open (up to 50), resolved, dismissed, every report. `staff_report`: one report with Note comments. `staff_directory`, `staff_entry`.
- Only the last list of a page pages through. Staff lists of live topics (new, unanswered, most viewed) carry the live rule; the others show every status.
- Recently solved, hidden topics and closed reports: newest activity first (no list orders by last-modified time).

## Which rights does a moderator need?
- Users, then Content and Forms: tick `private`, `registration` and `public`, and switch on "Reach the data submitted through forms". Administrators, designers and managers need none of it.
- `private` opens the moderators' pages. `registration` holds the three forms: `doc_submission` and `staff_report` are `submitter_only`, so edit rights on the form show a moderator every guide and report. `public` holds the topic and guide pages (publish, edit or delete replies and feedback, close replies) and the contact form (the inbox).

## How is a topic moderated?
- Off the site: Status Hidden (the pencil on a row, Moderate on the page); it leaves every list and its page at once, Open brings it back. A list that may hold one (Topics desk, hidden topics) links no title: its page would not show it.
- Solved, pinned and announcement are office fields. Closed is only a word: replies stop with Stop new replies under them.
- Replies publish at once, feedback is held: both are moderated under Comments or with Edit on each; Featured puts the best answer first ([[design-template-forum-replies]]).

## How are guides and reports handled?
- A guide: Awaiting review, In review, Changes requested, then Live on the site, Withdrawn or Not accepted. A Status sends no e-mail: the moderator says it in a message under the guide.
- `doc_order` is compared as text: two digits (10, 20, 30); a guide without one comes first.
- A report's post is what a member typed: printed as text, never as a link (bound hrefs are not scheme-checked). Note, then Resolved or Dismissed. The post's author is never told who reported it.

## Which e-mails go out?
- New topic: `email_topic_new` to the site address, no copy to the author (it is live). Guide: `email_doc_new` and `email_doc_received`; desk messages `email_doc_message`. Report: `email_report_new` and `email_report_received`. Contact: `email_new_message` and `email_message_received`.
- Replies and feedback: `email_reply` (its comment drawn by `email_comment`) to the record's author and followers; moderators read them in Latest replies and under Comments.

## What must not change?
- The fields status, board, kind, pinned, announcement, section, featured and doc_order, and their options: lists filter on these words and fail closed. Office fields keep "Hide from the visitor (staff only)".
- A board, section or status is renamed in the visual editor together with the pages' links; then publish and set the new word on the old records.
- Site search is rebuilt daily; Update Search Index (manager and above) takes a hidden topic out at once. Moderators sign with the name box, prefilled from their profile ([[designed-page-comment-names]]).
