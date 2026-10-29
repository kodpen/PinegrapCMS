---
name: installer-wizard-a11y
description: Installer wizard (install/index.php) from the keyboard and a screen reader: focus on step change, step status, rail current/Completed, step problems, the announced run
---

## Where does focus go when the step changes?
- `pg_install_set_step(step, move_focus)`: Continue, Back and the rail pass `true`, because the panel holding the focused button is hidden. Focus goes to the new panel's first `.card-header` (`tabindex=-1`); a refused step focuses its first `[aria-invalid]` field, or keeps focus when the refused step is the one already open. The install/upgrade switch calls it without `true`.

## What does the rail say?
- `aria-current="step"` sits on the focusable `button.pg-install-step`, not the `li`; the current look is `.pg-install-step[aria-current="step"]`. A finished step's button holds a visually hidden 'Completed' (`.pg-step-done-text`, `hidden` toggled in `pg_install_set_step()`).

## How are the step and its problems announced?
- `#pg_step_status` (visually hidden `role=status`) reads 'Step 2/6: title' from the existing keys 'Step' and '{var:1} of {var:2}'; the visible line beside it is `aria-hidden`.
- Step problems: `.pg-step-problem` is `role=alert`; `aria-invalid` is set and cleared with `.is-invalid`.

## How is the install run announced?
- The run: Install/upgrade start focuses `#pg_run_area` (`pg_install_focus_run()`, the start buttons are hidden); `#pg_run_now` is `role=status`, the bar `aria-hidden` beside its percent; console ▲/✗ carry hidden 'Warning'/'Error' in both `pg_install_stream_step()` copies; every result put into `#pg_run_result` is focused (`pg_install_focus_result()`). `#pg_reinstall_message` is `role=alert` and the reinstall box takes focus.

## What pins it?
- Pinned by `tests/installer_wizard_a11y_test.php` (PR "Installer wizard from the keyboard and screen reader", October 2026).
