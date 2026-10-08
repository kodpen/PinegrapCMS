# Ön yüz çok dil: sanal dil dizini + çeviri katmanı (çok motorlu)

Durum: **plan** (2026-10-04), henüz kod yok; §15'teki öneriler Erdal
tarafından onaylandı (4. soru hariç). Açık sürüm 2026.4.6. Şema
adımları genel aralıkta (6.1 dolu; yazılırken `2026.4.6.php`'deki sıradaki
boş numara alınır, şema adımı `pinegrap-sema-adimi` kontrol listesiyle
yazılır).

İlgili: `docs/_plan_ai_tasarim.md` (tasarım asistanı, öneri/hash deseni),
`docs/_plan_claude_kanal.md` (Claude rutini), `docs/pinegrap-ai-gecit.md`
(@ai geçidi), `docs/_plan_shared_components.md` (ortak bileşenler).

Satır numaraları 2026-10-04 ağacına göredir; yazmadan önce yeniden bakılır.

## 0. Hedef (Erdal)

- Görsel tasarımcıda yapılmış sayfaların **tasarımı değişmeden** başka dilde
  sunulması: sanal bir dil dizini (`/en/hakkimizda`), dosya kopyası yok.
- Sayfa güncellenince **"Çevirileri güncelle"** ile yalnız değişen metin
  yeniden çevrilip saklanır.
- **Birden çok çeviri kaynağı:** Google Cloud Translation, tarayıcıda Chrome
  Translator API, yazılımdaki **Pinegrap AI (@ai)** ve **Claude**; ileride
  başkaları (DeepL vb.). Dış API metinleri verir, karşılıkları okunup
  kaydedilir. Hangi kaynağın kullanılacağı **site ayarlarından** belirlenir.

Bağlam (2026-10-04 konuşması): Google'ın arama sonuçlarındaki "Google
tarafından çevrildi" özelliği site sahibinin elinde değil (yalnız
`notranslate` ile kapatılır), ziyaretçiyi `*.translate.goog` vekiline
götürür; bunun yerine sitenin kendi, indekslenebilir dil sayfaları hedefleniyor.
Google'ın gömülü çeviri widget'ı (`translate_a/element.js`) 2026-10-01'de
desteği bitti; arka planda çıktısını toplamak şartlara aykırı ve kırılgan —
**motor olarak kullanılmayacak**.

## 1. Temel kararlar

1. **Çeviri birimi sayfa değil, metindir (segment).** Her kaynak metin
   normalleştirilir, özeti (`sha1`) anahtar olur, çevirisi dil başına bir kez
   saklanır. "Sepete ekle" kırk sayfada geçse de tek kayıttır.
2. **Tasarım kopyalanmaz.** `/en/` isteğinde aynı ağaç çizilir; metin düğümleri
   ve izinli nitelikler çizim anında çeviri tablosundan okunur. Türkçe sayfada
   renk/düzen değişince İngilizcesi de değişir, çeviri gerekmez.
3. **Motorlar yalnız çeviri tablosuna yazar.** Hiçbir motor (dış ajan dahil)
   sayfa ağacına, `page_tree_code`'a ya da kayda dokunmaz. Gelen çeviri
   güvenilmeyen girdidir: temizlenir, yer tutucuları denetlenir (§6).
4. **Çeviri yoksa kaynak görünür.** Eksik metin kaynak dilde çıkar; sayfa
   "kısmi" sayılır (indeks politikası buna bakar, §9).
5. **Elle çeviri korunur.** Kaynağı değişen metin yeni bir segmenttir; eski
   çeviri silinmez, aynı yerdeki önceki çeviri gözden geçirme ekranında ve
   yapay zekâ motorlarına bağlam olarak sunulur.
6. **Dil öneki `PATH`'e girmez.** `PATH` / `OUTPUT_PATH` yazılım dizini, varlık
   ve AJAX adreslerini de kuruyor, `init.php:773-776` onu `config.path`'e geri
   yazıyor. Önek ayrı sabitte tutulur (§3).
7. **Otomatik yönlendirme yok.** `Accept-Language`'e göre `/en/`'e atılmaz
   (Googlebot dil başlığı göndermez); dil seçici + `hreflang`. Öneri bandı
   ileride, isteğe bağlı.

## 2. Mevcut durum (kodda doğrulandı)

- **Tek dil:** `lang()` (`includes/fn/core.php:2838`) önce
  `ENFORCEMENT_SOFTWARE_LANGUAGE`, sonra `SOFTWARE_LANGUAGE` sabitini okur;
  `init.php:726` bunu `config.software_language`'den (ENUM en/tr) yalnız
  tanımlı değilse tanımlar. Panel ve site aynı dili kullanıyor. Dil dosyası
  `includes/local/tr.json` (anahtar İngilizce kaynak), `en.json` boş; dosyası
  olmayan dilde `lang()` İngilizce anahtarı döner.
- **Sayfa gövdesi kaydederken çiziliyor:** `pg_designer_save_page()` →
  `generate_style_code_from_tree()` (`designer.php:326`) → `page.page_tree_code`.
  İstek başına çizilen: ortak bileşenler (`_expand_shared_refs`), sistem
  widget'ları (`_expand_system_widgets`, `designer.php:709`), bölgeler, menüler.
  Her ağaç `_render_tree_node()`'dan (`designer.php:2489`) geçiyor.
- **Ağaç düğümü:** `{_id:'sd_N', type, props:{…}, children:[…]}`. Metin
  `props` içinde: `text` (content heading/paragraph/link/span/text'te satır içi
  HTML, semantic düğümde düz metin), `alt`, `_attrs:[{name,value}]`, bileşen
  propları (btn `text`, hero `title/subtitle/btnText`, card
  `headerText/title/text/footerText`, navbar `brand`, alert/badge `text`),
  `custom_html` içinde `html`. `_bindings` ile bağlı proplar veri, çevrilmez.
- **`<html lang="en">` sabit** (`designer.php:390`); `hreflang` hiçbir yerde yok,
  sitemap'te `xhtml:link` yok. Önizleme `designer.php:5714`'te `lang`'ı regex'le
  değiştiriyor — aynı yöntem kullanılır.
- **İç bağlantılar merkezsiz:** ~40 dosya `OUTPUT_PATH . encode_url_path(ad)`
  kuruyor; görsel ağaçta `href` olduğu gibi saklanıyor. Sayfanın çıktığı tek
  nokta `get_page.php:3187-3191` (`pg_version_assets()` → `echo`).
- **Akıllı etkin bağlantı** `pg_apply_smart_active()` (`designer.php:4193`)
  `REQUEST_URL` ile `PATH.page_name`'i eşleştiriyor; önekte bozulur.
- **Tam sayfa önbelleği yok** (LiteSpeed başlığı URL'e göre zaten ayrı).
- **Sabit Türkçe:** `widgets_catalog.php:1533,1537` `"Fiyat:"`, `"Uygula"`
  `lang()`'sız — düzeltilecek.
- **Kuplaj:** `widgets_cart.php:80,109` ağaçtaki etiketi
  `lang('Proceed to Checkout')` / `lang('Update')` ile karşılaştırıyor.
- **Biçim dizeleri `lang()`'dan:** sayı ayırıcıları `lang('1,234.56')`
  (`pg_number_separators()`, `ecommerce.php:4540`), tarih `lang('j/n/Y')`. Bu
  anahtarlar makine çevirisine **gönderilmez**; dil başına yerel ayardan gelir (§8).

## 3. Yönlendirme ve dil bağlamı

**Router** (`router.php`, `$request_url_without_path` hesaplandıktan hemen
sonra, ~:247, eski adres yönlendirmesinden önce):

- Etkin dil öneklerinin listesi `config.translation_prefixes` (CSV) sütunundan
  okunur — router ham `mysqli` kullandığı için tek sütun, tablo sorgusu yok.
- `^(en|de|…)(/|$)` eşleşirse önek kesilir, şunlar tanımlanır:
  - `FRONTEND_LANGUAGE` = `en`
  - `LANGUAGE_PATH` = `PATH . 'en/'` (bağlantı ve canonical için)
  - `SOFTWARE_LANGUAGE` = `en` (`init.php:726` tanımlıysa dokunmuyor)
- `REQUEST_URL` değişmez (`send_to` yönlendirmeleri `/en/…`'de kalır).
- Yalnız `/en` → o dilin ana sayfası.
- **Çakışma:** adı bir dil kodu olan sayfa/dosya/kısa bağlantı gölgede kalır.
  Dil eklenirken çakışan ad uyarısı; sayfa adı kaydında dil kodları yasak.

**`lang()`**: `FRONTEND_LANGUAGE` tanımlıysa `ENFORCEMENT_SOFTWARE_LANGUAGE`'dan
önce o kullanılır. Dosyası olmayan dil (`de`) için arayüz metinleri çeviri
tablosundan (`kind = ui`) bir kez yüklenip aynı statik önbelleğe konur (Faz 4).

**AJAX:** `frontend.src.js`'in `api.php` / `get_region_content.php` çağrıları
dil taşımıyor. `<html data-pg-lang>`'den okunan `pg_lang` parametresi eklenir;
sunucu yalnız etkin dillerden birini kabul eder ve `FRONTEND_LANGUAGE`'ı ondan
tanımlar.

### 3.1 Dosya, görsel, CSS ve JS adresleri — önek almaz

**Kural:** dil öneki yalnız **sayfa adreslerine** gelir. Dosyalar (görsel,
PDF, yüklenen CSS/JS) ve yazılımın kendi varlıkları tek adreste kalır:
`siteadi/dosyaadi.uzanti`, `siteadi/pinegrap/assets/...`. Dosya **çoğaltılmaz**,
`/en/` altında dosya ya da sanal dosya kaydı **oluşturulmaz**; dosya
yöneticisinde hiçbir şey değişmez (yeni klasör yok, kopya yok, silinecek
gölge kayıt yok).

**Neden çalışıyor (kodda doğrulandı):** başında `/` olan adres tarayıcıda
sitenin kökünden çözülür; `/en/hakkimizda` sayfası `/logo.png`'yi yine
`siteadi/logo.png`'den ister. Bugün üretilen adreslerin hepsi kökten:
- tasarımcının dosya seçicisi `OUTPUT_PATH + ad` döner → `/logo.png`
  (`assets/js/style_designer.js:44616`);
- zengin metin `{path}logo.png` saklar, çıkışta `OUTPUT_PATH` olur
  (`get_page_content.php:5996`);
- yazılım CSS/JS'i `OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/…'`;
- tasarımın dış CSS dosyası yalnız tam adres ya da `/` ile başlayan adres
  olarak kabul ediliyor (`get_page_content.php:310-316`);
- sürüm damgası `pg_version_assets()` / `pg_asset_path()` önce
  `FILE_DIRECTORY_PATH`'e, sonra web köküne bakıyor — önekten etkilenmez.

**Bağlantı öneki ayrımı** (`pg_tr_finalize`, §7.6): yalnız ilk yol parçası
bir **sayfa adı** olan bağlantılar önek alır. Router dosyayı sayfadan önce
aradığı için (`router.php:319`) aynı adı taşıyan bir dosya varsa dosya sayılır
ve önek almaz. `files` adı, yazılım dizini, fiziksel dosya, dış adres, `#`,
`mailto:` / `tel:` atlanır.

**Güvenlik ağı — elle yazılmış göreli adres:** `src="logo.png"` (başında `/`
yok) ya da satır içi CSS'te `url(bg.jpg)` `/en/` sayfasında `/en/logo.png`
diye istenir. Router önek kesildikten sonra kalan yol bir dosyaysa (`files`
tablosu ya da fiziksel dosya) **301 ile kök adrese** yönlendirir. Dosyanın
yine tek adresi ve tek önbelleği olur; dosya silinince kök adres 404 verir,
`/en/` yönlendirmesi de oraya düşer — temizlenecek ayrı bir kayıt yoktur.
Ayrıca `pg_tr_finalize` çıktıda dosyaya giden göreli `src`/`href`'leri köke
çevirir; tasarımcı göreli adres yazılınca uyarır (Faz 1).

**Yazılım kurallarıyla çakışma:** dil kodları ayrılmış ad olur. Yalnız tam
segment etkilenir (`^en(/|$)`): `en` adlı sayfa, dosya ya da kısa bağlantı
gölgede kalır; `en.png`, `english`, `en-iyi-urunler` etkilenmez. Dil
eklenirken bu adlarla kayıt varsa ayarlar ekranı listeler ve eklemeyi
durdurur; dil etkinken sayfa / dosya / kısa bağlantı kaydı bu adları reddeder.
`pinegrap` (yazılım dizini) zaten ayrılmış.

**Dile özel görsel** (üzerinde yazı olan afiş gibi): görsel düğümünün `src`'si
de çevrilebilir bir alan olur, çevirisi dosya yöneticisinden seçilen **başka
bir dosyadır** — yine normal bir dosya, yine kök adreste (Faz 5).

## 4. Şema (6.x, genel aralık)

| Tablo | Amaç | Ana sütunlar |
|---|---|---|
| `site_languages` | Hedef diller ve ayarları | `code` (PK, `en`, `de`, `pt-BR`…), `label`, `enabled`, `prefix`, `engine`, `fallback_engine`, `index_policy` ENUM(`reviewed`,`all`,`none`), `locale_number` (`1,234.56`), `locale_date` (`j/n/Y`), `og_locale`, `sort_order`, zamanlar |
| `translation_strings` | Kaynak metinler (segment) | `id`, `hash` CHAR(40) UNIQUE, `source_text` MEDIUMTEXT, `format` ENUM(`text`,`inline`), `kind` ENUM(`content`,`seo`,`ui`,`option`), `chars`, `first_seen`, `last_seen` |
| `translations` | Dil başına karşılık | `id`, `string_id`, `language`, `text` MEDIUMTEXT, `engine` (`google`,`chrome`,`ai`,`claude`,`api:<app>`,`manual`,`import`), `status` ENUM(`machine`,`reviewed`), `updated_by`, zamanlar; UNIQUE(`string_id`,`language`) |
| `translation_uses` | Metnin nerede geçtiği | `string_id`, `owner_type` (`page`,`shared`,`menu_item`,`page_seo`,`widget_config`,`product`, …), `owner_id`, `node_id`, `field`, `position`, `prev_string_id`; INDEX(`owner_type`,`owner_id`) |
| `translation_jobs` | "Çevirileri güncelle" işleri | `id`, `language`, `engine`, `scope` (`page:12` / `all`), `status` ENUM(`queued`,`sent`,`running`,`done`,`failed`,`cancelled`), `total`, `done_count`, `failed_count`, `created_by`, `claimed_at`, `finished_at`, `error` |
| `translation_job_items` | İşin metinleri | `job_id`, `string_id`, `status` ENUM(`waiting`,`done`,`failed`,`skipped`), `error`; PK(`job_id`,`string_id`) |
| `translation_glossary` | Sözlük | `id`, `language` (`''` = hepsi), `term`, `translation` (boş + `keep` = çevrilmez), `keep`, `note`, `case_sensitive` |
| `page_translations` | Dil başına çizilmiş gövde (önbellek) | PK(`page_id`,`language`), `tree_code` MEDIUMTEXT, `fingerprint` CHAR(40), `coverage` (0–100), `rendered_at` |

`config` sütunları: `translation_source_language` (varsayılan
`software_language`), `translation_prefixes`, `translation_google_key`
(`encrypt_string_with_iv()` ile `cipher:iv`, `includes/fn/auth.php:4808`),
`translation_style_note` TEXT, `translation_attribution` (Google rozeti açık/
kapalı — şartlar gereği değiştirilmemiş Google çıktısı varsa zorunlu, §9).

Yeni tablolar `install/index.php` `get_tables()`'a. Çevrilmiş adres (slug)
tablosu Faz 5'e kalır.

## 5. Metin çıkarma (`includes/translate/extract.php`)

`pg_tr_extract_page($page_id)` — ağaç `pg_page_tree_json()` (`seo.php:843`)
ile okunur, `_pg_dai_fill_ids` ile kimlik garanti edilir, sonra:

- **Toplanan:** content `text` (`inline`), semantic `text` (`text`), `alt`,
  izinli `_attrs` (`title`, `placeholder`, `aria-label`, `data-bs-title`,
  `data-bs-content`; buton `value`), bileşen metin propları (§2 listesi),
  widget yapılandırma metinleri (`empty_message`, `not_found_message`,
  `checkout_button_label` … — `designer.php:727-733`'te okunanlar),
  `page_title`, `page_meta_description`, sayfada kullanılan menülerin
  `menu_items.name`'i.
- **Atlanan:** `_bindings` ile bağlı proplar, `php`, `customName`, `_notes`,
  `_aiCss`, `href`/`src`, boş/yalnız sayı/yalnız simge metinler.
- **`shared_ref`:** ortak bileşen ağacı kendi sahibiyle (`owner_type=shared`)
  bir kez çıkarılır; sayfa onu kullandığını bilir.
- **`custom_html`:** DOM yürüyüşüyle blok düzeyinde bölünür (Faz 5).
- **Normalleştirme:** kırp, boşlukları tekle, varlıkları çöz, satır içi
  etiketleri `pg_design_ai_clean_inline()` kümesine indir; özet
  `sha1(format . "\n" . metin)`.
- **Parmak izi:** sayfanın segment özetlerinin sıralı birleşiminin `sha1`'i.
  `pg_design_ai_hash` kullanılmaz: o, not ve sınıf değişikliğinde de değişir.

Her çıkarma `translation_uses`'ı o sahip için yeniden yazar; aynı
(`owner`,`node_id`,`field`) için önceki segment `prev_string_id`'ye düşer.

## 6. Satır içi biçim, yer tutucu ve güvenlik

- Satır içi HTML motora gitmeden önce yer tutucuya çevrilir:
  `Bize <a href="/iletisim">yazın</a>` → `Bize <x1>yazın</x1>`; değişkenler
  (`{var:1}`, `^^alan^^`) ve sözlükteki "çevrilmez" terimler → `<x2/>`.
- Dönen metinde **aynı yer tutucular aynı sayıda** yoksa çeviri reddedilir
  (`translation_job_items.error`), segment bekleyen kalır.
- Geri açılan metin `pg_design_ai_clean_inline()` + `_pg_dm_neutralise_markers()`
  (`includes/designer_access.php:437`) süzgecinden geçer; `^^…^^`, `<cregion>`,
  `<menu>`, `<system>`, `<if>` sözde etiketleri yasak (get_page_content bunları
  sonradan tarıyor).
- Uzunluk denetimi: hedef/kaynak oranı 0,2–5 dışındaysa "şüpheli" işaretlenir.

## 7. Çizim (`includes/translate/render.php`)

1. **Düğüm kancası:** `_render_tree_node()` içinde proplar okunduktan hemen
   sonra (~:2505; akıllı etkin yeniden girişinden **sonra**, `_apply_bindings`'ten
   **önce**) `pg_tr_props($props)`: çeviri bağlamı açıksa metin propları,
   `alt` ve izinli `_attrs` çeviri tablosundan değiştirilir. Ortak bileşenler
   ve widget ağaçları da buradan geçtiği için tek kanca hepsini kapsar.
2. **Sayfa gövdesi:** `/en/`'de `page_tree_code` yerine `page_translations`
   okunur; parmak izi tutmuyorsa ağaç çeviri bağlamında yeniden çizilip
   kaydedilir (önbellek). Kaynak sayfa kaydedilince satır geçersizleşir.
3. **Widget yapılandırma metinleri:** `designer.php:727-733`'te `$configs`
   kurulurken çevrilir.
4. **Menüler:** `get_menu_content()` (`content.php:26`) ve
   `get_menu_sequence()` (`designer.php:4116`) ad yazarken.
5. **Başlık / meta:** `get_page_content.php` ~5450 (`<title>`) ve ~5615
   (`$meta_tags`).
6. **Son işlem — `pg_tr_finalize($content)`**, `get_page.php:3187`'deki tek
   çıkış noktasında:
   - `<html lang>` → hedef kod (değiştirilmemiş Google çıktısı varsa
     `en-x-mtfrom-tr`, §9);
   - iç sayfa bağlantılarına önek: ilk yol parçası bir sayfa adıysa; dosya,
     yazılım dizini, dış adres ve `#` atlanır (§3.1); dosyaya giden göreli
     adresler köke çevrilir;
   - canonical, `og:url`, JSON-LD adresleri; `og:locale` dilin ayarından;
   - `hreflang` alternatifleri + `x-default` (kaynak);
   - gerekiyorsa Google rozeti (altbilgi).
7. **Akıllı etkin:** `pg_apply_smart_active()` hedefi önekten arındırılmış
   yolla karşılaştırır.
8. **Formlar:** seçeneklerin **`value`'su kaynakta kalır**, yalnız görünen
   etiket çevrilir — `form_data`, `target_options` ve seçeneğe bağlı e-posta
   yönlendirmesi bozulmasın. Doğrulama mesajları (`_cf.validation_message`)
   çevrilir.

## 8. Arayüz metinleri ve yerel biçim

- `en` ve `tr` için mevcut dil dosyaları kullanılır; başka dil için
  `lang()` kaçırdığı anahtarı (yalnız ön yüz isteğinde, istek başına toplu,
  saatte bir kez) `translation_strings`'e `kind = ui` olarak yazar; sonraki
  "Çevirileri güncelle" onları da çevirir.
- Biçim anahtarları (`1,234.56`, `j/n/Y`, `H:i` …) bir **dışlama listesinde**;
  değerleri `site_languages.locale_number` / `locale_date`'ten gelir.
- `widgets_catalog.php:1533,1537` `lang()`'a alınır.
- `widgets_cart.php:80,109` karşılaştırması: çevrilmiş ağaç etiketiyle değil,
  kaynak etiketle yapılır (çeviri kancası karşılaştırmadan sonra uygulanır ya
  da karşılaştırma kaynak prop üzerinden yapılır — yazarken seçilir).

## 9. SEO ve şartlar

- **hreflang:** her dil sayfası, kaynağı ve yayımlanan öbür dilleri gösterir;
  `x-default` = kaynak.
- **Sitemap** (`get_sitemap_info()`, `get_sitemap_info.php:20`): dil adresleri
  + `xhtml:link` alternatifleri; yalnız indekse açık dil sayfaları.
- **robots** (`pg_build_robots_disallow_rules()`, `seo.php:1438`): kaynakta
  `noindex` olan sayfanın dil kopyaları da kapalı.
- **İndeks politikası** (`site_languages.index_policy`):
  - `reviewed` (varsayılan): sayfanın tüm segmentleri `reviewed` değilse
    `noindex` + sitemap'te yok;
  - `all`: makine çevirisi de indekslenir;
  - `none`: dil yalnız ziyaretçi içindir.
  Gerekçe: Google'ın "ölçekli içerik istismarı" politikası toplu, değersiz
  otomatik çeviriyi kapsıyor; kaliteli kendi içeriği sorun değil, ama gözden
  geçirme varsayılan olarak güvenli.
- **Google Cloud şartları:** değiştirilmemiş çıktı yayınlanıyorsa çevirinin
  yanında "powered by Google Translate" rozeti (translate.google.com'a bağlı),
  makine çevirisi işareti `lang="en-x-mtfrom-tr"` ve feragat metni zorunlu.
  Gözden geçirilip düzeltilen metin "değiştirilmemiş" sayılmaz. Bu yüzden
  rozet ve işaret, sayfada `engine = google` ve `status = machine` segment
  varken eklenir.
- **Chrome Translator API**'nin belgelerinde benzer bir rozet şartı yok;
  Claude / @ai çıktısı için de yok. Yine de `status = machine` işareti tutulur.

## 10. Motorlar (`includes/translate/engines.php`)

Ortak arayüz — `pg_tr_engines()` kayıt defteri:

| Anahtar | Tür | Nerede çalışır | Anahtar/şart | Not |
|---|---|---|---|---|
| `google` | anında | sunucu, zamanlanmış iş | Site sahibinin Cloud API anahtarı | `api_http_request('POST', 'https://translation.googleapis.com/language/translate/v2?key=…')`, `format=html`, toplu `q[]`; `max_body` büyük verilmeli (varsayılan 1000 bayt, `http.php:141`). Ayda 500 bin karakter ücretsiz, sonra milyon başına ~20 $ |
| `chrome` | anında | yöneticinin tarayıcısı | Yok (Chrome 138+ / Edge 148+, masaüstü) | `Translator.create({sourceLanguage, targetLanguage})` kullanıcı etkileşimi ister (düğme), yalnız üst pencere; ilk seferde model iner; sırayla çevirir. Türkçe destekli |
| `ai` | anında, bütçeli | sunucu, zamanlanmış iş | Pinegrap AI lisansı | `WS_AI_ENDPOINT` (`ai.php:52`) üzerinden `/chat/completions`; `ws_ai_http()`'den `pg_ai_chat()` sarmalayıcı çıkarılır (bugün workspace bootstrap'ına bağlı) |
| `claude` | kuyruk | Claude rutini, dış API | Çalışma Alanı'ndaki Claude kurulumu | §11 |
| `api` | kuyruk | herhangi bir uygulama | `translations:write` kapsamı | Çevirmen firması, harici araç |
| `manual` / `import` | — | panel | — | Elle düzenleme, CSV dışa/içe aktarma |

Motor seçimi: dilin `engine`'i, başarısızlıkta `fallback_engine`. Tek iş tek
motorla çalışır; yedeğe geçiş yeni iş açar (kayıtta görünür).

**Yapay zekâ motorlarına giden paket** (ai, claude, api): segment kimliği +
yer tutuculu metin + bağlam (sayfa adı, düğüm türü/etiketi, alan, önceki ve
sonraki segment, varsa önceki çeviri) + sözlük eşleşmeleri + üslup notu.
Yanıt `{hash: metin}` biçiminde; §6 denetimi.

## 11. Claude kuyruğu ve dış API

**Bugün:** `ws_claude_fire()` (`claude.php:581`) yükü sabit
`site: … · queue: workspace`; `ws_claude_dispatch()` (`:529`) yalnız
`ws_claude_only()` satırlarını sayıyor; rutin istemi
(`ws_claude_routine_prompt()`, `:1126`) yalnız Çalışma Alanı'nı anlatıyor.

**Değişiklik:**
- `ws_claude_fire($test, $queue = 'workspace')`; çeviri işi
  `pg_tr_claude_dispatch()` ile aynı kilit (`pg_ws_claude_fire`),
  `hold_until` ve günlük sınır üzerinden ateşlenir — sınır Çalışma Alanı ile
  ortaktır.
- Rutin istemine `queue: translations` dalı eklenir; yönetici istemi yeniden
  yapıştırır (kurulum kartı uyarır).
- Bağımlılık: Claude motoru Çalışma Alanı modülü açıkken vardır (rutin
  ayarları orada).

**Dış API** — yeni modül `translations` (`api_modules()`, `modules.php:49`),
kapsam grubu `translations:read` / `translations:write` (`scopes.php`):

| Yöntem ve yol | Kapsam | Amaç |
|---|---|---|
| `GET /translations/languages` | read | Kaynak ve hedef diller, motorları |
| `GET /translations/strings?language=&status=pending\|machine\|reviewed&page_id=&cursor=` | read | Segmentler, bağlamlarıyla |
| `GET /translations/pages/{id}?language=` | read | Sayfanın sırayla segmentleri + parmak izi |
| `POST /translations` | write | Toplu yazma `[{hash, language, text}]`; öğe başına sonuç; `Idempotency-Key` |
| `GET /translations/jobs?status=open` | read | Claude uygulamasına açık işler |
| `POST /translations/jobs/{id}/claim` | write | Atomik sahiplenme (409 `already_claimed`) |
| `GET /translations/jobs/{id}/items` | read | İşin paketi (§10) |
| `POST /translations/jobs/{id}/results` | write | Sonuçlar; kısmi kabul |
| `POST /translations/jobs/{id}/fail` | write | Hata |

İş uçları `ws_api_claude_guard()` benzeri bir kapıyla yalnız seçilen
uygulamaya açılır; `strings` / `translations` uçları kapsamı olan her
uygulamaya. Kaynak değiştiyse (`hash` artık kullanılmıyor) öğe `stale` ile
reddedilir. `tools/check_api_schema.php` sunucu çiftleri eklenir.

## 12. Panel

**Ayarlar → Diller ve Çeviri** (yeni kategori `languages`:
`settings_languages.php` + `includes/settings/languages.php` +
`languages.save.php`, `registry.php`'ye kayıt):
- kaynak dil;
- hedef diller tablosu: ekle/çıkar, önek, motor, yedek motor, indeks
  politikası, sayı/tarih biçimi, `og:locale`;
- Google API anahtarı (şifreli, boş bırakılırsa korunur), rozet;
- üslup notu; sözlüğe bağlantı.

**Çeviriler ekranı** (`translations.php`):
- dil seçici; sayfa listesi: kapsama yüzdesi, bekleyen / makine / gözden
  geçirilmiş sayıları, son güncelleme;
- **"Çevirileri güncelle"**: seçili sayfalar ya da tümü → çıkarma → iş →
  motor; ilerleme (Claude için "gönderildi, 42 metin bekliyor");
- yan yana düzenleyici: kaynak | çeviri | motor | durum; "gözden geçirildi"
  işaretleme; önceki çeviriyi kullan; filtreler;
- sözlük sekmesi; CSV dışa/içe aktarma; iş geçmişi.

**Görsel tasarımcı:** sayfa ayarlarında "Bu sayfanın çevirileri" kısayolu ve
dil önizlemesi; **Dil seçici** widget'ı (dil adları ya da kodları, mevcut
sayfanın karşılığına bağlanır).

**Zamanlanmış iş:** `translation_job.php`, `pg_cron_jobs()`'a
`interval => 300`, dağıtıcıda. Sunucu motorlarının (google, ai) kuyruğunu
bütçeyle işler, Claude işlerini ateşler, bayatlayan işleri kapatır. Panel
düğmesi de aynı işi bütçeyle başlatır.

## 13. Fazlar

**Faz 1 — altyapı + görsel sayfalar (Google, Chrome, elle)**
1. Şema (§4), `get_tables()`.
2. `includes/translate/` (giriş kapılı dosyalar): `store.php`, `extract.php`,
   `render.php`, `engines.php`, `engine_google.php`.
3. Router öneki, `FRONTEND_LANGUAGE`, `lang()` önceliği, AJAX `pg_lang`.
4. Çizim kancası, `page_translations`, menü/başlık/meta, `pg_tr_finalize`
   (lang, bağlantı öneki, canonical, hreflang), akıllı etkin.
5. Ayarlar → Diller ve Çeviri; Çeviriler ekranı; Chrome motoru
   (`assets/js/translations.src.js`); CSV.
6. Sayfa adı / dil kodu çakışma denetimi.
7. Dil seçici widget'ı.

**Faz 2 — yapay zekâ motorları**
1. `pg_ai_chat()` sarmalayıcı; `ai` motoru (bütçeli, zamanlanmış iş).
2. Claude kuyruğu: `ws_claude_fire($queue)`, iş tabloları, `translations`
   API modülü, rutin istemi dalı.
3. Sözlük, üslup notu, bağlam paketi.

**Faz 3 — SEO tamamlama**
Sitemap alternatifleri, robots, indeks politikası, `og:locale`, Google rozeti
ve `mtfrom` işareti, site araması (`search_items`'a `language` sütunu,
`update_search_index.php` dil adreslerini de tarar).

**Faz 4 — katalog, formlar, arayüz metinleri**
Ürün / ürün grubu adı, açıklamalar, SEO alanları, nitelik etiketleri;
form etiketleri ve mesajları; widget yapılandırma metinleri; `kind = ui`
kaçırma kaydı ve yerel biçim; sabit Türkçe metinlerin düzeltilmesi.

**Faz 5 — geri kalanlar**
Ziyaretçi e-postaları ve siparişte/başvuruda ziyaretçi dili (bugün hiçbir
kayıtta dil yok); çevrilmiş adresler (slug); eski sayfa bölgeleri
(`pregion` / `cregion`); `custom_html`; takvim, galeri; dile özel görsel
(§3.1); kaydederken otomatik çeviri seçeneği; DeepL ve benzeri motorlar.

## 14. Riskler

- Sayfa/dosya/kısa bağlantı adı bir dil koduyla çakışırsa gölgede kalır (§3, §3.1).
- Elle yazılmış göreli dosya adresi `/en/` altında yanlış yere gider → router
  301 güvenlik ağı + çıktıda köke çevirme (§3.1).
- `PATH`'e önek konursa varlık ve AJAX adresleri kırılır (§1.6).
- `widgets_cart.php` etiket karşılaştırması (§8).
- Form seçenek değerleri çevrilirse gönderilen veri ve yönlendirme bozulur (§7.8).
- Dış ajandan gelen çeviri ham HTML olarak çıkar → süzgeç zorunlu (§6).
- Claude günlük sınırı Çalışma Alanı ile ortak; @ai Kodpen geçidine bağlı —
  çekirdek akış (Google, Chrome, elle) bu ikisi olmadan da çalışmalı.
- Google'ın şartları: rozet + işaret atlanırsa ihlal.
- Not kaydı (`designer_collab.php:390-400`) `page_tree_code`'u yeniden çizmiyor;
  `page_translations` parmak izi metin özetlerinden kurulduğu için etkilenmez.

## 15. Kararlar (Erdal, 2026-10-04) ve açık soru

Onaylanan öneriler:
1. Kaynak dil panel dilinden **ayrı** bir ayar; varsayılanı panel dili.
2. Faz 1'de adresler **kaynak slug'la** (`/en/hakkimizda`); çevrilmiş slug
   Faz 5'te. Önek yalnız sayfa adreslerine gelir; dosya, CSS ve JS kökte
   kalır, çoğaltılmaz (§3.1 — Erdal'ın sorusu üzerine netleştirildi).
3. Varsayılan indeks politikası **`reviewed`**.
5. Sayfa kaydedilince otomatik çeviri **Faz 5**'te, varsayılan kapalı.
6. Dil seçici **dil adı/kodu** gösterir, bayrak yok.

Açık:
4. Claude motoru Çalışma Alanı'na bağlı kalsın mı, yoksa rutin ayarları ortak
   bir yere mi taşınsın? (Faz 2'ye kadar karar gerekmiyor; o zamana dek
   varsayım: bağlı.)
