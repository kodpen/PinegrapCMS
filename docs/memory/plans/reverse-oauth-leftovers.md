---
name: reverse-oauth-leftovers
description: Remaining outside-account connections (Meta, Business Profile, social posting, AI accounts), the connect.pinegrap.com broker and open decisions
---

## What is done and what is left?
- Done: the connection layer (model A) and every connection built on it, #282 to #4658 (mail, Search Console, sign-in, backups, carrier REST, MCP, calendar sync, Notion, OneNote, file picker, Merchant Center, GA4, Google reviews); rules: `api/api-connected-accounts.md`, `api/api-connected-accounts-usage.md`.

| Item | What | Effort |
|---|---|---|
| Meta | Product feed (step 0, shared with Merchant), Meta Business Extension for Pixel + catalog, Instagram Login feed. Model B only. WhatsApp left this row: `whatsapp-channel.md`, the site's own app (owner 2026-11-12, #8950) | L |
| Business Profile | Needs Google's access form; on demand | M |
| Social posting | Facebook Page, Instagram, LinkedIn; needs app review; X is paid, not recommended | M + process |
| AI accounts | Not recommended (subscription OAuth is limited to Anthropic's own products); only OpenRouter PKCE as "bring your own model" (`includes/workspace/ai.php`) | S |

## What are models A and B?
- A (built): each site registers its own app at Google / Entra / Meta; the consent screen shows the site; Google sensitive scopes show the unverified warning and a 100-user cap unless the Workspace org marks it Internal.
- B (not built): Kodpen registers one verified app per provider; sites connect through `connect.pinegrap.com`: a start request signed with the licence key (site URL, provider, scopes, PKCE challenge) → consent → the broker swaps the code with Kodpen's secret → the browser returns to the site's `connect.php` with a one-time code → the site fetches the token server to server with its PKCE verifier. The broker stores no tokens (refresh: the site sends its refresh token, the broker adds the secret); revocation goes to the provider. A licence feature; once ready the default for operator connections, A stays for customer sign-in.

## Which owner decisions are open?
- Google verification for Kodpen and the broker: yes / no.
- Meta business verification and App Review for Kodpen (Meta, social posting): yes / no.
- OAuth server phase E, "Sign in with Pinegrap" (OpenID Connect, `id_token`): waits for a decision.

## What must be rechecked before starting?
- Provider terms were read on 2026-10-13 from public pages; recheck in the console when applying (Google scope classes, Microsoft MC786329 dates, FedEx migration dates). Live tests on dev with the owner's accounts.
