---
name: auto-memory-off
description: Claude Code's auto memory (MEMORY.md) is off for this repository (2026-11-23, #11186): memory lives in docs/memory; what it cost
---

## Is Claude Code's auto memory used?
- No. `.claude/settings.json` sets `autoMemoryEnabled: false` since 2026-11-23 (#11186), so a session neither writes nor loads `~/.claude/projects/<project>/memory/MEMORY.md`.
- Why: auto memory stays on the machine that wrote it, so what one account's session learned there no other account saw, while what another account needs goes into `docs/memory` (`CLAUDE.md`). Its index also loads at every session start: on one account's machine `MEMORY.md` was about 9,000 tokens beside `CLAUDE.md`'s 3,500 (`/context`, 2026-11-23).
- Cloud sessions had none: auto memory is not carried into cloud environments.

## What happens to an old MEMORY.md?
- Nothing loads it any more. What is worth keeping from it is written into the matching `docs/memory` folder by the rules of `memory-writing.md`; the rest is left or deleted by its machine's owner.

Related: docs/memory/workflow/quota-use-measured.md
