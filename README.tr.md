# Pinegrap CMS

[English](README.md) | **Türkçe**

![PHP Version](https://img.shields.io/badge/PHP-7.0%20--%208.5-777BB4?style=flat-square&logo=php)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)
![Status](https://img.shields.io/badge/Status-Active%20Development-success?style=flat-square)

Pinegrap CMS, LiveSite temeli üzerine inşa edilmiş açık kaynaklı bir içerik yönetim ve kurumsal web platformudur. 2017'den beri performans, esneklik ve geniş PHP uyumluluğu hedefiyle aktif olarak geliştirilmektedir.

---

## Genel Bakış

Pinegrap CMS; kurumsal web sitelerini, e-ticareti, kullanıcı yetkilerini ve özel dinamik içeriği yönetmek için tasarlanmış güçlü bir sistemdir. Paylaşımlı bir cPanel hosting'den özel bir IIS sunucusuna kadar, eski ve modern web ortamlarında yüksek güvenilirlikle çalışır.

### Gururla Monolitik

Pinegrap **bilinçli olarak monolitiktir** — ve bu bir özür değil, bir özelliktir.

* **Composer yok. Build pipeline yok. `node_modules` yok.** Dosyaları yükle, kurulumu çalıştır, bitti.
* **Tek kod tabanı, tek dağıtım.** Her şey birlikte gelir ve sürümlü, panel içi yükseltme sistemiyle birlikte güncellenir.
* **PHP'nin çalıştığı her yerde çalışır.** Kökleri 2001'e uzanan, gerektiğinde hâlâ düz FTP ile dağıtılabilen bir üretim kod tabanı.
* **Her satırı incelenebilir.** Hiç okumadığınız on bin dosyalık bir vendor klasörü yoktur.

### Öne Çıkanlar

* **Geniş Uyumluluk:** PHP 7.0'dan PHP 8.5'e kadar sorunsuz çalışır.
* **Sunucu Desteği:** Apache, Nginx ve Microsoft IIS ile uyumludur (otomatik yönlendirme ve `.htaccess` / `web.config` rewrite desteği dahil).
* **Kurumsal Mimari:** Güçlü form yönetimi (`liveform`), dinamik sayfa motoru ve güvenli oturum yönetimi.
* **Özelleştirilebilir Arayüz:** Modern temalar ve Bootstrap 5 entegrasyonu için yerleşik destek.

---

## Özellikler

**İçerik ve Tasarım**

* Sayfa, ortak, tasarımcı ve dinamik bölgelerden oluşan dinamik sayfa motoru — içerik, sayfanın kendisi üzerinde yerinde düzenlenir
* Sürükle-bırak sistem widget'larıyla (katalog, sepet, hızlı sipariş, sipariş görünümü) görsel tema ve stil tasarımcısı; sihirli CSS sınıfları yerine veri bağlama (data binding) mimarisi
* Blog / makale yayınlama, foto galeriler, menüler, zamanlanmış yayınlanabilen yorumlar ve site içi arama
* Yinelenen etkinlik, mekân ve rezervasyon destekli takvimler
* Panelden çıkmadan görsel kırpma, boyutlandırma ve tasarım yapılabilen yerleşik görsel editörü

**E-ticaret**

* İç içe ürün grupları, varyant setleri ve ürün formu şablonlarıyla ürün kataloğu
* Kampanyalar, kuponlar, hediye kartları, çapraz satış ve terkedilmiş sepet otomatik kampanyaları
* Tam sipariş yaşam döngüsü: iptal akışları, iade (Iyzipay entegrasyonu), Türk kargo firmaları için takip linkleri, yazdırılabilir fatura ve sipariş zaman çizelgesi
* Barkod tabanlı stok işlemleri

**Pazarlama ve İletişim**

* Mail-merge değişkenleri ve izin (opt-in) yönetimiyle zamanlanmış e-posta kampanyaları
* MailChimp senkronizasyonu, kişi yönetimi, satış ortaklığı ve komisyon takibi
* Kısa linkler, canlı destek modülü ve özel entegrasyonlar için REST API (`apps.php`)

**Güvenlik**

* Yerleşik Web Uygulama Güvenlik Duvarı (WAF): imza taraması, hız sınırlama, IP itibarı, bot sınıflandırma ve IPv6 destekli IP yasakları
* CSRF token'ları, rol tabanlı erişim (Yönetici / Tasarımcı / Müdür / Kullanıcı), geliştirici PIN kilidi
* TLS doğrulamalı güncelleme kanalı — güncelleme paketleri sertifika doğrulaması olmadan asla kabul edilmez

**Performans ve Operasyon**

* Yüzdelik dilim raporlu, istek seviyesinde performans izleyici
* Yüksek trafikli siteler için saatlik özet (rollup) tablolarıyla ziyaretçi istatistikleri
* Görsel optimizasyonu, Cloudflare entegrasyonu, otomatik yedekleme ve sürümlü veritabanı yükseltme sistemi

**Çoklu Dil**

* `lang()` çeviri sistemiyle tam İngilizce ve Türkçe arayüz
* Türkçe karakterler için UTF-8 güvenli büyük/küçük harf dönüşümü ve ASCII güvenli URL üretimi (IIS'te önemli)

---

## Sistem Gereksinimleri

* **PHP:** 7.0'dan 8.5'e kadar
* **Veritabanı:** MySQL / MariaDB
* **Web Sunucusu:** Apache (`mod_rewrite` ile), Nginx veya IIS
* **Eklentiler:** `mysqli`, `gd`, `curl`, `mbstring` (önerilen: yazılım güncellemeleri için `zip` ve `openssl`)

---

## Kurulum

1. **Depoyu Klonlayın**

   ```bash
   git clone https://github.com/kodpen/pinegrap.git
   ```

2. **Web Sunucunuza Yükleyin**

   Dosyaları document root'a (veya bir alt dizine) yerleştirin. Apache'de paketle gelen `.htaccess`, IIS'te `web.config` URL yönlendirmeyi otomatik halleder.

3. **Veritabanı Oluşturun**

   Boş bir MySQL / MariaDB veritabanı ve üzerinde tam yetkili bir kullanıcı oluşturun.

4. **Kurulumu Çalıştırın**

   Tarayıcıdan `https://alan-adiniz.com/pinegrap/install/` adresini açın ve sihirbazı izleyin. Kurulum, şemayı oluşturur ve `data/config.php` dosyasını (otomatik üretilen şifreleme anahtarı dahil) sizin için yazar.

5. **Cron Görevlerini Ekleyin** (önerilir)

   ```cron
   */5 * * * * php /path/to/pinegrap/job.php
   */5 * * * * php /path/to/pinegrap/email_campaign_job.php
   ```

   `job.php` zamanlanmış yayınlama, terkedilmiş sepet kampanyaları ve genel bakım işlerini yürütür. `email_campaign_job.php` zamanlanmış e-posta kampanyalarını gönderir ve `data/config.php` içindeki `EMAIL_CAMPAIGN_JOB` sabitiyle etkinleştirilir.

---

## Güncelleme

Güncellemeler yönetim panelinden uygulanır. Şema değişiklikleri sürümlü yükseltme adımları olarak gelir ve aynı `install/` arayüzünden çalışır — elle SQL gerekmez. Her sürümde nelerin değiştiği için `changelog.txt` dosyasına bakın.

---

## Tarihçe

Pinegrap, hayatına 2001'den itibaren Camelback Web Architects tarafından geliştirilen **LiveSite** olarak başladı. 2017'den beri **Erdal Güral (Kodpen)** tarafından Pinegrap adıyla sürdürülüp geliştirilmektedir; son LiveSite güncellemesi (2019) tamamen entegre edilmiştir. LiveSite, ayrı bir legacy sürüm olarak erişilebilir durumdadır.

---

## Lisans

[MIT Lisansı](license.txt) ile yayınlanmıştır.

Telif Hakkı © 2001–2019 Camelback Consulting, Inc. · © 2016–2026 Kodpen
