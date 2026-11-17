---
name: automation-platforms-steps-2
description: Plan automation-platforms, AX1 to AX4, the apps that wait for the release of 2026.11.0: the n8n node, the Zapier, Make and Pabbly Connect apps, what each needs of the owner, how each is built
---

## What waits for the release of 2026.11.0?
- AX1 (M, in the owner's repository) The n8n community node: an MIT npm package with the credentials, a trigger node and an action node. Needs of him: the repository, and an npm account to publish from. It needs no demo site: a self-hosted n8n runs in a sandbox and installs the package at once; n8n's verified list is a review of up to four weeks.
- AX2 (M, in the owner's repository) The Zapier app, private first: by invitation it serves up to 100 users and reaches the same directory. Needs of him: the repository and a Zapier developer account.
  - Then its public listing: every trigger and action tried in a test Zap, a test account that does not expire on a site the reviewers reach, the product open to the public; an answer "in 1 week or less", then 90 days as beta. Needs of him: the demo site, the listing's texts and logo, the submission.
- AX3 (M, in the owner's account) The Make app, on the same triggers and actions; an invitation link serves before the review. Needs of him: a Make account, and one question to Make before a review is asked: its rule that the base URL be "a production endpoint with a domain that belongs to the app itself", against a product whose every site has its own domain.
- AX4 (M, in the owner's account) The Pabbly Connect app. Needs of him: a Pabbly developer account. Its builder is a web form; who fills it in is settled when the step is near.
- The owner (2026-11-17, message 7562) may not open a demo site before the release, so none of these starts before it. Then in this order; each waits for AU1 and AU2, a public listing for AU3 too.

## How is each app built?
- From the contract (`api/automation-contract.md` after AU1): the same triggers, actions, searches and names on every platform.
- Sign-in: the site's address, the key, the secret. The test call is `GET /meta`; the connection is named by the site's title and host. A site whose jobs do not run is told so: deliveries are sent by the job.
- Triggers as `automation-platforms-features.md` says. A module that is off (`/meta`) hides its triggers.
- Every write is a POST (`api/external-api-iis-environment.md`) and carries an `Idempotency-Key`; a 429 waits as `Retry-After` says.
- No payment action: Zapier's rules refuse them.

## What does the work on an app keep?
- Nothing in a public repository or listing tells of a session, a model or this repository's process; no key, secret or customer data; English texts (as `claude-plugin-bundle.md`).
- A session opens no account and accepts no terms: the owner submits from his own accounts.
- Each app is tried against a throwaway install before it is shown (`workflow/sandbox-on-windows.md`).
- The setup pages are user documentation: on pinegrap.com, never here (`workflow/owner-rules.md`).

## When may a figure be said?
- Once an app is public on a platform: "connects to 9,000+ apps through Zapier", with the platform's name and its figure of the day, never as integrations of our own (`integrations-census-platforms.md`). The wording and its place are the owner's.
- The plan's files are deleted when AX1 to AX4 are done or given to a plan of their own.
