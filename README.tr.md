# Pinegrap CMS

[English](README.md) | **Türkçe**

![PHP Sürümü](https://img.shields.io/badge/PHP-7.1%20--%208.5-777BB4?style=flat-square&logo=php)
![Veritabanı](https://img.shields.io/badge/MySQL%205.7%2B%20%7C%20MariaDB%2010.2%2B-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Lisans](https://img.shields.io/badge/Lisans-MIT-green?style=flat-square)
![Durum](https://img.shields.io/badge/Durum-Aktif%20Geli%C5%9Ftirme-success?style=flat-square)

Pinegrap, PHP ile yazılmış, açık kaynaklı, hepsi bir arada bir web sitesi platformudur. Kendi tasarımıyla bir web sitesi, online mağaza, ön muhasebe (ERP), ekip çalışma alanı ve dış API tek kod tabanında yer alır; hazır bir tasarım şablonuyla yeni bir site dakikalar içinde yayına hazır olur.

Pinegrap, LiveSite'tan doğdu ve 2017'den beri Kodpen tarafından geliştirilmektedir. Paylaşımlı bir cPanel hesabından özel bir IIS sunucusuna kadar PHP'nin çalıştığı her yerde çalışır; Composer adımı, build pipeline ya da kurulacak bir dış hizmet gerekmez.

---

## Bağlantılar

| | |
|---|---|
| **Web sitesi** | [pinegrap.com](https://pinegrap.com) |
| **Özellikler** | [pinegrap.com/features](https://pinegrap.com/features) |
| **Dokümantasyon** | [pinegrap.com/introduction](https://pinegrap.com/introduction) |
| **Değişiklikler** | [pinegrap.com/changelog](https://pinegrap.com/changelog) |
| **Canlı demo** | [demo.pinegrap.site](https://demo.pinegrap.site) |

---

## Demoyu deneyin

[demo.pinegrap.site](https://demo.pinegrap.site), kurmadan önce denemek isteyenler için hazırlanmış bir **demo kurulumudur**: siteyi gezin, yönetim panelini açın ve görsel editörle bir sayfa oluşturun. Lütfen orada gerçek kişisel bilgi ya da ödeme bilgisi girmeyin.

---

## Neler var?

Kısa bir özet; tam liste [pinegrap.com/features](https://pinegrap.com/features) sayfasında.

* **Görsel sayfa editörü ve tasarım şablonları:** Bootstrap 5 ya da özel CSS ile sürükle-bırak sayfalar, görünümler ve renk paletleri, ortak bileşenler, taslaklar, HTML / ZIP içe aktarma ve kurulum sırasında seçilebilen hazır şablonlar (Pinegrap'a merhaba deyin, Online Mağaza, Kitapçı, Butik, Kafe, Klinik, Oyun Bahçesi).
* **İçerik:** sayfalar, blog, foto galeriler, menüler, yorumlar, formlar, rezervasyonlu takvimler, site içi arama, görsel araçlarıyla dosya yöneticisi ve silinen sayfalar, sayfa stilleri ve dosyalar için Geri Dönüşüm Kutusu.
* **Çok dilli siteler:** sayfalar, mağaza, formlar ve e-postalar `/en/` gibi bir önek altında başka dillerde sunulur; birden çok çeviri motoruyla Çeviriler ekranı ve otomatik dil yönlendirmesi.
* **E-ticaret:** varyantlı ürün kataloğu, kampanyalar, kuponlar ve hediye kartları, kayıtlı adreslerle ödeme ekranı, Iyzipay, Stripe ve PayPal gibi ödeme altyapıları, kargo ücretleri ve takibi, satış noktası (POS) ekranı ve n11 pazaryeri senkronu.
* **ERP:** müşteri ve tedarikçi carileri, faturalar, teklifler, irsaliyeler, satın alma siparişleri, stok, kasa ve banka, raporlar; Logo İşbaşı ya da Paraşüt üzerinden e-Fatura / e-Arşiv.
* **Çalışma Alanı:** ekip kanalları, görevler, plan panosu, takvim, notlar, misafir odaları, onaylar, zaman takibi ve programlanmış işlemler; kanallarda ve görevlerde Pinegrap AI ya da Claude yardım eder.
* **API ve entegrasyonlar:** izinli anahtarlar, OpenAPI tanımı ve webhook'larla dış API; Claude ve ChatGPT gibi yapay zekâ uygulamalarının kişinin kendi yetkileriyle bağlanabilmesi için OAuth 2.1 yetkilendirme sunucusu ve MCP sunucusu; e-posta ve yedekler için bağlı Google ve Microsoft hesapları; MailChimp ve Cloudflare.
* **Giriş ve güvenlik:** Google, Microsoft ya da Apple ile giriş, iki adımlı giriş, roller ve bot denetimi, hız sınırları, ülke kısıtlaması ve güvenlik başlıklarıyla yerleşik web uygulama güvenlik duvarı.
* **Operasyon:** ziyaretçi istatistikleri, SEO puanı ve Google Search Console raporu, performans izleyici, tek tıkla onarımlarıyla Sistem Durumu, uzak kopyalı zamanlanmış yedekler, e-posta kuyruğu ve yönetim panelinden güncelleme.
* **Yönetim paneli:** İngilizce ve Türkçe, uygulama olarak kurulabilir; her ekrandaki "?" düğmesi o ekranın pinegrap.com'daki dokümantasyonunu açar.

### Gururla monolitik

* **Composer yok, build pipeline yok, `node_modules` yok.** Dosyaları yükle, kurulumu çalıştır, bitti.
* **Tek kod tabanı, tek dağıtım.** Her şey birlikte gelir ve sürümlü yükseltme adımlarıyla birlikte güncellenir.
* **PHP'nin çalıştığı her yerde çalışır.** Kökleri 2001'e uzanan, gerektiğinde hâlâ düz FTP ile dağıtılabilen bir üretim kod tabanı.

---

## Sistem gereksinimleri

| | |
|---|---|
| **PHP** | 7.1'den 8.5'e kadar |
| **Veritabanı** | MySQL 5.7 veya üstü ya da MariaDB 10.2 veya üstü (kurulum MySQL 5.6 ve MariaDB 10.1'i kabul etmez) |
| **Web sunucusu** | `mod_rewrite` ile Apache ya da LiteSpeed, URL Rewrite ile Microsoft IIS ya da nginx |
| **PHP eklentileri** | `mysqli`, `mbstring`, `openssl`, `curl`; görseller için GD ya da Imagick; güncelleme, yedek ve içe aktarma için `zip` |
| **Yazma izni** | Yapılandırma, yedekler ve geçici dosyalar için `pinegrap/data/`; yönetim panelinden güncelleme için `pinegrap/` klasörünün tamamı |

---

## Kurulum

### 1. Dosyaları edinin

**İndirme Asistanı (en hızlısı):** [`download_assistant.php`](download_assistant.php) dosyasını web kök dizininize yükleyin, `https://alan-adiniz.com/download_assistant.php` adresini açın ve **Install** düğmesine tıklayın. Asistan son kararlı paketi indirir, `pinegrap/` klasörüne açar ve kurulum sihirbazını başlatır.

**GitHub'dan:** son sürümü [GitHub Releases](https://github.com/kodpen/PinegrapCMS/releases) sayfasından indirin ya da depoyu klonlayın ve dosyaları document root'a (veya bir alt klasöre) yükleyin. `main` dalı yayınlanmış sürümleri taşır; süren çalışmalar `development` dalına gelir.

```bash
git clone https://github.com/kodpen/PinegrapCMS.git
```

### 2. Kurulum sihirbazını çalıştırın

1. Boş bir MySQL / MariaDB veritabanı ve üzerinde tam yetkili bir kullanıcı oluşturun.
2. `https://alan-adiniz.com/pinegrap/install/` adresini açın ve altı adımı izleyin: kaynak (başlangıç sitesi ya da sunucudaki bir yedekten veya zip dosyasından geri yükleme), yönetim paneli dili, site tasarımı (başlangıç sitesi ya da bir tasarım şablonu), veritabanı, ilk yönetici hesabı ve kurulum.
3. Kurulum tabloları oluşturur, üretilen şifreleme anahtarıyla `pinegrap/data/config.php` dosyasını yazar ve web sunucusu kurallarını yazar: Apache'de `.htaccess`, IIS'te `web.config`; nginx'te ise sunucu bloğunuza kopyalamanız için `nginx.conf.sample`.

### 3. Genel görevi zamanlayın (önerilir)

```cron
* * * * * php /path/to/pinegrap/job.php
```

Tek bir zamanlanmış görev yeterlidir. **Ayarlar › Genel › Cron işleri** bölümünde **Cron İşlerini Otomatik Yürüt** seçeneğini açın ve genel görevin sizin yerinize, her turda bir tane olmak üzere çalıştıracağı işleri seçin: e-posta kampanyaları, webhook gönderimi, arama dizini, SEO denetimleri, yedekler ve diğerleri. Her işi ayrı zamanlamak isterseniz aynı kart her birinin tam komutunu listeler (Windows'ta Görev Zamanlayıcı'yı kullanın).

---

## Docker ile deneyin

[`docker/`](docker/) klasöründe bir Docker Compose yığını var: nginx, PHP-FPM, genel görev ve MariaDB 10.11, her biri kendi konteynerinde. Pinegrap'i kendi bilgisayarınızda denemenin en hızlı yolu budur; yönettiğiniz bir sunucuda siteyi bununla da çalıştırabilirsiniz. Asıl kurulum yolu yukarıdaki kurulumdur.

```bash
git clone https://github.com/kodpen/PinegrapCMS.git
cd PinegrapCMS
docker compose -f docker/docker-compose.yml up -d --build
```

`http://localhost:8080/pinegrap/install/` adresini açın ve kurulum sihirbazını çalıştırın. Veritabanı adımında **Veritabanı Sunucusu** alanına `db` yazın; veritabanı adı, kullanıcı adı ve parola `pinegrap` (ya da `PG_DB_NAME`, `PG_DB_USER` ve `PG_DB_PASSWORD` ile verdikleriniz).

**Sihirbazsız:** ilk yöneticiyi ilk başlatmadan önce verin, başlangıç sitesi sizin yerinize kurulsun:

```bash
PG_INSTALL_ADMIN_EMAIL=siz@example.com PG_INSTALL_ADMIN_PASSWORD='uzun bir parola seçin' \
PG_INSTALL_STARTER=turkish_default \
  docker compose -f docker/docker-compose.yml up -d --build
docker compose -f docker/docker-compose.yml logs install
```

Site bundan sonra `http://localhost:8080/` adresinde, yönetim paneli `http://localhost:8080/pinegrap/` adresindedir.

* **Ayarlar** kabuktan ya da `docker/.env` dosyasından okunur: `PG_HTTP_PORT` (8080), `PG_DB_PASSWORD` (`pinegrap`; deneme dışında ilk başlatmadan önce kendi parolanızı verin), `PG_INSTALL_STARTER` (`english_default` ya da `turkish_default`) ve [`docker/docker-compose.yml`](docker/docker-compose.yml) dosyasının başında listelenen diğerleri.
* **Verileriniz** iki Docker biriminde (volume) kalır: `pinegrap_data` (yapılandırma, yüklenen dosyalar, yedekler) ve `pinegrap_db`. `docker compose -f docker/docker-compose.yml down` siteyi durdurur ve birimleri korur; `down -v` siteyi siler.
* **Güncelleme:** yazılım imajdan gelir. Yeni sürümü alın (`git pull` ya da yeni sürüm dosyaları) ve aynı `up -d --build` komutunu çalıştırın. Veritabanı adımları, yönetim panelinin bir sonraki girişte açtığı yükseltme ekranından ya da hemen `docker compose -f docker/docker-compose.yml exec -u www-data app php pinegrap/install/index.php automated_upgrade` ile çalışır. Yönetim panelinden kurulan bir güncelleme yalnız konteynerler yeniden oluşturulana kadar kalır.

---

## Güncelleme

Güncellemeler yönetim panelinden (**Yazılım Güncelleme**), Kararlı ya da Beta kanalından kurulur. Veritabanı değişiklikleri kendiliğinden çalışan sürümlü yükseltme adımları olarak gelir; elle SQL gerekmez. Bir güncelleme eksik dosya bırakırsa **Yazılımı Onar** sürüm dosyalarını geri yükler ve veritabanı adımlarını yeniden çalıştırabilir.

Her sürümde nelerin değiştiği: [pinegrap.com/changelog](https://pinegrap.com/changelog).

---

## Tarihçe

Pinegrap, hayatına 2001'den itibaren Camelback Web Architects tarafından geliştirilen **LiveSite** olarak başladı. 2017'den beri **Erdal Güral (Kodpen)** tarafından Pinegrap adıyla sürdürülüp geliştirilmektedir; son LiveSite güncellemesi (2019) tamamen entegre edilmiştir. LiveSite, ayrı bir legacy sürüm olarak erişilebilir durumdadır.

---

## Katkı ve güvenlik

Katkılar memnuniyetle karşılanır: kurulum, iş bitirme ölçütleri ve issue kuralları için [CONTRIBUTING.md](CONTRIBUTING.md) dosyasına bakın. Pull request'ler `development` dalına açılır. Güvenlik bulguları [SECURITY.md](SECURITY.md) içinde anlatıldığı gibi gizli kanaldan bildirilir; lütfen bunlar için herkese açık issue açmayın.

---

## Lisans

[MIT Lisansı](license.txt) ile yayınlanmıştır.

Telif Hakkı © 2001–2019 Camelback Consulting, Inc. · © 2017–2026 Kodpen
