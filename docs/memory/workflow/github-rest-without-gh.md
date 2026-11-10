---
name: github-rest-without-gh
description: GitHub from the owner's machine, where gh is not installed: the token from git credential fill, a REST helper, GraphQL for draft and ready, the ready label, reading CI, mergeable_state unstable
---

## How is GitHub reached without `gh`?
- `CLAUDE.md` writes its steps as `gh api …`; on the owner's Windows machine `gh` is not installed and no `GITHUB_TOKEN` is set. `printf 'protocol=https\nhost=github.com\n\n' | git credential fill` prints the token git push uses as `password=…`.
- A small helper in the session's scratch folder (Python `urllib`, or PowerShell `Invoke-WebRequest`) sends it as `Authorization: Bearer …` to `https://api.github.com/<the path after gh api>`. It never prints the token, and nothing writes it to a file.
- Requests from there do not pass the cloud proxy: a body written this way gets no footer (`pr-footer-and-trailers.md`).

## Which calls differ from the `gh` lines?
- Open PRs: `GET repos/kodpen/pinegrapcms/pulls?state=open&per_page=100`, the next page while a page is full; the files of one: `pulls/<n>/files`.
- A PR: `POST repos/kodpen/pinegrapcms/pulls` with `title`, `head`, `base`, `body` and `draft`. The `ready` label: `POST repos/kodpen/pinegrapcms/issues/<n>/labels` with `{"labels":["ready"]}`.
- REST cannot change `draft`. Ready: GraphQL `markPullRequestReadyForReview(input:{pullRequestId:…})`; back to draft: `convertPullRequestToDraft`; both take the PR's `node_id`.
- CI of a head: `GET repos/kodpen/pinegrapcms/commits/<sha>/check-runs`. A draft gets no run (`php-checks.yml` skips drafts), so a PR opened as a draft shows nothing until it is ready.

## What misleads after the `ready` label?
- For a few minutes `mergeable_state` reads `unstable`: the label starts the `agent-channel.yml` job "report the hand-over to the agent channel" (`continue-on-error`), which is no check of the PR.
- A missing label is no gate: a green PR with no push for an hour is taken by a train anyway (`merge-train.md`). A PR whose green head must not land is turned back to draft at once.

## Which traps does Windows add?
- A JSON body goes from a file, written and read as UTF-8: Turkish letters typed inline through bash and curl gave "Problems parsing JSON", and a body typed in a Git Bash heredoc lost its backslashes.
- Printing GitHub's JSON needs UTF-8 output (`python -X utf8`, or `sys.stdout.reconfigure(encoding='utf-8')`), or Turkish letters look broken although they are stored right.
- A file list printed by Python ends its lines in CRLF: strip `\r` before comparing with git's list.
