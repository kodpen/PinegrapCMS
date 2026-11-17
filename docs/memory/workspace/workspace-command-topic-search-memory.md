---
name: workspace-command-topic-search-memory
description: /topic (/konu), /search (/ara) and /memory (/bellek) in a Workspace channel (CC6b): what each reads, the function it writes through, its gate, live line and answer (#10277)
---

## What does /topic (/konu) do?
- `/konu Bahar lansmanı`: the channel's "What it is about" through `ws_channel_update()` with `topic` only, as the channel settings write it: its right (`ws_can_manage_channel()`) and its refusal "Only the owner of the channel can change it.". Cut to 255 characters as the function cuts it.
- The answer, to the writer alone (`ephemeral`, `ws_command_topic_html()`): "Now set: “…”", with `refresh`: the composer calls `refreshInfo()` so the header and info panel show it.
- No text: "Now: “…”", or "This channel does not say what it is about yet." with "Set it with /konu and the text." (the name in the screen's language); nothing is written.
- Live line: "Will set what it is about: “…”", warned ahead when the writer may not change it; no text: "Will show what it is about, to you alone". `where` channel: a discussion has no settings of its own.

## What does /search (/ara) do?
- `/ara fatura mart`: the box opens the search palette on this channel (scope `here`) with the words; nothing is posted and the line is never sent. `send()` of workspace.js asks `commandSearchWords(body, BOOT.commands)` (pure: the line is one of the `search` entry's names, either language, any case; "//ara" and "/aralık" are not) before `ws_send`, then `PGWsPalette.open({ scope: 'here', query })`.
- `open()` of workspace_palette.js takes `options.query` into its box and searches at once.
- The runner `ws_command_run_search()` is reached only over the API (class `preview`): it writes nothing and answers `search` with the query and channel. `search` is one of `ws_api_command_preview_types()`.
- Live line (the composer's usual ask, not the command's): "Will search #general for: “…”", or "Will open the search on #general". `where` any.

## What does /memory (/bellek) do?
- `/bellek Faturalar ayın 5'ine kadar`: an entry checked by `ws_memory_check()` and written by `ws_memory_save($channel_id, $values, 'user', $viewer_id)`, as the Memory tab's New entry writes one, with its refusals (database not updated, cannot post, title, text, tags, expiry).
- `ws_command_memory_entry()` (pure): the first line is the title, the lines after it the text. A first line over `WS_MEMORY_TITLE_MAX` (150) is cut with "…" and the whole typed text kept as the text; the live line warns of the cut.
- Typed as a reply: `ws_command_answered_message()` (else "That message could not be found.") and `ws_command_message_link()` under the text. The answer (`ws_command_memory_html()`): "Saved in the channel's memory", the title, "With the link of the message you answered.".
- No text: answer `memory_form` with `fill` (title empty, text the link if a reply). The composer empties the box, sets `memoryFill` and draws the Memory tab; `PGWsMemory.mount(pane, channel, fill)` opens New entry filled (`form(null, fill)`). API class `ws_api_command_kind_memory()`: preview without a text.
- Gate `ws_command_gate_memory()`: the memory table is there and the writer may post (`ws_can_post_channel()`), as New entry is offered. `where` channel.
- Live line: "Will remember: “title”"; no text: "Will open New entry of the channel's memory".

## Where are they offered?
- Group The channel (`ws_command_registry_channel()`), orders 975, 980, 985, after /leave and before /help.

## Tests?
- tests/workspace_command_topic_search_memory_test.php: names in both languages, /topic's writing call and refusal and the answer without a text, /search's client path before `ws_send` (Node runs `commandSearchWords()`), /memory's check before save, the reply link, the title cut, the filled form.
