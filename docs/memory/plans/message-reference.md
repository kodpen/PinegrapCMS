---
name: message-reference
description: Plan (owner 2026-11-15, in the architect's window): a message pasted into a message box as a reference, shown by its number, opening the referenced message in a popup; for people and agents
priority: 6
---

## What is asked?
- Owner (2026-11-15, in the architect's window): "bir mesajı kanaldaki mesaj yazma alanına referans olarak yapıştırma özelliği getirelim. yanıtlama değil."
- How it shows, his words: "soru içinde referans mesaj numara ile görünür mouse üzerine götürürsem veya telefondan tıklarsam popup şeklinde bahsettiği mesaj (metin veya görsel veya diğer öğe) o açılan popup içinde görünsün."
- For people too: "ayrıca kullanıcılarda bir mesajı referans olarak mesaj alanına yapıştırabilsin. aynı şekilde kullanılsın."
- His reason: an agent's question about a picture or an earlier message does not say which one: "ben hangi görselden bahsediyorsun anlıyamıyorum".
- Today a message has "Copy link" (`?channel=…&message=…`) and a reply; a link is plain text and a reply opens a discussion.

## What is the shape?
- A new tag kind `message`: `<#message:N>` in a text, beside the record tags of `includes/workspace/refs.php` (`ws_tag_type_keys()`, `ws_refs_resolve()`). No schema change: `ws_refs.ref_type` is `VARCHAR(20)`.
- Drawn as a chip with the message's number. Pointer on it (desktop), tap (phone) or focus and Enter (keyboard) opens a popup with the referenced message as the channel draws it: sender, time, text, picture, file, poll or other item, and "Go to the message".
- Only a reader who may read the message's channel sees it; anyone else sees the chip and "You cannot see this message". A deleted message says so.
- It is not a reply: no discussion opens, nobody is woken or notified by it.

## Which steps are there?
- MR1 (M, server) The tag kind: resolved per reader (sender, time, channel, kind, a short text; `known` false without the right, `gone` when deleted), the popup's data from the message payload behind the same right, `ws_plain_text()` words for push, e-mail and export, the API's `text` taking the token (schema and docs), tests.
- MR2 (M, after MR1) The chip and the popup in `workspace.js` and `backend.src.css`: in a message, a discussion, a poll's and an agent question's text; the float frame of the agent card (`.pg-card`, `--pg-surface-float`); a long message cut with "Go to the message"; closes as the agent card does.
- MR3 (S, after MR2) "Copy as reference" in a message's menu beside "Copy link"; pasted into a message box it becomes the chip, and so does a pasted link of a message of this site. Any channel's or discussion's box.
- MR4 (S, after MR1) The agents' side: the working rule that an agent's question about a picture or an earlier message carries its reference, the tools' help text, close-out into `workspace/message-reference.md`, this plan deleted.

## Whose look?
- The owner's: the chip and the popup are new on Workspace. MR2 posts pictures (the chip in a message and in an agent question; the popup over a text, a picture and a poll; 1440 and 390, light and dark); the architect brings them to him in one question with the picture in its box. MR2 is not marked ready before his look.
- The architect's readings, not his words: the token and its rights, the keyboard way, "Go to the message", a pasted link becoming a reference, what a reader without the right sees.
