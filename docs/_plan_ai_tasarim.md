# Yapay zekâ ile görsel sayfa düzenleme + API genişletmesi

Durum: uygulandı, dev'de denendi (2026-09-27). Açık sürüm 2026.4.5,
şema adımı **5.70** (API aralığı 5.70–5.79).

İlgili: `docs/_plan_claude_kanal.md` (Claude rutini), `docs/pinegrap-ai-gecit.md`
(@ai geçidi), `docs/_plan_icerik_api.md` (sayfa uçları),
`dev/_handoff/API-ERP-koordinasyon.md` (ortak dosyalar).

## 0. Hedef (Erdal)

- Tasarımcıda: bir alan seçiliyken "@claude seçili alanı şöyle güncelle …",
  hiçbir şey seçili değilken "@claude sayfayı baştan tasarla, neon tarzı olsun".
- Çalışma Alanı'nda: aynı istek bir sayfayı anarak (`#sayfa`) yazılabilir.
- İki yöntem de hesaba katılır: **Claude** (rutin + dış API, 1–3 dk) ve
  **@ai** (Pinegrap AI, sitenin kendisi modele gider, araç çağrısı).
- Dış API'de ERP ve Çalışma Alanı için eksik uçlar kapatılır, mevcutlar
  geliştirilir.
- Çekilen ve düzenlenebilir kayıt alanları (ürün, sayfa, form, sipariş, ERP,
  kullanıcı, kişi) genişletilir.

## 1. Kararlar (Erdal, 2026-09-27)

1. **Öneri → önizle → uygula.** AI sayfaya yazmaz. Önerisi işlem listesidir.
   Tasarımcıda açık sekmeye uygulanır (Ctrl+Z ile geri alınır, Kaydet'e kadar
   yayına çıkmaz). Çalışma Alanı'ndan gelen öneri isteyen kişi tarafından
   uygulanır; önceki ağaç saklanır, "Geri al" olur; sayfa tasarımcıda başka
   birinde açıksa (kilit) uygulanmaz.
2. **Yeni kapsam `design:read` / `design:write`**, ama **yalnız görsel sayfa
   tasarımcısının sayfaları** (`add_system_style.php` ile açılan tasarımlar,
   ağacı JSON olarak saklanan sayfalar — `pg_page_is_visual_design()` ve dolu
   `page_tree_json`). Custom style ile eklenen / içeriği HTML olan sayfalar
   kapsam dışı: uçlar onlara 404 değil 422 `validation_failed` ("görsel
   tasarım sayfası değil") döner. Önceki "sayfa içeriği API'den dönmeyecek"
   kararı `pages` uçları için geçerli kalır; tasarım ağacı ayrı kapsamdadır.
3. **Stil yeri: sayfaya özel CSS bloğu.** Bootstrap sınıfları + düğüm stili;
   gerekirse o sayfaya özel, süzülmüş tek bir CSS bloğu (dış `url()`,
   `@import`, `expression`, `</style` yok). Tasarımın öbür sayfaları etkilenmez.
4. Kapsam yalnız görsel tasarımcı sayfaları. Paralel ajan olabilir: ortak
   dosyalar yazılmadan hemen önce taze okunur, üç yollu birleştirme yapılır.

## 2. Mimari

```
Tasarımcı (seçili düğüm / tüm sayfa)     Çalışma Alanı (@claude / @ai + #page)
        │ designer/ai_ask                         │ mesaj
        ▼                                         ▼
  ws_ai_requests  (agent claude|ai, page_id, node_id, design_base = sekmenin ağacı)
        │
        ├─ agent = ai  → ws_ai_kick → model ↔ araçlar (read_page, read_page_node,
        │                propose_page_edit) — sitenin içinde
        └─ agent = claude → rutin → GET /workspace/claude/requests (design bloğu)
                          → GET /design/pages/{id}?node=… → POST /design/pages/{id}/proposals
                          → POST …/requests/{id}/answer
        ▼
  design_proposals (işlem listesi, özet, taban özeti, durum, önceki ağaç)
        │
        ├─ Tasarımcı: designer/ai_apply → sunucu işlemleri SEKMENİN güncel ağacına
        │   uygular → yeni ağaç tuvale (saveState ile, geri alınabilir)
        └─ Çalışma Alanı: yanıtın altında kart → Tasarımcıda aç (öneri tuvalde) /
            Uygula (kayıtlı ağaca, kilit denetimi, önceki ağaç saklanır) / Geri al / Reddet
```

### 2.1 AI'nın gördüğü sayfa (`pg_design_ai_view()`)

- Ağaçtan üretilmiş sade HTML: her öğe `data-pg="<_id>"` taşır. Kimliği olmayan
  düğüme (eski şablon ağacı) belirlenimci `sd_<n>` verilir (en büyük sayıdan
  devam), aynı kayıtlı ağaçta aynı kimlik çıkar.
- Sınıf = yapısal sınıflar (container/row/col/heading hizası) + `cssClass`;
  stil = `inlineStyle` + kullanıcının `style` niteliği. Boyut propları
  gösterilmez, dokunulmaz.
- **Dokunulmaz adalar** `<pg-keep data-pg="…" kind="…" label="…">`: bölge
  (`region`), ortak bileşen (`shared_ref`), `custom_php`, `custom_html`,
  döngü alanları, sistem widget'ı ve Bootstrap bileşenleri (accordion, card…).
  Bileşenlerin düz metin/seçim propları `data-pg-props` içinde görünür ve
  `set_props` ile değiştirilebilir. Adalar taşınabilir, silinebilir; içleri
  değiştirilemez. Bağlama (`_bindings`) taşıyan düğüm ada gibi korunur.
- Ana hat: düz liste (id, üst, derinlik, tür, etiket, ad, sınıf, metin önizlemesi).
- Sayfanın AI CSS bloğu ayrı alan (`css`).

### 2.2 İşlemler (öneri içeriği)

| op | alanlar | ne yapar |
|---|---|---|
| `update` | node, text, classes / add_classes / remove_classes, style, attrs | küçük düzeltme |
| `set_props` | node, props | bileşen/ada düz propları (var olan anahtarlar) |
| `replace` | node, html | düğümü HTML ile değiştirir; `data-pg` taşıyan öğe o düğüme eşlenir (bağlamalar, ad, not korunur), `pg-keep` ada olarak geri gelir, yeni öğeler içe alıcıyla düğüm olur |
| `insert` | parent + index / before / after, html | yeni içerik |
| `remove` | node | siler |
| `move` | node, parent + index / before / after | taşır |
| `page` | html | tüm sayfa gövdesi (root çocukları) |
| `css` | css | sayfanın AI CSS bloğu (boş = kaldır) |

- En çok 40 işlem, HTML parçası 200 KB, CSS 60 KB.
- AI HTML'i önce süzülür: `script`, `iframe`, `object`, `embed`, `form`,
  `base`, `meta`, `link`, `style` atılır; `on*` nitelikleri, `javascript:` /
  `vbscript:` / `data:` (görsel dışı) adresler, `srcdoc` silinir. `custom_php`
  hiçbir yoldan oluşmaz.
- Uygulayıcı tek fonksiyon (`pg_design_ai_apply_ops($tree, $ops)`): hem
  öneri kaydında (kuru deneme, hatalı işlem 422) hem uygulamada çalışır.
  Sonuç `_validate_and_clean_tree_json()`'dan geçer.

### 2.3 Öneri deposu — `design_proposals` (5.70)

`id, request_id, agent, app_id, page_id, style_id, node_id, ops (JSON),
summary, base_hash, status (pending | applied | dismissed | stale | failed |
reverted), before_tree, after_hash, requested_by, channel_id, message_id,
decided_by, decided_at, created_at, error`.

`ws_ai_requests`'e: `page_id`, `node_id`, `design_base` (sekmenin ağacı).
Tasarımcı isteğinin metni `note_text`'te, yanıtı `note_reply`'da durur (kanal
mesajı olmayan istek — not isteğiyle aynı yol).

### 2.4 Yetki

- Tasarımcıdan istek: `pg_designer_is_full()` (tasarımcı rolü) + sayfa
  erişimi. İçerik düzeyi operatörler ilk sürümde yok.
- Uygulama: isteyen kişi, kendi yetkisiyle; `pg_designer_save_page()` üzerinden
  (aynı doğrulama, kısıtlı birleştirme, not damgası).
- Claude/@ai uygulamasının `design:read` + `design:write` kapsamı yoksa tasarım
  istekleri açılmaz; kart ve tasarımcı bunu söyler.

## 3. Dış API

Yeni (`includes/api/resources/design.php`, kapsam `design:read/write`):

- `GET /design/pages` · `GET /design/pages/{id}` (`node`, `format`)
- `POST /design/pages/{id}/proposals` (`ops`, `summary`, `request_id`, `dry_run`)
- `GET /design/pages/{id}/proposals` · `GET /design/proposals/{id}`

Claude kuyruğu: istek nesnesine `design` bloğu (page_id, page_name, node_id,
scope). Tasarımcı isteği `answer` ile yalnız metin alır (öneri ayrı uçtan).
Kanal isteğinde `answer`, isteğe bağlı önerileri yanıt mesajına bağlar.

## 4. @ai

- Tasarım araçları düz argümanlı (küçük model iç içe JSON'u okuyamadı):
  `read_page`, `page_set`, `page_replace`, `page_insert`, `page_remove`,
  `page_css`, `page_rewrite`. Tasarımcı isteğinde kanal araçları yok;
  `max_tokens` tasarımda 8000.
- İstem: sayfanın çatısı (Bootstrap 5 / özel), adalar, araç rehberi (neon
  örneği), CSS kuralı; yanıt tek-iki düz cümle.
- Küçük model korumaları: metin olarak yazılmış çağrılar okunur / temizlenir,
  yanıtın içindeki çağrılar çalıştırılıp yanıt yeniden istenir; CSS'siz
  uydurma sınıf ve sayfa CSS'ini ezen Bootstrap yardımcı sınıfı için bir kez
  geri gönderme; @ai önerisinde ezen yardımcı sınıf kendiliğinden kaldırılır;
  kapanmamış `{` içeren CSS reddedilir.
- Claude ya da @ai Çalışma Alanı'nda kurulu değilse tasarımcıda asistan hiç
  görünmez (Erdal, 2026-09-27).

## 5. Çekilen / düzenlenebilir alanlar (changes.php + okuma sunumu)

İlk tur adayları (her biri panelin yazdığı sütun ve yardımcıyla):

- **Ürün:** stok takibi, stokta yok mesajı, ağırlık, boyutlar, vergi oranı,
  karşılaştırma fiyatı, kargo gerekmez, ürün kodu/barkod…
- **Sipariş:** kargo takip no, müşteri notu, fatura/teslimat adres alanları…
- **Kişi:** ev adresi, telefonlar, web, doğum günü, kişi grupları, bülten izni…
- **Kullanıcı:** ad (bağlı kişi üzerinden), e-posta, departman… (parola asla)
- **Sayfa:** yorum ayarları, ana sayfa (yalnız personel), klasör.
- **Form:** gönderilmiş form kaydının alanları ve durumu (yeni tür).
- **ERP:** cari kartın kalan alanları; taslak fatura/teklif/gider başlık alanları.

Uygulanan (2026-09-27): yeni değer türleri `decimal`, `code`, `lines`,
`answers`.

- **Ürün:** details, google_product_category, taxable, tax_rate (boş = vergi
  bölgesi), vat_exemption_code, shippable, free_shipping, extra_shipping_cost,
  weight/length/width/height, track_stock (`inventory`), backorder,
  out_of_stock_message, minimum/maximum_quantity, reward_points,
  order_receipt_message, custom_field_1–4; sınırlar sütun boyuna çekildi.
- **Sipariş:** po_number, custom_field_1–2, tracking_numbers (tek adres,
  `update_order()`, e-posta yok).
- **Kişi:** suffix, nickname, department, office_location, opt_in,
  business_phone/fax, website, home_* (adres, şehir, il, posta kodu, ülke,
  telefon, faks), lead_source, tax_number/office, expiration_date.
- **ERP carisi:** country_code, currency, credit_limit, invoice_email,
  invoice_mail, overdue_notify_days, overdue_notify_customer.
- **Kullanıcı:** email (tekil, yalnız yönetici).
- **Sayfa:** meta_keywords, comments, comments_open/publish/login.
- **Form (yeni tür):** complete + answers (text box / text area / e-posta).
- Açık: ERP taslak belgeleri (teklif/gider başlığı) önerilebilir değil.

Claude okuması: `GET /workspace/claude/requests/{id}/change-types`,
`GET /workspace/claude/requests/{id}/records/{type}/{record_id}`.

## 6. ERP ve Çalışma Alanı API boşlukları

- ERP (uygulandı, okuma): `/erp/quotes`, `/erp/cheques` (`erp_cash:read`),
  `/erp/stock/levels`, `/erp/stock/moves`, `/erp/stock-counts`,
  `/erp/reports/aging`, `/erp/reports/vat`. Açık: iadeler, kâr ve nakit akışı
  raporları, fatura taslağı yazma.
- Çalışma Alanı (uygulandı): `POST /workspace/channels`, `POST|PATCH
  /workspace/channels/{id}`, `GET|POST /workspace/channels/{id}/members`,
  `DELETE /workspace/channels/{id}/members/{user_id}` (prova destekli),
  `GET /workspace/changes`, `GET /workspace/assistant-requests`,
  `GET /workspace/notes`, `GET /workspace/notes/{id}`. Açık: takvim olayları.

## 7. Adımlar

| # | İş | Durum |
|---|---|---|
| 1 | Plan | yazıldı |
| 2 | Şema 5.70 | tamam, dev'de iki kez |
| 3 | `includes/designer_ai.php` çekirdek | tamam; design 781'in 28 sayfası gidiş-dönüş aynı |
| 4 | Dış API tasarım uçları + Claude kuyruğu | tamam |
| 5 | @ai tasarım araçları, Claude rutin istemi | tamam; rutin claude.ai'da yeniden yapıştırılacak |
| 6 | Tasarımcı kapısı (istem çubuğu, uygula) | tamam; @ai ile uçtan uca denendi |
| 7 | Çalışma Alanı öneri kartı | dev'de denendi (kart, kilit reddi, reddet; uygula + geri al API önerisiyle) |
| 8 | Alan genişletmesi | tamam (ERP taslakları açık) |
| 9 | ERP / Çalışma Alanı API boşlukları | tamam; okuma ve yazma uçları dev'de denendi |
| 10 | Denetimler, dev turu, belgeler | tamam |
