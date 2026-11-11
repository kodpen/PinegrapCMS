---
name: model-assistant-site-log
description: The Site Log line of a model assistant's answer: "@handle answered a request in #channel (its name)" through ws_ai_log_answered() and ws_ai_log_line(); Pinegrap AI's own lines unchanged (#8454)
---

## How is a model assistant's answer logged?
- Its request runs through Pinegrap AI's loop (`model-assistants.md`), so `ws_ai_deliver()` writes the Site Log line of its answer too. The sentence is `ws_ai_log_answered($channel)`: Pinegrap AI's own ("Pinegrap AI answered a request in #channel") with nobody running.
- While an assistant runs (`ws_model_running()`) the sentence is `ws_model_log_answered($agent, $channel)`, the words an application agent's answer leaves (`ws_api_agent_request_answer()`): "@handle answered a request in #channel". One key for both kinds of agent.
- `ws_ai_log($what)` signs every line through `ws_ai_log_line($what)`, in brackets after the sentence: "(Pinegrap AI)", or the running assistant's name, where an application agent's line carries "(API: <application>)" (`ws_api_log()`).
- The user the line is logged under is the owner of the application the answer is written through (`ws_ai_app()`, the assistant's own while it runs).
- Only the channel answer has a sentence of the assistant's: a request from the Visual Page Editor, in a note or on a task is never an agent's (its requests are made by a message in a channel).

## What did it fix? (PR #8454)
- The assistant's answer was logged as "Pinegrap AI answered a request in #… (Pinegrap AI)". In a throwaway local install (2026-11-10, Turkish) the row read "Pinegrap AI #deneme-acik kanalındaki bir isteği yanıtladı (Pinegrap AI)" before and "@yardimci, #deneme-acik kanalındaki bir isteği yanıtladı (Deneme Asistanı)" after.

## How is it tested?
- `tests/workspace_model_agents_test.php`: both sentences and signatures, and that `ws_ai_deliver()` and `ws_ai_log()` write through the two functions. A new line the loop logs for whoever answers takes a function like `ws_ai_log_answered()`, never a "Pinegrap AI …" literal in `ws_ai_deliver()`.

Related: docs/memory/workspace/model-assistant-channel-rule.md, docs/memory/workspace/model-assistants.md, docs/memory/ui/site-log-paging.md
