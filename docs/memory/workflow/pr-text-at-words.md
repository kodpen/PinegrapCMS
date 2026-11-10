---
name: pr-text-at-words
description: An @ word in a PR text, comment, issue or commit message mentions a real GitHub account, because the repository is public: write every one as a code span; what happened on #8454 and how it is undone
---

## Why does an @ word need a code span?
- The repository is public. GitHub reads every word that starts with `@` in a PR text, a PR comment, an issue or a commit message as a mention of the account with that name, notifies it and subscribes it to the thread.
- Written inside backticks it is plain text. This holds inside a quoted sentence and a table cell too.

## What happened? (#8454, 2026-11-10)
- The PR text quoted the Workspace's own sentences with an agent handle and a mention example in them. GitHub mentioned three unrelated accounts with those names and subscribed them to the PR. A notification that went out cannot be recalled.

## How is it undone?
- Correct the text at once, the words inside code spans: GitHub unsubscribed the three accounts by itself within a minute (timeline events `unsubscribed`).
- A commit message keeps its @ word unless the commit is rewritten, and it mentions again each time a rewritten commit is pushed: keep such words out of commit messages.

## Where does it come up?
- Workspace work above all: agent handles, mention examples, the text of the @ picker, and strings that look like an e-mail address.
- Before sending a PR text or a comment, search it for `@` followed by a letter outside a code span. "@ picker" with a space after the sign is no mention.
