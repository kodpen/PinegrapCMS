# Pinegrap CMS

[English](README.md) | **Türkçe**

![PHP Version](https://img.shields.io/badge/PHP-7.1%20--%208.5-777BB4?style=flat-square&logo=php)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)
![Status](https://img.shields.io/badge/Status-Active%20Development-success?style=flat-square)

Pinegrap CMS, LiveSite temeli üzerine inşa edilmiş açık kaynaklı bir içerik yönetim ve kurumsal web platformudur. 2017'den beri performans, esneklik ve geniş PHP uyumluluğu hedefiyle aktif olarak geliştirilmektedir.

---

## Genel Bakış

Pinegrap CMS; kurumsal web sitelerini, e-ticareti, kullanıcı yetkilerini ve özel dinamik içeriği yönetmek için tasarlanmış güçlü bir sistemdir. Paylaşımlı bir cPanel hosting'den özel bir IIS sunucusuna kadar, eski ve modern web ortamlarında yüksek güvenilirlikle çalışır. Yerleşik web uygulama güvenlik duvarı ve bot denetimi, eklenti ya da dış hizmet kurmadan her siteyi ilk istekten itibaren korur.

### Gururla Monolitik

Pinegrap **bilinçli olarak monolitiktir** — ve bu bir özür değil, bir özelliktir.

* **Composer yok. Build pipeline yok. `node_modules` yok.** Dosyaları yükle, kurulumu çalıştır, bitti.
* **Tek kod tabanı, tek dağıtım.** Her şey birlikte gelir ve sürümlü, panel içi yükseltme sistemiyle birlikte güncellenir.
* **PHP'nin çalıştığı her yerde çalışır.** Kökleri 2001'e uzanan, gerektiğinde hâlâ düz FTP ile dağıtılabilen bir üretim kod tabanı.
* **Her satırı incelenebilir.** Hiç okumadığınız on bin dosyalık bir vendor klasörü yoktur.

### Öne Çıkanlar

* **Geniş Uyumluluk:** PHP 7.1'den PHP 8.5'e kadar sorunsuz çalışır.
* **Sunucu Desteği:** Apache, Nginx ve Microsoft IIS ile uyumludur (otomatik yönlendirme ve `.htaccess` / `web.config` rewrite desteği dahil).
* **Hepsi Bir Arada:** Web sitesi, görsel sayfa editörü, e-ticaret, ERP, ekip çalışma alanı ve dış API tek kod tabanında.
* **Yerleşik Güvenlik Duvarı ve Bot Denetimi:** Her isteğin önünde saldırı imzaları, hız sınırları ve otomatik yasaklar; gerçek arama motorları doğrulanır, sahte tarayıcılar, kazıyıcılar, yapay zekâ eğitim botları ve saldırı araçları geri çevrilir.
* **Çok Dilli Ön Yüz:** Görsel editörde yapılmış sayfalar, kopyalanmadan, sanal bir dil dizininden başka dillerde sunulur.

---

## Özellikler

**Görsel Sayfa Editörü ve Şablonlar**

* Bootstrap 5 ya da tamamen özel tasarımlar için sürükle-bırak sayfa editörü; her tasarım bir görünüm (köşeler, gölgeler, yazı, düğmeler) ve renk paleti giyer, ortak bileşenler (üst menü, alt bilgi, bantlar) bir kez düzenlenir, her sayfada değişir
* Kurulum sihirbazından doğrudan kurulabilen hazır site şablonları: "Pinegrap'a merhaba deyin" ve "Online Mağaza"
* Sihirli CSS sınıfları yerine veri bağlamayla çalışan sistem widget'ları: katalog, ürün, sepet, ödeme, sipariş görünümü, hesap sayfaları, giriş bölgesi, sepet bağlantısı, dil seçici, hata sayfası, form listesi ve form öğesi görünümleri
* Var olan bir siteyi düzenlenebilir tasarıma çeviren HTML / ZIP içe aktarma; tekrarlanan bölümleri ortak bileşen olarak önerir, satır içi SVG'yi korur
* Aynı editörde tasarlanan e-posta sayfaları (form bildirimi ve yanıtı, sipariş fişi); asistan sayfa değişikliği önerir, uygulamadan önce önizlenir

**İçerik**

* Sayfa, ortak, tasarımcı ve dinamik bölgelerden oluşan dinamik sayfa motoru — içerik, sayfanın kendisi üzerinde yerinde düzenlenir
* Blog / makale yayınlama, foto galeriler, menüler, zamanlanmış yayınlanabilen yorumlar ve site içi arama
* Yinelenen etkinlik, mekân ve rezervasyon destekli takvimler
* Masaüstü tarzı dosya yöneticisi (ağaç görünümü, sürükle-bırak, hızlı bakış, toplu yeniden adlandırma, görsel optimizasyonu, geri dönüşüm kutusu) ve yerleşik görsel editörü

**Çok Dilli Site**

* Görsel editör sayfaları aynı tasarımla sanal bir dizinden (`/en/hakkimizda`) başka dillerde sunulur; dosyalar, görseller ve varlıklar tek adreste kalır
* Kapsama oranı, yan yana düzenleme, gözden geçirme işareti, CSV içe / dışa aktarma ve sözlük içeren Çeviriler ekranı; ürün, form ve widget metinleri de çevrilir
* Motorlar: Google Cloud Translation (kendi anahtarınızla), tarayıcının yerleşik Translator API'si, Pinegrap AI, Claude ya da elle çeviri; "kaydedince çevir" sayfaları güncel tutar
* Sitemap'te hreflang alternatifleri, dil içinde arama, dile özgü sayı ve tarih biçimleri ve dil seçici widget'ı

**E-ticaret**

* İç içe ürün grupları, fiyat kurallı varyant setleri, ürün başına vergi oranı, satır içi düzenleme ve tablo dosyasından içe aktarmayla ürün kataloğu
* Tek ekranlı kampanya oluşturucu, kuponlar, hediye kartları, çapraz satış ve terkedilmiş sepet otomatik kampanyaları
* Kayıtlı adresler, T.C. kimlik / vergi numarası alanları ve "fatura adresim kargo adresimle aynı" seçeneğiyle ödeme ekranı; havale siparişleri ödemeyi bekler, ödeme gelmezse kendiliğinden iptal olur
* Tam sipariş yaşam döngüsü: iptal akışları, iade (Iyzipay entegrasyonu), Türk kargo firmaları için takip linkleri, yazdırılabilir fatura, sipariş zaman çizelgesi, hızlı POS ekranı ve barkod tabanlı stok işlemleri
* n11 pazaryeri entegrasyonu: kategori ve nitelik eşleme, fiyat ve stok senkronu, sipariş aktarımı

**ERP (Ön Muhasebe ve Faturalama)**

* Müşteri ve tedarikçi carileri, ekstreler, kasa, banka ve POS hesapları, mutabakat mektupları
* Siparişten ya da elle fatura ve irsaliye, taslaklar, iade ve iptaller, PDF çıktısı
* Logo İşbaşı ya da Paraşüt üzerinden e-Fatura / e-Arşiv, yaşlandırma raporları, ödeme hatırlatmaları ve çok dövizli bakiyeler

**Çalışma Alanı (Ekip CRM)**

* Müşteriler ve işler hakkında kanallar; görevler, plan panosu, iş takvimi ve notlar
* Hesabı olmayan müşteri ya da tedarikçi için tek seferlik veya süreli bağlantıyla açılan misafir odaları
* Koşullu ve zincirlenebilen programlanmış işlemler; e-postayla görev hatırlatmaları
* Kanallarda ve notlarda asistanlar (`@ai` ile Pinegrap AI ya da bağlanan Claude) görev ve kayıt değişikliği önerir, tek tıkla uygulanır

**Pazarlama ve İletişim**

* Mail-merge değişkenleri ve izin (opt-in) yönetimiyle zamanlanmış e-posta kampanyaları
* MailChimp senkronizasyonu, kişi yönetimi, satış ortaklığı ve komisyon takibi
* Kısa linkler (tek seferlik ve süreli bağlantılar dahil), canlı destek modülü ve web push bildirimli PWA

**Dış API**

* Anahtar başına izinler, secret, IP izin listesi ve hız sınırıyla `integration.php`; OpenAPI tanımı ve yerleşik konsol
* Ürün, sipariş, kişi, form, dosya, ERP, Çalışma Alanı, tasarım ve çeviri kaynakları; prova (dry-run) yazma, ETag ve `fields=`
* Gerçek zamanlı olaylar için webhook'lar ve mobil uygulamalar için ekip cihazı oturumları

**Güvenlik**

* **Yerleşik Web Uygulama Güvenlik Duvarı (WAF):** SQL enjeksiyonu, XSS, dizin aşma, uzaktan kod çalıştırma ve protokol kötüye kullanımı için imza taraması; genel, giriş / ödeme ve API hız sınırları, eşzamanlı istek sınırı, otomatik geçici yasaklar ve IPv6'yı (/64) anlayan izin / engel listeleri. İzleme kipinde başlar: engellemeye geçmeden önce günlükte neyin durdurulacağı görülür. Hata durumunda açık kalır: güvenlik duvarındaki bir hata siteyi asla düşürmez
* **Bot denetimi:** arama motoru tarayıcıları ters ve ileri DNS ile doğrulanır, sahte bir "Googlebot" yakalanır; yapay zekâ asistanları yayımladıkları IP aralıklarıyla denetlenir, yapay zekâ yanıtlarında ve aramasında görünmek için ayrı anahtarlar vardır; saldırgan tarayıcılar, yapay zekâ eğitim tarayıcıları, saldırı araçları (sqlmap, Nikto, …) ve betik istemcileri engellenebilir
* Güvenlik başlıkları ve ihlal raporlu İçerik Güvenliği Politikası (CSP); Cloudflare ve güvenilir proxy arkasında gerçek ziyaretçi IP'si çözülür
* CSRF token'ları, rol tabanlı erişim (Yönetici / Tasarımcı / Müdür / Kullanıcı), geliştirici PIN kilidi
* TLS doğrulamalı güncelleme kanalı — güncelleme paketleri sertifika doğrulaması olmadan asla kabul edilmez — ve GitHub'daki sürüm etiketine karşı dosya bütünlüğü denetimi

**Performans ve Operasyon**

* Yüzdelik dilim raporlu, istek seviyesinde performans izleyici
* Yüksek trafikli siteler için saatlik özet (rollup) tablolarıyla ziyaretçi istatistikleri
* Sağlık puanı ve tek tıkla onarımlarıyla (yazma izinleri, CA paketi) Sistem Durumu kartı
* Görsel optimizasyonu, Cloudflare entegrasyonu, otomatik yedekleme ve sürümlü veritabanı yükseltme sistemi

**Çoklu Dil**

* `lang()` çeviri sistemiyle tam İngilizce ve Türkçe yönetim arayüzü
* Çok dilli ön yüz (yukarıda); formlar, siparişler ve ziyaretçiye giden e-postalar ziyaretçinin dilinde kalır
* Türkçe karakterler için UTF-8 güvenli büyük/küçük harf dönüşümü ve ASCII güvenli URL üretimi (IIS'te önemli)

---

## Sistem Gereksinimleri

* **PHP:** 7.1'den 8.5'e kadar (en düşük sürüm 7.1; kurulum ve Sistem Durumu kartı daha eskisini işaretler)
* **Veritabanı:** MySQL / MariaDB
* **Web Sunucusu:** Apache (`mod_rewrite` ile), Nginx veya IIS
* **Eklentiler:** `mysqli`, `gd`, `curl`, `mbstring` (önerilen: yazılım güncellemeleri için `zip` ve `openssl`)

---

## Kurulum

1. **Depoyu Klonlayın**

   ```bash
   git clone https://github.com/kodpen/PinegrapCMS.git
   ```

2. **Web Sunucunuza Yükleyin**

   Dosyaları document root'a (veya bir alt dizine) yerleştirin. Apache'de paketle gelen `.htaccess`, IIS'te `web.config` URL yönlendirmeyi otomatik halleder.

3. **Veritabanı Oluşturun**

   Boş bir MySQL / MariaDB veritabanı ve üzerinde tam yetkili bir kullanıcı oluşturun.

4. **Kurulumu Çalıştırın**

   Tarayıcıdan `https://alan-adiniz.com/pinegrap/install/` adresini açın ve sihirbazı izleyin. Kurulum, şemayı oluşturur ve `data/config.php` dosyasını (otomatik üretilen şifreleme anahtarı dahil) sizin için yazar.

5. **Cron Görevlerini Ekleyin** (önerilir)

   ```cron
   * * * * * php /path/to/pinegrap/job.php
   */5 * * * * php /path/to/pinegrap/email_campaign_job.php
   ```

   `job.php` zamanlanmış yayınlama, terkedilmiş sepet kampanyaları, webhook gönderimi, Çalışma Alanı programlanmış işlemleri ve genel bakım işlerini yürütür; webhook'ların ve programlanmış işlemlerin zamanında çıkması için dakikada bir çalışmalıdır. `email_campaign_job.php` zamanlanmış e-posta kampanyalarını gönderir ve `data/config.php` içindeki `EMAIL_CAMPAIGN_JOB` sabitiyle etkinleştirilir.

---

## Güncelleme

Güncellemeler yönetim panelinden uygulanır. Şema değişiklikleri sürümlü yükseltme adımları olarak gelir ve aynı `install/` arayüzünden çalışır — elle SQL gerekmez. Her sürümde nelerin değiştiği için `changelog.txt` dosyasına bakın.

---

## Tarihçe

Pinegrap, hayatına 2001'den itibaren Camelback Web Architects tarafından geliştirilen **LiveSite** olarak başladı. 2017'den beri **Erdal Güral (Kodpen)** tarafından Pinegrap adıyla sürdürülüp geliştirilmektedir; son LiveSite güncellemesi (2019) tamamen entegre edilmiştir. LiveSite, ayrı bir legacy sürüm olarak erişilebilir durumdadır.

---

## Katkı ve güvenlik

Katkılar memnuniyetle karşılanır — kurulum, iş bitirme ölçütleri ve issue kuralları için [CONTRIBUTING.md](CONTRIBUTING.md) dosyasına bakın. Güvenlik bulguları [SECURITY.md](SECURITY.md) içinde anlatıldığı gibi gizli kanaldan bildirilir; lütfen bunlar için herkese açık issue açmayın.

---

## Lisans

[MIT Lisansı](license.txt) ile yayınlanmıştır.

Telif Hakkı © 2001–2019 Camelback Consulting, Inc. · © 2017–2026 Kodpen
