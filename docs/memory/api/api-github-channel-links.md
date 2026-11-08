---
name: api-github-channel-links
description: GitHub repository links of a Workspace channel (G2): repository name, link events and hook events, the hook request, X-Hub-Signature-256, delivery id, the [ci]/[pr]/[label] lines
module: github_links
---

## What is already built (no schema)?
- `includes/fn/github_links.php`, tested in `tests/github_links_test.php`: `pg_github_repo_parse()` (owner/name, or a github.com address; GitHub's owner and name rules), `pg_github_link_events()` (`ci`, `pull_request`, `label`) and `pg_github_hook_events()` (`workflow_run`, `pull_request`), `pg_github_hook_request()` (the body of `POST /repos/{o}/{r}/hooks`: web, json, TLS checked, the link's secret), `pg_github_signature_valid()` (constant-time `sha256=` HMAC of the raw body; an empty secret never matches), `pg_github_delivery_id()` (the `X-GitHub-Delivery` GUID), `pg_github_line()`.
- The table, the receiver, the hook calls and the card wait for the schema decision (link table or rows in an existing one), asked on the G2 issue.

## Which lines are written?
- `workflow_run` completed: `[ci] green|red #<n> <branch> <sha7> <workflow>=<conclusion> <run url> <title>`. Today's CI line wrote one `job=result` per job; a hook knows only the workflow's conclusion. `#<n>` is left out for a run of no pull request (a push to a branch). `success`/`neutral` are green; `failure`, `timed_out`, `action_required`, `startup_failure`, `stale` red; `cancelled`/`skipped` write nothing.
- `pull_request` labeled with a label the link follows: `[<label>] #<n> <branch> <sha7> <title>`, so `ready` gives today's `[ready]` line. Label names are folded to `a-z0-9._-`, at most 40.
- `pull_request` opened, reopened, closed: `[pr] opened|reopened|merged|closed #<n> <branch> <sha7> <url> <title>` (new prefix). Pushes (`synchronize`) and `ping` write nothing.
- Free text from a payload is one line, control characters folded, the title at most 200 characters.
