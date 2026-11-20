---
name: decisions-made-product-88
description: Decisions list, product part 88 (2026-11-20 on): Application Access drawn again, with starting points, bulk permission choices, a guide per way to connect and an agent set up from the application
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-87.md`.

## Is Application Access drawn again? (owner, 2026-11-20, in the architect's window)
- "api_settings.php yani uygulama erişimi modernize edildi ancak birkaç sorun var, claude console nasıl bağlanır bilgisi yok. mcp, oauth, mobil uygulama, normal bağlantı tam olarak nasıl kullanır anlatım zayıf"
- "az önce claude console da bağlama denedim olmadı token istiyordu mcp ile kurdum o da ajan kanalı için uygun değildi @console çağrısı yerine kullanıcı olarak giriyordu."
- "yeni uygulama ekleme ve mevcut uygulamayı düzenleme ekranını baştan çizmeni istiyorum." "seçenekler ikonlarla ve açıklamalar ile düzgün anlatılsın. kullanım kolaylaştırılsın. hazır şablon türü seçimler olsun."
- "izinler toplu hepsini hiçbiri hepsini okuma hepsini yazma izinlerini tek düğme ile yapacak bir düğme ekleyeli. izin seçim alanı ayrıca modernize edelim şu anda düğme yığınından başka birşeye benzemiyor."
- Called urgent. Binds: starting points, the four bulk choices, a picker that is no row of buttons, each way to connect told with who it acts as, Claude Console among them. The plan: `plans/app-access-redraw.md`.

## Does Application Access move into Connections? (owner, 2026-11-20, in the architect's window)
- "uygun görürsen bu uygulamalarda connections.php alanıdna durabilir (mantıksız sa yapmayalım)." He left it to the architect.
- Architect (2026-11-20): it stays its own screen beside Connections; Connections holds what the site reaches, this screen who may reach the site, with other rights. Each links the other.

## Is an agent given to a channel from the application's screen? (owner, 2026-11-20, in the architect's window)
- "uygulamayı düzenlerken kanal ajanına atamayıda oradan yapabilirsek iyi olur talimatları ile çıktı verir, direkt bir kanalda seçeriz tek alandan ajan ekleriz. şu anda anahtar üretiyorum, ayardan ajan ataması yapıyorum sonrada kanala gidip ajanı o kanala ekliyorum."
- Binds: one place makes the key, the agent and its channel rows and gives the start text. The Agents card and the channel's own agent settings stay; the same checks and rights hold.
