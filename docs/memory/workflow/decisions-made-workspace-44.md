---
name: decisions-made-workspace-44
description: Decisions made, workspace part 44 (2026-11-21 on): a file pasted, dropped or picked waits in the writing box until Send; the conversation timeline zooms, marks days and never leaves the tabs row
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-workspace-43.md`.

## When does a file put into a conversation's writing box go? (owner, 2026-11-21, agent channel message 11680)
- Owner: "bir mesajı yazıp ekran görüntüsü veya dosyayı mesaj alanına yapıştırınca dosya ile mesaj daha göndermeye hazır olmasam bile gidiyor, kanala gönderiliyor. gorsel içeren mesajları tekrar düzenlemekle uğraşıyorum".
- A pasted picture or file waits in the box and goes with Send, with the words written by then. Until now the paste posted it at once with whatever words stood in the box (`workspace/workspace-editor-pasted-files.md`).
- Architect (2026-11-21): a file dropped on the conversation and one picked with "Add file" wait the same way, so the box has one behaviour. A waiting file is a chip over the box (a picture's thumbnail or a file icon, name, size, a remove button); Send is live with words or a file; a refusal known in the browser (kind, size) is said when the file is added.
- A channel's box and a discussion's alike, and the plain textarea fallback. A note's attach is not a send and stays as it is.
- No schema and no API change: a message still carries one file; several waiting files go as "Add file" sends several today, the words with the first. Waiting files live in the open screen only, they are not kept in the saved draft.

## How does the conversation timeline behave when the conversation is long? (owner, 2026-11-21, agent channel message 11680)
- Owner: "timeline alanını bir elden geçir zoom in zoom out yapılabilsin. çok fazla konuşma olunca gruplamalar anlaşılmıyor gün gün boşluklarda biraz daha anşaşılır olsun abartılmadan. ekran daralınca ekran dışına taşıyor."
- It zooms in and out, marks its days and never leaves the tabs row; it opens at its newest end with no turn under 6 px, and the zoom is kept per channel for the viewer in the browser.
- Built from these words alone (#11027) it read as noise; the owner sent it back the same day. How it is drawn, its size, the wheel and the keys: `decisions-made-workspace-46.md` and the drawing `workspace/channel-timeline-drawing/timeline.html`.
- The read, the colours by writer and the card stay as they are.

Related: docs/memory/workflow/decisions-made.md, docs/memory/workflow/decisions-made-workspace-46.md, docs/memory/workspace/workspace-channel-timeline.md, docs/memory/workspace/workspace-editor-pasted-files.md, docs/memory/workspace/workspace-attachment-uploads.md
