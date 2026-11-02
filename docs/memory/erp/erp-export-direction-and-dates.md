---
name: erp-export-direction-and-dates
description: ERP Export screen — which formats take a direction, and how its typed dates are read
---

## Which formats take the Direction selector?

A profile in `erp_export_profiles()` lists its `directions`; the first is
the default. Only `csv_invoices` lists both (`sales`, `purchase`);
`parasut_invoices` lists only `sales`, because its template is Paraşüt's
sales invoice import. Profiles without the key (accounts, receipts) have no
direction. The screen disables the selector unless the chosen option's
`data-directions` holds two values (a disabled select posts nothing).

## What does the server do with a posted direction?

`erp_export_filters($input, $profile)` takes it through
`erp_export_direction()`: the posted value when the profile lists it, else
the profile's first. `erp_export_direction_refused()` makes the screen show
"This format writes sales invoices only." when a purchase is posted for the
Paraşüt sales format. Before this, a purchase run filled the sales template
with suppliers as customers and marked the purchases exported (#5731).

## How are the Start and End dates read?

With `pg_date_input_iso()`, so the dotted Turkish `01.10.2026` is taken like
`01/10/2026` and ISO; `validate_date()` knew no dot and refused it (#5732).

## Who sees the reconciliation letter's send form?

Not a read-only accountant (`USER_ERP_READONLY`): `erp_reconciliation.php`
leaves the "Send by e-mail" card out for them, as `validate_erp_access()`
refuses their POST. The preview and the PDF stay (#5587).
