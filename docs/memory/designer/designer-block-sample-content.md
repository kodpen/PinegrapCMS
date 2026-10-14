---
name: designer-block-sample-content
description: Canonical sample content (brand, companies, people, plans, domains, images, prices) to use when writing ready-made designer blocks.
---

## Why one canon?
Do not invent sample content for a new ready-made block; pick from this set. If each block builds its own world, the palette shows one site as three different companies.

| What | Canon |
|---|---|
| Site's own brand | `ACME` |
| Customer / logo / reference company | `Vertex`, `TechBase`, `StartMate`, `LaunchLab`, `Northwind`, `PineWorks`, `OrbitSoft`, `Quanta`, `ByteWave`, `Helios`; **the site's own brand is never used as a customer** |
| Person | `Jane Cooper`, `Michael Brooks`, `Emily Turner`, `Daniel Ross` |
| Plan | `Starter`, `Pro`, `Enterprise` |
| Email / domain | `example.com` (`name@example.com`, `docs.example.com`) |
| Image | photo `picsum.photos`, logo `placehold.co` |

## How are prices written?
- **A price is sample content and goes through `_sdT()`.** The key is the English source (`_sdT('$199')`), the Turkish `₺199` lives in tr.json. A raw `'₺199'` prices an English site in lira.
- **Currency and period are not embedded in one key.** The amount is one node, `/mo` is another node (inside a `Price` wrapper); this is the pattern of price blocks and new blocks use it.
- **`_sdT()` keys are English.** Turkish inside a key (`_sdT('Studio Paketi')`) makes translation impossible: Turkish and English sites see the same Turkish text.

Related: docs/memory/designer/designer-sdt-translation.md
