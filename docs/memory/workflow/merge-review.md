---
name: merge-review
description: Owner rule (2026-10-25): the merging session reviews each PR once, fixes only blocking findings, lists minor ones in a follow-up issue, digs deeper only for access, payments, SQL, schema
---

## How deep is the review before a merge?
- One reading of the PR's diff and the files it shares with `development` and the other PRs in the train, by the merging session itself. No reviewer fan-out, no refuter rounds. In one day the old "two lenses, three refuters" review cost about 26 million sub-agent tokens for 13 merged PRs and hit the session limit three times, while more than 17 new PRs opened.
- Deeper verification (a second reader, or a refuter for a blocking finding) only for a PR that touches access control or roles, payments, SQL that builds queries from input, or the schema.

## Which findings are fixed before merging?
- Blocking ones only: a security hole, data loss, a wrong result on a real path, a broken install or upgrade, red CI. Fix it on the branch (small) or send the PR back (below).
- Everything else (naming, style, a missing test for an edge case, a memory wording) goes to the follow-up issue: one open GitHub issue titled "Merge review follow-ups", one comment per PR listing its points with file:line. A later session takes items from there as its own small PR.

## Who fixes a PR that is sent back?
- The session that opened it is usually gone (one task, one session). A small blocker (a few lines, clear fix) is fixed by the merging session on the branch itself before the train. A larger one gets one comment on the PR naming it, the PR stays out of the train, and the owner or a new session takes it with a claim.

## What else stays the merging session's job?
- The PR body cleanup and footer removal (`pr-footer-and-trailers.md`), the squash title and message, closing claims, the `changelog.txt` lines (`merge-train.md`), and marking the plan steps a PR finishes when its author could not write the PR number.

Related: docs/memory/workflow/merge-train.md, docs/memory/workflow/working-mode.md
