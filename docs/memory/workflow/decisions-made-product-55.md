---
name: decisions-made-product-55
description: Decisions made, product part 55 (2026-11-15): a Send later e-mail of a writer who lost the right turns back into its draft; the star rating input as five stars; message boxes' width on a wide screen
---

## Does a Send later e-mail leave after its writer lost the right to answer there? (owner, 2026-11-15, channel message 4238)
- Found by the e-mail bug hunt: the job that sends a waiting e-mail at its time did not look at its writer again, so the e-mail left in the mailbox's name after its writer was taken out of the channel or lost the account.
- Offered "it is not sent: back to its draft, a notice to the writer and to the one responsible for the inbox", "it stays as it is: planned while the right was there, it goes" and "something else", the owner voted: "Gitmesin: e-posta taslağa dönsün, yazana ve gelen kutusunun sorumlusuna bildirim düşsün (Önerilen)". Outcome: channel message 4243.
- The architect's readings, not his words. "The one responsible for the inbox" is the conversation's holder when that is somebody else; when nobody else holds it, the owners of the channel it is kept in; in a mailbox's own room, its members who hold the Workspace settings right.
- Also the architect's: the draft keeps its recipients, subject, text and files; "Send now" by somebody who may answer there still sends the e-mail, whoever wrote it; an e-mail waiting its ten seconds for "Undo" passes the same test; no schema change.
- Work: the `bug` issue #9676. Its PR writes the rule into `workspace/mail-send-later.md` and the notice into `workspace/mail-notices-and-event.md`.

## How does the comment form's star rating input look? (owner, 2026-11-15, channel message 4700)
- On CB2's canvas sample (the true markup of `pg_comments_rating_input_html()`: a button group, each button a star and its number) he wrote: "bu yıldız şekli hoş görünmüyor. google yorumlardaki gibi bir düğme yapısı daha uygun olur".
- The architect's reading, not his words: five stars side by side with no number; the chosen star and those before it filled, the rest in outline; a pointer over a star previews the same way.
- Kept: the field `rating` with values 1 to 5, the ids `comment_rating_1..5`, a radio group for the keyboard and a screen reader. The look changes on running sites' designed pages; the form posts as before. The classic block already draws a row of stars and is left.
- Work: in CB2 (#9716).

## Do Workspace's message boxes stop growing on a wide screen? (owner, 2026-11-15, in the architect's window)
- His words, with two pictures of a channel about 2000px wide: "mesaj kutularını maksimum bir sınır verelim. geniş ekranlarda uzayıp gidiyor. mesela 1700px üzeri ekranlarda görsel 2 deki gibi çok belli belirsiz opacity li kullanıcı rengi ile uyumlu bir renk olabilir". In his second picture one message sits in a faint box about 1480px wide.
- The architect's reading, not his words: a message's box gets a maximum width; on a screen wider than about 1700px the box also takes a very faint tint of its sender's colour. The exact width and tint go to him as pictures with a close-up pair.
- Work: a small task of its own (`[iş]`).
