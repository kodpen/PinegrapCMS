---
name: erp-parasut-devhub-application
description: Paraşüt's partner application (2026-11-26): what was entered, the two fixed redirect addresses, the staging client and company, a partner client on a self-hosted site
---

## What was applied for?
- Owner (2026-11-26): the partner application ("İş Ortağı Başvurusu") on `devhub.parasut.com`, as Paraşüt's support told him to; not the one-company application of a Paraşüt user. It is under review: a link to finish it comes by e-mail when it is approved.
- Entered: application name "Pinegrap"; web site `pinegrap.com`; the state "live, with a Paraşüt integration, moving to the new API" with the earlier demo client's id; about 10 companies; a server-side (confidential) client; a staging environment asked for.

## Which redirect addresses does the client have?
- Two, and they cannot be changed (a change is a new application; at most three):
  - `https://dev.pinegrap.com/pinegrap/connect.php`, dev's own return address (`pg_connect_redirect_uri()`);
  - `https://pinegrap.com/pinegrap/connect.php`, meant as the one address customers' sites could return through. Nothing is built there for it.
- The staging client's redirect address is `https://dev.pinegrap.com/pinegrap/connect.php`; staging addresses can be changed and added on the portal's "API Bilgilerim" page.

## What did the staging answer give?
- At once, before the review: a staging client (id and secret on "API Bilgilerim") and a Paraşüt staging company, number 35477, with its own user and a temporary password shown once. Paraşüt deletes the staging company six months after it was made (about 2027-05-26).
- No secret, password or user name is in the repository or in memory: the owner holds them.
- Staging signs in at `id-staging.parasut.com` (the endpoints of its `/realms/parasut/.well-known/openid-configuration`). The portal's pages name no API address for staging; the first connect on dev (2026-11-26) found `api-staging.parasut.com`, as `api.parasut.com` refuses a staging token (`erp-parasut-devhub-staging.md`).

## What do Paraşüt's plans allow?
- A Paraşüt user's own client: one company. "Temel" is free, 10 requests in 10 s and 25,000 a month; "Plus" (50,000, with staging) and "Pro" (150,000, with webhooks too) are announced, not yet sold.
- A partner client: several companies under one client, a plan agreed with Paraşüt.

## What does a partner client mean for a self-hosted site?
- Pinegrap runs on each customer's own server and address. A partner client is one id and secret with at most three fixed return addresses, and its secret must stay on a server its holder runs.
- So a customer's site cannot use the partner client by itself: its own address is not a registered return address, and the secret cannot be shipped in the software.
- Two ways stay:
  - As built (#11694): each site saves its own client, got by its owner as a Paraşüt user; nothing of ours in between (`erp-parasut-devhub-connection.md`).
  - A relay we run at the registered address: it takes the return, exchanges the code and renews tokens with the partner secret for the site that asked. Customers then need no application of their own, and every Paraşüt connection depends on that server. Not decided, not planned: the owner's choice once the application is approved.
- dev itself is a registered return address, so dev connects with the partner client or the staging client as any site does with its own.

Related: docs/memory/erp/erp-parasut-devhub-client.md, docs/memory/erp/erp-parasut-devhub-connection.md
