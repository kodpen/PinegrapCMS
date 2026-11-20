---
name: api-github-channel-lines-read
description: The GitHub application's channel lines read back into their parts, and the Activity tab's repository numbers of today counted from them (KA4, #10803)
module: github_links
---

## How is a written line read back?
- `pg_github_line_read()` (`includes/fn/github_links.php`) reads a `[ci]`, `[pr]` or label line into kind, state, number, branch, sha, check, address and title; typed text is null. A label named `ci` or `pr` is told from a CI or pull request line by the missing state word.
- A change of a line's format in `pg_github_line()` needs the reader changed too: `tests/github_links_test.php` writes every shape and reads it back.

## What does the Activity tab count?
- `pg_github_day_counts()`: pull requests opened (a reopened one counts as opened), merged, closed; checks green and red; the newest lines with their address (a label line, or a check with no run address, links to its pull request).
- `ws_channel_activity_repo()` (`includes/workspace/channel_activity_repo.php`): null without a link the reader may see; counts only the GitHub application's messages, not deleted, since local midnight, within the channel's newest 5,000 messages; at most 5 lines. It reads the `github_channel_app` service entry and never makes the application.

Related: docs/memory/api/api-github-channel-links.md, docs/memory/workspace/channel-activity-tab.md
