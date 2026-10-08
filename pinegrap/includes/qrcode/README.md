# QR Code generator (PHP)

`qrcode.php` is the PHP port of Kazuhiko Arase's QR Code generator, embedded
unchanged.

- Source: https://github.com/kazuhikoarase/qrcode-generator/blob/master/php/qrcode.php
- Revision: commit `95af9c2e1249337047ca478ccf98650a0a30af13` (2021-12-10),
  git blob `ff14f0c08faac549a1ad139ea0463c81342d9116`
- License: MIT, see `LICENSE`
- Requirements: PHP 5 or later; no extensions (GD only for its own
  `createImage()`, which Pinegrap does not use)

Do not edit the file; replace it whole when updating.

Pinegrap does not call it directly: `includes/fn/qr.php` wraps it
(`pg_qr_matrix()`, `pg_qr_svg()`, `pg_qr_svg_data_uri()`). The wrapper picks
the version from the Reed-Solomon block table (versions 1-40) and always
encodes in byte mode, because:

- `QRCode::getMinimumQRCode()` and `QRUtil::getMaxLength()` only know
  versions 1-10 and raise a fatal error above that;
- `QRUtil::getMode()` can take UTF-8 byte pairs for Kanji.

"QR Code" is a registered trademark of DENSO WAVE INCORPORATED.
