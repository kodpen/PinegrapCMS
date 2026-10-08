---
name: "pinegrap-tasarimci-widget"
description: "Pinegrap CMS görsel tasarımcısında çalışırken kullan: palette component, sistem widget, data binding tokenları, tuval/DOM tuzakları, ortak bileşenler, HTML içe alma ve eşzamanlı düzenleme."
---

# Pinegrap — görsel tasarımcı ve sistem widget

**Palette component yazacaksan** önce `docs/component-development-guide.md`
baştan sona okunur. Özeti aşağıda; ayrıntı ve örnek orada. O dosya depo dışıdır
(geliştirme makinasında durur) — bulamıyorsan iste, özetle yetinme.

Test URL'leri: `/c` shopping_cart, `/d` express_order, `/e` order_view,
`/b/<slug>` catalog_item_view. `/cart`, `/express-order`, `/checkout-*`
legacy'dir ve değişiklikler oraya yansımaz. Dolu sepet: `/c?r=<reference_code>`.
Çıkış yapmış hâlde doğrula.

## Data binding — fonksiyonu class değil bağlama taşır

- Sistem widget'ında tüm veri akışını ve fonksiyonu **data binding** üzerinden
  kur; magic CSS class kullanma. Ölçüt: element silinip yeniden eklenip aynı
  bağlama yapıldığında sistem çalışmaya devam etmeli.
- Binding türleri: `_bindings.text`, `src`, `href`, `action`, `eo_field`,
  `value`, `section`, `eo_visible_if`.
- `href` bağlıysa URL alanını gizle (`_sdHrefIsBound()` +
  `_sdBoundHrefNotice()`) — sunucu href'i render'da yeniden yazar, kutuya
  yazılan URL işlevsizdir ("görünür ama işlevsiz" yasağı).
- Binding select'i değişince `renderProperties()`'i **yalnız `href` için**
  çağır; custom token input'u her tuş vuruşunda `_setBinding()` çağırır ve panel
  yeniden çizilirse odak kaçar.
- Bağlamayı yapısal etikete (`table/thead/tbody/tr/td/th/caption/col`) yapma;
  içerik elemanına (`span`, `h1..h6`, `p`, `a`, `img`) yap.
- Fonksiyonel class ve `data-*` hook'larını silme: `pg-eo-form`,
  `pg-eo-cc-fields`, `pg-eo-total-formatted`, `pg-qty-stepper`,
  `data-pg-qty-action`, `data-pg-remove-href`, `data-pg-eo-form`,
  `data-pg-cc-required` ve kardeşleri. Dekoratif class'lara davranış bağlama.

## Token adlandırma

- **Çıplak ad** = gönderilen formun sistem alanı (`^^reference_code^^`).
  **`__` önekli ad** = widget'ın hesapladığı durum (`^^__cart_subtotal^^`).
- Sistem alanı için ikinci isim uzayı açma: tek kayıt
  `get_standard_fields_for_view()` (`includes/fn/forms.php`), widget tarafı
  `pg_sw_standard_fields()` ile aynı kayda çözer.
- Aynı değere her widget'ta aynı adı ver; boş durumun iki hâli iki addır
  (`empty_message` / `not_found_message`) ve ayar anahtarı ile token aynı adı
  taşır.
- Kaçışı tek yerde yap: `pg_sw_escape_token_values()` haritanın tamamını
  kaçırır; ham HTML olan anahtarları `pg_sw_raw_token_keys()` içinde adıyla say.
  Değeri `pg_sw_apply_tokens()` içinde **bir kez** escape et.
- Değiştirmeyi token başına **tek geçiş** yap (`preg_replace(..., 1)`).
- Kırpma ve bağlantıya çevirmeyi escape'ten **önce** yap.
- Tarih biçimini tokenın içine yazma: `props._bindFormats[<prop>]` alanında
  durur, `_apply_bindings()` (`includes/fn/designer.php`) ekler.
- Döngü satırı kimliklerini `pg_sw_uniquify_row_ids()` ile tekilleştir (yedi
  nitelik birlikte taşınır: `id`, `for`, `data-bs-target`, `aria-controls`,
  `aria-labelledby`, `href="#…"`, `data-bs-parent`).
- HTML üreten yeni token eklersen canvas önizlemesini `_SD_HTML_PREVIEW_TOKENS`'a
  da yaz.
- **Bitirmeden `php tools/check_bindings.php`** çalıştır: sunulan her `__token`
  bir renderer tarafından üretiliyor mu, üretilen her token açılır listede var mı.
  İkincisi atlanırsa token elle "Özel"den yazılmak zorunda kalır — uydurma token
  yazımları depoya bu yoldan girdi. Muaf iki şekil: `*_inner_html`,
  `__link_label`.

## Palette component (özet)

- Custom JS yok: `<script>`, `onclick`, `onload` emit etme; davranış yalnız
  Bootstrap `data-bs-*` ile.
- Yeni `.css` dosyası, `<link>` veya `<style>` bloğu yok. Yalnız Bootstrap 5.3.8
  + Bootstrap Icons 1.11.3.
- `render(doc, p)` ile `toHTML(p, pad)` **birebir aynı** markup üretsin — en sık
  bug kaynağı budur.
- Varsayılan olarak `semantic` ağacı üret (explosion); monolitik
  `COMPONENTS[...]` yalnız atomik BS bileşenleri ve özel çift-tık alanları için.
- Sabit inline style yazma; sabit değer için Bootstrap utility bul. Inline style
  yalnız değer bir user prop'undan geliyorsa ve utility karşılamıyorsa, `esc()`
  ile.
- Class için `cssClass` prop'unu kullan; `_attrs` = `data-*`, `aria-*`, `role`,
  kullanıcının `style`'ı, özel HTML. Altçizgi prefixli proplar designer
  metadata'sıdır ve `style_code`'a sızmamalı.
- Her yapısal `semantic` node'a anlamlı `customName` ver; aynı rol her blokta
  aynı adı taşısın, sarmalayıcı ile çocuğuna aynı adı verme, boş `customName`
  yazma (alanı hiç koyma).
- Grid hiyerarşisi: `container/container-fluid > row > col`. Utility sırası:
  display → flex/grid → spacing → sizing → typography → color → border → shadow.
  Breakpoint'ler mobile-first.
- `canDrop` semantiğine uy: void tag çocuk almaz, `<a>` içinde `<a>` yok,
  `<p>`/`<button>`/`<h1-h6>` inline-only, `<ul>/<ol>` sadece `<li>`.
- Erişilebilirlik minimumu: ikon-only düğmede `aria-label`, toggle'da
  `aria-expanded`+`aria-controls`, `<img>`de `alt`, input'ta `<label for>`.
- Satır oluğunu `gutter: '4'` + `cssClass: 'g-lg-5'` yaz; düz `gutter: '5'`
  `.container` içinde telefonda yatay kayma üretir.
- Blok stilini ya Bootstrap sınıfıyla ver, ya `_SD_BLOCK_CSS` +
  `_SD_BLOCK_CSS_NEEDS`'e yaz (sınıf `pg-` önekli). `!important` kullanma.
- Yeni bloğu `_sdBlockPaletteItems()`'a yaz ve `componentType` önekini
  `_sdBlockPaletteGroups()` tablosuna ekle; öneksiz blok "Diğer"e düşer.
- Örnek içerik kanondan seçilir (ACME / Vertex / Jane Cooper / example.com /
  picsum.photos / placehold.co); Lorem ipsum yazma, sitenin kendi markasını
  müşteri olarak kullanma. Örnek metinler `_sdT()`'den geçer
  (bkz. `pinegrap-ceviri`).
- Responsive ölçümü tuvalde yapma: bloğu tek başına bırak → `#sd-html-tree`
  HTML'ini al → temiz Bootstrap sayfasında ölç → Ctrl+Z.

## Tuval / DOM tuzakları

- `data-sd-id` için `_sdFindNodeForEl(id, el)`, ebeveyn için `_sdAnyParent(node)`
  kullan — `findNodeById(id, tree)` widget içindeki düğümde `null` döner.
- Tuvalde `<a href>` için koşulsuz `preventDefault()` yap (`<base>` yüzünden
  `#fragment` bile gezinir); `auxclick`'i de kapat.
- Dropdown'ı editör açar (`.sd-wrap` kardeşliği bozduğu için Bootstrap istisna
  fırlatır). Yeni Bootstrap JS bileşeni eklerken kardeş/çocuk ilişkisine dayanıp
  dayanmadığını kontrol et.
- Bootstrap doğrudan-çocuk kuralı varsa canvas'ta iki kural birden yaz:
  `X > .sd-wrap { display:contents }` ve `X > .sd-wrap > * { …kural… }` —
  ikincisine `width`/`max-width`/`flex` kopyalama.
- `<td>`/`<th>`'ye asla `display:flex` verme (`colspan` iptal olur).
- Seçimi `renderSelectionOnly()` ile yap; form kontrollerinde `renderCanvas()`
  sentetik `change`'i düşürür.
- Panel/modal iskeletine satır içi ölçü ve `flex-column`/`p-4`/`mb-3` yazma;
  ölçü `style_designer.css`'te. Dar ekranda kontrolü `display:none` ile yok
  etme — etiketi gizle, ikonu ve `title`'ı bırak.
- Taşmayı `scrollWidth > clientWidth` ile arama; `getBoundingClientRect().right
  > innerWidth` kullan.
- Yerinde düzenlemede metni `_sdInlineEditValue()` ile oku: çocuklu öğede
  çocuk sarmalayıcısının ad etiketi (`.sd-tb-name`, "Görsel") `textContent`'e
  karışır ve metin olarak kaydedilir.
- Tuvalin iki sarmalayıcı modeli var (`_sdFxFlatOn()`): paketli Bootstrap
  tasarımında kutulu (Bootstrap 5 taklit kuralları `_SD_BX +` ile başlar),
  öteki çatılarda saydam (`.sd-fx-flat`, `display:contents`; çatının yapı
  seçicileri `_sdBridgeSheets()` ile sarmalayıcı üstünden köprülenir).
  `IFRAME_CSS` dizidir, `_sdIframeCss()` modele göre birleştirir.
- Sarmalayıcının kutusunu `_sdBoxRect(w)` ile ölç: saydam sarmalayıcıda
  `getBoundingClientRect()` sıfır döner. Saydam sarmalayıcının araç çubuğu,
  not noktası ve rozeti `_sdPlaceWrapChrome()` ile konur.
- Ölçüye bağlı düzeltmeler (`sd-fx-block`, `sd-fx-inline`, loop alanının
  satır düzeni, görsel yer tutucusunun boyu) `_sdCanvasFidelityPass()`'te,
  render'dan sonraki karede koşar: önce hepsi ölçülür, sonra yazılır.
  Seçime bağlı parça (boyut rozeti) `_sdPlaceSelectedChrome()`'dan yeniden
  konur.
- Kaydırmada tam geçiş (`adjustCanvasToolbars()`) koşmaz: `_sdOnCanvasScroll()`
  yalnız seçili ve fare altındaki sarmalayıcıyı günceller. Tuvalin stil
  hesabı pahalıdır (binlerce gizli araç çubuğu, köprü kuralları): geçişler
  farkla yazar, değişmeyen sınıfı ya da stili kaldırıp yeniden koyma. Köprü
  seçicisini tek `:is()` ile sarma; düz liste kalsın ki tarayıcı kuralı
  sağdaki bileşiğin sınıfına göre kovalasın (özgüllük `:where()` ile korunur).
- Editör stilinde gövde varsayılanı `@layer sd-canvas-base` içinde kalır:
  katmansız `body` kuralı Bootstrap 6'nın katmanlı kurallarını yener. Satırdaki
  sütuna (`.row > .sd-wrap[data-sd-type="col"]`) padding verme: Bootstrap'ın
  oluğunu ezer.
- Yalnız seçimi değiştiren yol `render()` çağırmaz, `_sdSelectRender()`
  çağırır: tuval DOM'u kalır (oynayan animasyon, video, betik durumu baştan
  başlamaz). `render()` ağacı değiştiren yollar içindir.
- Tasarımın JS'i tuval belgesi başına **bir kez** koşar
  (`applyAssetsToIframe()`, imza `_sdCanvasJsSig()`); JS değişince
  `_sdCanvasRealmReset()` iframe'i yeniler. Tuvale betik ekleyen yeni bir
  yol bu kapıdan geçsin; CSS `<link>`/`<style>` farkla yazılır
  (`_sdSyncCanvasLinks()`), kaydedilen yönetilen dosya `_sdBustAsset(url)`
  ile yeniden istenir. Editörün tuval işi tasarımcı penceresinin
  `requestAnimationFrame`'inde zamanlanır: tuval penceresininki sürüklemede
  bekletilir (`_sdMotionShimFn()`). Tuval gövdesine yeni bir editör durum
  sınıfı eklersen `renderCanvas()` içindeki `_EDITOR_STATE_CLASSES`'a yaz.

## Sistem widget kimliği

- Yeni tür `SW_TYPES` + `_swTypeDefaults`'a yazılır; tür yalnız `_swIsType` ile
  kabul edilir.
- Ad `<sayfa>-<tür>-widget` (`_swAutoName`/`_swSlug`); tekilliği sunucu
  `_sc_unique_name` ile korur, istemcide sayaç tutma.
- `regionType === ''` boş görünsün; ilk türü seçilmiş gibi gösterme.
- Silme/yeniden adlandırma/kullanım taramasını `_pgAllPageTrees()` üzerinden yap;
  sunucuda kayıtlı sayfalardaki kullanım tek yerden: `pg_shared_component_usage()`
  (`usage_all`, toplu silme `delete_many`).
- Sayfa seçicisini `_swPageOptions()` ile çiz, `_swPageVal(el)` ile oku;
  `parseInt(value)` kullanma (`tab:<key>`'i 0 yapar).
- PHP ve designer JS varsayılanlarını **lockstep** tut; bir düzeltmeyi üç yerde
  birden uygula: PHP varsayılan ağaç, JS starter tree, sunucu fallback'i.
- Kapalı özellik frontend'e hiç çıkmasın: `_pg_catalog_binding_disabled()` (PHP)
  ile `_sdCatalogBindingDisabled()` (JS) aynanın iki yüzüdür.
- Widget her durumda kendi ağacını çizsin (girişli ziyaretçi, onay ekranı,
  kapalı özellik hepsi budamadır); ağaç yerine düz `<div class="alert">`
  döndürme.
- Her renderer `_pg_inject_messages_node($tree, '<liveform adı>')` çağırsın;
  damgasız düğüm sayfadaki her formu basar. Hesap bandındaki widget'lar
  (`cart_link`, `login_region`, `language_switcher`) kimsenin göndermediği
  bir adla damgalanır, mesaj göstermez; JS'te `_SW_NO_MESSAGES`'tadırlar:
  başlangıç ağacına "Mesajlar (PHP)" eklenmez, eski ağaçtaki yüklenirken
  düşer. Böyle yeni bir tür eklersen iki tarafa birden yaz.
- Grup kapsamı: normal gezinmede doğrudan üyeler, arama/filtre varsa tüm alt
  ağaç (`_pg_catalog_group_subtree_ids`). Doğrudan `products_groups_xref`
  üyeliğine bakma.
- Sistem widget katmanında geriye dönük uyumluluk için ölü kod tutma; legacy
  custom style (`get_catalog.php`, `get_shopping_cart.php`) production'dır ve
  dokunulmaz. Doğru davranışın ölçütü legacy sayfanın çıktısıdır — **tahmin
  etme, legacy sayfayı aç ve karşılaştır.**
- Heredoc (`<<<HTML`) içindeki her literal `$`'ı `\$` ile kaçır.
- PHP ağaç renderer'ı (`_render_tree_node`) editörün dışa aktarıcısının
  aynasıdır: çocuk alan içerik/bileşen (bağlantı, `btn`, `badge`) çocuklarını
  `_render_content_html` / `_render_component_html($kids)` ile yazar. Yeni bir
  `toHTML(p, pad, children)` ekleyince PHP tarafını da güncelle; yoksa ortak
  bileşende ve widget'ta canlı sayfada kaybolur (önizleme istemcide çizdiği
  için göstermez).
- `form_list_view` cfg `filters`: klasik liste görünümünün filtreleri,
  `pg_sw_form_list_filter_sql()`. Alan standart alan anahtarı ya da form
  alanının adı; dinamik değer alanın tipine göre (tarih → bugün / gün önce,
  username → izleyici, e-posta → izleyicinin e-postası). Editör etiketleri
  `_sdT('…')` literal'i olmalı: çeviri haritası literal çağrılardan taranır.

## Ortak bileşenler (`shared_ref`)

- Referans **ID**'ye bağlanır: `props.sharedId` gerçek bağ, `props.sharedName`
  yalnız görüntü snapshot'ı; rename'de tree JSON'daki adı güncelleme.
- `shared_ref` node'unun `children`'ı her zaman boş; inline expand edilmiş
  çocukları DB'ye yazma.
- İsim çakışmasında hata döndürme; `find_unique_shared_name()` ile `[1]`, `[2]`
  suffix ver.
- İç içe ortak bileşen ve widget serbesttir (2026.4.8); yasak olan döngüdür.
  Denetim üç yerde: `canDrop()` (`_sdSharedReaches()`), kayıtta
  `_validateTree()` (`_sdSharedCyclePath()`), sunucuda
  `pg_shared_component_cycle()` (`_validate_and_clean_tree_json()` ve
  `shared_component/update`). Bileşen ağaçlarını yürüyen yeni kod döngüye
  karşı `_sdSharedOpen` / ata kümesi taşısın; önbellekteki bir ağacı
  değiştiren yol içeren bileşeni `_sharedDirty` işaretlesin
  (`_sdSharedOwnerOf()`, `_findParentAnywhere()`). Widget çıktısında kalan
  işaretleri `pg_expand_nested_components()` açar. Dangling referans
  render'da sessizce atlanır.
- Düzenleme inline yapılır; kayıtta önce `shared_components` güncellenir, sonra
  stil kaydedilir.
- `_render_tree_node()` çağrılarında `$depth` taşınır ve 8'i aşınca durulur.
- Renk şeması: kilit `#f07820` turuncu, ortak bileşen `#22c55e` yeşil.
- `prefetchShared()` her sekme açılışında sayfanın bütün widget'larını
  okur ama `_sharedDirty` işaretli girişin üstüne yazmaz: kayda kadar
  değişikliğin tek kopyası önbellektir. Önbelleğe ağaç koyan yeni bir yol
  `_sharedDirty`'yi (ve karşılaştırma için `_sharedSnapshots`'ı) yazsın.
- Şablon widget'ının satırı hâlâ yer tutucuysa (`_swIsTemplatePlaceholder()`,
  ayarda `template_origin`) tam erişimli editör yüklerken türünün başlangıç
  ağacını koyar ve kirli işaretler; kayıt yazar. Editör açmadan onarmak:
  ağacı `StyleDesigner.starterTree(tür)` ile kur (sayfada `sdDesign.i18n`
  tanımlıyken), `shared_component/update` ile yaz.

## Eşzamanlı düzenleme (`includes/designer_collab.php`)

- Kimliği sekme belirler (`sessionStorage` `pg_sd_tab`).
- Kilidi tek `INSERT ... ON DUPLICATE KEY UPDATE` ile al; `session_key`'i SET
  listesinde **en sona** yaz — önce yazılırsa kilit her isteyene geçer.
- Kilidi yetki olarak kayıt yolunda doğrula (`pg_collab_may_edit_page()`).
- Kilitli sayfayı kaydı düşürmeden atla ve uyar (editör tüm sekmeleri birden
  kaydeder).
- Emekliliği bayatlamaya bırak (`PG_COLLAB_STALE`, 65 sn).
- Görüntüleme modunda düzenleme panellerini `display:none` yap, sayfa ayarlarını
  `inert` ile kilitle, sağ tık menüsünü tek maddeye indir.
- Not sinyalini nabza bindir; `notes_fetch` ağaç döndürmesin ve not birleştirmesi
  `saveState()` çağırmasın. Yazarı `pg_stamp_note_authors()` ile
  `pg_designer_save_page()` içinde damgala.

## HTML içe alma ve yapıştırma

- Üç yolun tek dönüştürücüsü vardır (`includes/designer_import.php`); cevabı
  sekmelere çeviren tek yer `_pgImportApply()`.
- Satır içi `<svg>` dosyaya yazılmaz, her yolda `svg` içerik düğümü olur
  (`html` = çizimin kodu, `class` → `cssClass`); temizliğin iki yüzü
  `pg_designer_svg_markup()` (PHP) ve `_sdSvgElement()` (JS). Ayırma
  `pg_di_lift_svgs()` ile, iç içe `<svg>` sayılarak. Fragman modunda
  `<style>`/`<script>` sayılıp atılır.
- Bootstrap ikonu: metinsiz `bi-*` sınıflı `<i>`/`<span>` ikon düğümü olur
  (`pg_di_icon_props()`), ikonlu bağlantı düğümlerle kurulur
  (`pg_di_has_icon_child()`); başlık/paragraf içi ikon zengin metinde kalır.
  Editörde sınıfıyla ikon çizen her öğe ikon seçicisini alır
  (`_sdBiIconClass()`, `_sdBiIconSection()`, `_sdSetBiIcon()`).
- Pano ayrımını tuşa değil içeriğe göre yap (`/*pinegrap-nodes*/` öneki).
- Çok sayfalı içe aktarmada tekrarlanan bloklar `_pgImportFindShared()` ile
  bulunup sorulur; karşılaştırma `_id`'siz ağaçla, `active`/`aria-current`
  yok sayılarak. Açılan satır `category = import:<proje>` taşır ve şablon
  satırları gibi yayınlanmamış tasarımdan çıkılınca silinir.
- Metin kovasına yapıştırılanı `esc()` ile düz metne indir.
- Çatı dosyası süzgeci (`pg_di_is_framework_url()`) yalnız Bootstrap 5
  tasarımında çalışır: istemci `framework_bootstrap` ('1'/'0') gönderir, '0'
  ise sunucu `keep_framework` ile projenin bootstrap/jquery/popper'ını alır.
  Yeni bir içe aktarma yolu eklersen bu bayrağı da taşı.
  Yeni tasarım her yoldan (boş, içe aktar, yapıştır) önce çatıyı sorar:
  `sdNewDesignModal` + `data-sd-start`; `add_system_style.php`
  `framework`'süz `start=import|paste`'i soruya geri yollar.
- JS varlık kaydında `module: true` = `<script type="module">`; dört yer
  okur: `get_page_content.php`, `applyAssetsToIframe()`, HTML ağacı,
  önizleme. Kayıt kopyalayan her yer (`parseFiles`, `sdImportAssets`,
  Çoğalt) alanı taşımalı.
- Tuvalde her öğe `.sd-wrap` içinde: üst öğeye bağlı ölçüler (`h-100`,
  `> *` seçicileri) kırılır. Çare `display:contents` ya da sarmalayıcıya
  ölçüyü geçiren kural (`.sd-wrap:has(> .h-100)`), ön yüze dokunmadan.

## Gövde etiketi ve sayfa kaydı

- `<body>` parçaları **birleştirilir, atılmaz**: `class` = ek gövde sınıfları →
  kök `cssClass` → `_attrs class`; `style` = `fontFamily` → `inlineStyle` →
  `_attrs style`; `id` tek yetkili.
- Üç yeri lockstep güncelle: `generate_style_code_from_tree()` (PHP),
  `_sdBodyAttrParts()` → `generateHTML()`, aynı yardımcı → `renderCanvas()`.
- `page_tree_code` anlık görüntüdür; gövde düzeltmesi var olan sayfalarda ancak
  yeniden kayıtla yayına çıkar.
- Sekme durumu üçtür: yeni (`page_id === 0`), seçilmiş-kaydedilmemiş
  (`p.pickedPending`), bu tasarımın sayfası. `page_id > 0` tek başına sahiplik
  değildir.
- Metin basarken düğüm tipini ayır: `content` (paragraph/heading) → innerHTML,
  yazan taraf `esc()` yapar; `semantic` → ham yazılır, `generateHTML()` `esc()`
  eder.
- Editörün verisini betikten **önce** bas (`includes/designer_screen.php`);
  ayrıştırma anında gereken her yeni global o bloğa girer.
- Kirli kontrolü (`_pgBaselineOf()`) ağacı `_pgBaselineTreeKey()` ile yazar:
  `_expanded` (Katmanlar paneli durumu) ve boş `_attrs` sayılmaz. Panel
  çizimi düğüme yazmaz; düğümde yeni bir arayüz durumu tutulacaksa anahtarı
  oraya da eklenir, yoksa öğe seçmek sayfayı "kaydedilmemiş" gösterir.

## Çatı (framework) ve şablon

- Tasarımın çatısı `style.style_framework` (`bootstrap5` | `custom`; Bootstrap 6
  bir girdi daha olacak). Tek kayıt `pg_design_frameworks()`; CSS/JS adresini,
  Bootstrap paletinin geçerli olup olmadığını o söyler. Adresi başka yere
  yazma: ön yüz (`get_page_content.php`), tuval, önizleme ve sentinel satırları
  oradan okur (JS: `_sdFrameworkInfo()` / `_sdUsesBootstrap()`).
- Çatı yalnız tasarım **oluşurken** yazılır; kopya ve sayfa ayırma kaynağınkini
  taşır. Yeni bir "stil satırı oluşturan" yol eklersen çatıyı da taşı — yoksa
  özel tasarımın kopyası Bootstrap 5 olur.
- Özel tasarımda `bootstrap-css` / `bootstrap-js` sentinel'i olmaz ve ön yüz
  Bootstrap yedeğini basmaz. Bootstrap sınıflarına dayanan yeni palet grubuna
  `bootstrap: true` yaz; işaretsiz grup özel tasarımda da görünür.
- Tuvalde editörün kendi sarmalayıcısını gizlemek için Bootstrap sınıfına
  (`d-none`) güvenme ya da `IFRAME_CSS`'te karşılığını yaz — özel tasarımın
  tuvalinde Bootstrap yok.
- Şablon: `includes/design_templates/<id>.php`, metinler `lang()`. Sayfalar
  değişince **sürümü artır** (tasarım başladığı sürümü kaydeder). Yer
  tutucular `{{page:<key>}}` (adres), `{{tab:<key>}}` (widget ayarında /
  form ayarında sayfa; Yayınla'da id olur), `{{folder:<key>}}` (klasör id'si,
  ör. `_cf.upload_folder_id`), `{{site_name}}`, `{{year}}`; widget'a
  `props.templateWidget` ile bağlan. `'folders'` açılışta oluşur
  (`_pg_tpl_folders()`: boş aynı adlı kökü kullanır). `'requires' =>
  'ecommerce'` sayfa/widget'ı (şablon düzeyinde şablonun kendisini) mağazasız sitede atlar; `'tree' => 'starter'`
  widget editörün başlangıç ağacıyla açılır. Sayfalar Yayınla'ya kadar
  oluşmaz; widget satırı açılışta oluşur. Birden çok sayfada tekrarlanan
  parça (üst menü, alt bilgi, çağrı bandı) şablonun `'shared'` girdisidir,
  sayfada `props.templateShared` ile bağlanır; satırı açılışta
  `_pg_tpl_shared_row()` yazar (`category = 'template:<şablon>/<anahtar>'`
  işareti, çünkü düz ortak bileşenin `system_region_config`'i yok). Menüde
  aktif bağlantıyı `smartActive` bulur; sayfaya göre değişen `active`
  yazma. Shared ve widget ağaçlarındaki `templateWidget`/`templateShared`
  de bağlanır: prepare iç içe shared'ı da açar, bütün satırlar yazıldıktan
  sonra ikinci geçişte widget/shared ağaçlarını `_pg_tpl_link_widgets()`'ten
  geçirip değişen satırı yeniden yazar. Yayınlamadan editörden çıkılınca
  `pagehide` beacon'ı `designer/template_discard` ile şablon satırlarını
  siler (hiçbir sayfa ya da başka bileşen kullanmıyorsa —
  `_pg_tpl_row_in_use()` bileşen ağaçlarına da bakar, discard bir tur
  hiçbir şey silmeyene kadar döner); kalan satır 12 saat sonra aynı şablon
  açılınca yeniden kullanılır (`_pg_tpl_reuse_cutoff()`). Form ayarında
  `'sample_records' => 'blog'`: form ilk yayında oluşunca sitenin örnek
  blog yazıları kopyalanır (`pg_cf_seed_sample_records()`). E-posta
  sayfaları da şablon sayfasıdır (özel klasör, sitemap/arama kapalı): form
  ayarı `notify_page_id` / `confirm_page_id`, yorum `comments` dizisinde
  `email_page` / `email_subject` / `notify_email`, ödeme cfg
  `order_receipt_email_page_id`; `{{site_email}}` = `EMAIL_ADDRESS`. E-posta
  sayfasındaki widget kaydı/siparişi `pg_sw_render_context()`'ten alır (URL
  yok); mesaj düğümü e-postada boş kalır. Ayar anahtarlarını
  renderer'ın okuduğu `$cfg[...]`'dan doğrula (ör. sepet
  `next_page_id_with_shipping` / `_without_shipping`, `next_page_id` değil).
  Sayfada kalan katalog widget'ı (`add_to_cart_stay_on_page`) da
  `add_to_cart_next_page_id` alır: bildirimin "Sepete git" bağlantısı
  (`pg_sw_cart_url()`); verilmezse ziyaretçinin son gördüğü sepet sayfası.
  Şablon ağacında yapısal düğümün tanımlayıcı adı `_label`'a yazılır,
  `customName`'e değil (customName Genel Bakış'ta etiketi gizler);
  `pg_design_thumb_svg()` yeni çizimi `_pg_design_thumb_<kind>()` ile bulur.
- Şablonun kendi verisi: `'catalog'` (gruplar + ürünler, açılışta bir kez;
  grup üst grubu altında adıyla, ürün SKU'suyla bulunur — `_pg_tpl_catalog()`),
  `'contact_groups'` (adıyla bulunur). Yer tutucular
  `{{product_group:<anahtar>}}`, `{{product_group_path:<anahtar>}}`,
  `{{product_group_image:<anahtar>}}`, `{{contact_group:<anahtar>}}`.
  Önerilen tema `'look'`/`'palette'`, kurulum kartı maddeleri `'highlights'`.
  Şablonlar birbirinin satırını kullanmaz (`template_origin` / `category`
  şablon kimliğiyle). Koyu bölüm `data-bs-theme="dark"` ile birlikte
  `bg-body text-body` ister: tema değişkeni değişir ama `color` gövdeden
  miras kalır. Bootstrap'te `ratio-3x4` yok (1x1, 4x3, 16x9, 21x9).
- Tasarımdan şablon (2026.4.8): `includes/fn/design_templates_custom.php`,
  tablo `design_template`, id `custom-<n>`. `pg_design_templates()` dosya
  şablonlarını (`'builtin' => true`) ve tablodakileri (`builtin` false;
  yalnız bunlar silinir) aynı şekilde birleştirir; dizi şekli şablon
  dosyasınınkiyle birebir aynı kalır (prepare/install ayırt etmez). Yer
  tutucuları `pg_dtc_*` üretir: `_pg_tpl_fill()`'in tersi, yalnız tam
  eşleşme. Tasarımın CSS/JS/font/head/body sınıfı `'assets'` girdisindedir
  (`add_system_style.php` gizli alanlara, install `save_system_style()`'a).
  Şablon dizisine yeni anahtar eklersen dışa aktarmaya da ekle. Aynı
  istekte yazılan şablon `pg_design_templates()`'in static önbelleğinde
  yoktur: `pg_design_template_custom_get()`.
- Sunucunun düşürdüğü bağlantı (`_pg_member_drop_empty_links`, boş token'lı
  `_bindings.href`) yalnız o düğümü atar: ızgarada hücre boş kalmasın diye
  bağlamayı hücrenin kendisine (`a.col-*`) koy.
- Giriş Bölgesi (`login_region`, `widgets_account.php`): iki durum
  (`is_signed_out` / `is_signed_in`), tuvalde şerit çipleriyle geçilir.
  Yerleşim başına id tekilleştirmesi form sarmadan **önce** yapılır (CSRF
  alanının id'si korunur). Tasarlanmış form detay sayfasında yorumlar kayda
  aittir: `pg_sw_record_comment_context()`; görünürlük widget'la aynı
  (`pg_sw_submitted_form_visible()`).
- Hata sayfası widget'ı (`error_page`): `get_error_screen()` bu widget'ı taşıyan
  sayfayı eski `error` sayfa tipinden önce seçer. Tek ekranlı yeni widget
  türünü `_SW_ONE_SCREEN`'e yaz (loop alanı gizlenir).

## Önizleme, hayaletler, düzenleme kalemleri

- Önizleme (blob) sistem widget'ını sunucuya çizdirir: `designer/preview_widgets`
  → `pg_designer_preview_widgets()`; editörün elindeki ağaç/ayar
  `pg_sw_preview_overrides()` ile `_expand_system_widgets()`'e girer. Arka plan
  render'ıdır (`pg_seo_rendering(true)`): yeni bir renderer oturum, durum kodu
  ya da yönlendirme yazıyorsa bu bayrağa bakmalı.
- Canlı önizleme: pencere açıkken değişiklik `_sdLivePreviewSchedule()` ile
  yansır (800 ms debounce; `preview_widgets` yalnız yük imzası değişince;
  `location.replace(blob)` + kaydırma geri yükleme, `document.write` değil).
  Kancalar: `render()` sonu, `scheduleAutosave()`, `pg-design-theme-change`,
  `_setCustomField()`, `_pgTabsSwitch()`, ayarlar modalının
  `hidden.bs.modal`'ı. Pencere kapalıyken kanca tek özellik okur; önizlemeye
  giren yeni bir durumu bu kancalardan birinden geçmeyen yolla
  değiştiriyorsan çağrıyı ekle.
- Tuvaldeki hayalet kayıtlar (`_sdAppendGhosts`, `designer/widget_ghosts`)
  yalnız DOM'dur: `data-sd-id` taşımaz, `inert`, ağaca girmez. Yeni bir liste
  türü eklenecekse sunucuda `pg_designer_widget_ghosts()` tür listesine ve JS'te
  `_SD_GHOST_TYPES`'a yaz. Hayalet sayısı widget'ın sayfada gösterdiğini izler:
  sunucu ilk sayfayı (`items_per_page`, `max_results` sınırıyla) çizer, ilk
  kaydı atar (yeri kartındır), kalanı döndürür; üst sınır `_SD_GHOST_LIMIT`.
  Kartın bağlı görseli ilk hayalet görselin doğal boyutunu alır
  (`data-sd-bind-src`, `_sdBindsSrc()`).
- Yetkiliye kalem: `pg_sw_add_edit_chip()` (kaydın ilk öğesine; bağlantı /
  tablo satırı olan kayda konmaz), yetki `pg_sw_can_edit_products()` /
  `pg_sw_can_edit_submitted_forms()`. Düzenleme modu görsel sayfada yoktur
  (`get_page.php` onu yok sayar).

## Tema: görünüm ve renk paleti (`includes/fn/design_themes.php`)

- Tasarım iki bağımsız stil dosyası giyer: **görünüm** (`style.style_look`,
  `assets/css/themes/looks/<ad>.css`, yalnız `--pg-*` belirteci) ve **renk
  paleti** (`style.style_palette`, `palettes/<ad>.css`). Değer `''` / yerleşik
  anahtar / `file-<id>` (dosya yöneticisindeki özel dosya, başında
  `/*! pg-theme {json} */`). Sayfalara hiçbir şey yazılmaz.
- Yükleme sırası: bootstrap → `bridge.css` (Bootstrap'in sabit bileşen
  renklerini değişkene bağlar) → `base.css` (belirteçleri bileşenlere
  çevirir) → görünüm → palet. Çıktıda `get_page_content.php`, tuvalde
  `_sdThemeApply()` (applyAssetsToIframe sonunda), önizlemede
  `_sdPreviewDocHtml()` bunu **son Bootstrap bağlantısının arkasına** koyar.
- Yeni bileşen stili yazarken renk sabitlemeyin: `var(--bs-primary)`,
  `--pg-on-primary`, `--pg-primary-fg` kullanın; şekil için `--pg-radius-*`,
  `--pg-shadow*`, `--pg-border-width`. Belirteç tanımı `:root, [data-bs-theme]`
  üzerinde olmalı (renge bağlı belirteç koyu bölümde yeniden hesaplansın).
- Başlangıç ağaçlarında Bootstrap sınıfları + yardımcılar yeter; tema onları
  zaten giydirir. Sayfalama çıkaran widget kapsayıcısı `py-4` taşır.
- Palet dosyaları elle düzenlenmez: `php tools/build_theme_palettes.php`.

## Çeviri ve taslak (2026.4.7)

- Görünen ama alt çizgili prop'ta duran metin (katalog düğmesinin
  `_labelDetail` / `_labelExpand`'i) `pg_tr_node_fields()`'a yazılmadan
  çeviriye girmez; renderer onu bağlama geçişinde okuyorsa orada
  `pg_tr_text()` ile oku. Editör tarafı `_sdTrCandidate` ve
  `_sdTrFieldLabel`.
- form_list_view / form_item_view `translate_records`: kayıt metinleri
  `pg_tr_extract_form_records()` (sahip `form_records`, Formlar grubu) ve
  çizimde `pg_tr_record_fields()` (ham değer, kaçıştan önce).
- Taslak = özel "Taslaklar" klasöründeki sayfa (`pg_page_is_draft()`,
  `page_drafts` dönülecek klasörü tutar). Editör `page_folder`'ı her yerde
  dönülecek klasör olarak taşır; durum `page_draft` alanıyla
  `pg_designer_save_page()`'ten geçer, ayrı uç yok. Taslağa göre karar veren
  yeni kod klasörü `pg_page_draft_publish_folder()` ile çözsün.
- Sayfa kopyalayan kod taslak sayfanın `page_drafts` satırını da kopyalar
  (`duplicate_page_f.php`, `duplicate_style.php`); klasör seçicide
  Taslaklar klasörünün yalnız kendi satırı çıkarılır, alt ağacı değil.
- Editörün yarattığı sayfa taslak başlar (`_pgNewPageDraft()`): yeni bir
  sayfa yaratma yolu eklersen sekme nesnesine `page_draft:
  _pgNewPageDraft()` yaz. Taslak sekmede ana düğme "Kaydet", yanında
  `#sd-publish-page` "Yayınla" (`_pgSetDraft([etkin], false)`); yayındaki
  sekmede tek düğme. Tek kayıt bütün tasarımı yazar: taslaktan Kaydet,
  yayındaki sayfalarda bekleyen değişikliği de yayına koyar (ileti
  söyler). Sunucu varsayılanı yayında kalır — kurulum, API ve AI
  asistanı sayfaları `page_draft` göndermez.
- Ctrl+S editörün kendisinindir: `#style_designer_form`
  `disable_shortcut` taşır, pencerenin yakalama dinleyicisi ve tuvalin
  `_sdShortcutKeydown()`'u `_sdSaveShortcut()`'a (ana düğme) gider; not
  penceresi ve ayarlar modalı kendi Ctrl+S'lerini tutar. Araç çubuğu
  düğmesinin başlığını `_sdSetButtonTitle()` ile değiştir: panel her
  başlıklı öğeye yüklenişte bir popover verir (`pgBindTitlePopovers()`),
  sonradan yazılan `title` ona ulaşmaz.
- Tasarım listesi (`view_system_styles.php`) durum sütunu:
  `pg_designer_design_draft_counts()` / `pg_designer_design_state()`;
  toplu yayından kaldır / yayına al `designer/design_publish` →
  `pg_designer_design_set_draft()` (editörde açık tasarımı reddeder).

## Dil Seçici Düğme ve şablon resmi (2026.4.7)

- `COMPONENTS.language_switcher` ("Dil Seçici Düğme"): çıktısı isteğe
  (dil, adres, sorgu) bağlı bileşen kaydedilen gövdeye çizilmez.
  `_render_component_html()` ayarları taşıyan bir yer tutucu yazar
  (`pg_language_switcher_placeholder()`), `pg_tr_finalize()` dil geçişinden
  sonra onu çizer (`pg_language_switcher_expand()`). İsteğe bağlı yeni bir
  bileşen aynı deseni kullansın; yer tutucunun ayarları geri okunurken
  bilinen değerlere süzülür. Editöre dil listesi `sdDesign.siteLanguages`
  ile gelir; tuval ve `toHTML` aynı işaretlemeyi
  (`_sdLangSwitcherMarkup()`) çizer. 2026.4.6'nın belirteçli dil seçici
  sistem widget'ı ayrıdır ve kalır.
- Şablon resmi: şablon dosyasında `'thumb'` (`'store'` →
  `_pg_design_thumb_store()`, yoksa başlangıç sitesinin çizimi). Yeni bir
  şablon türü kendi çizimini `pg_design_thumb_svg()`'ye aynı CSS
  değişkenleriyle (`--tp`, `--ts`, `--tr`, `--tbr`, …) ekler; şablon
  penceresi palet değişince yalnız değişkenleri günceller.

## Bitirmeden önce

`php tools/check_bindings.php`, `php tools/check_lang.php`, `php tools/lint.php`
ve `node --check assets/js/style_designer.js` temiz olmalı.
