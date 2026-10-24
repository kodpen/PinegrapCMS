<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - a channel's own icon (ws_channels.icon, 2026.11.0, 11.0.81).
 *
 * Whoever may rename a channel may give it an icon from a curated list of
 * Bootstrap Icons, drawn before its name wherever the channel is listed. A
 * channel without one keeps the icon its kind gives (ws_channel_icon(),
 * customer.php). The kind is never lost: a private, guest or customer
 * channel with an icon of its own carries a small lock, door or person in
 * the icon's corner.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The icons a channel may be given, in the order of the picker's grid, each
 * with the words it is found by: Turkish, then English, comma-separated
 * phrases, the first of each being the icon's name in that language. Every
 * class must exist in assets/fonts/bootstrap-icons and fit the 40 characters
 * of the column (tests/workspace_channel_icons_test.php). The marks of a
 * channel's kind (hash, lock, door, person) are left out on purpose: a lock
 * chosen for a public channel would say it is private.
 *
 * @return array[] array(class, Turkish words, English words)
 */
function ws_channel_icon_list()
{
    return array(
        // Talking and telling
        array('bi-megaphone', 'Duyuru, ilan, kampanya, pazarlama', 'Announcement, megaphone, campaign, marketing'),
        array('bi-chat-dots', 'Sohbet, konuşma, mesaj', 'Chat, talk, message, conversation'),
        array('bi-envelope', 'E-posta, zarf, posta, mektup', 'Email, envelope, mail, letter'),
        array('bi-send', 'Gönderim, gönder, giden', 'Send, outgoing, dispatch'),
        array('bi-telephone', 'Telefon, arama, çağrı', 'Telephone, phone, call'),
        array('bi-headset', 'Destek, kulaklık, çağrı merkezi, müşteri hizmetleri', 'Support, headset, call centre, call center, customer service, helpdesk'),
        array('bi-inbox', 'Gelen kutusu, talep, başvuru', 'Inbox, requests, incoming'),
        array('bi-bell', 'Bildirim, zil, hatırlatma, uyarı', 'Notification, bell, reminder, alert'),
        // People and work
        array('bi-people', 'Ekip, takım, insanlar, kişiler', 'Team, people, group, staff'),
        array('bi-person-workspace', 'Çalışan, personel, insan kaynakları, ik', 'Employee, staff, human resources, hr, desk'),
        array('bi-briefcase', 'İş, çanta, proje, danışmanlık', 'Work, briefcase, business, project, consulting'),
        array('bi-building', 'Şirket, bina, ofis, kurum', 'Company, building, office, corporate'),
        array('bi-house', 'Genel, ev, ana sayfa, merkez', 'General, house, home, main'),
        array('bi-diagram-3', 'Organizasyon, şema, yapı, hiyerarşi', 'Organisation, organization, diagram, structure, hierarchy'),
        array('bi-kanban', 'Pano, kanban, iş akışı, süreç', 'Board, kanban, workflow, process'),
        array('bi-clipboard-check', 'Kontrol listesi, denetim, onay, kalite', 'Checklist, clipboard, audit, approval, quality'),
        // Time and goals
        array('bi-calendar-event', 'Takvim, etkinlik, toplantı, randevu', 'Calendar, event, meeting, appointment'),
        array('bi-clock', 'Saat, zaman, mesai, vardiya', 'Clock, time, hours, shift'),
        array('bi-flag', 'Hedef, bayrak, kilometre taşı', 'Goal, flag, milestone'),
        array('bi-trophy', 'Kupa, başarı, yarışma, kazanç', 'Trophy, success, competition, win'),
        array('bi-award', 'Ödül, rozet, kalite, sertifika', 'Award, badge, quality, certificate'),
        array('bi-star', 'Yıldız, favori, önemli, öne çıkan', 'Star, favourite, favorite, important, featured'),
        array('bi-lightning', 'Hızlı, şimşek, acil, enerji', 'Fast, lightning, urgent, energy'),
        array('bi-bookmark', 'Yer imi, kayıtlı, işaret', 'Bookmark, saved, marked'),
        // Selling
        array('bi-cart', 'Sepet, alışveriş, sipariş, e-ticaret', 'Cart, shopping, order, ecommerce'),
        array('bi-bag', 'Çanta, alışveriş, mağaza, perakende', 'Bag, shopping, store, retail'),
        array('bi-shop', 'Mağaza, dükkan, şube, satış noktası', 'Shop, store, branch, point of sale'),
        array('bi-tag', 'Etiket, fiyat, ürün', 'Tag, price, product, label'),
        array('bi-percent', 'İndirim, yüzde, kampanya, vergi', 'Discount, percent, sale, tax'),
        array('bi-gift', 'Hediye, promosyon, kupon', 'Gift, present, promotion, coupon'),
        array('bi-ticket-perforated', 'Bilet, destek kaydı, talep, etkinlik', 'Ticket, support case, event'),
        array('bi-box-seam', 'Paket, kutu, stok, depo', 'Package, box, parcel, stock, warehouse'),
        // Shipping and places
        array('bi-truck', 'Kargo, kamyon, sevkiyat, teslimat, lojistik', 'Shipping, truck, cargo, delivery, logistics'),
        array('bi-airplane', 'Seyahat, uçak, ihracat', 'Travel, airplane, plane, export'),
        array('bi-geo-alt', 'Konum, pin, yer, adres, harita', 'Location, pin, place, address, map'),
        array('bi-globe', 'Dünya, web sitesi, uluslararası', 'World, globe, website, international'),
        // Money
        array('bi-receipt', 'Fatura, fiş, makbuz', 'Invoice, receipt, bill'),
        array('bi-cash-coin', 'Nakit, para, tahsilat, ödeme', 'Cash, money, coin, collection, payment'),
        array('bi-credit-card', 'Kredi kartı, ödeme, pos', 'Credit card, payment, pos'),
        array('bi-wallet2', 'Cüzdan, bütçe, harcama', 'Wallet, budget, spending'),
        array('bi-bank', 'Banka, finans, hazine', 'Bank, finance, treasury'),
        array('bi-calculator', 'Muhasebe, hesap makinesi, hesap', 'Accounting, calculator'),
        array('bi-graph-up-arrow', 'Büyüme, grafik, satış, artış', 'Growth, graph, sales, increase'),
        array('bi-bar-chart', 'Rapor, çubuk grafik, istatistik, analiz', 'Report, bar chart, statistics, analytics'),
        // Papers
        array('bi-file-earmark-text', 'Belge, doküman, sözleşme, dosya', 'Document, file, contract'),
        array('bi-folder', 'Klasör, dosyalar, arşiv', 'Folder, files, archive'),
        array('bi-journal-text', 'Defter, günlük, not, tutanak', 'Journal, notebook, notes, minutes'),
        array('bi-book', 'Kılavuz, kitap, eğitim, bilgi', 'Guide, book, manual, knowledge'),
        array('bi-newspaper', 'Haber, gazete, basın, blog', 'News, newspaper, press, blog'),
        array('bi-printer', 'Baskı, yazıcı, matbaa', 'Printing, printer, print'),
        // Technology
        array('bi-gear', 'Ayarlar, çark, ayar, sistem', 'Settings, gear, system'),
        array('bi-tools', 'Bakım, araçlar, onarım', 'Maintenance, tools, repair'),
        array('bi-wrench', 'Servis, anahtar, tamir, teknik', 'Service, wrench, repair, technical'),
        array('bi-code-slash', 'Yazılım, kod, geliştirme', 'Software, code, development'),
        array('bi-bug', 'Hata, böcek, sorun', 'Bug, defect, issue'),
        array('bi-database', 'Veritabanı, veri', 'Database, data'),
        array('bi-cloud', 'Bulut, sunucu, barındırma', 'Cloud, server, hosting'),
        array('bi-laptop', 'Bilgisayar, dizüstü, bilişim', 'Computer, laptop, it'),
        array('bi-phone', 'Mobil, cep telefonu, uygulama', 'Mobile, phone, smartphone, app'),
        array('bi-puzzle', 'Entegrasyon, yapboz, eklenti', 'Integration, puzzle, plugin'),
        array('bi-robot', 'Otomasyon, robot, yapay zeka', 'Automation, robot, ai, artificial intelligence'),
        array('bi-shield-check', 'Güvenlik, kalkan, koruma', 'Security, shield, protection'),
        array('bi-key', 'Erişim, anahtar, şifre', 'Access, key, password'),
        // Making things
        array('bi-palette', 'Tasarım, palet, renk, sanat', 'Design, palette, colour, color, art'),
        array('bi-brush', 'Grafik, fırça, boya', 'Graphics, brush, paint'),
        array('bi-pencil', 'İçerik, kalem, yazı, düzenleme', 'Content, pencil, writing, editing'),
        array('bi-camera', 'Fotoğraf, kamera, çekim', 'Photo, camera, shoot'),
        array('bi-film', 'Video, film, sinema', 'Video, film, cinema'),
        array('bi-music-note-beamed', 'Müzik, ses, nota', 'Music, sound, note'),
        // Everything else
        array('bi-lightbulb', 'Fikir, ampul, öneri, ar-ge', 'Idea, lightbulb, suggestion, research'),
        array('bi-rocket-takeoff', 'Lansman, roket, başlangıç, girişim', 'Launch, rocket, start, startup'),
        array('bi-compass', 'Strateji, pusula, yön', 'Strategy, compass, direction'),
        array('bi-mortarboard', 'Eğitim, kep, okul, akademi', 'Training, mortarboard, education, school, academy'),
        array('bi-translate', 'Çeviri, dil, tercüme', 'Translation, translate, language'),
        array('bi-heart', 'Sağlık, kalp, sevgi, gönüllü', 'Health, heart, love, volunteer'),
        array('bi-emoji-smile', 'Sosyal, gülümseme, mutluluk, eğlence', 'Social, smile, happy, fun'),
        array('bi-cup-hot', 'Mola, fincan, kahve, çay', 'Break, cup, coffee, tea'),
        array('bi-tree', 'Doğa, ağaç, çevre, bahçe', 'Nature, tree, environment, garden'),
        array('bi-umbrella', 'Sigorta, şemsiye, güvence', 'Insurance, umbrella, cover'),
        array('bi-gem', 'Premium, mücevher, değerli, vip', 'Premium, gem, jewel, vip'),
    );
}

/**
 * The icons a channel may be given, by class.
 *
 * @return array[] class => array('tr' => words, 'en' => words)
 */
function ws_channel_icon_choices()
{
    static $choices = null;

    if ($choices === null) {
        $choices = array();

        foreach (ws_channel_icon_list() as $row) {
            $choices[$row[0]] = array('tr' => $row[1], 'en' => $row[2]);
        }
    }

    return $choices;
}

/**
 * Has the database the channel icons (2026.11.0, 11.0.81)?
 *
 * @return bool
 */
function ws_channel_icons_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has') && pg_schema_has('ws_channels', 'icon');
    }

    return $ready;
}

/**
 * A stored or sent icon as it may be drawn: one of the choices, or '' for an
 * empty, unknown or retired value.
 *
 * @param mixed $value
 * @return string
 */
function ws_channel_icon_clean($value)
{
    if (!is_string($value)) {
        return '';
    }

    $value = strtolower(trim($value));

    return isset(ws_channel_icon_choices()[$value]) ? $value : '';
}

/**
 * An icon sent to be saved: empty clears it, anything outside the list is
 * refused rather than dropped.
 *
 * @param mixed $value
 * @return array ok, icon, error
 */
function ws_channel_icon_check($value)
{
    if (($value === null) || (is_string($value) && (trim($value) === ''))) {
        return array('ok' => true, 'icon' => '', 'error' => '');
    }

    $icon = ws_channel_icon_clean($value);

    if ($icon === '') {
        return array('ok' => false, 'icon' => '', 'error' => lang('Choose the icon from the list.'));
    }

    return array('ok' => true, 'icon' => $icon, 'error' => '');
}

/**
 * The icon the channel was given, '' when it has none. A brief
 * (ws_channel_brief()) already holds the outcome in icon and says with
 * icon_chosen whether it was chosen; a row holds the column itself.
 *
 * @param array $channel a row or a brief
 * @return string
 */
function ws_channel_icon_chosen($channel)
{
    if (array_key_exists('icon_chosen', $channel)) {
        return !empty($channel['icon_chosen']) ? ws_channel_icon_clean($channel['icon'] ?? '') : '';
    }

    return ws_channel_icon_clean($channel['icon'] ?? '');
}

/**
 * The mark of the channel's kind in the corner of an icon of its own: a lock
 * for a private channel, a door for a guest's room, a person for a channel
 * about a customer; '' when the channel has no icon of its own (its icon is
 * the mark) or nothing to mark.
 *
 * @param array $channel a row or a brief
 * @return string
 */
function ws_channel_icon_badge($channel)
{
    if (ws_channel_icon_chosen($channel) === '') {
        return '';
    }

    $kind = ws_channel_kind_icon($channel);

    if (in_array($kind, array('bi-lock', 'bi-door-open'), true)) {
        return $kind;
    }

    return ($kind === 'bi-hash') ? '' : 'bi-person';
}

/**
 * An icon with a mark in its corner, as markup; the plain icon when there is
 * no mark. Both are hidden from screen readers: the name beside them is the
 * label.
 *
 * @param string $icon  a bi-* class
 * @param string $badge a bi-* class or ''
 * @param string $class classes for the outer element
 * @return string
 */
function ws_icon_badged_html($icon, $badge, $class = '')
{
    $class = trim((string) $class);

    if ((string) $badge === '') {
        return '<i class="bi ' . h($icon) . (($class !== '') ? ' ' . h($class) : '') . '" aria-hidden="true"></i>';
    }

    return '<span class="ws-icon-badged' . (($class !== '') ? ' ' . h($class) : '') . '" aria-hidden="true">'
        . '<i class="bi ' . h($icon) . '"></i>'
        . '<i class="bi ' . h($badge) . ' ws-icon-badge"></i></span>';
}

/**
 * The choices for the picker in the channel form: the icon, its name in the
 * interface language (Turkish or English) and every word it is found by.
 *
 * @return array[] icon, label, words
 */
function ws_channel_icon_choices_js()
{
    $turkish = (strtolower(substr((string) lang(array('info' => true)), 0, 2)) === 'tr');
    $out = array();

    foreach (ws_channel_icon_choices() as $icon => $words) {
        $label = explode(',', $turkish ? $words['tr'] : $words['en']);

        $out[] = array(
            'icon'  => $icon,
            'label' => trim($label[0]),
            'words' => $words['tr'] . ', ' . $words['en'],
        );
    }

    return $out;
}

/**
 * The words the screen needs for the icon picker.
 *
 * @return array
 */
function ws_channel_icon_js_strings()
{
    return array(
        'icon'         => lang('Icon'),
        'icon_none'    => lang('No icon of its own'),
        'icon_help'    => lang('Shown before the name in the lists, the same for everybody. A private, guest or customer channel keeps its mark in the corner of the icon.'),
        'icon_search'  => lang('Search icons...'),
        'icon_nothing' => lang('No icons found'),
    );
}
