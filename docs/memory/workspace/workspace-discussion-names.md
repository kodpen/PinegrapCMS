---
name: workspace-discussion-names
description: How a Workspace discussion (ws_channels kind thread) is named outside its panel: bell lines name its channel via ws_inbox_place(), its writing box says "Reply in the discussion" (#5187)
---

## Why is a discussion not named like a channel?
- A discussion is a `ws_channels` row of kind `thread` whose `name` is its title. Text written for channels ("mentioned you in #x", "Write to #x") used that title as a channel name and never said which channel the discussion was in (#5187).

## How does a bell line name it?
- `ws_inbox_describe()` takes its place from `ws_inbox_place($channel, $parent)` (pure, `includes/workspace/notify.php`): a channel is `#name`; a discussion is `#<parent channel>` with `thread => true` (parent from `ws_thread_parent_channel()`), an empty name if that channel is gone.
- With `thread` set, mention and read-receipt lines use their own keys: "{var:1} mentioned you in a discussion in {var:2}", "{var:1} asked you to say you read a message in a discussion in {var:2}". Other lines that print `$channel_name` get the parent channel.

## What does the discussion panel's box say?
- `composer()` in `workspace.js`: `self.isThread` (the discussion panel view) uses `thread_composer_placeholder` ("Reply in the discussion"); a channel keeps "Write to #name". The box's accessible name follows.

Test: `tests/workspace_thread_names_test.php`.
