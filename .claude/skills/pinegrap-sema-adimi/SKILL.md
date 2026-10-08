---
name: "pinegrap-sema-adimi"
description: "Pinegrap CMS'te veritabanı şeması değiştiren (tablo, kolon, index ekleyen) bir migration adımı yazarken kullan. Adım adım kontrol listesi ve atlanınca ne bozulduğu."
---

# Pinegrap — DB şema adımı ekleme

Bu liste daha önce atlandığı için hata üretti. Maddeleri sırayla uygula ve her
birini bitirdiğini söyle.

## Nereye yazılır

Yeni şema değişikliği **açık (yayınlanmamış) sürümün** içine alt adım olarak
eklenir. Yeni sürüm numarası yalnız yayın sonrası açılır. Hangi sürümün açık
olduğunu `docs/degisiklikler.md` başındaki "Dağıtım durumu" söyler; o dosya depo
dışıdır (geliştirme makinasında durur) — erişemiyorsan **tahmin etme, sor**.

2026-10-06 itibarıyla 2026.4.7 yayında ve kapalı; açık sürüm **2026.4.8**
(`includes/migrations/2026.4.8.php`). Alt adım etiketleri 2026.4.7'deki yapıyla
`8.x` (Çalışma Alanı 8.80–8.89, ERP 8.58–8.69 ve 8.90–, API 8.70–8.79). Dosyayı
ve `versions.php` satırını ilk şema adımını yazan açar; satır eklendiği an dev
paneli yükseltme ekranına yönlenir, yükseltme hemen koşulur. Dosya açıldıktan
sonra gelen adım dosyayı yeniden oluşturmaz, `upgrade_to_2026_4_8()` girişine
yeni bir çağrı ekler.

DB'ye dokunan her sürümün kendi dosyası olur (`2026.2.5.php` →
`upgrade_to_2026_2_5()`); kod-only sürümler için migration dosyası eklenmez.

Migration dosyaları: `pinegrap/includes/migrations/`
(`install/` klasörü bazen siliniyor, `includes/` altında güvende. `install/index.php`
içine yazılan şema kodu o klasörle birlikte kaybolur.)

Adlandırma: sürüm girişi `upgrade_to_2026_4_8()` (sistemin aradığı tek isim),
alt adımlar `upgrade_2026_4_8_<konu>()` gibi (2026.4.4'te
`upgrade_2026_4_4_recycle_bin()`) `to_` almaz.

## Kontrol listesi

1. **Idempotent yardımcıları kullan** — `install_add_column()`,
   `install_add_index()`, `install_create_table()`, `install_drop_*`,
   `install_modify_column()`, `install_rename_column()`, `install_rename_table()`.
   `db("ALTER TABLE ...")` yazmak **yasaktır**. Ham `SHOW COLUMNS ... LIKE`
   yasak: `_` joker karakterdir ve yanlış kolonu "var" sayar — probe'u ada tam
   eşitlikle yaz (`WHERE Field =`, `information_schema`).

2. **Adım sonunda `install_note()`** çağır.

3. **Büyük tabloyu yeniden yazıyorsa** `runner.php` içindeki
   `install_heavy_tables()` haritasına `'<sürüm>' => array('<tablo>')` ekle
   (ön kontrol uyarısı oradan üretilir).

4. **YENİ TABLO eklediysen** `install/index.php` içindeki `get_tables()`
   listesine ekle.
   Atlanırsa: aynı DB'ye yeniden kurulumda eski tablo kalır, idempotent adım
   "zaten var" deyip geçer, site eski şemayla **sessizce** açılır.

5. **`pinegrap/changelog.txt`** — operatör dilinde madde yaz: `[YENİ]` /
   `[ŞEMA]` / `[GÜVENLİK]` / `[DÜZELTME]` / `[HIZ]`. Şema değişikliğinde `[ŞEMA]`
   zorunlu. Yayınlanmamış sürümdeki düzeltmeler `[DÜZELTME]` olarak YAZILMAZ.
   Hata düzeltmesinde **belirtiyi** de yaz.

6. **`docs/degisiklikler.md`** — kararın gerekçesini, kök sebebi ve ödünü yaz.
   Yeni madde başlık bloğunun (`---`) hemen altına, önceki en yeni maddenin
   üstüne girer.

7. **`docs/CLAUDE-tam.md` sürüm tablosuna** alt adımı işle.

## Genel kurallar

- `versions.php`'de satır silme, numara yeniden kullanma; sistem yalnız
  `config.version`'a bakar ve anahtarı **eşit** olan sürümü atlar.
- Kapalı (yayınlanmış) sürümlerin fonksiyon gövdeleri düzeltilebilir (o adımı
  henüz almamış siteler için); sürüm geçmişi ve her sürümün getirdiği DB
  değişikliği sabit kalır.
- Kod-only sürümler için migration dosyası eklenmez.
- Veri ifadeleri (`UPDATE`/`DELETE`) tekrar koşulabilir yazılır
  (`WHERE ... IS NULL`, `WHERE created_at = 0`).
- Enum daraltan `MODIFY` önce `install_column_info()` ile mevcut şekli okur ve
  korur; okumadan yazmak o kolondaki satırları `''` yapar.
- `config` tablosuna geniş alan **TEXT** olarak açılır, `VARCHAR(500)` değil —
  satır utf8mb4'te 65535 bayt sınırına dayalı, MySQL 1118 verir.
  (TINYINT/INT/ENUM sorun değil.)
- Host'un kaldırabileceği PHP fonksiyonları (`set_time_limit`,
  `ignore_user_abort`, `disk_free_space`, `getmypid`, `php_uname`, `error_log`,
  `mail`, `fsockopen`, `exec`/`proc_*`/`posix_*`) `function_exists()` ile
  sorulmadan çağrılmaz — PHP 8'de devre dışı fonksiyon tanımsızdır, `@` susturmaz
  ve yükseltme ekranı beyaz sayfa olur. `runner.php`'de `error_log` yerine
  `install_log()`.
- Pahalı adımı sürümlere böl (şema / backfill / motor); `ini_set('max_execution_time')`
  yetmez (IIS FastCGI 90 sn). Her parçadan sonra imleci kaydet.
- Ürün MySQL 5.7'yi desteklemeye devam eder; yalnız daha yenisinde çalışan
  sözdizimi kullanılmaz.

## Teşhis

- "Özelliği ekledim, upgrade seçeneği çıkmıyor": yeni numara **açma**;
  `UPDATE config SET version = '<bir önceki yayınlanmış sürüm>'` ile geri çekip
  yükseltmeyi yeniden koştur (DB erişimi yoksa `edit_config.php` → "Veritabanı
  Şema Sürümü"; oraya yalnız `versions.php`'de bulunan numara girilir, listede
  olmayan numara yükseltme ekranını tamamen kapatır).
- "Zip açtım, dosya eski kaldı": Yazma İzinleri kartına bak —
  `includes/migrations` 0555 ise runner kısa listeyi koşar, `config.version`
  ilerler, şema yarım kalır.

## Bitirmeden önce

- `php -l` çalıştır (ParseError yalnız o sürümü durdurur, yarım şema bırakır).
- Dev DB'de adımı **iki kez** koştur; ikincisinde "zaten yerindeydi" demeli.
- Yedi maddeyi tek tek gözden geçir ve hangilerini yaptığını listele. Bir madde
  uygulanamadıysa sebebini söyle — sessizce atlama.
