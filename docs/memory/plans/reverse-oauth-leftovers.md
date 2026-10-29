---
name: reverse-oauth-leftovers
description: Remaining outside-account connections (calendar, notes, cloud picker, Meta, Merchant, GA4, Business Profile, social, AI), the connect.pinegrap.com broker and open decisions
---

## What is done and what is left?
- Done: shared connection layer (model A, #282), e-mail through Gmail / Microsoft 365 (#294), Search Console (#310), Microsoft and Apple sign-in (#306) and Google sign-in (#318), backups to Drive / OneDrive / Dropbox (#312), carrier REST APIs UPS / FedEx / USPS with `client_credentials` (#302, #308, #314), Workspace MCP client and remote agent (#347–#361). Rules: `api/api-connected-accounts.md`, `api/api-connected-accounts-usage.md`.
- Nothing below starts until the owner picks it; each picked item is its own thread and PR.

| Item | What | Effort |
|---|---|---|
| Calendar sync | Step 0: personal secret iCal address read with the `ws_holiday_feeds` mechanism as a read-only layer. OAuth step: write Workspace tasks to the person's calendar and read changes back (Google `syncToken`, Graph delta; scopes `calendar.events`, `Calendars.ReadWrite`). Touches `includes/workspace/calendar_feed.php`, `workdays.php`, `workspace_calendar.php` | S / M–L |
| Notion, OneNote | Notion first through its hosted MCP server (remote agent alias, no provider code); else Notion OAuth with blocks ↔ Markdown. OneNote via Graph `Notes.ReadWrite`. Imported note is an ordinary `ws_notes` row. Google Keep, Evernote, Apple Notes: no usable API | M (S via MCP) |
| Cloud file picker | Google Picker + `drive.file`, OneDrive picker, Dropbox Chooser (app key only). Download on the server, then the normal upload rules | S–M |
| Meta | Product feed (step 0, shared with Merchant), Meta Business Extension for Pixel + catalog, Instagram Login feed, WhatsApp Cloud API. Model B only | L |
| Merchant Center | Merchant API (Content API for Shopping shut 2026-08-18). Slice 1 done (#3213, `commerce/merchant-center.md`). Left: per-item push + sync table (schema question) | M |
| GA4, Business Profile | Admin/Data API; Business Profile needs Google's access form (5 read-only reviews: Places API key is enough). On demand | M |
| Social posting | Facebook Page, Instagram, LinkedIn; needs app review; X is paid, not recommended | M + process |
| AI accounts | Not recommended (subscription OAuth is limited to Anthropic's own products). Only option: OpenRouter PKCE as "bring your own model" in `includes/workspace/ai.php` | S |

## What are models A and B?
- A (built): each site registers its own app at Google / Entra / Meta; the consent screen shows the site; Google sensitive scopes show the unverified warning and a 100-user cap unless the Workspace org marks it Internal.
- B (not built): Kodpen registers one verified app per provider; sites connect through `connect.pinegrap.com`. Flow: the site sends a start request signed with its licence key (site URL, provider, scopes, its PKCE challenge) → the person consents → the broker swaps the code with Kodpen's secret → redirects the browser to the site's `connect.php` with a one-time hand-off code → the site fetches the token server to server with its PKCE verifier. The broker stores no tokens; on refresh the site sends its refresh token and the broker adds the secret. Revocation goes to the provider directly. B is a licence feature; once ready it becomes the default for operator connections, A stays for customer sign-in.

## Which owner decisions are open?
- Apply for Google verification for Kodpen and run the broker: yes / no.
- Meta business verification and App Review for Kodpen (Meta, social posting): yes / no.
- OAuth server phase E, "Sign in with Pinegrap" (OpenID Connect, `id_token`): not started, waits for a decision.

## What must be rechecked before starting?
- Provider terms were read on 2026-10-13 from public pages; recheck in the provider console when applying (Google scope classes for `webmasters`, Microsoft MC786329 dates, FedEx migration dates). Live tests run on dev with the owner's accounts.
