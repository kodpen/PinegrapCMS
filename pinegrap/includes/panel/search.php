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
    // Every call reads the same candidates, best first, whatever the
    // offset: a limit that grew with it let a better match found by a later
    // call jump ahead of rows already shown, so Load More repeated one and
    // never showed the other.
    $per_limit = PG_PANEL_SEARCH_PER_TYPE;
    $results = array();

    // Role helpers
    $role = (int) $user['role']; // 0=admin,1=designer,2=manager,3=user
    $can_design = ($role <= 1);
    $can_manage = ($role <= 2);
    $can_ecommerce = ($role <= 2) || !empty($user['manage_ecommerce']);
    $can_manage_forms = ($role <= 2) || !empty($user['manage_forms']);
    $can_contacts = ($role <= 2) || !empty($user['manage_contacts']);

    // Each chip is offered under the rule of the main-menu entry that opens
    // the same screen (output_menu() in includes/fn/output.php). The
    // rights that need a query are read once, and only for role 3: lower
    // roles pass every one of them.
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

    // Build quick actions based on role (used for both empty and typed searches)
    $base_url = PATH . SOFTWARE_DIRECTORY;
    $actions = array();

    // ── Pages ─────────────────────────────────────────────────────────────
    if ($gate['folders']) {
        $actions[] = array('label' => lang('Pages'), 'icon' => 'bi-file-earmark-text', 'url' => $base_url . '/view_pages.php', 'keys' => array('sayfa', 'sayfalar', 'page', 'pages', 'say'));
    }
    if ($gate['add_page']) {
        $actions[] = array('label' => lang('Add Page'), 'icon' => 'bi-file-earmark-plus', 'url' => $base_url . '/add_page.php', 'keys' => array('sayfa ekle', 'page add', 'yeni sayfa', 'add page', 'sayfaekle'));
    }
    if ($gate['folders']) {
        $actions[] = array('label' => lang('File Manager'), 'icon' => 'bi-folder2', 'url' => $base_url . '/view_folders.php', 'keys' => array('klasor', 'klasör', 'folder', 'fol', 'kla', 'dosya', 'yonetici', 'file', 'manager'));
        $actions[] = array('label' => lang('Add Folder'), 'icon' => 'bi-folder-plus', 'url' => $base_url . '/add_folder.php', 'keys' => array('klasor ekle', 'add folder', 'yeni klasor', 'klasorekle'));
    }
    if ($gate['manage']) {
        $actions[] = array('label' => lang('Short Links'), 'icon' => 'bi-link-45deg', 'url' => $base_url . '/view_short_links.php', 'keys' => array('kisa link', 'kisa', 'short', 'link', 'kis'));
    }
    if ($gate['not_found_log']) {
        $actions[] = array('label' => lang('404 Log'), 'icon' => 'bi-sign-dead-end', 'url' => $base_url . '/view_not_found_log.php', 'keys' => array('404', 'bulunamadi', 'bulunamayan', 'not found', 'kirik link', 'broken link', 'yonlendirme', 'redirect'));
    }
    if ($gate['manage']) {
        $actions[] = array('label' => lang('Accessibility report'), 'icon' => 'bi-universal-access', 'url' => $base_url . '/view_accessibility.php', 'keys' => array('erisilebilirlik', 'erişilebilirlik', 'accessibility', 'a11y', 'wcag', 'engelli'));
    }
    if ($gate['folders']) {
        $actions[] = array('label' => lang('Comments'), 'icon' => 'bi-chat-dots', 'url' => $base_url . '/view_comments.php', 'keys' => array('yorum', 'comment', 'com', 'yor'));
    }
    if ($gate['manage']) {
        $actions[] = array('label' => lang('Auto Dialogs'), 'icon' => 'bi-chat-square-text', 'url' => $base_url . '/view_auto_dialogs.php', 'keys' => array('dialog', 'auto', 'oto', 'diy'));
    }

    // ── Files ─────────────────────────────────────────────────────────────
    if ($gate['folders']) {
        $actions[] = array('label' => lang('Files'), 'icon' => 'bi-folder2-open', 'url' => $base_url . '/view_files.php', 'keys' => array('dosya', 'dosyalar', 'file', 'files', 'fil', 'dos'));
        $actions[] = array('label' => lang('Add File'), 'icon' => 'bi-file-earmark-arrow-up', 'url' => $base_url . '/add_file.php', 'keys' => array('dosya yukle', 'dosya ekle', 'upload', 'add file', 'yukle'));
    }

    // ── eCommerce ─────────────────────────────────────────────────────────
    if ($gate['ecommerce']) {
        $actions[] = array('label' => lang('Orders'), 'icon' => 'bi-receipt', 'url' => $base_url . '/view_orders.php', 'keys' => array('siparis', 'siparisler', 'order', 'orders', 'ord', 'sip'));
        $actions[] = array('label' => lang('Products'), 'icon' => 'bi-box-seam', 'url' => $base_url . '/view_products.php', 'keys' => array('urun', 'urunler', 'product', 'products', 'pro', 'uru'));
        $actions[] = array('label' => lang('Add Product'), 'icon' => 'bi-box-seam', 'url' => $base_url . '/add_product.php', 'keys' => array('urun ekle', 'add product', 'yeni urun', 'urunek'));
        $actions[] = array('label' => lang('Product Groups'), 'icon' => 'bi-boxes', 'url' => $base_url . '/view_product_groups.php', 'keys' => array('urun grubu', 'grup', 'product group', 'group', 'gru'));
        $actions[] = array('label' => lang('Add Product Group'), 'icon' => 'bi-boxes', 'url' => $base_url . '/add_product_group.php', 'keys' => array('grup ekle', 'add group', 'yeni grup', 'grupekle'));
        $actions[] = array('label' => lang('Offers'), 'icon' => 'bi-percent', 'url' => $base_url . '/view_offers.php', 'keys' => array('teklif', 'teklifler', 'indirim', 'offer', 'offers', 'off', 'tek', 'ind'));
        $actions[] = array('label' => lang('Add Offer'), 'icon' => 'bi-percent', 'url' => $base_url . '/add_offer.php', 'keys' => array('teklif ekle', 'add offer', 'indirim ekle', 'teklifekle'));
        $actions[] = array('label' => lang('Gift Cards'), 'icon' => 'bi-gift', 'url' => $base_url . '/view_gift_cards.php', 'keys' => array('hediye', 'gift', 'kart', 'giftcard', 'hed'));
        $actions[] = array('label' => lang('Shipping Methods'), 'icon' => 'bi-truck', 'url' => $base_url . '/view_shipping_methods.php', 'keys' => array('nakliye', 'kargo', 'shipping', 'gonder', 'gönderim', 'kar'));
        $actions[] = array('label' => lang('Currencies'), 'icon' => 'bi-currency-exchange', 'url' => $base_url . '/view_currencies.php', 'keys' => array('para', 'doviz', 'döviz', 'currency', 'cur', 'par', 'döv'));
        $actions[] = array('label' => lang('Countries'), 'icon' => 'bi-globe2', 'url' => $base_url . '/view_countries.php', 'keys' => array('ulke', 'ülke', 'country', 'countries', 'ulk'));
        $actions[] = array('label' => lang('Tax Zones'), 'icon' => 'bi-receipt-cutoff', 'url' => $base_url . '/view_tax_zones.php', 'keys' => array('vergi', 'kdv', 'tax', 'ver'));
        $actions[] = array('label' => lang('Zones'), 'icon' => 'bi-map', 'url' => $base_url . '/view_zones.php', 'keys' => array('bolge', 'bölge', 'zone', 'zon', 'böl'));
        $actions[] = array('label' => lang('States'), 'icon' => 'bi-geo-alt', 'url' => $base_url . '/view_states.php', 'keys' => array('sehir', 'şehir', 'eyalet', 'state', 'seh'));
    }

    // ── Order reports ─────────────────────────────────────────────────────
    // A right of its own: a role 3 user may hold it without manage_ecommerce.
    if ($gate['order_reports']) {
        $actions[] = array('label' => lang('Order Reports'), 'icon' => 'bi-bar-chart', 'url' => $base_url . '/view_order_reports.php', 'keys' => array('siparis rapor', 'order report', 'rapor sip', 'raporord'));
    }

    // ── Visitors ──────────────────────────────────────────────────────────
    if ($gate['visitors']) {
        $actions[] = array('label' => lang('Visitor Reports'), 'icon' => 'bi-people', 'url' => $base_url . '/view_visitor_reports.php', 'keys' => array('ziyaretci', 'ziyaretçi', 'visitor', 'visit', 'zia', 'rap'));
        $actions[] = array('label' => lang('Visitor Report'), 'icon' => 'bi-graph-up', 'url' => $base_url . '/view_visitor_report.php', 'keys' => array('ziyaret rapor', 'visitor report', 'rapor ziy', 'ziyrapor'));
        $actions[] = array('label' => lang('Google Search Console'), 'icon' => 'bi-google', 'url' => $base_url . '/search_console.php', 'keys' => array('search console', 'arama konsolu', 'google arama', 'gsc', 'site haritasi', 'sitemap', 'tiklama', 'gosterim'));
    }

    // ── Contacts ──────────────────────────────────────────────────────────
    if ($gate['contacts']) {
        $actions[] = array('label' => lang('Contacts'), 'icon' => 'bi-people', 'url' => $base_url . '/view_contacts.php', 'keys' => array('kisi', 'kişi', 'contact', 'rehber', 'con', 'kis', 'reh'));
        $actions[] = array('label' => lang('Add Contact'), 'icon' => 'bi-person-plus', 'url' => $base_url . '/add_contact.php', 'keys' => array('kisi ekle', 'add contact', 'yeni kisi', 'kisieki'));
        $actions[] = array('label' => lang('Contact Groups'), 'icon' => 'bi-people-fill', 'url' => $base_url . '/view_contact_groups.php', 'keys' => array('grup kisi', 'contact group', 'kisigrup'));
    }

    // ── Users ─────────────────────────────────────────────────────────────
    if ($gate['users']) {
        $actions[] = array('label' => lang('Users'), 'icon' => 'bi-person-gear', 'url' => $base_url . '/view_users.php', 'keys' => array('kullanici', 'kullanıcı', 'user', 'usr', 'kul'));
        $actions[] = array('label' => lang('Add User'), 'icon' => 'bi-person-plus', 'url' => $base_url . '/add_user.php', 'keys' => array('kullanici ekle', 'add user', 'yeni kullanici', 'kullanicieki'));
    }

    // ── Campaigns, calendars, forms, menus, ads ───────────────────────────
    if ($gate['campaigns']) {
        $actions[] = array('label' => lang('Email Campaigns'), 'icon' => 'bi-megaphone', 'url' => $base_url . '/view_email_campaigns.php', 'keys' => array('kampanya', 'mail', 'email', 'campaign', 'kamp'));
        $actions[] = array('label' => lang('Add Email Campaign'), 'icon' => 'bi-megaphone', 'url' => $base_url . '/add_email_campaign.php', 'keys' => array('kampanya ekle', 'add campaign', 'kampanyaekle'));
    }
    if ($gate['calendars']) {
        $actions[] = array('label' => lang('Calendars'), 'icon' => 'bi-calendar3', 'url' => $base_url . '/view_calendars.php', 'keys' => array('takvim', 'calendar', 'tak', 'cal'));
    }
    if ($gate['forms']) {
        $actions[] = array('label' => lang('Submitted Forms'), 'icon' => 'bi-ui-checks', 'url' => $base_url . '/view_submitted_forms.php', 'keys' => array('form', 'gonderilen', 'submitted', 'frm', 'gon'));
    }
    if ($gate['menus']) {
        $actions[] = array('label' => lang('Menus'), 'icon' => 'bi-menu-button', 'url' => $base_url . '/view_menus.php', 'keys' => array('menu', 'men'));
    }
    if ($gate['ads']) {
        $actions[] = array('label' => lang('Ads'), 'icon' => 'bi-badge-ad', 'url' => $base_url . '/view_ads.php', 'keys' => array('reklam', 'ad', 'ads', 'rek'));
    }

    // ── Settings and system tools ─────────────────────────────────────────
    if ($gate['manage']) {
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
        // The System tools screen and every tool on it, from the groups the
        // screen draws (pg_settings_tool_groups()), so each tool keeps the
        // gate it has there.
        $actions = array_merge($actions, pg_panel_search_tool_actions(pg_settings_tool_groups($user), $base_url, $actions));
    }

    // ── Design ────────────────────────────────────────────────────────────
    if ($gate['design']) {
        $actions[] = array('label' => lang('Styles'), 'icon' => 'bi-window', 'url' => $base_url . '/view_styles.php', 'keys' => array('stil', 'stiller', 'style', 'styles', 'stl'));
        $actions[] = array('label' => lang('Add Style'), 'icon' => 'bi-window-plus', 'url' => $base_url . '/add_style.php', 'keys' => array('stil ekle', 'add style', 'yeni stil', 'stilekle'));
        $actions[] = array('label' => lang('Themes'), 'icon' => 'bi-palette', 'url' => $base_url . '/view_themes.php', 'keys' => array('tema', 'theme', 'them', 'tem'));
        $actions[] = array('label' => lang('Design Files'), 'icon' => 'bi-filetype-css', 'url' => $base_url . '/view_design_files.php', 'keys' => array('tasarim dosya', 'design file', 'css', 'js', 'des', 'tas'));
        $actions[] = array('label' => lang('Common Regions'), 'icon' => 'bi-columns-gap', 'url' => $base_url . '/view_regions.php?filter=all_common_regions', 'keys' => array('ortak bolge', 'common region', 'region', 'reg', 'ort', 'common', 'bol'));
        $actions[] = array('label' => lang('Login Regions'), 'icon' => 'bi-shield-lock', 'url' => $base_url . '/view_regions.php?filter=all_login_regions', 'keys' => array('giris bolge', 'login region', 'logi', 'gir'));
        $actions[] = array('label' => lang('Designer Regions'), 'icon' => 'bi-code-square', 'url' => $base_url . '/view_regions.php?filter=all_designer_regions', 'keys' => array('tasarim bolge', 'designer region', 'desi'));
        $actions[] = array('label' => lang('Find & Replace'), 'icon' => 'bi-search', 'url' => $base_url . '/find_and_replace.php', 'keys' => array('bul degistir', 'find replace', 'degistir', 'bul'));
    }

    // view_regions.php lists dynamic regions for administrators only.
    if ($gate['dynamic_regions']) {
        $actions[] = array('label' => lang('Dynamic Regions'), 'icon' => 'bi-arrow-repeat', 'url' => $base_url . '/view_regions.php?filter=all_dynamic_regions', 'keys' => array('dinamik bolge', 'dynamic region', 'dyna', 'din'));
    }

    // ── Help ──────────────────────────────────────────────────────────────
    // The keyboard shortcuts are a dialog of the header, not a screen: the
    // fragment is what the chip handler in backend.src.js answers to by
    // opening it, as the ? key does.
    if ($gate['help']) {
            $actions[] = array('label' => lang('Keyboard Shortcuts'), 'icon' => 'bi-keyboard', 'url' => '#keyboard-shortcuts', 'keys' => array('klavye', 'kisayol', 'kısayol', 'keyboard', 'shortcut', 'tus', 'tuş'));
    }

    // Empty search: return only quick actions (no DB query needed)
    if (strlen($search) < 1) {
        echo encode_json(array('status' => 'success', 'results' => array(), 'actions' => $actions, 'has_more' => false));
        exit();
    }

    $like = pg_list_like_pattern($search);
    $s = escape($like);
    $s_exact = escape($search);
    $s_prefix = escape(substr($like, 1));

    // ORDER BY of a type's query: exact name, then starts with, then the
    // rest, the order pg_panel_search_score() ranks them in.
    $rank = function ($column, $tie) use ($s_exact, $s_prefix) {
        return "ORDER BY CASE WHEN $column = '$s_exact' THEN 0 WHEN $column LIKE '$s_prefix' THEN 1 ELSE 2 END, $tie";
    };

    $score_fn = function ($name, $secondary = '') use ($search) {
        return pg_panel_search_score($name, $search, $secondary);
    };

    // Each type's query keeps to the records its edit screen would open for
    // this viewer (pg_panel_search_scope_sql()), so a result never ends on
    // "Access denied" and the per-type cap counts only those.
    $folder_ids = ($role == 3) ? get_folders_that_user_has_access_to($user['id']) : array();

    $add = function ($type, $id, $name, $sub, $score, $extra = array ()) use (&$results) {
        $results[] = array_merge(array(
            'type' => $type,
            'id' => $id,
            'name' => $name,
            'sub' => $sub,
            'score' => $score
        ), $extra);
    };

    // ── Pages (folders the viewer may edit) ──────────────────────────────
    $rows = db_items(
        "SELECT page_id AS id, page_name AS name, page_type AS type
         FROM page
         WHERE page_name LIKE '$s'" . pg_panel_search_scope_sql('folder', $role, 'page_folder', $folder_ids) . "
         " . $rank('page_name', 'page_id') . "
         LIMIT $per_limit"
    );
    foreach ($rows as $r) {
        $add('page', $r['id'], $r['name'], $r['type'], $score_fn($r['name']));
    }

    // ── Files (folders the viewer may edit) ──────────────────────────────
    // Design files only for designers and up, and never a document the ERP
    // module keeps: edit_file.php refuses both.
    $file_where = pg_panel_search_scope_sql('folder', $role, 'folder', $folder_ids);
    if (!$can_design) {
        $file_where .= ' AND design = 0';
    }
    if (waf_table_has_column('files', 'erp_doc_type')) {
        $file_where .= " AND erp_doc_type = ''";
    }
    $rows = db_items(
        "SELECT id, name, folder AS folder_id, design
         FROM files
         WHERE name LIKE '$s'" . $file_where . "
         " . $rank('name', 'id') . "
         LIMIT $per_limit"
    );
    foreach ($rows as $r) {
        $add(
            'file',
            $r['id'],
            $r['name'],
            '',
            $score_fn($r['name']),
            array('folder_id' => (int) $r['folder_id'], 'design' => (bool) $r['design'])
        );
    }

    // ── Menus (designer+, as edit_menu.php) ──────────────────────────────
    if ($can_design) {
        $rows = db_items(
            "SELECT id, name
             FROM menus
             WHERE name LIKE '$s'
             " . $rank('name', 'id') . "
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
             " . $rank('name', 'id') . "
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
             " . $rank('name', 'id') . "
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
               AND page_name LIKE '$s'" . pg_panel_search_scope_sql('folder', $role, 'page_folder', $folder_ids) . "
             " . $rank('page_name', 'page_id') . "
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('form', $r['id'], $r['name'], '', $score_fn($r['name']));
        }
    }

    // ── Users (manager+; only accounts below the viewer's role) ──────────
    if ($can_manage) {
        $rows = db_items(
            "SELECT user_id AS id,
                    user_username AS name,
                    user_email AS sub
             FROM user
             WHERE (user_username LIKE '$s'
                OR user_email LIKE '$s')" . pg_panel_search_scope_sql('user_role', $role, 'user_role') . "
             " . $rank('user_username', 'user_id') . "
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('user', $r['id'], $r['name'], $r['sub'], $score_fn($r['name'], $r['sub']));
        }
    }

    // ── E-commerce (manager+ or manage_ecommerce) ────────────────────────
    if ($can_ecommerce) {
        // Products; not those in the Recycle Bin, which the catalogue lists
        // no longer show either.
        $rows = db_items(
            "SELECT id, name, short_description, image_name
             FROM products
             WHERE (name LIKE '$s' OR short_description LIKE '$s')" . pg_catalog_bin_live_sql('products') . "
             " . $rank('name', 'id') . "
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
             WHERE (name LIKE '$s')" . pg_catalog_bin_live_sql('product_groups') . "
             " . $rank('name', 'id') . "
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
             " . $rank('code', 'id') . "
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
        $order_query = ltrim($search, '#');
        $s_order_num = escape(pg_list_like_pattern($order_query));
        $s_order_exact = escape($order_query);
        $s_order_prefix = escape(substr(pg_list_like_pattern($order_query), 1));
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
             ORDER BY CASE WHEN orders.order_number = '$s_order_exact' THEN 0 WHEN orders.order_number LIKE '$s_order_prefix' THEN 1 ELSE 2 END, orders.order_date DESC, orders.id
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add(
                'order',
                $r['id'],
                '#' . $r['order_number'],
                $r['customer'],
                pg_panel_search_score($r['order_number'], $order_query, $r['customer'])
            );
        }
    }

    // ── Design: styles + design files (designer+) ────────────────────────
    if ($can_design) {
        $rows = db_items(
            "SELECT style_id AS id, style_name AS name, style_type AS style_type
             FROM style
             WHERE style_name LIKE '$s'
             " . $rank('style_name', 'style_id') . "
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            // The palette builds edit_<style_type>_style.php from this value,
            // so only the two edit screens that exist pass.
            $style_type = in_array($r['style_type'], array('system', 'custom'), true) ? $r['style_type'] : '';
            $add('style', $r['id'], $r['name'], '', $score_fn($r['name']), array('style_type' => $style_type));
        }
    }

    // ── Contacts / Rehber (manager+ or manage_contacts) ──────────────────
    if ($can_contacts) {
        $contact_group_ids = ($role == 3) ? get_items_user_can_edit('contact_groups', $user['id']) : array();
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
                OR c.company LIKE '$s')" . pg_panel_search_scope_sql('contact_group', $role, 'c.id', $contact_group_ids) . "
             " . $rank("TRIM(CONCAT(c.first_name, ' ', c.last_name))", 'c.id') . "
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
             " . $rank('cregion_name', 'cregion_id') . "
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
             " . $rank('cregion_name', 'cregion_id') . "
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('design_region', $r['id'], $r['name'], '', $score_fn($r['name']));
        }

        $rows = db_items(
            "SELECT id, name
             FROM login_regions
             WHERE name LIKE '$s'
             " . $rank('name', 'id') . "
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
             " . $rank('name', 'id') . "
             LIMIT $per_limit"
        );
        foreach ($rows as $r) {
            $add('short_link', $r['id'], $r['name'], $r['destination_type'], $score_fn($r['name']));
        }
    }

    $page = pg_panel_search_page($results, $offset, 20);

    echo encode_json(array('status' => 'success', 'results' => $page['results'], 'actions' => $actions, 'has_more' => $page['has_more']));
    exit();
}

// Results read per type on every call, Load More or not.
if (!defined('PG_PANEL_SEARCH_PER_TYPE')) {
    define('PG_PANEL_SEARCH_PER_TYPE', 100);
}

// One page of the merged results, $limit long from $offset, and whether
// more follow. Each result has one fixed place (pg_panel_search_compare()),
// so pages asked for at two offsets never share a result and together are
// the best results.
function pg_panel_search_page($results, $offset, $limit)
{
    usort($results, 'pg_panel_search_compare');
    return array(
        'results'  => array_slice($results, $offset, $limit),
        'has_more' => count($results) > $offset + $limit
    );
}

// Order of two results: score high to low, then the type's place in the
// list below, then id low to high. usort() is not stable before PHP 8.0,
// so equal scores need the full tie-break to keep their place between calls.
function pg_panel_search_compare($a, $b)
{
    static $types = array(
        'page', 'file', 'menu', 'calendar', 'email_campaign', 'form', 'user',
        'product', 'product_group', 'offer', 'order', 'style', 'contact',
        'common_region', 'design_region', 'login_region', 'short_link'
    );
    if ($a['score'] != $b['score']) {
        return $a['score'] < $b['score'] ? 1 : -1;
    }
    $rank_a = array_search($a['type'], $types, true);
    $rank_b = array_search($b['type'], $types, true);
    $rank_a = $rank_a === false ? count($types) : $rank_a;
    $rank_b = $rank_b === false ? count($types) : $rank_b;
    if ($rank_a !== $rank_b) {
        return $rank_a < $rank_b ? -1 : 1;
    }
    if ($a['type'] !== $b['type']) {
        return strcmp($a['type'], $b['type']);
    }
    return (int) $a['id'] - (int) $b['id'];
}

// Relevance score of one result: exact=100, starts-with=60, contains=30, plus
// 15 when the secondary text contains the query. Both sides are folded with
// fold_keyword_for_duplicates() (I, İ and ı read as i): mb_strtolower() turns
// İ into i + U+0307, so "iade" never matched "İade" although the database,
// being utf8mb4_unicode_ci, had found it.
function pg_panel_search_score($name, $query, $secondary = '')
{
    $n = fold_keyword_for_duplicates((string) ($name ?? ''));
    $q = fold_keyword_for_duplicates((string) $query);
    $sc = fold_keyword_for_duplicates((string) ($secondary ?? ''));
    $score = 0;
    if ($q === '')
        return $score;
    if ($n === $q)
        $score += 100;
    elseif (mb_strpos($n, $q) === 0)
        $score += 60;
    elseif (mb_strpos($n, $q) !== false)
        $score += 30;
    if ($sc !== '' && mb_strpos($sc, $q) !== false)
        $score += 15;
    return $score;
}

// Search words of each tool, by its script, in Turkish and English. A tool
// missing here is still found by its own label.
function pg_panel_search_tool_keys()
{
    return array(
        'system_tools.php'          => array('sistem araclari', 'sistem araçları', 'araclar', 'araçlar', 'system tools', 'tools', 'sis'),
        'view_log.php'              => array('log', 'kayit', 'site log', 'journal', 'akt'),
        'view_audit_log.php'        => array('denetim', 'denetim izi', 'audit', 'audit trail', 'kim degistirdi', 'den'),
        'view_sessions.php'         => array('oturum', 'oturumlar', 'session', 'sessions', 'giris yapan', 'otu'),
        'smtp_settings.php'         => array('smtp', 'mail ayar', 'email ayar', 'smtpayar'),
        'api_settings.php'          => array('uygulama erisimi', 'uygulama erişimi', 'api anahtar', 'application access', 'api key', 'token', 'uyg'),
        'connections.php'           => array('bagli hesap', 'connected account', 'google', 'microsoft', 'oauth', 'bagla', 'connect'),
        'marketplace_settings.php'  => array('pazaryeri', 'pazar yeri', 'marketplace', 'trendyol', 'hepsiburada', 'n11', 'paz'),
        'view_referral_sources.php' => array('yonlendiren', 'yönlendiren', 'referans kaynak', 'referral', 'referral source', 'yon'),
        'view_currencies.php'       => array('para', 'doviz', 'döviz', 'currency', 'cur'),
        'software_update.php'       => array('guncelle', 'güncelle', 'guncelleme', 'güncelleme', 'update', 'updates', 'gun'),
        'software_repair.php'       => array('onar', 'onarim', 'onarım', 'repair', 'yazilim onar', 'ona'),
        'si.php'                    => array('sistem bilgi', 'system info', 'php info', 'phpinfo', 'sunucu bilgi', 'server info'),
        'view_performance_log.php'  => array('performans', 'performance', 'yavas', 'slow', 'per'),
        'clean_up.php'              => array('temizle', 'temizlik', 'clean', 'clean up', 'eski dosya', 'tem'),
        'database_engine.php'       => array('veritabani', 'veritabanı', 'database', 'innodb', 'myisam', 'tablo onar', 'motor', 'vt'),
        'purge_cache.php'           => array('onbellek', 'önbellek', 'cache', 'purge', 'purge cache', 'temizle cache', 'onb'),
        'mail_queue.php'            => array('posta kuyrugu', 'posta kuyruğu', 'mail kuyruk', 'mail queue', 'queue', 'giden posta', 'kuy'),
        'install'                   => array('yeniden kur', 'kurulum', 'reinstall', 'upgrade', 'install', 'kur'),
        'backups.php'               => array('yedek', 'yedekler', 'backup', 'backups', 'bak', 'yed'),
        'cloudflare.php'            => array('cloudflare', 'cdn', 'clo'),
    );
}

// Palette rows for the System tools screen and each of its tools, from
// pg_settings_tool_groups(). A tool that asks first (a confirm) opens the
// screen, where the question is asked: the palette follows its row without
// one. A screen the palette already lists ($actions) is not repeated. No
// tool, no row, not even the screen's.
function pg_panel_search_tool_actions($groups, $base_url, $actions)
{
    $keys = pg_panel_search_tool_keys();
    $screen_url = $base_url . '/system_tools.php';

    $listed = array();

    foreach ((array) $actions as $action) {
        $listed[$action['url']] = true;
    }

    $rows = array();

    foreach ((array) $groups as $group) {

        foreach ($group['items'] as $item) {

            $script = strtok($item['url'], '?');
            $url = isset($item['confirm']) ? $screen_url : $base_url . '/' . $item['url'];

            if (isset($listed[$url]) && !isset($item['confirm'])) {
                continue;
            }

            $listed[$url] = true;

            $rows[] = array(
                'label' => $item['label'],
                'icon'  => $item['icon'],
                'url'   => $url,
                'keys'  => array_merge(
                    array(mb_strtolower($item['label'], 'UTF-8')),
                    isset($keys[$script]) ? $keys[$script] : array()),
            );
        }
    }

    if (count($rows) === 0) {
        return array();
    }

    array_unshift($rows, array(
        'label' => lang('System tools'),
        'icon'  => 'bi-tools',
        'url'   => $screen_url,
        'keys'  => $keys['system_tools.php'],
    ));

    return $rows;
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
