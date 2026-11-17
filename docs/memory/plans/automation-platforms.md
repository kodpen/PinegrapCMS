---
name: automation-platforms
description: Plan (owner 2026-11-17): a site reached from Zapier, Make, n8n and Pabbly Connect, by a published app or by a pasted address; features A1 to A13, steps AU1 to AU10, the apps AX1 to AX4 after 2026.11.0
priority: 4
---

## What is asked?
- Owner (2026-11-17, agent channel message 7359): "entegrasyonu kolay ama getirisi büyük entegrasyonları kontrol edelim. bir yerde 2000+ entegrasyon diyordu bunun gibi bir iddia ileride işimize yarıyabilir."
- Owner (2026-11-17, typed to the architect, message 7562; `workflow/decisions-made-product-69.md`): "entegrasyonlar ile ilgili olumlu düşünüyorum ancak 2026.11.0 yayınlanmadan önce bir test demosu açamayabilirim, bağdaştırıcı hazırlıklarımızı yaparız ancak 2026.11.0 yayınlanması sonrasına bırakırız. bugün engel olmayanları 4/10 öncelikle onaylarız."
- The study behind it: `integrations-census.md`; what goes now and what waits: `integrations-census-order.md`.

## What is the idea?
- One published app on an automation platform reaches that platform's whole catalogue: Zapier states 9,000+ apps, Make 3,500+, n8n and Pabbly Connect 2,000+ (`integrations-census-platforms.md`).
- The apps live outside this repository. The steps here are what every app relies on, and a second way that needs no app: a subscription made on the panel that sends events to an address a platform gives.

## What is there today, and what is in the way?
- An application's key and secret reach the whole API; with `webhooks:manage` it subscribes to events by itself, signed and tried six times (`api/api-webhook-events.md`). `GET /meta` names the site and `/.well-known/pinegrap.json` leads from its address to its API: no new route is needed to name a connection.
- An event carries its record's id and little else (`order.created`: `id`, `order_number`); the record is read from its own route.
- In the way: an application may hold ten subscriptions (409 `too_many_webhooks`) and a platform makes one for each automation; only `/orders` lists the newest first, and a platform's editor shows a trigger's newest records as samples.
- What an event carries: fields and a sample beside the catalogue for the shop's events since AU4 (`api/api-webhook-event-payloads.md`); the rest come with AU5 and AU6.
- The panel lists, stops and removes subscriptions and cannot make one; "New application" offers 60 scopes and no starting point.

## What is built, and by which steps?
- The features: `automation-platforms-features.md`. AU1 to AU10 and their order: `automation-platforms-steps.md` and `automation-platforms-steps-2.md`. What no step may lose and what a PR carries: `automation-platforms-rules.md`.
- Claim as `[plan:automation-platforms] <step> — <topic>`.

## What waits for the release of 2026.11.0?
- The apps: the n8n node (AX1), the Zapier app and its public listing (AX2), the Make app (AX3), the Pabbly Connect app (AX4). Each with what it needs of the owner (his repositories, developer accounts, the demo site): `automation-platforms-steps-3.md`.
- Nothing of AU1 to AU10 needs an account, a demo site or another repository.

## Whose look?
- Every step with a screen or a page: the architect's (agent channel message 2645).
- The owner's: AU8's sheet, one short question before AU9: a new form and two new controls on a screen he uses.

## What is left out?
- OAuth sign-in for the apps: possible on paper for Zapier and Make, but it brings no instant trigger (`api/oauth-client-automation-platforms.md`).
- Payment actions; embedded automation, other vendors' catalogues, IFTTT, Power Automate (`integrations-census-platforms.md`, `integrations-census-proposal.md`).
- Descriptions of parameters and example bodies in the API document: plan `api-clean-modern`.
- Any count stated in the product before an app is public.
- No schema change in any step.
