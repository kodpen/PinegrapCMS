---
name: integrations-census-settings
description: Census (2026-11-17) of how well-regarded products lay out connection settings (Claude, Slack, Notion, Linear, Vercel, GitHub, Shopify, Stripe, HubSpot, Google, Atlassian) and what they share
---

## Where was it read?
- On the vendors' help pages on 2026-11-17. Slack's, Linear's, Vercel's, GitHub's, Shopify's, HubSpot's, Google's and Atlassian's came through a fetch summary, so a label may differ. OpenAI's help centre refused a read.

## What does each do?
- Claude: one directory with search and categories, "Your connectors" above it, a page for each connector with its tools and their permissions (`integrations-census-connectors.md`).
- Notion: Settings > Connections is a catalogue with search and category filters; access is also given page by page; an administrator limits what members may install (no restrictions, approved and recommended, approved only).
- Slack: "Manage apps" with "Installed Apps" and "Custom Integrations"; an app's page has "Configuration", "Authorizations" with "Revoke", and "Remove App"; administrators approve or restrict apps and take requests.
- Linear: workspace integrations installed by administrators; a person's own "Security & Access" holds API keys and "Authorized applications" with "Revoke access"; webhooks and OAuth applications under "Administration > API".
- Vercel: a marketplace split into "Native integrations" and "Connectable accounts"; access to projects is chosen at install; "Manage" shows permissions.
- GitHub: "Applications" lists authorised apps with "Revoke"; tokens under Developer settings with owner, repositories, permissions and expiry; a webhook shows "Recent deliveries" with redelivery.
- Shopify: Settings > Apps; for each app "Activity and permissions" with its latest activity and "Unused access".
- Stripe: API keys as "Standard keys" and "Restricted keys", each with Rotate, Expire and its request log; webhooks apart, a signing secret for each endpoint.
- HubSpot: "Connected Apps" opens with "Needs your attention" (expired, disconnected, errors; "Reconnect"), then "My apps" with status, installed date, installed by and last activity.
- Google Account: one page of linked apps filtered by the kind of link ("Sign in with Google", "Access to your Google Account" …), with "Remove access".
- Atlassian: the administrator's "Connected apps" for a site; each person revokes their own grants on their profile.

## What do they share?
- One catalogue with search and categories, what is connected first.
- One state on a card: connected, needs attention, not connected; an attention block on top.
- One page for a connection: the account, the permissions, what uses it, who connected it and when, the last activity, re-authorise, disconnect.
- What the product connects to is kept apart from what connects to the product: keys, authorised applications, webhooks with a delivery log.
- A person's own grants are apart from the administrator's page; administrators hold an approved list.
- For MCP tools: reads and writes apart, each "always allow", "needs approval" or "blocked", writes asking by default.
- Adding by address: a name, the URL, the sign-in found by itself, headers.

## What do they not do?
- On the pages read, none shows one service spread over several screens by the feature that uses it, which is today's state here (`integrations-census-screens.md`).
