---
name: claude-context-reader
description: tools/claude_context.php reads a Claude Code session's used context from its newest transcript; ws_profile.php --context=auto sends it (#11871)
---

## What does it read?
- The newest `.jsonl` (by modification time) in `~/.claude/projects/<folder>/`, the folder being the working directory with every character but a letter or digit turned into a dash (`/home/user/PinegrapCMS` → `-home-user-PinegrapCMS`). `CLAUDE_CONFIG_DIR` replaces `~/.claude`.
- The last line of type `assistant` with a `message.usage`, not a sub-agent's (`isSidechain`) and not a `<synthetic>` message: `input_tokens` + `cache_creation_input_tokens` + `cache_read_input_tokens`. Output tokens are not counted. A usage summing to 0 is skipped.
- The file is read from its end: a 1 MB window that grows fourfold until a usage is found.

## How is it run?
- `php tools/claude_context.php [--cwd=DIR]` from the folder the session started in: prints the number, exit 0; nothing found prints nothing, exit 1.
- `php tools/ws_profile.php --context=auto`: replaces auto with what was read (`ws_profile_context_auto()`); nothing read drops the context; with no other field it sends nothing and exits 0.
- When an agent sends it: `agent-channel-wake.md`.

## What does it never do?
- Guess the window's size: only `used` is sent; the size is typed on the agent's form (`workspace/agent-context-size.md`).

## Where are the tests?
- `tests/claude_context_test.php`: folder name, the sum, the skipped lines, the newest file, a temporary folder, `--context=auto`.
