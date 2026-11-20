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

require_once(PG_FUNCTIONS_DIR . '/includes/fn/panel_screens.php');

function pg_panel_backend_search($request, $action)
{
    // validate_user() could redirect the request to the sign-in page.
    if (!USER_LOGGED_IN) {
        respond(panel_login_refusal());
    }

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
    // the same screen (output_menu() in includes/fn/output.php).
    $gate = pg_panel_search_viewer_gates($user);

    // Build quick actions based on role (used for both empty and typed searches)
    $base_url = PATH . SOFTWARE_DIRECTORY;
    $actions = array();

    // Every screen chip listed before the settings ones, in the list's order.
    foreach (pg_panel_search_screens() as $screen) {
        if (!$screen['after_settings'] && $gate[$screen['gate']]) {
            $actions[] = pg_panel_search_screen_action($screen, $base_url);
        }
    }

    // ── Settings and system tools ─────────────────────────────────────────
    // A manager, or a holder of the Workspace settings right, who finds the
    // Workspace categories alone (pg_settings_categories_for()).
    if (!defined('PG_SETTINGS_MENU')) {
        define('PG_SETTINGS_MENU', true);
    }

    include_once(PG_FUNCTIONS_DIR . '/includes/settings/registry.php');

    $settings_reader = pg_settings_reader($user);

    if ($gate['manage'] || $settings_reader['workspace']) {
        // Every settings section the reader may open, from the one list the
        // hub and the sidebar of the dialog draw from
        // (includes/settings/registry.php).
        // Registering them is what answers "where is that setting": the
        // operator types the thing itself (ssl, waf, cron, kargo) and the
        // settings open on the card holding it instead of on a long page.
        // The keywords are the field names of the section, not just its
        // title, because nobody searches for "Feature Options" when they
        // are looking for the cart.

        // Plain "Settings" opens on the category last used, which may be
        // any of them: the managers' alone. The ones below each name one.
        if ($gate['manage']) {
            $actions[] = array('label' => lang('Settings'), 'icon' => 'bi-gear', 'url' => $base_url . '/' . pg_settings_link(), 'keys' => array('ayar', 'ayarlar', 'setting', 'settings', 'set', 'aya'));
        }

        foreach (pg_settings_categories_for($user, $settings_reader) as $settings_key => $settings_category) {

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
                    'keys'  => pg_connections_place_words(
                        '#settings/' . $settings_key . '/' . $settings_section_id,
                        isset($settings_category['keywords'][$settings_section_id])
                            ? $settings_category['keywords'][$settings_section_id]
                            : array()));
            }
        }
        // The System tools screen and every tool on it, from the groups the
        // screen draws (pg_settings_tool_groups()), so each tool keeps the
        // gate it has there. Those screens are the managers'.
        if ($gate['manage']) {
            $actions = array_merge($actions, pg_panel_search_tool_actions(pg_settings_tool_groups($user), $base_url, $actions));
        }
    }

    // The design chips and the help dialog, after the settings ones.
    foreach (pg_panel_search_screens() as $screen) {
        if ($screen['after_settings'] && $gate[$screen['gate']]) {
            $actions[] = pg_panel_search_screen_action($screen, $base_url);
        }
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
    // Pages, forms and files in the Recycle Bin are left out everywhere
    // below: they are found on the Recycle Bin screen only.
    $rows = db_items(
        "SELECT page_id AS id, page_name AS name, page_type AS type
         FROM page
         WHERE page_name LIKE '$s'" . pg_panel_search_scope_sql('folder', $role, 'page_folder', $folder_ids) . pg_designer_not_binned_sql('page_folder') . "
         " . $rank('page_name', 'page_id') . "
         LIMIT $per_limit"
    );
    foreach ($rows as $r) {
        // The type's name as the page list shows it; the stored code goes
        // along for the result's icon (PAGE_ICONS, backend.src.js).
        $page_type_name = get_page_type_name($r['type']);
        $add('page', $r['id'], $r['name'], ($page_type_name !== null) ? $page_type_name : $r['type'], $score_fn($r['name']), array('page_type' => $r['type']));
    }

    // ── Files (folders the viewer may edit) ──────────────────────────────
    // Design files only for designers and up, and never a document the ERP
    // module keeps: edit_file.php refuses both.
    $file_where = pg_panel_search_scope_sql('folder', $role, 'folder', $folder_ids) . pg_designer_not_binned_sql('folder');
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
               AND page_name LIKE '$s'" . pg_panel_search_scope_sql('folder', $role, 'page_folder', $folder_ids) . pg_designer_not_binned_sql('page_folder') . "
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

    // ── Bookings (the agenda's viewers: roles 0-2, a role-3 staff member
    //    their own) by the customer's name or e-mail address ─────────────
    if (pg_booking_ready()) {
        require_once(PG_FUNCTIONS_DIR . '/includes/fn/booking_agenda.php');

        $booking_scope = pg_booking_agenda_user_scope($user);

        if ($booking_scope !== false) {
            $rows = db_items(
                "SELECT bookings.id, bookings.name, bookings.email, bookings.start_at
                 FROM bookings
                 WHERE bookings.status <> 'held'
                   AND (bookings.name LIKE '$s' OR bookings.email LIKE '$s')" . pg_booking_agenda_scope_sql($booking_scope) . "
                 " . $rank('bookings.name', 'bookings.start_at DESC, bookings.id') . "
                 LIMIT $per_limit"
            );
            foreach ($rows as $r) {
                $sub = pg_booking_agenda_time($r['start_at'], true) . (((string) $r['email'] !== '') ? ' · ' . $r['email'] : '');
                $add('booking', $r['id'], $r['name'], $sub, $score_fn($r['name'], $r['email']));
            }
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
        'common_region', 'design_region', 'login_region', 'short_link',
        'booking'
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

// Palette rows for the System tools screen and each of its tools, from
// pg_settings_tool_groups(), found by the names of the outside services a
// tool holds too (pg_connections_place_words()). A tool that asks first (a confirm) opens the
// screen, where the question is asked: the palette follows its row without
// one. A screen the palette already lists ($actions) is not repeated. No
// tool, no row, not even the screen's.
function pg_panel_search_tool_actions($groups, $base_url, $actions)
{
    $keys = pg_settings_tool_keys();
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
                'keys'  => pg_connections_place_words($script, array_merge(
                    array(mb_strtolower($item['label'], 'UTF-8')),
                    isset($keys[$script]) ? $keys[$script] : array())),
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
