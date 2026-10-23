---
name: design-template-news-publishing
description: News template publishing: who may submit, the three-row live rule, scheduling and search, where a draft could leak, search noise, structured data and feeds, author HTML, signed-in tips, e-mails.
---

## Who may submit, and how is an author let in?
- A member with view rights on `contributors` (Users, Private Content Access). Editors: role ≤ 2, or edit rights on `private` (inherited by `contributors`) plus "Reach the data submitted through forms" for Change Status.
- Tips and contact messages sit in `public` (visitors must send them), so a role-3 editor needs `public` ticked under Content and Forms (edit rights, `check_edit_access()`) to open a tip in the panel or answer in the inbox; the staff note says so.
- `my_account.staff_page_id = {{tab:my_articles}}` shows the author desk card; `login_region.staff_page_id = {{tab:staff}}` the "Newsroom desk" link.

## What makes a piece public?
- Every public list, the ticker, most read and the article view carry: status = Live on the site; publish_date >= `2000-01-01 00:00:00`; publish_date <= current date and time.
- The second row: `prepare_sql_operation()` compiles `IFNULL(x,'') <= 'now'`, true for an empty time (API, import, a panel save). The desk list "Live, but no publication time" shows them; a test guards each row.
- Renaming a status option in the panel breaks the filters; change both.

## How does scheduling work?
- A future publish_date holds the piece back ("Scheduled to go live"). Lists, feeds and the sitemap compare with now per request; site search adds it only at the next index run (daily, or Update Search Index).

## Where could a draft leak?
- Closed: lists, ticker and feeds by the live rule (feeds and the sitemap merge the detail view's filters); the article page by its record filters (404 + noindex); desk, My Articles and `submission` by private folders plus `submitter_only` / viewer filter; mail pages by `submitter_only`.
- Residual: a piece taken off the site stays in search, linking to a 404, until the next index run.
- Uploaded photos (cover, byline; folder `public`) open for readers once the piece is live ([[designer-form-uploads-for-readers]]); before that only the newsroom opens them, as the staff note says. Staff photos and tip files (`private`) stay closed.
- Search noise: the index takes each page's whole text with its widgets, so a headline in the strip or most read matches every page that showed it (follow-up: index no list output).

## What do the structured data and the feeds reveal?
- BlogPosting `author` is the sending account's contact name, not the byline (no username fallback: samples print the Organization); `datePublished` is the desk-arrival time.
- RSS feeds order and date items by `submitted_timestamp`: a piece set live days later has the old pubDate. Follow-up: author, published date and feed order from fields with roles.

## What can an author's rich HTML still do?
- Sanitizing keeps `class` and `style`; `.pn-body{position:relative;contain:paint}` keeps a `stretched-link` or a fixed overlay inside the body box. An `<img>` from another site still loads for the editor.

## Is a signed-in tip anonymous?
- No: the record stores the member, the confirmation goes to the account's address, and the desk and `email_tip_new` show its username (`submitter`). The tips page says so. Signed out, no account is made.

## Which e-mails go when?
- Submit: `email_article_new` to the site address (with the note), `email_article_received` to the author. Tips: `email_tip_new`, `email_tip_received`.
- A message under the piece on `submission`: `email_desk_message` to the author and every watcher. Conversations: `email_reply`.
- Reader comments are held; approving one (`edit_comment.php`) mails `email_reply` to watchers only, the commenter too. The author is not mailed (accepted platform limit; core follow-up: `add_comment.php`'s designed-page mapping).
- No "published" e-mail (no status-change hook): the editor writes "It is live" under the piece.
