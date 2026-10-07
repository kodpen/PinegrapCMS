# Plan: Görsel editör tuvalinde betik birikmesi ve animasyonlar (2026.4.8)

Erdal'ın seçimi (2026-10-07): **A + B + C**, animasyonlar varsayılan olarak **duraklatılmış**. D (içe aktarmada tekilleştirme) şimdilik yok.

## Teşhis (özet)
- Tuvalde tek iframe var ve yalnızca aktif sekme çiziliyor. Sorun CSS'te değil, tasarımın JS dosyalarında.
- `applyAssetsToIframe()` her çağrıda bütün `<script>`'leri silip yeniden ekliyor. Eski kod durmuyor: zamanlayıcı, rAF, dinleyici ve observer birikiyor. Çağrıldığı yerler: undo/redo (`_restoreAssetFields`), Stiller paneli (`setUserStylesContent` → `saveFiles`), tema değişkeni (`_writeThemeVar`, iki kez), font ve dosya değişiklikleri. Yalnızca CSS değişse bile JS baştan çalışıyor. `_file` girdileri `?_t=` ile her seferinde yeniden indiriliyor.
- Ölçüm (dev, tasarım 776): 7 undo/redo, içerik değişmeden → 8 dosyanın her biri 7 kez indirildi; notfound.js +7 setInterval ve +7 MutationObserver; Bootstrap 6 bundle +119 document click dinleyicisi; 8 kez "Identifier 'AppUtils' has already been declared"; index sekmesindeyken 404'ün zamanlayıcıları çalışmaya devam etti. Büyük sayfada `renderCanvas` 160–285 ms.

## A — Gereksiz yeniden çalıştırmayı kaldır (`assets/js/style_designer.js`)
1. `applyAssetsToIframe()` (~20272):
   - İmzalar: `jsSig` = etkin jsFiles girdilerinden `[type, name, content, module]` JSON'u; `linkSig` = etkin external-css href'leri ve GF URL'leri.
   - Harici CSS ve GF `<link>`'leri yalnızca `linkSig` değişince yeniden kurulur. `?_t=` yalnızca dosya gerçekten kaydedildiğinde eklenir: designer_file/save yolundan (~35697) `applyAssetsToIframe({ bust: true })`.
   - JS: `jsSig === _sdCanvasJsSig` ise JS bölümü tamamen atlanır. Değiştiyse aynı pencereye enjekte etme, **B**'yi çağır (`_sdCanvasRealmReset()`).
   - Satır içi CSS (`pg-page-css`, `pg-user-styles`) olduğu gibi kalır; ucuz ve yan etkisiz.
2. `_writeThemeVar()` (~36831–36836): `saveFiles()` zaten zamanlanmış bir çağrı yapıyor, doğrudan `applyAssetsToIframe()` çağrısı kaldırılsın (tek çağrı).
3. `_restoreAssetFields()` (~2906): snapshot mevcut alanlarla aynıysa hiçbir şey yapmasın.

## B — JS değişince temiz tuval ortamı
1. `createCanvasIframe()` (~5690) ikiye bölünsün: kapsayıcı ve araç çubuğu (bir kez) + `_sdInitCanvasDoc()` (doc.open/write/close, ~5843–6240 arasındaki canvasDoc dinleyicileri, Bootstrap JS, tema, assets, render).
2. `_sdCanvasRealmReset()`: `#sd-canvas-iframe` öğesini yenisiyle değiştir (eski browsing context yok olur, bütün döngüler ölür), sonra `_sdInitCanvasDoc()`.
   - Korunacaklar: kaydırma, zoom, kırılım genişliği, koyu tuval, grid/outline sınıfları, seçim.
   - Sıfırlanacaklar: `canvasDoc`, `canvasIframe`, `iframeReady`, `_sdHoverWrap`, `_sdFxRaf`, satır içi düzenleme ve zengin metin araç çubuğu referansları.
3. Tetikleyiciler yalnızca: JS dosyası ekleme/silme/düzenleme/açma-kapama, jQuery/Bootstrap sentinel değişimi, animasyon düğmesi (C).
4. (İsteğe bağlı) `_reExecuteCanvasScripts()` özel HTML bloklarındaki satır içi betikleri her renderda çalıştırıyor. Yalnızca realm açılışında ya da düğüm içeriği değişince çalıştırılsın.

## C — "Animasyonlar" düğmesi (varsayılan duraklatılmış)
1. Durum: `localStorage['pg_sd_motion'] !== 'play'` → duraklatılmış (try/catch).
2. Düğme: `createCanvasIframe()` bp-bar-right, Dark/Grid yanında: `sd-vb-btn sd-vb-toggle`, id `sd-vb-motion`, ikon `bi-pause-circle` / `bi-play-circle`, metin `_sdT('Animations')`, title `_sdT('Pause animations')` / `_sdT('Play animations')` (`_sdSetButtonTitle()` ile).
3. Tuval gövdesinde `sd-motion-paused` sınıfı. `renderCanvas()` içindeki `_EDITOR_STATE_CLASSES` listesine eklensin ki silinmesin.
4. IFRAME_CSS kuralı: `body.sd-motion-paused` altında `*`, `::before`, `::after` için `animation-duration:0s; animation-delay:0s; animation-iteration-count:1; transition-duration:0s; transition-delay:0s; scroll-behavior:auto` (!important). Editör kromu (`.sd-*`, ör. `.sd-sect-flash`, `.sd-wrap` geçişleri) muaf tutulsun.
5. Betikler için: doc.write sırasında head'e, tasarım betiklerinden önce küçük bir satır içi betik konsun. `matchMedia`'yı sarar; `prefers-reduced-motion: reduce` sorgusu duraklatılmışken `matches: true` döner (notfound.js gibi betikler döngü başlatmaz). Düğme değişince B ile realm yenilensin.
6. tr.json'un sonuna eklenecekler: `Animations` → "Animasyonlar", `Pause animations` → "Animasyonları duraklat", `Play animations` → "Animasyonları oynat".
7. Önizleme penceresi etkilenmez, gerçek animasyonları gösterir.

## Doğrulama
- `node --check assets/js/style_designer.js`, `php tools/check_lang.php`, `php tools/lint.php`, `php tools/check_bindings.php`.
- Dev'de 776 / 404 sekmesinde tuval penceresine sayaç kurulur (setInterval, MutationObserver, document click, resource sayısı). Beklenenler:
  - 7 undo/redo → 0 yeni kayıt, 0 yeniden indirme.
  - Stiller ve tema değişikliği → 0.
  - JS dosyası düzenlenince tek realm yenilemesi; sayaçlar baz değere döner.
  - Konsolda AppUtils hatası yok.
  - Duraklatılmışken `getAnimations()` boş ya da bitmiş, 404 kanvası statik; Oynat ile animasyonlar geri gelir.
- `docs/degisiklikler.md` ve `pinegrap/changelog.txt` (2026.4.8 bölümü, en üst).

## Notlar
- `style_designer.js`'te başka bir oturumun commit'lenmemiş büyük farkı var. Düzenleme cerrahi olmalı; dosya düzenlemeden hemen önce yeniden okunmalı.
- Bağlı klasörde git komutu çalıştırma (silme izni olmadan .lock bırakıyor).
- Ayrı konu (D, sonra): içe aktarmada satır içi CSS/JS içeriğe göre tekilleştirilsin; "yalnız şu sayfalarda" asset seçeneği eklensin (canlı sitede de her sayfa bütün sayfaların JS'ini yüklüyor).
