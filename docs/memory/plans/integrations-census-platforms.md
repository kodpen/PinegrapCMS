---
name: integrations-census-platforms
description: Census (2026-11-17) of automation platforms where one published app reaches the whole catalogue: the counts they state, customer domains, triggers, listing terms, wording, embeds, open catalogues
---

## Where was it read?
- On the vendors' own pages and repositories on 2026-11-17. Make's pages refuse a direct read, so its figure comes through a fetch summary. Relay.app says it shut down in September 2026.

## What does each state, and what does a public listing ask?
- Zapier: "No code solutions to connect 9,000+ apps". Free. Sign-in by API key, Basic or OAuth 2 with PKCE, and a form may ask a site URL "for self-hosted apps" first. Triggers by REST hooks (subscribe, unsubscribe) with a sample-list endpoint, or by polling.
- Zapier's public listing needs three users with live Zaps, a test account that does not expire, API documentation and a logo; an answer "in 1 week or less", then 90 days as beta. A private invite link works before that. Its rules refuse payment-processing actions.
- Make: "3,500+" apps. The domain is asked in the connection when each user has a different one; instant triggers by webhook attach and detach calls; an invite link before review. A rule to watch: the base URL must be "a production endpoint with a domain that belongs to the app itself".
- n8n: "Browse 2,000+ integrations" (its counter shows 2,698). A verified community node is an MIT npm package; the authorization and token addresses can be fields the user fills; "Reviews can take up to four weeks"; an unverified node installs on a self-hosted n8n at once.
- Pabbly Connect: "2,000+ Integrations Available"; a webhook address the user pastes is its preferred trigger. viaSocket: "Integrate 2,300+ apps". Pipedream: "3,219 apps", and it builds the app itself from a test account. Power Automate: "More than 1,400 prebuilt, certified connectors", a host for each connection, weeks of certification. Activepieces: "775 open-source pieces", a pull request to an MIT repository.
- Left aside: IFTTT ("1,000+ apps"; one sign-in address for all users, so it needs a central server); Integrately, Zoho Flow and Latenode (no builder of one's own); Albato, Workato.

## How may the figure be worded?
- No page read sets a rule on such claims. Zapier's documents say an integration "can connect to Zapier's app directory of 9,000+ integrations"; Pipedream and IFTTT word it the same way for their partners.
- So, once a public listing exists: connects to 9,000+ apps through Zapier, naming the platform and its figure of the day, never as integrations of our own.

## What do embedding and ready catalogues cost?
- Zapier's embedded editor needs the public listing first. Pipedream Connect $99 a month and $2 for each user over 100; Activepieces Embed "From $36k a year"; Albato Embedded from $3,000 a month; n8n's OEM by agreement.
- Unified APIs are priced for a vendor with one cloud: Merge $650 a month for ten linked accounts, Apideck from $599, Unified.to from $750, Truto "From $999". Nango ("1,300+ APIs") is under the Elastic License.
- Open catalogues: n8n's 443 node files come with a licence that forbids to "Allow external end users to build or configure their own workflows through your product"; Pipedream's excludes commercial use; Activepieces' pieces are MIT and TypeScript; APIs.guru holds 2,529 API definitions under CC0, the only one PHP reads directly.

## What must a site have for these apps?
- A connection by the site's address with a key and secret is taken by every platform above: the first step, and nothing is missing for it but the sample lists.
- For OAuth: every site must accept the platform's one client. A client-ID metadata document, which the server reads today, may serve; untried with these platforms.
- An endpoint that names the account for the connection's label; REST hooks open to that connection; for each trigger a newest-first list with stable ids; a demo site with a lasting test account.
