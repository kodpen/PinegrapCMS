---
name: message-highlights
description: Plan (owner 2026-11-16, agent channel message 5547): a reader highlights a selected part of a message for everyone or for himself, in his own colour; My highlights in My Notes; HL1 to HL5
priority: 6
---

## What is asked?
- Owner (agent channel message 5547, with a picture of a sentence selected in another person's message): "birisinin mesajını (kendi mesajında olabilir) üzerini seçince seçili alanı önemli olarak işaretlemeye yarıyan bir minik toolbar çıksın (mesaj yazarken çıkan vurgula düğmesi) herkes için vurgula, kendim için vurgula seçenekleri olsun."
- For everyone: "insanlara bunun ile ilgili bir mesaj bildirim gitmesin ama o kişinin mesajını diğer kullanıcılar vurgulanmış olarak görüntülesinler. (üzerine gelindiğinde vurgulama mesajı (varsa) ve vurgulayan kişinin adı görünsün)". For himself: "sadece sen görebilirsin".
- Storage: "bunun için yeni tablo onaylıyorum"; "silinen sohbetlerden ve kanallardan vurgulamalar temizlensin db yükü olmasın".
- The list: "notlarım alanında küçük bir vurgulamalarım alanı olsun (notlarımdaki 3 nokta menüsünde olabilir) orada hem sadece benim hemde benim herkes olarak işaretlediğim vurgulanan alan kadar içerik olsun. üzerine tıkladığımda hangi kanaldaki mesajdaysa ona gitsin. ayrıca vurgulamaları toplu hızlı kaldıracak bir seçenek olsun."
- Colour: "vurgulama rengi kullanıcı rengi ile eşit olabilir".

## What is it not?
- Not the writer's own `==text==` (`workspace/workspace-text-highlight.md`): that is part of the message's text and stays. This is a reader's mark kept beside the message; the message's text is never changed.

## What is built?
- The numbered features: `message-highlights-features.md` (H1 to H9, the server's rules) and `-features-2.md` (H10 to H22, the message side and My highlights).
- Steps: `message-highlights-steps.md`. Order: HL1; HL2 after HL1; HL3 after HL2; HL4 after HL3; HL5 last.
- Claim as `[plan:message-highlights] <step> — <topic>`. A step's PR body lists its H numbers, each with the test or the picture that shows it, as `plans/prototype-canvas-rules.md` asks of that plan.

## Whose look?
- The owner's, twice (Workspace is a screen he keeps): after HL3 (the toolbar, a drawn highlight, its card) and after HL4 (My highlights), each one sheet and one short question the architect brings him. Neither is marked ready before it.

## What is left out?
- Apps and agents making highlights, the outside API and MCP, highlights in notes, tasks and canvases, any notification, a search inside highlights.
