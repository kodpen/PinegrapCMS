# Ortak Componentler (Shared Components) — Mimari Plan

> **Durum:** Kararlar entegre edildi — kod yazılmadı  
> **Referans versiyon:** `2026.1.20` (`install/index.php:464`)  
> **Tarih:** 2026-04-23 (kararlar: 2026-04-23)

---

## Özet

Bir stildeki node, isme değil ID'ye bağlı bir referans node'una (`shared_ref`) dönüştürülür. Kayıt `shared_components` tablosunda tutulur; render zamanında `_render_tree_node()` merkezi kaydı inline expand eder. Düzenleme seçilen stilden **inline** yapılır; stil kaydedildiğinde shared kayıt da güncellenir, böylece o bileşeni kullanan tüm stiller otomatik güncel olur. Palette'te "Ortak" sekmesinden sürükle-bırak ile herhangi bir stile yerleştirilebilir. Endpoint: `api.php` (session-based). Erişim: sadece admin + designer. İsim çakışmasında sessiz `[1]`/`[2]` suffix. Phase 1 kapsamı: CRUD + rename + delete + inline edit propagation.

---

## A. Veri Modeli

### Yeni Tablo: `shared_components`

```sql
CREATE TABLE shared_components (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(255) NOT NULL DEFAULT '',
    description  TEXT NOT NULL DEFAULT '',
    tree_json    LONGTEXT NOT NULL DEFAULT '',
    tree_hash    VARCHAR(64) NOT NULL DEFAULT '',      -- SHA-256(tree_json); Phase 4 cache-bust
    category     VARCHAR(100) NOT NULL DEFAULT '',     -- Phase 4 gruplama; şimdi boş
    created_by   INT UNSIGNED NOT NULL DEFAULT 0,      -- soft ref → user.user_id
    created_at   INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at   INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uk_name (name),                         -- isim benzersiz; çakışma → sessiz suffix
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> Tablo adı: `shared_components` — `forms`, `products` gibi mevcut çoğul örneklerle tutarlı.

**`LONGTEXT`:** `style.style_tree_json` da `LONGTEXT` (`install/index.php:4887`). Tutarlılık için aynı tip.

**`UNIQUE KEY uk_name`:** İsim çakışmasında hata dönme — `create`/`rename` backend'i DB'ye yazmadan önce `"Adın Sonu [1]"`, `"Adın Sonu [2]"` gibi suffix deneyerek boş slot bulur ve sessizce kullanır. Bu, sistem geneli için planlanan naming policy ile tutarlıdır.

### Mevcut tablolarda değişiklik

`style` tablosuna **hiçbir yapısal değişiklik gerekmez.** `style.style_tree_json` (eklendi: `upgrade_to_2026_1_20`, `install/index.php:4887`) zaten JSON saklar; bu JSON içine yeni bir node tipi eklenmesi şemayı değiştirmez.

### Tree içinde referans node yapısı

```json
{
  "_id": "abc123",
  "type": "shared_ref",
  "props": {
    "sharedId": 42,
    "sharedName": "Ana Navbar"
  },
  "children": []
}
```

- `sharedId` (integer) — gerçek referans; rename'de kopmaz
- `sharedName` — display-only snapshot; rename'de güncellenmez, sadece palette/badge içindir
- `children` her zaman boş — içerik `shared_components.tree_json`'dan gelir

### Cache / versioning

`tree_hash` (SHA-256) alanı Phase 4 için ayrıldı; Phase 1'de her kayıtta boş bırakılır. Phase 1'de `updated_at` Unix timestamp yeterlidir. Server-side cache şart değil — `shared_components` kayıt sayısı az, her render'da tek `db_value()` sorgusu ucuzdur.

---

## B. DB Upgrade — install/index.php

Mevcut son versiyon: `2026.1.20` (`install/index.php:464`).  
Sonraki versiyon: **`2026.1.21`**

Pattern referansı: `upgrade_to_2026_1_19()` (`install/index.php:4877`) — tek `db()` çağrısı.  
CREATE TABLE referansı: `upgrade_to_2026_1_X` içinde `CREATE TABLE` kullanılmış örnekler mevcut.

**1.** `$versions` dizisine ekle (`install/index.php:464` — `array('number' => '2026.1.20')` satırından sonra):

```php
array('number' => '2026.1.21'),
```

**2.** Upgrade fonksiyonu ekle (dosya sonuna, `upgrade_to_2026_1_20()` bitişinden sonra):

```php
function upgrade_to_2026_1_21() {
    // Shared component library — reusable tree nodes referenced across styles.
    // name is UNIQUE; duplicate names are silently suffixed ([1], [2]...) at insert time.
    db("CREATE TABLE IF NOT EXISTS shared_components (
        id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(255) NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        tree_json   LONGTEXT NOT NULL DEFAULT '',
        tree_hash   VARCHAR(64) NOT NULL DEFAULT '',
        category    VARCHAR(100) NOT NULL DEFAULT '',
        created_by  INT UNSIGNED NOT NULL DEFAULT 0,
        created_at  INT UNSIGNED NOT NULL DEFAULT 0,
        updated_at  INT UNSIGNED NOT NULL DEFAULT 0,
        UNIQUE KEY uk_name (name),
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
```

> `IF NOT EXISTS` — upgrade tekrar çalıştırılsa güvenlidir.

---

## C. Backend (PHP)

### C.1 Endpoint'ler — `api.php`

**`apps.php` kullanılmaz** — dış erişime açık, API key doğrulaması istiyor.  
**`api.php`** kullanılır — session-based, JSON body, `validate_token()` + `USER_LOGGED_IN` kontrolü mevcut (`api.php:87–103`).

**Pattern** (`api.php` mevcut yapısı):
```php
// api.php giriş: $request = json_decode(file_get_contents('php://input'), true);
// switch ($action) { case 'xxx': validate_token(); $user = validate_user(); ... respond($response); }
// respond(): json_encode + exit
```

Yeni case: `'shared_component'` — `api.php`'deki `switch ($action)` bloğuna eklenir.

**Erişim kısıtlaması:** `api.php:95–102` global check `USER_ROLE > 1` ile designer (rol 2) ve üstünü zaten engelliyor. `shared_component` case'i bu global checkten **muaf tutulur** (exclusion listesine eklenir), ardından case içinde özel kontrol yapılır:

```php
// case başında:
validate_token();
$user = validate_user();
// Sadece admin (0) ve designer (2); manager (1) ve contributor (3+) erişemez
if ($user['role'] != 0 && $user['role'] != 2) {
    respond(['status' => 'error', 'message' => lang('Permission denied.')]);
}
```

**Sub-action routing:** `$request['sub_action']` ile dallanır.

| sub_action | İşlev | Zorunlu alanlar |
|---|---|---|
| `list` | id, name, description, updated_at listesi (tree_json YOK) | — |
| `get` | Tek kayıt + tree_json | `id` |
| `create` | Yeni kayıt | `name`, `tree_json` |
| `update` | tree_json güncelle | `id`, `tree_json` |
| `rename` | Sadece isim değiştir | `id`, `name` |
| `delete` | Sil (Phase 1: dependency check YOK — Phase 2'de eklenecek) | `id` |

**Naming policy — auto-suffix (`create` + `rename`):**

```php
function find_unique_shared_name($desired_name) {
    $base = trim($desired_name);
    $name = $base;
    $i = 1;
    while (db_value("SELECT COUNT(*) FROM shared_components WHERE name = '" . e($name) . "'") > 0) {
        $name = $base . ' [' . $i . ']';
        $i++;
    }
    return $name;
}
```

`create` ve `rename` her ikisinde de çağrılır; kullanıcıya uyarı gösterilmez, sonuç adı `response` içinde döner.

**Yanıt formatı** (`api.php` mevcut `respond()` fonksiyonu):
```php
respond(['status' => 'success', 'data' => [...]])
respond(['status' => 'error', 'message' => '...'])
```

---

### C.2 Render — `functions.php::_render_tree_node()`

Mevcut `switch ($type)` bloğu: `functions.php:17232`.  
Mevcut case'ler: `container`, `area`, `row`, `col`, `semantic`, `region`, `component`, `content`.

**`get_page_content.php` değişmez** — render zinciri `generate_style_code_from_tree()` → `_render_tree_node()` üzerinden geçiyor (`functions.php:17134`). Yeni case bu zincire entegre olur, başka değişiklik gerekmez.

Yeni case (satır ~17300, `default`'tan önce):

```php
case 'shared_ref':
    $shared_id = isset($props['sharedId']) ? (int)$props['sharedId'] : 0;
    if ($shared_id <= 0) break;
    if ($depth >= 8) {
        $html .= $pad . "<!-- shared_ref depth limit: id=$shared_id -->\n";
        break;
    }
    $sc_tree_json = db_value(
        "SELECT tree_json FROM shared_components WHERE id = '$shared_id' LIMIT 1"
    );
    if ($sc_tree_json) {
        $sc_node = json_decode($sc_tree_json, true);
        if ($sc_node) $html .= _render_tree_node($sc_node, $indent, $depth + 1);
    }
    // Silinen/bulunamayan ref: sessizce atlanır; HTML'e hiçbir şey yazılmaz
    break;
```

`_render_tree_node` imzası genişler:
```php
function _render_tree_node($node, $indent = 0, $depth = 0)
```

Mevcut özyinelemeli iç çağrılar (`functions.php:17237` vd.) `$depth + 1` ile güncellenir.

> **Region içinde shared:** `pregion`/`cregion`/`dregion` içerebilir — kısıtlama yok. Bu node tipler `_render_tree_node` içinde zaten region tag'e dönüşüyor (`case 'region'`, `functions.php:17289`); `shared_ref` expand'ı onların önünde çalışır, sorun olmaz.

> **Gelecek plan notu:** Kullanıcı `cregion` (Ortak Bölgeler) özelliğini shared component ile **değiştirmeyi** planlıyor. Bu, ileriki bir iterasyonda `cregion_designer_type = 'no'` kayıtlarının shared component'e migrate edilmesini içerecek. Planda şimdilik not olarak tutuldu; ayrı bir migration konusu.

---

### C.3 Opsiyonel: `view_shared_components.php` (Phase 2)

Style designer dışında basit tablo görünümü — rename/delete için.  
Yetki: `validate_area_access($user, 'designer')`.  
Tasarım menüsüne ekleme: `output_header` navigation bölümünde.

---

## D. Frontend — style_designer.js

### D.1 Dokunulacak fonksiyonlar (doğrulanmış satır numaraları)

| Fonksiyon | style_designer.js satırı | Eklenecek davranış |
|---|---|---|
| `buildNodeEl` | L3062 | `shared_ref` erken return → `buildSharedRefEl()` delegasyonu; canvas sarı outline |
| `buildTreeItems` | L9762 | `shared_ref` için `sd-tree-shared` class + puzzle ikonu + lock badge yok |
| `buildNodeHTML` | ~L9093 | `shared_ref` → cache'ten expand, recursive HTML |
| `proc()` | ~L14966 | aynı (Preview modal içi render) |
| `getNodeLabel` | ~L3450 | `shared_ref` → `"[Ortak] " + props.sharedName` |
| `canDrop` | ~L3976 | `shared_ref` → `component` gibi davransın (kendi içine drop kabul etmez) |
| Canvas ctx menu | L1307 handler → `showCanvasCtxMenu` L9689 | "Ortak bileşene dönüştür" + "Bağlantıyı Koy" menü öğeleri |
| Tree ctx menu | `buildTreeItems` L9807 (contextmenu listener) | aynı iki öğe |
| `renderProperties` | ~L10508 | `shared_ref` → read-only bilgi paneli + "Düzenle" butonu |

### D.2 Shared Component önbelleği (frontend)

`buildNodeEl` sync çalışmak zorunda — AJAX asenkron olduğu için canvas render'dan **önce** prefetch gerekir.

**Yükleme zamanı:** Style designer açılışında (init anında), tüm `shared_ref` ID'leri toplanır, tek seferde `list` + gerekirse `get` ile cache doldurulur. Sonraki render'lar sync çalışır.

```js
var _sharedCache = {};  // { id: { name, treeParsed: {node} } }

function collectSharedIds(node, acc) {
    acc = acc || {};
    if (node.type === 'shared_ref' && node.props.sharedId) acc[node.props.sharedId] = true;
    (node.children || []).forEach(function(c) { collectSharedIds(c, acc); });
    return acc;
}

function prefetchShared(ids, cb) {
    var pending = Object.keys(ids).filter(function(id) { return !_sharedCache[id]; });
    if (!pending.length) { cb(); return; }
    var done = 0;
    pending.forEach(function(id) {
        // AJAX GET api.php { action:'shared_component', sub_action:'get', id:N }
        // → _sharedCache[id] = { name: data.name, treeParsed: JSON.parse(data.tree_json) }
        // → if (++done === pending.length) cb();
    });
}
```

`StyleDesigner.init()` sırasında, tree'deki mevcut `shared_ref` ID'leri `prefetchShared` ile yüklenir, tamamlanınca `render()` çağrılır.

### D.3 Palette "Ortak" sekmesi

**Konum:** `compOuter.innerHTML` bloğu, `style_designer.js:7847`.

Mevcut:
```js
'<button type="button" class="sd-panel-tab" data-tab="system">Sistem</button>'
```

Hemen sonrasına:
```js
'<button type="button" class="sd-panel-tab" data-tab="shared">Ortak</button>'
```

Yeni pane (`sd-tab-system` div'inden sonra):
```js
'<div id="sd-tab-shared" style="display:none" class="sd-tab-pane">' +
    '<div id="sd-shared-list" class="sd-comp-items-scroll"></div>' +
'</div>'
```

Mevcut tab-switching kodu (`.sd-panel-tab` click handler, `style_designer.js:~8105`) zaten `data-tab` attribute ile ilgili pane'i gösterip diğerlerini gizliyor — yeni sekme için ek kod gerekmez; sadece `sd-tab-shared` pane var olmalı.

**Sekme ilk açıldığında:** `loadSharedTab()` çağrılır (lazy). AJAX `apps.php?action=shared_components&sub_action=list` → her kayıt için:

```js
// Her palette item:
// <div class="sd-comp-item sd-shared-item" draggable="true">
//   <span class="bi bi-puzzle-fill sd-shared-badge"></span>
//   <span class="sd-comp-label">Navbar</span>
//   <button class="sd-shared-opts bi bi-three-dots-vertical"></button>
// </div>
```

**Drag source:** `dragstart` event'inde:
```js
e.dataTransfer.setData('application/sd-node', JSON.stringify({
    source: 'palette', type: 'shared_ref',
    extra: { sharedId: id, sharedName: name }
}));
```

Mevcut drop handler `source:'palette'` + `type:'shared_ref'` için yeni case:
```js
case 'shared_ref':
    newNode = { _id: gid(), type: 'shared_ref',
                props: { sharedId: extra.sharedId, sharedName: extra.sharedName },
                children: [] };
    break;
```

### D.4 Sağ tık: "Ortak Bileşene Dönüştür"

**Canvas ctx menu** (`showCanvasCtxMenu` L9689) ve **tree ctx menu** (`buildTreeItems` L9807) — her ikisine de eklenir.

Mevcut son öğe "Kilitle/Kilidi Aç" (L9758). Hemen altına:

```js
{ sep: true },
{ icon: 'bi-puzzle-fill',
  label: 'Ortak Bileşene Dönüştür',
  disabled: node.type === 'root' || node.type === 'shared_ref' || multi,
  handler: function() { convertToShared(node); }
},
```

`shared_ref` seçiliyken farklı menü:
```js
{ icon: 'bi-box-arrow-up-right',
  label: 'Bağlantıyı Kes (Detach)',
  handler: function() { detachShared(node); }
},
{ icon: 'bi-pencil-square',
  label: 'Ortak Bileşeni Düzenle',
  handler: function() { openSharedEditor(node.props.sharedId); }
},
```

**`convertToShared(node)` akışı:**
1. `prompt()` → isim al (boşsa iptal)
2. POST `apps.php` `create` → `{ id: N }` döner
3. `saveState()`
4. Tree'de `node`'u `{ _id: gid(), type:'shared_ref', props:{ sharedId:N, sharedName:name }, children:[] }` ile replace et
5. `_sharedCache[N] = { name, treeParsed: originalNode }` (anında cache'e al)
6. `render(); loadSharedTab();`

**`detachShared(node)` akışı:**
1. `_sharedCache[node.props.sharedId]` var mı kontrol et; yoksa fetch
2. `saveState()`
3. Cache'teki `treeParsed`'i deep-clone + `reId()` ile yeni `_id`'ler ver
4. Tree'de `shared_ref` node'unu clone ile replace et
5. `render()`

### D.5 Düzenleme stratejisi — **Seçenek B (Inline Edit) — KARAR**

**Karar:** Shared component, kullanıldığı herhangi bir stilden **inline** düzenlenir. Ayrı editör penceresi yok, double-click izole mod yok.

**Mekanizma:**

Canvas'ta `shared_ref` node expand edilmiş hâlde görünür — normal node gibi. Kullanıcı içindeki bir elementi seçip değiştirdiğinde, o değişiklik `_sharedCache[id].treeParsed`'a yazılır (stil tree'sinde `shared_ref` node korunur, expand edilmiş kopya değişmez).

```
Stil tree (kalıcı):   [ ..., { type:'shared_ref', props:{sharedId:42} }, ... ]
                                           ↓ canvas render zamanı expand
Canvas'ta görünen:    [ ..., { type:'section', ... expanded content ... }, ... ]
                                           ↓ kullanıcı düzenler
_sharedCache[42]:     { treeParsed: { type:'section', ... güncellenmiş ... } }
```

Stil kaydedildiğinde **iki yazma** gerçekleşir (atomic değil):
1. `style.style_tree_json` güncellenir (shared_ref node ID'si korunur)
2. `shared_components.tree_json` güncellenir (cache'teki güncel treeParsed ile)

Adım 2 başarısız olursa stil yine kaydedilir; shared kayıt bir sonraki kayıtta güncellenir. (Phase 4: transaction veya retry.)

**Save flow implementasyonu (`save` butonu handler'ı):**

```js
function saveStyle() {
    // 1. Stil tree'sindeki shared_ref nodelarını bul, cache'teki güncel treeParsed'ı topla
    var sharedUpdates = [];
    collectSharedIds(tree, {});  // id listesi
    Object.keys(_sharedCache).forEach(function(id) {
        if (_sharedCache[id]._dirty) {
            sharedUpdates.push({ id: id, tree_json: JSON.stringify(_sharedCache[id].treeParsed) });
        }
    });

    // 2. Önce shared_components güncelle (ripple effect için önce kaynak)
    function doSharedUpdates(idx, cb) {
        if (idx >= sharedUpdates.length) { cb(); return; }
        // POST api.php { action:'shared_component', sub_action:'update', id, tree_json }
        // → doSharedUpdates(idx+1, cb)
    }

    // 3. Sonra stili kaydet (mevcut save mantığı)
    doSharedUpdates(0, function() { submitStyleForm(); });
}
```

`_dirty` flag: `buildSharedRefEl` içindeki bir node değiştiğinde `_sharedCache[id]._dirty = true` set edilir.

**Undo/redo notu:** Stil undo'su shared değişikliklerini de geri alır (her ikisi de `_sharedCache` ve tree üzerinde çalışır). Bu Phase 1'de kabul edilebilir; Phase 4'te scope ayrımı değerlendirilebilir.

**Concurrency (Phase 4):** Aynı shared component'i iki farklı editörde aynı anda düzenlenirse last-write-wins. Phase 4'te `updated_at` karşılaştırması ile uyarı eklenecek.

### D.6 Canvas'ta `shared_ref` görünümü

**Designer modunda:** Normal HTML render + **yeşil outline** (`sd-shared-wrap` CSS class). İçerik tam görünür, placeholder yok.

**Preview/export modunda:** Outline yok, tamamen normal HTML — `shared_ref` ile normal node arasında görsel fark kalmaz.

Kullanıcı outline rengiyle "bu paylaşımlı" bilgisini edinir; canvas'ta düzenleme tıpkı normal element gibi çalışır.

`buildSharedRefEl` — canvas wrap:
```js
wrapper.className = 'sd-wrap sd-shared-wrap';
// İçerik: _sharedCache[id].treeParsed üzerinden buildNodeEl() — normal expand
// Toolbar'da sadece yeşil puzzle rozeti (isim)
// sd-selected, drag handle gibi normal wrapper özellikleri korunur
```

### D.7 `buildSharedRefEl` — canvas wrapper detayı

```js
function buildSharedRefEl(node) {
    var doc = canvasDoc;
    var wrapper = doc.createElement('div');
    wrapper.className = 'sd-wrap sd-shared-wrap';
    wrapper.setAttribute('data-sd-id', node._id);
    wrapper.setAttribute('data-sd-type', 'shared_ref');
    wrapper.setAttribute('data-sd-shared-id', node.props.sharedId);
    if (selectedNode && selectedNode._id === node._id) wrapper.classList.add('sd-selected');

    var cached = _sharedCache[node.props.sharedId];
    if (cached && cached.treeParsed) {
        // Normal expand — içerik tam render, placeholder yok
        var inner = buildNodeEl(cached.treeParsed);
        if (inner) wrapper.appendChild(inner);
    }
    // Cache yoksa: init prefetch tamamlanmamış demektir; render() tekrar çağrılacak

    // Toolbar: sadece yeşil puzzle badge (isim)
    var tb = doc.createElement('div');
    tb.className = 'sd-tb';
    var tbName = doc.createElement('span');
    tbName.className = 'sd-tb-name sd-shared-tb-name';
    tbName.innerHTML = '<span class="bi bi-puzzle-fill"></span> ' +
        h(node.props.sharedName || '#' + node.props.sharedId);
    tb.appendChild(tbName);
    wrapper.insertBefore(tb, wrapper.firstChild);

    setupDropEvents(wrapper, node);
    return wrapper;
}
```

**Önemli:** `buildSharedRefEl` içindeki `buildNodeEl(cached.treeParsed)` çağrısı, içerik node'larını normal node gibi canvas'a ekler. O node'lardaki herhangi bir değişiklik (property panel, drag vs.) `_sharedCache[id].treeParsed`'ı mutate eder ve `_dirty = true` set edilir.

### D.7 `buildTreeItems` — tree paneli `shared_ref` görünümü

`buildTreeItems` fonksiyonu (`style_designer.js:9762`) node tiplerine göre ikon ve badge atayor. `shared_ref` için:

```js
if (node.type === 'shared_ref') {
    icon = '<span class="bi bi-puzzle-fill"></span>';
    li.classList.add('sd-tree-shared');
    // lockBadge gösterilmez (shared_ref kendisi kilitli sayılmaz; düzenleme farklı akış)
}
```

`getNodeLabel` içinde:
```js
if (node.type === 'shared_ref') return '[Ortak] ' + (node.props.sharedName || '#' + node.props.sharedId);
```

---

## E. Kilit Outline Renk Şeması Revizyonu

Renk şeması iki dosyada tanımlı: **tree paneli** için `style_designer.css`, **canvas iframe outline** için `style_designer.js` içindeki enjekte CSS bloğu.

### Mevcut durum

**`style_designer.css` — tree paneli (doğrulanmış satırlar):**

| CSS class | Satır | Renk | Anlam |
|---|---|---|---|
| `.sd-tree-lock-badge` | css:2251–2254 | `#f0c040` (sarı) | Ağaç paneli kilit rozeti |
| `.sd-tree-locked .sd-tree-lbl` | css:2257 | `#f0c040` (sarı) | Kilitli node etiketi |
| `.sd-tree-locked-desc .sd-tree-lbl` | css:2260 | `rgba(240,192,64,.6)` | Kilitli altındaki etiket |

**`style_designer.js` — canvas iframe enjekte CSS (~L1113+):**

```
.sd-locked          → outline: 2px dashed rgba(255,180,0,.7)   [sarı/amber]
.sd-wrap-locked-desc→ outline: 1px dashed rgba(255,180,0,.3)   [açık sarı]
```

**`style_designer.js:3082–3083` — class ataması (`buildNodeEl`):**
```js
if (node.props._locked) wrapper.classList.add('sd-locked');
else if (hasLockedAncestor(node)) wrapper.classList.add('sd-wrap-locked-desc');
```

### Yeni renk şeması — KARAR

| Kavram | Eski renk | Yeni renk | Değişecek yer |
|---|---|---|---|
| Kilitli element (tree badge) | `#f0c040` sarı | **`#f07820` turuncu** | `style_designer.css:2252, 2257` |
| Kilitli alt-node (tree) | `rgba(240,192,64,.6)` | `rgba(240,120,32,.6)` | `style_designer.css:2260` |
| Kilitli element (canvas outline) | `rgba(255,180,0,.7)` amber | `rgba(255,120,0,.8)` turuncu | `style_designer.js` ~L1113 |
| Kilitli alt-node (canvas outline) | `rgba(255,180,0,.3)` | `rgba(255,120,0,.3)` | `style_designer.js` ~L1115 |
| **Shared component (YENİ)** | — | **`#22c55e` yeşil** | `style_designer.css` YENİ + JS YENİ |

**Shared renk seçimi:** `#22c55e` (Tailwind green-500 — canlı, mavi/turuncu ile çakışmaz, "canlı/bağlı" algısı verir). Semi-transparent varyantlar: `rgba(34,197,94,.7)` outline, `rgba(34,197,94,.12)` background.

> **Sarı renk (`#f0c040`) not:** Kilit turuncu'ya taşındı; shared yeşil oldu. Sarı artık boşta — ileride başka bir durum (örn. "taslak", "uyarı", "onay bekliyor") için ayrılabilir. Şimdilik atanmamış.

> **"Kilitli içerik" kırmızı:** `sd-lock-content` class'ı codebase'de yok (`style_designer.css:2251–2261` grep'inde rastlanmadı). Bu özellik henüz uygulanmamış; kırmızı renk o zaman kullanılacak. Mevcut `#e05c5c` yalnızca `sd-recent-clear-btn:hover` ve validate uyarısı için — çakışma yok.

### Yeni CSS sınıfları (style_designer.css'e eklenir)

```css
/* Shared component — tree panel */
.sd-tree-shared .sd-tree-lbl  { color: #22c55e; }
.sd-tree-shared-badge {
    font-size: .68rem; color: #22c55e; opacity: .95;
    margin-left: 5px; flex-shrink: 0; line-height: 1;
}

/* Shared component — canvas iframe enjekte CSS bloğuna eklenir (~L1113 JS bloğu) */
/* .sd-shared-wrap outline: designer modda görünür; preview'da CSS enjekte edilmez */
.sd-shared-wrap              { outline: 2px solid rgba(34,197,94,.6); }
.sd-shared-wrap.sd-selected  { outline: 2px solid #22c55e; }
.sd-shared-tb-name           { color: #22c55e !important; }
```

Note: `sd-shared-placeholder` class'ı artık kullanılmıyor (D.6 kararı — placeholder yok, içerik normal render). Class tanımına gerek yok.

---

## F. Edge Case'ler

### F.1 Nested Shared (shared içinde shared)

**Durum (2026.4.8):** açıldı. Ortak bileşen içinde ortak bileşen ve sistem
widget'ı serbest; döngü kaydedilmeden reddedilir (`canDrop()`,
`_validateTree()`, `pg_shared_component_cycle()`), çizimde
`_expand_shared_refs()` ata kümesi ve editörde `_sdSharedOpen` döngüye karşı
korur. Ayrıntı: `docs/degisiklikler.md`, 2026.4.8. Aşağısı Faz 1 kaydıdır.

Phase 1'de **kısıtlanır:** `shared_ref` node'u drop target kabul etmez (JS'de `canDrop` → false). "Ortak Bileşene Dönüştür" menüsü de `shared_ref` içindeki bir node seçiliyken disabled kalır. Böylece nested ve döngüsel referans imkânsız hâle gelir.

Server-side'da yine de derinlik guard'ı eklenir (kötü veri olasılığına karşı):

```php
function _render_tree_node($node, $indent = 0, $depth = 0) {
    if ($depth > 8) return $pad . "<!-- max shared_ref depth -->\n";
    ...
    case 'shared_ref':
        $html .= _render_tree_node($sc_node, $indent, $depth + 1);
```

Phase 4'te nested shared desteklenirse döngü için `$visited = []` guard eklenir:

```php
case 'shared_ref':
    if (in_array($shared_id, $visited)) {
        $html .= $pad . "<!-- circular shared_ref: id=$shared_id -->\n"; break;
    }
    $visited[] = $shared_id;
    $html .= _render_tree_node($sc_node, $indent, $depth + 1, $visited);
```

### F.2 Döngüsel Referans

Phase 1'de `shared_ref` drop kısıtlaması ile önlenir.  
Phase 4'te `create`/`update` sırasında sunucu tarafında traverse: yeni tree_json içinde `sharedId` taranır, kaydedilecek component'in kendi ID'sine ulaşılırsa `api_error()`.

### F.3 Silme → Dependency Check (Phase 2)

Phase 1'de `delete` doğrudan siler (dependency check YOK — hız için kabul edildi).  
Phase 2'de akış:
1. `delete` POST → LIKE sorgusu → `used_in: [{style_id, style_name}, ...]`
2. `used_in.length > 0` → `{status:'error', count: N, used_in: [...]}` döner
3. Frontend: `"Bu bileşen 3 stilde kullanılıyor. Yine de silmek istiyor musunuz?"` onay diyaloğu
4. Onay → `force=1` ile tekrar POST → silinir
5. Dangling `shared_ref` node'lar render'da sessizce atlanır (sayfa görünümü bozulmaz)

### F.4 Rename → Referans Bütünlüğü

`shared_components.name` güncellenir, stile ait tree JSON'daki `props.sharedName` **güncellenmez** (denormalize).  
`sharedName` yalnızca editör badge'i içindir; `sharedId` referans bağını taşır.  
Stale `sharedName` → "Ortak" sekmesi yenilenince veya stil sonraki açılışında güncellenir.  
DB'de cascade güncelleme gerekmez.

### F.5 Dangling Ref (Silinen Component)

Render'da: `db_value()` NULL döner → `case 'shared_ref'` sessizce `break` → HTML'de hiçbir şey üretilmez.  
Canvas'ta: `_sharedCache[id]` boş → `sd-shared-placeholder` "Silinmiş Bileşen #N" gösterir.

### F.6 Versioning / Concurrency

Phase 1–3: last-write-wins. `updated_at` değişiklik öncesi Properties panelinde gösterilebilir.  
Phase 4: `tree_hash` + ETag header ile optimistic lock; çakışmada `409 Conflict` → kullanıcıya "Başkası bu bileşeni değiştirdi, yenile" uyarısı.

### F.7 Export / Site Taşıma (Phase 3)

**Karar:** Export sırasında `shared_ref` node içerik kopyası olarak taşınır (ID değil). Hedefte yeni `shared_components` kaydı oluşturulur (aynı isimle veya prefix ile).

Phase 1–2'de "basit duplicate" yeterli: hedef sitede yeni kayıt, canlı link yok. Phase 3'te export/import akışı detaylandırılacak.

### F.8 `find_and_replace.php` Entegrasyonu

`shared_components.tree_json` içindeki metin içerikleri (link href, label, vb.) bul-değiştir kapsamına alınabilir.  
`find_and_replace.php`'deki `$all_table_defs` dizisine eklenecek kayıt:

```php
[
    'label'       => lang('Shared Components'),
    'table'       => 'shared_components',
    'id_column'   => 'id',
    'name_column' => 'name',
    'columns'     => ['tree_json'],
    'edit_url'    => 'edit_shared_component.php?id={id}',
    'url_raw'     => false,
],
```

Phase 3'te uygulanır.

---

## G. Implementation Phasing — GÜNCEL

### Phase 1 — CRUD + Inline Edit + Render (Tam MVP)

Teslim kriteri: Node → "Ortak Bileşene Dönüştür" → palette'e eklenir → başka stile sürükle-bırak → inline düzenle → stil kaydedince shared kayıt güncellenir → tüm stillerde yayılır. Rename + delete de Phase 1'de.

### Phase 2 — Safety & Management

- Silme dependency check (N stilde kullanılıyor uyarısı + force)
- `usage` endpoint
- `view_shared_components.php` — liste yönetim sayfası
- `find_and_replace.php` entegrasyonu

### Phase 3 — Export / Transfer

- Stil export'unda `shared_ref` → içerik kopyası
- Import'ta yeni shared_components kaydı oluşturma
- Site taşıma akışı

### Phase 4 — Concurrency / Versioning / Advanced

- `tree_hash` (SHA-256) + ETag
- Concurrency uyarısı (`updated_at` karşılaştırma)
- Nested shared_ref + döngü guard
- Category/tag sistemi
- Undo scope ayrımı (stil undo vs shared undo)

---

## H. Dosya Etki Haritası — GÜNCEL

> `get_page_content.php` ve `apps.php` değişmez.  
> `api.php` exclusion list + yeni case alır.

| Dosya | Eklenecek / Değişecek | Phase |
|---|---|---|
| `install/index.php` | `$versions` + `upgrade_to_2026_1_21()` — `shared_components` CREATE TABLE | 1 |
| `functions.php` | `_render_tree_node()`: `shared_ref` case + `$depth` param (~L17300) | 1 |
| `api.php` | Exclusion listesine `shared_component` ekleme (~L47–84) | 1 |
| `api.php` | `case 'shared_component'`: list/get/create/update/rename/delete + `find_unique_shared_name()` | 1 |
| `assets/js/style_designer.js` | `_sharedCache`, `collectSharedIds()`, `prefetchShared()` | 1 |
| `assets/js/style_designer.js` | `StyleDesigner.init()`: prefetch → render zinciri | 1 |
| `assets/js/style_designer.js` | `buildNodeEl` (L3062): `shared_ref` → `buildSharedRefEl()` delegation | 1 |
| `assets/js/style_designer.js` | `buildSharedRefEl()` — yeni fonksiyon | 1 |
| `assets/js/style_designer.js` | `buildTreeItems` (L9762): `shared_ref` ikon + `sd-tree-shared` class | 1 |
| `assets/js/style_designer.js` | `getNodeLabel`: `shared_ref` display etiketi | 1 |
| `assets/js/style_designer.js` | Palette HTML (L7847): "Ortak" sekme + pane | 1 |
| `assets/js/style_designer.js` | `loadSharedTab()` + liste render | 1 |
| `assets/js/style_designer.js` | Drop handler: `shared_ref` node oluşturma | 1 |
| `assets/js/style_designer.js` | `showCanvasCtxMenu` (L9689): "Ortak Bileşene Dönüştür" + "Bağlantıyı Kes" | 1 |
| `assets/js/style_designer.js` | Tree ctx menu (L9807): aynı öğeler | 1 |
| `assets/js/style_designer.js` | `convertToShared(node)` + `detachShared(node)` — yeni fonksiyonlar | 1 |
| `assets/js/style_designer.js` | `canDrop`: `shared_ref` için nested drop engeli | 1 |
| `assets/js/style_designer.js` | `saveStyle()` (veya submit handler): shared dirty flush | 1 |
| `assets/js/style_designer.js` | Canvas kilit outline (~L1113): amber → turuncu | 1 |
| `assets/js/style_designer.js` | Canvas shared outline CSS inject — `.sd-shared-wrap` yeşil | 1 |
| `assets/js/style_designer.js` | `buildNodeHTML` + `proc()`: `shared_ref` case (HTML export) | 1 |
| `assets/js/style_designer.js` | `renderProperties`: `shared_ref` info paneli | 1 |
| `assets/css/style_designer.css` | `.sd-tree-lock-badge` → `#f07820` (L2252) | 1 |
| `assets/css/style_designer.css` | `.sd-tree-locked .sd-tree-lbl` → `#f07820` (L2257) | 1 |
| `assets/css/style_designer.css` | `.sd-tree-locked-desc .sd-tree-lbl` → `rgba(240,120,32,.6)` (L2260) | 1 |
| `assets/css/style_designer.css` | Yeni: `.sd-tree-shared`, `.sd-tree-shared-badge`, `.sd-shared-wrap`, `.sd-shared-tb-name` | 1 |
| `includes/local/tr.json` | Yeni string'ler (aşağıda liste) | 1+ |
| `view_shared_components.php` | YENİ — liste/yönetim sayfası | 2 |
| `find_and_replace.php` | `$all_table_defs`'e `shared_components` kaydı | 2 |

### tr.json — Phase 1 String'leri

```json
"Shared Components":          "Ortak Bileşenler",
"Shared":                     "Ortak",
"Convert to Shared Component":"Ortak Bileşene Dönüştür",
"Detach":                     "Bağlantıyı Kes",
"Shared component name:":     "Ortak bileşen adı:",
"[Shared] {var}":             "[Ortak] {var}",
"Delete anyway?":             "Yine de silinsin mi?",
"Rename":                     "Yeniden Adlandır"
```

---

## I. Alınan Kararlar (Özet)

| # | Konu | Karar |
|---|---|---|
| 1 | Düzenleme UX | **Seçenek B — Inline edit.** Stil kaydedince shared_components da güncellenir. Ayrı editör penceresi yok. |
| 2 | Renkler | Kilit: **`#f07820` turuncu**; Shared: **`#22c55e` yeşil**; Sarı (`#f0c040`) ileride başka kullanım için boşta. |
| 3 | Canvas preview | Normal HTML render (placeholder yok). Designer modunda sadece yeşil outline. |
| 4 | Export | Phase 3. MVP'de basit ID+içerik kopyası. Hedefte yeni shared_components kaydı. |
| 5 | Erişim | Sadece admin (0) + designer (2). Manager (1) erişemez. |
| 6 | Region in shared | Kısıtlama yok — `pregion`/`cregion`/`dregion` içerebilir. |
| 6b | Gelecek plan | Kullanıcı cregion'ı shared component ile değiştirmeyi planlıyor (ayrı iteration). |
| 7 | İsim benzersizliği | DB'de `UNIQUE KEY`. Çakışmada sessiz `[1]`/`[2]` suffix — hata/uyarı yok. |
| 8 | Phase 1 kapsamı | CRUD + rename + delete + inline edit **hepsi Phase 1**. |
| 9 | Endpoint | **`api.php`** — session-based, JSON body, token. `apps.php` kullanılmaz. |

---

## J. Phase 1 Implementation Checklist

Sıra, implementasyon bağımlılık sırasına göre düzenlenmiştir.

### 1 — DB Migration
- [ ] `install/index.php`: `$versions` dizisine `array('number' => '2026.1.21')` ekle (L464 sonrası)
- [ ] `install/index.php`: `upgrade_to_2026_1_21()` fonksiyonu ekle — `shared_components` CREATE TABLE + `UNIQUE KEY uk_name` + `INDEX idx_updated`
- [ ] Tahmini etki: **~20 satır** (`install/index.php`)

### 2 — Backend: Render
- [ ] `functions.php`: `_render_tree_node($node, $indent, $depth)` imzasına `$depth = 0` ekle (L17222)
- [ ] `functions.php`: Mevcut özyinelemeli çağrılara `$depth + 1` geç (~L17237, 17245, 17255, 17263, 17284)
- [ ] `functions.php`: `case 'shared_ref':` bloğu ekle (~L17300) — db_value + decode + recursive call + depth guard
- [ ] Tahmini etki: **~25 satır** (`functions.php`)

### 3 — Backend: API Endpoint
- [ ] `api.php`: `'shared_component'`'ı exclusion listesine ekle (~L47–84, mevcut `and ($action != 'xxx')` zinciri)
- [ ] `api.php`: `case 'shared_component':` bloğu ekle — `validate_token()`, `validate_user()`, rol check (0 ve 2), `switch ($sub_action)`
- [ ] `api.php` — `list`: `SELECT id, name, description, category, updated_at FROM shared_components ORDER BY name`
- [ ] `api.php` — `get`: `SELECT * FROM shared_components WHERE id = N`
- [ ] `api.php` — `create`: `find_unique_shared_name()` + INSERT + `respond(['status'=>'success','id'=>$id,'name'=>$final_name])`
- [ ] `api.php` — `update`: `UPDATE ... SET tree_json, updated_at WHERE id = N`
- [ ] `api.php` — `rename`: `find_unique_shared_name()` + `UPDATE ... SET name, updated_at WHERE id = N`
- [ ] `api.php` — `delete`: `DELETE FROM shared_components WHERE id = N` (Phase 1: dependency check yok)
- [ ] `api.php`: `find_unique_shared_name($desired)` yardımcı fonksiyonu (case dışında veya functions.php'ye)
- [ ] Tahmini etki: **~80 satır** (`api.php`)

### 4 — Frontend: Cache ve Init
- [ ] `style_designer.js`: `var _sharedCache = {}` global tanım
- [ ] `style_designer.js`: `collectSharedIds(node, acc)` fonksiyonu
- [ ] `style_designer.js`: `prefetchShared(ids, cb)` fonksiyonu — `api.php` GET çağrısı
- [ ] `style_designer.js`: `StyleDesigner.init()` içinde prefetch zinciri → `render()` (mevcut init akışına entegre)
- [ ] Tahmini etki: **~40 satır** (`style_designer.js`)

### 5 — Frontend: buildNodeEl + buildSharedRefEl
- [ ] `style_designer.js`: `buildNodeEl` (L3062) başına `shared_ref` early return → `buildSharedRefEl(node)`
- [ ] `style_designer.js`: `buildSharedRefEl(node)` yeni fonksiyon — wrapper, `data-sd-shared-id`, expand cache, toolbar badge, `setupDropEvents`
- [ ] `style_designer.js`: `canDrop`: `shared_ref` → `targetNode.type === 'shared_ref'` için `false` döndür (nested engeli)
- [ ] Tahmini etki: **~55 satır** (`style_designer.js`)

### 6 — Frontend: Tree Panel
- [ ] `style_designer.js`: `buildTreeItems` (L9762) — `shared_ref` için ikon `bi-puzzle-fill` + `li.classList.add('sd-tree-shared')`
- [ ] `style_designer.js`: `getNodeLabel` — `shared_ref` → `'[Ortak] ' + sharedName`
- [ ] Tahmini etki: **~10 satır** (`style_designer.js`)

### 7 — Frontend: Palette "Ortak" Sekmesi
- [ ] `style_designer.js`: Palette HTML (L7851) — "Sistem" butonundan sonra "Ortak" butonu
- [ ] `style_designer.js`: Yeni pane `id="sd-tab-shared"` — `sd-tab-system`'den sonra
- [ ] `style_designer.js`: `loadSharedTab()` fonksiyonu — AJAX `list`, her item için `draggable` div render
- [ ] `style_designer.js`: Tab switch handler — "Ortak" sekmesi tıklandığında `loadSharedTab()` (lazy, ilk açılışta)
- [ ] `style_designer.js`: Drag source setup — `dragstart` event + `application/sd-node` `shared_ref` payload
- [ ] `style_designer.js`: Drop handler — `shared_ref` node oluşturma case
- [ ] Tahmini etki: **~70 satır** (`style_designer.js`)

### 8 — Frontend: Sağ Tık Menüsü
- [ ] `style_designer.js`: `showCanvasCtxMenu` (L9758 sonrası) — separator + "Ortak Bileşene Dönüştür" öğesi; `disabled` when `root` || `shared_ref` || `multi`
- [ ] `style_designer.js`: Tree ctx menu (L9807 contextmenu handler) — aynı öğe
- [ ] `style_designer.js`: `shared_ref` seçiliyken ek öğe: "Bağlantıyı Kes"
- [ ] `style_designer.js`: `convertToShared(node)` fonksiyonu — prompt → API create → node replace → cache güncelle → render + palette refresh
- [ ] `style_designer.js`: `detachShared(node)` fonksiyonu — cache clone → reId → tree replace → render
- [ ] Tahmini etki: **~60 satır** (`style_designer.js`)

### 9 — Frontend: Inline Edit Save Flow
- [ ] `style_designer.js`: `_dirty` flag mekanizması — shared içindeki bir node değiştiğinde `_sharedCache[id]._dirty = true` set edilir (property change, drag vs. event'lerinden tetiklenir)
- [ ] `style_designer.js`: Save submit handler'a `shared dirty flush` adımı ekle — `doSharedUpdates()` → `submitStyleForm()`
- [ ] `style_designer.js`: `buildNodeHTML` + `proc()` — `shared_ref` case (cache expand → recursive HTML)
- [ ] `style_designer.js`: `renderProperties` — `shared_ref` seçiliyken read-only info paneli (isim + sharedId)
- [ ] Tahmini etki: **~50 satır** (`style_designer.js`)

### 10 — CSS: Renk Değişiklikleri
- [ ] `style_designer.css` L2252: `.sd-tree-lock-badge` `color: #f0c040` → `#f07820`
- [ ] `style_designer.css` L2257: `.sd-tree-locked .sd-tree-lbl` `color: #f0c040` → `#f07820`
- [ ] `style_designer.css` L2260: `.sd-tree-locked-desc .sd-tree-lbl` `rgba(240,192,64,.6)` → `rgba(240,120,32,.6)`
- [ ] `style_designer.js` ~L1113: Canvas inject CSS — `.sd-locked` outline amber → turuncu; yeni `.sd-shared-wrap` yeşil outline
- [ ] `style_designer.css`: Yeni sınıflar ekle — `.sd-tree-shared`, `.sd-tree-shared-badge`, `.sd-shared-wrap`, `.sd-shared-tb-name`
- [ ] Tahmini etki: **3 satır değişiklik** (css) + **~15 satır ekleme** (css) + **~8 satır değişiklik** (js inject bloğu)

### 11 — tr.json
- [ ] `includes/local/tr.json`: Phase 1 string'lerini ekle (bkz. H bölümü — 8 kayıt)
- [ ] Tahmini etki: **~8 satır** (`tr.json`)

---

**Phase 1 toplam tahmini etki:**  
`install/index.php` ~20 + `functions.php` ~25 + `api.php` ~80 + `style_designer.js` ~285 + `style_designer.css` ~18 + `tr.json` ~8 = **~436 satır** (değişiklik + ekleme)
