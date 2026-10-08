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

- `.src.js` düzenlediysen ikizi `.min.js` de güncellendi mi?
- `node --check assets/js/style_designer.js` (sandbox'ta `php -l` bu dosyayı
  denetlemez).
- Metin ekledin mi? `pinegrap-ceviri` skill'i ve `php tools/check_lang.php`.
