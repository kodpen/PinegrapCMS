---
name: workspace-discussion-follow
description: The assistant a discussion follows (ws_threads.follow_agent, step 11.0.113, decision #2419, PR #2924): who is followed, which message asks it, the no-pile-up rule, scheduled sends
---

## What is followed?
- `ws_threads.follow_agent`: '' (nobody), `claude`, `ai` or `mcp:<server id>` (an MCP server that is an agent itself). VARCHAR(64), so later agent keys fit without a MODIFY. Read and written only behind `ws_thread_follow_ready()` (`pg_schema_has()`): files land before the upgrade runs. Getter/setter `ws_thread_follow_agent()` / `ws_thread_follow_set()` (threads.php).
- Pinegrap AI asked with an MCP server's tools is followed as `ai`: later messages ask it without the server's tools.

## When is it set?
- `ws_thread_start()`: the discussion is about an assistant's answer (its app, or an MCP agent's answer found by `reply_message_id`) or a request to one (`ws_thread_message_agent()`). Answer 5 of #2419.
- `ws_message_ask_assistants()`: an agent asked by name or by replying to its answer becomes the followed one; of several, the last (`ws_thread_agents_asked()`, order Claude, then MCP agent or Pinegrap AI). Answer 3a.
- Anybody in the discussion may stop it or bring it back (answer 4a, PR #2997): workspace-discussion-follow-screen.md.
- Channels never follow: there only `@name`, `/name` or a reply to an answer asks an assistant (owner, 2026-10-21, Slack's way).

## Which message asks the followed agent?
- `ws_thread_follow_decide()` (pure): a person's message (`sender_kind = 'user'`) that asks nobody and mentions nobody (no `@` token: person, team or app; `ws_thread_body_mentions()`). Answer 1b. A message of an application or bot asks nobody and changes nothing, so agents never answer each other.
- The hooks take a `followed` argument (`ws_claude_after_send(..., true)`, `ws_ai_after_send(..., true)`, `ws_mcp_agent_after_send(..., $server_id)`) that skips only the asked-by-name check. The usual checks stay; a refusal is not written in followed mode, else every message of the discussion would carry a line.
- A scheduled message goes through `ws_message_ask_assistants()` too (scheduled_messages.php), so it follows and marks the channel's line like the writing box.

## What stops a pile-up?
- `ws_thread_follow_pending_plan()` (pure) over the agent's open requests in that discussion: one `sent` (a run took it, or an MCP request waits for approval) or one whose message asked the agent by name means no new request; queued follow-only requests are deleted once the new one is queued, so the request is always the latest message and the answer replies to it (answer 6a). Claude follows at once with this rule (2a, "with step 3").
- The brief (`ws_ai_briefing()`) adds `ws_ai_followed_sentence()` when the discussion follows that assistant; Claude's routine prompt says the same. The routine prompt is served by `/workspace/claude/instructions`, so `WS_CLAUDE_BOOTSTRAP_VERSION` (the pasted short prompt) stays.
- Claude's settings card says a followed discussion costs runs (2a).

## Where are the tests?
- tests/workspace_thread_follow_test.php: the decision (1b, 3a, colleague-only mention, bot message), the pending plan, the column guard and step, the scheduled send, the prompts. No dev DB in the session that built it: the step was not run twice.
