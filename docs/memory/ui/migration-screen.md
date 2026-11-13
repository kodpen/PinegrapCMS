---
name: migration-screen
description: The Migration screen (migration.php): source password handling, API URL check, error details, wording
module: update
---

## Is the source site's password kept or shown again?

No. `migration.php` reads it once into `$source_password` right after the
required checks and sets the liveform value to `''`, so neither the error
redirect nor the success redirect draws it back into `value="…"` and the
session never holds it. After an error the operator types it again
(#6001).

## How is a failed request reported?

`send_api_request()` returns `pg_migration_response()`: always an array with
`status`, `message` and `errno`. No answer, an HTTP error page or a body
that is not JSON gives `status` `''` and a `reason` (`cURL error N: …`,
`HTTP N`). `pg_migration_error_suffix()` appends ` Error: <message or
reason>` plus `pg_curl_tls_hint($errno, true)`. Success keys (`styles`,
`folders`, …) are read with `isset()` (#6002).

## How is the API URL checked?

The field is `type="url"` with an `https://` placeholder (no preset value,
which passed `required`). The server also asks `pg_migration_url_valid()`:
http or https scheme and a host (#6003).

## Which wording was settled?

Button "Migrate" = "Taşı", screen and menu "Migration" = "Taşıma" (also the
name of the parent folder imported folders go into); "Invalid login." =
"E-posta veya parola hatalı." (api.php sends it only to password requests).
When the source has no items of the chosen types the notice is "No items to
migrate were found on the source site." and no activity line is logged
(#6004).

## Does it carry visual designs?

Yes, with the "Designs" switch (LD4, #6221): `designer/migration-carries-designs.md`.
The source never sends its Recycle Bin, Drafts or Workspace folders.
