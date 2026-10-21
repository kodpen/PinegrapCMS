---
name: workspace-assistant-reach
description: Claude and Pinegrap AI read and answer a discussion or allowed private channel their app owner is not in through the person who asked (PR #1085)
---

## Where may an assistant answer?
- Where it may be asked (`ws_claude_channel_allowed()`, `ws_ai_channel_allowed()`: public channels, private ones whose manager allowed it, a discussion as its channel) and where somebody can still write the answer: the owner of its application, otherwise the person who asked (`ws_assistant_writer()`, messages.php). The message stays the application's (`sender_kind = 'app'`); the person who asked is mentioned and told.
- Used by Claude's `/answer` and `/fail` (`ws_api_claude_channel()`, api_claude.php) and Pinegrap AI's `ws_ai_deliver()` and `ws_ai_finish_fail()`. An MCP agent's answer was already posted in the asker's name (mcp_agent.php).

## What did it fix?
- Before PR #1085 the owner had to be in the room. In a discussion (only its starter and the message's author are in it) or a private channel the owner was not in, Claude failed with "Claude yapamadı: Kanala yazamıyorum: Claude uygulamasının sahibi bu özel kanalın üyesi değil…" (API: context 404, answer 403 "The owner of Claude's application cannot post in that channel…") and Pinegrap AI with "Pinegrap AI'ın uygulamasının sahibi o kanala yazamıyor".

## How does Claude read such a place?
- `GET /workspace/channels/{id}/context` with Claude's application key: where the owner does not read the channel it is read through the person who asked, while Claude is switched on and a Claude request asked there (or a run on one of its tasks) is queued, sent or running (`ws_claude_channel_asker()`, claude.php), as that person reads it (tags, open tasks), the asker of the running request first (`ws_claude_asker_order()`). Once the request closes it is 404 again.
- Every other endpoint and every other application keep the owner's reach (`ws_api_channel_or_404()`). Pinegrap AI always read with the asker's rights (`ws_ai_channel_context($asker, …)`); only its delivery changed.

## Why is that safe?
- Only someone who writes in the room can ask, and only where the assistant may be asked: a private channel's words still reach Anthropic only after its manager allowed Claude (`claude_access`), and they appear in the routine owner's claude.ai sessions as before. The invitation is one request, not a membership.
- Never through inspection, reading or writing (role 3 is forced, as in `ws_app_reads_channel()`): the work runs in whichever screen carries it.
- Only the key used as itself is Claude (`ws_api_is_claude()`, also the queue's guard): a device or OAuth session of the chosen application is its person, else a device app wrongly chosen for Claude let every signed-in phone read such rooms (seen in the sandbox before the check).

## When is nothing posted?
- The person who asked left the channel or the discussion, or it was archived or concluded, and the owner is not in it: `/answer` 403 "The answer cannot be posted there any more: …", Pinegrap AI's request fails with the same sentence; `/fail` keeps the reason on the request only (`told_in_channel` false).

## What was checked, and how?
- Sandbox (tools/setup_sandbox.sh), routine played with the app key and curl: the bug reproduced on the old code (context 404, answer 403); with the fix the discussion and the private channel were answered and the refusal posted, the context closed with the request, another app of the same owner got 404, a concluded discussion 403. Pinegrap AI's delivery and refusal were called directly (no model). Never with a real routine: workspace-claude-routine.md.
- Tests: tests/workspace_assistant_reach_test.php, the dry-run order in tests/workspace_api_test.php.
