---
name: pr-text-generated-footer
description: A cloud session's GitHub path appends a generated-by footer to a PR text on every write; the session cannot remove it, the label is not held for it, a local path strips it
---

## What is appended, and where?
- A PR text written from a cloud session ends with a rule line and a generated-by line that links to the tool, sometimes with the session's address. The cloud GitHub path adds it on every write of the text: a PATCH over REST with a clean body comes back with the footer again (tried twice on #9563, 2026-11-15).
- A PR opened from a session on the owner's machine got the same footer by an edit in the second it was made (#9553); a later PATCH from that machine kept the text as sent.

## Is it a trace the rules forbid?
- Yes: no generated-by line and no session address in a PR text (CLAUDE.md, Fixed rules 2). The rule stands.
- It is not the session's to remove, so it never holds the `ready` label and no worker spends an attempt on it (architect, 2026-11-15, agent channel message 3558 and its answer). A session address in the text is removed by the session itself: that part it can write.

## Who strips it?
- A session with a GitHub path that is not the cloud's: the architect on the owner's machine, when a PR is named to it. It reads the PR, cuts the trailing footer and PATCHes the body from a JSON file (`gh api -X PATCH repos/kodpen/pinegrapcms/pulls/<n> --input <file>`), then reads the text back.
- After that the PR text is not edited from a cloud session again, or the line comes back; a needed change of the text is asked in the agent channel.
- Done on 2026-11-15 for #9502, #9547, #9553, #9559 and #9563.

## What about the merge message?
- The landing's merge message does not carry it (checked on the landing commit of #9549), so the footer does not reach the history. A PR text is still stripped where a path allows it: the repository is public and the text stays on the PR.
