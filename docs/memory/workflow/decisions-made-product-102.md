---
name: decisions-made-product-102
description: Decisions made, product part 102 (2026-11-24 on): a rule-based Security Scan joined to the firewall's screen and card, with a manual run and a full test round; not an antivirus
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-product-101.md`.

## Does the software scan itself for security faults? (owner, 2026-11-24, agent channel messages 12839 and 12847)
- Yes. He asked: "waf korumamıza ek antivirüs koruması gibi bir koruma yapmayı düşünmelimiyiz? PHP2Shell ve daha eski güvenlik sorunlarını tespit eden, claude asistanı gibi bağlanmış araçlarla sistem sağlığını ve güvenlik risklerini inceleyip denetleyen, yapay zeka katkısı olmadan da sistemi otomatik tarayan bir sistem".
- After the architect's five parts (files, quarantine, stored content, accounts and settings, a report with an API and MCP read): "planlayalım ancak waf ve bot engelleme ile bütünleşik şekilde ilerleyelim. her birisi için ayrı ekranlar karmaşayı artırıyor."
- "manuel tarama seçeneğide ekleyelim ve zaten var olan güvenlik widgetinda (uygunsa) tarama düğmesi ile son tarama ve sonuçları takip edilebilsin."
- "lansman sonrası sitelerde seo ve kullanıma engelleyecek sorun oluşturmaması adına iyi test etmemiz de lazım bir döngü boyunca her şekilde testi de plana ekleyelim, her senaryo denensin."
- The name: "antivirüs olmaz db den beslemiyor olacağız". The architect's proposal: Security Scan ("Güvenlik Taraması"), his to change.
- The architect's reading, not his words: the scan only reads and never acts by itself; quarantine is a person's click.
- The feature: `waf/security-scan-overview.md`; the table: `decisions-made-schema-51.md`.
