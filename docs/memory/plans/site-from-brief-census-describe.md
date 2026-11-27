---
name: site-from-brief-census-describe
description: Site from a brief census: what the Sketch tool's "Describe the page" and "Write the texts" send and get back, their limits, the provider and licence they need, what a page costs in requests and tokens
---

## What does "Describe the page" send?
- One `pg_ai_chat()` call (`includes/fn/sketch_ai.php:316`) through the editor's `designer/sketch_ai` (`includes/panel/designer.php:542`), designers only; it writes nothing.
- System: wireframe rules, a page 100 units wide, header, intro, bands, footer, box headings in the pages' language at most six words, the shared writer rules, then the JSON shape (`pg_sketch_ai_messages()`, `sketch_ai.php:225`). User: the person's words, plain, at most 2,000 characters (`sketch_ai.php:181`).
- Temperature 0.3, `max_tokens` 3000 (`sketch_ai.php:317-318`).

## What comes back?
- `{"boxes": [{x, y, w, h, text}]}`: at most 40 boxes (`PG_SKETCH_AI_BOXES`, `sketch_ai.php:29`), clipped to the 100-wide page and 5,000 high, duplicates dropped, text cut to 80 (`pg_sketch_ai_boxes()`, `sketch_ai.php:98`). No kinds: the browser's sketch reader gives them (`designer/sketch-reader.md`).
- The boxes replace one sketch page's boxes; "Build the page" turns them into a draft tab (`designer/sketch-build.md`).

## What does "Write the texts" send?
- One call a section of a built page: site name, the page's name, title and description, the section's kind, heading and at most 24 sample texts; the answer must give the same count (heading 80, link 40, paragraph 600). Temperature 0.7, `max_tokens` 2000 (`sketch_ai.php:225-262`, `designer/sketch-ai.md`).

## Which limits apply?
- `pg_ai_write_problem()` (`includes/fn/ai_write.php:1547`): "Write with AI" switched off when `PG_AI_WRITE_DAILY_CAP` is 0.
- `pg_ai_state()` (`includes/translate/engine_ai.php:70`): the Workspace module on, Pinegrap AI switched on, an application of its own chosen and active, no hold after a failed call.
- 20 requests a minute a person (`PG_SKETCH_AI_PER_MINUTE`, `sketch_ai.php:27`); every request takes one of the site's daily drafts (`PG_AI_WRITE_DAILY_CAP`, default 200, `ai_write.php:1321`, `ui/ai-write.md`).
- Over the licence's daily tokens the gateway answers 429; AI not in the licence, 403 (`workspace/workspace-ai-token-counter.md`).

## Which provider and licence does it need?
- Pinegrap AI only: `https://ai.pinegrap.com/v1` (`WS_AI_ENDPOINT`, `includes/workspace/ai.php:55`), `/chat/completions` with the model the gateway lists (`engine_ai.php:117-140`), on a licence that includes AI. The actions show only while `pg_ai_write_engine()` is `pinegrap` (`ai_write.php:1579`).
- The browser's model is not used by the Sketch's AI actions ("Read the picture" alone uses it, `designer/sketch-read-picture.md`). A site without the Workspace module or without AI in its licence has no AI path.

## What does a page cost?
- Requests: one describe plus one a section; a page of 6 to 8 sections is 7 to 9 requests, 7 to 9 of the 200 daily drafts.
- Tokens, estimated from the prompts (not measured, no live model in the session): describe about 700 in, 600 to 1,500 out; a section about 500 in, 200 to 600 out. A page about 6,000 to 12,000 tokens.
- A site of 6 pages: about 45 to 55 requests and 40,000 to 75,000 tokens; at the 20-a-minute pace about three minutes. The licence's daily token limit is set at the gateway, not in the code: whether one site fits a day is the gateway's figure.
