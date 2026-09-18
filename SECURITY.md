# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| Current `2026.x` release line (see `pinegrap/changelog.txt`) | Yes |
| Older release lines | No — please update first |

Pinegrap runs on PHP 7.0 through 8.5 (see the README); fixes are verified
against that range.

## Reporting a vulnerability

Please report security issues **privately**:

1. Preferred: use GitHub's private vulnerability reporting on this repository
   (**Security** tab → **Report a vulnerability**). If the button is missing,
   the maintainer has not yet enabled it under **Settings → Code security →
   Private vulnerability reporting**; use the fallback below in the meantime.
2. Fallback: contact the maintainer through the form at
   <https://kodpen.com>.

Include the affected file(s) and line(s), the request that triggers the
issue, what you observed, and a proposed fix if you have one. Turkish or
English are both fine.

## What not to do

Do **not** open a public issue or pull request for:

- Web Application Firewall bypasses (signature, rate limit, IP reputation,
  bot classification, IP ban),
- authentication, session, password-reset or role-gate weaknesses,
- CSRF token gaps or state-changing GET requests,
- anything involving payment data or the payment provider integrations,
- secrets, encryption-key handling or backup exposure.

A public report of this kind is closed and moved to a private channel.

## What to expect

- Acknowledgement within a few working days.
- The maintainer confirms the finding, decides on the fix and the affected
  release line, and keeps you informed.
- Fixes ship to installed sites through the in-product update channel
  (`Updating` in the README); the change log entry is written so that it does
  not disclose exploitation details before sites have updated.
- Credit is given in the change log if you want it.
