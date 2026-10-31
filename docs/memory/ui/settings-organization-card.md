---
name: settings-organization-card
description: Site Settings General › Organization card: which fields it gathered from where, who saves them, the two support e-mail fields, the ERP Settings pointer.
---

## What is on the Organization card?
- `pgset-organization`, the first General card, since #3874: name and address (`organization_*`, were on Communication › Campaigns), Support E-mail Address (`email_address`, was on Server & Domain), Organization Logo (`organization_logo`, was on SEO), the seller's tax number and tax office (`erp_seller_vkn`, `erp_seller_tax_office`, were on the ERP card).
- Same post names and columns. `general.save.php` writes them all with the rules of the saves they left: the tax number keeps digits only in Turkey and is cut to the column's width elsewhere; the logo is written only where `og_default_image` exists, as the SEO save did.
- The logo picker is built in `prep.php` (`$output_organization_logo_picker`) next to the share image, from the same file list.
- Old links: `pgset-organization` is in `pg_settings_legacy_anchors()`, so `settings*.php#pgset-organization` lands on General. Campaigns keeps its opt-in label and footer.

## Why are there two support e-mail fields?
- Decided #3799: Organization's "Support E-mail Address" is the site's (`config.email_address`); the Affiliate Program card keeps `affiliate_email_address`, labelled "Affiliate Program E-mail Address". Its readers (`AFFILIATE_EMAIL_ADDRESS`, affiliate sign-up and approval) are unchanged.

## What does ERP Settings say?
- `erp_settings.php`'s Seller Information card (drawn only for role 3, who has no Site Settings) names General › Organization as plain text for the seller title, address, logo, tax number and office; the address the sale was made at stays on the Accounting › ERP card. Role < 3 sees no such card there (`erp/erp-settings-cards.md`).
