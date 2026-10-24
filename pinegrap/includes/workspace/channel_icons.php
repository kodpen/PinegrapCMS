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
 * The fourth column is the icon's name for the picker, in the interface
 * language.
 *
 * @return array[] array(class, Turkish words, English words, name)
 */
function ws_channel_icon_list()
{
    return array(
        // Talking and telling
        array('bi-megaphone', 'Duyuru, ilan, kampanya, pazarlama', 'Announcement, megaphone, campaign, marketing', lang('Announcement')),
        array('bi-chat-dots', 'Sohbet, konuşma, mesaj', 'Chat, talk, message, conversation', lang('Chat')),
        array('bi-envelope', 'E-posta, zarf, posta, mektup', 'Email, envelope, mail, letter', lang('Email')),
        array('bi-send', 'Gönderim, gönder, giden', 'Send, outgoing, dispatch', lang('Send')),
        array('bi-telephone', 'Telefon, arama, çağrı', 'Telephone, phone, call', lang('Telephone')),
        array('bi-headset', 'Destek, kulaklık, çağrı merkezi, müşteri hizmetleri', 'Support, headset, call centre, call center, customer service, helpdesk', lang('Support')),
        array('bi-inbox', 'Gelen kutusu, talep, başvuru', 'Inbox, requests, incoming', lang('Inbox')),
        array('bi-bell', 'Bildirim, zil, hatırlatma, uyarı', 'Notification, bell, reminder, alert', lang('Notification')),
        // People and work
        array('bi-people', 'Ekip, takım, insanlar, kişiler', 'Team, people, group, staff', lang('Team')),
        array('bi-person-workspace', 'Çalışan, personel, insan kaynakları, ik', 'Employee, staff, human resources, hr, desk', lang('Employee')),
        array('bi-briefcase', 'İş, çanta, proje, danışmanlık', 'Work, briefcase, business, project, consulting', lang('Work')),
        array('bi-building', 'Şirket, bina, ofis, kurum', 'Company, building, office, corporate', lang('Company')),
        array('bi-house', 'Genel, ev, ana sayfa, merkez', 'General, house, home, main', lang('General')),
        array('bi-diagram-3', 'Organizasyon, şema, yapı, hiyerarşi', 'Organisation, organization, diagram, structure, hierarchy', lang('Organisation')),
        array('bi-kanban', 'Pano, kanban, iş akışı, süreç', 'Board, kanban, workflow, process', lang('Board')),
        array('bi-clipboard-check', 'Kontrol listesi, denetim, onay, kalite', 'Checklist, clipboard, audit, approval, quality', lang('Checklist')),
        // Time and goals
        array('bi-calendar-event', 'Takvim, etkinlik, toplantı, randevu', 'Calendar, event, meeting, appointment', lang('Calendar')),
        array('bi-clock', 'Saat, zaman, mesai, vardiya', 'Clock, time, hours, shift', lang('Clock')),
        array('bi-flag', 'Hedef, bayrak, kilometre taşı', 'Goal, flag, milestone', lang('Goal')),
        array('bi-trophy', 'Kupa, başarı, yarışma, kazanç', 'Trophy, success, competition, win', lang('Trophy')),
        array('bi-award', 'Ödül, rozet, kalite, sertifika', 'Award, badge, quality, certificate', lang('Award')),
        array('bi-star', 'Yıldız, favori, önemli, öne çıkan', 'Star, favourite, favorite, important, featured', lang('Star')),
        array('bi-lightning', 'Hızlı, şimşek, acil, enerji', 'Fast, lightning, urgent, energy', lang('Fast')),
        array('bi-bookmark', 'Yer imi, kayıtlı, işaret', 'Bookmark, saved, marked', lang('Bookmark')),
        // Selling
        array('bi-cart', 'Sepet, alışveriş, sipariş, e-ticaret', 'Cart, shopping, order, ecommerce', lang('Cart')),
        array('bi-bag', 'Çanta, alışveriş, mağaza, perakende', 'Bag, shopping, store, retail', lang('Bag')),
        array('bi-shop', 'Mağaza, dükkan, şube, satış noktası', 'Shop, store, branch, point of sale', lang('Shop')),
        array('bi-tag', 'Etiket, fiyat, ürün', 'Tag, price, product, label', lang('Tag')),
        array('bi-percent', 'İndirim, yüzde, kampanya, vergi', 'Discount, percent, sale, tax', lang('Discount')),
        array('bi-gift', 'Hediye, promosyon, kupon', 'Gift, present, promotion, coupon', lang('Gift')),
        array('bi-ticket-perforated', 'Bilet, destek kaydı, talep, etkinlik', 'Ticket, support case, event', lang('Ticket')),
        array('bi-box-seam', 'Paket, kutu, stok, depo', 'Package, box, parcel, stock, warehouse', lang('Package')),
        // Shipping and places
        array('bi-truck', 'Kargo, kamyon, sevkiyat, teslimat, lojistik', 'Shipping, truck, cargo, delivery, logistics', lang('Shipping')),
        array('bi-airplane', 'Seyahat, uçak, ihracat', 'Travel, airplane, plane, export', lang('Travel')),
        array('bi-geo-alt', 'Konum, pin, yer, adres, harita', 'Location, pin, place, address, map', lang('Location')),
        array('bi-globe', 'Dünya, web sitesi, uluslararası', 'World, globe, website, international', lang('World')),
        // Money
        array('bi-receipt', 'Fatura, fiş, makbuz', 'Invoice, receipt, bill', lang('Invoice')),
        array('bi-cash-coin', 'Nakit, para, tahsilat, ödeme', 'Cash, money, coin, collection, payment', lang('Cash')),
        array('bi-credit-card', 'Kredi kartı, ödeme, pos', 'Credit card, payment, pos', lang('Credit card')),
        array('bi-wallet2', 'Cüzdan, bütçe, harcama', 'Wallet, budget, spending', lang('Wallet')),
        array('bi-bank', 'Banka, finans, hazine', 'Bank, finance, treasury', lang('Bank')),
        array('bi-calculator', 'Muhasebe, hesap makinesi, hesap', 'Accounting, calculator', lang('Accounting')),
        array('bi-graph-up-arrow', 'Büyüme, grafik, satış, artış', 'Growth, graph, sales, increase', lang('Growth')),
        array('bi-bar-chart', 'Rapor, çubuk grafik, istatistik, analiz', 'Report, bar chart, statistics, analytics', lang('Report')),
        // Papers
        array('bi-file-earmark-text', 'Belge, doküman, sözleşme, dosya', 'Document, file, contract', lang('Document')),
        array('bi-folder', 'Klasör, dosyalar, arşiv', 'Folder, files, archive', lang('Folder')),
        array('bi-journal-text', 'Defter, günlük, not, tutanak', 'Journal, notebook, notes, minutes', lang('Journal')),
        array('bi-book', 'Kılavuz, kitap, eğitim, bilgi', 'Guide, book, manual, knowledge', lang('Guide')),
        array('bi-newspaper', 'Haber, gazete, basın, blog', 'News, newspaper, press, blog', lang('News')),
        array('bi-printer', 'Baskı, yazıcı, matbaa', 'Printing, printer, print', lang('Printing')),
        // Technology
        array('bi-gear', 'Ayarlar, çark, ayar, sistem', 'Settings, gear, system', lang('Settings')),
        array('bi-tools', 'Bakım, araçlar, onarım', 'Maintenance, tools, repair', lang('Maintenance')),
        array('bi-wrench', 'Servis, anahtar, tamir, teknik', 'Service, wrench, repair, technical', lang('Service')),
        array('bi-code-slash', 'Yazılım, kod, geliştirme', 'Software, code, development', lang('Software')),
        array('bi-bug', 'Hata, böcek, sorun', 'Bug, defect, issue', lang('Bug')),
        array('bi-database', 'Veritabanı, veri', 'Database, data', lang('Database')),
        array('bi-cloud', 'Bulut, sunucu, barındırma', 'Cloud, server, hosting', lang('Cloud')),
        array('bi-laptop', 'Bilgisayar, dizüstü, bilişim', 'Computer, laptop, it', lang('Computer')),
        array('bi-phone', 'Mobil, cep telefonu, uygulama', 'Mobile, phone, smartphone, app', lang('Mobile')),
        array('bi-puzzle', 'Entegrasyon, yapboz, eklenti', 'Integration, puzzle, plugin', lang('Integration')),
        array('bi-robot', 'Otomasyon, robot, yapay zeka', 'Automation, robot, ai, artificial intelligence', lang('Automation')),
        array('bi-shield-check', 'Güvenlik, kalkan, koruma', 'Security, shield, protection', lang('Security')),
        array('bi-key', 'Erişim, anahtar, şifre', 'Access, key, password', lang('Access')),
        // Making things
        array('bi-palette', 'Tasarım, palet, renk, sanat', 'Design, palette, colour, color, art', lang('Design')),
        array('bi-brush', 'Grafik, fırça, boya', 'Graphics, brush, paint', lang('Graphics')),
        array('bi-pencil', 'İçerik, kalem, yazı, düzenleme', 'Content, pencil, writing, editing', lang('Content')),
        array('bi-camera', 'Fotoğraf, kamera, çekim', 'Photo, camera, shoot', lang('Photo')),
        array('bi-film', 'Video, film, sinema', 'Video, film, cinema', lang('Video')),
        array('bi-music-note-beamed', 'Müzik, ses, nota', 'Music, sound, note', lang('Music')),
        // Everything else
        array('bi-lightbulb', 'Fikir, ampul, öneri, ar-ge', 'Idea, lightbulb, suggestion, research', lang('Idea')),
        array('bi-rocket-takeoff', 'Lansman, roket, başlangıç, girişim', 'Launch, rocket, start, startup', lang('Launch')),
        array('bi-compass', 'Strateji, pusula, yön', 'Strategy, compass, direction', lang('Strategy')),
        array('bi-mortarboard', 'Eğitim, kep, okul, akademi', 'Training, mortarboard, education, school, academy', lang('Training')),
        array('bi-translate', 'Çeviri, dil, tercüme', 'Translation, translate, language', lang('Translation')),
        array('bi-heart', 'Sağlık, kalp, sevgi, gönüllü', 'Health, heart, love, volunteer', lang('Health')),
        array('bi-emoji-smile', 'Sosyal, gülümseme, mutluluk, eğlence', 'Social, smile, happy, fun', lang('Social')),
        array('bi-cup-hot', 'Mola, fincan, kahve, çay', 'Break, cup, coffee, tea', lang('Break')),
        array('bi-tree', 'Doğa, ağaç, çevre, bahçe', 'Nature, tree, environment, garden', lang('Nature')),
        array('bi-umbrella', 'Sigorta, şemsiye, güvence', 'Insurance, umbrella, cover', lang('Insurance')),
        array('bi-gem', 'Premium, mücevher, değerli, vip', 'Premium, gem, jewel, vip', lang('Premium')),
    );
}

/**
 * The icons a channel may be given, by class.
 *
 * @return array[] class => array('tr' => words, 'en' => words, 'label' => name)
 */
function ws_channel_icon_choices()
{
    static $choices = null;

    if ($choices === null) {
        $choices = array();

        foreach (ws_channel_icon_list() as $row) {
            $choices[$row[0]] = array('tr' => $row[1], 'en' => $row[2], 'label' => $row[3]);
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
 * interface language (lang()) and every word it is found by, in both
 * languages.
 *
 * @return array[] icon, label, words
 */
function ws_channel_icon_choices_js()
{
    $out = array();

    foreach (ws_channel_icon_choices() as $icon => $words) {
        $out[] = array(
            'icon'  => $icon,
            'label' => $words['label'],
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
