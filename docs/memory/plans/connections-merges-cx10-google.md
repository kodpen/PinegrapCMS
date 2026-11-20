---
name: connections-merges-cx10-google
description: CX10 of plan connections-merges, what Google's and Microsoft's pages add: the two redirect addresses, the APIs to enable, the "API not enabled" error, Google's verification warning
---

## What else does CX10 put on Google's and Microsoft's pages?
- Architect (2026-11-19, message 9689; owner 2026-11-19 "evet ekleyelim kullanıcılar zorlanmasın"; after a redirect_uri_mismatch and a "Google Calendar API has not been used in project" 403 on dev): Google's page, beside the client ID and secret, gets three parts, Microsoft's the first. No schema; part of CX10.
- (1) "Google Cloud'a eklenecek dönüş adresleri": `pg_signin_redirect_uri('google')` and `pg_connect_redirect_uri()` one under the other, each with a copy button, and a note that both must be added. Microsoft's page shows the same two address boxes.
- (2) "Projede açılması gereken API'ler": the API each Google feature in use needs (Calendar, Gmail, Search Console, Analytics, Drive; read from the registry), each with a link to its enable page in Google Cloud.
- (3) Google's "API has not been used in project … or it is disabled" error reads "Google Cloud projenizde <API adı> açık değil" with the enable link; the raw text stays in the details.
- (4) "Google'ın doğrulama uyarısı" (owner 2026-11-19 "google eposta eklerkende tuzak var"), on Google's page and where a mailbox is connected with Google: an unverified app shows "Google hasn't verified this app" for sensitive scopes (mail, calendar).
- (4) The note gives three ways: a) in "Testing", add the account to "Test users" and pass the warning with "Advanced" › go to the site; b) a Google Workspace organisation's "Internal" app shows none; c) for public use, apply for Google's verification.
- (4) Also: in Testing, Google drops the granted access after 7 days and the account is connected again. Every sentence is checked against Google's current documentation when CX10 is built; none that is not certain.

- Where a mailbox is connected with Google, MG1 drew (1), the Gmail API of (2) and (4) inside the box form's Gmail part (`workspace/mail-google.md`); CX10 draws them on Google's page.

Related: docs/memory/plans/connections-merges-build.md
