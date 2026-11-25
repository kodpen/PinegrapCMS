---
name: agent-brief-who-wakes
description: Plan (owner 2026-11-25): an agent's brief says who wakes how in the request's channel or discussion, the instructions say when to name an agent; one step, WW1
priority: 5
---

## What is asked?
- Owner (2026-11-25, agent channel messages 13461 and 13472): agents answer one another without the name, so the asking agent is not woken, and in discussions they go on naming where no name is needed. "kanala baktıklarında kanal için talimatlar görseler"; "aynı durum tartışmalarda görünse iyi olur"; "ajan guide api si vardı o olabilir".

## What is there today?
- The instructions (`GET /workspace/agent-instructions`, `workspace/agent-instructions.md`) list the agent's own channels with what wakes the agent itself (`ws_agent_instructions_wake()`).
- The brief of an agent's request has one line for the asker: the answer wakes it only when it names it or its rule wakes it on replies (`ws_agent_brief_asker_lines()`).
- Nothing tells an agent how the other agents of the place wake, or that an application's words wake by a name or a reply only: the rules "every message" and the prefix never match them (`ws_agent_wake_matches()` with `$by_app`, `workspace/agent-words-wake.md`).

## What is built?
- The brief (`ws_agent_brief()`) gets a block "Who wakes here" after the place: one line for each other active agent the place reaches (`ws_agent_reach_rows()`): its `@handle`, its name, and what the reader's own words do: "wakes when you name it", or "wakes when you name it or answer one of its messages" under the rule "Replies to its messages".
- The lines are made by asking the function that decides the wake as an application writes, so the list cannot say other than what happens. A paused or not allowed agent and the reader itself are left out. Over twelve agents: the first twelve and a count.
- Claude and Pinegrap AI, where they can be asked there: one line saying an application's words never ask them.
- In a discussion the block names the agent the discussion follows (`ws_thread_follow_agent()`): a person's message there reaches it with no name; the reader's own words still wake only by a name.
- A request from a person: one line that the answer itself tells the asker, so the person is not named again.
- The instructions' writing lines (`ws_agent_instructions_writing_lines()`) get the rule once: name an agent only in a question or an order to it, or in an answer it must read; a note that asks nothing names nobody with the mark; an answer under a message wakes nobody by itself.

## What must it keep?
- No schema, no setting, no new route: text in the brief and the instructions only. The wake rules do not change (`workspace/agent-doors.md`).
- The block is text for the model: English, no key, no address, at most 1,000 characters. The instructions' version changes once with the new wording, never with a channel's list: the list is in the brief, read live at each request.
- What a person sees in the channel does not change.

## Which step? (S under 400 product lines; tests red first)
- WW1 (S): the block as a pure function over the reach rows, the followed agent and the reader; the instructions' lines; tests for each rule, a discussion, a paused agent and the cut at twelve. Memory in `workspace/` (the brief in `agent-wakes.md`, `agent-instructions.md`), `workflow/agent-channel-language.md` pointing to it, and this file deleted in the same PR.
- Claim as `[plan:agent-brief-who-wakes] WW1 — who wakes here`.
