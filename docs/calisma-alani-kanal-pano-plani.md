# Çalışma Alanı — kanalı pano (board) görünümünde açma: plan

Durum: **yalnız plan** (plan maddesi 24, 2026-09-27). Kod yazılmadı.

## 1. Amaç

Bir kanalın işini tek bakışta görmek: kanalın görevleri, kararları ve
dosyaları sütunlarda, kartlar hâlinde. Bugün bunlar kanalın ayrı sekmelerinde
(Mesajlar, Kararlar, Görevler, Dosyalar, Özet) liste olarak duruyor; Plan
Panosu (`ws_board`) ise kişi × gün düzeninde bütün şirketin işini gösteriyor.
Kanal panosu ikisinin arasını doldurur: tek kanal, duruma göre sütunlar.

Mesajlaşma yapısından ödün verilmez: pano kanalın **bir sekmesi**dir, yazı
kutusu ve mesaj akışı olduğu gibi kalır.

## 2. Görünüm

- Kanal başlığındaki sekmelere **Pano** eklenir (`tab_board`), adres
  `workspace.php?channel=N&view=board` (paylaşılabilir, geri tuşu çalışır).
- **Gruplama seçici** (panonun üstünde):
  1. **Duruma göre** (varsayılan): Yapılacak · Sürüyor · Bekliyor · Bitti
     (`ws_task_statuses()`; İptal gizli, süzgeçle açılır). Yalnız görevler.
  2. **Türe göre:** Görevler · Kararlar · Dosyalar · Sabit mesaj ve özet.
  3. **Kişiye göre:** kanal üyeleri sütun, görevler üzerlerindeki kişiye
     göre; kimsesiz görevler "Atanmamış".
  4. **Tarihe göre:** Gecikmiş · Bugün · Bu hafta · Sonra · Tarihsiz.
- **Kart:** başlık, numara (G-12), kişiler (baş harf avatarları), bitiş
  etiketi (saatle, 5.111), öncelik rengi, kontrol listesi ilerlemesi, not
  sayısı, tekrar simgesi, hatırlatma zili. Karar kartı: kararın ilk satırı,
  kimin ne zaman aldığı. Dosya kartı: ad, küçük resim, yükleyen.
- **Süzgeçler:** aciliyet (Plan Panosu'ndaki süzgeçlerle aynı bileşen),
  kişi, "yalnız benimkiler", metin araması.
- Dar ekranda sütunlar yatay kaydırılır; 768 px altında sütun seçici ile tek
  sütun.

## 3. Etkileşim

- **Sürükle-bırak:** durum sütunları arasında → `ws_task_set_status()`;
  kişi sütunları arasında → atama (`ws_task_update` `assignees`, izin
  kuralları `ws_can_assign_to` ve çakışma denetimi `ws_assignment_check`
  aynen; onay penceresi Plan Panosu'ndakiyle aynı); tarih sütunları
  arasında → bitiş tarihi (tekrarlayan görevde Plan Panosu'nun "yalnız bu
  kopya / seri" sorusu). Klavye: kart odaktayken Boşluk ile tut, oklarla
  taşı, Enter ile bırak (erişilebilirlik).
- Karta tıklamak mevcut görev çekmecesini açar; karar kartı mesaja gider.
- Sütun başında "+" → o sütunun değeriyle önceden doldurulmuş yeni görev
  (durum / kişi / tarih).
- Sıralama: sütun içinde öncelik, sonra bitiş. İlk sürümde elle sıralama
  yok (şema gerektirir: `ws_tasks.board_rank`).

## 4. Veri

- Yeni eylem `ws_channel_board` (`actions.php`): `channel_id`, `group_by`,
  süzgeçler → `{columns: [{key, title, items: [...]}], counts}`. Görevler
  `ws_tasks_list($viewer, ['scope' => 'channel', 'channel_id' => N, ...])`
  ile (okuma yetkisi orada), kararlar `ws_messages.kind = 'decision'`,
  dosyalar `ws_channel_files` ile aynı kaynak.
- Yenilenme: kanalın mevcut yoklaması (`ws_sync`) görevlerin `updated_at`'ini
  zaten izliyor; pano açıkken değişen kartlar tek tek yenilenir.
- Dış API: ilk sürümde gerek yok (görev ve karar uçları zaten var).
- **Şema gerekmez** (elle sıralama ve kişiye özel "son görünüm" hariç;
  ikincisi `ws_channel_members.view` ile ileride).

## 5. Dosyalar

- `assets/js/workspace_board_channel.js` (yeni; `workspace.js` zaten çok
  büyük): sekme, sütunlar, sürükle-bırak (Plan Panosu'nun sürükleme
  yardımcıları ortaklaştırılarak).
- `includes/workspace/channel_board.php` (yeni): eylemin verisi, sütun
  tanımları, JS metinleri.
- `screen.php`: sekme adı, betik etiketi; `backend.src.css`: `.ws-cb-*`.
- Tur (`tour.php`): "Kanal" adımının metnine pano sekmesi eklenir, tur
  anahtarı `workspace.2` olur (herkes yeni hâlini bir kez görür).

## 6. Aşamalar

1. Duruma göre gruplama, kartlar, çekmece, süzgeçler (salt okunur + tıklama).
2. Sürükle-bırak (durum), klavye desteği.
3. Kişiye ve tarihe göre gruplama, onay pencereleri.
4. Türe göre gruplama (kararlar, dosyalar).
5. İsteğe bağlı: elle sıralama (şema), kişiye özel varsayılan görünüm.

## 7. Açık sorular

- Pano sekmesi her kanalda mı, yoksa kanal ayarından açılan bir seçenek mi?
- Misafir kanalında (plan maddesi 22, `docs/calisma-alani-misafir-kanal-tasarim.md`) pano misafire gösterilmez; personel
  için olağan.
- "Bitti" sütunu ne kadar geriye gitsin (öneri: son 14 gün, "daha fazla").
