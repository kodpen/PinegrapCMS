# Pinegrap CMS — Claude context

Loaded in every session, so it holds only the rules that apply to every task.
Everything else is in memory files, opened only when needed.

## Memory

- Index: `docs/memory/README.md` lists the memory folders and the other
  documents under `docs/`. Each folder's `README.md` starts with the area's
  key rules, then lists its files one line each. Read the folder README the
  task needs (table below), follow its key rules, then open only the files
  the task needs.
- Changes are kept as memory; there is no change log. When something must be
  remembered, or a PR changes behaviour (what it does now, why, the symptom it
  fixed, the PR number), add or update a file under
  `docs/memory/<folder>/`: English, one topic per file, at most 4 KB, written
  as questions (`## …?`) with short answers, starting with frontmatter:
  `---` / `name: <file name without .md>` / `description: <one line>` / `---`.
  Stale information is deleted or corrected, never kept beside a "now" note.
- Plans are memory files in `docs/memory/plans/`: remaining work, decisions
  made, open questions, where to start. When a plan is finished, its lasting
  decisions move to the topic folder and the plan file is deleted.
- The indexes are generated: run `php tools/memory_index.php` after adding,
  renaming or deleting a file and commit the result. Never edit or merge a
  file list by hand; CI runs `--check`. Only a folder's key rules are written
  by hand, between its `key-rules:start` / `key-rules:end` marker lines; the
  tool keeps them. After a merge conflict in an index, run the tool again.

## Memory folders — read before the matching work

Read `docs/memory/<folder>/README.md` first. When you start a sub-agent,
write that path into its task.

| Work | Folder |
|---|---|
| SQL, reading tables/columns, SQL traps | `data/` |
| High-volume counters, rollups, visitor statistics, sitemap/noindex | `visitors/` |
| A migration step that changes the schema, versions, changelog | `release/` |
| New file, function, folder or library; user-facing text (`lang()`, `_sdT()`, language shards); JavaScript twins and bundles; CSRF, escaping, secrets, file integrity; roles and permission columns | `core/` |
| Redirects, server rules, update/TLS, uploads, images, cookie consent | `platform/` |
| `waf.php`, rate limits, IP lists, security headers | `waf/` |
| Panel screen, toolbar, form, settings modal, panel JavaScript | `ui/` |
| Visual designer, system widget, data binding, palette component, designer content roles | `designer/` |
| External API, webhooks, OAuth, MCP, connected accounts | `api/` |
| Orders, cart, checkout, products, marketplaces | `commerce/` |
| ERP (accounting) module, e-documents | `erp/` |
| Workspace module and its endpoints | `workspace/` |
| An audit, or proposing "dead code / cleanup" | `workflow/` (`decisions-made.md`) |

Read `docs/memory/workflow/decisions-made.md` **before** writing any audit
finding: it lists decisions already made.

## Project in two lines

PHP CMS (PHP 7.1–8.5, MySQL/MariaDB via `mysqli`, Bootstrap 5 + jQuery). Code
lives under `pinegrap/`; paths in memory are relative to it. Open
version **2026.11.0**; details in `docs/memory/release/release-status.md`.
Version numbers are `YYYY.M.PATCH` from 2026.11.0 on (2026.11.0, 2026.11.1,
2026.12.0; month without a leading zero, three parts always):
`docs/memory/release/version-numbering.md`.
Branches: work from `development`, PRs to `development`, never to `main`.

## Claim work before starting it

Several sessions work on `development` at once, and a plan file shows a step
as taken only after its PR merges, so work is claimed on GitHub first: with a
draft PR, never an issue. Issues are not used between sessions at all (no
claim, hand-off, decision request or follow-up issue): what a session has to
say goes in its PR, the plan file or its own reply. Use REST
(`gh api repos/kodpen/pinegrapcms/…`); `gh issue`, `gh pr` (GraphQL) and the
search API are blocked in cloud sessions.

1. **Check.** Before starting a plan step or any task that ends in a PR, list
   the open PRs, drafts included:
   ```bash
   gh api 'repos/kodpen/pinegrapcms/pulls?state=open&per_page=100' --jq '.[]|"#\(.number) \(if .draft then "draft" else "open" end) \(.head.ref) \(.title)"'
   ```
   Open each one on the same plan file or area (`…/pulls/<n>/files` lists a
   PR's files). If another PR holds the same plan file (any step) or the same
   files, do not start, even if it looks stale: tell the owner which PR holds
   it. Steps of one plan run in parallel only when the owner assigns them so.
   The queue limit (Working mode, rule 8) is checked here too.
2. **Claim.** Branch from `origin/development`, push your first commit (the
   failing test, or the first slice) and open a **draft** PR to `development`
   titled `[plan:<plan file name>] <steps> — <topic>` (`[iş] <topic>` outside
   a plan); its What section names the plan step and the files or folders it
   will touch. The draft is the claim: it is in the list every session checks,
   it does not count against the queue limit, and CI does not run on it until
   it is marked ready. Then list the PRs again: if a lower-numbered draft on
   the same plan or files appeared meanwhile, close yours and stop. Small
   fixes, memory-only edits and reviews skip the draft, never the check.
3. **Finish.** The PR that finishes the steps marks them done in the plan
   file (each step with its PR number, the status in `description`; after the
   last step the plan is finished, see Memory). Push the finished work, then
   mark the draft ready (`gh pr ready <n>` on a machine; in a cloud session
   `mcp__github__update_pull_request` with `draft: false`): CI runs on that,
   and the PR counts in the queue from then on. When it is green, Working
   mode rule 2.

## Working mode (2026-10-25)

About fifteen sessions on four accounts share one merge queue. Quota spent
waiting on it, refreshing PRs and re-running checks is quota not spent on
work, so these rules bind every session (they are the sessions' working
arrangement, not an owner decision: Fixed rules, 6). Details:
`docs/memory/workflow/working-mode.md` and `session-checks.md` (every
session), `merge-train.md` and `merge-train-landing.md` (the merging
session).

1. **One open PR per session; no stacked branches.** Branch only from
   `development`, never from an open PR. Start the next task after your PR is
   merged or handed over; a plan's next step waits for the previous step's
   merge.
2. **Hand over when green.** CI green on your head and nothing left for you:
   put the `ready` label on the PR
   (`gh api -X POST repos/kodpen/pinegrapcms/issues/<n>/labels -f 'labels[]=ready'`;
   the merge order, if it has one, goes in the PR body), cancel your
   check-ins and wait, still subscribed to the PR's activity. From then on do
   not merge `development` into it, rebase it or re-run its checks; only the
   merging session keeps it current. Act on it again only when that session
   asks on the PR; its comment wakes you, and so does the merge (rule 6).
3. **Only the merging session merges**, one session (a backup merges only
   when the owner says so). It merges in merge windows as a merge train
   (`merge-train.md`): the PRs merged onto `development` with one merge
   commit each, tested once, landed by one fast-forward push
   (`merge-train-landing.md`). An open `merge-train/*` PR is the lock: one
   train at a time, never a train built on an open train. It reviews each PR
   once and fixes only blocking findings (`merge-review.md`). No other
   session merges, also not its own PR. A PR that changes only `docs/`,
   `CLAUDE.md` or `.github/` lands alone as soon as it is green, outside the
   windows: every new session reads the rules from `development`.
4. **Light by default.** Do the task in this session: no multi-agent review
   chains, no sandbox or browser run unless a runtime path cannot be covered
   by a unit test (say why in the PR). Locally run the tests of what you
   changed; `git push` runs the fast checks; CI runs the full suite and the
   static analysis. If you run `tools/static_analysis.sh`, give it your own
   cache folder (`PG_ANALYSIS_TOOLS=<your scratch dir>`): sessions on one
   machine share `~/.cache/pinegrap-analysis` and overwrite its report.
5. **Small PRs**: about 1,000 product lines or fewer (changed lines in files
   under `pinegrap/`, not counting `.min.js` bundles and language files;
   tests, tools and memory never count). A bigger feature is a plan whose
   steps are cut to that size when the plan is written, separate PRs, one at
   a time. A finished PR over the limit still lands, in a train of its own;
   the split is asked of the next step, not of the PR.
6. **On to the next step when your PR lands.** When GitHub closes your PR
   as merged, check the queue limit (rule 8). Under it, take the next step
   of your plan that no open PR holds (Claim work, steps 1-2); with no plan,
   no free step or the queue over the limit, end. At most three steps in a
   row, then hand over through the plan file and end: a long session pays
   for its whole context on every wake. At most one safety check-in, never
   polling.
7. **Shared hot files**: a PR does not edit `changelog.txt`; its body names
   the `[NEW]` line or the `[SCHEME]` item and the merging session writes it
   when it lands the PR. Translation keys go in with `php tools/tr_add.php`
   (sorted shards in `includes/local/tr/`: `docs/memory/core/lang-files.md`).
   Generated memory indexes, `.min.js` bundles and
   `docs/memory/core/fn-module-map-additions.md`: append only; the merging
   session settles their conflicts (indexes and bundles by rebuilding them).
8. **Queue limit.** While more than 15 PRs are open, drafts and
   `merge-train/*` PRs not counted, no session starts a new task. The check
   is made before the draft PR that claims the task, before any work; a
   session whose work is done still marks its PR ready. About 6 to 8 working
   sessions at a time.
   `gh api 'repos/kodpen/pinegrapcms/pulls?state=open&per_page=100' --jq '[.[]|select(.draft|not)|select(.head.ref|startswith("merge-train")|not)]|length'`

## Fixed rules

1. **Code comments in English, no exceptions** (PHP, JS, CSS, SQL, comments
   inside strings). Translate Turkish comments in files you touch. Language
   file values and `lang()` strings are not comments.
2. **No AI or process trace** in comments, commits, PR bodies, PR comments or
   merge messages: no "user decision", "per feedback", phase numbers, plan or
   session references, `Co-Authored-By: Claude`, `Claude-Session:`,
   "Generated with Claude Code" or claude.ai links. A footer GitHub adds to a
   PR body or comment is cleaned by the session that merges the PR; others do
   not spend turns on it (`docs/memory/workflow/pr-footer-and-trailers.md`).
   Commit author `Erdal Güral <erdaltyy@gmail.com>`. Comments say what the
   code does and the technical why; decision history goes to memory.
3. **Every user-facing text is translated:** `lang('English text')` in PHP and
   the panel, `_sdT('English text')` in the designer (single-quoted literal).
   The English source is the key and must exist in the Turkish shards
   (`includes/local/tr/`): add it with `php tools/tr_add.php "English" "Türkçe"`,
   never by hand.
4. **New file:** Pinegrap header block (`Pinegrap`, not `PineGrap`); only
   URL-called files sit at the root, the rest under `includes/` with the gate
   constant; new functions in `includes/fn/<module>.php`, not `functions.php`.
5. **Schema changes only through a migration step** in the open version, with
   the idempotent helpers (`install_add_column()` family); never
   `db("ALTER TABLE …")`. Ask before any ADD/CREATE/DROP/MODIFY/CHANGE.
6. **Owner decisions bind, and only the owner makes them.** An owner decision
   is about the product's behaviour, taken so that the global usage structure
   and the sites already running do not break, and it comes with its reason;
   it is recorded in `docs/memory/workflow/decisions-made.md` with that
   reason. Places repeatedly reported as "dead code", "unused field" or
   "missing check" are listed there; leave them. If you think one is wrong,
   ask the owner in your reply before changing it; a question never becomes
   an issue. Rules on how sessions work (Working mode, the workflow memory)
   are the sessions' own arrangements: never written as the owner's decision,
   and changed by a PR that gives its reason.
7. **Four silent traps:** table names are singular (`page`, `style`,
   `pregion`, `forms`) and a failed query returns `false` without throwing
   (keep `mysqli_report(MYSQLI_REPORT_OFF)`); prices are integer minor units,
   round with `round()`, never truncate with `(int)`; edit `.src.js` **and**
   serve `.min.js` — after editing a bundled script (`pg_js_bundles()`,
   including `workspace*.js`) run `php tools/build_js.php`; in the role
   hierarchy Designer (1) is above Manager (2).
8. Talk to the owner in Turkish. One task is one small PR. User documentation
   is never written in the repo (see `docs/memory/workflow/owner-rules.md`).
9. **`pinegrap/changelog.txt` is English and lists only major features.** Only
   a plan-driven major feature gets its own one-line `[NEW] Added …` entry.
   Every other change is covered by the open version's single
   `[FIX] Miscellaneous bug fixes and new features.`,
   `[SECURITY] Implemented various security fixes.` and one short
   `[SCHEME] …` line, never one line per fix. A PR does not edit the file:
   its body names the line or the item and the merging session writes it
   when it lands the PR (Working mode, rule 7).
   Layout and order: `docs/memory/release/upgrade-changelog-rules.md`.

## When a task is done

CI (`.github/workflows/php-checks.yml`) runs all of these; none needs a database.
In a session run the ones your change touches and the tests of your area
(Working mode, rule 4); the merging session and CI run the rest.

```bash
php tools/lint.php              # php -l over the product tree
php tools/check_lang.php        # lang()/_sdT() keys against the language shards
php tools/check_bindings.php    # designer dropdown tokens against renderer
php tools/check_api_schema.php  # external API fields against the schema
php tools/test.php [filter]     # tests/*_test.php (pure functions)
php tools/check_copies.php      # only the intended get_file.php copies
php tools/build_js.php --check  # .min.js bundles match their sources
php tools/build_code_editor.php --check  # code editor bundle matches tools/code_editor/
php tools/memory_index.php --check  # memory index matches its files
bash tools/static_analysis.sh   # PHPStan level 0 + PHP 7.1+ compatibility against their baselines
```

The last one fetches the pinned PHPStan and PHPCompatibility once (no
Composer); a newer API used behind a guard gets a `phpcs:ignore` with the
reason, never a baseline entry: `docs/memory/workflow/static-analysis.md`.

`git push` runs the fast ones on the pushed commit first and stops a push
that fails them (`tools/pre_push.sh`: `docs/memory/workflow/pre-push-checks.md`).
A cloud session that starts in this clone has the hook; if your context lacks
the line "git push runs tools/pre_push.sh first", run
`bash tools/install_git_hooks.sh` once before pushing. Change a branch only
with `git push` (API and MCP file writes skip the check); fix a failing
commit and push again, `--no-verify` is only for a broken script.

Also: a test in `tests/<topic>_test.php` for a pure function you add or change
(red first when behaviour changes); a schema step rerun twice on a dev DB;
`node --check` after touching `style_designer.js`. The PR says what changed,
why, and what could not be verified (say so when no running instance was set
up). A plan step's PR marks the step done in the plan file (Claim work before
starting it, step 3).
