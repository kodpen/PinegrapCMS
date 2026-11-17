---
name: integrations-census
description: Start file of the integrations census (2026-11-17, the owner's request in the agent channel): what was asked, the parts, the decisions that bind, the summary, what is still to decide
---

## What was asked?
- Owner (2026-11-17, agent channel message 7359): "@arge sen entegrasyonlar ile ilgili araştırmalar yap entegrasyonu kolay ama getirisi büyük entegrasyonları kontrol edelim. bir yerde 2000+ entegrasyon diyordu bunun gibi bir iddia ileride işimize yarıyabilir."
- And: "claude ai de bağdaştırıcılar gibi seçenekler var bunlar oldukça güzel ama şu anda github vs. bağlamalarda ayarlarda karışık durumda sonuç olarak hem onları hemde yazılımdaki ayarlar sayfasını güncellemek gerekecek."
- A census and a proposal by the R&D agent. The owner's answer: `integrations-census-order.md`.

## Which parts?
- Today: `integrations-census-today.md` and `-today-2.md` (the services), `-machinery.md` (API, webhooks, OAuth, MCP), `-screens.md` (where connections are set).
- Outside: `-platforms.md` (automation platforms), `-others.md` (other products' figures, the Turkish lists), `-aggregators.md` (one integration that brings many), `-connectors.md` (AI assistants' directories), `-settings.md` (how others lay out connections).
- `-proposal.md`: six packages and their order.

## Which decisions bind?
- One place per setting, one Site Settings dialog (owner 2026-10-30, `workflow/decisions-made-product-14.md`).
- A Pinegrap plugin bundle goes to Claude's directory; a hub server is not decided (owner 2026-11-14, `workflow/decisions-made-product-51.md`).
- New payment, carrier and marketplace drivers keep landing in the core until the add-on system opens (`decisions-made-product-6.md`, `decisions-made-product-52.md`); carriers with an open integration first (`decisions-made-product-2.md`).
- Open with the owner: Google's and Meta's verification for Kodpen and the broker (`reverse-oauth-leftovers.md`).

## What did it find?
- 79 outside services are in the code, and 12 ready-made MCP connectors; an API of 668 routes, 137 webhook events an application subscribes to by itself, an OAuth server and an MCP server on every site.
- No app on any automation platform. One public Zapier app allows saying 9,000+ apps through Zapier; Make states 3,500+, n8n and Pabbly 2,000+. ikas words its Zapier card that way.
- The figures near 2,000 elsewhere (HubSpot, Zoho, n8n, Pabbly) count other people's apps or a connector's reach, never the vendor's own work. Turkish platforms state 100 to 130, XML suppliers and their own modules counted.
- Missing from the market's lists: Hepsiburada (planned), Amazon, PttAVM, Çiçeksepeti, Pazarama, the banks' virtual POS, Sipay, Param, Papara, Mikro, Netsis, BizimHesap, Akakçe, Cimri.
- Connections are spread: GitHub in five places, AI keys in six, mail in four, and "Connections" names four things. Each site registers its own application at Google, Microsoft or GitHub before a Connect button works.
- Claude's directory now takes a listing where each user enters their own address (Verified listings only), and an install link fills its custom connector dialog.

## What did the architect say? (2026-11-17, agent channel message 7524)
- Three plans: automation platforms, the connections hub, the market's table stakes. The Claude button and the MCP Registry entry join `claude-plugin-bundle.md` (`api/claude-connector-listing.md`); MCP catalogue rows are single steps, each tried first.
- The OAuth ceiling on webhooks stays: platform apps sign in with an application's key and secret. A form for a new subscription in Application Access is a step drawn first, on the owner's yes.
- The hub is the redraw of Connected accounts under System tools › Connections: one list in code, no table, no second home for a field. Its first step moves nothing, its count is the public figure, its screen is drawn and approved first.
- The owner's polls are the architect's, each when its step is near: accounts, repositories and demo site, the figure's wording, Google's verification.
