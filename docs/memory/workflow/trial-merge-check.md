---
name: trial-merge-check
description: Checking whether a branch or an open PR still merges into development without touching a checkout: git merge-tree --write-tree lists the conflicts; the merged tree's memory index from an export
---

## How do I see whether a branch still merges into development?
- `git fetch origin`, then `git merge-tree --write-tree --name-only --no-messages origin/development <branch or sha>`: exit 0 prints the merged tree's id, exit 1 lists the files in conflict under it.
- No checkout, index or branch is touched, so it is safe beside other sessions and for a PR that carries the `ready` label.
- An open PR whose branch is not fetched: `git fetch origin refs/pull/<n>/head` and name `FETCH_HEAD`. Two open PRs against each other: name one head in place of `origin/development`.

## How is the merged tree checked?
- The memory index: `git archive <tree id> docs tools/memory_index.php CLAUDE.md pinegrap/includes/fn | tar -x -C <scratch dir>`, then `php tools/memory_index.php --check` in that folder. All of `docs/` goes along, or the top README reads stale; `pinegrap/includes/fn` is there for the `module:` fields.
- A clash in meaning between two PRs, such as a test that counts what the other adds, shows only when the area's tests run on the merged tree (`merge-review.md`).

## Who acts on a conflict?
- Before the `ready` label the PR's own session merges `origin/development` once (`session-lifecycle.md`). After the label only the merging session settles it (`merge-train.md`): the trial merge tells whether to say so on the PR.
- A conflict only in a generated `INDEX.md` or README goes away in the train's own run of `php tools/memory_index.php`; it is no reason to touch a labelled branch.
- A PR that waits on the owner for hours drifts in the shared files (memory indexes, language shards) and looks abandoned: say on the PR what it waits for, or turn it back to draft (`CLAUDE.md`, Working mode 8).
