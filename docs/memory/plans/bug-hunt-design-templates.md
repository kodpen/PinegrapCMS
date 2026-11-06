---
name: bug-hunt-design-templates
description: Module list of plans/bug-hunt.md, design templates part (second round): one shipped template each with its state
---

## Which design templates are hunted?
- Done: pinegrap-dealership (`includes/design_templates/pinegrap-dealership.php` and folder) #7511, #7513.
- Done: pinegrap-hotel (`pinegrap-hotel.php` and folder) #7514.
- Done: pinegrap-market (`pinegrap-market.php` and folder) #7515, #7516.
- Done: pinegrap-social (`pinegrap-social.php` and folder) #7507, #7508, #7509, #7510.
- Done: pinegrap-realestate (`pinegrap-realestate.php` and folder) #7512, #7513.
- Done: pinegrap-law (`pinegrap-law.php` and folder) #7513 (the blog cover only; otherwise clean).
- Clean: pinegrap-salon (`pinegrap-salon.php` and folder).
- Not in this round (still being built): gym, charity, travel, photo, wedding.

## What did the round check?
- Every binding, list filter, search and sort field, `url_param` and `^^field^^` subject against the fields of the form it reads; filter values against select options; select defaults; duplicate field names; detail pages, access (`submitter_only`, viewer filter, private folders); PHP 7.1 syntax; variables shared across the included files. All clean but the issues above.
- Left as product limits, not filed: Hotel's booking Room list is fixed when the template opens (rooms staff add later cannot be picked); Marketplace's home and gift guide product cards are static text; departure may come before arrival (no rule compares two fields).
