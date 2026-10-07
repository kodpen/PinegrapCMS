---
name: "pinegrap-ui-deseni"
description: "Pinegrap CMS panel ekranı yazarken veya düzenlerken kullan: sayfa çerçevesi, araç çubuğu, düğme reçeteleri, ikonlar, geniş ekran düzeni, liste/tablo, ayarlar modalı ve form alanları."
---

# Pinegrap — panel ekranı deseni

Referans uygulama: `view_folders.php` (araç çubuğu), `product_builder.php`
(düzenleme ekranı düzeni).

## Çerçeve

- Sayfayı `output_header([...])` / `output_footer()` ile aç-kapat. Bootstrap 5.
  Kartları `mb-5` ile ayır.
- Bildirim: `$liveform->output_errors()` / `output_notices()`; mesaj
  `add_notice()` / `add_error()`; alan hatası `mark_error('field','mesaj')` +
  `go($url)`.
- `add_notice()` çağıran ekran `output_notices()` de çağırmalı; gösterilen uyarı
  oturumdan silinmediği için render'dan sonra `remove_form()` çağır.
- Form alanını `$liveform->output_field([...])` ile render et; elle `<input>`
  yazma.
- Yönetim ekranına `max-width` koyma — ekranlar `container-fluid` genişliğindedir;
  alan genişliğini `col-*` ızgarasından al.

## İkon

**Bootstrap Icons**, her zaman `<i class="bi bi-…"></i>` içinde. Düğmenin
`class`'ına `bi bi-x` yazma, `<span class="bi …">` kullanma. Material Icons eski
kodda kalabilir, yeni kodda kullanılmaz. Dekoratif ikonda `aria-hidden="true"`.

İstisna: panelin **ana menü satırları** (`output_menu()`) iki renkli SVG taşır —
`pg_menu_icon()` (`includes/fn/output.php`), öğede `'menu_icon' => '<ad>'`.
Yeni bir ana menü öğesi eklerken oraya bir çizim ekle: 24 birim ızgara; modül
rengi çizgi (`currentColor`) + gövde tonu (`.pg-mi-2`); ikinci renk simgenin
konusu olan ayrıntıda (`.pg-mi-a` çizgi, `.pg-mi-at` ton) ve rengi
`backend.src.css` "Main menu icons" bloğunda `--pg-mi-a` ile seçilir — ikinci
renk yoksa kullanıcı simgeyi "tek renk" görür. Hover hareketini aynı bloğun
`prefers-reduced-motion: no-preference` bölümüne yaz. Sağ tık menüsündeki
satırlar `bi` kalır.

## Araç çubuğu

Ekranın yapabildiği her şey başlığın hemen altındaki **tek araç çubuğunda**
toplanır. Sıra:

1. birincil eylem — `btn btn-sm btn-primary rounded-pill px-3`
2. ikincil araçlar — `btn btn-sm btn-ghost`
3. breadcrumb — `flex: 1 1 220px`
4. arama — `input-group input-group-sm rounded-pill`
5. görünüm değiştirici — `btn-group btn-group-sm` + `btn-ghost.active`
6. taşma menüsü `⋮` — `btn btn-sm btn-ghost` + `dropdown-menu-end`

Sınıflar `assets/css/backend.src.css` sonundaki *Admin screen toolbar* bloğunda
(`.pg-toolbar`, `.pg-toolbar-grow`, `.pg-toolbar-search`, `.btn-ghost`) —
ekrana özel yeni çubuk CSS'i yazma. Dar ekranda kontroller ilk satır,
breadcrumb ve arama tam genişlikte alt satırlar. `.pg-toolbar` içinde çoklu
`form-control` varsa forma `flex-wrap` verme, alanlara `w-auto` koy.

## Düğme reçeteleri

- Birincil iş → `btn-primary`
- İlgili ekrana git / yerinde araç → `btn-sm btn-outline-secondary`
- Geri alınamaz iş → `btn-sm btn-outline-warning`
- Dört düğmeden fazlası menüye iner. Aynı listede dolu + outline + `btn-lg`
  karıştırma.
- **Menü satırlarında renk kullanılmaz** (`link-body-emphasis`); tehlikeyi ikon
  ve onay diyaloğu anlatır. Bu kural yalnız menülere aittir — düğme, rozet ve
  uyarı kutusunda renk her zamanki gibi kullanılır.
- Ctrl+S (`backend.src.js`) imlecin içinde olduğu formun `submit_save` adlı
  düğmesine gider ve ipucunu (`(Ctrl+S | ⌘+S)`) her formun o düğmesine koyar;
  imleç bir formda değilse sayfadaki **ilk** forma gider. Yardımcı formlar
  (arama, hesap menüsündeki kutu, yalnız silen formlar) `disable_shortcut`
  taşır. Kısayolu kendisi karşılayan ekran (görsel editör) formuna
  `disable_shortcut` verir ve tuşu kendisi yakalar.

## Geniş ekran tek sütuna harcanmaz

(Ürün sahibi kararı.) Alt alta dizilmiş tam genişlikte kartlar "yayık" görünür.
Düzenleme ekranının deseni:

- form sütunu `col-12 col-lg-8 col-xxl-9 order-1`
- yanında `position-sticky` durum sütunu `col-12 col-lg-4 col-xxl-3 order-2` —
  içine **okunan** bilgi girer: durum rozetleri, numaralar, özet, önizleme
- altta kaydet çubuğu `col-12 order-3 position-sticky`
- `lg` altında sütunlar kaynak sırasına göre alt alta iner
- durum sütununu form sütunundan **sonra** yaz; yapışkanlığı sütuna değil
  içindeki kutuya ver
- kısa kartlar ya ikişerli satıra alınır ya da durum sütununa taşınır

Bu desenden ayrılıyorsan gerekçesini yorumda yaz.

## Kart ve başlık

- `card-header` = `d-flex justify-content-between align-items-center`; solda
  `text-uppercase h5 text-primary fw-bold mb-0`, sağda o karta ait tek bağlantı
  düğmesi. Kart içine kartla aynı adı taşıyan ikinci başlık koyma.
- Kartta açılan her `<div>`'i kapat; sızan bir `div` kendinden sonraki bütün
  kartları yutar (belirti: kartlar yapışık + Kaydet asılı).
- `alert-dismissible` üstüne `py-*` koyma (kapatma düğmesi mutlak konumlu).
- Sırayla okunan ekranlarda adım rayı: `backend.src.css` *Admin screen step rail*
  bloğu (`.pg-step`, `.pg-step-rail`, `.pg-step-node`).

## Liste ve tablo

- DataTables listesi:
  `<table class="chart table-hover table" style="width:100%;display:none;">`
  (kurulumu `backend.src.js` yapar), ilk sütun `<th class="noVis">Eylem</th>`.
  Ekleme/düzenleme **ayrı sayfa**.
- Sırası anlam taşıyan listeleri (ekstre, kasa defteri, fatura kalemleri)
  DataTable **yapma**; düz `table table-hover` kullan — yürüyen bakiye ancak
  tarih sırasında doğrudur.
- Tablo satırına asla `<form>` koyma (iç içe form parser'da düşer); satır
  düğmeleri `type="button"` + `data-*`, POST hedefi sayfa formunun kardeşi tek
  gizli form, listener `document`'a delegate.
- Uzun ekran dosyasına kart eklerken değişkenlere kartın kendi önekini ver
  (`$reminder_lines`) — `$lines` yeniden kullanmak başka kartı boşaltır.

## Offcanvas, modal, takvim

- Offcanvas panelleri `<form>` **içinde** tut (offcanvas DOM'da taşımaz); kendi
  formu olan şeyleri ana formun dışına çıkar.
- Offcanvas/modal içine jQuery UI takvimi koyacaksan `z-index`'i zorla
  (`#ui-datepicker-div` `backend.src.css`'te 1065 `!important`).
- `multiselectCheckbox` kutu başına olay atmaz: davranışı duruma bağla ve her
  değişimde hepsini eşitle; `indeterminate`'i de sen hesapla.

## Ayarlar modalı (`includes/settings/`)

- Ayarlara ekmek kırıntısı veya geri düğmesi koyma; ayarlar bir yer değil,
  başlıktaki dişliden açılan modaldır. Bağlantı olmayan kontrol
  `pgSettingsGo(url)` çağırır.
- Ayar bağlantısını elle yazma: `pg_settings_link($pane, $section)`
  (`includes/fn/output.php`); `href` gerçek ekran olarak kalsın (orta tık).
- Ayar diyaloğunu ve düğmesini `output_header()` içinde bas, footer'da değil.
- Bir ayar bir satırdır: solda ad+açıklama, sağda kontrol
  (`"pgname pgctrl" / "pgnote pgnote"`). Karta elle `<hr>` koyma.
- Alan etiketine `form-label` yaz (`form-check-label` onay kutusuna aittir).
- Kontrol genişliğini sınıfla söyle — `pg-f-xs` (11rem) / `pg-f-sm` (19rem) /
  `pg-f-md` (28rem) / `pg-f-lg` (34rem) / `col-12`; `style="width"` veya
  `max-width` yazma. Sınıf dizgisinde boşluğu kontrol et
  (`col-xxl-6col-lg-3` sessizce tam genişlik verir).
- Kart içi diyalogları `#pg_settings_modal_extras`'a koy ve pane değişiminde
  `dispose()` et; kart içi `data-bs-toggle="modal"` → `data-pgdialog="modal"`.
- Kart betiğini `$pg_settings_scripts` ile o kartın modülüne yaz; sayfa
  betiğinde kalan `onclick` bölmede düşer.
- Kart düğmesi **adı olan `type="submit"`** olsun; diyaloğun kendi Kaydet'i
  `type="button"` kalır.
- Bir kategori yalnız kendi sütunlarını yazsın; kontrolü `<kat>.php`'ye, yazmayı
  `<kat>.save.php`'ye koy. Kapalı özelliğin kartını gizleme — kontroller DOM'da
  kalmazsa o kategorinin kaydı görünmeyen ayarları boşaltır.
- Yeni kart eklerken `registry.php`'deki bölüm kaydını güncelle (kart id = bölüm
  id = çip hedefi = `#hash`); kartı başka kategoriye taşırken
  `pg_settings_legacy_anchors()`'a satır yaz.
- Reddedilen kaydı `mark_error` ile işaretle, fırlatma; yarısı kaydedilmiş
  kategori bırakma.
- Yeni hazırlık kodunu `includes/settings/prep.php`'ye yaz ve `$row`'u config
  satırı olarak bırak; ikinci sorguyu kendi değişkenine yaz.
- Uç noktada kapı `validate_user()` + `validate_area_access($user,'manager')`,
  CSRF `hash_equals` ile elle, cevap her zaman JSON (hata da).

## CSS tuzakları

- `:has()` içinde `:has()` yazma (seçici geçersiz olur, kural sessizce düşer);
  `:has()` taşıyan kuralı ezen kural aynı yüklemi taşımalı.
- Kaydırma zincirindeki her flex öğesine `min-height: 0` ver; `100dvh`'yi
  `100vh`'den sonra ve `@supports` içinde yaz.

## Bitirmeden önce

- Metin eklediysen `pinegrap-ceviri` + `php tools/check_lang.php`.
- `php tools/lint.php` temiz.
