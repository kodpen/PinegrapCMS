---
name: memory-index-key-rules
description: The hand-written key-rules block of each memory folder README, what tools/memory_index.php keeps and regenerates, when it stops with exit 1 and how a merge conflict in a README is settled
---

## What is the key-rules block and who writes it?
- The few rules every task in a memory area follows, written by hand between `<!-- key-rules:start -->` and `<!-- key-rules:end -->` in each `docs/memory/<folder>/README.md`, under `## Key rules`. 5-15 bullets, each naming the topic file that explains it.
- Added in PR #1052, when `.claude/skills` was folded into memory (`owner-rules.md`, "Where do the area rules live?"). A rule that changes is edited in the block and in its topic file together.

## What does tools/memory_index.php keep, and what does it regenerate?
- It keeps the block verbatim (blank lines at its edges trimmed). It regenerates everything else: the heading, the folder description (from `FOLDERS` in the tool), the `## Files` list from each file's frontmatter, and the whole top index `docs/memory/README.md`.
- A folder whose README does not exist yet gets an empty marker pair; the `## Key rules` heading appears only once the block has text.

## When does it stop with exit 1?
- "no single key rules block": a README without exactly one start line followed by one end line, usually an older copy taken during a merge. Take the file from `development` (`git checkout origin/development -- <file>`) and run again, or delete it if the folder has no key rules yet.
- "a merge conflict inside the key rules": conflict markers inside the block. Settle the block by hand (keep both sides' rules), then run again.
- Also, as before: a memory file over 4 KB, missing or invalid frontmatter, `name` not equal to the file name, a file outside a known folder. With `--check` (CI) a README that differs from what the files produce fails too.

## How is a README conflict settled after a merge?
- Conflict only in the file list: run `php tools/memory_index.php`; it rewrites the README and the conflict is gone.
- Conflict inside the block: merge the rules by hand, then run the tool.
- Never resolve a folder README with `--theirs` from a branch whose README has no block, and never delete a README to regenerate it: the tool rebuilds it with an empty block and the folder's rules are lost. Restore the file from git instead, then check each folder README's block is intact. Taking one side's README whole drops the other side's key rule (#1382 lost one this way, restored before merging).

Related: docs/memory/workflow/git-branches-and-shared-files.md
