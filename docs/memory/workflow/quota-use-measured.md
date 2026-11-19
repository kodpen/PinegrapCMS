---
name: quota-use-measured
description: Where the accounts' quota goes, measured 2026-11-19 in session records: a fixed start of 80-92k tokens, every call reading the whole context again, the wait after ready; what would lower it
---

## What was measured, and how?
- Architect (2026-11-19): on the owner's question why the week's quota ran out so fast (his guess: memory had grown too large). Read from the sessions' own records on one account: the architect's session whole, one worker whole (a small `tools/` PR), a coordinator and the merging session by their last 20 calls. The other three accounts were not read.

## Is memory the cause?
- No. Memory is 3,800 files and 12 MB, but a session reads only what it greps. A worker's first call already carries 92,000 tokens before it has read anything (the system prompt, the tool definitions, `CLAUDE.md` at about 3,500); the architect's cloud session 80,000. The worker's reading of memory and code added 28,000 in its first six minutes.

## Where does the quota go?
- Every model call reads the whole context again, so use is calls times context. The architect, 17 November: 1,956 calls at 221,000 tokens on average, 448 million tokens read from the cache, about 70% of its cost; auto-compaction only at 349,600 tokens, 17 times that day.
- Six of ten of the architect's shell calls were plumbing: claim, answer, the waiter's restart, reading a PR's checks (212 calls), git.
- The worker: 92 calls, 12.7 million tokens, of which 7.7 million after its work was done, in wake-ups while it waited on checks, the merge and messages at 142,000 to 170,000 tokens.
- A coordinator's calls ran at 200,000 to 212,000 tokens, the merging session's at 125,000 to 146,000.

## What would lower it? (proposed 2026-11-19; none is in force until it is written into the working rules)
- A smaller compaction window in every session (`/autocompact 150k`, or `CLAUDE_CODE_AUTO_COMPACT_WINDOW` in the environment; from the Claude Code documentation, not tried): the average context about halves. The owner's hand.
- A worker ends at the `ready` label and the next step goes to a fresh worker. This changes Working mode 2 and 6.
- Standing sessions: one command that answers a request, waits for the next and prints its brief; a PR's checks waited for in one shell loop or by its event, never read call by call; a look asked only for a new screen, as one small picture.
- The fixed start: `/context all` in a worker shows what fills it; connectors and skills a development session never uses are turned off for it. The owner's hand.
- The model and the effort per role (the architect ran on the highest model at effort `xhigh`; the workers, the coordinator and the merging session on Opus): the owner's choice.

Related: `architect-no-hand-over.md`, `working-mode.md`, `agent-channel-wake.md`
