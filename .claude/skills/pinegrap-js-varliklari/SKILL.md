---
name: "pinegrap-js-varliklari"
description: "Pinegrap CMS'te JavaScript düzenlerken kullan: .src.js / .min.js ikizleri, panel ile ön yüz JS'inin ayrımı, CodeMirror ve Chart.js yıkımı, yükleniyor perdesi, JSON gövdeli uçlar."
---

# Pinegrap — JS varlıkları

## `.src.js` düzenlenir, `.min.js` servis edilir — ikisi birden

`ENVIRONMENT_SUFFIX` sabiti hangisinin yükleneceğini seçer ve çoğu kurulumda
`min`'dir. Yalnız `.src.js` düzenlemek **hiçbir şey yapmaz ve hata vermez**:
`frontend.*`, `chat_backend.*`, `add_to_cart.*`, `dropzone.*`.

Ölçüt dosya adı değil, o dosyayı basan `<script src>` satırında
`ENVIRONMENT_SUFFIX` geçip geçmediğidir. Doğrudan servis edilenler:
`assets/js/backend.src.js`, `style_designer.js`, `page_designer.js`,
`assets/js/erp_invoice_editor.js`.

## Paketler: `pg_js_bundles()` + `tools/build_js.php`

Çalışma Alanı betikleri (`workspace.js` ve `workspace_*.js`) adlarında
`.src` olmadan da derlenir: `pg_js_bundles()` (`includes/fn/output.php`)
hangi `.min.js`'in hangi kaynaklardan, hangi sırayla yapıldığını söyler.
`workspace.min.js` on iki kaynağı birleştirir; diğerleri bire bir ikizdir.

- Kaynağı düzenledikten sonra `php tools/build_js.php` koş (Node + `npx`
  gerekir, terser sürümü araçta sabit). CI `--check` ile denetler; eski
  kalan `.min.js` PR'ı kırmızıya çeker.
- Ekran kaynak dosya adı yazmaz: `pg_js_bundle_tags('assets/js/x.min.js')`
  ya da `pg_js_bundle_urls()`. `src` kipinde kaynaklar tek tek gelir.
- Yeni bir `workspace_*.js` bir pakete girmeli
  (`tests/js_bundles_test.php` denetler); yeni paketi listeye ekleyip
  aracı koşmak yeter.
- Kayıt ekranlarındaki Çalışma Alanı çekmecesi betiği ilk tıkta yükler
  (`ws_record_loader()`); orada sayfa açılırken `workspace.js` yoktur.

## Yeni kod editörü: `PgCodeEditor` facade'ı (CodeMirror 6)

Yeni bir kod alanı açacaksan CodeMirror 5'e değil facade'a git:
`assets/js/pg_code_editor.js` (`window.PgCodeEditor`). Motor
`assets/lib/code_editor/cm6.bundle.js`'dir ve ilk editör açılışında
tembel yüklenir (`PgCodeEditor.open(host, opts)` / `attach(textarea, opts)`);
sayfa `PgCodeEditor.configure({ bundle: '…?v=damga' })` ile adresini söyler.
Facade'da `_sdT()`/`lang()` yoktur, metinler çağırandan gelir. Panel
ekranında bir textarea'ya editör vermek için PHP'den
`pg_code_editor_attach(array('id' => …, 'code_type' => …))` basılır ve
ekrana `'code_editor' => false` verilir (`docs/memory/ui/panel-code-fields.md`);
`get_codemirror_javascript()` yeni koda yazılmaz. Kilitli aralık,
izlenen aralık, tamamlama kaynağı ve eklenti kaydı için
`docs/memory/core/code-editor-facade-and-bundle.md`. Bundle'ı elle düzenleme:
`tools/code_editor/` kaynaklarını değiştirip `php tools/build_code_editor.php`
koş (CI `--check` ile denetler).

## Kod editörü (CodeMirror) başlıkta olmayabilir

`pg_page_shell()`/`output_header()`'a `'code_editor' => false` verilen
ekranda (Çalışma Alanı ekranları) CodeMirror başlıkta değil,
`<template id="pg-code-editor-assets">` içinde bekler. AJAX ile kod alanı
getiren yol işaretlemeyi basmadan önce
`window.pgCodeEditorReady(markup, done)` çağırmalı (Site Ayarları penceresi
böyle yapar); CodeMirror zaten varsa `done` hemen koşar.

## İki yerde birden ara

Ön yüzde çalışan JS iki yerde yaşar: panel ekranları `backend.src.js`, ziyaretçi
sayfası `frontend.<suffix>.js` yükler. İkisinin de kendi klavye kısayolu bloğu ve
araç çubuğu kancaları vardır — bir düğmeyi taşırken ikisini birden ara, yoksa
kısayol sessizce düşer.

## Yıkım kuralları

- **CodeMirror:** düzenleyici taşıyabilecek bir bölgeyi değiştiren/kapatan her
  kod önce `window.pgReleaseEditors(scope)` çağırsın (`backend.src.js`).
  `autoRefresh` gizli düzenleyiciyi 250 ms'de bir yoklar ve sekme kapanana kadar
  sürer. `toTextArea()` yeterli değildir: eklenti yıkımı değil seçeneği dinler ve
  `window`'a bağladığı `mouseup`/`keyup` işleyicilerini kaldırmaz.
- **Chart.js:** grafik taşıyabilecek bir bölgeyi `.html()` / `.remove()` /
  `.append()` ile değiştiren her kod önce o bölgedeki grafikleri kapatsın
  (`destroy_widget_charts($scope)` → `Chart.getChart(canvas).destroy()`).
  Canvas'ı DOM'dan çıkarmak Chart.js kayıt defterinden çıkarmaz — ölçülen:
  50 dakikada 7 canvas'a karşılık 58 grafik. Yeni bir widget tazeleme yolu
  eklerken `destroy_widget_charts()`'ı da çağır; kancayı jQuery
  `remove()`/`$.cleanData` üzerine yazma.

## Yükleniyor perdesi

- Tek yerdedir: `pg_preloader_markup()`, `output_header()` içinde, `<body>`'den
  hemen sonra. Ekrana özel ikinci perde yazma.
- Markup + stil + betik birlikte gelir; ortadaki blok 300 ms gecikmeli.
  `PRIVATE_LABEL` açıkken marka basılmaz.
- Yapay taban koyma; DOM hazır olduğunda kalksın. Geç kurulan ekran
  `window.pgPreloaderHold = true` yazar ve `window.pgPreloaderDone()` çağırır.
- Dört çıkışı olmalı (DOM hazır / ilk etkileşim / zaman aşımı / CSS animasyonu);
  ilk `hide()` CSS animasyonunu silsin — `forwards` dolgusu kalırsa perde bir
  daha açılamaz.
- `toolbar.php` ve `output_header_secure()` ekranlarında basılmaz.

## AJAX ile gelen işaretleme

- `.html()` ile bas (`innerHTML` satır içi `<script>`'i çalıştırmaz).
- Yeni başlatıcıyı **kapsam alan** biçimde yaz ve `pgInitInjected(scope)`'a
  bağla; dinleyicileri `$(document).on(...)` ile devret. Atlanırsa sonradan gelen
  kontrol sessizce kurulmamış kalır ve konsolda hiçbir şey çıkmaz.

## JSON gövdeli uçlar

- `api.php` gövdeyi **JSON** okur; FormData göndermek "Invalid action" döner.
  Multipart gereken yer ayrı uç noktadır (`designer_import.php`).
- JSON gövdeli uçta `pg_post_body_discarded()` kullanma (PHP `$_POST`'u JSON'dan
  doldurmaz, küçük PNG bile 413 döner) — `php://input`'a bak.
- Çok dosyalı yüklemeyi parçalara bölüp sırayla gönder
  (`uploadBatches()`/`sendBatch()`); `upload_max_filesize` üstündeki tek dosyayı
  hiç gönderme. Tarayıcıda ön-küçültmeyi yalnız sığmayan dosyaya uygula ve
  eşikleri sunucununkiyle (`pg_pb_upload_limits()`) aynı tut.
- `ini_get()` dizgi döner; boyut karşılaştırmasında daima `pg_ini_bytes()`.

## Görünürlük ve düzen tuzakları

- Görünürlüğü `d-none` ile aç-kapat, `hidden` ile değil: Bootstrap'in
  `d-flex`/`d-block` sınıfları `!important` taşır ve `[hidden]`'ı yener.
- Flex kutusuna `overflow` yazmadan önce o eksende taban (`min-height`/
  `min-width`) olduğundan emin ol; `visible` dışı her değer `min-height: auto`'yu
  sıfırlar ve panel dar ekranda 0 piksele iner (geniş ekranda belirti yok).
- Kaydırma kutusu içindeki Popper menüsünü `strategy: "fixed"` ile kur.
- `.sortable()` kurarken `backend.src.js`'in dokunduğu **her seçeneği** açıkça
  yaz (`items`, `handle`, `axis`, `delay`, `containment`, `helper`, `scroll`,
  `dropOnEmpty`, `revert`, `tolerance`).

## Bitirmeden önce

- `.src.js` düzenlediysen ikizi `.min.js` de güncellendi mi? Paketli
  betikte (`pg_js_bundles()`) `php tools/build_js.php --check` temiz mi?
- `node --check assets/js/style_designer.js` (sandbox'ta `php -l` bu dosyayı
  denetlemez).
- Metin ekledin mi? `pinegrap-ceviri` skill'i ve `php tools/check_lang.php`.
