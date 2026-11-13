---
name: workspace-card-long-words
description: Task card in a channel and template card in the New channel drawer wrap a long word or link (overflow-wrap anywhere)
---

## How does a long word or link wrap in a workspace card?
`.ws-task-card` (the task card posted in a channel) and `.ws-tpl-card-text` (the text column of a template card in the New channel drawer) set `overflow-wrap: anywhere`, like `.ws-msg-body`. A title or description without spaces wraps inside the card instead of running past its border. Fixed in issue #9044.

## What does a new workspace card need for long text?
A card drawn beside the message body does not inherit its wrapping: give its text box `overflow-wrap: anywhere`, and `min-width: 0` when it is a flex or grid child.
