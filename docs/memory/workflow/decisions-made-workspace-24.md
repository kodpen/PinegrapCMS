---
name: decisions-made-workspace-24
description: Decisions list, workspace part 24 (2026-11-14 on): a guest sees the agents' messages and wakes none; guests listed in Channel info; the channel's agents' answers read aloud
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does a guest see the agents' messages? (owner, 2026-11-14, in the architect's window)
- Yes, and a guest still wakes no agent. Owner: "misafir ajan mesajlarını görebilsin ancak onları tetikleyemesin. kanalda sahip ile konuşabilsin. (ajan kanalı dışında da bu dikkat edilmeli)".
- It holds in every channel shared with a guest and in a guest's room, not only in an agent channel.
- Why: he shared the agent channel with a guest by a link; her tag was answered by an agent that reads the channel on a timer, and she could not see the answer. Owner: "ya etiketi dikkate alınmamalıydı ya da mesaja yanıtı görmeliydi (önceki mesajlar dahil)".
- The architect's reading: "agents' messages" are those of the channel's agents and of the assistants (Claude, Pinegrap AI, MCP); lines of applications that are not agents (CI, integrations) stay hidden from a guest.
- Not changed: a guest's message, tag, answer, emoji, vote or tick makes no request for an agent (`decisions-made-workspace-23.md`), and no agent answers a guest's line (`agent-channel-guests.md`).
- It is a standing rule of the product, not an order to the agents of one channel. Owner: "bunu ajan kanalı için kalıcı kurala döndürebiliriz sadece komut değil ajan kanallarında ve sohbet kanallarında aynı durum gerçekleşmeli."
- Owner: "misafir ajanların konuşmalarını görüp takip edebilir ancak kanala yazma yetkisi varsa bile bir ajanı tetikleyemez."
- Every channel's brief and prompt carries the caution by default. Owner: "ayrıca her kanalda bu konuda varsayılan dikkatli olmayı sağlayacak bir promp bilgisi olması iyi olurdu".
- Plan: `plans/guest-channel-view.md`, steps GV1 and GV5.

## Is a guest listed in Channel info? (owner, 2026-11-14, in the architect's window)
- Yes: "ayrıca misafir kanalda bilgi alanında görünmesi iyi olurdu". A guest with an open share is named in Channel info beside People and Agents. Step GV2.

## Are the channel's agents' answers read aloud? (owner, 2026-11-13, in the architect's window)
- Yes, as an assistant's answers are. Owner, with "Read assistant answers to me aloud" on in the agent channel: "benim tarayıcım anıldığımda ses çalmıyor ve sesli okunmuyor." Read aloud covered only Claude, Pinegrap AI and MCP answers. Step GV3.
- The sound was the hidden tab: it stays silent there (`decisions-made-workspace-18.md`, #8798); that decision is not changed.
