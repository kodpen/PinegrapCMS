---
name: memory-as-guide-trials
description: Trials of plans/memory-as-guide.md: six tasks in the owner's words given to fresh sessions on 2026-11-10, what each found, its tokens, tool calls and files opened, and how a rerun is judged
---

## What is a trial?
- A fresh session that knows nothing of the project reads `CLAUDE.md`, prepares one task up to the first line of code, changes nothing, and reports its searches, the memory files it opened, what binds the task and where memory slowed it.
- Run on 2026-11-10, `development` at 1058f58b6b, one run per task.

## Which tasks, and what did each run find?
- Wait box: "Workspace akışlarına yeni bir kutu türü eklemek istiyorum: Bekle, akışı verilen süre kadar bekletip sonra devam ettirecek." Found that the box exists (kind `delay`, label Wait). 173,000 tokens, 51 calls, 10 memory files.
- Cash till: "ERP'de nakit kasadan, kasadaki bakiyeden büyük bir ödeme girince reddediyor. Bu kontrolü kaldır." Found the owner decision (#4460) and chose to ask. 171,000 tokens, 50 calls, 13 files. The topic file did not say it was a decision; a search of the whole tree for the issue number found it.
- New list: "Panelde yeni bir liste ekranı yapacağım: Kargo firmaları (ad, durum, son kullanım tarihi); sıralama, arama, toplu silme, satır işlemleri." Found the list rules and the schema question. 256,000 tokens, 100 calls, 41 files. No recipe; the files tell which batch moved which screen.
- Coupon: "Sepette yüzde indirimli kupon kullanınca satır toplamları ile genel toplam arasında 1 kuruş fark çıkıyor." Found four decided one-kurus sources. 241,000 tokens, 67 calls, 20 files in two folders.
- Documentation: "ERP dökümantasyon sayfalarını güncelleyeceğiz; 17 Ekim 2026'dan beri kullanıcıya görünen neler değişti?" Listed 25 items from `git log`. 220,000 tokens, 49 calls. The state of the pages came from the machine's private notes.
- Commission: "Bir sipariş iptal edilince ortaklık komisyonuna ne oluyor, iadede durum ne?" Right, with the reason. 117,000 tokens, 28 calls; the answer by the fourth call, then 15 calls checking in code what memory had said.
- The same question with `docs/` closed: the behaviour right, the reason guessed. 158,000 tokens, 31 calls.

## What else was measured?
- The words of a merge's title find the memory file that merge edits in its folder index 92% of the time, 54% with a word that returns ten lines or fewer; full text finds 98% (263 pairs from 395 merges since 2026-11-03).
- Adding the question headings to the index changed nothing (92%).

## How is a rerun judged?
- The same tasks and instructions, one fresh session each.
- Better: the same findings with fewer memory files opened and fewer tool calls; the till decision seen in the first file opened; the list recipe and the flows overview opened first; the documentation list made without private notes.
