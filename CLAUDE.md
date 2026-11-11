# Pinegrap CMS — Claude context

Loaded in every session on every turn, so it holds only what every task
needs. The detail is in memory files, opened when a task needs them.

## Memory

- `docs/memory/<folder>/README.md` holds the area's key rules: read the one
  your task needs (table below) and follow them. Its `INDEX.md` lists the
  folder's files one line each: search it, never read it whole
  (`grep -i 'word' docs/memory/<folder>/INDEX.md`, or
  `docs/memory/*/INDEX.md`), then open only the files that match. Give a
  sub-agent the README's path.
- What must be remembered, and what a PR changes (the behaviour now, why,
  the symptom it fixed, the PR number), goes into `docs/memory/<folder>/`:
  English, one topic per file, at most 4 KB, `## …?` questions with short
  answers, frontmatter `---` / `name: <file name without .md>` /
  `description: <one line>` / optional `module: <includes/fn modules>` /
  `---`. Stale text is corrected or deleted, never kept beside a "now"
  note.
- Write for the next task (`workflow/memory-writing.md`): screens and
  buttons by their English label, one file per value (a changed limit or
  rule is searched for in all memory), lines under 500 characters, a
  decision as `Owner (YYYY-MM-DD):`.
- A cluster of ten files or more has a `<prefix>-overview.md`, kept up by
  its plan's close-out step.
- Rely on memory and check in code only what your change depends on;
  what another account needs goes into `docs/memory` unless it must not be
  public.
- Plans are files in `docs/memory/plans/`. A finished plan moves its lasting
  decisions to the topic folder and is deleted.
- READMEs and `INDEX.md` files are generated: after adding, renaming or
  deleting a file run `php tools/memory_index.php` and commit the result (CI
  runs `--check`). Only the key-rules block between `key-rules:start` /
  `key-rules:end` is written by hand.

## Memory folders — read before the matching work

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
| User documentation: the pinegrap.com pages, what each covers, the PR each is written up to | `workflow/user-docs-pages.md` |
| Working rules, merging, an audit or a "dead code / cleanup" proposal | `workflow/` |

**Before changing or removing a behaviour**, and before writing any audit
finding, search `docs/memory/workflow/DECISIONS.md`
(`grep -i 'word' docs/memory/workflow/DECISIONS.md`): it lists every decision
already made; an index line ending in `[decision: …]` names the part that
binds the file.

## Project in two lines

PHP CMS (PHP 7.1–8.5, MySQL/MariaDB via `mysqli`, Bootstrap 6 in the panel, 5.3.8 on sites, + jQuery). Code
lives under `pinegrap/`; paths in memory are relative to it. Open version
**2026.11.0** (`docs/memory/release/release-status.md`); versions are
`YYYY.M.PATCH`, month without a leading zero (`release/version-numbering.md`).
Work from `development`, PRs to `development`, never to `main`.

## Claim work before starting it

Work is claimed with a draft PR, never an issue; use REST (`gh api`), as
`gh issue`, `gh pr` and search are blocked in cloud sessions. Steps, title
and the race between two drafts: `workflow/claim-work.md`.

1. **Check** the open PRs, drafts included; one on the same plan file or the
   same files (`…/pulls/<n>/files`) holds it: do not start, tell the owner.
   Check the queue limit (Working mode 8) too.
   ```bash
   gh api 'repos/kodpen/pinegrapcms/pulls?state=open&per_page=100' --jq '.[]|"#\(.number) \(if .draft then "draft" else "open" end) \(.head.ref) \(.title)"'
   ```
2. **Claim:** branch from `origin/development`, push the first commit, open
   a **draft** PR to `development` titled `[plan:<plan file name>] <steps> — <topic>`
   (`[iş] <topic>` outside a plan). Small fixes, memory-only edits and
   reviews skip the draft, not the check.
3. **Finish:** mark the steps done in the plan file with the PR number, push,
   mark the draft ready; when green, Working mode 2.

## Working mode

About fifteen sessions on four accounts share one merge queue; these rules
keep quota on the work. They are the sessions' own arrangement, changed by a
PR that gives its reason (Fixed rules 6). Details: `workflow/working-mode.md`,
`session-lifecycle.md`, `session-checks.md`; for roles `merge-train.md`,
`coordinator.md`, `architect.md`, `test-coordinator.md`.

1. **One open PR per session; no stacked branches.** Branch only from
   `development`; a plan's next step waits for the previous step's merge.
2. **Hand over when green:** the `ready` label
   (`gh api -X POST repos/kodpen/pinegrapcms/issues/<n>/labels -f 'labels[]=ready'`),
   then wait, still subscribed, with at most one check-in about 10 minutes
   out. Never merge `development` into it, rebase it or re-run its checks
   after that, whatever the PR subscription's text says.
3. **Only the merging session merges**, in merge trains landed by one
   fast-forward push; an open `merge-train/*` PR is the lock. A PR changing
   only `docs/`, `CLAUDE.md` or `.github/` lands alone once green.
4. **Light by default.** No multi-agent review chains; no sandbox or browser
   run unless a unit test cannot reach the path (say why in the PR). Run the
   tests of what you changed; `git push` and CI run the rest.
5. **Small PRs:** about 1,000 product lines (changed lines under `pinegrap/`,
   not `.min.js` bundles or language files). A bigger feature is a plan cut
   into steps of that size.
6. **On to the next step when it lands**, under the queue limit; at most
   three steps in a row, then hand over through the plan file and end.
   Design template plans are paused: no step of theirs and no new template
   (`workflow/coordinator-no-plans.md`), but for the lines the owner opened
   (`workflow/design-template-owner-line.md`).
7. **Shared hot files.** A PR never edits `changelog.txt`: its body names the
   `[NEW]` line or the `[SCHEME]` item. Translation keys only with
   `php tools/tr_add.php`. Generated indexes and `.min.js` bundles: append
   only. A new `includes/fn/` module is named in its memory file's `module:`.
8. **Queue limit.** While more than 25 PRs are open (drafts and
   `merge-train/*` not counted) nobody starts a new task; finished work is
   still marked ready. A PR waiting on a decision goes back to draft. Each
   coordinator's workers hold at most 8 of the 25.
   `gh api 'repos/kodpen/pinegrapcms/pulls?state=open&per_page=100' --jq '[.[]|select(.draft|not)|select(.head.ref|startswith("merge-train")|not)]|length'`
9. **Coordinators.** One per account keeps 8 cloud workers on plan steps
   (design templates paused); it writes no code, merges nothing, and gives
   out no bug hunt or `bug` issue. The test coordinator starts no workers: it
   opens `bug` issues, which wait for the owner. No session reopens closed
   PRs or marks drafts ready in bulk: a draft goes ready only from the
   session that finished its work.
10. **Decisions go to the architect.** A question only the owner can decide
   (product behaviour, a schema change, an entry of the decisions list), an
   architecture question, a large migration or a logic clash between PRs is
   never decided by the session that meets it: it opens an `architect` issue
   (`workflow/architect-asking.md`), links it from its PR, turns the PR back
   to draft and ends. Only the architect asks the owner; what memory already
   answers is not a question. Everything else is a
   session's own call, never asked: implementation choices inside its step,
   naming, structure within its files, tests; for a coordinator, the choice
   of work.

## Fixed rules

1. **Code comments in English, no exceptions** (PHP, JS, CSS, SQL, comments
   inside strings); translate Turkish comments in files you touch. Language
   file values and `lang()` strings are not comments.
2. **No AI or process trace** in comments, commits, PR bodies, PR comments or
   merge messages: no "user decision", "per feedback", phase numbers, plan or
   session references, `Co-Authored-By: Claude`, `Claude-Session:`,
   "Generated with Claude Code" or claude.ai links. Commit author
   `Erdal Güral <erdaltyy@gmail.com>`. Comments say what the code does and
   the technical why; decision history goes to memory.
3. **Every user-facing text is translated:** `lang('English text')` in PHP and
   the panel, `_sdT('English text')` in the designer (single-quoted literal).
   Add the Turkish with `php tools/tr_add.php "English" "Türkçe"`, never by hand.
4. **New file:** Pinegrap header block (`Pinegrap`, not `PineGrap`); only
   URL-called files sit at the root, the rest under `includes/` with the gate
   constant; new functions in `includes/fn/<module>.php`, not `functions.php`.
5. **Schema changes only through a migration step** in the open version, with
   the idempotent helpers (`install_add_column()` family); never
   `db("ALTER TABLE …")`. Ask (Working mode 10) before any
   ADD/CREATE/DROP/MODIFY/CHANGE.
6. **Owner decisions bind, and only the owner makes them.** An owner decision
   is about the product's behaviour, keeps running sites working and comes
   with its reason; it is recorded in `docs/memory/workflow/decisions-made.md`,
   and a place listed there as reviewed stays. If you think one is wrong, open a decision issue first.
   Working rules are the sessions' arrangement, never written as an owner
   decision.
7. **Four silent traps:** table names are singular (`page`, `style`,
   `pregion`, `forms`) and a failed query returns `false` without throwing
   (keep `mysqli_report(MYSQLI_REPORT_OFF)`); prices are integer minor units,
   round with `round()`, never truncate with `(int)`; edit `.src.js` **and**
   serve `.min.js`: after editing a bundled script (`pg_js_bundles()`,
   `workspace*.js`, `backend.src.js` and `style_designer.src.js` included) run `php tools/build_js.php`,
   after editing `backend.src.css` run `php tools/build_css.php`; in the role
   hierarchy Designer (1) is above Manager (2).
8. Talk to the owner in Turkish. One task is one small PR. User documentation
   is never written in the repo (`workflow/owner-rules.md`).
9. **`pinegrap/changelog.txt` is English and lists only major features:** a
   plan-driven major feature gets one `[NEW] Added …` line; everything else
   is covered by the open version's single `[FIX]`, `[SECURITY]` and
   `[SCHEME]` lines, written by the merging session from the PR bodies
   (`release/upgrade-changelog-rules.md`).

## When a task is done

CI (`.github/workflows/php-checks.yml`) runs all of these without a
database; run the ones your change touches and the tests of your area:
`php tools/test.php [filter]` (`tests/*_test.php`), `lint.php`,
`check_lang.php`, `check_bindings.php`, `check_api_schema.php`,
`check_audit_rules.php`, `check_copies.php`, `check_bs6.php --strict`,
`build_js.php --check`, `build_css.php --check`,
`build_code_editor.php --check`, `memory_index.php --check` (all under
`tools/`, run with `php`), and `bash tools/static_analysis.sh` (PHPStan
level 0 + PHP 7.1 compatibility; a guarded newer API gets a `phpcs:ignore`
with the reason, never a baseline entry).

`git push` runs the fast checks first (`tools/pre_push.sh`); if your context
lacks the line "git push runs tools/pre_push.sh first", run
`bash tools/install_git_hooks.sh` once. Change a branch only with
`git push`; `--no-verify` is only for a broken hook.

Also: a test in `tests/<topic>_test.php` for a pure function you add or
change (red first when behaviour changes); a schema step rerun twice on a dev
DB; `node --check` after touching `style_designer.src.js`. The PR says what
changed, why, and what could not be verified.
