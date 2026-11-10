---
name: workspace-app-mentioned-webhook
description: workspace.app.mentioned (PR #809): an app mentioned with <@app:N> is told through its own subscriptions only, where it may read the channel; self, assistants, expired keys, privacy, payload
---

## What does it do?
- A message that mentions an application (`<@app:N>`) queues `workspace.app.mentioned` for that application's subscriptions; an outside service answers with `POST /workspace/channels/{channel_id}/messages`, no AI code in the site (PR #809). Before it no event told an application it was mentioned.
- Once per application per message (a repeated tag counts once); an edit tells only the applications it adds (`edited: true`), as with people. People's and applications' messages (API, Claude's answers) count; system lines, guests (tags stripped) and MCP agent answers (tags neutralised) mention nobody.

## Who receives it?
- Only subscriptions whose `app_id` is the mentioned one: `api_webhook_enqueue()` asks `api_webhook_event_app_id()` → `ws_webhook_event_app_id()` (`mentioned_app_id`; missing or invalid → -1, queued for nobody). Another application subscribed to the event gets nothing; a channel group narrowing still applies on top.
- Never the writer itself. An application mentioning another is heard, `app_id` names the writer: no loop guard in the site, so a receiver should not answer every application's mention.
- Never Claude's or Pinegrap AI's application: they answer their mentions inside the site (`ws_message_ask_assistants()`). MCP agents are `@alias` text, not app tags.
- A registry agent the channel allows is woken by its tag through the registry, whatever its owner reads (agent-doors.md); this event still goes only where the owner reads the channel, and such an agent is never in the writer's cannot-read line (workspace-app-mention-unread-line.md).

## Where do the words go, and where nothing?
- The key is active, not expired (the API refuses it) and holds `workspace:read` or `workspace:write` (`ws_app_hears_mentions()`), and its owner reads the channel as the API lets it (`ws_app_reads_channel()`): public channels; private channels, guest rooms and discussions the owner is in. Never through inspection: `ws_can_read_channel()` is asked with role 3, since an inspection is open in one person's session and the API keeps none.
- Elsewhere no event at all rather than one without text: the name, existence and writers of a private channel stay inside, and the application could not answer there (404).

- The writer is told, in a line only they see (workspace-app-mention-unread-line.md, PR #8494).

## What does the payload hold?
`id`, `channel_id` (where to answer; a discussion's own id), `channel`, `channel_kind` (public, private, guest, thread), `thread` (null, or the `channel_id` and `message_id` the discussion is about, as in `WorkspaceThread`), `parent_id`, `sender_id` (0 for an application), `app_id` (the writer, 0 for a person), `mentioned_app_id`, `kind`, `text` (drawn as the owner reads it), `body` (tokens), `task_id`, `edited`; ids are ints, 0 for none (`ws_app_mention_payload()`).
- A discussion is in no group: `ws_webhook_event_channel_id()` matches it by `thread.channel_id`, so a narrowed subscription hears its channel's discussions.

## Where is the code?
- `includes/workspace/messages.php`: `ws_message_tell_apps()`, called by `ws_message_send()` (after `workspace.message.created`) and `ws_message_edit()`. Filter: `includes/api/outbound/webhooks.php`. Event text: `includes/workspace/api.php`. No schema, scope, tr.json string, MCP tool or changelog line of its own (the open version's catch-all line covers it).
- Tests: `tests/workspace_app_mention_test.php`, `tests/webhook_channel_group_test.php`.
