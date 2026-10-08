# Pinegrap CMS — Claude Bağlam Dosyası

Bu dosya **her oturumda otomatik yüklenir**, bu yüzden kısa tutulur: yalnız her
görevde geçerli olan kurallar burada durur. Konuya özel kurallar `.claude/skills/`
altında ayrı dosyalardadır — **işe başlamadan önce aşağıdaki tablodan ilgili
skill'i yükle ya da dosyasını oku.**

| Nerede | Ne |
|---|---|
| `CLAUDE.md` (bu dosya) | Her göreve uygulanan kurallar + yönlendirme |
| `.claude/skills/<ad>/SKILL.md` | Konu bazlı kurallar; yalnız gerektiğinde okunur |
| `docs/CLAUDE-tam.md` | Tam arşiv: vaka notları, gerekçeler, ayrıntı |
| `docs/degisiklikler.md` | Değişiklik günlüğü (neden, kök sebep, ödün) |

---

## Hangi iş için hangi skill

Skill'i `Skill` aracıyla adıyla yükle; oturum skill listesini göremiyorsa
dosyayı doğrudan oku: `.claude/skills/<ad>/SKILL.md`. Alt ajan çalıştırıyorsan
**yolu göreve yaz** — ajan bu dosyayı görmeyebilir.

| Ne yapıyorsan | Skill |
|---|---|
| SQL yazıyor, tablo/kolon okuyor, sayaç veya rapor tasarlıyorsan | `pinegrap-veritabani` |
| Şema değiştiren migration adımı yazıyorsan | `pinegrap-sema-adimi` |
| Yeni dosya, yeni fonksiyon, yeni klasör veya kütüphane ekliyorsan | `pinegrap-yeni-dosya` |
| Kullanıcıya görünen metin yazıyorsan (`lang()`, `_sdT()`, `tr.json`) | `pinegrap-ceviri` |
| JavaScript düzenliyorsan (`.src.js`/`.min.js`, panel, ön yüz) | `pinegrap-js-varliklari` |
| CSRF, escape, yönlendirme, sır, dosya bütünlüğü, sunucu kuralı | `pinegrap-guvenlik` |
| `waf.php`, hız sınırı, IP listeleri, güvenlik başlıkları | `pinegrap-waf` |
| Panel ekranı, araç çubuğu, form, ayarlar modalı | `pinegrap-ui-deseni` |
| Rol kapısı, izin sütunu, tasarımcı içerik rolleri | `pinegrap-roller-yetki` |
| Görsel tasarımcı, sistem widget, data binding, palette component | `pinegrap-tasarimci-widget` |
| Dış API, webhook, konnektör, pazaryeri, e-belge | `pinegrap-dis-api` |
| ERP (ön muhasebe) modülü | `pinegrap-erp` |
| Denetim yapıyor ya da "ölü kod / temizlik" öneriyorsan | `pinegrap-verilmis-kararlar` |

Son satır önemlidir: bir denetim bulgusu yazmadan **önce** o skill'e bak, orada
zaten verilmiş kararlar listeleniyor.

---

## Proje

- PHP tabanlı monolitik CMS. 2017'den beri Erdal Güral (Kodpen) geliştiriyor;
  LiveSite fork'u, 2019 LiveSite güncellemesi entegre edilmiş.
- Bootstrap 5 + jQuery. PHP 7.1–8.5 uyumlu (en düşük 7.1, 2026.4.6'dan beri;
  kurulum ve Sistem Durumu daha eskisini işaretler). Veritabanı MySQL/MariaDB,
  erişim `mysqli`.
- **Bu depo yalnız ürünü içerir. Kod tabanı `pinegrap/` altındadır ve
  değişiklikler oraya yazılır.** Bu dosyadaki ve skill'lerdeki yollar o köke
  göredir: `includes/fn/core.php` demek `pinegrap/includes/fn/core.php` demektir.
- **Sürüm durumu (2026-10-06):** 2026.4.7 yayında (GitHub release `v2026.4.7`,
  etiket `8eb2716`) ve **kapalıdır**; etiket taşınmaz. Açık sürüm **2026.4.8**:
  yeni şema adımı `includes/migrations/2026.4.8.php` → `upgrade_to_2026_4_8()`
  girişine `upgrade_2026_4_8_<konu>()` alt adımı olarak girer (dosya ve
  `versions.php` satırı ilk şema adımıyla açılır); `changelog.txt`'de yeni
  maddeler en üste açılacak `2026.4.8` bölümüne yazılır. Barındırma platformuna
  (pinegrap.site) özgü özellikler — `PG_HOSTED` / `PG_DEMO` kipleri —
  changelog'a, README'ye ve GitHub release notuna yazılmaz. Güncel durum her
  zaman `docs/degisiklikler.md` → "Dağıtım durumu".
- Dil dosyası `includes/local/tr.json`, yapılandırma `data/config.php` (çalışma
  anında `CONFIG_FILE_PATH`). Gerçek `config.php` asla commit'lenmez; depodaki
  boş taslaktır, örnek değerler `data/config(default).php` içindedir.
- **`CLAUDE.md`, `.claude/` ve `docs/` yalnız `development` dalında izlenir**,
  `main`'e ve sürümlere girmez. `dev/` ile `.gitignore`'da adı geçen iç notlar
  (`docs/_software_create_hash.php`, `docs/_plan_barindirma.md` gibi) hiçbir
  dalda yoktur; yalnız geliştirme makinasında durur. Klonlanmış bir oturumda
  bunlar **yoktur**: yokluklarını sorun sanma, içeriklerini tahmin etme —
  gereken parçayı iste.

---

## Çalışma ortamı

Sandbox'ta PHP 8.3 hazır gelir. **Composer kullanılmaz**: üçüncü taraf
kütüphaneler `includes/` altında gömülüdür. Kod okuma, düzenleme ve `tools/`
altındaki denetimler için hiçbir hazırlık gerekmez.

Çalışan bir örnek gerekiyorsa — yalnızca çalışma zamanı hatasını yeniden üretmek
için — tek komut yeter:

```bash
bash tools/setup_sandbox.sh
```

MariaDB'yi kurup başlatır, veritabanını oluşturur, `data/config.php`'yi yazar,
kurulumu koşturur ve `127.0.0.1:8000`'de sunar (hazır SQL dökümü yoktur, şema
migration runner'ından çıkar). **Pahalıdır, gerekmedikçe yapma**; görevlerin
çoğu kod okuma ve statik denetimle çözülür. Betik hata verirse sebebini yaz,
etrafından dolaşma.

### Git ve GitHub işleri

Depoya dokunan her işlem tek elden yürür: dal açma, commit, push, PR ve issue.
Bunları git ve GitHub erişimi olan oturum yapar. Kod üzerinde çalışan ajan
değişikliğini çalışma ağacında bırakır ve `dev/_handoff/` benzeri bundle ya da
devir dosyası **üretmez**.

Kod üzerinde çalışan ajan değişikliği bırakır; gereken pull/PR'ı git erişimi
olan oturum hazırlar.

Ortak dosyalarda yalnız **ekleme** yap, tam yeniden yazma ve kendi alanının
bölümüne yaz:

- **Ortak dosyalar** (`includes/local/tr.json`, `pinegrap/changelog.txt`, açık
  sürümün migration dosyası, `init.php`, `docs/CLAUDE-tam.md`,
  `docs/degisiklikler.md`): yazmadan hemen önce
  `git fetch origin development && git merge origin/development` çalıştır ki
  en güncel kopyayı düzenle; push'tan hemen önce `development` üzerine rebase et.

**Dallar:** iş dalı `development`'tan açılır, PR'ın tabanı `development`'tır.
`main` yalnız yayınlanmış sürümleri taşır; ajan `main`'e PR açmaz, `main`'i
merge ya da rebase etmez — sürüm PR'ını depo sahibi açar.

---

## Değişmez kurallar

### 1. Kod içi yorumlar İngilizce — istisnasız

PHP, JS, CSS, SQL; string içine gömülü CSS/JS yorumları dahil. Ürün dosyaları
müşteriye dağıtılır, yorumlar ürünün parçasıdır. (`tr.json` değerleri ve
`lang()` string'leri yorum değildir; Türkçe olmaları normaldir.)

Dokunduğun bir dosyada Türkçe yorum görürsen aynı değişiklik içinde İngilizceye
çevir.

### 2. Yorumda AI/süreç izi yasak

Bir kod yorumunda şunların hiçbiri geçemez: "kullanıcı kararı", "kullanıcı
isteği", "kullanıcı onayıyla", "saha geri bildirimi", "Faz 1/2/3", plan dosyası
referansı, oturum/konuşma referansı. Karar tarihçesinin yeri değişiklik
günlüğüdür (`docs/degisiklikler.md`). Yorum klasik geliştirici yorumudur: kodun
ne yaptığını ve teknik olarak neden öyle yapıldığını anlatır (kilitler, yarışlar,
geriye dönük uyumluluk, güvenlik kapıları).

Aynı yasak commit mesajları ve PR açıklamaları için de geçerlidir:
`Co-Authored-By: Claude…`, `Claude-Session:`, `🤖 Generated with Claude Code`
ya da `claude.ai/code/session…` bağlantısı gibi hiçbir AI/oturum izi eklenmez.
Depo geneli commit yazarı `Erdal Güral <erdaltyy@gmail.com>`; mesaj yalnızca
değişikliği ve teknik gerekçesini anlatır.

### 3. Kullanıcıya görünen her metin çeviriden geçer

PHP ve panel `lang('English text')`, görsel tasarımcı `_sdT('English text')`.
Anahtar **İngilizce kaynak metindir** ve `tr.json`'da bulunmak zorundadır;
`_sdT()` anahtarı ayrıca **tek tırnaklı düz literal** olmalıdır.
→ `pinegrap-ceviri`

### 4. Yeni dosya: Pinegrap başlığı + kapı sabiti

Yeni PHP/JS dosyası Pinegrap başlık bloğuyla açılır (`PineGrap` değil,
**`Pinegrap`**); LiveSite başlığı yalnız miras dosyalara aittir. Kökte yalnız URL
ile çağrılan dosya durur, gerisi `includes/` altına iner ve kapı sabitiyle
başlar. Yeni fonksiyon `includes/fn/<modül>.php`'ye yazılır, `functions.php`'ye
değil. → `pinegrap-yeni-dosya`

### 5. Şema değişikliği yalnız migration ile

`db("ALTER TABLE x ADD ...")` yazmak yasaktır. Her `ADD`/`CREATE`/`DROP`/
`MODIFY`/`CHANGE` **önce sorar, sonra yapar** ve idempotent yardımcılarla
(`install_add_column()` ailesi) açık sürümün içine alt adım olarak girer.
→ `pinegrap-sema-adimi`

### 6. Ürün sahibinin kararı bağlayıcıdır

Kodda gerekçesi görünmediği için denetimlerde tekrar tekrar "ölü kod",
"kullanılmayan alan" ya da "eksik kontrol" diye bulunan yerlerin bir listesi
vardır. Bulunmaları bir bulgu değildir. Değiştirmeyin, temizlemeyin,
"iyileştirmeyin". Bir maddenin yanlış olduğuna gerçekten inanıyorsanız
uygulamadan **önce** sorun; sessizce değiştirmek bu kuralın ihlalidir.
→ `pinegrap-verilmis-kararlar`

### 7. En sık düşülen dört tuzak

Hata verdirmeden yanlış çalıştıkları için burada duruyorlar; ayrıntısı skill'de.

- **Tablo adları tekildir** (`page`, `style`, `pregion`, `forms`) ve **sorgu
  hatası `false` döner, istisna fırlatmaz** — `mysqli_report(MYSQLI_REPORT_OFF)`
  satırları kaldırılmaz.
- **Fiyatlar kuruş cinsinden `int`**; kesirli kuruşta `(int)` ile kırpma,
  `round()` kullan.
- **`.src.js` düzenlenir, `.min.js` servis edilir — ikisi birden.** Yalnız
  `.src.js` düzenlemek hiçbir şey yapmaz ve hata vermez.
- **Rol hiyerarşisinde Designer (1), Manager'ın (2) üstündedir.**
  `validate_area_access($user, 'designer')` rol ≤ 1, `'manager'` rol ≤ 2 ister.

---

## Bir iş ne zaman biter

Denetim betikleri ve küçük bir birim test takımı vardır (Composer'sız,
veritabanısız; yalnız saf fonksiyonlar — `tools/test.php` başlık yorumu nasıl
test yazılacağını anlatır). CI (`.github/workflows/php-checks.yml`) hepsini
koşar. Temiz çıkmadan iş bitmiş sayılmaz; "testler geçti" yalnız bu takım için
denebilir, çalışma zamanı davranışı sandbox'ta elle doğrulanır:

```bash
php tools/lint.php             # tum agacta php -l
php tools/check_lang.php       # lang() / _sdT() anahtarlari tr.json ile ortusuyor mu
php tools/check_bindings.php   # tasarimci acilir listesi ile renderer tokenlari ortusuyor mu
php tools/check_api_schema.php # dis API alanlari sema ile ortusuyor mu
php tools/test.php [filtre]    # tests/*_test.php birim testleri (saf fonksiyonlar)
php tools/check_copies.php     # get_file.php'de yalniz bilerek birakilan fonksiyon kopyalari var mi
```

1. `php tools/lint.php` temiz.
2. `php tools/check_lang.php` temiz — eksik anahtar ve `_sdT()` literal kuralı
   ihlali yok.
3. Tasarımcıya/widget'a dokunduysan `php tools/check_bindings.php` temiz.
4. Dış API'ye dokunduysan `php tools/check_api_schema.php` temiz;
   `style_designer.js`'e dokunduysan `node --check` temiz.
   (Dokunup dokunmadığından emin değilsen koştur — betikler ucuzdur.)
4a. `php tools/test.php` temiz; saf bir fonksiyona dokunduysan ya da yenisini
   yazdıysan `tests/<konu>_test.php`'ye test ekledin (davranışı değiştiriyorsan
   önce test kırmızı, sonra yeşil).
5. Yeni dosyada başlık bloğu, include ise kapı sabiti var.
6. Yeni ve dokunulan yorumlar İngilizce, süreç/AI izi taşımıyor.
7. Şema değişikliği varsa migration üzerinden ve tekrar koşulabilir; dev DB'de
   iki kez koşturuldu.
8. `.src.js` düzenlendiyse ikizi `.min.js` de güncellendi.
9. Değişiklik, değişiklik günlüğüne yazılacak şekilde PR açıklamasında özetlendi.

PR açıklamasında ne değiştiğini, neden öyle yapıldığını ve **neyi
doğrulayamadığını** yaz (çalışan örnek kurulmadıysa bunu açıkça belirt).

---

## Ayrıntı nerede

`docs/` `development` dalında izlenir; `dev/` hiçbir dalda yoktur, yalnız
bağlı geliştirme klasöründe okunur (yukarı bak).

| Konu | Dosya |
|---|---|
| Konu bazlı kurallar (yukarıdaki tablo) | `.claude/skills/<ad>/SKILL.md` |
| Palette component yazımı (zorunlu okuma) | `docs/component-development-guide.md` |
| `lang()` sözdizimi ve kullanımı | `docs/LANG_USAGE.md` |
| Tam bağlam arşivi | `docs/CLAUDE-tam.md` |
| Değişiklik günlüğü | `docs/degisiklikler.md` |
| API ile ERP aynı depoda: dikiş yeri ve ortak dosya kuralları | `dev/_handoff/API-ERP-koordinasyon.md` |
| 2026-10-08 keşif raporları ve beş paralel uygulama planı | `docs/_tespit_2026_10_08/`, `docs/_plan_{panel_api_widget,innodb,kargo,posta_kuyrugu_cron_webhook,csrf_2fa}.md` |

Önemli değişiklikler `docs/degisiklikler.md`'ye, proje bağlamını etkileyen
değişiklikler `docs/CLAUDE-tam.md`'ye yazılır; kalıcı bir kural ortaya çıktıysa
ilgili skill'e **de** işlenir — skill kısa kalır, vaka anlatısı arşive gider.
`dev/` ağacı ve `.gitignore`'daki iç notlar bilerek depo dışındadır.
Bir kararın gerekçesi gerçekten gerekiyorsa PR açıklamasına soru olarak yaz,
uydurma.
