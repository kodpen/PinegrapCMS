# Ziyaretçi kayıtları: saklama süresi, günlük özet, parça parça silme (2026.4.10, şema 10.3)

Durum: birinci parça (#343) ve ikinci parça (MyISAM tablonun kendiliğinden
InnoDB'ye taşınması, botların yazılmaması; aşağıda "İkinci parça") yapıldı.
"Ertelenenler" ayrı işlerdir.
Sahibi: `includes/fn/visitor_retention.php`, Veritabanı Motoru ekranı,
Ayarlar › SEO › Ziyaretçi Takibi.

## Sorun

`visitors` tablosuna her ziyaret bir satır yazılıyor, hiçbiri silinmiyordu.
Yoğun sitelerde 3,8 milyon satır ve tek başına 800 MB'ın üstü; tek seferlik
büyük bir `DELETE` ise tabloyu dakikalarca kilitleyip siteyi durdurur.

Bu büyüklükteki bir tablo büyük olasılıkla hâlâ MyISAM'da: 2026.4.8'in InnoDB
taşıması 250.000 satır / 128 MB üstündeki tabloları elle dönüştürmeye
bırakıyor. MyISAM'da her yazma ve o tabloyu okuyan her sorgu tablonun
tamamını kilitler; "tüm sistemi kilitliyor" belirtisi buradan. Temizlik
bittikten sonra Veritabanı Motoru'nda tabloyu InnoDB'ye dönüştürmek hem
kilidi hem dosya boyutunu çözer (dönüştürme tabloyu yeniden yazar).

## Yapılanlar

1. **Saklama süresi** (`config.visitor_retention_days`, gün; varsayılan 90,
   0 = sınırsız, aralık 7–3650) ve **"silmeden önce günlük özet tut"**
   (`config.visitor_summary`, varsayılan açık). Ayar: Ayarlar › SEO ›
   Ziyaretçi Takibi. Geçersiz değer kaydedilmez, alan hatasıyla döner.
2. **Günlük özet** (`visitor_stats_daily`): her gün için, raporun gruplama
   anahtarlarıyla (kaynak alan adı, arama motoru, arama terimi, ücretli/organik,
   giriş sayfası, ilk ziyaret, izleme kodu, ortaklık kodu, UTM beşlisi, form,
   ülke/il/şehir, site içi arama, para birimi, cihaz) değer başına ziyaret,
   sayfa görüntüleme, form, sipariş sayıları ve sipariş toplamı. Boyut başına
   günün ilk 100 değeri ayrı satır, kalanı tek "diğer" satırı (`is_rest`);
   toplam satırı (`dimension = ''`) en son yazılır ve günü "özetlendi" yapar.
   Yarıda kalan gün bir sonraki koşuda baştan yazılır.
3. **Parça parça silme** genel görevde (`job.php`): birikmiş iş varken her
   turda, yoksa saatte bir. Bir koşu en çok 15 sn; özet en çok 9 sn'sini alır,
   kalanı silmeye gider. Silme birincil anahtar sırasıyla 5.000 id'lik
   pencerelerle (`DELETE … WHERE id > a AND id <= b AND start_timestamp <
   sınır`), her biri kendi kısa işlemi, aralarında 20 ms ara. MyISAM'da
   (tablo kilidi) pencere 1.000 satır, ara en az parçanın sürdüğü kadar.
   Özet açıkken yalnız özetlenmiş günler silinir. İki koşu çakışmaz
   (`GET_LOCK`). Saatlik grafik tablolarının geçmiş doldurması bitmeden onun
   ulaşmadığı satırlara dokunulmaz.
4. **Veritabanı Motoru › Ziyaretçi kayıtları** kartı: satır, boyut, boş alan,
   en eski kayıt, saklama süresi, özetin vardığı gün, son temizlik; şimdi
   silinecek satır sayısı ve tahmini boyut. **"Ziyaretçi kayıtlarını şimdi
   temizle"** onay ister, sayfa açıkken 20 sn'lik adımlarla ilerler, sayaç
   kartta görünür. **"Optimize et"** ayrı, uyarılı adım (tablo boyutu kadar
   boş disk, büyük tabloda dakikalar).
5. **Uyarı**: tablo 2 milyon satırı ya da 512 MB'ı (veri + dizin) geçince
   kartta ve Sistem Durumu'nda ("Ziyaretçi Kayıtları", puana etki etmez).
   Silinen satırlardan kalan boş alan dosyanın yarısını ve 128 MB'ı geçince
   kartta "Optimize et" bilgisi.
6. **Rapor ve API özeti okuyor**: Ziyaretçi Raporu tarih seçiciyle açıldığında
   ilk ham günden öncesini özetten, sonrasını satırlardan alır (yıl/ay/gün +
   en çok bir gruplama, filtresiz). Karşılayamadığı raporda o günlerin
   rapora girmediğini söyler. `GET /reports/visitors` aynı yolla; yanıtta
   `summary_to`. Rapora ve API'ye **cihaz** (masaüstü/mobil/tablet) eklendi.
7. **Küçük şişme önlemleri** (yalnız InnoDB, tablo kopyalanmadan): `ip_address`
   dizini kaldırıldı (`idx_dedup` aynı sütunla başlıyor), kullanılmayan
   `idx_user_agent` kaldırıldı. Sandbox'taki 3,8 milyon satırda ikisi birlikte
   ~350 MB, tablonun beşte biri. MyISAM'da dokunulmaz.

Yükseltme hiçbir satırı silmez; büyük sitelerde temizliği genel görev ilk
turlarında azar azar yapar.

## Varsayılanlar

| Ne | Değer |
|---|---|
| Saklama | 90 gün (0 sınırsız, 7–3650) |
| Günlük özet | açık |
| Özette boyut başına | günün ilk 100 değeri + "diğer" |
| Silme parçası | InnoDB 5.000 satır, aralarında 20 ms; MyISAM 1.000 satır, ara parçanın süresi kadar (en az 20 ms) |
| Genel görev koşusu | 15 sn (özete en çok %60) |
| Elle temizlik adımı | 20 sn |
| Uyarı eşiği | 2.000.000 satır ya da 512 MB |

## Ölçüm (sandbox, MariaDB 10.11, 128 MB buffer pool)

~3,8 milyon satır, 400 güne yayılı (~9.500/gün); 90 günde ~2,94 milyon satır
siliniyor. Siteyi taklit eden yük aynı anda koştu: ziyaret ekleme, sayfa
görüntüleme güncellemesi, tekilleştirme sorgusu, son bir saat sayımı; 10
sn'lik pencerelerde süreler.

| | InnoDB | MyISAM |
|---|---|---|
| Parça parça (genel görev gibi 15 sn'lik koşular) | 11 koşu, 151 sn; 2.945.272 satır, 310 gün özet | 20 koşu, 277 sn; 2.939.369 satır, 310 gün özet |
| Bu sırada site sorguları (p99) | ekleme ~4,5 ms, güncelleme ~3 ms, tekilleştirme ~1,5 ms; en uzun 28 ms; ortanca değişmedi | ekleme 0,5 → ~43 ms, güncelleme 0,4 → ~33 ms, tekilleştirme 0,9 → ~15 ms; en uzun 83 ms |
| Tek seferlik `DELETE … WHERE start_timestamp < sınır` | 49,5 sn, tek işlem; yükü bekletmedi ama yarıda kesilemez, kesilirse aynı süre geri alır | 24,5 sn; bu sürede tabloya giden **her** sorgu bekledi (tekilleştirme 24,4 sn) |
| Silmeden sonra dosya | küçülmez (1,34 GB); `OPTIMIZE` 854 bin satırda 5,5 sn → 310 MB, tek sorgu 0,85 sn bekledi | küçülmez (872 MB verinin 675 MB'ı boş) |

- MyISAM'daki tek `DELETE` kilidi "tüm sistemi kilitliyor" belirtisinin
  kendisi; canlı trafikte ve soğuk diskte dakikalar sürer.
- MyISAM'da ilk deneme `DELETE … ORDER BY id LIMIT 1000` idi: sunucu
  `start_timestamp` dizinini seçip her parçada eşleşen satırları sıraladı,
  parça 0,3–0,5 sn sürdü, site sorguları 300–870 ms bekledi. Birincil anahtar
  pencereleri bunu giderdi.
- MyISAM'da koşuyu özet sınırlıyor (gün başına ~0,55 sn, koşu başına 16 gün
  ~152 bin satır); InnoDB'de gün ~0,35 sn, koşu başına ~28 gün.
- Ön izleme sayımı (`COUNT(*) … start_timestamp < sınır`): 0,7 sn.
- Rapor ve API: özet + ham satırların toplamı ham kopyayla birebir aynı
  (3.794.000 ziyaret, 18.969.998 sayfa görüntüleme; ay ay ve ülke, kanal,
  cihaz kırılımları).
- "Ziyaretçi kayıtlarını şimdi temizle" tarayıcıda: 570.297 satır, iki adım,
  28 sn.

## İkinci parça: mevcut sitelerde kimse karta bakmadan

Bir müşteride yanlışlıkla silinen ürünü geri getirmek için kurulmak istenen
950 MB'lık yedek, ağırlığını ziyaretçi tablosundan alıyordu. Yedek
(`includes/fn/backup.php`) satırları döken bir SQL dökümüdür: silinen satır
dökümden hemen düşer, dosyanın diskteki boyutu yedeği etkilemez.

- **90 gün mevcut sitelere zaten geliyor.** 10.3 kolonu
  `ADD COLUMN … DEFAULT 90` ile açtığı için 2026.4.9'dan yükselen her sitenin
  `config` satırı 90 alır (sandbox'ta kolon silinip adım yeniden koşturularak
  doğrulandı). 0 yalnız birinin elle seçtiği yerde olur; o yüzden 0'ı 90'a
  çeviren yeni şema adımı yazılmadı (seçimi ezerdi).
- **MyISAM tablonun InnoDB'ye taşınması** (`pg_visitor_move_run()`, genel
  görev): `visitors` 2026.3.6'da tek `ALTER` ile InnoDB'ye taşındı; olmadığı
  sitede MyISAM kalır, Veritabanı Motoru'nun "Tablolar MyISAM'da" listesi
  de `visitors`'ı taşımıyordu (liste yalnız `pg_innodb_core_tables()`).
  Temizlik gecikmesi bitince (`backlog` yok) ve veritabanı sunucusunun boş
  diski okunabilip yeterliyse (yerel sunucu, `@@datadir`, kalan satırların
  üç katı + 1 GB) genel görev `visitors_innodb` kopyasını kurar, birincil
  anahtar sırasıyla 2.000 satırlık `INSERT … SELECT` parçalarıyla doldurur
  (MyISAM'da her parça tabloyu tuttuğu kadar ara), yetişince taşıma
  başladıktan sonra değişen ziyaretleri `stop_timestamp` dizininden yeniden
  kopyalar, son saniyelerin satırlarını alır ve `RENAME TABLE` ile yer
  değiştirir; eski tablo `visitors_myisam` adıyla kalır, süre içindeki her
  satırı yenide var mı diye bakılır, sonra silinir. Durum
  `data/temp/visitor_move.json`; kopyanın vardığı yer tablodan okunur.
  Kartta durum ve "Şimdi InnoDB'ye taşı" (disk ve gecikme koşulunu atlar).
- **Botlar**: güvenlik duvarı kapalıyken, izinli adreste ya da hariç yolda
  `IS_BOT` tanımlanmadığı için her tarayıcı sayfası ziyaret yazılıyordu.
  `init_tracking()` artık `waf_classify_bot()`'a soruyor, insan olmayan her
  sınıfı (güvenlik duvarının atladığı sınıfların aynısı) yazmıyor.

## Ertelenenler (ayrı işler)

1. **Botlar** (yapıldı, ikinci parça): güvenlik duvarı açıkken zaten
   yazılmıyordu; kapalıyken de yazılmıyor.
2. **Uzun metin sütunlarını ayırmak**: `http_referer`, `user_agent`,
   `utm_term`, `utm_content`, `referring_search_terms`, `site_search_terms`
   satırın çoğu. Sözlük tablosuna taşımak satırı yarıdan fazla küçültür ama
   büyük tabloyu kopyalayan bir şema değişikliğidir.
3. **Aylık bölümleme (partition)**: eski ayı `DROP PARTITION` ile anında
   atmak. Birincil anahtarın `(id, start_timestamp)` olmasını ister, paylaşımlı
   barındırmada her sunucuda yok.
4. **IP adresini erken silmek** (KVKK): N günden sonra `ip_address = 0`.
   Tablonun tamamına UPDATE; saklama süresiyle aynı parça yöntemiyle yapılabilir.
5. **Kendiliğinden OPTIMIZE**: sakin saatte, yeterli disk varsa. Disk dolma
   riski yüzünden şimdilik elle. InnoDB'de silinen satırların yeri dosyada
   kalır (yedeği etkilemez, diski etkiler); MyISAM'da taşıma bunu çözüyor.
   Aynı kopyala-yer değiştir yöntemi InnoDB'yi de küçültebilir.
6. Özetin tutmadığı gruplamalar (tam yönlendiren adres, posta kodu, sayfa
   görüntüleme sayısı, sipariş bayrakları) ve filtreli raporlar için özet:
   gerek görülürse boyut eklenir.
7. **Yedekte ziyaretçi satırları** (yapıldı, PR #434; ürün sahibi
   2026-10-14'te onayladı): her veritabanı yedeği (otomatik, panelden elle,
   güncelleme öncesi) `visitors`, `visitor_content_hourly`,
   `submitted_form_views` ve `submitted_form_view_stats` tablolarını yalnız
   yapısıyla döker (`pg_backup_rowless_tables()`); taşımanın çalışma
   kopyaları (`visitors_innodb`, `visitors_myisam`) hiç dökülmez. Günlük
   özet ve saatlik ziyaretçi grafiği satırlarıyla kalır. `sql.sql` bunu
   söyleyen bir yorumla başlar, yedek ekranları not gösterir.
8. **Büyük yedeğin geri yüklenmesi**: 950 MB'lık yedeği kurarken sunucu
   yanıt veremedi; geri yükleme yolunun parça parça çalışması ayrı bir iş.
