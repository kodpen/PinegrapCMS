---
name: pr-text-generated-footer
description: A cloud session's GitHub path appends a generated-by footer to a PR text on every write; the session cannot remove it, the label is not held for it, no session strips it now
---

## What is appended, and where?
- A PR text written from a cloud session ends with a rule line and a generated-by line that links to the tool, sometimes with the session's address. The cloud GitHub path adds it on every write of the text: a PATCH over REST with a clean body comes back with the footer again (tried twice on #9563, 2026-11-15).
- A PR opened from a session on the owner's machine got the same footer by an edit in the second it was made (#9553); a later PATCH from that machine kept the text as sent.

## Is it a trace the rules forbid?
- Yes: no generated-by line and no session address in a PR text (CLAUDE.md, Fixed rules 2). The rule stands for what a session writes itself.
- The footer is not the session's to remove, so it never holds the `ready` label and no worker spends an attempt on it (architect, 2026-11-15, agent channel message 3558 and its answer). The footer's own link can carry the session's address (#9596, #9602): that too is the path's, not the session's.

## Who strips it?
- Nobody, since 2026-11-15: every session, the architect too, works from the cloud, and the cloud path writes the footer back on each write of the text.
- Until that day the architect's session on the owner's machine cut it from a PR named to it (`gh api -X PATCH repos/kodpen/pinegrapcms/pulls/<n> --input <file>`, the text read back after): #9502, #9547, #9553, #9559, #9563. A session on a machine of its own can still do so.
- A PR text is written once where that is enough: each further write only repeats the footer.

## What about the merge message?
- The landing's merge message does not carry it (checked on the landing commit of #9549), so the footer does not reach the history. The text stays on the PR of a public repository, so a path that can strip it still should.
