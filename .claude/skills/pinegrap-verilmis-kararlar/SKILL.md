---
name: "pinegrap-verilmis-kararlar"
description: "Pinegrap CMS'te denetim yaparken, 'ölü kod' / 'kullanılmayan alan' / 'eksik kontrol' bulgusu yazmadan ya da temizlik-refactor önermeden önce oku. Zaten incelenmiş ve öyle kalmasına karar verilmiş yerlerin listesi."
---

# Pinegrap — verilmiş kararlar

Aşağıdaki yerler **incelenmiş ve öyle kalmasına karar verilmiştir.** Kodda
gerekçesi görünmediği için bunlar bir denetimde tekrar tekrar "ölü kod",
"kullanılmayan alan" ya da "eksik kontrol" diye bulunur. Bulunmaları bir bulgu
değildir; karar zaten verilmiştir.

**Değiştirmeyin, temizlemeyin, "iyileştirmeyin".** Bir maddenin yanlış olduğuna
gerçekten inanıyorsanız uygulamadan **önce** sorun; sessizce değiştirmek bu
kuralın ihlalidir.

| Yer | Karar |
|---|---|
| `data/backups/` | İçerik ve düzen dokunulmaz. **İstisna (2026-09-21):** başlangıç dökümlerindeki kimlik bilgisi alanları boş gelir — `usps_user_id`, `ups_user_id`, `ups_password`, `ecommerce_authorizenet_api_login_id`, `ecommerce_authorizenet_transaction_key`. Depo herkese açık; oraya değer yazılmaz. |
| `get_express_order.php` ve `get_order_preview.php` içindeki hesaplama | Değiştirilmez: canlıda o özelliği kullanan siteler var, değişiklik hesaplamalarını bozar. |
| `frontend.*.css`, `pinegrap.*.css`, `livesite.min.css` | Silinmez. Hiçbir `<link>` satırından sunulmuyor görünürler; ön yüzde HTML içeriğin içinden çağrılıyorlar. |
| `get_help_url.php` içindeki switch'in dikkate alınmaması | Kasten. Doküman sitesi kapalı olduğu için tek bir PDF'e gidiliyor; site hazır olunca değişecek. |
| `barcode_decrease_inventory.php` — "evet" dönüşü | Özelliktir, hata değildir. |
| `orders.notes`, `orders.tracking_company` | Kullanılmıyor görünür; ERP için duran alanlardır, devamı gelecek. |
| `pi.php`, `si.php` | Herkese açık kalır. Oturum ya da rol kapısı eklenmez; bir denetimde "kimlik doğrulamasız açık" diye bulunmaları beklenen durumdur. |
| `submit_order.php` — misafir siparişinin, fatura e-postası eşleşen mevcut hesaba doğrulamasız bağlanması | Özelliktir (sipariş geçmişi). Giriş ya da e-posta doğrulama şartı eklenmez (#67). |
| `backups.php` ve `api.php` `software_backup` — manager (rol ≤ 2) kapısı | Kalır; politika "manager ve üstü" (#62). |
| `includes/settings/prep.php` — Google Client Secret'ın Güvenlik formuna geri render edilmesi | Operatör tercihi; kural 10'un "sırrı geri render etme" maddesinin yazılı istisnası (#62). |
| `edit_calendar.php`, `edit_contact_group.php` — rol 3'ün kendisine atanan takvim/grubu yeniden adlandırması ve (boşsa) silmesi | Kalır; oluşturma yasağı ayrı karardır (#59). |
| `pg_write_permission_repair()` — klasör 0777 / dosya 0666 | Kalır; gerekçe `docs/CLAUDE-tam.md` "Onarım" satırında (#59). |
| `test_secure_mode.php` — `init.php` yüklemez, oturum/anahtar kapısı yok | Kalır; sayfa tam da site kilitliyken cevap vermek için böyle tasarlandı (#70). |
| `pg_curl_tls()` — `ALLOW_INSECURE_UPDATE_TLS` bayrağı ödeme, lisans ve kargo çağrılarını da kapsar | Kalır; operatörün config.php'de açıkça verdiği tek son çare (#70). |
| `editor_select_image.php` — `UNSPLASH_ACCESS_KEY` istemciye basılır | Tasarım gereği: Access Key istemci tarafı client_id'dir, gizli olan Secret Key'dir (#62). |
| `software_update.php` — `VERSION === '2026'` bloğu | Kaldırılmaz. Noktasız `2026` gerçek bir ara duraktır: eski sürümden (örn. 2025.2) gelen kurulum yükseltmeye başlamadan önce burada durur, `config.php` ve diğer veri yollarını `data/` altına taşır, sonra güncelleme kanalı değişir. "Hiçbir noktalı sürüm bunu sağlamıyor" diye ölü kod sanılmıştır. |
| `myself_upsell.php` | Şimdilik kalır. Dikkatli inceleme sonucu netleşirse `clean_up`'a eklenebilir, kendi başınıza silmeyin. |
| `lang()` → `pg_tr_ui_text()` — dil dosyası olmayan ön yüz dilinde yazılımın metni çeviri deposundan | Karar (ürün sahibi, 2026-10-06): yeni bir dil için dil dosyası eklenmez, site kendi çevirisini yapar; dosyası olan dilde dosya geçerlidir. `lang()` içindeki veritabanı erişimi bu yüzdendir (istek başına bir okuma, yeni metinler istek sonunda toplu yazılır). |
| `pg_tr_placeholder_latin()` — Lorem ipsum yer tutucusu çeviriye girmez | Karar (ürün sahibi, 2026-10-06). Lorem metni listede ve motor işlerinde görünmez; "çevrilmeyen metin" bulgusu değildir. |
| Görsel editör — yayındaki sayfada "Kaydet" yok, tek düğme "Yayınla"; değişiklik hemen yayına girer | Karar (ürün sahibi, 2026-10-06): WordPress düzeni — yeni sayfa taslak başlar, taslakta Kaydet + Yayınla. Yayındaki sayfa için ayrı çalışma kopyası (Webflow düzeni) bilerek yapılmadı; "Kaydet canlıya gidiyor" bulgusu değildir. |

Aynı kural yazılı diğer kararlar için de geçerlidir: yayınlanmış bir sürümün
migration'ı, o güncellemeyi henüz almamış siteler için düzeltilebilir (sürümü
geçmiş kurulumun o adımla işi yoktur). Ürün MySQL 5.7'yi desteklemeye devam
eder; yalnız daha yenisinde çalışan sözdizimi kullanılmaz.

---

## Kasıtlı görünen başka şeyler

- `STRUTURED_DATA` sabitinin bozuk yazımı tutarlıdır — düzeltme.
- `user.secret_key*` kolonları bilerek duruyor; hiçbir kod okumaz.
- `apps.php`, `apps_settings.php` ve `custom_apps` tablosu 2026.4.4'te
  kaldırıldı — geri ekleme.
- Pano widget id'lerinde **22 ve 24 emekli kalır**; yeniden kullanılmaz.
- `order_items.tax` artık yazılmıyor (yeni satırlarda 0, 2026.5.0'da düşecek);
  0 vergi görüyorsan muhtemelen yanlış kolonu okuyorsun.
- `orders.tracking_code` kampanya kodudur, kargoyla ilgisi yoktur; kargo takip
  numarası `shipping_tracking_numbers` tablosundadır.
- `escape_url()` **gösterim** için doğrular ve `//`, `http://`, `https://`
  adreslerini bilerek geçerli sayar — sıkılaştırmak `http_referer` basan
  ekranları bozar.
- `robots.txt` ile engellenen sayfanın `noindex` etiketi okunmaz; bu bilinen bir
  ödündür, hata değil.
- Legacy custom style katmanı (`get_catalog.php`, `get_shopping_cart.php`,
  custom style şablonları) **production'dır** — dokunulmaz ve sistem widget'ının
  doğru davranışı için ölçüt olarak kullanılır. Buna karşılık sistem widget
  katmanında geriye dönük uyumluluk için ölü kod tutulmaz.
- Ödeme ve kargo API çağrıları `pg_curl_tls()` sertleştirmesinin bilerek
  dışındadır.
- ERP'de bugün bilerek yapılmayanlar için `pinegrap-erp` skill'inin son
  bölümüne bak — eksiklik değil, faz sınırıdır.

## Yeni bir karar çıkarsa

Bu listeye **ekleme yapan** taraf ürün sahibidir. Bir denetimde "bu da karar mı?"
sorusu doğduysa bulguyu yazma, PR açıklamasına soru olarak koy. Kabul edilen
karar buraya ve `docs/degisiklikler.md`'ye yazılır.
