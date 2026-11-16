---
name: owner-question-picture
description: A question to the owner about a picture carries the picture inside its own box (owner 2026-11-15 and 2026-11-16): how it is asked today, and what plan agent-question-picture changes
---

## What did the owner say?
- Owner (2026-11-15, in the architect's window), on questions that said "this look" with the picture somewhere above: "şu anda senin sorduğun sorularda bu referans olmadığı için ben hangi görselden bahsediyorsun anlıyamıyorum". Between a picture and its question other messages arrive, and a channel holds many pictures.
- Owner (2026-11-16, in the architect's window), on questions that named the picture's message with a tag: "çizimlerimizde ajan sorusu tek görsel de sorunun solunda, her seçenek için görsel de seçenek içinde olacaktı. ancak sen resmi atıp mesaj etiketi ile soruya dahil ediyorsun".
- So the picture belongs in the question's box, as in the drawing he approved (`workspace/agent-questions/drawing.html`); a tag in the question's text is not enough.

## How is a question about one picture asked today?
- The sheet is posted in the channel (`php tools/ws_post.php --file=PATH "text"`, it prints the message number N): the box takes only a picture posted there (`workspace/poll-option-parts.md`).
- The question carries it on both options and has no "Other" row: `php tools/ws_poll.php ask --recommend "question" "Evet, böyle çıksın :: #N" "Hayır, böyle çıkmasın :: Ne değişsin, bu sorunun altına yazın. :: #N"`.
- Why no "Other": one answer, a picture on every option and no "Other" row is the only case in which the box draws a large picture beside the options (`workspace/poll-box.md`). What he wants changed he writes under the question.
- One picture a question: several screens are stacked into one sheet with their names written on it. First asked so in poll 81 (agent channel message 6427).

## How is a question that compares looks asked?
- Each option carries its own picture (`label :: description :: #N`, one posted picture per option): the large picture follows the option pointed at. With an "Other" row each option shows its picture as a thumbnail in its row.

## When does a question name a message with a tag?
- Only when it is about an earlier message that is not a picture (`<#message:N>`, `workspace/message-reference.md`).

## What does plan agent-question-picture change?
- The question is asked with its picture in one call (`ws_poll.php ask --file=PATH`), with no message of its own for the picture, and "Şunu değiştir" comes back as the words field under the options. QP3 of `plans/agent-question-picture.md` corrects this file.

## Is this an owner decision?
- His words are the reason; how a question carries its picture is the sessions' working rule, the architect's to change.
- A question already asked is not asked again for this; he is not reminded (`workflow/owner-contact-channel.md`).
