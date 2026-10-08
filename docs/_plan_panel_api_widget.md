# Plan 1 — Panel `api.php` ve pano widget'ları (ana ajan Fable 5.1, alt ajanlar Opus 5.5)

Durum: plan (2026-10-08). Kaynak keşif: `docs/_tespit_2026_10_08/refactor_altyapi_raporu.md` maddeler 1, 2, 4, 5, 7, 6.
Durum (2026-10-08): Faz 0–2 tamamlandı (PR'da), Faz 3 sırada. Faz 2'de widget'lar `echo`+`exit` yerine dizi döndürüyor; `api.php` `respond()` ediyor.
Durum (2026-10-08): Faz 3 ilk parça (11 eylem: software, system, explorer, search grupları → `includes/panel/`) PR'da. Tablonun `exempt`/`token`/`write` sütunları bilgi amaçlı, dağıtıcı kapı uygulamıyor; muafiyet zincirinin tablodan üretilmesi sonraki PR'da.
Satır numaraları `development` @ `0294c42`'ye göre; kaymışsa grep ile bul, yeniden keşif yapma.

## 0. Sabit bağlam (her oturumda, kısaca)

- Depo kökü `/workspace/pinegrapcms`, ürün kodu `pinegrap/`. Önce `CLAUDE.md`; skill'ler: `.claude/skills/pinegrap-yeni-dosya/SKILL.md`, `pinegrap-js-varliklari`, `pinegrap-ui-deseni`, `pinegrap-roller-yetki`, `pinegrap-verilmis-kararlar`. Alt ajana görev yazarken skill **dosya yolunu** göreve yaz; alt ajan konuşmayı görmez.
- Dal `development`'tan açılır, PR tabanı `development`. Commit/PR'da AI izi yok.
- Ortak dosyalar (`includes/local/tr.json`, `changelog.txt`, `init.php`, `docs/degisiklikler.md`, `docs/CLAUDE-tam.md`): yazmadan önce `git fetch origin development && git merge origin/development`; yalnız ekleme, kendi bölümüne; push öncesi rebase.
- Şema adımı gerekmiyor (bu planda migration yok). Gerekirse önerilen aralık **8.50–8.57** (Erdal onaylar).
- Test koşucusu: `php tools/test.php` + `tests/*_test.php` (development'ta; yoksa merge et). Yeni test dosyası nasıl yazılır: `tools/test.php` başlık yorumu.
- Bitiş kriteri (her fazda): `php tools/lint.php`, `php tools/check_lang.php`, `php tools/test.php`, `php tools/check_bindings.php` (tasarımcıya dokunulduysa), `node --check` (JS'e dokunulduysa) temiz; yorumlar İngilizce; PR açıklamasında "doğrulayamadıklarım".
- **Paralel planlar — dokunma:** `includes/api/**`, `includes/fn/mail.php`, `email_campaign_job.php`, `includes/fn/cron.php`, `job.php` (Plan 4); `includes/migrations/**`, `includes/fn/core.php` db yardımcıları (Plan 2); `update_order.php`, `view_order.php`, `view_orders.php`, `edit_orders.php`, `includes/fn/ecommerce.php` kargo bölümü (Plan 3); `index.php`, `*_password.php`, `includes/fn/auth.php`, `get_file.php`, `api.php:148-262` kapı zinciri (Plan 5).
- **Sıra kısıtı:** Faz 3 (eylem tablosu) Plan 5'in CSRF adımı (`api.php`'deki 6 yazan uca `validate_token()`) **merge olduktan sonra** başlar; Faz 1–2 bağımsızdır.

## 1. Kapsam ve dosya sahipliği

| Faz | İş | Sahip olunan dosyalar |
|---|---|---|
| 1 | `api.php:273` `include_once('mysqldump.php')` kaldır | `api.php` (yalnız o satır) |
| 2 | `get_widget_data` (`api.php:844-8303`, 27 widget) → `includes/dashboard/widgets/<id>.php` | `api.php` case gövdesi, yeni `includes/dashboard/` |
| 3 | Satır içi ~81 case → eylem tablosu + modül dosyaları | `api.php` switch, yeni `includes/panel/` |
| 4 | JS derleme betiği + `style_designer.js` `.src/.min` ikizi | `tools/build_js.sh`, `assets/js/style_designer*.js`, `includes/designer_screen.php:502`, `includes/fn/designer.php:1936-1966`, `tools/check_lang.php` (tarama yolu) |
| 5 (ayrı PR, opsiyonel) | Liste ekranı durum yardımcısı + `view_products.php` | `includes/fn/output.php` (yeni fonksiyon), `view_products.php` |
| 6 (ayrı PR, opsiyonel) | `add_page`/`edit_page` ortak form | `includes/forms/page_form.php`, iki kök dosya |

## 2. Adımlar

### Faz 0 — Altın kayıt (önce, mutlaka)
- **Yap:** `bash tools/setup_sandbox.sh`. Panel yöneticisiyle oturum açan küçük bir betik (`tools/_golden_api.php` **geçici**, PR'a girmez; ya da Playwright) her widget için `{"action":"get_widget_data","widget_id":"<id>","token":…}` POST eder; `clock`, `1`…`26` (22 ve 24 dahil — emekli ama case'leri var, aynen taşınır). Yanıtları `/tmp/golden/widget_<id>.json`'a yaz; zaman damgası/rastgele alanları normalize et (regex ile `"timestamp":\d+` → `0`).
- Aynı betikle kapı davranışı: oturumsuz istek, rol 3 kullanıcı, tokensız istek → üç durumun yanıtı (mesaj metni dahil) her eylem için `/tmp/golden/gate_<action>.json`.
- **Kanıt:** 27 widget + gate dosyaları diskte; betik tekrar koşunca fark yok (deterministik).
- **Devredilebilir:** evet (tek alt ajan; betik ve normalize kuralları raporlanır).

### Faz 1 — `mysqldump.php` gereksiz yükleme (K)
- **Yap:** `api.php:273` satırını sil. `software_backup` case'i (`api.php:~8945`) kendi `include_once`'ını zaten yapıyor; `grep -n "mysqldump\|MySQLDump" api.php` ile başka kullanıcı olmadığını doğrula.
- **Kanıt:** sandbox'ta `software_backup` → `create_mysql_dumb` adımı çalışıyor (yedek dosyası oluştu); golden fark yok.

### Faz 2 — Pano widget'ları ayrı dosyalara (O)
- **Tasarım:**
  - `includes/dashboard/widgets.php` (kapı sabiti `PG_DASHBOARD_WIDGETS`): `pg_dashboard_widget_run($widget_id, $request, $user)` → `includes/dashboard/widgets/<id>.php` dosyasını `require_once` eder, `pg_dashboard_widget_<id>($request, $user)` çağırır; dosya yoksa bugünkü `default` dalının yanıtı.
  - Widget dosyası: Pinegrap başlığı + kapı sabiti; gövde case'ten **aynen** taşınır (davranış değişmez; `echo encode_json(...); exit();` kalıbı korunur, `respond()` zaten exit eder). `$output_rows`, `$user`, `$widget_id` gibi case yerel değişkenleri fonksiyon parametresi/yerel olur; `global` kullanılmaz. `session_write_close()` ve `validate_user()` `api.php`'de kalır.
  - Case gövdesi: `$user = validate_user(); session_write_close(); pg_dashboard_widget_run(...)`.
  - Dosya adları `widget_1.php` … `widget_26.php`, `widget_clock.php`. 22 ve 24 taşınır, silinmez, "retired" yorumu İngilizce.
- **Devredilebilir:** evet, **mekanik.** 27 widget'ı 3–4 alt ajana böl; her göreve: kaynak satır aralığı (`grep -n "^            case '<id>':" api.php` ile güncel), hedef dosya adı, başlık/kapı şablonu (`pinegrap-yeni-dosya` yolu), "gövdeyi değiştirme, yalnız yerel değişkenleri parametreye bağla", "`lang()` anahtarlarını değiştirme". Ana ajan `widgets.php` yükleyicisini ve `api.php` case değişimini kendisi yazar.
- **Kanıt:** golden 27 dosya birebir; `php tools/test.php` (yeni `tests/dashboard_test.php`: `pg_dashboard_widget_run('clock', …)` DB'siz `status=success` döner; 27 dosyanın hepsi için `function_exists('pg_dashboard_widget_<id>')`); `wc -l api.php` ≈ 7.600; lint, check_lang temiz.

### Faz 3 — Eylem tablosu (B, kademeli; Plan 5 CSRF adımı merge olduktan sonra)
- **Önce envanter (devredilebilir):** `docs/_panel_api_eylemler.md` — her case için: ad, satır aralığı, bugünkü kapı (negatif listede mi `api.php:148-262`, kendi `validate_user`/`USER_ROLE` kontrolü, `validate_token` var/yok), yazıyor mu, hangi istemci dosyadan çağrılıyor (`grep -rn "action: *'<ad>'\|action\":\"<ad>\"" assets/js *.php`). Bu tablo golden gate dosyalarıyla karşılaştırılır.
- **Tasarım:** `includes/panel/actions.php` `pg_panel_actions()` → `['<action>' => ['file' => 'includes/panel/<grup>.php', 'handler' => 'pg_panel_<grup>_<action>', 'gate' => 'designer|manager|session|public', 'token' => true|false]]`. `api.php`: eylem tabloda ise kapıyı tablodan uygula (mesaj metinleri bugünkülerle **aynı**), handler'ı çağır, `respond()`. Tabloda yoksa eski `switch` çalışmaya devam eder (kademeli geçiş).
- **Sıra:** `software_update`/`software_backup`/`software_*` → `file_explorer`+`explorer_*` → `designer`, `designer_file` → `shared_component` → `backend_search` → kalan. Her grup ayrı commit; `ws_`/`chat_` zaten modülde, tabloya yalnız satır olarak girer.
- **Devredilebilir:** grup taşımaları evet (göreve: envanter satırları, hedef dosya, kapı değeri, "gövde aynen"). Tablo ve `api.php` dağıtıcısı ana ajanda.
- **Kanıt:** golden gate dosyaları birebir (üç kapı durumu × her eylem); `tests/panel_actions_test.php`: tablodaki her eylem için dosya var + fonksiyon var, `gate` değeri izinli kümede, `switch`'te kalan case ile tabloda olan çakışmıyor; `php tools/check_lang.php`; sandbox'ta dosya yöneticisi, tasarımcı kaydet, güncelleme kontrolü, yedek al elle.

### Faz 4 — JS derleme ve `style_designer.js` ikizi (O)
- **Yap:** `tools/build_js.sh`: terser (`npx terser@5`) bayrakları `--compress --mangle --format ascii_only=true` sabit; `.src.js` → `.min.js` listesi dosyada; `--check` kipinde ikizin güncel olup olmadığını (hash) raporlar. `style_designer.js` → `style_designer.src.js` + `.min.js`; `includes/designer_screen.php:502` `.min` servis eder; `pg_designer_i18n_keys()` (`includes/fn/designer.php:1936-1966`) ve `tools/check_lang.php` **`.src`** dosyasını tarar; `install/index.php:2524` yolu güncellenir.
- **Risk:** mangle `_sdT('literal')` çağrılarını bozmaz (string literal), ama tarayıcı kaynak dosyada kalmalı. `pg_designer_asset_stamp()` yeni dosya adıyla çalışmalı.
- **Kanıt:** `node --check` iki dosya; `php tools/check_lang.php` (anahtar sayısı öncekiyle aynı — önce/sonra say); Playwright: `add_system_style.php?framework=bootstrap5` açılıyor, öğe sürükleniyor, kaydediliyor, konsolda hata yok; boyut: ham 3,08 MB → min ölçülen değer PR'a yazılır. `.github/workflows/php-checks.yml`'e `bash tools/build_js.sh --check` adımı (Plan 4 da bu dosyaya adım ekleyebilir → merge öncesi fetch).

### Faz 5–6 (opsiyonel, ayrı PR'lar)
- **Faz 5:** `pg_list_state($screen, $columns, $default)` (`includes/fn/output.php`): sıralama anahtarı **kolon kodu**, etiket değil; `$_REQUEST['screen']` doğrulaması tek yerde. Yalnız `view_products.php:137-330` 8 kopya blok buna geçer; DataTables davranışı değişmez. Kanıt: `tests/list_state_test.php` (geçersiz kolon → varsayılan, `DROP` → `asc`), sandbox'ta ürün listesi sıralama/filtre/dil değiştirme.
- **Faz 6:** `add_page.php`/`edit_page.php` (1.759 ortak satır) → `includes/forms/page_form.php`; ERP `includes/erp/invoice_form.php` deseni. Kök dosyalar ince sarmalayıcı kalır (taşınmaz). Kanıt: sandbox'ta sayfa ekle/düzenle, tüm alanlar (önce alan listesini çıkar, her alanı kaydet-oku karşılaştır).

## 3. Varsayılan kararlar (itiraz yoksa böyle)
- Widget id'leri ve yanıt biçimleri değişmez; 22/24 taşınır, silinmez.
- Kapı mesaj metinleri aynen korunur (golden ile kanıtlanır).
- `switch` kademeli boşalır; tek PR'da tamamen kaldırılmaz.
- `style_designer.js` **bölünmez** (ikiz + minify yeter); bölme ayrı karar.

## 4. Elle doğrulanacaklar (Erdal, dev/canlı)
- Pano tüm widget'larıyla gerçek veride; dosya yöneticisi büyük klasörde; tasarımcıda `.min` ile gerçek tasarım; güncelleme kontrolü canlı kanalla.

## 5. PR açıklaması şablonu
Ne değişti · Neden (keşif maddesi) · Golden karşılaştırma sonucu (kaç dosya, fark 0) · Çalıştırılan denetimler ve çıktıların son satırı · **Doğrulayamadıklarım** · Sorular (varsa).
