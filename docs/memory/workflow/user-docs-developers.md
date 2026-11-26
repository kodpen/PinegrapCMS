---
name: user-docs-developers
description: Outside developers of the API (owner 2026-11-26): their pages sit in the documentation on pinegrap.com, no developers subdomain, no staging run by us, no central client registry
---

## Where does documentation for developers of the API go?
- Owner (2026-11-26, in the architect's window): into the documentation on pinegrap.com, beside the other pages (`/api` is there today). No `developers.pinegrap.com`: "api bilgisini bu alanda paylaşabiliriz subdomain gerek yok".
- Written as every documentation page is: by a session on the owner's machine, never in the repository (`user-docs-writing.md`, `user-docs-pages.md`).

## Do we run a staging or test site for developers?
- No. Owner (2026-11-26): Pinegrap is free and needs no licence, so a developer installs it on their own machine and tries the API there. The panel's licence check does nothing without a key (`platform/license-check.md`).
- Architect (2026-11-26): every install is its own test environment. A shared demo site is looked at again only if trying without an install is asked for often.

## Is there a central registry of clients, as Paraşüt's Developer Portal has?
- No. Architect (2026-11-26): each site gives its own keys on Application Access and is its own OAuth server, where a client names itself by its own address (`api/api-oauth-server.md`). A central issuer would make every site's connections depend on a server of ours (`erp/erp-parasut-devhub-application.md` shows what that costs a self-hosted site).
- A directory of checked applications, which lists and issues nothing, belongs to the addons work after the launch (`after-launch-roadmap.md`).

## What do the pages lack for an outside developer?
- Not planned, not asked (2026-11-26): a first-call guide (install, sample records, a key, one call); OAuth, webhooks and MCP told for an outside application; the OpenAPI reference in one place. Each site serves its own OpenAPI document today.
- Sample records are set up today only by `tools/setup_sandbox.sh`, a tool for our own sessions (`sandbox-and-environment.md`); a version for outside developers would be part of that work.
