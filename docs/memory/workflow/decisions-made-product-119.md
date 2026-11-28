---
name: decisions-made-product-119
description: Decisions made, product part 119 (2026-11-27 on): US sales tax is not built for now; a beta site also takes stable releases, Repair reruns the running version's steps, no beta after the cut
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-118.md`.

## Is US sales tax built? (owner, 2026-11-27, agent channel poll 249, message 14895)
- Asked after the count of automatic tax: in the US the rate changes by state, county and product and no free, dependable source of rates exists, so a shop would connect a tax service with its own account and key on the Connections screen, as with a payment provider; every such service costs the shop money. The question was which service to build first, with Stripe Tax recommended.
- Owner: "ABD şimdilik yapılmasın". Not chosen: any of the services.
- So: no tax driver, no connection to a tax service and no US rate is built. Automatic tax is the EU's rates by the buyer's country, the reduced rate and the one-stop-shop report (`commerce/tax-automatic-rules.md`).
- A shop selling to the US sets its rates by hand in its tax zones, as today.
- It comes back only when he asks for it; no session proposes a tax service or adds one to a plan.

## Does a beta site take the stable release of its version, and what does Repair at the running version do? (owner, 2026-11-28)
- Asked after a beta and the stable release of 2026.11.0 share one number: a site on the beta should reach that version's stable release, which carries the fixes the beta found, and Repair should give a site the steps added after it ran the version.
- Owner (2026-11-28): "Evet, yap", then "önerilerin önceki kararlarımı değiştirdiği için önceki kararlarımı da düzelt".
- A beta site follows both channels and takes the newer version of the two, the stable release at equal version (`platform/update-channel-stable-beta.md`). The stable release of a version is offered over a recorded beta build of it on either channel; without `data/update_build.json` the same version is no update, as #3204 decided.
- Repair Software at the running version always reruns its upgrade steps, also where no build was recorded (`release/rerun-upgrade-steps.md`).
- No beta is built once `release/<version>` or the tag `v<version>` exists: it would hold code the stable release lacks under the same number (`release/release-packages.md`).
- It widens #3204 (`decisions-made-product-5.md`) and replaces "beta to stable waits for a newer stable release" of `platform/update-channel-stable-beta.md`.
