---
name: decisions-made-product-10
description: Decisions list, product part 10 (2026-10-30 evening on): Turkish i-fold in panel lists, public invoice PDF address, e-mail triage suggestion, Workspace uploads, no collection schedule columns
---

## Where are the rules and the earlier parts?
- `decisions-made.md` (rules) and the product parts `-2` to `-9`. Reasons are on the issues.

## Do panel list searches fold i, I, ı and İ on a Turkish panel? (#3742)
- Decided (architect, 2026-10-30): yes, in every panel list when the panel or page language is `tr`: "yildiz" finds "Yıldız". Server-paged lists add a folded alternative in `pg_list_search_where()` beside the plain and `turkish_ci` LIKEs; browser lists fold both sides with `pgFoldTR` inside `pgListTurkishMatch()`. One rule with the Workspace (`workspace/workspace-text-fold.md`) and the marketplace listing. No schema, no setting; a wider match only.

## May the store publish an invoice PDF address, and does the marketplace get it by itself? (#3743)
- Decided (architect, 2026-10-30): yes. A root token page serves the archived PDF for `?t=<token>` (hash kept in `erp_invoices.public_token_hash`, `decisions-made-schema-6.md`), 404 for a wrong token or a cancelled or deleted invoice, `noindex`, the WAF rule and rate limit of `cart_quote.php`; a token is revoked by clearing the hash, a new one is made on the next need, so a secret rotation breaks no link (Trendyol asks for ten years). Behind `config.erp_auto_invoice_marketplace`: after the automatic invoice the link and number go to Trendyol (seller-invoice-links) and n11 and the Invoiced push follows when its preconditions hold; a failure shows on the Imported orders card for a manual retry; the manual form stays, pre-filled. An HMAC address without a stored token was refused (one secret, no single revocation).

## What does Pinegrap AI do with a new e-mail conversation? (#3744)
- Decided (architect, 2026-10-30, plan `incoming-email-plan.md` G8): behind a per-mailbox switch in `ws_mail_boxes.settings`, off by default and shown only with an AI key, it posts one suggestion in the card's thread per new conversation: a summary, a suggested status, a suggested member and an optional reply draft that is never sent. The mail reaches the model as quoted untrusted data; nothing is sent, assigned or changed without a member. Prerequisite: `ws_ai_author()` stops reading a mail as written by System.

## Do channel and note attachments take the import's upload steps? (#3745)
- Decided (architect, 2026-10-30): progress and Cancel over the import's multipart XHR transport (`sendFiles()` shape), no picked-file list; `notes.php` and `screen.php` take multipart beside base64 with the same `ws_upload_max_bytes()` and type checks. No schema.

## Do collection items get schedule columns? (#3746)
- Decided (architect, 2026-10-30): no. The templates' `publish_date` field and live filter schedule items; nobody asked for unpublishing; `forms` is large on every site. Recorded in `designer/collections-decisions.md`.

Related: docs/memory/workflow/decisions-made-product-9.md, docs/memory/workflow/decisions-made.md
