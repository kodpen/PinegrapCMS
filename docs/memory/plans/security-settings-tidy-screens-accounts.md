---
name: security-settings-tidy-screens-accounts
description: Plan security-settings-tidy, its second screen: the Accounts and sign-in pane as folded rows, Sign-in attempts and Deleting many at once on rows of their own, the providers as connect rows
---

## By which rules?
- As `plans/security-settings-tidy-screens.md`: parts copied from the Design examples screen, its section in brackets; no part is new.

## How is the pane laid out?
- Head: "Accounts and sign-in" and one purpose line [Type and text]: how people sign in, what keeps accounts safe, and what a form accepts.
- Folded rows [Settings pane], one open, each line saying its state, in this order: "Passwords and sessions", "Sign-in attempts", "Two-step verification", "Devices", "Sign in with Google, Microsoft and Apple", "Forms and spam", "Registration and membership", "Audit trail", "Deleting many at once", "Encryption key".
- Passwords and sessions: "Require a strong password", "Password hint", "Remember me", "Forgot password link" [Fields, switch], each with its line.
- Sign-in attempts: "Limit failed sign-ins" and under it, always drawn, Failed attempts, Counted over, Locked out for, Ask a question after; the line on shared addresses links "the allowed list" to `#settings/firewall/pgset-iplists`.
- Two-step verification: Required for [Fields, select], "Sign in with a passkey alone"; the encryption key line where it is missing.
- Devices: "Limit devices per account", Max devices, "Refuse new sign-ins when the limit is full", all drawn while off.
- Sign in with Google, Microsoft and Apple: three [Connect row]s: the provider's pill ("Ready", "Not set up"), its sentence, its switch, "Set up in Connections" quiet; under them the line on the rules every provider follows.
- Forms and spam: "CAPTCHA", "Check visitors against StopForumSpam", the form submission limits' three boxes with "the allowed list" linked, Blocked e-mail addresses; a line names the chat's own puzzle, under Communication › Chat on the site (`pgset-chat-site`), linking its row.
- Registration and membership: the contact groups and e-mail addresses, Member ID label; "Send an expiration warning to members" and under it, always drawn, Subject, Page, Days before; its line says the membership job must run.
- Audit trail: Keep lines for, "Open it" quiet. Deleting many at once: the switch and one line on where it applies. Encryption key: "Reset the encryption key" and its warning.
- Under 576 px: rows full width, a connect row's switch and button under its sentence. Foot: Save the fill, one only.

## Which words?
- "Passwords and sessions", "Sign-in attempts", "Limit failed sign-ins", "Forms and spam", "Deleting many at once", "Set up in Connections"; no name starts with "Enable"; sentence case; never "Session & Password" or "Spam Protection" as a row.

## What must be true?
- Every field posts under today's name; `security.save.php` stores what it stored.
- `#settings/security/pgset-session`, `…/pgset-captcha`, `…/pgset-device`, `…/pgset-mfa`, `…/pgset-audit`, `…/pgset-signin`, `…/pgset-membership`, `…/pgset-encryption` open their rows; `login_throttle` opens "Sign-in attempts", `mass_deletion` "Deleting many at once".
- A provider not set up shows its state and its switch; no popover is left, nothing more than one fold deep.
- The settings search finds "throttle", "passkey" and "StopForumSpam".

Related: docs/memory/plans/security-settings-tidy.md, docs/memory/plans/security-settings-tidy-steps.md, docs/memory/plans/security-settings-tidy-screens.md
