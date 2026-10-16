# Pinegrap CMS — Claude context

Loaded in every session, so it holds only the rules that apply to every task.
Everything else is in memory files and skills, opened only when needed.

## Memory

- Index: `docs/memory/README.md` — one line per file. Read it, then open only
  the files the task needs. It also lists the other documents under `docs/`.
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
- The index is generated: run `php tools/memory_index.php` after adding,
  renaming or deleting a file and commit the result. Never edit or merge it
  by hand; CI runs `--check`.

## Skills — load before the matching work

Load with the `Skill` tool, or read `.claude/skills/<name>/SKILL.md`. When you
start a sub-agent, write the path into its task.

| Work | Skill |
|---|---|
| SQL, reading tables/columns, counters, reports | `pinegrap-veritabani` |
| A migration step that changes the schema | `pinegrap-sema-adimi` |
| A new file, function, folder or library | `pinegrap-yeni-dosya` |
| User-facing text (`lang()`, `_sdT()`, `tr.json`) | `pinegrap-ceviri` |
| JavaScript (`.src.js`/`.min.js`, panel, front end) | `pinegrap-js-varliklari` |
| CSRF, escaping, redirects, secrets, file integrity, server rules | `pinegrap-guvenlik` |
| `waf.php`, rate limits, IP lists, security headers | `pinegrap-waf` |
| Panel screen, toolbar, form, settings modal | `pinegrap-ui-deseni` |
| Role gates, permission columns, designer content roles | `pinegrap-roller-yetki` |
| Visual designer, system widget, data binding, palette component | `pinegrap-tasarimci-widget` |
| External API, webhook, connector, marketplace, e-document | `pinegrap-dis-api` |
| ERP (accounting) module | `pinegrap-erp` |
| An audit, or proposing "dead code / cleanup" | `pinegrap-verilmis-kararlar` |

Read the last one **before** writing any audit finding: it lists decisions
already made.

## Project in two lines

PHP CMS (PHP 7.1–8.5, MySQL/MariaDB via `mysqli`, Bootstrap 5 + jQuery). Code
lives under `pinegrap/`; paths in memory and skills are relative to it. Open
version **2026.4.10**; details in `docs/memory/release/release-status.md`.
Branches: work from `development`, PRs to `development`, never to `main`.

## Fixed rules

1. **Code comments in English, no exceptions** (PHP, JS, CSS, SQL, comments
   inside strings). Translate Turkish comments in files you touch. `tr.json`
   values and `lang()` strings are not comments.
2. **No AI or process trace** in comments, commits, PR bodies, PR comments or
   merge messages: no "user decision", "per feedback", phase numbers, plan or
   session references, `Co-Authored-By: Claude`, `Claude-Session:`,
   "Generated with Claude Code" or claude.ai links. If a tool adds one, edit it
   out right away. Commit author `Erdal Güral <erdaltyy@gmail.com>`. Comments
   say what the code does and the technical why; decision history goes to
   memory.
3. **Every user-facing text is translated:** `lang('English text')` in PHP and
   the panel, `_sdT('English text')` in the designer (single-quoted literal).
   The English source is the key and must exist in `tr.json`.
4. **New file:** Pinegrap header block (`Pinegrap`, not `PineGrap`); only
   URL-called files sit at the root, the rest under `includes/` with the gate
   constant; new functions in `includes/fn/<module>.php`, not `functions.php`.
5. **Schema changes only through a migration step** in the open version, with
   the idempotent helpers (`install_add_column()` family); never
   `db("ALTER TABLE …")`. Ask before any ADD/CREATE/DROP/MODIFY/CHANGE.
6. **Owner decisions bind.** Places repeatedly reported as "dead code",
   "unused field" or "missing check" are listed in `pinegrap-verilmis-kararlar`;
   leave them. If you think one is wrong, ask before changing it.
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
   `[SCHEME] …` line: add them once if missing, never one line per fix.
   Layout and order: `docs/memory/release/upgrade-changelog-rules.md`.

## When a task is done

CI (`.github/workflows/php-checks.yml`) runs all of these; none needs a database.

```bash
php tools/lint.php              # php -l over the product tree
php tools/check_lang.php        # lang()/_sdT() keys against tr.json
php tools/check_bindings.php    # designer dropdown tokens against renderer
php tools/check_api_schema.php  # external API fields against the schema
php tools/test.php [filter]     # tests/*_test.php (pure functions)
php tools/check_copies.php      # only the intended get_file.php copies
php tools/build_js.php --check  # .min.js bundles match their sources
php tools/memory_index.php --check  # memory index matches its files
```

Also: a test in `tests/<topic>_test.php` for a pure function you add or change
(red first when behaviour changes); a schema step rerun twice on a dev DB;
`node --check` after touching `style_designer.js`. The PR says what changed,
why, and what could not be verified (say so when no running instance was set
up).
