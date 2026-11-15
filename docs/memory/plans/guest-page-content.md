---
name: guest-page-content
description: Plan (owner 2026-11-15): the guest page shows an agent's role and model, agents' pictures and files, a canvas as its picture, the agent's task card and live pages, all read only; GP1 to GP3
---

## What is asked?
- Owner (2026-11-15, in the architect's window): "workspace_guest.php de görsel, tuval (sadece önizleme) ve diğer sayfa içi içerikleri de gösterelim olur mu? hepsi salt okunur. düzenleme yetkisi olan bir guest için de tuval vs. erişimi olmasın ama görüntüleyebilsin".
- And on a picture of the guest page: "ajan yazıyor ama rol leri ve modelleri yazmıyor o konuda da bir düzeltme yapalım".
- Before: a guest reads the agents' lines under the badge "AI agent" alone (`workspace/guest-agent-lines.md`), and only a person's file is served (`ws_guest_file_send()`), so an agent's picture, canvas and live page do not show.
- The record: `workflow/decisions-made-workspace-33.md`.

## What does a guest see?
- Beside an agent's name: the badge, then its role (`ws_agent_role_label()`), then the model it reported (`workspace/agent-report.md`), muted. An empty fact is left out. No usage, no state dot, no card.
- An agent's file as a person's: a picture in the page; video, sound and PDF in the page; anything else a download. HTML and SVG are never run.
- A canvas, a person's or an agent's: its drawn picture as a preview. No Design Canvas, layers, versions, Undo or document, for a guest who may write as well. A picture not drawn yet: a line saying the preview is not ready.
- An agent's task card as a person's: title, state and due date, to read.
- A live page: its card (the name and the line naming the agent) with Open and Full screen, run in the guarded frame as in the panel. Owner: "Kart ve Aç". No View source, versions, Undo or Download.
- Unchanged. Owner: "Yalnız türü, bugünkü gibi": a record's tag reads as its kind alone; mail cards, change cards, buttons, request lines and the lines of applications that are no agents stay hidden.
- A guest's own abilities do not change, and nothing a guest does asks an agent (`workflow/decisions-made-workspace-24.md`).

## Which steps?
- GP1 (S) Role and model beside the badge: `ws_guest_agent_apps()` carries them, `workspace_guest.js` draws them. Tests. Done: #9693.
- GP2 (M) Agents' files and pictures, the canvas preview, the agent's task card: `ws_guest_messages()` and `ws_guest_file_send()` serve the file of an agent's line the guest reads, inside the room's current conversation (`workspace/workspace-guest-cleared-room.md`). Tests of reach: the file of a hidden application, of another channel and of an earlier conversation stays 404.
- GP3 (M) The live page for a guest: the card on the guest page; the frame's address answers a guest's cookie only for the page of a line that guest reads, with the panel's sandbox and size bound. A test for each refusal.
- Close-out, with GP3: `workspace/guest-agent-lines.md` and a new `workspace/guest-page-content.md` say what a guest sees; this plan is deleted.

## What does every step keep?
- No schema change; a need for one is asked of the architect. Everything is read only: no new ability for a guest.
- One worker, one step after another: all three edit `guests.php` and `workspace_guest.js`.
- Pictures of the guest page (light and dark, a phone and 1440) go to the architect before the label.
- Claim as `[plan:guest-page-content] <step> — <topic>`.
