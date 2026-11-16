---
name: channel-commands-census-findings
description: Census of the channel commands (2026-11-16): what is not there at all (counting, a palette that runs) and four things read in the code that look wrong or unplanned
---

## What is not there at all?
- Counting: no command's use is logged or counted anywhere.
- The palette (Ctrl+K, "/" mode) only writes "/name " into the open box (`ws_palette_commands()`); it runs nothing.

## What did the census find wrong?
- A name in the table with no `case` falls into `case 'help': default:` and shows /help without a word.
- The / picker can open while a message is edited, where no command runs (`pickerCheck()` does not look at `editing`; `commandArgument()` does).
- The record drawer's "Share in a channel" sends its line through `ws_send`: a line that starts with a slash and a word is read as a command, nothing is posted, and the drawer still says "Shared in the channel.". Read in the code, not tried on a site.
- Not wrong, but nobody planned it: a saved reply whose body is a command line is run as that command when the person sends it.
