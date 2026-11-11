---
name: decisions-made-product-43
description: Decisions list, product part 43 (2026-11-11 on): the Kodpen API client (PG_VENDOR_API_V1) goes on in 2026.11.0 before its release keys exist; the api2 client branches leave in the next open version
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries.

## Does the Kodpen API client go on before its release keys exist? (owner, 2026-11-11)
- Owner (2026-11-11): yes, "close the api2 topic". In the owner's words: "sorunu tümden düzeltecek development dalına bir pr geç api2 konusu kapansın".
- `PG_VENDOR_API_V1` is true from #8653 (`platform/vendor-api-client.md`), before the plan's old gate held: release packages (P2), the two release keys (P5c), production key fingerprints, a licence check and an update on the vendor's test API.
- Not changed by it: the api2 client branches stay, unreached, and leave in the next open version, as #3205 says; `download_assistant.php` keeps its api2 call for good.
- What follows: the licence check asks the Kodpen API; the update check answers no update while nothing is offered; an offered release is refused at `unknown_key_id` (`platform/vendor-api-update-download.md`). So P2 and P5c land before the 2026.11.0 release branch, else a 2026.11.0 site updates only through `download_assistant.php`.
