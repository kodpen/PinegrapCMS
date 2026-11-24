---
name: bug-hunt-areas-two-step-sign-in
description: Bug hunt area 45, two-step sign-in (2026-11-24): code window and single use, recovery codes, trusted devices, reset, throttle, API keys and open sessions
---

## What was the area?
- Panel sign-in with two-step verification: `mfa.php`, `includes/fn/mfa.php`, the gate's callers, the reset on Edit User, the API paths. Hunted in the PR titled "Bug hunt — two-step sign-in".

Related: docs/memory/workflow/bug-hunt.md, docs/memory/core/two-step-sign-in.md
