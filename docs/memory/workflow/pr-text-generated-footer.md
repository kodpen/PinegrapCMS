---
name: pr-text-generated-footer
description: A cloud session's GitHub path appends a generated-by footer to a PR text on every REST write; one update through the GitHub MCP tool stores the text clean; the label is never held for it
---

## What is appended, and where?
- A PR text written from a cloud session ends with a rule line and a generated-by line that links to the tool, sometimes with the session's address. The cloud GitHub path adds it on every write of the text over REST (`gh api`): a PATCH with a clean body comes back with the footer again (tried on #9563, 2026-11-15; on #12097 and #12086, 2026-11-27). `mcp__github__create_pull_request` adds it too.
- `mcp__github__update_pull_request` with the full text stores it as given (#12097, #12086, read back clean, 2026-11-27). How each tool behaves: `pr-footer-and-trailers.md`.
- A PR opened from a session on the owner's machine got the same footer by an edit in the second it was made (#9553); a later PATCH from that machine kept the text as sent.

## Is it a trace the rules forbid?
- Yes: no generated-by line and no session address in a PR text (CLAUDE.md, Fixed rules 2). The rule stands for what a session writes itself.
- The footer never holds the `ready` label and never holds a PR in the queue, and no worker spends more than one write on it (architect, 2026-11-15, agent channel message 3558 and its answer; said again 2026-11-27 for #12101). The footer's own link can carry the session's address (#9596, #9602): that too is the path's, not the session's.

## Who strips it?
- The session that opened the PR may, with one `update_pull_request` carrying the same text, where it has that tool; it need not. The merging session cuts what is left when it lands the train (`pr-footer-and-trailers.md`; it did so for #12101, 2026-11-27).
- Never with `gh api`: each such write only repeats the footer, also on a text that was clean.
- A session without the tool writes its text once and leaves the footer.

## What about the merge message?
- The landing's merge message does not carry it (checked on the landing commit of #9549), so the footer does not reach the history. The text stays on the PR of a public repository, which is why it is still cut where a path can.
