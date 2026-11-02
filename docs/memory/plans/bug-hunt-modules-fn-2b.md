---
name: bug-hunt-modules-fn-2b
description: Module list of plans/bug-hunt.md, part 2b: includes/fn/ modules widgets to widgets_lists_checkout, one line each with its state
---

## Which includes/fn/ modules (widgets to widgets_lists_checkout) are hunted?
- `widgets`: open (#5128: page links on a pretty address carried the router's page name; checked, clean: calendar view and grid, search results, URL filter state and titles, hide-if-empty, token fill, order view access, custom form wrap, edit chip rules, record addresses)
- `widgets_account`: open (checked, clean: return_to and account_done redirects, posted controls, tax columns, account e-mail pick and setting, subject, values)
- `widgets_ad_region`: done (#4999: a region placed twice shared its rows' ids)
- `widgets_affiliate_sign_up`: done (#4998: posted next_page_id); rest clean (prefill as the classic screen, terms, confirmation, row changes)
- `widgets_attrs`: clean (denied names and tokens, placeholders and carriers, empty switches dropped)
- `widgets_billing_information`: clean (start values, liveform restore, flags and dropped controls, page row changes as the classic page)
- `widgets_cart`: taken by fix/bug-hunt-widgets-cart (checked, clean: cart link subtotal and placement ids, express order charged totals)
- `widgets_catalog`: open (#5134: clear filters kept the attribute filters; checked, clean: RSS and JSON-LD parts, group paths and subtree, item resolution, breadcrumb rows, group tree, card templates, variant flag marks, variant attribute markers, wishlist and stock-notify controls)
- `widgets_comments`: open (checked, clean: options, order, paginate, page param and URLs, quote excerpt, link values, mask email, watcher label, row root id and anchor links)
- `widgets_comments_recent`: clean (source decisions, access and scope SQL, record gate, rows read again under the scope, titles)
- `widgets_conditions`: clean (specs and joins, denied tokens, required controls, amounts and figure grouping, every operator)
- `widgets_entity_fields`: clean (allow-lists, gates, money and dates, order and account reads; order money tokens in the visitor's currency, as the classic receipt and the order view totals)
- `widgets_error`: clean (error page pick, flags, back address, requested address)
- `widgets_express_order`: done (#5082: unticked opt-in, SMS, tax-exempt and update-contact boxes came back ticked after a refused submit)
- `widgets_fill`: clean (filter parse, case with Turkish i, number, truncate, escaping, typed fills)
- `widgets_finish`: clean (conditions, chips, row positions, hide-if-empty, stage order)
- `widgets_folder_view`: done (#5060: placed twice, shared static and row ids; rest clean: rows, access and archive as the classic folder view, hidden page types)
- `widgets_gallery`: clean (template layouts, page map, starters)
- `widgets_google_reviews`: done (#4999: placed twice, shared row ids; rest clean)
- `widgets_language`: done (#4999: placed twice, shared row ids; rows, address and query clean)
- `widgets_lists`: clean (spec, span and carriers, form record values and files, order and limit, expand pass)
- `widgets_lists_calendar`: clean (weeks, ISO week of a Sunday-first week, outside days, events)
- `widgets_lists_cart`: clean (rows, form, schedule and gift card rows, raw markup keys, cart read)
- `widgets_lists_checkout`: clean (applied offers, gift card redemption as submit_order(), recipients, cart items)
