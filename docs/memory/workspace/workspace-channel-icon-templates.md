---
name: workspace-channel-icon-templates
description: A channel template's colour and icon onto a channel made from it (ws_template_channel_look()), only where the creator chose none; the form's free bi- class vs the curated list
---

## What does a template give a channel?
- `ws_template_apply()` with `new_channel` writes `ws_template_channel_look()`: the template's colour and icon, each only where the creator chose none (an unknown stored icon counts as none), the icon only when `ws_channel_icon_check()` accepts it, behind `ws_channel_colors_ready()` / `ws_channel_icons_ready()`. Applying to an open channel never changes its look.
- The template form still takes any `bi-` class (saved templates keep working); its help text says only a listed icon reaches the channel. The built-ins use listed icons (`bi-person-workspace`, `bi-globe`, `bi-calendar-event`); tests/workspace_templates_test.php holds them to the list.
- "Make a template from this channel" (`ws_template_from_channel()`) carries the chosen icon.
