---
name: memory-mirror-tool
description: tools/memory_mirror.php (MS4, #PR): docs/memory mirrored one way into the general memory of the agent channels; what a record holds, the mirror-<hash> tag, the listing, dry run, --full, limits
---

## What does it do?
- `php tools/memory_mirror.php [--dry-run] [--full] [--root=PATH]` writes each memory file as a record of the general memory (channel_id 0, `workspace/channel-memory-general.md`) through the external API, with the key of `PG_WS_SITE`, `PG_WS_KEY`, `PG_WS_SECRET` (`tools/ws_client.php`, as the `ws_*` tools). The application's owner must be able to post in an agent channel, or the routes answer 403.
- One way: git stays the record (`decisions-made-workspace-49.md`). After a reset of dev one run fills the memory again. Who runs it after a landing is the merging session's rule, written by the architect.

## What is a record of a file?
- Project the folder (`workspace`), title the file name without `.md`, text the body without the frontmatter (line ends `\n`, blank lines around it trimmed), tags the frontmatter's `module:` names (one word, at most 32 characters, nine at most) and last the tag `mirror-<10 hex>`.
- Read: `<folder>/*.md` one level deep, a folder's README.md (its key rules) included; the generated `INDEX.md` and `DECISIONS.md`, the root README.md, deeper folders and pictures are not. 4879 files on 2026-11-26.
- A body over 4000 characters (the record's limit) is cut with the closing line `[Cut; the whole file: docs/memory/<folder>/<file>]` inside the limit; five files on 2026-11-26.

## How does it know its own records and what changed?
- The tag `mirror-<hash>`: the first ten hex digits of the SHA-1 of project, title, text and module tags. A record without it is never touched, so agents' own records under the same project stay.
- The plain list stops at 500 records and carries no text, so a run lists with `search=mirror-` (`workspace/channel-memory-search.md`), 50 a page through `next_cursor`, then compares the hash tag: no text is read.
- A file with no record is created (an Idempotency-Key from the file and hash, so a retried create is not doubled); a record whose tag differs is updated; a record whose file is gone, or a second record of one file, is deleted. A failed listing writes nothing; no file found under the root exits 2 before anything is listed.
- `--full` updates every mirrored record: the site writes only what differs (`workspace/channel-memory-note.md`), so a record changed by hand on the site is put back. An edit by hand keeps its tag, so a plain run leaves it until the file changes.
- `--dry-run` prints `create <file>`, `update #id`, `delete #id` lines and their counts, writing nothing.

## What does a run cost?
- Every call counts against the application's rate limit (`api/external-api-iis-environment.md`, 120 a minute by default); a 429 is waited out (Retry-After, else 30 s) and asked again, ten tries. A first fill of about 4900 creates takes about 40 minutes at the default.
- Each write of the general memory adds a line to the "General memory updated" note of every agent channel; the burst rule keeps it to notes of 3000 characters, so a first fill leaves many notes there.

## How is it tested?
- `tests/memory_mirror_tool_test.php`: frontmatter split, the cut, the record and its tag, which files are read, the plan, the 429 retry. The API calls themselves were not run against a site in its PR.
