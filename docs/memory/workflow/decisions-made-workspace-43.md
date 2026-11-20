---
name: decisions-made-workspace-43
description: Decisions made, workspace part 43 (2026-11-20 on): a Claude Console agent wakes on a schedule, no Anthropic key on the site; @herkes in a discussion: its own agents, a hint line, a bulk add
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-workspace-42.md`.

## How does a Claude Console agent wake when it is called in a channel? (owner, 2026-11-20, agent channel poll 182, channel message 10601)
- "Zamanlı çalışsın": Console's own scheduled run reads the agent's request list; a call waits in the queue until the next run.
- The site keeps no Anthropic API key and calls no Anthropic address; no third door form is added beside waiting and the push door.
- Not chosen: the site waking the session itself through Anthropic's API with an Anthropic key kept on the site.
- Why it was asked: the research agent's reading (#10731) found that a Console session is started or woken only through Anthropic's API; nothing calls in.
- Built by plan app-access-redraw, step AA7 (`plans/app-access-redraw-console.md`).

## Which agents does @herkes wake in a discussion? (owner, 2026-11-20, agent channel poll 184, channel message 10624)
- "Eklenenler uyansın, ipucu ve toplu ekleme gelsin": the rule stays. In a discussion `@herkes` wakes only the discussion's own agents; the channel's other agents answer there when named (`workspace/workspace-everyone-mention.md`).
- New: a line under a person's `@herkes` that woke no agent there, saying why; the channel's agents added to a discussion in one go, all or the picked ones.
- Not chosen: "Kanalın tüm ajanları da uyansın" (every allowed application agent of the channel woken, added or not), "Olduğu gibi kalsın".
- His case, in the architect's window: "ajanları eklediğim halde @herkes etiketi çalışmıyor". In the discussion "Hızlı bir koordinasyon turu" the tag was written a minute before the three agents were added, so it woke nobody; the later edit did not add the tag, so it woke nobody either.
- The plan: `plans/discussion-everyone-hint.md`.
