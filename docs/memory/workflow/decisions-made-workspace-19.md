---
name: decisions-made-workspace-19
description: Decisions list, workspace part 19 (2026-11-12): a discussion follows its root message's agent, a textless close reads "saw it", a fitting emoji, agents among a channel's members and last seen
---

## Where are the rules and the earlier parts?
- `decisions-made.md` holds the rules; `DECISIONS.md` lists the entries of every part. The owner's words of this part stand on #8871, #8872, #8873 and #8880.

## Whom does a discussion under an agent's own message follow? (owner, 2026-11-12, #8871)
- The agent that wrote the message, whether the message answered a request or was the agent's own, while a row of the agent reaches the discussion. It comes before "the agent the root message names".
- Owner (2026-11-12), in the architect's window with a picture of the channel: "ajan kanalında gördüğün gibi senin ile tartışma başlattım ama sana ulaşmadı". His answer to the question asked there: "Mesajı yazan ajan (Önerilen)".
- It widens `workspace/agent-discussion-follow.md`, which started the follow only from an answer or a name. One followed agent per discussion, Stop and Follow again and the brakes stay.
- Built in #8911 (`workspace/agent-discussion-follow.md`).

## What does the line read when an agent closes a request without text? (owner, 2026-11-12, #8872)
- "saw it · no answer needed" (Turkish "gördü · yanıt gerekmedi") with the eye kept. The tick and "answered" show only when the agent wrote a message.
- Owner (2026-11-12), his answer in the architect's window: "Gördü · yanıt gerekmedi (Önerilen)". Earlier that day, with a picture of the tick and "`@mimar` yanıtladı" boxed in red: "buradaki uygulamadan gelen mesaj ayrışmamayı çözmek gerekiyor".
- It replaces the line of `workspace/agent-shown.md` that shows a request closed with `no_answer` as answered.
- Built in #8919 (`workspace/agent-shown.md`).

## May an agent leave an emoji on a message? (owner, 2026-11-12, #8873)
- Yes: one that fits the moment, on the message that asked, beside its text or in place of it.
- Owner (2026-11-12): "ajanlar kanallarda duruma göre varklı ifadeler de kullanabilsin. mesela kutlama ikonu veya kalp gibi". After the how below was put to him: "uygun, plana ekle".
- The architect's, approved by him as written: at most one emoji per request, never on the agent's own message; the brief tells every agent; the answer route takes `emoji`; a model assistant gets the same choice; the automatic eye and tick stay.
- Built in #8932 (`workspace/agent-emoji.md`).

## Who is listed as a channel's member, and who sees a last seen? (owner, 2026-11-12, #8880)
- The channel's agents are listed with its people, in the channel head's faces and in "Channel info", with their handle and a mark that tells them from people; both get a last seen. A person's last seen time is shown only to managers; an agent's to everyone in the channel.
- Owner (2026-11-12): "kanallarda uygulama olarak dahil olan bir ajan varsa bunlar kullanıcı gibi üst tarafta kendi etiketleri ile […] görünsün ki yönetici kanalda kimler kayıtlı bilsin. birde hem kullanıcı hem de ajanların son görülme zamanı üyeler bölümünde görünsün […] bu sayede düşen ajanları takip edebiliriz". Asked who sees a person's time: "Yalnızca yöneticiler (Önerilen)"; not chosen: "Kanaldaki herkes".
- Step A22 of `plans/agent-channel-steps-7.md`; the look he approved: `plans/panel-design-language-dialogs.md`.
