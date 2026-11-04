---
name: image-same-address-switches
description: WebP at a picture's own address: bits 4 and 8 of config.image_derivative_formats, the two Image Optimization switches, clamp, new-install default, System Status line (#5350, #6144, #6667)
module: image
---

## Which switches?
- Bits of `config.image_derivative_formats` (no schema): 4 = answer the own address with WebP, needs 1 (WebP offered), so the switch is on only when `bits & 5 == 5`; 8 = the CDN in front keeps a copy per format (ticked by the operator or set by the token-free self-test of the general job and Cloudflare Tools' button, which sets it only when both copies come out of the edge's cache and takes it back, even when ticked by hand, when the edge hands one copy to all or keeps none: `cloudflare-image-vary-rule.md`). Without it a proxied request is answered private (`image-same-address.md`).
- Settings › Features › Image Optimization: "Send WebP at the picture's own address" (`image_same_address`) and "The CDN keeps a copy per format" (`image_same_address_cdn`). `pg_image_delivery_settings()` returns them as `same_address` (`on`, `cdn`); `pg_image_delivery_bits()` puts the stored number back together (bit 4 dropped without 1, unknown bits dropped), so `pg_image_settings()['derivative_formats']` and the save keep all four bits. Only bits 1 and 2 trigger the page HTML rebuild; any bit change drops the System Status cache.
- Default (owner, question 1): off on a running site (the column's 3); `install/index.php` sets bit 4 for a starter site where WebP is offered. A restored backup keeps its value.

## What does System Status say?
- "WebP At Own Address" (server group, reported, not scored), from `pg_image_same_address_state()` with the self-test's mode: `off` grey; `no_webp` yellow (on, the server writes no WebP); `private` yellow (the panel request came through a CDN/proxy, bit 8 not set: WebP is sent, the edge keeps no copy); `original` yellow (the same, but the self-test found the edge keeps private answers: the original goes); `on` green (no CDN in front, or confirmed). Links to the image card.

Related: image-same-address.md, image-delivery-settings.md
