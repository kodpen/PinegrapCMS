---
name: tools-git-without-shell
description: Why tools/free_work.php and its test run git through free_work_git() (proc_open, no shell) and never with >/dev/null, inline GIT_* variables or rm -rf; the Windows push that it unblocked (#11616)
---

## How does tools/free_work.php run git?
Through `free_work_git(array $args, array $env)`: `proc_open()` with `bypass_shell`, the arguments quoted by `free_work_git_command()` (`escapeshellarg()` on POSIX, `free_work_windows_arg()` on Windows), stderr read from a pipe and dropped, extra environment merged into `getenv()`. Returns stdout lines or null.

## What broke on Windows?
`test_free_work_landed_pr_older_than_sixty_days_counts` failed with "git could not make the test repository" and cmd.exe printed "the system cannot find the path specified": the command ended in `>/dev/null 2>&1` and set `GIT_AUTHOR_DATE=… git …` inline, which cmd.exe does not understand. `git push` runs the tests, so every push from a Windows checkout stopped (issue #11616).

## What must a git call in a tool or test avoid?
`>/dev/null` / `2>/dev/null`, `VAR=value cmd`, `cd X && …`, `rm -rf` and single-quoted arguments for cmd.exe. Use `git -C <dir>`, an argument array, the env parameter of `proc_open()`, and a PHP recursive delete (`pg_free_work_rmdir()` in the test). The argument-array form of `proc_open()` needs PHP 7.4, so the line is still quoted by hand.

## How is a local path given to git as a URL?
`free_work_file_url()`: backslashes become `/` and a drive path gets the third slash (`file:///C:/…`); `'file://' . $path` is wrong on Windows.

## What was not verified?
The fix was tested on Linux only; `tests/free_work_test.php` asserts the command carries no POSIX-only syntax and the Windows quoting, not a Windows run.
