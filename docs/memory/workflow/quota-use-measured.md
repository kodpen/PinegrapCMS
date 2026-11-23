---
name: quota-use-measured
description: Where the quota goes (2026-11-19): a fixed start of 80-92k tokens, every call reading the whole context again, the time after the ready label; changes of 2026-11-19 and 11-23, what is open
---

## What was measured, and how?
- Architect (2026-11-19): on the owner's question why the week's quota ran out so fast (his guess: memory had grown too large). Read from the sessions' own records on one account: the architect's session whole, one worker whole (a small `tools/` PR), a coordinator and the merging session by their last 20 calls. The other three accounts were not read.

## Is memory the cause?
- No. Memory is 3,800 files and 12 MB, but a session reads only what it greps. A worker's first call carries 92,000 tokens before it has read anything (the system prompt, the tool definitions, `CLAUDE.md` at about 3,500); the architect's cloud session 80,000. The worker's reading of memory and code added 28,000.

## Where does the quota go?
- Every model call reads the whole context again, so use is calls times context. The architect, 17 November: 1,956 calls at 221,000 tokens on average, 448 million tokens read from the cache, about 70% of its cost; auto-compaction at 349,600 tokens, 17 times that day. Six of ten of its shell calls were plumbing: claim, answer, the waiter's restart, a PR's checks (212 calls), git.
- The worker, 92 calls and 12.7 million tokens: starting and reading 2.4 million (22 calls), the work and the wait for its checks 5.9 million, after the `ready` label 4.4 million. By price the time after the label was 48%: two wakes came more than an hour after the call before, and each wrote about 100,000 tokens into the cache again (a cached context is kept for an hour).
- A coordinator's calls ran at 200,000 to 212,000 tokens, the merging session's at 125,000 to 146,000, a worker's at the end of a product step at 174,000 to 189,000.

## What was changed on it? (architect, 2026-11-19)
- A worker ends at the `ready` label, a plan's next step goes to a new worker, and work in one file or on one screen is one task (`coordinator-worker-steps.md`, `session-lifecycle.md`).
- A bug hunt fixes what it finds in its own PR (`bug-hunt.md`).

## What changed on 2026-11-23? (#11186)
- The merging session waits on `merge_train.php --wait`, not on a 3- or 15-minute tick chain ([[merger-wake]]).
- Request waiters time out at 3300 seconds, so an idle restart reads the cached context instead of writing it again (`agent-channel-request-wait.md`).
- The architect's auto-compact window is 300k (`architect-no-hand-over.md`); a screen's look is designed in a session of its own (`design-work-session.md`); Claude Code's auto memory is off (`auto-memory-off.md`).
- The architect's `/context` that day: 322,600 of a 400,000 window, 253,900 of it messages, tool definitions 47,900 (system 25,800, MCP 22,100), `CLAUDE.md` 5,500.

## What is open?
- Owner (2026-11-19): "bağlamları 250k ile 400k arasında tutuyorum çünkü düşürürsem bağlam kopması yüzünden geliştirme kalitesi düşeceğinden endişeleniyordum özellikle mimar için." The architect's 300k is the step he took; a lower one is his to try.
- Standing sessions: one command that answers a request, waits for the next and prints its brief; a PR's checks waited for in one shell loop, never read call by call; a look only for a new screen, as one small picture.
- The fixed start: connectors and skills a development session never uses are turned off for it. The owner's hand.
- Models stay. Owner (2026-11-19): "sonet5.5 kodlama konusunda başarısız ve aynı yere geri dönme riskimizi artırır."

Related: `architect-no-hand-over.md`, `working-mode.md`, `agent-channel-wake.md`
