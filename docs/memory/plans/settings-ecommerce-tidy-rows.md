---
name: settings-ecommerce-tidy-rows
description: Plan settings-ecommerce-tidy: the eleven folded rows of Site Settings › Ecommerce, what each says closed (line, pill, attention) and what it holds when open
---

## Which rows, in which order?
- Store, Payment methods, Shipping, Address autocomplete, Stock locations, Gift cards, Rewards, Reference price, Affiliate programme, Referral programme, Product feeds: a shop's set-up first, its programmes after. Purpose line: "How the shop sells, takes payment and ships."
- While the store is off every row's pill is Off and its line says its own state still; no row asks for attention.

## What does each row say closed?
- Store: "Next order number 10234 · tax-exempt orders allowed" from what is set; pill On.
- Payment methods: the methods that are on by name ("Stripe, PayPal, bank transfer"); with the store on and none on, "No payment method is on", amber pill "None", attention; a gateway picked but not connected, "Connect {gateway} in Connections", attention.
- Shipping: "One recipient an order" or "Several recipients an order", then the carriers connected by name.
- Address autocomplete: the provider's name, or "Off".
- Stock locations: "3 locations, 1 off", or "Not in use".
- Gift cards: "Accepted, valid 365 days", "Accepted, never expire", or "Off".
- Rewards: "On: 100 points to the goal", or "Off".
- Reference price: the rule chosen, in its own words.
- Affiliate programme, Referral programme: "On" with its rate or reward, or "Off".
- Product feeds: "Built today 03:10 · 412 products", "Not built yet", or "The store is off: no feed is built".

## What does each row hold open?
- Store: Next order number, Commerce e-mail address, Folder for product images; the group "Prices and tax" (Multi-currency, Allow tax-exempt with its label under it); the group "Products" (Show product images in tables, Photo reviews, Barcodes with the default type under it); the group "Labels" (Create a label only after the pack is checked).
- Payment methods: one group a method, each headed by its switch: Cards (accepted cards as ticked chips, the gateway as a select, then that gateway's own fields: transaction type and mode as `pg-seg`, instalments, surcharge, 3D Secure), Iyzico, PayPal, Offline payments (only on specific orders, the days before an unpaid transfer is cancelled); then the group "After the order" (Grant private access with its folder, the reorder page).
- Shipping: Recipient mode as `pg-seg`; a group a carrier (USPS with address checking and its enforcement as `pg-seg`, UPS, FedEx), each with its state and lead; the two messages; End of day time beside the site's time now.
- Address autocomplete and Stock locations: what their functions draw, the new buttons; the "New location" fields behind an "Add a location" button, not always open.
- Gift cards: Accept gift cards, Validity, Givex with its state, the bonus on refunds. Rewards: the switch, Goal, points per unit, smallest conversion; the group "When the goal is reached" (Grant membership with its length, Send e-mail with BCC, subject and page).
- Reference price: the three rules as radio rows, each with its one line.
- Affiliate programme: the switch, automatic approval, default rate, contact group, e-mail address, group offer. Referral programme: as drawn today, its fields in two groups, "For the friend" and "For the customer".
- Product feeds: the feed addresses with "New address" in a drawer, the group "Seller", the groups left out as a ticked list with a filter above eight, "Save and build now".

## How does it look on a phone and in the dark?
- As the other folded panes: one column, the row's line under its name, pills kept; no fixed width inside a row.

Related: docs/memory/plans/settings-ecommerce-tidy.md, docs/memory/ui/settings-communication.md
