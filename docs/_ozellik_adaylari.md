---
name: _ozellik_adaylari
description: Feature gap research of 2026-10-19 behind the approved plans: competitor tables (global builders, Turkish SaaS, headless CMS), 2025-26 regulation facts, sources (research, not decisions)
---

# Feature candidates — research note (2026-10-19)

**Status:** research, not decisions. The decisions are in `docs/memory/plans/feature-roadmap-2026-10.md` and the plan files it lists. This note keeps the evidence those plans rest on so it is not searched for twice. Everything below was read on 2026-10-19 from the pages listed under Sources; competitor cells show what the vendor's own page states (✓ stated, ◐ partial or via add-on, — not stated on the pages read).

## 1. Method

Four code scans of `development` @ fd9de20 (commerce; content and designer; platform, security and ops; Workspace, ERP, API and AI) checked about 90 candidate features in code, `tr.json` and migrations and marked each as exists / partial / absent. Those findings are inside each plan file's "Why?" section. The market side compared 7 global builders, 4 Turkish e-commerce SaaS products, 10 headless or open-source products and the 2025–26 regulation that touches a self-hosted shop.

## 2. Global site builders × Pinegrap

| Capability | Webflow | Framer | Wix Studio | WordPress 7 | Shopify | Pinegrap (today) |
|---|---|---|---|---|---|---|
| Reusable components | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ shared components |
| Design variables / tokens | ✓ | ◐ | ◐ | ✓ | ◐ | ◐ looks and palettes as CSS variables |
| Interaction / animation builder | ✓ GSAP | ✓ | ✓ | — | ◐ | — |
| Collections + dynamic pages | ✓ | ✓ 100K items | ✓ | ✓ | ◐ | ◐ forms as collections, no relations |
| Version history / restore | ✓ | ✓ snapshots | ✓ | ✓ visual revisions | ◐ | — browser-local snapshots only |
| Scheduled publishing | ◐ | ◐ | — | ✓ | ✓ Rollouts | — |
| Staging / test site | ✓ | ✓ | ✓ traffic % | — | ✓ | — (no working copy, owner decision) |
| Real-time co-editing | ✓ | ✓ | ✓ | ◐ | — | — lock + view mode |
| Figma import | ✓ | ✓ | ✓ | — | — | — |
| AI page/section generation | ✓ | ✓ agent | ✓ | ◐ | ✓ | ✓ proposal engine |
| Agent / MCP access | ✓ | ✓ | ✓ WebMCP | ✓ Abilities API | ✓ Sidekick | ✓ OAuth 2.1 + ~296 tools |
| Localization (hreflang) | ✓ | ✓ | ✓ | — | ✓ Markets | ✓ (no translated slugs) |
| Accessibility checker | ✓ contrast | ◐ | ✓ | — | — | ◐ 13 rules, no contrast |
| Image CDN / WebP-AVIF / srcset | ✓ | ✓ | ◐ | ◐ | — | — manual WebP, no srcset |
| Form logic / multi-step / payments | ◐ | ◐ | ✓ | — | ◐ | ◐ logic only in legacy forms |
| Memberships / paywall | — (sunset Jan 2026) | — | ◐ | ✓ | — | ✓ |
| Redirect manager (301, CSV) | ✓ | ◐ | ✓ 500 bulk | — | — | ◐ short links only, no 404 log |
| AI visibility / AEO analytics | ✓ | ◐ | ✓ | — | — | — |
| Built-in analytics | ✓ click maps | ✓ funnels, CWV | ✓ | — | ✓ | ✓ visitor reports |
| A/B testing / personalization | ✓ Optimize | ✓ 5 variants | ◐ | — | ✓ | — |
| App / plugin marketplace | ✓ 420 | ✓ | ✓ 500+ | ✓ | ✓ | — no add-on system |

Notable 2025–26 moves: Webflow made real-time collaboration default (Feb 2026) and exposes Analyze/Optimize/AEO data over MCP (Oct 2026); Framer added a List field, Skills and staging with deploy/revert; Wix Studio shipped Test Sites with traffic percentages and WebMCP (Oct 2026); Squarespace added block animations (Apr 2026) and an AI Visibility dashboard (Jul 2026); WordPress 6.9 added Notes and the Abilities API, 7.0 (May 2026) the AI Client/Connectors, visual revisions and per-device block visibility; Elementor 4.0 (Mar 2026) added Variables, Classes, Components; Shopify shipped Agentic Storefronts, Rollouts (scheduled/gradual theme publishing) and Canvas (Oct 2026).

## 3. Turkish e-commerce SaaS × Pinegrap

| Expectation | ikas | IdeaSoft | Ticimax | T-Soft | Pinegrap (today) |
|---|---|---|---|---|---|
| Marketplace sync (Trendyol, Hepsiburada, n11, Amazon TR…) | ✓ 19 TR + 7 intl | ✓ 8+ (returns, Q&A, invoice link) | ✓ ~30 | ✓ 19 + 14 | ◐ n11 only |
| Carrier integrations + labels | ✓ 10 | ✓ 5 | ✓ 26 | ✓ 24 | — tracking links only |
| Contracted cargo without carrier contract | ✓ ikas Kargo | ✓ Kargonomi | ✓ | ✓ Cargong | — |
| Local payments (bank vPOS, iyzico, PayTR, Sipay, Param…) | ✓ own vPOS | ✓ all banks | ✓ 15 banks, ~40 PSPs | ✓ 22 banks | ◐ iyzico (3DS, installments) |
| BNPL / shopping credit | — | — | — | ✓ | — |
| SMS provider (Netgsm) + order/OTP SMS | ✓ | ✓ | ✓ | ✓ | — |
| İYS consent sync | ◐ via Netgsm | ✓ app | ✓ two-way API | — | — |
| WhatsApp (cart reminder, order) | ✓ | ◐ | ✓ | ✓ | — share button (Meta planned) |
| e-Fatura/e-Arşiv auto issue, several integrators | ✓ 4 | ✓ 6 | ✓ | ✓ | ◐ İşbaşı test; Paraşüt empty; manual step |
| Auto pre-information + distance contract, KVKK texts | ✓ | ✓ placeholders, stored on order | — | — | — terms link only |
| Reviews (verified buyer, reminder, reply) | ✓ | — | ◐ | ✓ | ◐ starred page comments |
| Wishlist / back in stock | ◐ | — | ✓ | — | — |
| Bundles | ✓ | ✓ | ✓ | ✓ | — |
| Spendable loyalty points | ✓ | — | — | ✓ | ◐ earn only |
| Google Merchant / Meta feeds | ✓ XML | ✓ | ✓ + OpenAI Ads | ◐ | ◐ `?rss=true` Google fields |
| Campaign engine, basket discounts, abandoned cart | ✓ e-mail+SMS+WhatsApp | ✓ | ✓ 400+ templates | ✓ | ✓ rules; e-mail only |
| B2B / dealer (account, credit limit, quotes) | ✓ | ◐ | ✓ | ✓ | ✓ ERP |
| Mobile app | ✓ | ◐ | ✓ | ✓ | — (shelved) |
| AI headline 2026 | ✓ image generation, analytics | ✓ Admin MCP Server | ✓ video, model shots | ✓ Nova Action | ✓ MCP + assistants |

Trendyol retired V1 order endpoints on 15 Oct 2026 (HTTP 426 brownouts); V2 has webhooks, a cursor stream and rate limits tied to listing limits. Shopify Payments is not available in Türkiye (41-country list), so local PSPs decide.

## 4. Headless and open-source products

All of Payload 3, Strapi 5, Sanity, Directus, Statamic 6 and Craft 5 keep versions and drafts; most schedule publishing and offer review workflows (often in a paid tier); all shipped an MCP server in 2025–26 (Strapi GA, Directus native, Payload plugin, Sanity hosted). Craft 5 has native control-panel passkeys; WordPress core has none (ticket closed as duplicate, plugin route only). Ghost 6 ships Docker Compose by default, `llms.txt` and ActivityPub; Medusa 2 and Saleor publish MCP/agent docs; Odoo 19 has a Türkiye localization through Nilvera (e-Fatura, e-Arşiv, e-İrsaliye).

## 5. Regulation and platform facts used by the plans

- İYS (Ticari İletişim ve Ticari Elektronik İletiler Hakkında Yönetmelik, as amended RG 30998, 4 Jan 2020): senders register; consents taken outside İYS recorded within three business days; consents not recorded in İYS are invalid; refusals forwarded and sending stopped within three business days; records kept three years; every message names the sender (MERSİS/trade name) and offers a free opt-out; service notices (billing, purchase, delivery) are exempt. 2026 fines (RG 33118, 25 Dec 2025): 2,859–14,309 TL per unsolicited message, 5,723–42,930 TL for opt-out violations.
- Mesafeli Sözleşmeler Yönetmeliği (consolidated, in force 1 Jan 2026): pre-information content (features, total price with taxes, withdrawal conditions and the named return carrier, dispute venues with mediation note); 14-day withdrawal; delivery within 30 days; refund within 14 days after goods reach the named carrier; return shipping free when the named carrier is used (RG 32909, 24 May 2025); platforms jointly liable, records three years.
- ETBİS (Tebliğ RG 30151, 11 Aug 2017): own-site sellers register before starting and report payment, logistics and infrastructure providers within 30 days.
- e-Belge: VUK 509 as amended by 535 (RG 31727): sellers on their own or intermediaries' sites with gross sales ≥ 500 Bin TL join e-Fatura from the seventh month after that year (general threshold 3 Milyon TL); e-İrsaliye ≥ 10 Milyon TL. Tebliğ 588/589 (RG 33124, 31 Dec 2025): from 1 Jan 2026 taxpayers outside e-Fatura/e-Arşiv issue every invoice as e-Arşiv via the GİB portal regardless of amount; 2026 invoice threshold 12,000 TL.
- Pricing: Fiyat Etiketi Yönetmeliği (RG 31754, from 1 Mar 2022): discount baseline is the lowest price of the previous 30 days, both prices shown. Ticari Reklam Yönetmeliği amendment (RG 33297, 1 Jul 2026, effective 1 Aug 2026): in discount advertising the pre-discount price is the lowest of the previous 10 days per channel; only verified-purchaser reviews, kept at least one year; AI-generated ad disclosure.
- KVKK Çerez Rehberi (2022, revised July 2025): opt-in only; accept/reject/preferences with equal prominence; no pre-ticked categories; per-purpose consent; withdrawal panel; cookie inventory in the notice.
- Accessibility: Cumhurbaşkanlığı Genelgesi 2025/10 (RG 32933, 21 Jun 2025): e-commerce providers under Law 6563 meet the Level A checklist plus WCAG 2.2 within two years (~June 2027). EU Accessibility Act applies to e-commerce since 28 Jun 2025 (microenterprises exempt); EN 301 549 v4.1.1 (2 Sep 2026) moves the baseline to WCAG 2.2 AA.
- Consent Mode v2: `ad_user_data` and `ad_personalization` required for EEA advertisers since March 2024; without a default-denied call before tags, remarketing audiences are lost.
- AI Act: general application 2 Aug 2026 with transparency duties (chatbot disclosure, labelling of AI-generated content); high-risk deadlines delayed by the omnibus to Dec 2027 / Aug 2028.
- Agentic commerce: OpenAI ACP sellers implement `POST /checkout_sessions`, `…/{id}`, `…/complete`, `…/cancel`, `GET …/{id}` with `API-Version`, `Idempotency-Key` and `Signature` headers and HMAC-signed `order_created/updated` webhooks; feed with nine required fields and eligibility flags. Google UCP (Jan 2026): `/.well-known/ucp` manifest, capability `dev.ucp.shopping.checkout`, Merchant Center feed, PSP accepting Google Pay tokens; FAQ updated 16 Oct 2026. Stripe shared payment tokens are available in US/CA/GB/EEA, not Türkiye. Shopify Agentic Storefronts syndicate the catalog to ChatGPT, Perplexity and Copilot; WooCommerce published a Claude Commerce Agent guide (16 Sep 2026).
- AI search: Google states it does not use `llms.txt` and that no special files or schema are needed for AI Overviews/AI Mode; Cloudflare's Content Signals policy (Sep 2025) and default blocking of training and agent bots on new zones (from 15 Sep 2026) matter for crawl policy.
- Web platform: CrUX reports the share of origins with good INP falling from 87.2 % (Mar 2026) to 85.3 % (Aug 2026); AVIF has 96 % browser support; Web Almanac 2024 shows `srcset` on 42 % of pages and AVIF at 1 % of image requests; FIDO estimates ~5 billion passkeys (May 2026).

## 6. Sources

Global builders: webflow.com/blog/2026-builder-keynote · webflow.com/updates · webflow.com/optimize · webflow.com/localization · framer.com/updates/july-update-2026 · framer.com/help/articles/staging-and-versions/ · framer.com/cms · wix.com/studio/features · support.wix.com/en/article/about-test-sites · wix.com/seo · squarespace.com/blog/squarespace-refresh-2025-product-releases · squarespace.com/blog/product-updates-july-2026 · wordpress.org/news/2025/12/gene/ · wordpress.org/news/2026/05/armstrong/ · make.wordpress.org/core/2026/05/14/wordpress-7-0-field-guide/ · elementor.com/blog/?p=153661 · developer.woocommerce.com/2026/08/04/woocommerce-11-0/ · developer.woocommerce.com/2026/09/16/wc-claude-commerce-agent/ · shopify.com/editions/winter2026 · help.shopify.com/en/manual/markets-new/rollouts · changelog.shopify.com/posts/design-a-fully-bespoke-store-with-canvas

Turkish SaaS: ikas.com/tr/e-ticaret-paketleri · ikas.com/tr/kargo-ve-lojistik-entegrasyonlari · ikas.com/tr/e-fatura-entegrasyonlari · ikas.com/tr/blog/ikas-eylul-2025-guncellemesi-yayinda · ideasoft.com.tr/ozellikler/ · ideasoft.com.tr/yardim/trendyol-entegrasyonu/ · ideasoft.com.tr/yardim/sozlesmelerin-duzenlenmesi/ · ideasoft.com.tr/yardim/netgsm-iys-app-entegrasyonu/ · ideasoft.com.tr/sayfa/admin-mcp-server/ · ticimax.com/ozellikler/ · ticimax.com/e-ticaret-pazaryeri-entegrasyonlari/ · ticimax.com/kargo-entegrasyonlari/ · ticimax.com/ticimax-iys-ileti-yonetim-sistemi-entegrasyonu/ · tsoft.com.tr/e-ticaret-lojistik-entegrasyonlari · tsoft.com.tr/odeme-sistemleri-entegrasyonlari · tsoft.com.tr/b2b-bayi-sistemi-e-ticaret · help.shopify.com/en/manual/payments/shopify-payments/supported-countries · apps.shopify.com/turkiye-eticaret-sozlesmeleri · developers.trendyol.com/changelog/changelog.md · developers.trendyol.com/docs/ortak-etiket-barkod-talebi-createcommonlabel.md · docs.iyzico.com/urunler/alisveris-kredisi/alisveris-kredisi-entegrasyonu

Headless and open source: payloadcms.com/docs/versions/overview · payloadcms.com/docs/plugins/mcp · strapi.io/blog/the-strapi-mcp-server-is-now-ga · docs.strapi.io/cms/features/releases · sanity.io/docs/content-releases · directus.com/docs/guides/content/content-versioning · directus.com/docs/guides/ai/mcp · statamic.dev/content-modeling/revisions · craftcms.com/docs/5.x/system/drafts-revisions.html · craftcms.com/docs/5.x/system/user-management.html · ghost.org/changelog/ · medusajs.com · odoo.com/documentation/19.0/applications/finance/fiscal_localizations/turkiye.html

Agentic commerce and AI search: openai.com/index/buy-it-in-chatgpt/ · developers.openai.com/commerce/specs/checkout · developers.openai.com/commerce/specs/feed · docs.stripe.com/agentic-commerce/concepts/shared-payment-tokens.md · developers.googleblog.com/en/under-the-hood-universal-commerce-protocol-ucp/ · developers.google.com/merchant/ucp/faq · shopify.com/news/winter-26-edition-agentic-storefronts · claude.com/blog/claude-for-commerce-agents · developers.google.com/search/docs/appearance/ai-features · developers.google.com/search/docs/fundamentals/ai-optimization-guide · blog.cloudflare.com/content-signals-policy/ · developers.cloudflare.com/changelog/product/bots/ · developer.chrome.com/docs/crux/release-notes/ · caniuse.com/avif · almanac.httparchive.org/en/2024/media · fidoalliance.org/fido-alliance-reports-accelerating-global-passkey-adoption-on-world-passkey-day-2026/

Regulation: turmob.org.tr/arsiv/mbs/resmigazete/30998-30998-1.pdf (İYS) · erdem-erdem.av.tr/bilgi-bankasi/elektronik-ticaret-kanunu-kapsaminda-idari-para-cezalari-2026-yili-icin-guncellendi · lexpera.com.tr/mevzuat/yonetmelikler/mesafeli-sozlesmeler-yonetmeligi · alomaliye.com/2025/05/24/mesafeli-sozlesmeler-yonetmeliginde-degisiklik-24-05-2025/ · lexpera.com.tr/mevzuat/tebligler/elektronik-ticaret-bilgi-sistemi-ve-bildirim-yukumlulukleri-hakkinda-teblig · turmob.org.tr/arsiv/mbs/resmigazete/31727-2.pdf (VUK 535) · alomaliye.com/2025/12/31/vergi-usul-kanunu-genel-tebligi-sira-no-589/ · kvkk.gov.tr/Icerik/7353/Cerez-Uygulamalari-Hakkinda-Rehber · turmob.org.tr/arsiv/mbs/resmigazete/31754-2-2.pdf (Fiyat Etiketi) · ticaret.gov.tr/haberler/ticaret-bakanligindan-indirimli-satis-reklamlarinda-seffafligi-artiracak-yeni-duzenleme · alomaliye.com/2025/06/21/web-siteleri-ve-mobil-uygulamalarin-erisilebilirligi-2025-10/ · eur-lex.europa.eu/legal-content/EN/TXT/HTML/?uri=CELEX:32019L0882 · w3.org/TR/WCAG22/ · support.google.com/tagmanager/answer/14754100 · digital-strategy.ec.europa.eu/en/policies/regulatory-framework-ai

Not reachable on 2026-10-19 (secondary sources used): resmigazete.gov.tr, mevzuat.gov.tr, ebelge.gib.gov.tr, iys.org.tr, developers.hepsiburada.com.
