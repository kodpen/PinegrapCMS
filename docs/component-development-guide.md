# Pinegrap Style Designer — Component Geliştirme Rehberi

> **Hedef kitle:** Bu rehber, Pinegrap Style Designer için yeni component (hero, CTA, features, testimonials, pricing, stats, vs.) geliştirecek **AI agent'lar için** referans dokümandır. Instruction-dense. Kullanım için değil, üretim için.
>
> **Kullanım:** Component geliştirmeye başlamadan önce bu rehberi baştan sona oku. Bölüm 9'daki kontrol listesini tamamlamadan component'i "bitmiş" sayma.
>
> **Kapsam:** `assets/js/style_designer.js` (~13.500 satır). Ağaç tabanlı node modeli, canvas iframe önizleme, `style_code` serialization.
>
> **Son güncelleme:** 2026-04-22.

---

## 0. Mimari Kısıtlar (zorunlu, istisnasız)

| # | Kural | Gerekçe |
|---|---|---|
| 1 | **Custom JS yazılmaz** | Component sadece Bootstrap 5.3.8 native `data-bs-*` davranışlarıyla çalışır. Ek `<script>` üretme. |
| 2 | **Template-hardcoded inline style yasak** | Bkz. bölüm 7 — inline style kuralı 3 kaynağa ayrılır. |
| 3 | **Ek CSS dosyası yok** | Component `.css` referansı eklemez. |
| 4 | **Yalnızca BS 5.3.8 + Bootstrap Icons 1.11.3** | Başka kütüphane/font yok. |
| 5 | **`render(doc, p)` ve `toHTML(p, pad)` birebir aynı markup** | Tutarsızlık en sık bug kaynağı. |

**Doğrulama:**
```bash
grep -n "style=\"[^\"]*\"" assets/js/style_designer.js | grep "componentType === 'YENİ_ID'"
grep -n "<script"        assets/js/style_designer.js | grep "componentType === 'YENİ_ID'"
```
İki komut da boş dönmeli — yalnızca prop-driven veya `_attrs`-driven style kabul (bkz. bölüm 7).

---

## 1. Tree JSON Yapısı

### 1.1. Node şeması

```js
{
    _id:      string,    // gid() üretir
    type:     'root' | 'container' | 'area' | 'row' | 'col' | 'region' |
              'component' | 'content' | 'semantic',
    props:    object,    // bkz. 1.3
    children: array      // bazı tiplerde boş kalır
}
```

Tek giriş noktası: [`createNode(type, props, children)` — style_designer.js:62](assets/js/style_designer.js:62).

### 1.2. Node tipleri

| Tip | Kullanım | Kimin çocuğu olabilir |
|---|---|---|
| `root` | Ağaç kökü; kullanıcı oluşturmaz | — |
| `container` | `.container` / `.container-fluid`; grid'in dış katmanı | root, area, semantic, col |
| `row` | `.row`; sadece container veya col içinde | container, col |
| `col` | `.col-*`; sadece row içinde | row |
| `area` | Pinegrap page region wrapper (page_header, page_content, …) | root, container, col |
| `region` | Pinegrap şablon etiketi (`<pregion>`, `<cregion>`, `<menu>`, …) | container, col, area |
| `component` | `COMPONENTS[x]` içinden render'lanan monolitik BS bileşeni | geniş (bkz. canDrop 3950) |
| `content` | Atomik içerik: `heading`, `paragraph`, `image`, `icon`, `link`, `divider`, `spacer`, `custom_html` | col, semantic, area |
| `semantic` | Saf HTML etiketi (`section`, `article`, `nav`, `div`, `a`, `button`, `ul`, `li`, `h1-h6`, `p`, `form`, `table` altları) | geniş |

**Ayrım — `component` vs `semantic`:**

- `component` → render/serialize mantığı `COMPONENTS[type]` içinde kodlanmış, monolitik.
- `semantic` → kullanıcı tag ve class'ları serbestçe değiştirebilir, ağaç gezilerek HTML üretilir.

**Varsayılan yeni component kararı: `semantic` ağacı (explosion) üret.** Monolitik `COMPONENTS[...]` sadece: atomik BS bileşenleri (btn, alert, progress, badge) veya özel çift-tık düzenlenebilir alanları olan bileşenler (pricing fiyatı, hero başlık+subtitle).

### 1.3. `props` — ortak alanlar

| Prop | Amaç | style_code'a sızar mı |
|---|---|---|
| `id` | HTML `id` | Evet |
| `cssClass` | HTML `class` ekleri | Evet |
| `_attrs` | `[{name, value}]` — data-*, aria-*, role, style, vb. | Evet |
| `_label` | Sol tree paneli badge'i | **Hayır** |
| `_locked` | Düzenleme kilidi | **Hayır** |
| `_expanded` | Tree açık/kapalı | **Hayır** |
| `customName` | `getNodeLabel` tarafından tree'de gösterilen ad | **Hayır** |

**Altçizgi prefix (`_`) kuralı:** Designer metadata — `style_code`'a sızmaz, sızmamalı. Yeni metadata flag ekliyorsan `_` prefixi kullan.

### 1.4. Tip-özel proplar

```js
container : { fluid: bool, cssClass: '' }
row       : { gutter: '0..5'|'', justify: 'start|center|end|around|between|evenly', align: 'start|center|end', cssClass: '' }
col       : { xs, sm, md, lg, xl, xxl, offsetXs, offsetMd, order, cssClass }    // colProps() helper
semantic  : { tag: 'section', cssClass: '', text?, href?, target?, rel?, _attrs?, customName? }
content   : { contentType: 'heading|paragraph|image|icon|link|divider|spacer|custom_html', cssClass, ...contentType-özel }
component : { componentType: 'btn|card|navbar|hero|pricing|...', cssClass, ...componentType-özel }
region    : { regionType: 'page|system|common|dynamic|menu|menu_sequence|login|ad|cart|tag_cloud|pdf|mobile_switch', regionName, cssClass }
```

### 1.5. `contentType` envanteri — [CONTENT registry style_designer.js:725](assets/js/style_designer.js:725)

| contentType | Default props | Render çıktısı |
|---|---|---|
| `heading` | `tag:'h2', text, align, cssClass` | `<h2 class="…">text</h2>` |
| `paragraph` | `text, lead, align, cssClass` | `<p class="lead …">text</p>` |
| `image` | `src, alt, width, height, fluid, rounded, objectPosition, aspectRatio, cssClass` | `<img>` veya `.ratio > img` |
| `icon` | `iconName:'bi-star', fontSize:24, cssClass` | `<i class="bi bi-star">` |
| `link` | `text, href, target, rel, cssClass` | `<a>` |
| `divider` | `margin:'4', cssClass` | `<hr class="my-4">` |
| `spacer` | `height:'40'` | `<div style="height:40px">` |
| `custom_html` | `html` | ham HTML |
| `text` | `text, cssClass` | `<span>` — **LEGACY**, palette'te yok, eski kayıtlar için tutuluyor |

Yeni `contentType` ekleme eşiği yüksek: sadece atomik, özel UX'li (icon picker, image picker gibi) ihtiyaçlar için. Aksi halde `semantic` yeterli.

---

## 2. Component Anatomisi

### 2.1. Monolitik component manifestosu

```js
myComp: {
    label:         'İnsan Okunur Ad',
    icon:          'bi-XXX',
    defaultProps:  { ... },
    render:        function(doc, p) { return HTMLElement; },
    toHTML:        function(p, pad) { return string; }
}
```

`render` ve `toHTML` **aynı HTML'i üretir**. DOM mutasyonu ile string template birebir eşleşir.

### 2.2. Pricing örneği — [style_designer.js:598](assets/js/style_designer.js:598)

Monolitik. Çift-tıkla edit hookları var (tier, price, btnText — [3540](assets/js/style_designer.js:3540)).

```js
pricing: {
    label: 'Pricing Table', icon: 'bi-tags',
    defaultProps: { tier:'Pro', price:'15', currency:'$', period:'/mo',
        features:'Up to 5 Users\n10GB Storage\nEmail Support\nHelp Center Access',
        btnText:'Get Started' },
    render: function(doc, p) {
        var d = doc.createElement('div');
        d.className = 'card mb-4 rounded-3 shadow-sm';
        d.innerHTML =
            '<div class="card-header py-3 text-center"><h4 class="my-0 fw-normal">' + esc(p.tier) + '</h4></div>' +
            '<div class="card-body text-center">' +
                '<h1 class="card-title pricing-card-title">' + esc(p.currency) + esc(p.price) +
                    '<small class="text-body-secondary fw-light">' + esc(p.period) + '</small></h1>' +
                '<ul class="list-unstyled mt-3 mb-4">' +
                    (p.features||'').split('\n').map(i => '<li>'+esc(i)+'</li>').join('') + '</ul>' +
                '<button type="button" class="w-100 btn btn-lg btn-primary">' + esc(p.btnText) + '</button>' +
            '</div>';
        return d;
    },
    toHTML: function(p, pad) { /* aynı markup, pad indent'lenmiş */ }
}
```

### 2.3. Explosion (ağaç) örneği — navbar [style_designer.js:4183](assets/js/style_designer.js:4183)

`createFromPalette` case'i monolitik component döndürmek yerine `createNode` ağacı inşa eder:

```js
return createNode('semantic', { tag: 'nav', cssClass: 'navbar navbar-expand-lg navbar-dark bg-dark', customName: 'Navbar' }, [
    createNode('semantic', { tag: 'div', cssClass: 'container-fluid', customName: 'Navbar Container' }, [
        createNode('content', { contentType: 'link', text: 'Brand', href: '#', cssClass: 'navbar-brand' }),
        createNode('semantic', { tag: 'button', cssClass: 'navbar-toggler', customName: 'Toggler',
            _attrs: [
                { name: 'type',             value: 'button' },
                { name: 'data-bs-toggle',   value: 'collapse' },
                { name: 'data-bs-target',   value: '#' + navId }
            ] }, [
            createNode('semantic', { tag: 'span', cssClass: 'navbar-toggler-icon', customName: 'Toggler Icon' })
        ]),
        // ... collapse wrapper + ul.navbar-nav + li.nav-item * N
    ])
]);
```

**Seçim kuralı:**

| Kriter | Monolitik | Explosion |
|---|---|---|
| İçindeki parçalar kullanıcı tarafından düzenlenebilir olacak mı? | Hayır | Evet |
| Özel çift-tık edit davranışı var mı? | Olabilir | Genellikle yok (content kendiliğinden editable) |
| Kullanıcı iç yapıyı değiştirmek isteyecek mi? | Nadir | Sık |
| Kullanıcı parça ekleyip çıkarabilmeli mi? | Hayır | Evet (composite add) |

**Yeni starter section component'leri için varsayılan: explosion.**

---

## 3. Palette Kaydı

Palette kategorileri: [style_designer.js:5156](assets/js/style_designer.js:5156).

### 3.1. Kategoriler

```js
[
    { title:'Layout',    icon:'bi-grid',        items:[ container, container-fluid, row, col ] },
    { title:'Semantic',  icon:'bi-code-square', items:[ section, article, main, header, footer, aside, nav, div ] },
    { title:'Bootstrap', icon:'bi-bootstrap',   items: Object.keys(COMPONENTS).map(...) },  // otomatik
    { title:'Content',   icon:'bi-fonts',       items: Object.keys(CONTENT).filter(k => k !== 'text').map(...) },  // otomatik
    { title:'Örnekler',  icon:'bi-magic',       items:[ navbar-light-cta, footer-multi-col, ... ] },  // manuel
    { title:'Formlar',   icon:'bi-ui-checks',   items:[ form-text, form-email, ..., form-reset ] }
]
```

### 3.2. Yeni component'in yeri

| Component tipi | Nereye |
|---|---|
| Yeni `COMPONENTS[x]` girdisi (btn, pricing gibi atomik BS) | Otomatik `Bootstrap` kategorisinde görünür |
| Yeni explosion (section-level — CTA, stats, testimonial grid) | Manuel olarak `Örnekler` kategorisine item ekle, `createFromPalette` case'i yaz |
| Yeni `CONTENT[x]` girdisi (yüksek eşik) | Otomatik `Content` kategorisinde |
| Yeni kategori | `categories` dizisine obje ekle |

### 3.3. Örnekler kategorisine item ekleme pattern'i

```js
// ~5186
{ title: 'Örnekler', icon: 'bi-magic', items: [
    ...mevcut...,
    { label: 'CTA: Gradient BG',
      type:  'component',
      icon:  'bi-megaphone',
      extra: { componentType: 'cta-gradient' } },
] }
```

Sonra `createFromPalette` → `component` case → `extra.componentType === 'cta-gradient'` dalı → explosion döndür.

### 3.4. Palette HTML çıktısı

```html
<div class="sd-drag-item" draggable="true"
     data-type="component"
     data-search="cta: gradient bg"
     data-extra='{"componentType":"cta-gradient"}'>
    <span class="sd-drag-icon bi bi-megaphone"></span>
    <span class="sd-drag-label">CTA: Gradient BG</span>
</div>
```

### 3.5. `customName` kuralı

Tree'de görünen ad `getNodeLabel(node)` → `node.props.customName` öncelik. **Her yapısal `semantic` node'a anlamlı `customName` ver** (örn. `'Navbar Container'`, `'Hero Body'`, `'Slide Content'`). Aksi halde tree "div, div, div" dolar.

---

## 4. Auto-wrap ve Drop Davranışı

### 4.1. Auto-wrap kuralları — [handleDrop style_designer.js:4049](assets/js/style_designer.js:4049)

Root'a düşürüldüğünde:

1. **`col` → root** ⇒ `container-fluid > row > col` ile sarılır.
2. **`row` → root** ⇒ `container-fluid > row` ile sarılır.
3. **Palette source + `type=component` + sonucu `semantic` ağaç → root** ⇒ **`container` ile sarılır**, şu `componentType`'lar **hariç**:
   ```js
   var _noWrapCtypes = [
       'navbar-light-cta', 'navbar-search', 'navbar-dark-center',
       'footer-minimal',   'footer-multi-col', 'footer-newsletter',
       'carousel',         'modal', 'offcanvas', 'dropdown'
   ];
   ```

### 4.2. `_noWrapCtypes` kararı

Yeni component eklerken kendine sor: **"Bu component ekran kenarına dayanmalı mı? (full-width arka plan / barlar)"**

| Karar | Tipik örnekler | `_noWrapCtypes`'a |
|---|---|---|
| **Evet, tam genişlik** | navbar, footer, full-bleed hero, carousel, modal, offcanvas | **Ekle** |
| **Hayır, içerik** | pricing table, feature grid, testimonial card, contact form, CTA (container içeride) | **Ekleme** (auto-wrap devreye girsin) |

### 4.3. `canDrop` semantik kuralları — [3950](assets/js/style_designer.js:3950)

- Void HTML tag'leri (img, input, hr, br, meta, link, …) çocuk alamaz.
- `<button>`, `<h1-h6>`, interactive component'ler → inline-only içerik.
- `<a>` içinde başka `<a>` veya `content:link` olamaz.
- `<p>` → inline-only.
- `<ul>/<ol>` → sadece `<li>`.
- `<table>` → `thead/tbody/tfoot/caption/colgroup`; `<tr>` → `<td>/<th>`.
- `<form>` içinde başka `<form>` olamaz.

Yeni component ağacı kurarken bu kurallara uy.

---

## 5. Editability (Çift Tıkla Düzenleme)

`setupInlineEditing(wrapper, innerEl, node)` — [style_designer.js:3434](assets/js/style_designer.js:3434).

### 5.1. Otomatik editable

| Node | Çift-tık |
|---|---|
| `content:heading`, `content:paragraph`, `content:text` | Rich text edit |
| `content:link` | Metin edit |
| `content:icon` | Icon picker modal |
| `content:image` | `software_image_picker` modal |
| `semantic` + `props.text` var | Metin edit |
| `component:hero` | title + subtitle ayrı |
| `component:alert_box`, `component:btn` | Metin edit |
| `component:card` | title + text ayrı |
| `component:pricing` | tier + price + btnText ayrı |
| `component:features` | title + description ayrı |

### 5.2. Yeni monolitik component'e edit hook ekleme

```js
// setupInlineEditing içinde ~3522 — cType === '...' dalları zincirine ekle
} else if (cType === 'cta-gradient') {
    var h = innerEl.querySelector('h2');
    var p = innerEl.querySelector('p');
    var b = innerEl.querySelector('.btn');
    if (h) editableTargets.push({ el: h, prop: 'title' });
    if (p) editableTargets.push({ el: p, prop: 'description' });
    if (b) editableTargets.push({ el: b, prop: 'btnText' });
}
```

Framework geri kalanı (contentEditable, commit, save-state) halleder.

### 5.3. Explosion component'lerde edit

`createFromPalette` ağacı `content:heading / content:paragraph / content:link` döndürüyorsa → **zaten editable**, ek kod gerekmez.

---

## 6. Bootstrap Kullanım Prensipleri

### 6.1. Grid disiplini

```
container (veya container-fluid)
 └─ row
     └─ col-{bp}-{n} / col-auto
         └─ içerik
```

- `row` sadece `container/container-fluid` veya `col` içinde.
- `col` sadece `row` içinde.
- `col > row > col > …` ile nested grid.

### 6.2. Responsive breakpoint'ler (BS 5.3)

| Prefix | Min width |
|---|---|
| (none) | 0 (xs) |
| `sm-` | ≥576px |
| `md-` | ≥768px |
| `lg-` | ≥992px |
| `xl-` | ≥1200px |
| `xxl-` | ≥1400px |

Mobile-first sıralama: `col-12 col-md-6 col-lg-4`.

### 6.3. Utility class sıralama kuralı

```
display → flex/grid helpers → spacing (m,p) → sizing (w,h) →
typography (text-*, fw-*, fs-*) → color (bg-*, text-*, border-*) →
border / rounded → shadow → other (position, overflow, ...)
```

Örnek: `d-flex align-items-center justify-content-between p-3 w-100 text-white fw-bold bg-primary border rounded-3 shadow-sm`.

### 6.4. `data-bs-*` native davranışları

**Custom JS yasaktır.** Bootstrap native widget'larını `data-bs-*` ile kullan:

| Davranış | Attribute |
|---|---|
| Collapse | `data-bs-toggle="collapse" data-bs-target="#id"` |
| Modal | `data-bs-toggle="modal" data-bs-target="#id"` / `data-bs-dismiss="modal"` |
| Offcanvas | `data-bs-toggle="offcanvas" data-bs-target="#id"` |
| Tab / Pill | `data-bs-toggle="tab"` / `"pill"`, `data-bs-target="#pane"` |
| Dropdown | `data-bs-toggle="dropdown"` |
| Tooltip | `data-bs-toggle="tooltip" data-bs-title="..."` |
| Popover | `data-bs-toggle="popover"` |
| Carousel | `<div class="carousel slide" data-bs-ride="carousel">` |
| Scroll spy | `data-bs-spy="scroll" data-bs-target="#nav"` |

Pattern:
```js
_attrs: [
    { name: 'data-bs-toggle', value: 'collapse' },
    { name: 'data-bs-target', value: '#' + uid }
]
```

### 6.5. Accessibility minimum

| Öğe | Zorunlu |
|---|---|
| İkon-only button | `aria-label` |
| Toggle button | `aria-expanded`, `aria-controls` |
| İkon-only link | `aria-label` |
| `<img>` | `alt` attribute (dekoratifse `alt=""`) |
| `<i class="bi">` | `aria-hidden="true"` (parent'ta label varsa) |
| Form input | `<label for="id">` + `id` |
| Collapse target | `role` + `aria-expanded` |

---

## 7. Inline Style Kuralı — Üç Kaynak Ayrımı

Inline `style=""` yasağı **mutlak değil, kaynak-koşullu**. Style'ın geldiği yere göre değerlendirilir.

### 7.1. Üç kaynak

| Kaynak | Örnek | Kararı |
|---|---|---|
| **A. Template-hardcoded** (author component yazarken string'e gömmüş) | `toHTML: function(p, pad){ return pad+'<div style="padding:20px;color:white">...'; }` | **YASAK** |
| **B. Options-driven** (kullanıcı Options panelinde prop seçer; render'da dinamik oluşur) | `<div style="background-color: ' + esc(p.bgColor) + '">` | **İZİNLİ** |
| **C. Attributes-driven** (kullanıcı Attributes panelinde `_attrs`'e doğrudan `style` değeri girer) | `_attrs: [{name:'style', value:'transform: rotate(5deg)'}]` | **İZİNLİ** |

**Özet mantık:** Style'ın kaynağı kullanıcı etkileşimi ise kabul. Author'ın template string'ine gömülmüş sabit style ise ret.

### 7.2. Yasak örnekler (template-hardcoded)

```js
// ❌ Sabit padding — BS utility var: p-4 / py-4 / px-4
'<div style="padding: 20px">…</div>'

// ❌ Sabit renk — BS utility var: text-white, bg-primary
'<h1 style="color: white">Title</h1>'

// ❌ Sabit margin — BS utility var: mt-5 / my-5
'<div style="margin-top: 50px">…</div>'

// ❌ Sabit font-weight — BS utility var: fw-bold
'<span style="font-weight: bold">…</span>'

// ❌ Sabit display — BS utility var: d-flex
'<div style="display: flex">…</div>'
```

### 7.3. İzinli örnekler (options-driven)

```js
// ✅ bgColor user prop'tan geliyor
'<div class="py-5" style="background-color: ' + esc(p.bgColor) + '">…</div>'

// ✅ imageUrl user seçiyor
'<div class="hero" style="background-image: url(' + esc(p.imageUrl) + ')">…</div>'

// ✅ fontSize user kontrollü (icon resize handle)
'<i class="bi ' + esc(p.iconName) + '" style="font-size: ' + p.fontSize + 'px"></i>'

// ✅ aspectRatio user seçiyor (ama burada BS .ratio-X sınıfı olduğu için sınıf kullanılmış)
'<div class="ratio ratio-' + esc(p.aspectRatio) + '">…</div>'

// ✅ objectPosition user girişi
'<img src="…" style="object-position: ' + esc(p.objectPosition) + '">'
```

Kural: **Prop değeri render zamanında dinamik interpolasyon yapılır** ve **BS utility bu değeri karşılayamaz** (sürekli değer, arbitrary transform, user-girdili renk vb.) → OK.

### 7.4. İzinli örnekler (attributes-driven)

Kullanıcı Attributes panelinde `_attrs`'e `{name: 'style', value: 'transform: rotate(5deg); filter: blur(2px)'}` eklemişse → `style_code`'da korunur, dokunulmaz.

```js
// _attrs serialization (style_designer.js içindeki addGlobalAttrs / toHTML pattern'leri)
// User'ın girdiği her attribute olduğu gibi emit edilir.
```

Component author'ı bunu **programatik olarak yazmaz**; sadece framework'ün kullanıcı girişini koruduğunu bilir.

### 7.5. Karar algoritması

Bir style eklerken sırayla:

1. Bu değer sabit mi? (Her render'da aynı.) → **Evet ⇒ BS utility class bul, kullan.**
2. Değer bir user prop'tan mı geliyor? → Evet ⇒ **Inline style prop'tan interpolasyon OK**, ama önce BS class karşılığı var mı kontrol et:
   - `padding: p.pad + 'px'` ⇒ BS spacing scale (p-1..p-5) yeterli mi? → Evet ⇒ `class="p-' + p.pad + '"`. Hayır ⇒ inline.
   - `background-color: p.bg` (arbitrary renk) ⇒ BS utility yok, inline OK.
3. User Attributes'e mi yazıyor? → Zaten framework koruyor.

---

## 8. İçerik Varsayılanları

| Alan | Kural | Örnek |
|---|---|---|
| Başlık | Component türünü ima eden cümle | `'Ready to get started?'`, `'Pricing that scales'` |
| Açıklama | 1-2 cümle, 80-140 karakter, gerçekçi | `'Join thousands of teams shipping faster every day.'` |
| Buton metni | Aksiyon ifade eden 1-3 kelime | `'Start Free Trial'`, `'Learn More'`, `'View Plans'` |
| Link href | `'#'` (placeholder) | — |
| Image src | `''` (boş — placeholder kutu devreye girer) | — |
| Fiyat | Mantıklı sayı + currency | `'15'`, `'$'`, `'/mo'` |
| Feature list | Newline ayırmalı gerçekçi özellikler | `'Up to 5 Users\n10GB Storage\nEmail Support'` |
| Testimonial | 1-2 cümle gerçekçi alıntı | `'This product changed how we work.'` |
| İsim | Placeholder isim | `'Alex Morgan'`, `'Jane Doe'` |

**Locale:** Placeholder İngilizce. `tr.json`'a key eklenmez — kullanıcı drop sonrası kendi içeriğini yazar.

---

## 9. Component Kontrol Listesi

Merge eşiği: tamamı geçmeli.

- [ ] Canvas iframe'de sorunsuz render
- [ ] Preview (`Ctrl+Alt+P`, blob:) console error'suz
- [ ] Frontend `/a` veya demo sayfada `style_code` render'ı doğru
- [ ] Responsive: 576/768/992/1200/1400 breakpoint'lerinin her birinde anlamlı
- [ ] `data-bs-*` davranışları çalışıyor (tab, collapse, modal, carousel, dropdown)
- [ ] **Template-hardcoded inline style yok**: `grep` ile doğrula (bkz. bölüm 0)
- [ ] `<script>` emit yok
- [ ] Harici CSS link yok
- [ ] Palette'te doğru kategoride (Bootstrap / Örnekler / Content / Formlar)
- [ ] Icon anlamlı (Bootstrap Icons, component'i temsil eden)
- [ ] Auto-wrap kararı doğru — `_noWrapCtypes` listesi bilinçli
- [ ] Editable slot'lar çift-tıkla açılıyor (metin component'ler)
- [ ] Her yapısal `semantic` node'da `customName` var
- [ ] Accessibility: `aria-label`, `role`, `alt`, `<label for>` uygun
- [ ] `style_code` valid HTML: console warning yok, semantic iç içe geçme doğru
- [ ] Light + dark canvas her ikisinde okunur (dark toggle [1183](assets/js/style_designer.js:1183))
- [ ] Undo/redo bileşen üzerinde çalışıyor (`saveState` zinciri doğru)
- [ ] `render(doc, p)` ve `toHTML(p, pad)` birebir aynı markup

---

## 10. Anti-Pattern'ler

| # | Yasak | Neden |
|---|---|---|
| 1 | Template-hardcoded inline style (bkz. 7.2) | BS utility var, cascade/override imkansız |
| 2 | `<script>` veya inline event handler (`onclick`, `onload`) | Güvenlik / XSS; `data-bs-*` yeterli |
| 3 | Yeni `.css` dosyası veya `<link rel="stylesheet">` | Kural 3 |
| 4 | Canvas-only CSS eklemek (style_code'a yansımayan) | Kullanıcı canvas'ta görür, frontend'de görmez. İstisna: altyapı `IFRAME_CSS` [907](assets/js/style_designer.js:907) |
| 5 | Hardcoded `px` sabitler | BS spacing scale (`p-3`, `mt-5`, `fs-4`). Gerçekten gerekli ise 7.3'teki dinamik pattern |
| 6 | `!important` override | Class önceliği kötü; yeniden tasarla |
| 7 | Component HTML içinde `<style>` block | Kural 3 |
| 8 | jQuery veya başka 3rd-party JS bağımlılığı | Component JS zaten yok; dış utility'lerde de yaratma |
| 9 | Başka component'in render mantığını kopyala-yapıştır | DRY — `createNode('component', {componentType:'btn', …})` ile tekrar kullan |
| 10 | `innerHTML +=` ile kullanıcı girdisi | `esc()` helper'ı mutlaka kullan — XSS |
| 11 | `customName` vermeden `semantic` ağacı | Tree'de kaybolur |
| 12 | `_attrs` üzerinden `class` eklemek | `cssClass` prop'u var. `_attrs` = `data-*`, `aria-*`, `role`, `style` (user), özel HTML |
| 13 | Grid disiplinini bozmak (`row` içinde olmayan `col`, `col` içinde olmayan `row`) | BS layout bozulur |
| 14 | Altçizgi prefix'siz designer metadata | `_label`, `_locked`, `_expanded`, `_attrs` kuralı — sızmamak için |

---

## 11. "Hello World": `cta-simple` Eklemek

Tam bir explosion component eklemenin adım adım örneği.

### Adım 1. `createFromPalette` case'i

`assets/js/style_designer.js` — `createFromPalette` fonksiyonunun `case 'component'` içindeki `if (ctype === 'hero')` altına:

```js
if (ctype === 'cta-simple') {
    return createNode('semantic', { tag: 'section', cssClass: 'py-5 bg-light', customName: 'CTA Section' }, [
        createNode('container', { fluid: false, cssClass: 'text-center' }, [
            createNode('content', {
                contentType: 'heading', tag: 'h2',
                text: 'Ready to get started?',
                cssClass: 'fw-bold mb-3'
            }),
            createNode('content', {
                contentType: 'paragraph',
                text: 'Join thousands of teams shipping faster every day.',
                cssClass: 'lead mb-4 text-body-secondary'
            }),
            createNode('component', {
                componentType: 'btn',
                text: 'Start Free Trial',
                variant: 'primary',
                size: 'lg',
                href: '#'
            })
        ])
    ]);
}
```

**Checklist gözetildi:**
- Kök wrapper: `semantic tag:'section'` — anlamlı HTML.
- Class'lar: `py-5 bg-light` — BS utility; inline style yok.
- Grid: `container` içeride.
- Düzenlenebilir metinler: `content:heading` + `content:paragraph` → otomatik editable.
- Buton: mevcut `component:btn`; markup kopyalanmadı.
- `customName`: her `semantic`'te dolu.
- Placeholder: gerçekçi (bölüm 8).

### Adım 2. Palette kaydı (`Örnekler` kategorisi)

`assets/js/style_designer.js` ~5186'da:

```js
{ title: 'Örnekler', icon: 'bi-magic', items: [
    // ... mevcut ...
    { label: 'CTA: Simple',
      type: 'component',
      icon: 'bi-megaphone',
      extra: { componentType: 'cta-simple' } }
] }
```

### Adım 3. Auto-wrap kararı

`<section class="py-5 bg-light">` full-width bg-light istiyor → `_noWrapCtypes`'a ekle:

```js
// handleDrop ~4062
var _noWrapCtypes = [
    'navbar-light-cta', 'navbar-search', 'navbar-dark-center',
    'footer-minimal',   'footer-multi-col', 'footer-newsletter',
    'carousel',         'modal', 'offcanvas', 'dropdown',
    'cta-simple'   // ← yeni
];
```

(Full-width bg istemeseydi liste dışında bırakırdın; auto-wrap kendiliğinden container sararak içeriği ortalar.)

### Adım 4. Test sırası

1. Sayfa reload. Palette `Örnekler` → "CTA: Simple" var.
2. Canvas'a drop → section + container + heading + paragraph + btn doğru yerleşti.
3. Çift-tık başlık → editable, metin değiştir.
4. Çift-tık buton → editable.
5. Preview (Ctrl+Alt+P) → full-width bg-light, ortalanmış içerik.
6. Responsive: 576px, 992px, 1400px — hepsinde anlamlı.
7. HTML tree viewer → inline style yok, `<script>` yok.
8. Kaydet → DB `style_code` temiz.
9. Frontend test sayfası render'ı 8. adımla tutarlı.
10. Bölüm 9 checklist'i geç.

---

## 12. Kod Yer İmleri

| Konu | Dosya:satır |
|---|---|
| `COMPONENTS` registry | [style_designer.js:338](assets/js/style_designer.js:338) |
| `CONTENT` registry | [style_designer.js:725](assets/js/style_designer.js:725) |
| `createNode` | [style_designer.js:62](assets/js/style_designer.js:62) |
| `colProps` | [style_designer.js:66](assets/js/style_designer.js:66) |
| `createFromPalette` (tüm explosion'lar) | [style_designer.js:4123](assets/js/style_designer.js:4123) |
| Palette categories | [style_designer.js:5156](assets/js/style_designer.js:5156) |
| `handleDrop` (auto-wrap) | [style_designer.js:4049](assets/js/style_designer.js:4049) |
| `canDrop` | [style_designer.js:3950](assets/js/style_designer.js:3950) |
| `setupInlineEditing` | [style_designer.js:3434](assets/js/style_designer.js:3434) |
| `IFRAME_CSS` (altyapı-only) | [style_designer.js:907](assets/js/style_designer.js:907) |
| `generateHTML` (preview) | [style_designer.js:12189](assets/js/style_designer.js:12189) |
| `renderHtmlTree` (HTML panel) | [style_designer.js:6586](assets/js/style_designer.js:6586) |
| `generate_style_code_from_tree` (PHP) | [functions.php:17109](functions.php:17109) |
| `_render_content_html` (PHP — image, icon, vb.) | `functions.php` içinde grep |

---

## 13. Özet Prensipler

1. Explosion > Monolitik — kullanıcıya düzenlenebilir parçalar sun.
2. BS utility class > inline style (template-hardcoded).
3. Inline style user-driven (options/attrs) olduğunda OK.
4. `data-bs-*` > custom JS — her zaman.
5. Her `semantic`'e anlamlı `customName`.
6. Placeholder gerçekçi (Lorem ipsum değil).
7. Auto-wrap kararı bilinçli (`_noWrapCtypes`).
8. `render` ve `toHTML` birebir aynı markup.
9. `_` prefix'li proplar designer metadata, sızmaz.
10. Grid hiyerarşisine saygı: `container > row > col`.
11. Bölüm 9 checklist'i geçmeden merge yok.

---

## Nasıl Kullanılır (AI agent için not)

- **Component geliştirmeden önce bu rehberi baştan sona oku.** Hedef kitlesi sensin.
- **Bölüm 9 kontrol listesini tamamlamadan component'i "bitmiş" kabul etme.** Her kutucuğu fiilen doğrula (grep çalıştır, preview aç, tree panelini incele).
- Bir kural tartışmalı veya kararsız bir durumla karşılaşırsan (ör. yeni bir inline style sınıfı) — **bu dosyaya dönüp bölüm 7'nin üç-kaynak ayrımıyla** kararı ver.
- Rehber eksik/yanılgılı gelirse **dosyayı güncelle** — canlı bir referanstır.
- Component eklerken çıktıyı bölüm 11'deki pattern'e uyarla: (1) `createFromPalette` case, (2) palette item, (3) auto-wrap kararı, (4) checklist.
