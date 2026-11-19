---
name: whatsapp-template-authentication
description: Why the WhatsApp template designer does not offer the Authentication type (#10356): Meta's fixed code shape, no sender of codes; saved ones still list and read
module: whatsapp_design
---

## Is Authentication offered in the WhatsApp template designer?
- No, since #10356 (PR #10389): `pg_whatsapp_design_categories()` offers Utility and Marketing only, and `pg_whatsapp_design_check()` refuses any other type, so an Authentication draft is neither saved nor sent.
- A saved Authentication template (read from Meta, or an old draft) still lists on Communication › Templates and opens in the designer with its type shown: `pg_whatsapp_design_category_choices()` (pure) adds the template's own type as a disabled button.
- Decided in the agent channel, message 9554 (2026-11-19): hidden for new templates and type changes; saved ones still list and read.

## Why?
- Meta takes an `AUTHENTICATION` template only in its fixed shape: no header or own text, a BODY with `add_security_recommendation`, an optional FOOTER with `code_expiration_minutes` (1–90) and one OTP button (`COPY_CODE`, `ONE_TAP`, `ZERO_TAP`). The designer sent a free-text BODY, which Meta always refused.
- No part of Pinegrap sends a one-time code over WhatsApp; a code form would make templates with no sender. It comes back with a code form once something sends codes.

Related: docs/memory/platform/whatsapp-template-designer.md, docs/memory/platform/whatsapp-template-approval.md
