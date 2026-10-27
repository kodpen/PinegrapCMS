---
name: sandbox-and-environment
description: Cloud sandbox (PHP 8.3, no Composer), when and how to run tools/setup_sandbox.sh, the owner's dev machine dev.pinegrap.com and how a linked session updates its checkout
---

## What does a cloud session have?
- PHP 8.3 ready; no Composer. Reading, editing and the checks under `tools/` need no setup; `bash tools/static_analysis.sh` fetches its pinned PHPStan and PHPCompatibility from GitHub on first use (`static-analysis.md`).

## When do I need a running instance?
- To reproduce a runtime error, and for the screenshot rounds below. `bash tools/setup_sandbox.sh` installs and starts MariaDB, creates the database, writes `data/config.php`, runs the installer and serves on `127.0.0.1:8000` (no SQL dump; the schema comes from the migration runner).
- It is expensive: skip it unless needed. If it fails, report the reason; do not work around it.
- The Bootstrap 6 panel plan's screenshot rounds are the other use (owner, 2026-10-25): B1b, the B8 rehearsals and the form-adorn pilot (`plans/bootstrap-6-panel.md`). A screen's look is a path no unit test reaches; the images are never committed.
- Say in the PR when no running instance was set up and what could not be verified.

## How does the sandbox serve theme files?
- PHP's built-in server runs `tools/sandbox_router.php` as its router, the part the root `.htaccess` plays in production: an existing file or folder is served as is, `data/` and `includes/` (and `.git`, `..`) answer 403, everything else (theme CSS, logos and other files from the `files` table, friendly URLs) runs `pinegrap/router.php` with `SCRIPT_NAME` set to it. Without it those files answered 404 and pages opened unstyled (PR #2222).

## What is dev.pinegrap.com?
- The owner's Windows PC: IIS 10 behind cloudflared, MySQL 8.0, no mail server, no AI key. Cloud sessions cannot reach it (or kodpen.com / pinegrap.com).
- Its PHP: `C:\PHP` = 8.5.11 x64 NTS, for the site and on PATH (from 2026-10-25); OpenSSL 3.5.8 with no `openssl.cnf`. The full unit suite passes there (#1669, #1678), so `git push` runs the pre-push hook as anywhere: a failure there is real, no `--no-verify`.
- Only one Remote Control session at a time works there; cloud threads never start one. They put dev checks in the PR's "Could not verify" section and questions for the owner under `## Questions for the owner` (`coordinator.md`).
- `gh` is not installed there. A session linked to that machine calls the GitHub REST API with the token `git credential fill` returns for github.com (the credential git push uses) through a small PowerShell helper in its scratchpad; those requests do not pass the cloud proxy, so bodies edited that way carry no Claude Code footer.
- DB re-run on dev: Ayarlar › İşler › Yazılımı Onar › "Veritabanı yükseltme adımlarını yeniden koş". Test records on dev only with the owner's written "yaz ve sil"; never delete visitor rows, run "şimdi temizle", Optimize or "Şimdi küçült", never send real e-mail.

## How does a cloud session linked to that machine update dev's checkout?
- Its shell (`device_bash`) is a Linux VM over the mounted folder (`$HOME/mnt/PinegrapCMS`): each call ends within 180 s and takes its background jobs with it, and deleting needs the owner's approval for the folder (`device_request_delete_permission`). git replaces a file by deleting it: without the approval a checkout updates the index and leaves the old file.
- A `git pull` over a few hundred files does not finish in time. Fetch, then `git checkout refs/remotes/origin/development -- <40 or so files>` per call (list: `git diff --name-only HEAD refs/remotes/origin/development`), then `git reset --soft refs/remotes/origin/development`; `git diff --cached --quiet` confirms. A killed git leaves `.git/index.lock`: remove it only when no git runs.
- Files written from Windows have CRLF; compare contents with `tr -d '\r' < file | git hash-object --stdin`.
