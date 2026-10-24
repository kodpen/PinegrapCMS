---
name: design-template-magazine-publishing
description: Magazine template publishing: the shared live rule and leak closures, the desk lists, letter names (username until 7r), author HTML and photos, the staff note's rules for renaming fields.
---

## What is the same as in the news template?
- Who may submit, the live rule on every public list and the story view, scheduling, structured data and the leak closures are the news ones ([[design-template-news-publishing]]). "Public" in the tests is every folder but `private` and `contributors`, so `registration` pages hold no story view.
- Test 3 checks every story view's filter rows against the form's control names and the renderer's operators. A list whose filter names a missing field shows nothing, not drafts (#1299, [[form-list-filters-fail-closed]]).
- Update Search Index needs a manager or above (`update_search_index.php`), else the daily job: the notes send editors to one. A story item indexes its whole page, More from the magazine and Most read too: a title also finds the story pages that listed it.
- Feeds date and order stories by `submitted_timestamp` (`widgets.php`): an issue's stories reach feeds dated when sent. The staff notes say both.

## Which desk lists are there?
- Waiting for review, Being edited (`In review`), With the writer (`Changes requested`), Scheduled to go live, Live but no publication time, Live now, Every story (the page's one paginated list, with a search over title, byline, section, status, issue and tags).
- Each row shows its Last activity (a list cannot be ordered by it; a long read can wait weeks).

## Why do the editors fix a letter's name before they publish it?
- `add_comment.php` keeps a typed name only for a signed-out visitor, role < 3, folder edit rights or the form editor; a member's letter is stored under the login username. A template cannot set `comments_allow_user_to_select_name`.
- Every letter is held (`auto_publish false`), so editors change the name under Comments → Edit Comment before publishing. The platform fix is 7r ([[design-templates-newsroom]]); the template needs no change.
- An editor's reply on `conversation` (`registration`, auto-published) is stored and mailed under their username, by the same rule: the note asks for user names readers may see.
- Letters sit on `blog_post` (`public`): `edit_comment.php` and the Comments list need edit rights there, so the access note has editors tick `public`, as for contact messages.

## What do the staff note's rules on fields guard?
- Never rename or remove `status`, `publish_date`, `placement` or `issue`, and keep the six office fields staff only: a missing field closes every list, and a visible office field would let an author set a story live.
- Statuses, placements and issues are filter words: an administrator or designer changes one in the visual editor (the panel refuses a designer form's fields), publishes, and sets it on the stories with the old one. A new list or story view of the form elsewhere needs the live rule.

## What can an author's rich HTML still do?
- `.pm-body{position:relative;contain:paint}` on the story and on `submission` keeps a stretched link or fixed overlay inside the text. Remote `<img>` sources load for the editor, and for every reader once live; links print without `ugc`. The staff note has editors check the story's name (it picks the contributor page), remove such pictures and check links before going live.
- Uploaded photos open for readers once the story is live and only for the editors before ([[designer-form-uploads-for-readers]]); until then the author sees a placeholder on `submission`.

## Which e-mails go when?
- Submit: `email_article_new` to the site address (with the note), `email_article_received` to the author. A message under the story on `submission`: `email_desk_message`. Contact replies and letters: `email_reply`. Approving a letter mails the story's author (unless they wrote it) and its watchers (the writer too). Newsletter: `email_welcome`. No "published" e-mail: the editor writes "It is live" under the story.
