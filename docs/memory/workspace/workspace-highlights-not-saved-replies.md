---
name: workspace-highlights-not-saved-replies
description: Why message highlights (ws_highlights) and saved replies (ws_saved_replies) stay two features with two tables: what each keeps, where they differ, the owner's question of 2026-11-16
---

# Highlights are not saved replies

## Is a highlight a copy of a saved reply?
- No. The owner asked (2026-11-16, typed to the architect): "işaretleme ile notlara kaydediyoruz ancak bu özellik kopyası gibi olmadı mı kontrol edelim gerekirse 2 tablo açmak yerine kayıtlı yanıtlarda geliştirme yapıp (kayıtlı notlar yine notlarda çıkar) şeklinde ilerleyelim yapı farklıysa kalabilir".
- Architect (2026-11-16): the structures differ, both stay, no table is added or dropped. Told to him the same day; he had allowed for this outcome in his own sentence.

## What does each keep?
- A saved reply (`workspace/workspace-saved-replies.md`): text written ahead to be sent again. Shortcut, title, body, scope personal or team; no message behind it; lives until deleted; put into the composer with `;`.
- A highlight (`workspace/workspace-message-highlights.md`): a mark on a part of one existing message. Message, channel, the quoted words and their occurrence, scope me or all, a short note; goes with its message; drawn on the message and listed under "My highlights" in My Notes.

## Why not one table?
- They share only the person and a scope word, with different values. One table would leave half its columns empty for each kind and need two key rules: a shortcut unique per scope for a reply, the per-message limits for a highlight.
- A highlight is not a note either: "My highlights" lists `ws_highlights` rows inside My Notes; nothing is written to the notes' table.

## What does the owner want before a new table?
- Owner (2026-11-16): "gereksiz tablo eklemek beni geriyor bilgin olsun".
- The reading of `workflow/decisions-made-schema-25.md` stands: when a table is proposed, the architect first says whether something that exists can be grown, then asks him with that view. A comparison like this one goes with the question.
