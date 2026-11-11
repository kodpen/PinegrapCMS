---
name: decisions-made-workspace-15
description: Decisions list, workspace part 15 (2026-11-11 on): the Workspace settings cards are simplified as they move under Site Settings, Anthropic as a provider of model assistants
---

## Where are the rules and the earlier parts?
- `decisions-made.md` holds the rules; `DECISIONS.md` lists the entries of every part. Entries name the issue and the date.

## Are the Workspace settings cards simplified as they move under Site Settings? (owner, 2026-11-11, #8750)
- Yes. Each card is simplified in the step that moves it; options that are rarely needed go under an "Advanced" part.
- Owner (2026-11-11), in the architect's window, after he registered the merging session as an agent: "pr mergeyi kanala bağladım. biraz zor oldu özellikle ekleme ekranı karışık geldi ve Sağlayıcı kısımında claude yoktu. bu ayarlar site ayarları altına taşınırken bu ekranları sadeleştirme de yapalım. gereksiz seçenekleri altında gelişmiş olarak gizleriz."
- The architect's, not the owner's words: what is rarely needed is each step's call, named in its PR; no field or value is dropped (the plan's no-schema rule); the "Add an agent" form shows only the chosen kind's fields (step SW4). Plan: `plans/settings-workspace-move.md`.

## Is Claude (the Anthropic API) a provider of model assistants? (owner, 2026-11-11, #8750)
- Yes: Anthropic joins the Provider list beside OpenAI, Gemini, Mistral, DeepSeek, OpenRouter, Azure and a custom address.
- Owner (2026-11-11): "Evet, Claude eklensin (Önerilen)", his vote on the one-choice poll in the agents channel, confirmed in the architect's window ("2 sorunu onayladım"); raised by his words "Sağlayıcı kısımında claude yoktu".
- Not chosen: "Hayır, gerek yok" (the list stays; a Claude model only through OpenRouter).
- The architect's, not the owner's words: no schema; a Claude Code session is an application agent and needs no provider, so its form shows no Provider field. Plan step: A16 of `plans/agent-channel-steps-4.md`.
