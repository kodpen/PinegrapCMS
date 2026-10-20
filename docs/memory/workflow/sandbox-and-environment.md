---
name: sandbox-and-environment
description: Cloud sandbox (PHP 8.3, no Composer), when and how to run tools/setup_sandbox.sh, the owner's dev machine dev.pinegrap.com
---

## What does a cloud session have?
- PHP 8.3 ready; no Composer. Reading, editing and the checks under `tools/` need no setup; `bash tools/static_analysis.sh` fetches its pinned PHPStan and PHPCompatibility from GitHub on first use (`static-analysis.md`).

## When do I need a running instance?
- Only to reproduce a runtime error. `bash tools/setup_sandbox.sh` installs and starts MariaDB, creates the database, writes `data/config.php`, runs the installer and serves on `127.0.0.1:8000` (no SQL dump; the schema comes from the migration runner).
- It is expensive: skip it unless needed. If it fails, report the reason; do not work around it.
- Say in the PR when no running instance was set up and what could not be verified.

## What is dev.pinegrap.com?
- The owner's Windows PC: IIS 10 behind cloudflared, MySQL 8.0, no mail server, no AI key. Cloud sessions cannot reach it (or kodpen.com / pinegrap.com).
- Only one Remote Control session at a time works there; cloud threads never start one. They list dev checks and doc notes in their final reply; the coordinator relays them.
- DB re-run on dev: Ayarlar › İşler › Yazılımı Onar › "Veritabanı yükseltme adımlarını yeniden koş". Test records on dev only with the owner's written "yaz ve sil"; never delete visitor rows, run "şimdi temizle", Optimize or "Şimdi küçült", never send real e-mail.
