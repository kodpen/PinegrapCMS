---
name: import-site-builders-census-squarespace
description: What Squarespace lets an owner export (read 2026-11-27): the WordPress-format XML, the products CSV and its columns, the contacts files, and the reader and writers each needs
---

## Which help pages is this read from?
- Under `support.squarespace.com/hc/en-us/articles/`: S1 `206566687` Export your site content; S2 `360000694948` Export products to a .csv; S3 `115000378108` Importing products from a .csv (the columns); S4 `229497568` Managing customers; S5 `360046485652` The Contacts panel; S6 `360001280708` mailing lists; S7 `205814578` URL slugs; S8 `205814028` Import to and export from your site.

## What does the site content XML hold?
- One .xml "in a WordPress format" from Import & export content > Export; the site must be published and public, a trial site cannot export (S1).
- In it: layout pages, one blog page with its posts and up to 1,000 comments a post, gallery pages on 7.0, text and image blocks, embed text, linked PDFs (S1).
- Not in it: album, cover, index, calendar, portfolio and store pages; headers, footers, sidebars; other blog pages; drafts; audio, video and product blocks; style and custom CSS (S1). Images and videos have no bulk export (S8); image links point at Squarespace and break once the site is cancelled (S1).
- Reader: the WordPress one, `pg_import_wordpress_open()` / `_next()` (`includes/import/wordpress.php` lines 376, 421; items by `pg_import_wp_item()` line 172). Writers `pg_import_write_<kind>()`: `write_page.php` 82, `write_record.php` 345, `write_comment.php` 71, `write_file.php` 31, `write_user.php` 89.
- No page says what marks this XML apart from WordPress's own. A post's old address is `/<blog page slug>/<post slug>` (S7).

## What does the products CSV hold?
- Up to 10,000 physical and service products; download products and gift cards are not exported (S2). The import file's columns plus one for pages (S2).
- Columns (S3): Product ID, Variant ID, Product Type (Physical, Service), Product Page, Product URL, Title, Description (plain text), SKU, Option Name 1-3, Option Value 1-3, Price, Sale Price, On Sale (Yes/No), Stock (a number or `unlimited`), Categories (`/slug` paths), Tags, Weight, Length, Width, Height, Visible (Yes/No), Hosted Image URLs.
- A product's first row holds it; each row below is a variant (options, price, stock, SKU) with the product cells blank; up to 250 variants (S3).
- Not in it: additional info, reviews, featured image, alt text, variant images (S2); GTIN and MPN are import-only (S3); no unit for weight or size.
- Reader: new, building the item `pg_import_shopify_product()` builds (`includes/import/shopify.php` line 291) for `pg_import_write_product()` (`write_product.php` line 78). A service product comes in shippable (`pg_import_product_row()` line 226). Old address `/<Product Page>/<Product URL>` on 7.0, `/<Product Page>/p/<Product URL>` on 7.1 (S7), for `pg_import_redirect_old_address()` (`write_redirect.php` line 44).

## What do the contacts files hold?
- Customers export (S4): Account, First Name, Last Name, Email, Total Orders, Total Spent, Average Order Value, Date of Last Order, Customer Since, Last used Shipping Name, Address, and Phone Number, the same for Billing, Tags. No notes, no consent column.
- Contacts panel export (S5) and a mailing list's .zip of subscribed, unsubscribed and cleaned CSVs (S6): no page lists their columns.
- Reader: new, giving the data of `pg_import_shopify_customer()` (`shopify.php` line 412) to `pg_import_write_contact()` (`write_contact.php` line 133). Without consent every address is logged refused (line 168); only the subscribed file could say granted. Order totals have no contact column (`pg_import_contact_columns()` line 32).

## Which kind has no file?
- Collections: no export of its own beyond the products and the blog. Left out.
