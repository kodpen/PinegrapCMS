---
name: qr-code-generator
description: QR code generation via includes/fn/qr.php (pg_qr_matrix, pg_qr_svg), its limits, forbidden library paths, and why secret QRs are inline SVG only.
---

## How is a QR code generated?
- Generator: `includes/qrcode/qrcode.php` (kazuhikoarase/qrcode-generator PHP port, MIT), embedded as-is. Do not edit inside it; on update the whole file is replaced.
- It is never called directly. Use `includes/fn/qr.php`: `pg_qr_matrix($text, $level)`, `pg_qr_svg($text, $size, ['level','label','id','class'])` (inline SVG, 4-module quiet zone; `$size` 0 scales to its box), `pg_qr_svg_data_uri()`.
- No GD needed.

## What are the limits and forbidden paths?
- Max 1000 bytes (`pg_qr_max_length()`); longer text returns `''` / `array()` and the caller must show the manual path.
- Always byte mode (UTF-8 as-is). Version 1-40 is chosen from the RS block table.
- The library's `getMinimumQRCode()` / `getMaxLength()` path fatals for version 11+: do NOT use it. `getMode()` may mistake UTF-8 for Kanji: do NOT use it.

- Never embed a second generator, and never ask an external QR service: the text (a TOTP secret included) would go to a third party.

## Where may a secret QR be rendered?
- A QR of secret text (TOTP key) is printed ONLY as inline SVG. No `<img src="qr.php?...">`-style endpoint and no external QR service (logs, Referer, third party).
- Used by two-factor setup: `mfa.php` `setup`, `pg_mfa_account_section()`.
