# Dağıtım durumu (arşiv, 2026-10-14)

`docs/degisiklikler.md` başındaki bölümün son hâli. Güncel durum artık `docs/memory/release/release-status.md` dosyasındadır.

**Yayındaki en güncel sürüm: 2026.4.9** — GitHub release `v2026.4.9`
(PR #238, etiket `1c3703e` — PR'ın birleştirme commit'i, 2026-10-13; önceki
2026.4.8 `348b8a2`, 2026-10-11; 2026.4.7 `8eb2716`, 2026-10-06; 2026.4.6
`a05c986`, 2026-10-05). Release dalı (`release/2026.4.9`) main'in üstünde
tek bir "Release 2026.4.9" commit'idir (`1534890`): ağacı `development`'ınkidir
(`96072dd`), yalnız `CLAUDE.md`, `.claude/`, `docs/`, `tools/` ve `tests/`
çıkarılmıştır; ağaç commit edilmeden önce çalışma kopyasına dokunmadan
(geçici index + `commit-tree`) kuruldu, dev makine `development`'ta kaldı.
Release notu `Claude outputs/release-notes-v2026.4.9.md` (depo dışı).
`tests/` ve `tools/` 2026.4.8'de yayına girmiş, aynı gün PR #237 ile
main'den kaldırılmıştı (ürün sahibinin kararı); ikisi yalnız
`development`'ta izlenir. GitHub kontrolünün (`php-checks.yml`) PHP adımları
`tools/` olmayan dalda atlanır; PR'lar `development`'a açılır
(`CONTRIBUTING.md`). 2026.4.9 artık **kapalıdır**: `2026.4.9.php`'nin
getirdiği şemaya, `versions.php` satırına ve changelog başlığına dokunulmaz.
Etiket taşınmaz — bütünlük denetiminin referansı bu etiketin ağacıdır;
sunucu paketi de bu etiketten üretilir.

**Yayına girmeyenler (ürün sahibinin kararı, 2026-10-05):** barındırma
platformuna (pinegrap.site) özgü özellikler — barındırılan site kipi
(`PG_HOSTED`) ve tanıtım sitesi kipi (`PG_DEMO`) — normal kullanıcıya değer
taşımadığı için `changelog.txt`'ye, README'ye ve GitHub release notuna
yazılmaz; bu dosyadaki ayrıntılı kayıtları kalır. Platformun kendi dosyaları
`dev/hosting/` altındadır ve `/dev/` ignore'u sayesinde depoya girmez.
Üründeki `pg_hosted()` / `pg_demo()` kontrolleri sabitler tanımlanmadıkça
etkisizdir ve yerinde kalır. 2026.4.6 yayınında bu yüzden changelog'daki
"Barındırılan site ve tanıtım sitesi" bölümü kaldırıldı, genel güvenlik
maddeleri "Oturum, hesap ve güvenlik"e taşındı.

**Açık sürüm: 2026.4.10** (2026-10-13'te ürün sahibinin isteğiyle açıldı:
`versions.php` satırı ve `changelog.txt` başlığı; migration dosyası
`includes/migrations/2026.4.10.php` ilk şema adımıyla — OAuth, 10.70 —
açıldı, alt adımlar `10.x`). 2026.4.9 2026-10-13'te yayınlandı ve kapandı.
Bir sürüm ilk şema adımı ya da changelog maddesiyle açılır: yeni
`includes/migrations/<sürüm>.php` → `upgrade_to_<sürüm>()`, `versions.php`'ye
satır, `changelog.txt`'de en üste yeni sürüm başlığı, bu dosyada
`## <sürüm> — …` bölümleri; numara ürün sahibinindir. Alt adım etiketleri
önceki sürümlerin aralık düzenini yeni önekle korur (genel işler .1'den,
ERP .58–.69 ve .90'dan sonrası, API .70–.79, Çalışma Alanı .80–.89 ve
.110–.119). 2026.4.9 beta olarak 2026-10-12'de yayınlanmıştı (pinegrap.com
onunla güncellendi); beta'yı koşmuş site `config.version`'ı zaten 2026.4.9
olduğu için kararlı sürüme geçerken 9.x alt adımlarını yeniden koşmaz, beta
sonrası düzeltmeler bu yüzden kodda kendini düzeltecek biçimde yazıldı
(örnek: `pg_tr_render_rules_check()`). Geliştirme `development` dalında
sürer.

2026.4.4'ün geçmişi: 2026.4.3'ten sonra dev makinada biriken sekiz çalışma
numarası (4.4–4.11) 2026-08-28'de tek 2026.4.4 olarak birleştirildi
(aşağıda "2026.4.4 — birleştirme").

**Kural (2026-08-28'den itibaren):** yayınlanmamış sürüm **açıktır**. Şema
adımları o sürümün fonksiyonuna eklenir; changelog bölümü nihai-durum
anlatımıdır — kullanıcı o sürümü hiç görmediğinden geliştirme içi
düzeltmeler [DÜZELTME] olarak yazılmaz, geliştirme sırasında eklenip
kaldırılan iç adreslerden (örn. view_folder_and_files.php) söz edilmez. Dev
veritabanı açık sürümü zaten koşmuşsa yeniden koşturmak için
`config.version` bir önceki yayınlanmış sürüme (bugün `2026.4.9`) çekilir;
adımlar idempotent olduğu için uygulanmış olanlar atlanır. Yeni numara
yalnız yayın sonrası açılır.

Dağıtılmış bir sürüm **kapalıdır.** Ne `changelog.txt` başlığına, ne
`upgrade_to_*` fonksiyonunun getirdiği DB değişikliğine, ne de `$versions`
dizisindeki satırına dokunulur. Operatör o yükseltmeyi çalıştırdı; numarayı
yeniden kullanmak, aynı numaranın iki farklı şey ifade etmesi demektir ve
yükseltme sistemi bunu fark edemez — sürümü zaten geçmiş bir kurulum yeni
eklenen adımı **hiç çalıştırmaz**. Fonksiyon gövdesini idempotent
yardımcılara çevirmek bu kuralın dışındadır: adımın anlamı değişmez, yalnız
yeniden koşturulabilir olur.

Aşağıdaki bölümlerin `İç tur` ve `(iç tur 4.x)` başlıkları **çalışma
numaralarıdır**, dağıtılmış sürüm değildir. `İç tur 2026.4.x` başlıkları
2026.4.2 birleştirmesine, `2026.4.4 (iç tur 4.x)` başlıkları 2026.4.4
birleştirmesine aittir. Gerekçe kaydı olarak oldukları gibi bırakıldılar.

---
