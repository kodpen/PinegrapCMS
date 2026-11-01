---
name: designer-turkish-terms-2
description: More Turkish designer words, continued from designer-turkish-terms: Parola (never Şifre) in the account widgets, their Bind Data labels and the account e-mails; tests/designer_password_word_test.php
---

## Which word names a password in the designer?
- "Parola", as in the panel (`core/panel-turkish-terms.md`): "Parolamı değiştir" (My Account link), "Parola Değiştirme Kartı", "Parola Belirleme Kartı", "Parola Formu", "Henüz Parola Yok", "Parolamı Unuttum Bağlantısı" (Sign In tree), "Parolayı Sıfırla" (reset e-mail subject and its placeholder), "parolayla oturum açarken" and "parola yöneticinizden" (Account Security passkey texts).
- Encryption words keep "şifre": "şifreleme", "şifreli", "şifre çözme".
- Symptom fixed: the Change Password widget showed "Mevcut parola" beside "şifreyle", My Account printed "Şifremi değiştir" on the site, and the Bind Data lists of the account widgets said "şifre" where the Registration and Membership lists said "parola" (#4784).

## Which visitor texts outside the editor were changed with them?
- The Forgot Password e-mail (`forgot_password.php`: the request line had "Parolanızı" and "şifrenizi" in one sentence, the ignore line, "not available"), the design templates' welcome e-mail line "To choose your password, use", the passkey warning "If you did not ask for it, change your password now…", and the Site Log line about a reset e-mail page with no set password link.
- Left as they were: Workspace mail settings (an app password of a mail provider), the installer, `API Password`, and the SEO keyword lists that hold both words on purpose ("password, sign in, …").

## What guards this?
`tests/designer_password_word_test.php`: no `_sdT()` key of `style_designer.src.js` has a Turkish value with "şifre" outside "şifrele…"/"şifreli", and the account e-mail keys above say "parola".

Related: docs/memory/designer/designer-turkish-terms.md, docs/memory/core/panel-turkish-terms.md
