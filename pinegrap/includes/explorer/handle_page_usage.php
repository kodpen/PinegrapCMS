<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's "Where used" sub-action for a page: the menu items and
 * short links that lead to it, the content that links to its address, the
 * pages whose settings send visitors or e-mails to it, the products, offers
 * and calendar events that name it and the site settings that choose it.
 * The handler answers through respond(), which ends the request.
 * Called by pg_explorer_handle() and loaded by view_folder_and_files_f.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// Whether a text links to the page's address. A page is reached at
// OUTPUT_PATH . page_name, so a link carries "/<name>" (a site path or a
// full address; "\/<name>" in JSON), followed by the end of the address: a
// quote, a query, a fragment, a space or the end of the text. "/about"
// is not found in "/about-us" nor in "/about/team", which no page answers.
function pg_explorer_page_link_found($text, $name)
{
    $text = (string) $text;
    $name = (string) $name;

    if (($name === '') || ($text === '')) {
        return false;
    }

    return (bool) preg_match('~/' . preg_quote($name, '~') . '(?=$|["\'?#<>\s)\\\\&,;])~i', $text);
}

// The columns of a table that hold a page another page or record chooses:
// every "<something>_page_id", never the owner's own page_id. The product
// table's two older columns (send_to_page, email_page) are named outright.
function pg_explorer_page_usage_pointer_columns($columns)
{
    $out = array();

    foreach ((array) $columns as $column) {
        $column = (string) $column;

        if (($column !== 'page_id') && (preg_match('/^[a-z0-9_]+_page_id$/', $column) || in_array($column, array('send_to_page', 'email_page'), true))) {
            $out[] = $column;
        }
    }

    return $out;
}

// The site settings that hold the page's id, one item each, in the order of
// the labels.
function pg_explorer_page_usage_setting_hits($config, $labels, $page_id)
{
    $hits = array();
    $page_id = (int) $page_id;

    if ($page_id <= 0) {
        return $hits;
    }

    foreach ($labels as $column => $label) {
        if (isset($config[$column]) && ((int) $config[$column] === $page_id)) {
            $hits[] = array('name' => $label, 'note' => '', 'url' => '');
        }
    }

    return $hits;
}

// Rows that pass $keep, capped: 'items' are what $item makes of them,
// 'truncated' whether more passed than the cap.
function pg_explorer_page_usage_take($rows, $keep, $item, $cap)
{
    $items = array();
    $truncated = false;

    foreach ((is_array($rows) ? $rows : array()) as $row) {
        if (!$keep($row)) {
            continue;
        }

        if (count($items) >= $cap) {
            $truncated = true;
            break;
        }

        $items[] = $item($row);
    }

    return array('items' => $items, 'truncated' => $truncated);
}

// "Can I delete, move or rename this page?" Menus, short links and settings
// keep the page by id, so deleting it leaves them pointing nowhere; content
// keeps its address, so renaming or moving it under another name breaks
// those links. On demand only: the content scan walks text columns without
// an index.
function pg_explorer_handle_page_usage($type, $request, $user, $folders_that_user_has_access_to)
{
    $usage_page = db_item(
        "SELECT page_id, page_name, page_folder
        FROM page
        WHERE page_id = '" . (int) (isset($request['page_id']) ? $request['page_id'] : 0) . "'");

    if (!$usage_page) {
        respond(array('status' => 'error', 'message' => lang('Page not found.')));
    }

    if (pg_explorer_folder_visible($usage_page['page_folder'], $folders_that_user_has_access_to) == false) {
        log_activity(lang('access denied to page usage because user does not have access to page'), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $usage_page_id = (int) $usage_page['page_id'];
    $usage_name = (string) $usage_page['page_name'];
    $usage_like = '%/' . escape_like($usage_name) . '%';
    $usage_groups = array();
    $usage_total = 0;
    $usage_cap = 40;
    $usage_window = 200;
    $usage_truncated = false;
    $usage_role = (int) $user['role'];
    $usage_commerce = defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE;

    // Columns are asked for: several arrived with later upgrades.
    $usage_column_cache = array();

    $usage_columns = function ($table, $wanted = null) use (&$usage_column_cache) {

        if (!isset($usage_column_cache[$table])) {
            $have = array();

            foreach ((array) db_items("SHOW COLUMNS FROM " . $table) as $row) {
                $have[] = (string) $row['Field'];
            }

            $usage_column_cache[$table] = $have;
        }

        return ($wanted === null) ? $usage_column_cache[$table] : array_values(array_intersect($wanted, $usage_column_cache[$table]));
    };

    $usage_folder_answers = array();

    $usage_folder_editable = function ($folder_id) use (&$usage_folder_answers) {
        $folder_id = (int) $folder_id;

        if (!isset($usage_folder_answers[$folder_id])) {
            $usage_folder_answers[$folder_id] = (bool) check_edit_access($folder_id);
        }

        return $usage_folder_answers[$folder_id];
    };

    $usage_add = function ($label, $taken) use (&$usage_groups, &$usage_total, &$usage_truncated) {

        if ($taken['items']) {
            $usage_total += count($taken['items']);
            $usage_groups[] = array('label' => $label, 'items' => $taken['items']);
        }

        if ($taken['truncated']) {
            $usage_truncated = true;
        }
    };

    $usage_page_item = function ($row) use ($usage_folder_editable) {
        return array(
            'name' => (string) $row['page_name'],
            'note' => pg_explorer_page_type_name($row['page_type']),
            'url' => $usage_folder_editable($row['page_folder']) ? ('edit_page.php?id=' . (int) $row['page_id']) : '');
    };

    // Menu items: chosen by id, or a typed address that is the page's.
    if (count($usage_columns('menu_items', array('link_page_id', 'link_url'))) == 2) {
        $usage_menus = ($usage_role == 3) ? array_map('intval', (array) get_items_user_can_edit('menus', $user['id'])) : array();

        $usage_add(lang('Menu Items'), pg_explorer_page_usage_take(
            db_items(
                "SELECT menu_items.id, menu_items.name, menu_items.menu_id, menu_items.link_page_id, menu_items.link_url, menus.name AS menu_name
                FROM menu_items
                LEFT JOIN menus ON menus.id = menu_items.menu_id
                WHERE menu_items.link_page_id = '" . $usage_page_id . "'
                OR menu_items.link_url LIKE '" . e($usage_like) . "'
                ORDER BY menus.name, menu_items.sort_order
                LIMIT " . $usage_window),
            function ($row) use ($usage_page_id, $usage_name) {
                return ((int) $row['link_page_id'] === $usage_page_id) || pg_explorer_page_link_found($row['link_url'], $usage_name);
            },
            function ($row) use ($usage_role, $usage_menus) {
                return array(
                    'name' => (string) $row['name'],
                    'note' => (string) $row['menu_name'],
                    'url' => (($usage_role < 3) || in_array((int) $row['menu_id'], $usage_menus, true)) ? ('edit_menu_item.php?id=' . (int) $row['id']) : '');
            },
            $usage_cap));
    }

    // Short links whose destination is this page (a page, a catalog or a
    // catalog detail page keep it in page_id).
    if (count($usage_columns('short_links', array('page_id', 'name'))) == 2) {
        $usage_add(lang('Short Links'), pg_explorer_page_usage_take(
            db_items(
                "SELECT id, name
                FROM short_links
                WHERE page_id = '" . $usage_page_id . "'
                ORDER BY name
                LIMIT " . $usage_window),
            function ($row) {
                return true;
            },
            function ($row) use ($usage_role) {
                return array(
                    'name' => ((string) $row['name'] !== '') ? ('/' . $row['name']) : ('#' . (int) $row['id']),
                    'note' => '',
                    'url' => ($usage_role <= 2) ? ('edit_short_link.php?id=' . (int) $row['id']) : '');
            },
            $usage_cap));
    }

    // Content that links to the page's address. LIKE narrows the rows, the
    // address test drops "/about-us" for "/about".
    $usage_scan = function ($table, $columns, $select, $label, $item) use ($usage_like, $usage_name, $usage_window, $usage_cap, $usage_add) {

        if (count($columns) == 0) {
            return;
        }

        $where = array();

        foreach ($columns as $column) {
            $where[] = $column . " LIKE '" . e($usage_like) . "'";
        }

        $usage_add($label, pg_explorer_page_usage_take(
            db_items("SELECT " . $select . ", " . implode(', ', $columns) . " FROM " . $table . " WHERE (" . implode(' OR ', $where) . ") LIMIT " . $usage_window),
            function ($row) use ($columns, $usage_name) {
                foreach ($columns as $column) {
                    $key = (($dot = strrpos($column, '.')) !== false) ? substr($column, $dot + 1) : $column;

                    if (pg_explorer_page_link_found(isset($row[$key]) ? $row[$key] : '', $usage_name)) {
                        return true;
                    }
                }

                return false;
            },
            $item,
            $usage_cap));
    };

    $usage_scan(
        'pregion LEFT JOIN page ON pregion.pregion_page = page.page_id',
        $usage_columns('pregion', array('pregion_content')) ? array('pregion.pregion_content') : array(),
        'pregion.pregion_name, page.page_id, page.page_name, page.page_folder',
        lang('Page Regions'),
        function ($row) use ($usage_folder_editable) {
            return array(
                'name' => ($row['page_name'] !== null) ? (string) $row['page_name'] : (string) $row['pregion_name'],
                'note' => (string) $row['pregion_name'],
                'url' => (($row['page_id'] > 0) && $usage_folder_editable($row['page_folder'])) ? ('edit_page.php?id=' . (int) $row['page_id']) : '');
        });

    $usage_scan(
        'page',
        $usage_columns('page', array('page_tree_code', 'page_tree_json')),
        'page_id, page_name, page_folder, page_type',
        lang('Pages'),
        $usage_page_item);

    $usage_scan(
        'cregion',
        $usage_columns('cregion', array('cregion_content')),
        'cregion_id, cregion_name, cregion_designer_type',
        lang('Common Regions'),
        function ($row) use ($usage_role) {
            return array(
                'name' => (string) $row['cregion_name'],
                'note' => '',
                'url' => ($usage_role <= 1) ? ((($row['cregion_designer_type'] == 'yes') ? 'edit_designer_region.php?id=' : 'edit_common_region.php?id=') . (int) $row['cregion_id']) : '');
        });

    $usage_scan(
        'style',
        $usage_columns('style', array('style_code', 'style_head', 'style_tree_json')),
        'style_id, style_name, style_layout',
        lang('Styles'),
        function ($row) use ($usage_role) {
            if ($row['style_layout'] == 'visual_designer') {
                $url = 'edit_system_style.php?id=' . (int) $row['style_id'];
            } else {
                $url = ($usage_role <= 1) ? ('edit_custom_style.php?id=' . (int) $row['style_id']) : '';
            }

            return array('name' => (string) $row['style_name'], 'note' => '', 'url' => $url);
        });

    // Edited inside whichever design uses them: named without a link.
    $usage_scan(
        'shared_components',
        $usage_columns('shared_components', array('tree_json')),
        'id, name',
        lang('Shared Components'),
        function ($row) {
            return array('name' => (string) $row['name'], 'note' => '', 'url' => '');
        });

    // Pages whose settings send visitors or e-mails to this page: a form's
    // next or confirmation page, a cart's next page, a catalog's detail page.
    // The settings live on one table per page type ("<type>_pages", keyed by
    // page_id) and on the page itself; every "<something>_page_id" column
    // is asked, so a column a later upgrade adds is asked too.
    $usage_setting_tables = array('page');

    foreach ((array) db_items("SHOW TABLES") as $usage_row) {
        $usage_table = (string) reset($usage_row);

        if (preg_match('/^[a-z0-9_]+_pages$/', $usage_table)) {
            $usage_setting_tables[] = $usage_table;
        }
    }

    $usage_setting_rows = array();

    foreach ($usage_setting_tables as $usage_table) {
        $usage_pointers = pg_explorer_page_usage_pointer_columns($usage_columns($usage_table));

        if (!$usage_pointers || !in_array('page_id', $usage_columns($usage_table), true)) {
            continue;
        }

        $usage_where = array();

        foreach ($usage_pointers as $usage_pointer) {
            $usage_where[] = $usage_table . '.' . $usage_pointer . " = '" . $usage_page_id . "'";
        }

        $usage_from = ($usage_table === 'page') ? 'page' : ($usage_table . ' INNER JOIN page ON page.page_id = ' . $usage_table . '.page_id');

        foreach ((array) db_items(
            "SELECT page.page_id, page.page_name, page.page_folder, page.page_type
            FROM " . $usage_from . "
            WHERE (" . implode(' OR ', $usage_where) . ")
            LIMIT " . $usage_window) as $usage_row) {
            $usage_setting_rows[(int) $usage_row['page_id']] = $usage_row;
        }
    }

    uasort($usage_setting_rows, function ($a, $b) {
        return strcmp((string) $a['page_name'], (string) $b['page_name']);
    });

    $usage_add(lang('Page Settings'), pg_explorer_page_usage_take(array_values($usage_setting_rows), function ($row) {
        return true;
    }, $usage_page_item, $usage_cap));

    // Records of other kinds that choose the page by id.
    $usage_records = array();

    if (defined('ECOMMERCE') && ECOMMERCE === true) {
        $usage_records[] = array('products', 'name', lang('Products'), 'edit_product.php?id=', $usage_commerce || ($usage_role < 3));
        $usage_records[] = array('offers', 'code', lang('Offers'), 'edit_offer.php?id=', $usage_commerce || ($usage_role < 3));
    }

    $usage_records[] = array('calendar_events', 'name', lang('Calendar Events'), 'edit_calendar_event.php?id=', (bool) validate_calendars_access($user, true));

    foreach ($usage_records as $usage_record) {
        list($usage_table, $usage_name_column, $usage_label, $usage_url, $usage_open) = $usage_record;

        $usage_pointers = pg_explorer_page_usage_pointer_columns($usage_columns($usage_table));

        if (!$usage_pointers || !in_array($usage_name_column, $usage_columns($usage_table), true)) {
            continue;
        }

        $usage_where = array();

        foreach ($usage_pointers as $usage_pointer) {
            $usage_where[] = $usage_pointer . " = '" . $usage_page_id . "'";
        }

        $usage_add($usage_label, pg_explorer_page_usage_take(
            db_items(
                "SELECT id, " . $usage_name_column . " AS record_name
                FROM " . $usage_table . "
                WHERE (" . implode(' OR ', $usage_where) . ")
                ORDER BY " . $usage_name_column . "
                LIMIT " . $usage_window),
            function ($row) {
                return true;
            },
            function ($row) use ($usage_url, $usage_open) {
                return array(
                    'name' => ((string) $row['record_name'] !== '') ? (string) $row['record_name'] : ('#' . (int) $row['id']),
                    'note' => '',
                    'url' => $usage_open ? ($usage_url . (int) $row['id']) : '');
            },
            $usage_cap));
    }

    // Site settings that choose the page.
    $usage_setting_labels = array(
        'ecommerce_retrieve_order_next_page_id' => lang('Reorder/Retrieve Order Next Page'),
        'ecommerce_reward_program_email_page_id' => lang('Reward Program, Reward Points'),
        'ecommerce_kvkk_page_id' => lang('KVKK Notice Page'),
        'membership_expiration_warning_email_page_id' => lang('Send Expiration Warning E-mail to Members'));

    $usage_setting_columns = $usage_columns('config', array_keys($usage_setting_labels));

    if ($usage_setting_columns) {
        $usage_config = db_item("SELECT " . implode(', ', $usage_setting_columns) . " FROM config LIMIT 1");
        $usage_setting_hits = $usage_config ? pg_explorer_page_usage_setting_hits($usage_config, $usage_setting_labels, $usage_page_id) : array();

        $usage_add(lang('Settings'), array('items' => $usage_setting_hits, 'truncated' => false));
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'page' => $usage_name,
        'total' => $usage_total,
        'truncated' => $usage_truncated,
        'groups' => $usage_groups));
}
