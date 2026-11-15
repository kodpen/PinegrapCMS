---
name: channel-profile-cards-census-scope
description: PF1 census of channel-profile-cards: the card's look (no popover in the design sheet), the exact files and functions of PF2 to PF7, and further facts for the owner's poll
---

## What does the design sheet offer for a card?
- No popover or hover card: the sheet (`ui/look-design-examples.md`) draws type, buttons, pills, icon tiles and initials, fields, cards and rows; Workspace is on its "Not drawn yet" list.
- The drawing for PF3 builds the card from `.pg-card` with `--pg-surface-float` and `--pg-shadow-float`, `.pg-title-status`, `.pg-text-meta`, `.pg-pill` tones and `.pg-tabular`; the bar of a window is new. A new part: its pictures go to the owner in PF3.
- Bootstrap 6's popover is the carrier (`ui/panel-hint-tooltip-or-popover.md`): hover, focus, a tap; Escape closes; one open at a time.

## What does each step touch?
- PF2: `includes/workspace/channel_agents_panel.php` (`ws_channel_member_agent()` adds role key, model, windows, report age and stale, state, waiting, usage_high), a new pure `ws_agent_card_state()` and the grouped request count; `ws_app_sender()` adds `agent_id` for an agent's application; `api_agents.php` `ws_api_channel_agent_present()` and the API schema; tests `tests/workspace_agent_card_test.php`.
- PF3: `assets/js/workspace.js`: `agentCard()` on the message face and name, `agentRow()` and the head's faces; style in `assets/css/backend.src.css` beside `.ws-agent-face` (the landing builds the bundle); the guest page untouched.
- PF4: the message meta: role badge in place of "(application)" (the name without the suffix for an agent), state dot on `.ws-msg-avatar`.
- PF5: `ws_agent_picker_entries()` meta gains role and state; `drawPicker()` draws the dot and the mark.
- PF6: a panel action `ws_person_card` (people.php reader), `personCard()` in workspace.js beside `personMenu()`.
- PF7: `workspace_channel_agents.js`, `ws_channel_agent_panel_row()` (role, state from PF2), `agents_settings.php` tier and wake words; texts with `tools/tr_add.php`. PF7b: `channelForm()` icon, colour, switches.
- AS1 and AS2 hold `agent_instructions.php` and `agents_settings.php`: PF7 waits for AS2 or says so in the channel.

## Which further facts could the card carry? (one poll for the owner)
- The channels the agent is in, as links.
- Its last answered request: when, in which channel.
- Requests answered today and failed today.
- Its tier and what wakes it here, in plain words (from PF7).
- Who added it to the channel and when (`added_by`, `added_at`).
- For a person: local time and "in working hours now", open tasks given to them, the department lead.
- A "Message" or "Mention" button on the card.
