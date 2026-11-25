---
name: agent-brief-who-wakes
description: The "Who wakes here" block of an app agent's brief and the instructions' naming, claim-first and progress lines (WW1, #11638)
---

## Why is it there?
- Owner (2026-11-25, agent channel messages 13461, 13472): agents answered one another without the name, so the asking agent was not woken, and in discussions they named agents where no name was needed. An agent now reads in each brief how the others of the place wake.

## What does the block say?
- `ws_agent_brief_who_wakes_lines()` (`agent_queue.php`, pure), after the place in the brief of a message's request (channel or discussion; not a wake or a task run). English, no key, no address, at most 1,000 characters.
- One line per other active, allowed agent of `ws_agent_reach_rows()`: `@handle` (or `<@app:N>`), its name (one line, 40 characters), and "wakes when you name it", or "… or answer one of its messages" under the rule "Replies to its messages". Each line is asked of `ws_agent_wake_matches()` with `$by_app`, so it cannot disagree with the wake; "every message" and a prefix never wake on an application's words (`agent-words-wake.md`).
- The reader itself, a paused and a not allowed agent are left out; none left: "your words wake no agent". Over twelve, or past 1,000 characters: the first lines and "N more agents are reached here".
- In a discussion that follows an agent (`ws_agent_brief_followed()`): a person's message reaches it with no name; the reader's words still wake it only as above ("follows you" when it is the reader; Claude or Pinegrap AI: "your words never ask it").
- Claude and Pinegrap AI where a person may ask them there (`ws_agent_brief_assistants()`): an application's words never ask them.
- A person's request: the answer is written under their message and tells them, so they are not named again.

## What did the instructions gain?
- Writing lines: name an agent with @ only in a question or an order to it, or in an answer it must read; a note that asks nothing names nobody with the mark; an answer under a message wakes nobody by itself unless that agent wakes on answers.
- Request step 1: claim before anything else, as the channel shows an agent working only from its claim to the close. Owner (2026-11-25, request 2951): only the architect showed as working. The progress line is a rule for work longer than a few minutes, no longer "you may".
- `instructions_version` changed once with this wording; the list of agents is never in the instructions: it is read live in each brief. What a person sees in the channel did not change.
- Tests: `tests/workspace_agent_who_wakes_test.php`.
