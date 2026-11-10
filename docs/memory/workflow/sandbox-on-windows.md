---
name: sandbox-on-windows
description: A throwaway Pinegrap install on the owner's Windows machine without touching dev: a private mysqld on its own port, a git archive export, php -S, the installer by curl, CLI scripts, the API over HTTP
---

## When is it used?
- When a path needs a database and dev's database must not be written (`dev-machine-testing.md`). `tools/setup_sandbox.sh` is Linux only (apt, sudo); this does the same by hand from the session's scratch folder in about five minutes (2026-11-10). Say in the PR why a unit test could not reach the path.

## How is the database made?
- The machine's MySQL 8.0 binaries (`C:\Program Files\MySQL\MySQL Server 8.0\bin`) run a second server; the `MySQL80` service on 3306 is dev's and is never used. `mysqld --no-defaults --initialize-insecure --basedir=… --datadir=<scratch>/mysql/data`, then start it in the background with `--port=33917 --bind-address=127.0.0.1 --mysqlx=OFF --skip-log-bin`.
- Create the database and a `mysql_native_password` user through `mysql --no-defaults -h 127.0.0.1 -P 33917 -u root`. A user that exists only there cannot write dev by mistake.
- The product calls `mysqli_connect(DB_HOST, …)` with no port, and mysqli does not read `host:port`: give every PHP that should reach the sandbox `-d mysqli.default_port=33917`, the built-in server and CLI scripts alike.

## How is the site made?
- `git archive origin/development pinegrap tools index.php | tar -x -C <scratch>/sbx`, then write `pinegrap/data/config.php` there (`DB_HOST` 127.0.0.1, an `ENCRYPTION_KEY` of 32 A-Z0-9, `DEFAULT_SOFTWARE_LANGUAGE`). A fix is tried by copying its files over the export; `git show <ref>:<file> >` puts the base back.
- `php -d mysqli.default_port=33917 -S 127.0.0.1:8917 -t <sbx> <sbx>/tools/sandbox_router.php`, then post the installer as `tools/setup_sandbox.sh` does (the token from `/pinegrap/install/`, `install_from_folder=turkish_default`): about 90 seconds.
- A second sandbox beside another session's takes other ports; stop a `php -S` by the port it listens on.
- Cookies are kept per host, not per port: two sandboxes on `127.0.0.1` in the app's browser pane sign each other out at once (the panel answers the sign-in page on the next request). Open a sandbox as `http://<name>.localhost:<port>/`, a host of its own.

## How is the product called from a script?
- A CLI script sets `$_SERVER` `REQUEST_METHOD` and `HTTP_HOST`, changes to the export's `pinegrap/` and requires `init.php`, then the module's bootstrap. `PG_API_PANEL` is defined before `includes/api/keys.php` is loaded, or that file exits without a word.
- `config.workspace_enabled` is 0 after install: set it and run again, the constant is read at init.
- A panel action runs as a person by setting `$_SESSION['sessionuserid']` before the call; `ws_channel_membership_forget()` between people. A test person gets a password with `pg_password_store($id, $password)`.
- A Workspace member of the user role is one `user` row with `user_role` 3 and `manage_workspace` 1; staff are members by their role. A Workspace action answers `status` success or error with `message`.
- A model call without a provider: a model agent with provider `custom` whose address is a second `php -S` answering `/v1/chat/completions` with a tool call; `ws_ai_kick()` then runs the whole loop.

## How is the API called over HTTP?
- The sandbox answers `https_required` until `UPDATE config SET api_require_https = 0`. The route follows the entry point with no version prefix (`/pinegrap/integration.php/workspace/channels/1/agents`).
- A key signs in only with its secret: make the caller with `api_app_create()` (`$with_secret` true) and send `X-Pinegrap-Key` and `X-Pinegrap-Secret`. A rehearsal is `?dry_run=true`.

## What is left behind?
- Nothing: `mysqladmin --no-defaults -h 127.0.0.1 -P 33917 -u root shutdown`, stop the `php -S` processes, delete the export and the data folder.
- A PHP script typed through a Git Bash heredoc loses backslashes: write it as a file.
