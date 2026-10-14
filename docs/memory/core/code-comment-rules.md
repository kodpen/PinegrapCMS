---
name: code-comment-rules
description: Mandatory prohibitions for in-code comments: English only, no AI/session/process traces, classic what-and-why style, retroactive conversion.
---

## What are the rules for comments in code? (no exceptions)
Product files ship to customers; comments are part of the product.
1. Comments in code are English ONLY. Forbidden: any other language in PHP, JS, CSS, SQL, including CSS/JS comments embedded in strings. (tr.json values and `lang()` strings are not comments; Turkish there is normal.)
2. No comment may carry an AI/assistant trace. None of these may appear in a code comment: "kullanıcı kararı", "kullanıcı isteği", "saha geri bildirimi", "kullanıcı onayıyla", "Faz 1/Faz 2/Faz 3", a plan-file reference, "dev veritabanında çalıştırıldı", "X dersi", or any session/process reference. They look like proof that AI developed the software and must not be in a file shipped to customers.
3. A comment is a classic developer comment: it says WHAT the code does and technically WHY (locks, races, backward compatibility, security gates). Decision history and who decided belong in the PR and the matching `docs/memory/` file, not in code.
4. The rule is retroactive: if you see an old Turkish/AI-traced comment in a file you touch, convert it to an English classic comment in the same change.
