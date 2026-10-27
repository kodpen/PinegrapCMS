---
name: otp-fields
description: Code and PIN fields on the Bootstrap 6 OTP component (B9d, #2210): MFA app and recovery code modes, no auto-submit, developer lock masked slots and submit on complete
---

## Which fields use the OTP component?
- `mfa.php`: the 6-digit app code (setup and verify) and, in verify, the recovery code (8 characters, shown as XXXX-XXXX). `developer_lock.php`: one masked slot per `DEVELOPER_PIN` digit.
- The site profile code field stays on Bootstrap 5.3.8 (site output); never this markup there.

## How is a field written?
- `<div class="otp" data-bs-otp="">` around one `<input class="otp-input" name=… maxlength=N>`: the data API starts it on DOMContentLoaded (the bridge module runs before), the one real input is what is posted, slot count is `maxlength`.
- App code: `otp-lg`, `data-bs-groups="[3,3]"`. Recovery code: `data-bs-type="alphanumeric"`, `data-bs-groups="[4,4]"`, `data-bs-separator="-"`, `text-uppercase` on the container; the dash is drawn, not posted, and `pg_mfa_recovery_normalize()` ignores case anyway. PIN: `otp-connected`, `data-bs-mask="true"` (stays a text input).
- `.pg-otp-fluid` (min-width 0 on slots) lets the slots shrink in a phone-width card; the rule sits in a `@layer custom` style block of each screen.

## How do the MFA modes work?
- Both fields are in the form; the one not shown is `hidden` and `disabled`, so one `code` is posted and only the shown one is `required`. "Use a recovery code" / "Use a code from the app" swap them.
- The screen opens in recovery mode when the keys cannot be read (`pg_mfa_available()` false) or when the refused code was a recovery code (`pg_mfa_code_kind()`, the same split `pg_mfa_verify_code()` uses: six digits are TOTP, eight normalized characters recovery).
- No auto-submit: every refused code counts against `pg_mfa_attempt_blocked()` (5 per 10 minutes), so the person presses Verify.

## How does the developer lock submit on complete?
- The component takes typing (`beforeinput`, `preventDefault`) and paste, so no native `input` event follows a keystroke. The screen reads the input's value on `keyup`, `input`, `change` and after `paste`, and submits once when it holds `maxlength` digits. No Bootstrap event is read, so `pgUI` needs no `otp` kind.
