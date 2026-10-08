# ERP geliştirme — devir notu (güncel: 2026-09-24)

## İş bölümü ve kurallar (Erdal)

- **GitHub işi (commit, push, PR, birleştirme) bu ajanın işi değil**; başka bir ajan çalışma ağacından alıyor. Bu ajan kodu yazar, dev'de ürün arayüzünden dener ve belgeler:
  - `docs/degisiklikler.md`: yeni bölüm, dosyanın başındaki ilk `---`'nin hemen altına (arkasından `---`).
  - `pinegrap/changelog.txt`: ERP maddeleri `  E-FATURA (PARAŞÜT)` başlığının hemen üstüne. Açık sürümde nihai-durum anlatımı: aynı sürümde eklenen bir şeyin düzeltmesi [DÜZELTME] olarak yazılmaz, ilgili [YENİ] maddesi güncellenir.
  - Şema adımında `docs/CLAUDE-tam.md` sürüm tablosuna satır (ERP satırları `| \`2026.4.4\` (4.102)` satırının altına) ve `.claude/skills/pinegrap-sema-adimi/SKILL.md`'nin yedi maddesi.
- Commit/PR metinlerine ve hiçbir dosyaya Claude atıf satırı, oturum bağlantısı yazılmaz.
- Bu not yalnız Erdal isteyince güncellenir; güncellenince dosya olarak da gönderilir.
- Kalıcı silme yok (dosya, İşbaşı'da belge/taslak, sipariş/fatura dahil): gereksiz dosya `dev/_to_delete/` altına `mv -n` ile taşınır. `pinegrap/data/` depo dışı.
- `orders.notes`, `orders.tracking_company` ERP'ye ayrılmış, dokunulmaz.
- Kimlik bilgilerini (parola, API anahtarı, kart/hesap numarası) **Erdal girer**; ajan kutu doldurmaz, anahtar üretmez, sohbete yazılan kimliği dosyaya yazmaz. Probe betikleri kimlik tutan ayar kolonlarını ekrana basmaz.
- **İşbaşı'ya yalnız ürünün kendi düğmeleriyle yazılır**; probe betikleri yalnız okur.
- **Başka ajanlar aynı ağaçta çalışıyor.** Ortak dosyalar: `add_order.php`, `includes/local/tr.json`, `includes/migrations/2026.4.5.php` (açık sürüm; 2026.4.4 yayında ve kapalı), `install/index.php`, `changelog.txt`, `docs/*.md`, `includes/fn/output.php`, `includes/notifications.php`. Dokunmadan hemen önce taze oku, yalnız ekle; `tr.json` yalnız sona ve atomik (`dev/_sync/tr_append.py`).
- **Çalışma Alanı modülü başka ajanın:** `includes/workspace/*`, `workspace*.php`, `assets/js/workspace*.js`, `_seed_ws_schema.php` — dokunulmaz. Mağaza hesap widget'ları (Faturalarım'ın gireceği yer) da başka oturumda.
- `settings_pane.php`'ye **POST yapılmaz** (boş POST ticaret ayarlarını siler); okumak için GET. Ayar kaydı Erdal'la ekrandan denenir.
- Kod yorumları İngilizce, AI/süreç izi yok. PHP 7.0 uyumlu (`??=`, `fn`, dönüş tipi, nullable tip, `[$a, $b] =` yok). Erdal'la Türkçe konuşulur.
- Deneme kayıtları kalır (silinmez, geri alınmaz).
- **E-posta:** `email()` doğru çalışır; dev makinada e-posta mekanizması yok, e-postanın ulaşması denenmez (ekran, PDF eki, adres doğrulaması yeter). Canlıda gerçek e-posta izinsiz gönderilmez.
- Kaynaklar:
  - ERP sözleşmesi ve dosya haritası: **`.claude/skills/pinegrap-erp/SKILL.md`** (tek güncel kopya; `Claude outputs/` klasörü kaldırılıyor). Diğer konular: `.claude/skills/README.md`.
  - Plan `docs/_plan_erp.md`; İşbaşı **`docs/_isbasi_api_notlari.md`** (okunmadan İşbaşı işine başlanmaz); mimari `CLAUDE.md` + `docs/CLAUDE-tam.md`.
  - Yol haritası `docs/_erp_yol_haritasi_adaylari.md` = proje dokümanı `claude/erp-yol-haritasi-adaylari.md` ("0. Durum" bölümü neyin yapıldığını söyler).
  - Bu not: proje dokümanı `claude/erp-devir-notu.md` = `docs/_erp_devir_notu.md`.

## Erdal'ın kararları

- ERP Logo/Paraşüt'e bağlı değil, **dünyanın her yerinde kullanılacak**: sağlayıcıya bağlı ekranlar yalnız sağlayıcı o işi yapıyorsa görünür; bir ülkenin vergi kuralı yalnız o ülkenin carisine uygulanır.
- ERP açıkken her tezgâh satışı faturalanır; satışta vergi numarası sorulmaz.
- "İrsaliye yerine geçer" kuralı: **şimdilik bekle.**
- Cari eşitleme (4.69): farklılıkta alan alan seçim; tek eşleşmede okumada kendiliğinden bağ; gönderimde bağlı kartın kodu; "Yalnız ERP'de" listesinde yalnız geçerli vergi numaralı cariler.
- Menü girişi "Kaynak Planlama (ERP)" (`'Resource planning (ERP)'`), pano başlığı "Kurumsal Kaynak Planlama (ERP)". Çevrimdışı ödemedeki "Sadece belirli siparişlerde" kısıtı sorun değil.
- 2026-09-23: **büyük ya da dış bağımlılığı olan işler es geçilir** (pazaryeri ve iyzico hakedişi, SMS/WhatsApp, Paraşüt, e-İrsaliye, açık bankacılık, kimlik isteyen her şey). Küçük ve orta işler yapıldı (aşağıda).

## Ortam

- **Ana makina (bu notun yazıldığı):** Windows `msi-b660m2026-2`.
  - Klasör `C:\Users\erdal\OneDrive\PinegrapCMS`; cihaz kabuğunda (`device_bash`) `$HOME/mnt/OneDrive--PinegrapCMS`. Burada çalışılır.
  - İkinci bağlı klasör `C:\PinegrapCMS` git ajanının klonudur; ERP ajanı dokunmaz.
  - **Dev adresi `https://dev.pinegrap.com/pinegrap/`** (bu makinanın ağacını sunar). Yerleşik tarayıcıda "seed" sekmesinde oturum açık.
- İkinci makina `acer-n300-pro`: dev `http://localhost/pinegrap/`, kendi veritabanı; orada `device_bash` yoktu, yalnız stage/commit ile çalışılıyordu. Hangi makinada olduğunu `get_device_info` söyler.
- **Cihaz kabuğunda python3 var, php yok.**
  - Düzenleme cihazda python ile: satır sonu dosyadan algılanır (çoğu PHP CRLF; `2026.4.4.php`, `2026.4.5.php`, `tr.json`, `docs/*.md`, `changelog.txt` LF), eski metin tam bir kez eşleşmeli, yazım `.tmp_sync` + `os.replace` ile atomik.
  - Kontroller bulutta: cihazda `tar czf dev/_sync/tree.tgz --exclude=pinegrap/data pinegrap tools docs/CLAUDE-tam.md` → stage → bulutta aç → `php tools/lint.php`, `php tools/check_lang.php`, `php tools/check_api_schema.php` (son koşu: 88 yol, 38 nesne, iki eski WARN), `php tools/check_bindings.php`; JS için `node --check`. Tarball sonra `dev/_to_delete/`'e.
- **Tarayıcı:** yerleşik tarayıcıda tıklama ve klavye güvenilmez (pane gizliyken zaman aşımı). `javascript_tool` ile `fetch()` + `FormData` ya da `form.submit()`; `location.reload()` dönüş değerini öldürür, sayfa durumu ayrıca okunur.
- **Şema adımı koşturucu:** `pinegrap/_dev_run_4XX.php` (şablon `dev/_to_delete/probes-2026-09-23/_dev_run_4102.php`: `init.php`, `validate_user()` + `validate_area_access($user, 'manager')`, runner + `INSTALL_OR_UPDATE` + `NO_ENGINE_SUBSTITUTION`, tek upgrade fonksiyonu). Tarayıcıdan `fetch` ile **iki kez** koşturulur (ikincisinde hepsi atlanmalı). Bitince aslı `dev/_to_delete/probes-…/`'e kopyalanır, dosya `pinegrap/`'tan `mv -n` ile çıkarılır.
- **Çeviri:** `lang('…')` anahtarları; `check_lang.php` eksikleri söyler. Ekleme: `dev/_sync/tr_add_NN.json` (`{"anahtar": "çeviri"}`) → `python3 dev/_sync/tr_append.py dev/_sync/tr_add_NN.json` (var olanı atlar) → json `dev/_to_delete/`'e.
  - **Yanlış anlamlı eski anahtarlar:** `'Quote'` "Alıntı", `'By'` "Şuna Göre", `'From'`/`'To'` "Şundan"/"Şuna", `'Count'` "Adet", `'Record'` "Kayıt", `'Due'` "Bitiş", `'Taken in'` "ERP'ye alındı". Yerine `'Sales quote'`, `'Sent by'`, `'Start date'`/`'End date'`, `'Add to the count'`, `'Save'`, `'Overdue'`, `'Taken in on'` gibi yeni anahtar.
- **Tuzaklar:**
  - `config`, `orders`, `log` MyISAM: işlem geri almaz; ayarı değiştiren probe değeri kendisi geri yazar.
  - `includes/settings/registry.php` `PG_SETTINGS_ENTRY`/`PG_SETTINGS_MENU` tanımlı değilse sessizce `exit` eder; probe onu yüklemez.
  - MySQL ayrılmış sözcükleri kolon/alias olmaz (`lines` → `line_data`, `line_count`).
  - `pg_page_shell`: `heading_description` kaçışlanır (düz metin ver), `heading` ham.
  - `d-flex` sınıfı `hidden` özniteliğini ezer (iç sarmalayıcı kullan).
- **Dev veritabanının durumu:** otomatik e-posta ve e-belge gönderimi kapalı; İşbaşı kimlikleri girilmedi; denemeler sırasında `push_subscriptions` boştu.

## Migration adımları

**2026-09-26: 2026.4.4 yayında ve kapalı** (GitHub'daki ilk release). Yeni ERP adımları açık sürüm **2026.4.5**'e girer: `includes/migrations/2026.4.5.php`, `upgrade_2026_4_5_erp_<konu>()`, etiketler 2026.4.4'teki yapıyla **5.58–5.69** (dolunca 5.90–). Dosyayı ve `versions.php` satırını ilk şema adımını yazan açar (satır eklenince dev paneli yükseltme ekranına yönlenir; yükseltme hemen koşulur). Yeniden koşturma için `config.version` artık `2026.4.4`'e çekilir.

### 2026.4.4 (kapalı)

- ERP: 4.58 line_offers · 4.59 walkin_account · 4.60 document_templates · 4.61 edoc_providers · 4.62 edoc_log · 4.63 edoc_autosend · 4.64 invoice_locality · 4.65 document_files · 4.66 cash_order · 4.67 edoc_inbox · 4.68 withholding_amount · 4.69 edoc_accounts · 4.70 tax_number_width · 4.71 state · 4.72 document_settings · 4.73 country_defaults · 4.74 local_sale_prices · 4.75 second_tax · 4.76 shipping_tax · 4.77 stock · 4.78 accountant · 4.79 period_lock.
- 4.80–4.89 Çalışma Alanı'nın.
- ERP devamı: 4.95 expenses · 4.96 expense_recurring · 4.97 document_mail · 4.98 credit_limit · 4.99 stock_minimums · 4.90 audit · 4.91 alerts · 4.92 quotes · 4.93 invoice_recurring · 4.94 account_prices · 4.100 stock_counts · 4.101 cheques · 4.102 bank_statements.
- Son ERP adımı 4.103 (muhasebe kuralları); 2026.4.4'e yeni adım eklenmez. Etiket yalnız addır; çalışma sırası sürüm dosyasındaki çağrı listesidir. Listenin sonundaki `upgrade_2026_4_4_chat_audio(); // 4.63` başka ajanın, ERP numarası değil.
- Yeni tablo → `install/index.php` `get_tables()` listesine. Adımlar idempotent `install_*` yardımcılarıyla; her biri dev'de iki kez koşturuldu.

## Bitmiş işler

- **2026-09-21'e kadar:** Faz -1…5 (cari, kasa, döviz, sipariş→fatura, elle fatura, PDF, iade/iptal, irsaliye, mutabakat, kasa akışı, dış API + webhook), yerel satış, e-Belge sürücü katmanı (Paraşüt/İşbaşı), İşbaşı gönderim zinciri.
- **2026-09-22/23 (öğleye kadar):** sipariş belge kartı, belgelerin kesildiği hâliyle saklanması (4.65), sipariş iptali/iadesi (4.66), gelen e-faturalar (4.67), tevkifat (4.68), cari eşitleme (4.69), ERP Türkiye dışında iki tur (4.70–4.75).
- **2026-09-23 öğleden sonra:**
  - Doğrulama turu (ayar kartı, üç numara biçimi, ikinci vergili fatura ve iade, alıcı eyaleti, API'den eyalet; dört düzeltme).
  - Tezgâhta dile göre tutarlar; kargo, ek ücret ve taksit masrafının vergisi (4.76).
  - Stok ve maliyet: alış stoğa girer, ağırlıklı ortalama / son alış (4.77) ve kalanları.
  - Muhasebeci paketi, süreli bağlantı, aylık e-posta (4.78); salt okuma muhasebeci kullanıcısı; dönem kilidi (4.79); tablolarda başlıkla sıralama.
  - Gider fişleri (4.95), kâr/zarar, tekrarlayan giderler (4.96); gider API'si, Dosya Yöneticisi'nde gider fişleri, fiş dosyasını değiştirme; menü adları.
- **Küçük işler:** faturayı müşteriye e-postala (4.97), kredi limiti (4.98), minimum stok uyarısı (4.99), tezgâhta karışık ödeme (şema yok), denetim izi ekranı (4.90), anlık bildirim + PWA push (4.91).
- **Orta işler:** teklif/proforma → fatura taslağı (4.92, ayrı `erp_quotes` tablosu), tekrarlayan fatura (4.93), cari fiyat listesi ve iskonto (4.94), barkodla stok sayımı (4.100), çek/senet portföyü (4.101), banka ekstresi CSV içe alma ve eşleştirme (4.102).
- Ayrıntı ve gerekçeler `docs/degisiklikler.md`'de; dosyalar skill'in "Dosya haritası"nda.

## Dev'de bırakılan deneme kayıtları (silinmez)

Teklifler; tekliften açılan taslak #63; tekrardan açılan taslak #64; tekrarla kesilen PGF2026000000033; durdurulmuş iki fatura tekrarı; "Deneme sayımı" stok sayımları; portföy/ciro/karşılıksız adımlarından geçmiş çekler ve senetler; "Deneme Müşteri A.Ş." eşleşmeli banka ekstresi; karışık ödemeli tezgâh satışı; önceki turların deneme verisi (ayrıntı `docs/degisiklikler.md`).

## Doğrulanmamış / açık kalanlar

1. **Push'un cihaza düşmesi:** sistem var ve ERP bildirimleri onu kullanıyor (`erp_alert_create()` → `pg_push_enqueue_notification()`). Push yalnız `push_subscriptions`'ta cihazı kayıtlı (panelde bildirimlere izin vermiş) kullanıcılar için kuyruğa girer; denemelerde bu tablo boştu, o yüzden yalnız zildeki bildirim görüldü. Tahsilat bildirimi onu giren kişiye gitmez: tek hesapla denenemez. Deneme: ikinci yetkili kullanıcı telefondan bildirimlere izin verir, birinci kullanıcı tahsilat girer; ya da minimum stok işi (`erp_stock_alert_job`, sahibi yok).
2. Ayarlar → ticaret → "Anında bildirimler" bloğunun kaydı (POST kuralı yüzünden denenmedi) — Erdal'la ekrandan.
3. Denetim izinde "API" kaynağı gerçek bir API anahtarıyla denenmedi (panelden yapılanlar denendi).
4. Mağazanın kendi eyaletiyle oran (ABD mağazası); siparişten faturanın sürekli biçimle gerçek kesimi.
5. İşbaşı kimliği isteyenler: e-belge gönderimi, cari eşitlemede bağlı kart koduyla gönderim, e-posta PUT'u, yabancı ülke kartı.
6. Çek/senet için Kasa ve Banka'da portföy kasası ve verilen çek kasası kullanıcı tarafından açılır (ekran söyler).
7. Yeni kayıt türleri (teklif, fatura tekrarı, cari fiyatı, sayım, çek, ekstre) dış API'de yok.

## Sırada ne var (aday)

- **Bilerek atlanan orta işler:**
  - Siparişi birden çok faturaya bölme: büyük çıktı (sipariş kartı, stok hareketi, iade zinciri etkilenir).
  - Faturaya ödeme linki + müşterinin "Faturalarım" sayfası: iyzico'ya bağlı ve başka oturumdaki mağaza hesap widget'larına dokunuyor; başlamadan Erdal'a sorulur.
- **Dış bağımlılıklı (Erdal "geç" dedi):** iyzico hakedişi, pazaryeri hakedişi, SMS/WhatsApp, Paraşüt sürücüsü, e-İrsaliye, açık bankacılık, `erp_edoc_queue` işleyicisi, canlı İşbaşı anahtarı.
- **Küçük adaylar:** ayrı KDV raporu ekranı (E1); vade farkı (C4'ün kalanı); banka ekstresinde xlsx okuma; yeni kayıt türlerini dış API'ye açma; fatura e-postasında okundu bilgisi.
- **İşbaşı tarafında bekleyen:** "İrsaliye yerine geçer" (bekle); gelen e-İrsaliyeler (`POST dispatch/mydispatchlist`); serbest meslek makbuzu (`POST Payments/smms`, `PUT Payments/smm`).
- **Logo'ya sorulacaklar:** internet satışında ödeme tarihinin alanı/biçimi (e-Arşiv 599; biçim denemeye devam etme); `dispatchIncluded` kapatılabilir mi; e-Arşiv'i API ile iptal; satış iadesini API ile kaydetme; özel matrahta satır matrahı; ihracat faturası; başarılı gönderimin neden HTTP 400 döndüğü.
- **Mali müşavire:** tüketici iadesinde yol; e-Fatura alıcısında iadeyi alıcının kesmesi; "irsaliye yerine geçer" ayrıntısı.

## İşbaşı'dan öğrenilenler (ayrıntı `docs/_isbasi_api_notlari.md`)

- Tarih sırası reddi `400 "Girilen tarihten sonra aynı tipte fatura kesilmiş…"`; e-Fatura / e-Arşiv ayrı sayılır. Test kiracısı paylaşımlı.
- Şema `shipmentAgentItem` (belgedeki `shippingAgentItem` değil); `invoiceDate` `yyyy-MM-dd HH:mm:ss`.
- Gelen faturalar `POST einvoices/myInvoicesList`; tür `EGovermentTypeDesc`'ten; ETTN sağlayıcının harf büyüklüğüyle.
- `master/withholdings`, `master/vatexcepts` → 404; tevkifat kodları GİB listesinden.
- Cari: ~913 kart, veri kirli; `firmType` 1 müşteri / 2 tedarikçi / 3 ikisi; faturanın `firm {id, code, name}` alanı düştüğü kartı söyler.
- İşlenmemiş e-Arşiv iptal edilemiyor; satış iadesi yalnız panelde; her entegrasyon faturası `dispatchIncluded: true`.

## Erdal'ın yapacakları

- `Claude outputs/` klasörünü kaldırmak (skill `.claude/skills/pinegrap-erp/SKILL.md`'de, not `docs/_erp_devir_notu.md` ve projede).
- `dev/_to_delete/` altını gözden geçirip boşaltmak: `probes-2026-09-23/` (probe ve koşturucu asılları), `skeletons-2026-09-24/` (`pinegrap/`'tan çıkarılan 28 boş iskelet), `sync-2026-09-23/`, `skill-2026-09-24/` (skill'in eski hâli).
- Çek/senet kullanılacaksa portföy ve verilen çek kasalarını açmak.
- İsteğe bağlı: push denemesi için ikinci bir kullanıcıyla telefondan bildirim izni.
- İşbaşı işine dönülecekse test kimliklerini girmek; Logo'ya soruları iletmek.

## Yeni ajan için başlangıç promptu

```
Pinegrap CMS'in ERP (ön muhasebe) modülünde geliştirmeye devam edeceksin. Benimle Türkçe konuş.

Başlamadan önce sırayla oku:
1. Proje dokümanı claude/erp-devir-notu.md (aynısı cihazda docs/_erp_devir_notu.md): kurallar, ortam, migration numaraları, açık işler, deneme kayıtları.
2. .claude/skills/pinegrap-erp/SKILL.md: ERP sözleşmesi ve dosya haritası. Şema adımı yazacaksan .claude/skills/pinegrap-sema-adimi/SKILL.md; başka konular için .claude/skills/README.md.
3. CLAUDE.md; gerektiğinde docs/CLAUDE-tam.md, docs/_plan_erp.md ve docs/_erp_yol_haritasi_adaylari.md ("0. Durum").

Klasör: C:\Users\erdal\OneDrive\PinegrapCMS (cihaz kabuğunda $HOME/mnt/OneDrive--PinegrapCMS). C:\PinegrapCMS git ajanının klonu, ona dokunma. Dev: https://dev.pinegrap.com/pinegrap/ (yerleşik tarayıcıda oturum açık). Önce get_device_info ile hangi makinada olduğuna bak; başka makinadaysan devir notundaki ortam bölümüne göre çalış.

Değişmez kurallar: commit/push/PR yapma; hiçbir yere Claude atıf satırı yazma; kalıcı silme yapma, dev/_to_delete/ altına taşı; kimlik bilgisi girme ve üretme; settings_pane.php'ye POST yapma; Çalışma Alanı dosyalarına dokunma; ortak dosyaları dokunmadan hemen önce oku ve yalnız ekle; kod yorumları İngilizce ve iz bırakmasın; PHP 7.0 uyumlu yaz; her özelliği docs/degisiklikler.md + pinegrap/changelog.txt'ye, şema adımını docs/CLAUDE-tam.md tablosuna yaz; ürün arayüzünden dene, deneme kayıtları kalsın; dev'de e-posta mekanizması yok, e-postanın ulaşmasını deneme. Büyük ya da dış bağımlılığı olan işlere ben istemeden başlama. Devir notunu yalnız ben isteyince güncelle.

Görev: <buraya iş>

İşe başlamadan önce kısa bir planla ne yapacağını söyle.
```
