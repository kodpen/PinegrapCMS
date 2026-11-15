---
name: owner-question-picture
description: A question to the owner about a picture or an earlier message says which one (owner 2026-11-15): the picture in the question's box or its message number named in the question
---

## What did the owner say? (owner, 2026-11-15, in the architect's window)
- Asking for the message reference feature (`plans/message-reference.md`), on the architect's questions in the agent channel: "şu anda senin sorduğun sorularda bu referans olmadığı için ben hangi görselden bahsediyorsun anlıyamıyorum".
- A question that says "this look" without saying which picture cannot be answered by him: between a picture and its question other messages arrive, and a channel holds many pictures.

## How does a question name its picture until the feature lands?
- The picture is posted as a file right above the question (`php tools/ws_post.php --file=PATH "text"`), and the question's own text names that message's number.
- Or the picture sits in the question's box: an option's `file_id` on `POST /workspace/channels/{id}/polls`, a picture posted in the same channel (`workspace/poll-option-parts.md`). `tools/ws_poll.php` has no flag for it.
- One picture a question: several screens are stacked into one picture with their names written on it.
- A question already asked is not asked again for this; he is not reminded (`workflow/owner-contact-channel.md`).

## And once the feature has landed?
- The question carries the reference itself (`<#message:N>`): he opens the picture from the question. MR4 of the plan writes that as the agents' working rule and corrects this file.

## Is this an owner decision?
- His words are the reason; how a question names its picture is the sessions' working rule, the architect's to change.
