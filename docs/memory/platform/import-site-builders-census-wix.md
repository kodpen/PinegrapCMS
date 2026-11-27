---
name: import-site-builders-census-wix
description: What Wix lets an owner export (read 2026-11-27): products CSV in two layouts, contacts CSV, collection CSV, the reader and writers each needs; no file for pages or blog posts
---

## Which help pages is this read from?
- Under `support.wix.com/en/article/`: X1 `wix-stores-exporting-your-product-list`; X2 `wix-stores-exporting-and-importing-your-products` (new product page); X3 `wix-stores-importing-products-to-wix-stores` (previous version).
- Same place: X4 `exporting-your-contacts`; X5 `wix-contacts-importing-contacts-by-uploading-a-csv-file`; X6 `cms-formerly-content-manager-exporting-content-from-your-collection`; X7 `cms-formerly-content-manager-importing-content-into-a-collection`; X8 `cms-formerly-content-manager-working-with-wix-app-collections`; X9 `wix-blog-request-exporting-blog-posts-to-other-platforms`; X10 `exporting-or-embedding-your-wix-site-elsewhere`.

## What does the products CSV hold?
- Products > More Actions > Export: physical products only, not digital ones; 5,000 rows a file (X1).
- New layout (X2): handle, fieldType (PRODUCT, VARIANT, MEDIA), name, visible, plainDescription, categorySlugs, primaryCategorySlug, media, mediaAltText, ribbon, brand, price, strikethroughPrice, cost, inventory (IN_STOCK, OUT_OF_STOCK or a number), sku, barcode, weight, packageLength/Width/Height, packageUnit, productOptionName/Type/Choices[n], modifier, unit price and pre-order columns.
- Previous layout (X3): handleId, fieldType (Product, Variant), name, description, productImageUrl (up to 15), collection, sku, ribbon, price, surcharge, visible, discountMode, discountValue, inventory, weight, cost, productOptionName/Type/Description[1-6], additionalInfoTitle/Description[1-6], customTextField[1-2].
- Reader: new, telling the layouts apart by `handle` or `handleId`, building the item of `pg_import_shopify_product()` (`includes/import/shopify.php` line 291) for `pg_import_write_product()` (`write_product.php` line 78). Brand, barcode, cost and package size are lost: `pg_import_product_row()` (line 218) writes them empty or 0. Modifiers and custom text fields have no item part.
- No slug or address column in either layout, so no redirects.

## What does the contacts CSV hold?
- Contacts > selected or by label > Export, up to 50,000, as Regular, Google or Outlook CSV; Google drops labels and keeps 5 e-mails, phones and addresses and 16 custom fields (X4).
- Named fields: e-mail and SMS subscription status, language, created date, labels, custom fields; no page lists the headers (X4, X5).
- Reader: new, giving the data of `pg_import_shopify_customer()` (`shopify.php` line 412) to `pg_import_write_contact()` (`write_contact.php` line 133); the e-mail subscription status gives `opt_in`, labels the tags.

## What does a collection CSV hold?
- CMS > the collection > More Actions > Export to CSV: the whole collection with hidden and system fields, the filtered items, or the fields only; UTF-8, times in UTC+0 (X6).
- System fields ID, Created Date, Updated Date, Owner; a reference holds the other item's ID; image and rich text cells are not described (X7). App collections (Stores, Bookings, Forms, Members) are read-only and export once shown (X8).
- Reader and writer as for Webflow's collection file (`import-site-builders-census-webflow.md`): a column mapping to a form's fields, `pg_import_write_record()` (`includes/import/write_record.php` line 345).

## Which kinds have no file?
- Blog posts: the help centre says they cannot be exported to other platforms (X9). Pages: a Wix site stays on Wix's servers, with no page export (X10). Both left out; the design comes through Import My Site (`import-design-fetch.md`).
