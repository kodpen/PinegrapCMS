# Varyant fiyat kuralları — nitelik seçeneğinden otomatik varyant fiyatı

Durum: **uygulandı** (2026-10-04). Ayrıntı ve doğrulama: `docs/degisiklikler.md`
"Varyant fiyat kuralları". Uygulamada plandan ayrılan yerler bölüm 6'da
işaretli.
Kararlar (Erdal, 2026-10-04):

- Yüzde farklar **çarparak** birleşir.
- Kurallar yalnız **ürün eklenirken** (varyant seti kurulurken) çalışır.
  Sonradan toplu varyant düzenleme bu planın kapsamında değil (bölüm 8).
- Kurallar sette saklanır (bölüm 5) — "çok büyük bir değişiklik değilse yapalım".

## 1. Bugünkü durum

- Varyant tablosu yok: her varyant `products` içinde kendi `price` (kuruş) satırı;
  set `product_groups` (`display_type = 'select'`), eksenler
  `product_groups_attributes_xref`, satırın seçimleri `products_attributes_xref`.
- Varyantlar yalnız **ekleme anında** ayarlanıyor: yeni ürün ekranındaki matris
  (`assets/js/product_builder.js`, `variantRow()`) her satıra `#price`'ı
  kopyalıyor; tek araç "fiyatı hepsine uygula" (`#pg_pb_apply_price`).
  4 asorti × 4 renk × 3 malzeme = 48 satır elle doluyor.
- Sonrası: her varyant kendi ürün ekranından (`edit_product.php`) düzenleniyor;
  set, ürün grubu ekranından (`edit_product_group.php`: üyeler, sıra, eksenler)
  yönetiliyor. Toplu fiyat değişikliği oradaki "Seçilileri değiştir" →
  `edit_products.php` ile yapılıyor: seçili ürünlere aynı ± tutar ya da ± %.
- Fiyatı okuyan her yer `products.price`: sepet, sipariş, `/products` API'si
  (`api_money()`), pazaryeri senkronu (`includes/api/outbound/`), ERP faturası,
  teklifler.

## 2. Hedef ve temel karar

Operatör fiyatı **nitelik seçeneğine** bir kez girer (Asorti A = 160 ₺, Beyaz
+%20, Deri +%50); matristeki her varyantın fiyatı bundan hesaplanır. 48 fiyat
yerine 7 değer.

**Kural fiyatı hesaplar, fiyatın yerine geçmez.** Motor ekleme ekranında
çalışır, sonucu her varyantın `products.price`'ına yazar. Sepet, sipariş, API,
pazaryeri, ERP, varyantın kendi ekranı ve grup ekranı **hiç değişmez**; kural
girilmeyen ekleme bugünkü gibi davranır. Fiyatı çalışma anında (sepette)
hesaplamak, fiyat okuyan her yeri değiştirmek demekti — reddedildi.

## 3. Kural türleri

Her nitelik kartında **Fiyata etkisi** seçimi (nitelik başına bir tür, değer
seçenek başına):

| Tür | Seçenek değeri | Boş bırakılırsa | Örnek |
|---|---|---|---|
| Yok (varsayılan) | — | — | — |
| Fiyatı belirler | tutar (₺) | ana fiyat kullanılır | Asorti: A 160, B 200, C 240, D 240 |
| Çarpan | katsayı (> 0) | ×1 | ana fiyat 20 ₺/çift; A ×8, B ×10, C ×12, D ×12 |
| Fark | ± tutar **veya** ± % (seçenek başına birim seçilir) | 0 | Renk: yalnız Beyaz +%20 · Malzeme: Deri +%50, Süet +%50 |

Kısıtlar:

- Bir sette en fazla **bir** nitelik "Fiyatı belirler" olabilir (iki taban
  çelişir). Arayüz ikinciyi seçtirmez, sunucu da reddeder.
- Birden fazla "Çarpan" serbest (çarpılırlar: asorti × koli adedi).
- Negatif fark serbest (indirim). Sonuç 0'ın altına düşerse 0'a kırpılır ve
  satırda uyarı çıkar. Yüzde en az −100.
- Mevcut ayrıştırıcılar (`pg_pb_price_to_cents()`, JS `priceToNumber()`) eksi
  işaretini atıyor: fark alanında işaret ayrı okunur, sayı mevcut kuralla.

## 4. Hesap kuralı

Sıra sabittir; nitelik kartlarının (müşteriye gösterilen eksen) sırasına bağlı
değildir — vitrin sırası bir mağazacılık kararıdır, fiyatı değiştirmemeli.

```
taban  = "Fiyatı belirler" seçeneğinin tutarı, yoksa ana fiyat
ara    = taban × (bütün çarpanlar) + (bütün tutar farkları)
fiyat  = ara × (1 + %₁/100) × (1 + %₂/100) × …
sonuç  = max(0, kuruşa yuvarla(fiyat))
```

- **Yüzdeler çarparak birleşir** (karar). Toplasaydık A·Beyaz·Deri
  160 × 1,7 = 272 ₺ olurdu; beyaz patent 192 ₺ olduğundan aradaki fark %41,7'ye
  düşer, "deri patentten %50 fazla" beyazda tutmazdı. Çarpınca her kural her
  kombinasyonda tek başına doğru kalır.
- **Tutar farkı yüzdeden önce eklenir**, aynı gerekçeyle: yüzde bütün fiyata
  uygulanır (Beyaz +20 ₺ → beyaz deri, beyaz patentten yine %50 fazla).
- **Tutar farkı çarpandan sonra eklenir**: "asorti başına +20 ₺" paket başınadır,
  çift başına değil.
- Ara adımlarda yuvarlama yok, yalnız sonda; yarımda yukarı (PHP `round()`,
  JS `Math.round()` — fiyat negatif olmadığından ikisi aynı sonucu verir). JS'te
  son yuvarlamadan önce `toFixed(6)` ile kayan nokta gürültüsü temizlenir.

## 5. Şema — tek kolon (onaylandı, 6.1)

Kurallar ekleme anında kullanılıp atılabilir; o zaman iş **yalnız kod**, şema
adımı yok. Önerilen ise kuralları setin üstünde saklamak:

**`product_groups.variant_price_rules`** TEXT NULL — JSON: ana fiyat (kuruş) +
nitelik başına tür + seçenek başına değer. Ekleme anında bir kez yazılır, bu
planda hiçbir ekran okumaz ya da değiştirmez.

Neden saklanmalı: ekleme anında girilen kural sonradan fiyatlardan güvenilir
biçimde geri çıkarılamaz. Bugün saklamazsak bölüm 8'deki adımlardan biri
gündeme geldiğinde her setin kuralları yeniden girilir; saklarsak o karar
bilgi kaybı olmadan ertelenebilir. Maliyeti tek bir boş kolon.

Saklanan kural değişmediği sürece ayrıca "elle değişti mi" bayrağına gerek
yok: varyantın bugünkü `price`'ı ≠ saklanan kuralla hesaplanan fiyat ise
fiyat sonradan (varyantın kendi ekranından, toplu düzenlemeden, içe
aktarmadan, API'den) ya da matriste elle değişmiştir. Bu yüzden önceki
taslaktaki `products.price_rule_cents` ve ayrı kural tablosu çıkarıldı.

Onaylanırsa: `includes/migrations/2026.4.6.php` →
`upgrade_2026_4_6_variant_price_rules()` (`install_add_column()`); dosya ve
`versions.php` satırı ilk şema adımıyla açılır. `pinegrap-sema-adimi` kontrol
listesi uygulanır (yeni tablo olmadığı için `get_tables()` maddesi geçersiz).

## 6. Kod yerleşimi

> Uygulamada: ayrı `includes/variant_pricing.php` yerine fonksiyonlar
> CLAUDE.md kural 4 gereği `includes/fn/ecommerce.php` modülüne yazıldı
> (`pg_variant_price_compute()`, `pg_variant_price_rules_normalize()`,
> `pg_variant_price_rule_value()`, `pg_variant_price_rules_owned()`,
> `pg_variant_price_rules_store()`, `pg_variant_price_rules_ready()`);
> `product_builder.php`'de `pg_pb_price_rules_from_post()`. Tarayıcı yerel
> ayrıştırmayı yapıp düz sayı gönderiyor. Panel nitelik kartının içine değil,
> şablonlarla matris arasında ayrı bir "Fiyat Kuralları" bloğuna kondu (kart
> tek ürün ekranında ve modalde de çiziliyor). Çelişkili kural reddedilmiyor,
> yok sayılıyor.

- **`includes/variant_pricing.php`** (yeni, arayüzsüz → `includes/`, giriş
  sabiti kapısıyla; `docs/CLAUDE-tam.md` "Dosya Yerleşimi"). Tek doğruluk
  kaynağı:
  - `pg_vp_compute($base_cents, $rules, $combo)` → `array(cents, formula, clamped)`
  - `pg_vp_rules_from_post($json)` — doğrulama (tek "belirler", çarpan > 0,
    % ≥ −100, seçenek gerçekten o niteliğe ait)
  `product_builder.php` bunu `require_once` eder.
- **JS**: kural paneli ve `compute()` ikizi `assets/js/product_builder.js`
  içinde (kullanan tek ekran). Bir gün ikinci ekran gelirse o zaman ayrı
  dosyaya çıkar. JS yalnız önizlemedir: kayıtta sunucu yeniden hesaplar ve
  otomatik satırlara **sunucunun sonucunu** yazar.
- `pg_pb_render_attribute_card()` — başlığa "Fiyata etkisi" seçimi, çiplerin
  altına seçili seçeneklerin değer alanları. `$single_choice` iken (tek ürün
  ekranı) hiç çizilmez. `product_attribute_action.php` aynı fonksiyonu
  kullandığı için modalden eklenen yeni nitelik de paneli kendiliğinden taşır.

## 7. Yeni ürün ekranı (matris)

- Satır fiyatı kuraldan dolar; altında küçük formül ipucu
  (`160 × 1,5`, `20 × 8 + 20`).
- Elle yazılan fiyat kilitlenir — SKU/açıklamadaki mevcut `touched` deseninin
  aynısı (`captureTypedValues()` kombinasyon anahtarıyla taşır). Satırda "Elle"
  rozeti + `bi-arrow-counterclockwise` "kurala dön".
- `#pg_pb_apply_price`, kural varken "Kurallarla yeniden hesapla" olur (elle
  kilitliler dahil hepsini sıfırlar; onay ister).
- Ana fiyat alanının etiketi türe göre: "Fiyatı belirler" varsa "Seçeneği boş
  varyantlar için taban"; "Çarpan" varsa "Birim fiyat (çarpanla çarpılır)".
- Post: kurallar `variant_price_rules` (JSON) + ana fiyat; matris satırına
  `price_manual: 1`. `pg_pb_save_new_product()` otomatik satırların fiyatını
  sunucuda yeniden hesaplar, elle satırları yazıldığı gibi alır; bölüm 5
  onaylanırsa grup oluşturulurken JSON'u `product_groups`'a yazar.
- Tek kombinasyon (grup oluşmuyor, `pg_pb_mode() = 'single'`): kural paneli
  gizlenir.

## 8. Sonradan düzenleme — kapsam dışı

Bugünkü araçlar (varyant başına ürün ekranı; grup ekranından seçip
`edit_products.php` ile ± tutar / ± %) şunları zaten karşılıyor:

- **Genel zam / indirim** (çift fiyatı 20 → 22 = bütün varyantlara +%10).
  Kurallar çarparak birleştiği için tek tip yüzde değişiklik aradaki bütün
  oranları korur. Yalnız tutar farkı (Beyaz +20 ₺) varsa o da yüzde kadar
  büyür — çoğu zaman sorun değil.
- **Tek varyantın fiyatı**: kendi ekranından.

Karşılanmayan, ileride gerekirse ayrı adım olacak iki şey:

1. **Bir seçeneğin etkisini değiştirmek** (Beyaz +%20 → +%25 bütün sette).
   Bugün beyazları seçip +%4,17 uygulamak gerekir — mümkün ama hesap
   operatörde.
2. **Var olan sete yeni varyant eklemek** (sonradan gelen yeni renk). Bugün
   ürün ayrı eklenip gruba bağlanıyor; fiyatı elle hesaplanıyor.

İkisi de bölüm 5'teki saklanan kurallarla, ekleme ekranındaki panel ve
`pg_vp_compute()` yeniden kullanılarak yapılabilir; şimdi adım atılmaz.

## 9. Fazlar

**Faz 1 (bu plan)** — `includes/variant_pricing.php`; nitelik kartında panel;
matriste otomatik fiyat + elle kilit; kayıtta sunucu hesabı; (onaylanırsa)
kuralların sette saklanması.

**İleride, ayrı karar** — bölüm 8'deki iki adım; seçeneğe varsayılan kural
(Beyaz her yeni sette +%20 ile önerilsin); vitrin seçicisinde fark etiketi
("Beyaz (+%20)", fiyatlar zaten satırda — farktan hesaplanır); yuvarlama
(tam ₺ / ,90 / 5'in katı). Yuvarlamada **dikkat**: fiyatlar KDV hariç
saklanıyor, KDV dahil mod yok; ",90" net fiyata uygulanırsa rafta ,90 görünmez.
Doğrusu etkin oranla (`get_effective_tax_rate()`) brütte yuvarlayıp nete
çevirmek, bölge oranı adrese bağlı olduğundan o da kesin değil. Faz 1'de yalnız
kuruşa yuvarlama.

## 10. Test vektörleri (PHP ve JS aynı tabloya karşı)

Senaryo: Asorti "Fiyatı belirler" A 160 / B 200 / C 240 / D 240; Renk "Fark"
Beyaz +%20; Malzeme "Fark" Deri +%50, Patent boş, Süet +%50.

| Kombinasyon | Formül | Beklenen |
|---|---|---|
| A · Kırmızı · Deri | 160 × 1,5 | **240,00** |
| A · Kırmızı · Patent | 160 | 160,00 |
| A · Beyaz · Patent | 160 × 1,2 | 192,00 |
| A · Beyaz · Deri | 160 × 1,2 × 1,5 | 288,00 |
| C · Beyaz · Süet | 240 × 1,2 × 1,5 | 432,00 |
| D · Mavi · Patent | 240 | 240,00 |
| A · Beyaz · Deri (Beyaz +20 ₺) | (160 + 20) × 1,5 | 270,00 |
| A · Beyaz · Patent (Beyaz +20 ₺) | 160 + 20 | 180,00 |
| Çarpan, ana 20: A / B / C / D · Kırmızı · Patent | 20 × 8 / 10 / 12 / 12 | 160 / 200 / 240 / 240 |
| Çarpan, ana 22: A / B / C / D · Kırmızı · Patent | 22 × 8 / 10 / 12 / 12 | 176 / 220 / 264 / 264 |
| Çarpan, ana 22: C · Kırmızı · Deri | 22 × 12 × 1,5 | 396,00 |
| Yarım kuruş | 10,01 × 1,5 = 15,015 | 15,02 |
| İndirim | 160 × 0,9 | 144,00 |
| Kırpma | 160 − 200 | 0,00 + uyarı |

Ayrıca: iki "Fiyatı belirler" niteliği → sunucu reddeder; matriste elle
yazılan fiyat kayıtta korunur; kural girilmeyen ekleme bugünkü fiyatları
yazar; tarayıcıdan bozuk/eksik kural JSON'u gelirse kayıt kuralsız devam eder,
fiyatlar matristeki gibi yazılır.

## 11. Belgeler

`docs/degisiklikler.md` (gerekçe) + `pinegrap/changelog.txt` (`[YENİ]`;
bölüm 5 onaylanırsa `[ŞEMA]`; `2026.4.6` bölümü) + (şema varsa) CLAUDE.md
sürüm tablosu. `docs/CLAUDE-tam.md` "Varyant modeli" bölümüne kural türleri,
hesap sırası ve (varsa) `variant_price_rules` kolonunun anlamı eklenir.
