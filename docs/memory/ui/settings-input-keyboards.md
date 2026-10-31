---
name: settings-input-keyboards
description: Phone keyboard and auto-capitalisation of the Site Settings and Workspace mailbox boxes for web addresses, hosts, user names, address lists and Merchant numbers.
---

## Which keyboard does a settings box open?
- Web addresses and host names (cookie policy, feed and stats URLs, hostname, proxy, FTP host, S3 endpoint, ERP web address, TSA URL, mailbox auth/IMAP/SMTP host): `inputmode="url" spellcheck="false" autocapitalize="off"`.
- Values stored or compared as typed (FTP user and folder, S3 region, bucket, prefix and access key, TSA user name, mailbox user): `spellcheck="false" autocapitalize="off"`.
- Comma lists of addresses (ERP overdue recipients, Workspace mail route match): `inputmode="email"` plus the same two.
- Merchant shipping rate `inputmode="decimal"`, return window `inputmode="numeric"`.
- Pinned by `tests/settings_input_keyboards_test.php`.

## Why not type="url" or type="email"?
- The saves take '/cookie-policy', 'www.example.com', 'host:port', comma lists and '+billing'; the browser's own validation would refuse them. Every box stays `type="text"`; only the keyboard changes.

## What broke before?
- iOS capitalised the first letter: `escape_url()` compares 'https://' case-sensitively, so 'Https://…' was refused, and backup remotes stored 'Backups' or 'Us-east-1' as typed and failed to connect.
- The attributes stand right after `type="text"`: prep.php's value expressions contain `>=`, which a simple tag regex would stop at.
- The country boxes are not touched: the structured-data `merchant_country` and the Merchant Center card's `merchant_center_country` are upper-cased by their saves and show capitals.
