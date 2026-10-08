<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Panel actions - the panel's search box.
 *
 *   backend_search  pages, files, products, contacts and the rest the user
 *                   may see, plus the screen shortcuts that match the query
 *
 * Called by pg_panel_dispatch() (includes/panel/actions.php); see that file
 * for the contract.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_PANEL_ACTIONS')) {
    exit;
}

function pg_panel_backend_search($request, $action)
{
    $user = validate_user();

    $search = isset($request['search']) ? trim($request['search']) : '';

    $offset = isset($request['offset']) ? max(0, (int) $request['offset']) : 0;
    $per_limit = $offset + 21; // +1 extra to detect has_more
    $results = array();

    // Role helpers
    $role = (int) $user['role']; // 0=admin,1=designer,2=manager,3=user
    $can_design = ($role <= 1);
    $can_manage = ($role <= 2);
    $can_ecommerce = ($role <= 2) || !empty($user['manage_ecommerce']);
    $can_manage_forms = ($role <= 2) || !empty($user['manage_forms']);
    $can_contacts = ($role <= 2) || !empty($user['manage_contacts']);

    // Build quick actions based on role (used for both empty and typed searches)
    $base_url = PATH . SOFTWARE_DIRECTORY;
    $actions = array();

    // ── Sayfalar / Pages ──────────────────────────────────────────────────
    $actions[] = array('label' => lang('Pages'), 'icon' => 'bi-file-earmark-text', 'url' => $base_url . '/view_pages.php', 'keys' => array('sayfa', 'sayfalar', 'page', 'pages', 'say'));
    $actions[] = array('label' => lang('Add Page'), 'icon' => 'bi-file-earmark-plus', 'url' => $base_url . '/add_page.php', 'keys' => array('sayfa ekle', 'page add', 'yeni sayfa', 'add page', 'sayfaekle'));
    $actions[] = array('label' => lang('File Manager'), 'icon' => 'bi-folder2', 'url' => $base_url . '/view_folders.php', 'keys' => array('klasor', 'klasör', 'folder', 'fol', 'kla', 'dosya', 'yonetici', 'file', 'manager'));
    $actions[] = array('label' => lang('Add Folder'), 'icon' => 'bi-folder-plus', 'url' => $base_url . '/add_folder.php', 'keys' => array('klasor ekle', 'add folder', 'yeni klasor', 'klasorekle'));
    if ($can_manage) {
        $actions[] = array('label' => lang('Short Links'), 'icon' => 'bi-link-45deg', 'url' => $base_url . '/view_short_links.php', 'keys' => array('kisa link', 'kisa', 'short', 'link', 'kis'));
    }
    $actions[] = array('label' => lang('Comments'), 'icon' => 'bi-chat-dots', 'url' => $base_url . '/view_comments.php', 'keys' => array('yorum', 'comment', 'com', 'yor'));
    $actions[] = array('label' => lang('Auto Dialogs'), 'icon' => 'bi-chat-square-text', 'url' => $base_url . '/view_auto_dialogs.php', 'keys' => array('dialog', 'auto', 'oto', 'diy'));

    // ── Dosyalar / Files ──────────────────────────────────────────────────
    $actions[] = array('label' => lang('Files'), 'icon' => 'bi-folder2-open', 'url' => $base_url . '/view_files.php', 'keys' => array('dosya', 'dosyalar', 'file', 'files', 'fil', 'dos'));
    $actions[] = array('label' => lang('Add File'), 'icon' => 'bi-file-earmark-arrow-up', 'url' => $base_url . '/add_file.php', 'keys' => array('dosya yukle', 'dosya ekle', 'upload', 'add file', 'yukle'));

    // ── e-Ticaret / eCommerce ─────────────────────────────────────────────
    if ($can_ecommerce) {
        $actions[] = array('label' => lang('Orders'), 'icon' => 'bi-receipt', 'url' => $base_url . '/view_orders.php', 'keys' => array('siparis', 'siparisler', 'order', 'orders', 'ord', 'sip'));
        $actions[] = array('label' => lang('Products'), 'icon' => 'bi-box-seam', 'url' => $base_url . '/view_products.php', 'keys' => array('urun', 'urunler', 'product', 'products', 'pro', 'uru'));
        $actions[] = array('label' => lang('Add Product'), 'icon' => 'bi-box-seam', 'url' => $base_url . '/add_product.php', 'keys' => array('urun ekle', 'add product', 'yeni urun', 'urunek'));
        $actions[] = array('label' => lang('Product Groups'), 'icon' => 'bi-boxes', 'url' => $base_url . '/view_product_groups.php', 'keys' => array('urun grubu', 'grup', 'product group', 'group', 'gru'));
        $actions[] = array('label' => lang('Add Product Group'), 'icon' => 'bi-boxes', 'url' => $base_url . '/add_product_group.php', 'keys' => array('grup ekle', 'add group', 'yeni grup', 'grupekle'));
        $actions[] = array('label' => lang('Offers'), 'icon' => 'bi-percent', 'url' => $base_url . '/view_offers.php', 'keys' => array('teklif', 'teklifler', 'indirim', 'offer', 'offers', 'off', 'tek', 'ind'));
        $actions[] = array('label' => lang('Add Offer'), 'icon' => 'bi-percent', 'url' => $base_url . '/add_offer.php', 'keys' => array('teklif ekle', 'add offer', 'indirim ekle', 'teklifekle'));
        $actions[] = array('label' => lang('Gift Cards'), 'icon' => 'bi-gift', 'url' => $base_url . '/view_gift_cards.php', 'keys' => array('hediye', 'gift', 'kart', 'giftcard', 'hed'));
        $actions[] = array('label' => lang('Shipping Methods'), 'icon' => 'bi-truck', 'url' => $base_url . '/view_shipping_methods.php', 'keys' => array('kargo', 'shipping', 'gonder', 'gönderim', 'kar'));
        $actions[] = array('label' => lang('Currencies'), 'icon' => 'bi-currency-exchange', 'url' => $base_url . '/view_currencies.php', 'keys' => array('para', 'doviz', 'döviz', 'currency', 'cur', 'par', 'döv'));
        $actions[] = array('label' => lang('Countries'), 'icon' => 'bi-globe2', 'url' => $base_url . '/view_countries.php', 'keys' => array('ulke', 'ülke', 'country', 'countries', 'ulk'));
        $actions[] = array('label' => lang('Tax Zones'), 'icon' => 'bi-receipt-cutoff', 'url' => $base_url . '/view_tax_zones.php', 'keys' => array('vergi', 'kdv', 'tax', 'ver'));
        $actions[] = array('label' => lang('Zones'), 'icon' => 'bi-map', 'url' => $base_url . '/view_zones.php', 'keys' => array('bolge', 'bölge', 'zone', 'zon', 'böl'));
        $actions[] = array('label' => lang('States'), 'icon' => 'bi-geo-alt', 'url' => $base_url . '/view_states.php', 'keys' => array('sehir', 'şehir', 'eyalet', 'state', 'seh'));
        $actions[] = array('label' => lang('Order Reports'), 'icon' => 'bi-bar-chart', 'url' => $base_url . '/view_order_reports.php', 'keys' => array('siparis rapor', 'order report', 'rapor sip', 'raporord'));
    }

    // ── Visitors ──────────────────────────────────────────────────────────
    $actions[] = array('label' => lang('Visitor Reports'), 'icon' => 'bi-people', 'url' => $base_url . '/view_visitor_reports.php', 'keys' => array('ziyaretci', 'ziyaretçi', 'visitor', 'visit', 'zia', 'rap'));
    $actions[] = array('label' => lang('Visitor Report'), 'icon' => 'bi-graph-up', 'url' => $base_url . '/view_visitor_report.php', 'keys' => array('ziyaret rapor', 'visitor report', 'rapor ziy', 'ziyrapor'));

    // ── Contacts ──────────────────────────────────────────────────────────
    if ($can_contacts) {
        $actions[] = array('label' => lang('Contacts'), 'icon' => 'bi-people', 'url' => $base_url . '/view_contacts.php', 'keys' => array('kisi', 'kişi', 'contact', 'rehber', 'con', 'kis', 'reh'));
        $actions[] = array('label' => lang('Add Contact'), 'icon' => 'bi-person-plus', 'url' => $base_url . '/add_contact.php', 'keys' => array('kisi ekle', 'add contact', 'yeni kisi', 'kisieki'));
        $actions[] = array('label' => lang('Contact Groups'), 'icon' => 'bi-people-fill', 'url' => $base_url . '/view_contact_groups.php', 'keys' => array('grup kisi', 'contact group', 'kisigrup'));
    }

    // ── Users ─────────────────────────────────────────────────────────────
    if ($can_manage) {
        $actions[] = array('label' => lang('Users'), 'icon' => 'bi-person-gear', 'url' => $base_url . '/view_users.php', 'keys' => array('kullanici', 'kullanıcı', 'user', 'usr', 'kul'));
        $actions[] = array('label' => lang('Add User'), 'icon' => 'bi-person-plus', 'url' => $base_url . '/add_user.php', 'keys' => array('kullanici ekle', 'add user', 'yeni kullanici', 'kullanicieki'));
    }

    // ── Campaigns ─────────────────────────────────────────────────────────
    if ($can_manage) {
        $actions[] = array('label' => lang('Email Campaigns'), 'icon' => 'bi-megaphone', 'url' => $base_url . '/view_email_campaigns.php', 'keys' => array('kampanya', 'mail', 'email', 'campaign', 'kamp'));
        $actions[] = array('label' => lang('Add Email Campaign'), 'icon' => 'bi-megaphone', 'url' => $base_url . '/add_email_campaign.php', 'keys' => array('kampanya ekle', 'add campaign', 'kampanyaekle'));
        $actions[] = array('label' => lang('Calendars'), 'icon' => 'bi-calendar3', 'url' => $base_url . '/view_calendars.php', 'keys' => array('takvim', 'calendar', 'tak', 'cal'));
        $actions[] = array('label' => lang('Submitted Forms'), 'icon' => 'bi-ui-checks', 'url' => $base_url . '/view_submitted_forms.php', 'keys' => array('form', 'gonderilen', 'submitted', 'frm', 'gon'));
        $actions[] = array('label' => lang('Menus'), 'icon' => 'bi-menu-button', 'url' => $base_url . '/view_menus.php', 'keys' => array('menu', 'men'));
        $actions[] = array('label' => lang('Ads'), 'icon' => 'bi-badge-ad', 'url' => $base_url . '/view_ads.php', 'keys' => array('reklam', 'ad', 'ads', 'rek'));

        // Every settings section, from the one list the hub and the sidebar
        // of the dialog draw from (includes/settings/registry.php).
        // Registering them is what answers "where is that setting": the
        // operator types the thing itself (ssl, waf, cron, kargo) and the
        // settings open on the card holding it instead of on a long page.
        // The keywords are the field names of the section, not just its
        // title, because nobody searches for "Feature Options" when they
        // are looking for the cart.
        if (!defined('PG_SETTINGS_MENU')) {
            define('PG_SETTINGS_MENU', true);
        }

        include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

        // Plain "Settings" opens on the category last used; the eight
        // below each name one.
        $actions[] = array('label' => lang('Settings'), 'icon' => 'bi-gear', 'url' => $base_url . '/' . pg_settings_link(), 'keys' => array('ayar', 'ayarlar', 'setting', 'settings', 'set', 'aya'));

        foreach (pg_settings_categories() as $settings_key => $settings_category) {

            $actions[] = array(
                'label' => lang('Settings') . ' - ' . $settings_category['label'],
                'icon'  => $settings_category['icon'],
                'url'   => $base_url . '/' . pg_settings_url($settings_key),
                'keys'  => array_merge(
                    array(mb_strtolower($settings_category['label'], 'UTF-8')),
                    call_user_func_array('array_merge', array_values($settings_category['keywords']))));

            foreach ($settings_category['sections'] as $settings_section_id => $settings_section_label) {

                $actions[] = array(
                    'label' => lang('Settings') . ' - ' . $settings_section_label,
                    'icon'  => $settings_category['icon'],
                    'url'   => $base_url . '/' . pg_settings_url($settings_key) . '#' . $settings_section_id,
                    'keys'  => isset($settings_category['keywords'][$settings_section_id])
                        ? $settings_category['keywords'][$settings_section_id]
                        : array());
            }
        }
        $actions[] = array('label' => lang('Log'), 'icon' => 'bi-journal-text', 'url' => $base_url . '/view_log.php', 'keys' => array('log', 'kayit', 'journal', 'akt'));
        $actions[] = array('label' => lang('Backups'), 'icon' => 'bi-database', 'url' => $base_url . '/backups.php', 'keys' => array('yedek', 'backup', 'bak', 'yed'));
        $actions[] = array('label' => lang('SMTP Settings'), 'icon' => 'bi-envelope-at', 'url' => $base_url . '/smtp_settings.php', 'keys' => array('smtp', 'mail ayar', 'email ayar', 'smtpayar'));
    }

    // ── Design ────────────────────────────────────────────────────────────
    if ($can_design) {
        $actions[] = array('label' => lang('Styles'), 'icon' => 'bi-window', 'url' => $base_url . '/view_styles.php', 'keys' => array('stil', 'stiller', 'style', 'styles', 'stl'));
        $actions[] = array('label' => lang('Add Style'), 'icon' => 'bi-window-plus', 'url' => $base_url . '/add_style.php', 'keys' => array('stil ekle', 'add style', 'yeni stil', 'stilekle'));
        $actions[] = array('label' => lang('Themes'), 'icon' => 'bi-palette', 'url' => $base_url . '/view_themes.php', 'keys' => array('tema', 'theme', 'them', 'tem'));
        $actions[] = array('label' => lang('Design Files'), 'icon' => 'bi-filetype-css', 'url' => $base_url . '/view_design_files.php', 'keys' => array('tasarim dosya', 'design file', 'css', 'js', 'des', 'tas'));
        $actions[] = array('label' => lang('Common Regions'), 'icon' => 'bi-columns-gap', 'url' => $base_url . '/view_regions.php?filter=all_common_regions', 'keys' => array('ortak bolge', 'common region', 'region', 'reg', 'ort', 'common', 'bol'));
        $actions[] = array('label' => lang('Login Regions'), 'icon' => 'bi-shield-lock', 'url' => $base_url . '/view_regions.php?filter=all_login_regions', 'keys' => array('giris bolge', 'login region', 'logi', 'gir'));
        $actions[] = array('label' => lang('Designer Regions'), 'icon' => 'bi-code-square', 'url' => $base_url . '/view_regions.php?filter=all_designer_regions', 'keys' => array('tasarim bolge', 'designer region', 'desi'));
        $actions[] = array('label' => lang('Dynamic Regions'), 'icon' => 'bi-arrow-repeat', 'url' => $base_url . '/view_regions.php?filter=all_dynamic_regions', 'keys' => array('dinamik bolge', 'dynamic region', 'dyna', 'din'));
        $actions[] = array('label' => lang('Find & Replace'), 'icon' => 'bi-search', 'url' => $base_url . '/find_and_replace.php', 'keys' => array('bul degistir', 'find replace', 'degistir', 'bul'));
    }

    // Empty search: return only quick actions (no DB query needed)
    if (strlen($search) < 1) {
        echo encode_json(array('status' => 'success', 'results' => array(), 'actions' => $actions, 'has_more' => false));
        exit();
    }

    $s = escape('%' . $search . '%');

    // Relevance score: exact=100, starts-with=60, contains=30, secondary=15
    $score_fn = function ($name, $secondary = '') use ($search) {
        $n = mb_strtolower((string) ($name ?? ''));
        $q = mb_strtolower($search);
        $sc = mb_strtolower((string) ($secondary ?? ''));
        $score = 0;
        if ($n === $q)
            $score += 100;
        elseif (mb_strpos($n, $q) === 0)
            $score += 60;
        elseif (mb_strpos($n, $q) !== false)
            $score += 30;
        if ($sc !== '' && mb_strpos($sc, $q) !== false)
            $score += 15;
        return $score;
    };

    $add = function ($type, $id, $name, $sub, $score, $extra = array ()) use (&$results) {
        $results[] = array_merge(array(
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'sub' => $sub,
            'score' => $score
        ), $extra);
    };

    // ── Pages (all authenticated users) ──────────────────────────────────
    $rows = db_items(
        "SELECT page_id AS id, page_name AS name, page_type AS type
         FROM page
         WHERE page_name LIKE '$s'
         LIMIT $per_limit"
    );
    foreach ($rows as $r) {
        $add('page', $r['id'], $r['name'], $r['type'], $score_fn($r['name']));
    }

    // ── Files (all authenticated users) ──────────────────────────────────
    $rows = db_items(
        "SELECT id, name, folder AS folder_id, design
         FROM files
         WHERE name LIKE '$s'
         LIMIT $per_limit"
    );
    foreach ($rows as $r) {
        // Design files restricted to designers+
        if ($r['design'] && !$can_design)
            continue;
        $add(
            'file',
            $r['id'],
            $r['name'],
            '',
            $score_fn($r['name']),
            array('folder_id' => (int) $r['folder_id'], 'design' => (bool) $r['design'])
        );
    }

    // ── Menus (manager+) ─────────────────────────────────────────────────
    if ($can_manage) {
        $rows = db_items(
            "SELECT id, name
             FROM menus
             WHERE name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('menu', $r['id'], $r['name'], '', $score_fn($r['name']));
        }
    }

    // ── Calendars (manager+) ──────────────────────────────────────────────
    if ($can_manage) {
        $rows = db_items(
            "SELECT id, name
             FROM calendars
             WHERE name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('calendar', $r['id'], $r['name'], '', $score_fn($r['name']));
        }
    }

    // ── Email campaign profiles (manager+) ────────────────────────────────
    if ($can_manage) {
        $rows = db_items(
            "SELECT id, name
             FROM email_campaign_profiles
             WHERE name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('email_campaign', $r['id'], $r['name'], '', $score_fn($r['name']));
        }
    }

    // ── Forms (manager+ or manage_forms) ──────────────────────────────────
    if ($can_manage_forms) {
        $rows = db_items(
            "SELECT page_id AS id, page_name AS name
             FROM page
             WHERE " . pg_form_page_sql('page') . "
               AND page_name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('form', $r['id'], $r['name'], '', $score_fn($r['name']));
        }
    }

    // ── Users (manager+) ─────────────────────────────────────────────────
    if ($can_manage) {
        $rows = db_items(
            "SELECT user_id AS id,
                    user_username AS name,
                    user_email AS sub
             FROM user
             WHERE (user_username LIKE '$s'
                OR user_email LIKE '$s')
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('user', $r['id'], $r['name'], $r['sub'], $score_fn($r['name'], $r['sub']));
        }
    }

    // ── E-commerce (manager+ or manage_ecommerce) ────────────────────────
    if ($can_ecommerce) {
        // Products
        $rows = db_items(
            "SELECT id, name, short_description, image_name
             FROM products
             WHERE (name LIKE '$s' OR short_description LIKE '$s')
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add(
                'product',
                $r['id'],
                $r['name'],
                $r['short_description'],
                $score_fn($r['name'], $r['short_description']),
                array('image' => $r['image_name'] ?: null)
            );
        }

        // Product groups
        $rows = db_items(
            "SELECT id, name
             FROM product_groups
             WHERE name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('product_group', $r['id'], $r['name'], '', $score_fn($r['name']));
        }

        // Offers
        $rows = db_items(
            "SELECT id, code, description
             FROM offers
             WHERE (code LIKE '$s' OR description LIKE '$s')
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add(
                'offer',
                $r['id'],
                $r['code'],
                $r['description'],
                $score_fn($r['code'], $r['description'])
            );
        }

        // Orders — search by order_number, customer name, email
        $s_order_num = escape('%' . ltrim($search, '#') . '%');
        $rows = db_items(
            "SELECT
                orders.id,
                orders.order_number,
                TRIM(CONCAT(COALESCE(contacts.first_name,''), ' ', COALESCE(contacts.last_name,''))) AS customer
             FROM orders
             LEFT JOIN contacts ON orders.contact_id = contacts.id
             WHERE orders.status != 'incomplete'
               AND (orders.order_number LIKE '$s_order_num'
                OR contacts.first_name LIKE '$s'
                OR contacts.last_name  LIKE '$s'
                OR contacts.email_address LIKE '$s')
             ORDER BY orders.order_date DESC
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add(
                'order',
                $r['id'],
                '#' . $r['order_number'],
                $r['customer'],
                $score_fn($r['order_number'], $r['customer'])
            );
        }
    }

    // ── Design: styles + design files (designer+) ────────────────────────
    if ($can_design) {
        $rows = db_items(
            "SELECT style_id AS id, style_name AS name
             FROM style
             WHERE style_name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('style', $r['id'], $r['name'], '', $score_fn($r['name']));
        }
    }

    // ── Contacts / Rehber (manager+ or manage_contacts) ──────────────────
    if ($can_contacts) {
        $rows = db_items(
            "SELECT c.id,
                    TRIM(CONCAT(c.first_name, ' ', c.last_name)) AS name,
                    COALESCE(NULLIF(c.email_address,''), NULLIF(c.company,'')) AS sub,
                    COALESCE(NULLIF(f.name,''), NULLIF(c.image,'')) AS image
             FROM contacts c
             LEFT JOIN files f ON f.id = c.file_id AND c.file_id > 0
             WHERE (c.first_name LIKE '$s'
                OR c.last_name LIKE '$s'
                OR c.email_address LIKE '$s'
                OR c.company LIKE '$s')
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $name = $r['name'] ?: lang('Unknown');
            $add(
                'contact',
                $r['id'],
                $name,
                $r['sub'],
                $score_fn($r['name'], $r['sub']),
                array('image' => $r['image'] ?: null)
            );
        }
    }

    // ── Regions (designer+) ───────────────────────────────────────────────
    if ($can_design) {
        $rows = db_items(
            "SELECT cregion_id AS id, cregion_name AS name
             FROM cregion
             WHERE cregion_designer_type = 'no'
               AND cregion_name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('common_region', $r['id'], $r['name'], '', $score_fn($r['name']));
        }

        $rows = db_items(
            "SELECT cregion_id AS id, cregion_name AS name
             FROM cregion
             WHERE cregion_designer_type = 'yes'
               AND cregion_name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('design_region', $r['id'], $r['name'], '', $score_fn($r['name']));
        }

        $rows = db_items(
            "SELECT id, name
             FROM login_regions
             WHERE name LIKE '$s'
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('login_region', $r['id'], $r['name'], '', $score_fn($r['name']));
        }
    }

    // ── Short links (manager+) ────────────────────────────────────────────
    if ($can_manage) {
        $rows = db_items(
            "SELECT id, name, destination_type
             FROM short_links
             WHERE name LIKE '$s' AND name <> ''
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('short_link', $r['id'], $r['name'], $r['destination_type'], $score_fn($r['name']));
        }
    }

    // Sort by score desc, paginate
    usort($results, function ($a, $b) {
        return $b['score'] - $a['score'];
    });
    $has_more = count($results) > $offset + 20;
    $results = array_slice($results, $offset, 20);

    echo encode_json(array('status' => 'success', 'results' => $results, 'actions' => $actions, 'has_more' => $has_more));
    exit();
}
