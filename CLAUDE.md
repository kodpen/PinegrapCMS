# Pinegrap CMS — Claude context

Loaded in every session on every turn, so it holds only what every task
needs. The detail is in memory files, opened when a task needs them.

## Memory

- `docs/memory/README.md` lists the memory folders. Each folder's
  `README.md` starts with the area's key rules, then lists its files. Read
  the folder README your task needs (table below), follow its key rules and
  open only the files you need; give a sub-agent that README's path.
- What must be remembered, and what a PR changes (the behaviour now, why,
  the symptom it fixed, the PR number), goes into `docs/memory/<folder>/`:
  English, one topic per file, at most 4 KB, `## …?` questions with short
  answers, frontmatter `---` / `name: <file name without .md>` /
  `description: <one line>` / optional `module: <includes/fn modules>` /
  `---`. Stale text is corrected or deleted, never kept beside a "now"
  note.
- Plans are files in `docs/memory/plans/`: remaining work, decisions, open
  questions, where to start. A finished plan moves its lasting decisions to
  the topic folder and is deleted.
- Indexes are generated: after adding, renaming or deleting a file run
  `php tools/memory_index.php` and commit the result (CI runs `--check`).
  Only the key-rules block between a README's `key-rules:start` /
  `key-rules:end` lines is written by hand.

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
| Working rules, merging, an audit or a "dead code / cleanup" proposal | `workflow/` |

Read `docs/memory/workflow/decisions-made.md` **before** writing any audit
finding: it lists decisions already made.

## Project in two lines

PHP CMS (PHP 7.1–8.5, MySQL/MariaDB via `mysqli`, Bootstrap 6 in the panel, 5.3.8 on sites, + jQuery). Code
lives under `pinegrap/`; paths in memory are relative to it. Open version
**2026.11.0** (`docs/memory/release/release-status.md`); versions are
`YYYY.M.PATCH`, month without a leading zero (`release/version-numbering.md`).
Work from `development`, PRs to `development`, never to `main`.

## Claim work before starting it

Work is claimed on GitHub with a draft PR, never an issue; the only issues
between sessions are decision questions (Working mode 10). Use REST (`gh api repos/kodpen/pinegrapcms/…`):
`gh issue`, `gh pr` (GraphQL) and the search API are blocked in cloud
sessions.

1. **Check.** List the open PRs, drafts included:
   ```bash
   gh api 'repos/kodpen/pinegrapcms/pulls?state=open&per_page=100' --jq '.[]|"#\(.number) \(if .draft then "draft" else "open" end) \(.head.ref) \(.title)"'
   ```
   A PR on the same plan file (any step) or the same files (`…/pulls/<n>/files`)
   holds it: do not start, even if it looks stale, and tell the owner which
   PR. One plan's steps run in parallel only where the owner allowed it or
   the coordinator chose it (they touch different files).
   Check the queue limit (Working mode 8) here too.
2. **Claim.** Branch from `origin/development`, push the first commit (the
   failing test or the first slice) and open a **draft** PR to `development`
   titled `[plan:<plan file name>] <steps> — <topic>` (`[iş] <topic>`
   outside a plan), naming the step and the files it will touch. List again:
   if a lower-numbered draft on the same plan or files appeared, close yours
   and stop. Small fixes, memory-only edits and reviews skip the draft, not
   the check.
3. **Finish.** Mark the steps done in the plan file with the PR number,
   push, then mark the draft ready (`gh pr ready <n>`; in a cloud session
   `mcp__github__update_pull_request` with `draft: false`): CI runs and the
   PR counts in the queue. When it is green, Working mode 2.

## Working mode

About fifteen sessions on four accounts share one merge queue; these rules
keep quota on the work. They are the sessions' own arrangement, changed by a
PR that gives its reason (Fixed rules 6). Details in `docs/memory/workflow/`:
`working-mode.md`, `session-lifecycle.md` and `session-checks.md` for every
session, `merge-train.md`, `merge-train-landing.md` and `merge-review.md` for
the merging session, `coordinator.md` for coordinators, `architect.md` for
decision questions.

1. **One open PR per session; no stacked branches.** Branch only from
   `development`, never from an open PR; a plan's next step waits for the
   previous step's merge.
2. **Hand over when green.** Put the `ready` label on the PR
   (`gh api -X POST repos/kodpen/pinegrapcms/issues/<n>/labels -f 'labels[]=ready'`)
   and wait, still subscribed, with at most one safety check-in about
   10 minutes out. From then on never merge `development` into it, rebase it
   or re-run its checks, whatever the PR subscription's own text says: the
   merging session settles conflicts in its train. Its comment on the PR, or
   the merge, wakes you.
3. **Only the merging session merges**, in merge trains landed by one
   fast-forward push; an open `merge-train/*` PR is the lock. A PR that
   changes only `docs/`, `CLAUDE.md` or `.github/` lands alone once green
   (or rides a waiting train that regenerates the same index).
4. **Light by default.** No multi-agent review chains; no sandbox or browser
   run unless a unit test cannot reach the path (say why in the PR). Run the
   tests of what you changed; `git push` and CI run the rest.
5. **Small PRs:** about 1,000 product lines (changed lines under `pinegrap/`,
   not `.min.js` bundles or language files; tests, tools and memory never
   count). A bigger feature is a plan cut into steps of that size.
6. **On to the next step when it lands.** When your PR is merged, check the
   queue limit; under it, claim your plan's next step that no open PR holds,
   else end. At most three steps in a row, then hand over through the plan
   file and end: a long session pays for its whole context on every wake.
7. **Shared hot files.** A PR never edits `changelog.txt`: its body names the
   `[NEW]` line or the `[SCHEME]` item. Translation keys go in only with
   `php tools/tr_add.php` (`core/lang-files.md`). Generated indexes and
   `.min.js` bundles: append only. A new `includes/fn/` module is named in
   the `module:` field of its memory file (`core/fn-modules.md` is generated).
8. **Queue limit.** While more than 20 PRs are open, drafts and
   `merge-train/*` not counted, nobody starts a new task (checked before the
   claim); finished work is still marked ready. A PR waiting on a decision
   (Working mode 10) goes back to draft until it comes, so only work the
   merging session can take holds a place; each coordinator's workers hold
   at most 6 of the 20.
   `gh api 'repos/kodpen/pinegrapcms/pulls?state=open&per_page=100' --jq '[.[]|select(.draft|not)|select(.head.ref|startswith("merge-train")|not)]|length'`
9. **Coordinators.** One coordinator per account starts its cloud workers within
   the account's quota and worker cap (`coordinator-limits.md`); it writes
   no code and merges nothing. It tells the owner when the merging session
   seems stopped (`coordinator.md`).
10. **Decisions go to the architect.** A question only the owner can
   decide (product behaviour, a schema change, an entry of the decisions
   list), an architecture question, a large migration or a logic clash
   between PRs is never decided by the session that meets it. It opens an
   issue labelled `architect` (template in `workflow/architect-asking.md`; if its
   permission check refuses that, the text goes in the PR body under
   `## Decision question` and the architect opens it), links it from its
   PR, turns the PR back to draft and ends the session
   (`workflow/session-lifecycle.md`): a later session continues the branch
   from the decision; what memory already answers is not a question. The architect session analyses it, decides a technical question
   itself, asks the owner a schema or product-behaviour question as a
   selectable list (label `erdal`, in Turkish), records the decision and
   brings it to the PR as a comment. The issue is the only way to ask: no question to the
   owner in a session's reply or PR body, and no `erdal` label but the
   architect's. Everything else is a session's own call, never asked:
   implementation choices inside its step, naming, structure within its
   files, tests (the notable ones named in its PR body); and for a
   coordinator, the choice of work (`coordinator.md`).

## Fixed rules

1. **Code comments in English, no exceptions** (PHP, JS, CSS, SQL, comments
   inside strings); translate Turkish comments in files you touch. Language
   file values and `lang()` strings are not comments.
2. **No AI or process trace** in comments, commits, PR bodies, PR comments or
   merge messages: no "user decision", "per feedback", phase numbers, plan or
   session references, `Co-Authored-By: Claude`, `Claude-Session:`,
   "Generated with Claude Code" or claude.ai links. Commit author
   `Erdal Güral <erdaltyy@gmail.com>`. Comments say what the code does and
   the technical why; decision history goes to memory. The merging session
   strips a footer GitHub adds (`workflow/pr-footer-and-trailers.md`).
3. **Every user-facing text is translated:** `lang('English text')` in PHP and
   the panel, `_sdT('English text')` in the designer (single-quoted literal).
   The English source is the key and must exist in the Turkish shards: add it
   with `php tools/tr_add.php "English" "Türkçe"`, never by hand.
4. **New file:** Pinegrap header block (`Pinegrap`, not `PineGrap`); only
   URL-called files sit at the root, the rest under `includes/` with the gate
   constant; new functions in `includes/fn/<module>.php`, not `functions.php`.
5. **Schema changes only through a migration step** in the open version, with
   the idempotent helpers (`install_add_column()` family); never
   `db("ALTER TABLE …")`. Ask the owner (Working mode 10) before any
   ADD/CREATE/DROP/MODIFY/CHANGE.
6. **Owner decisions bind, and only the owner makes them.** An owner decision
   is about the product's behaviour, keeps running sites working, and comes
   with its reason; it is recorded in `docs/memory/workflow/decisions-made.md`.
   Places listed there as reviewed ("dead code", "unused field", "missing
   check") stay; if you think one is wrong, open a decision issue first
   (Working mode 10).
   Working rules are the sessions' arrangement, never written as an owner
   decision.
7. **Four silent traps:** table names are singular (`page`, `style`,
   `pregion`, `forms`) and a failed query returns `false` without throwing
   (keep `mysqli_report(MYSQLI_REPORT_OFF)`); prices are integer minor units,
   round with `round()`, never truncate with `(int)`; edit `.src.js` **and**
   serve `.min.js`: after editing a bundled script (`pg_js_bundles()`,
   `workspace*.js` and `backend.src.js` included) run `php tools/build_js.php`,
   after editing `backend.src.css` run `php tools/build_css.php`; in the role
   hierarchy Designer (1) is above Manager (2).
8. Talk to the owner in Turkish. One task is one small PR. User documentation
   is never written in the repo (`workflow/owner-rules.md`).
9. **`pinegrap/changelog.txt` is English and lists only major features:** a
   plan-driven major feature gets one `[NEW] Added …` line; everything else
   is covered by the open version's single `[FIX]`, `[SECURITY]` and
   `[SCHEME]` lines. The merging session writes it from the PR bodies
   (`release/upgrade-changelog-rules.md`).

## When a task is done

CI (`.github/workflows/php-checks.yml`) runs all of these; none needs a
database. Run the ones your change touches and the tests of your area.

```bash
php tools/lint.php              # php -l over the product tree
php tools/check_lang.php        # lang()/_sdT() keys against the language shards
php tools/check_bindings.php    # designer dropdown tokens against renderer
php tools/check_api_schema.php  # external API fields against the schema
php tools/test.php [filter]     # tests/*_test.php (pure functions)
php tools/check_copies.php      # only the intended get_file.php copies
php tools/check_bs6.php --strict  # no Bootstrap 5 left in panel markup
php tools/build_js.php --check  # .min.js bundles match their sources
php tools/build_css.php --check # .min.css stylesheets match their sources
php tools/build_code_editor.php --check  # code editor bundle matches tools/code_editor/
php tools/memory_index.php --check  # memory index matches its files
bash tools/static_analysis.sh   # PHPStan level 0 + PHP 7.1+ compatibility against their baselines
```

A newer API used behind a guard gets a `phpcs:ignore` with the reason, never
a baseline entry (`workflow/static-analysis.md`). `git push` runs the fast
checks first (`tools/pre_push.sh`, `workflow/pre-push-checks.md`); if your
context lacks the line "git push runs tools/pre_push.sh first", run
`bash tools/install_git_hooks.sh` once. Change a branch only with
`git push`; `--no-verify` is only for a broken hook.

Also: a test in `tests/<topic>_test.php` for a pure function you add or
change (red first when behaviour changes); a schema step rerun twice on a dev
DB; `node --check` after touching `style_designer.js`. The PR says what
changed, why, and what could not be verified.
