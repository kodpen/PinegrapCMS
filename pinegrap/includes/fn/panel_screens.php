<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The screens the panel search box offers as chips and the gates that offer
 * them to a viewer (the main menu's rules). The search box
 * (includes/panel/search.php) draws its chips from them; the guide's panel
 * topic (includes/fn/agent_guide_panel.php) lists the same screens to an AI
 * agent working in the viewer's browser.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The chip gates of a viewer, from a user array in validate_user()'s shape:
// pg_panel_search_chip_gates() over the viewer's rights, the ones that need
// a query read once and only for role 3, plus content_review (the Content
// review screen is set up and the viewer opens folders). The search box and
// the guide's panel topic (includes/fn/agent_guide_panel.php) read it.
function pg_panel_search_viewer_gates($user)
{
    $role = (int) $user['role'];

    $gate = pg_panel_search_chip_gates(array(
        'role'                     => $role,
        'create_pages'             => !empty($user['create_pages']),
        'manage_visitors'          => ($user['manage_visitors'] == true),
        'manage_forms'             => ($user['manage_forms'] == true),
        'manage_emails'            => ($user['manage_emails'] == true),
        // The rules of USER_MANAGE_ECOMMERCE(_REPORTS) in initialize_user().
        'manage_ecommerce'         => ($user['manage_ecommerce'] == 'yes'),
        'manage_ecommerce_reports' => !empty($user['manage_ecommerce_reports']),
        'folder_edit'              => ($role == 3) && no_acl_check($user['id']),
        'contact_group'            => ($role == 3) && validate_contacts_access($user, true),
        'calendar'                 => ($role == 3) && validate_calendars_access($user, true),
        'ad_region'                => ($role == 3) && (ADS === true) && (count(get_items_user_can_edit('ad_regions', $user['id'])) > 0),
        'ecommerce_module'         => (ECOMMERCE === true),
        'calendars_module'         => (CALENDARS === true),
        'forms_module'             => (FORMS === true),
        'ads_module'               => (ADS === true),
        'dynamic_regions_module'   => defined('DYNAMIC_REGIONS') && (DYNAMIC_REGIONS == true),
        'not_found_log_ready'      => ($role < 3) && pg_not_found_log_ready(),
    ));

    $gate['content_review'] = $gate['folders'] && function_exists('pg_content_review_ready') && pg_content_review_ready();

    return $gate;
}

// The screens the search box offers as chips, in the order it lists them.
// Each: gate (the pg_panel_search_viewer_gates() flag that offers it), group
// (its main-menu section), label (English, a lang() key; with noun, the
// "Create {var:1}" label takes that noun), icon, url (under the software
// folder, or a "#" fragment for a dialog of the header), keys, and
// after_settings for the chips listed after the settings ones. The guide's
// panel topic (includes/fn/agent_guide_panel.php) reads the same list.
function pg_panel_search_screens()
{
    $rows = array(
        array('folders', 'Pages', 'Pages', 'bi-file-earmark-text', 'view_pages.php', array('sayfa', 'sayfalar', 'page', 'pages', 'say')),
        array('add_page', 'Pages', 'Create {var:1}', 'bi-file-earmark-plus', 'add_page.php', array('sayfa ekle', 'sayfa olustur', 'page add', 'yeni sayfa', 'add page', 'create page', 'sayfaekle'), 'Page'),
        array('folders', 'Pages', 'File Manager', 'bi-folder2', 'view_folders.php', array('klasor', 'klasör', 'folder', 'fol', 'kla', 'dosya', 'yonetici', 'file', 'manager')),
        array('manage', 'Pages', 'Short Links', 'bi-link-45deg', 'view_short_links.php', array('kisa link', 'kisa', 'short', 'link', 'kis')),
        array('not_found_log', 'Pages', '404 Log', 'bi-sign-dead-end', 'view_not_found_log.php', array('404', 'bulunamadi', 'bulunamayan', 'not found', 'kirik link', 'broken link', 'yonlendirme', 'redirect')),
        array('manage', 'Pages', 'Search and audits', 'bi-activity', 'search_audits.php', array('arama dizini', 'dizin', 'search index', 'index', 'denetim', 'audit', 'seo', 'tarama', 'yapi', 'structure')),
        array('manage', 'Pages', 'Accessibility report', 'bi-universal-access', 'view_accessibility.php', array('erisilebilirlik', 'erişilebilirlik', 'accessibility', 'a11y', 'wcag', 'engelli')),
        array('content_review', 'Pages', 'Content review', 'bi-calendar-check', 'view_content_review.php', array('icerik', 'içerik', 'gozden gecir', 'gözden geçir', 'content review', 'review', 'eskimis', 'overdue', 'gecikmis')),
        array('folders', 'Pages', 'Comments', 'bi-chat-dots', 'view_comments.php', array('yorum', 'comment', 'com', 'yor')),
        array('manage', 'Pages', 'Auto Dialogs', 'bi-chat-square-text', 'view_auto_dialogs.php', array('dialog', 'auto', 'oto', 'diy')),
        array('folders', 'Files', 'Files', 'bi-folder2-open', 'view_folders.php?view=files', array('dosya', 'dosyalar', 'file', 'files', 'fil', 'dos')),
        array('folders', 'Files', 'Add File', 'bi-file-earmark-arrow-up', 'add_file.php', array('dosya yukle', 'dosya ekle', 'upload', 'add file', 'yukle')),
        array('ecommerce', 'Store', 'Orders', 'bi-receipt', 'view_orders.php', array('siparis', 'siparisler', 'order', 'orders', 'ord', 'sip')),
        array('ecommerce', 'Store', 'Products', 'bi-box-seam', 'view_products.php', array('urun', 'urunler', 'product', 'products', 'pro', 'uru')),
        array('ecommerce', 'Store', 'Create {var:1}', 'bi-box-seam', 'add_product.php', array('urun ekle', 'urun olustur', 'add product', 'create product', 'yeni urun', 'urunek'), 'Product'),
        array('ecommerce', 'Store', 'Product Groups', 'bi-boxes', 'view_product_groups.php', array('urun grubu', 'grup', 'product group', 'group', 'gru')),
        array('ecommerce', 'Store', 'Add Product Group', 'bi-boxes', 'add_product_group.php', array('grup ekle', 'add group', 'yeni grup', 'grupekle')),
        array('ecommerce', 'Store', 'Offers', 'bi-percent', 'view_offers.php', array('teklif', 'teklifler', 'indirim', 'offer', 'offers', 'off', 'tek', 'ind')),
        array('ecommerce', 'Store', 'Add Offer', 'bi-percent', 'add_offer.php', array('teklif ekle', 'add offer', 'indirim ekle', 'teklifekle')),
        array('ecommerce', 'Store', 'Gift Cards', 'bi-gift', 'view_gift_cards.php', array('hediye', 'gift', 'kart', 'giftcard', 'hed')),
        array('ecommerce', 'Store', 'Shipping Methods', 'bi-truck', 'view_shipping_methods.php', array('nakliye', 'kargo', 'shipping', 'gonder', 'gönderim', 'kar')),
        array('ecommerce', 'Store', 'Currencies', 'bi-currency-exchange', 'view_currencies.php', array('para', 'doviz', 'döviz', 'currency', 'cur', 'par', 'döv')),
        array('ecommerce', 'Store', 'Countries', 'bi-globe2', 'view_countries.php', array('ulke', 'ülke', 'country', 'countries', 'ulk')),
        array('ecommerce', 'Store', 'Tax Zones', 'bi-receipt-cutoff', 'view_tax_zones.php', array('vergi', 'kdv', 'tax', 'ver')),
        array('ecommerce', 'Store', 'Shipping Zones', 'bi-map', 'view_zones.php', array('nakliye bolge', 'nakliye bölge', 'kargo bolge', 'bolge', 'bölge', 'shipping zone', 'zone', 'zon', 'böl')),
        array('ecommerce', 'Store', 'States/Provinces', 'bi-geo-alt', 'view_states.php', array('iller', 'eyalet', 'eyaletler', 'sehir', 'şehir', 'state', 'province', 'seh')),
        // A right of its own: a role 3 user may hold it without manage_ecommerce.
        array('order_reports', 'Store', 'Order Reports', 'bi-bar-chart', 'view_order_reports.php', array('siparis rapor', 'order report', 'rapor sip', 'raporord')),
        array('visitors', 'Visitors', 'Visitors', 'bi-speedometer', 'visitors.php', array('ziyaretci', 'ziyaretçi', 'visitors', 'genel bakis', 'overview', 'trafik', 'traffic', 'kaynak', 'kanal')),
        array('visitors', 'Visitors', 'Visitor Reports', 'bi-people', 'view_visitor_reports.php', array('ziyaretci', 'ziyaretçi', 'visitor', 'visit', 'zia', 'rap')),
        array('visitors', 'Visitors', 'Visitor Report', 'bi-graph-up', 'view_visitor_report.php', array('ziyaret rapor', 'visitor report', 'rapor ziy', 'ziyrapor')),
        array('visitors', 'Visitors', 'Google Search Console', 'bi-google', 'search_console.php', array('search console', 'arama konsolu', 'google arama', 'gsc', 'site haritasi', 'sitemap', 'tiklama', 'gosterim')),
        array('visitors', 'Visitors', 'Page Speed', 'bi-speedometer2', 'visitors.php#speed', array('sayfa hizi', 'page speed', 'web vitals', 'lcp', 'inp', 'cls', 'hiz')),
        array('visitors', 'Visitors', 'AI Visibility', 'bi-robot', 'visitors.php#ai', array('yapay zeka', 'ai visibility', 'ai gorunurluk', 'chatgpt', 'llms', 'bot')),
        array('contacts', 'Contacts', 'Contacts', 'bi-people', 'view_contacts.php', array('kisi', 'kişi', 'contact', 'rehber', 'con', 'kis', 'reh')),
        array('contacts', 'Contacts', 'Add Contact', 'bi-person-plus', 'add_contact.php', array('kisi ekle', 'add contact', 'yeni kisi', 'kisieki')),
        array('contacts', 'Contacts', 'Contact Groups', 'bi-people-fill', 'view_contact_groups.php', array('grup kisi', 'contact group', 'kisigrup')),
        array('users', 'Users', 'Users', 'bi-person-gear', 'view_users.php', array('kullanici', 'kullanıcı', 'user', 'usr', 'kul')),
        array('users', 'Users', 'Add User', 'bi-person-plus', 'add_user.php', array('kullanici ekle', 'add user', 'yeni kullanici', 'kullanicieki')),
        array('campaigns', 'Email', 'Email Campaigns', 'bi-megaphone', 'view_email_campaigns.php', array('kampanya', 'mail', 'email', 'campaign', 'kamp')),
        array('campaigns', 'Email', 'Add Email Campaign', 'bi-megaphone', 'add_email_campaign.php', array('kampanya ekle', 'add campaign', 'kampanyaekle')),
        array('calendars', 'Calendars', 'Calendars', 'bi-calendar3', 'view_calendars.php', array('takvim', 'calendar', 'tak', 'cal')),
        array('forms', 'Forms', 'Submitted Forms', 'bi-ui-checks', 'view_submitted_forms.php', array('form', 'gonderilen', 'submitted', 'frm', 'gon')),
        array('menus', 'Menus', 'Menus', 'bi-menu-button', 'view_menus.php', array('menu', 'men')),
        array('ads', 'Ads', 'Ads', 'bi-badge-ad', 'view_ads.php', array('reklam', 'ad', 'ads', 'rek')),
        array('design', 'Design', 'Experiments', 'bi-bar-chart-line', 'experiments.php', array('deney', 'deneyler', 'ab test', 'a/b', 'experiment', 'experiments', 'varyant', 'variant')),
        array('design', 'Design', 'Page Styles', 'bi-window', 'view_styles.php', array('stil', 'stiller', 'style', 'styles', 'stl')),
        array('design', 'Design', 'Create {var:1}', 'bi-window-plus', 'add_custom_style.php', array('stil ekle', 'stil olustur', 'add style', 'create style', 'yeni stil', 'stilekle'), 'Page Style'),
        array('design', 'Design', 'Themes', 'bi-palette', 'view_themes.php', array('tema', 'theme', 'them', 'tem')),
        array('design', 'Design', 'Design Files', 'bi-filetype-css', 'view_design_files.php', array('tasarim dosya', 'design file', 'css', 'js', 'des', 'tas')),
        array('design', 'Design', 'Common Regions', 'bi-columns-gap', 'view_regions.php?filter=all_common_regions', array('ortak bolge', 'common region', 'region', 'reg', 'ort', 'common', 'bol')),
        array('design', 'Design', 'Login Regions', 'bi-shield-lock', 'view_regions.php?filter=all_login_regions', array('giris bolge', 'login region', 'logi', 'gir')),
        array('design', 'Design', 'Designer Regions', 'bi-code-square', 'view_regions.php?filter=all_designer_regions', array('tasarim bolge', 'designer region', 'desi')),
        array('design', 'Design', 'Find & Replace', 'bi-search', 'find_and_replace.php', array('bul degistir', 'find replace', 'degistir', 'bul')),
        // view_regions.php lists dynamic regions for administrators only.
        array('dynamic_regions', 'Design', 'Dynamic Regions', 'bi-arrow-repeat', 'view_regions.php?filter=all_dynamic_regions', array('dinamik bolge', 'dynamic region', 'dyna', 'din')),
        // The keyboard shortcuts are a dialog of the header: the chip handler in
        // backend.src.js opens it for the fragment, as the ? key does.
        array('help', 'Help', 'Keyboard Shortcuts', 'bi-keyboard', '#keyboard-shortcuts', array('klavye', 'kisayol', 'kısayol', 'keyboard', 'shortcut', 'tus', 'tuş')),
    );

    $screens = array();

    foreach ($rows as $row) {
        $screens[] = array(
            'gate'           => $row[0],
            'group'          => $row[1],
            'label'          => $row[2],
            'icon'           => $row[3],
            'url'            => $row[4],
            'keys'           => $row[5],
            'noun'           => isset($row[6]) ? $row[6] : '',
            'after_settings' => in_array($row[1], array('Design', 'Help'), true),
        );
    }

    return $screens;
}

// The label a screen goes by in English, as pg_panel_search_screens() holds
// it with its noun filled in.
function pg_panel_search_screen_label($screen)
{
    return ($screen['noun'] === '') ? $screen['label'] : str_replace('{var:1}', $screen['noun'], $screen['label']);
}

// One screen of pg_panel_search_screens() as the chip the search box
// answers with: its label in the panel's language and its full address.
function pg_panel_search_screen_action($screen, $base_url)
{
    $label = ($screen['noun'] === '') ? lang($screen['label']) : lang(array('string' => $screen['label'], 'vars' => lang($screen['noun'])));

    return array(
        'label' => $label,
        'icon'  => $screen['icon'],
        'url'   => (substr($screen['url'], 0, 1) === '#') ? $screen['url'] : $base_url . '/' . $screen['url'],
        'keys'  => $screen['keys'],
    );
}


// Which chips of the panel search box the viewer gets: each one under the
// rule of the main-menu entry that opens the same screen (output_menu() in
// includes/fn/output.php), so a chip never ends on "Access denied" and
// none is missing for someone the screen lets in. $rights holds the role,
// the user's rights, the rights that need a query (folder_edit: an edit
// folder, contact_group, calendar, ad_region), the module switches and
// whether the 404 log table is there.
function pg_panel_search_chip_gates($rights)
{
    $r = array_merge(array(
        'role' => 3, 'create_pages' => false, 'manage_visitors' => false,
        'manage_forms' => false, 'manage_emails' => false, 'manage_ecommerce' => false,
        'manage_ecommerce_reports' => false, 'folder_edit' => false, 'contact_group' => false,
        'calendar' => false, 'ad_region' => false, 'ecommerce_module' => false,
        'calendars_module' => false, 'forms_module' => false, 'ads_module' => false,
        'dynamic_regions_module' => false, 'not_found_log_ready' => false,
    ), (array) $rights);

    $role = (int) $r['role'];
    $below_user = ($role < 3);
    // validate_area_access('user') lets a role 3 user in with an edit folder.
    $folders = $below_user || $r['folder_edit'];

    return array(
        'folders'         => $folders,
        // add_page.php also asks a role 3 user for create_pages.
        'add_page'        => $folders && ($below_user || $r['create_pages']),
        'manage'          => $below_user,
        'not_found_log'   => $below_user && $r['not_found_log_ready'],
        'ecommerce'       => $r['ecommerce_module'] && ($below_user || $r['manage_ecommerce']),
        'order_reports'   => $r['ecommerce_module'] && ($below_user || $r['manage_ecommerce_reports']),
        'visitors'        => $below_user || $r['manage_visitors'],
        'contacts'        => $below_user || $r['contact_group'],
        'users'           => $below_user,
        'campaigns'       => $below_user || $r['manage_emails'],
        'calendars'       => $r['calendars_module'] && ($below_user || $r['calendar']),
        'forms'           => $r['forms_module'] && ($below_user || $r['manage_forms']),
        'ads'             => $r['ads_module'] && ($below_user || $r['ad_region']),
        'menus'           => ($role < 2),
        'design'          => ($role < 2),
        'dynamic_regions' => ($role < 1) && $r['dynamic_regions_module'],
        'help'            => true,
    );
}
