---
name: workspace-typed-mention
description: A mention typed by hand (@handle, @username) becomes the tag a pick from the @ list writes, when the message is written or changed: the rule, where it runs, what it leaves as typed
---

## What happens to a typed @name?
- When a channel or discussion message is written or changed, `ws_mentions_typed_channel()` (`includes/workspace/refs.php`, beside `ws_tokens_normalise()`) turns `@name` into a tag: `<@app:N>` when it is the handle of an agent the channel's @ list offers (`ws_agent_picker_entries()` over `ws_agent_reach_rows()`), else `<@user:N>` when exactly one team member has that username and may read the channel. #PRNUM.
- Names are folded as `ws_command_fold()` folds them (case, Turkish letters). A handle is looked up before a username. The rule is the one `/task` has for a typed assignee (`ws_command_typed_assignees()`): only an exact, single match counts.
- The pure part is `ws_mentions_typed($body, $agents, $people)`; tests in `tests/workspace_mentions_typed_test.php`.

## What stays as typed?
- `@herkes`, `@everyone`, `@hepsi`, `@ai`, `@claude`, an MCP alias, a model agent without an application, a username two people share, a prefix, a display name, a department's name, a person who may not read the channel (a private channel or a discussion: its members; a public one: the team).
- In a channel shared with guests no agent handle is taken (the picker offers none there).
- The boundary is `ws_message_tags()`'s: not after a letter, a digit, `@`, `.`, `<` or `-` (an e-mail address, a tag's own text). A dot or hyphen after the name stays after the tag unless the username holds it (`@ayse.yilmaz.`).
- Never inside a code span, a fenced block (`ws_code_fence_lines()`), a link's address or a bare web address.

## Where does it run?
- In `ws_message_send_check()` and `ws_message_edit_check()`, so every message writer passes it once: the panel, the API, MCP, an agent's answer, a message with a file, a scheduled message at its send.
- Not in a task's description or notes, the notes screen, the channel summary, templates or saved replies. Messages written before are not rewritten; no migration.

## Why is nobody asked twice?
- `ws_message_ask_added()` converts the old and the new text before `ws_message_tags_added()` compares them, and `ws_message_edit()` reads the old text's tokens after the conversion, so a change asks or tells only whom the old text did not name in either form.
- A typed handle woke the agent before too (`ws_agent_wake_matches()` matches `@handle` and `<@app:N>`); with the tag it still wakes once.

## What changed for people?
- A colleague's typed `@username` now gets what a picked mention gets: the mention line, the notification and the `ws_refs` row. Before #PRNUM a typed name was neither drawn nor rung, and an agent's API message showed `@handle` as plain text while the line under it said the agent was asked (issue #9985, agent channel on dev, 2026-11-16).
- An API reader sees the tag in `body` and `@handle` in `text` (`ws_plain_text()` with the refs' labels from `ws_app_tag()`).
