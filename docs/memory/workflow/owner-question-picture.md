---
name: owner-question-picture
description: A question to the owner about a picture carries it in its own box (owner 2026-11-15, 2026-11-16): ws_poll.php ask --file with "Other"; looks compared by option pictures
---

## What did the owner say?
- Owner (2026-11-15, in the architect's window), on questions that said "this look" with the picture somewhere above: "şu anda senin sorduğun sorularda bu referans olmadığı için ben hangi görselden bahsediyorsun anlıyamıyorum". Between a picture and its question other messages arrive, and a channel holds many pictures.
- Owner (2026-11-16, in the architect's window), on questions that named the picture's message with a tag: "çizimlerimizde ajan sorusu tek görsel de sorunun solunda, her seçenek için görsel de seçenek içinde olacaktı. ancak sen resmi atıp mesaj etiketi ile soruya dahil ediyorsun".
- So the picture belongs in the question's box, as in the drawing he approved (`workspace/agent-questions/drawing.html`); a tag in the question's text is not enough.

## How is a question about one picture asked?
- With its picture in one call: `php tools/ws_poll.php ask --file=PATH --recommend "question" "Evet, böyle çıksın" "Hayır, böyle çıkmasın" "Other"`. The picture needs no message of its own; the box draws it large at the left of the options (#9948, #9964, `workspace/poll-question-picture-box.md`).
- "Other" is the words field under the options of an agent question: what he wants changed he writes there, beside the picture.
- One picture a question: several screens are stacked into one sheet with their names written on it (first asked so in poll 81, agent channel message 6427).
- The asker replaces or removes the picture while nobody has answered (`ws_poll.php`, `POST /workspace/polls/{id}`, the panel's Edit poll form); an answered question keeps what was asked.

## How is a question that compares looks asked?
- Each option carries its own picture (`label :: description :: #N`, one posted picture per option): the large picture follows the option pointed at. With an "Other" row, or beside a question picture of its own (`--file`), each option shows its picture as a thumbnail in its row.

## When does a question name a message with a tag?
- Only when it is about an earlier message that is not a picture (`<#message:N>`, `workspace/message-reference.md`).

## Is this an owner decision?
- His words are the reason; how a question carries its picture is the sessions' working rule, the architect's to change.
- A question already asked is not asked again for this; he is not reminded (`workflow/owner-contact-channel.md`).
