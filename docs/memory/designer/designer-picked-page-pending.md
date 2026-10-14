---
name: designer-picked-page-pending
description: Designer "Var Olan Sayfayı Seç": a picked page belongs to the other design until saved; three tab states, pickedPending, remove vs detach.
---

## Whose page is a picked page before save?
- "Var Olan Sayfayı Seç" neither copies nor moves the page at that moment: a tab opens, and `page.page_style` switches to this design only at **save**. In between, the page is another design's page to the server.

## What are the rules?
- **Tab state has three values, not two:** new tab (`page_id === 0`), picked but unsaved (`p.pickedPending`), this design's page. `page_id > 0` alone does **not** mean "this design's page": `page_delete` / `page_detach`, which require `page_style`, silently rejected because of this.
- **"Tasarımdan Çıkar" does not go to the server.** Nothing to undo; the tab closes and the page stays in the design it came from. "Ayır" (detach) creates a new design, which is the wrong answer for a borrowed page.
- `pickedPending` is not cleared: save reloads the editor and the page arrives from the server as this design's own page.
- **No variable inside a `_sdT()` key** (translation is found by key and replaces the whole text; an embedded name is lost). Use `{var}`: `_sdT('It stays in {var} as it was.', owner)`.

Related: docs/memory/designer/designer-sdt-translation.md
