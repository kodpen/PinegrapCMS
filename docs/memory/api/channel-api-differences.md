---
name: channel-api-differences
description: Where a /workspace/ route for a channel or its agents still differs from the panel action it mirrors: who may call it, what it lacks, the function it calls
---

## Who may call it?
- Own words, team devices session only, no tool (key and OAuth refused; the panel lets the person do it): `workspace.messages.update`, `delete`, `hide`, `pin`, `pin_close`, `workspace.scheduled_messages.create` and its changes, `workspace.polls.update`, `withdraw`, `again`, `workspace.messages.file.replace` (`ws_api_own_words_only()`). An agent may still edit or delete its own application's message (`ws_api_agent_own_app()`).
- Person only (a key gets 403, OAuth passes): `workspace.polls.vote`, `close`, `workspace.channels.notify`, `prefs`, `read`, `workspace.threads.join`, `leave`, `follow`, `workspace.messages.ack`, and the access and destructive writes of `channel-api-not-built.md` (`ws_api_person_only()`).
- A key writes as the application, shown as such; a team devices session writes as the person (`ws_api_writer_app_id()`); the panel always writes as the person.

## What does a route lack beside the panel?
- `workspace.channels.create`: no template; the panel applies one in the same call, the API needs `workspace.channels.apply_template` after it.
- `workspace.files.create`: one base64 file stored; the panel's attach also offers a table file for a bulk import and a product file.

## Which function differs?
- Decisions: `workspace.decisions.list` reads `ws_timeline()` over the channels the owner reads (filter `channel_id`); the channel's Decisions tab reads `ws_channel_decisions()`.
- Same gate both sides (checked 2026-11-15): archive (`ws_can_manage_channel`), memory delete (`ws_can_post_channel`), members add (post).

## And a channel's agents?
- "Add an agent": the panel shows Channel settings to a manager only; the set route takes it from whoever may post, and a private channel's new agent from its manager only (`workspace/channel-agent-rights.md`). Tier: the panel disables its choices for a non-manager, the route answers 403. Claude is never offered in the panel; the route refuses it with 422.
- The agent row gives raw facts (state, percent, resets_at); the panel draws words (`ws_channel_agent_state_words()`) the API never sends.
- Approvals: the panel form names agents for a person; over the API only a team devices session may.
- An agent's tier applies its proposals at once only on `workspace.changes.propose` with its key; through an OAuth connection or the task route they stay proposals.
- Every call of an agent's own (requests, brief, claim, answer, fail, agent-wake, agent-report, agent-instructions, approvals decide) is HTTP only, `'mcp' => false`; the brief and the instructions say so (`workspace/agent-route-words.md`).

Related: docs/memory/api/channel-api-not-built.md, docs/memory/api/api-panel-coverage-channels.md
