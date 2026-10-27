<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's "Where used" sub-action: the places content lives that
 * name a file, the settings that hold it and the Workspace messages and tasks
 * that carry it. The handler answers through respond(), which ends the
 * request.
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

// The settings that hold the file's name outright, one item each, in the
// order of the columns asked. Matched whole rather than by LIKE: these hold
// one file name, not a document that mentions one.
function pg_explorer_file_usage_setting_hits($config, $columns, $labels, $name)
{
    $hits = array();

    foreach ($columns as $column) {
        if (((string) $config[$column]) === $name) {
            $hits[] = array('name' => $labels[$column], 'note' => '', 'url' => '');
        }
    }

    return $hits;
}

// "Can I delete this?" is the question the file manager could not
// answer, and the way it was answered instead was by deleting it and
// waiting to see what broke.
//
// files.name IS the address -- every reader resolves a file as
// FILE_DIRECTORY_PATH . '/' . name -- so the name is what the content
// carries, and a LIKE over the places content lives finds it. The
// Workspace ties a file to a message or a task by its id, and is asked
// by id.
//
// On demand only. This walks several text columns without an index
// that could help, which is fine for a button somebody pressed and
// would not be fine on a listing.
function pg_explorer_handle_file_usage($type, $request, $user, $folders_that_user_has_access_to)
{
    $usage_file = pg_explorer_file_row((int) (isset($request['file_id']) ? $request['file_id'] : 0));

    if (!$usage_file) {
        respond(array('status' => 'error', 'message' => lang('Sorry, we could not find the file.')));
    }

    // The same rule the listing applies: a file is only a question
    // for someone who can see its folder.
    if (pg_explorer_folder_visible($usage_file['folder'], $folders_that_user_has_access_to) == false) {
        log_activity(lang('access denied because user does not have access to file'), $_SESSION['sessionusername']);
        respond(array('status' => 'error', 'message' => lang('Access denied')));
    }

    $usage_name = (string) $usage_file['name'];
    $usage_like = '%' . escape_like($usage_name) . '%';
    $usage_groups = array();
    $usage_total = 0;
    $usage_cap = 40;
    $usage_truncated = false;

    // Columns are asked for rather than assumed: half of these arrived
    // with the visual designer and are simply absent on a database that
    // has taken the code but not the upgrade.
    $usage_columns = function ($table, $wanted) {

        $rows = db_items("SHOW COLUMNS FROM " . $table);

        if (!$rows) {
            return array();
        }

        $have = array();

        foreach ($rows as $row) {
            $have[$row['Field']] = true;
        }

        $out = array();

        foreach ($wanted as $column) {
            if (isset($have[$column])) {
                $out[] = $column;
            }
        }

        return $out;
    };

    // One source: a table, the columns worth scanning, and how to name
    // and reach what was found.
    $usage_scan = function ($table, $columns, $select, $label, $link) use ($usage_like, &$usage_groups, &$usage_total, &$usage_truncated, $usage_cap) {

        if (count($columns) == 0) {
            return;
        }

        $where = array();

        foreach ($columns as $column) {
            $where[] = $column . " LIKE '" . e($usage_like) . "'";
        }

        $rows = db_items(
            "SELECT " . $select . "
            FROM " . $table . "
            WHERE (" . implode(' OR ', $where) . ")
            LIMIT " . ($usage_cap + 1));

        if (!$rows) {
            return;
        }

        if (count($rows) > $usage_cap) {
            $usage_truncated = true;
            $rows = array_slice($rows, 0, $usage_cap);
        }

        $items = array();

        foreach ($rows as $row) {
            $items[] = $link($row);
        }

        $usage_total += count($items);
        $usage_groups[] = array('label' => $label, 'items' => $items);
    };

    // Page regions, named by the page they belong to: a region id means
    // nothing to anybody, and the page is where it is edited from.
    $usage_scan(
        'pregion LEFT JOIN page ON pregion.pregion_page = page.page_id',
        $usage_columns('pregion', array('pregion_content')),
        'pregion.pregion_id, pregion.pregion_name, page.page_id, page.page_name',
        lang('Page Regions'),
        function ($row) {
            return array(
                'name' => ($row['page_name'] !== null) ? (string) $row['page_name'] : (string) $row['pregion_name'],
                'note' => (string) $row['pregion_name'],
                'url' => ($row['page_id'] > 0) ? ('edit_page.php?id=' . (int) $row['page_id']) : '');
        });

    $usage_scan(
        'cregion',
        $usage_columns('cregion', array('cregion_content')),
        'cregion_id, cregion_name, cregion_designer_type',
        lang('Common Regions'),
        function ($row) {
            return array(
                'name' => (string) $row['cregion_name'],
                'note' => '',
                'url' => (($row['cregion_designer_type'] == 'yes') ? 'edit_designer_region.php?id=' : 'edit_common_region.php?id=') . (int) $row['cregion_id']);
        });

    $usage_scan(
        'dregion',
        $usage_columns('dregion', array('dregion_code')),
        'dregion_id, dregion_name',
        lang('Dynamic Regions'),
        function ($row) {
            return array('name' => (string) $row['dregion_name'], 'note' => '', 'url' => 'edit_dynamic_region.php?id=' . (int) $row['dregion_id']);
        });

    // The visual designer keeps a page's markup on the page itself and
    // the design's assets on the style; both columns arrived in 4.4.
    $usage_scan(
        'page',
        $usage_columns('page', array('page_tree_code', 'page_tree_json')),
        'page_id, page_name',
        lang('Pages'),
        function ($row) {
            return array('name' => (string) $row['page_name'], 'note' => '', 'url' => 'edit_page.php?id=' . (int) $row['page_id']);
        });

    $usage_scan(
        'style',
        $usage_columns('style', array('style_code', 'style_head', 'style_tree_json', 'style_custom_css', 'style_custom_js')),
        'style_id, style_name, style_layout',
        lang('Styles'),
        function ($row) {
            return array(
                'name' => (string) $row['style_name'],
                'note' => '',
                'url' => (($row['style_layout'] == 'visual_designer') ? 'edit_system_style.php?id=' : 'edit_custom_style.php?id=') . (int) $row['style_id']);
        });

    // Shared components and system widgets have no screen of their own
    // -- they are edited inside whichever design uses them -- so they
    // are reported by name and without a link rather than with one that
    // goes nowhere.
    $usage_scan(
        'shared_components',
        $usage_columns('shared_components', array('tree_json')),
        'id, name',
        lang('Shared Components'),
        function ($row) {
            return array('name' => (string) $row['name'], 'note' => '', 'url' => '');
        });

    if (defined('ECOMMERCE') && ECOMMERCE === true) {

        $usage_scan(
            'products',
            $usage_columns('products', array('image_name', 'full_description', 'details', 'short_description')),
            'id, name, short_description',
            lang('Products'),
            function ($row) {
                return array(
                    'name' => (string) $row['name'],
                    'note' => (string) $row['short_description'],
                    'url' => 'edit_product.php?id=' . (int) $row['id']);
            });

        $usage_scan(
            'product_groups',
            $usage_columns('product_groups', array('image_name', 'description', 'short_description')),
            'id, name',
            lang('Product Groups'),
            function ($row) {
                return array('name' => (string) $row['name'], 'note' => '', 'url' => 'edit_product_group.php?id=' . (int) $row['id']);
            });
    }

    if (defined('ADS') && ADS === true) {

        $usage_scan(
            'ads',
            $usage_columns('ads', array('content')),
            'id, name',
            lang('Ads'),
            function ($row) {
                return array('name' => (string) $row['name'], 'note' => '', 'url' => 'edit_ad.php?id=' . (int) $row['id']);
            });
    }

    // Settings that name a file outright. Matched whole rather than by
    // LIKE: these hold one file name, not a document that mentions one.
    $usage_settings = array(
        'og_default_image' => lang('Default Share Image'),
        'organization_logo' => lang('Organization Logo'),
        'app_icon' => lang('Application Icon'));

    $usage_setting_columns = $usage_columns('config', array_keys($usage_settings));
    $usage_setting_hits = array();

    if (count($usage_setting_columns) > 0) {

        $usage_config = db_item("SELECT " . implode(', ', $usage_setting_columns) . " FROM config LIMIT 1");

        if ($usage_config) {
            $usage_setting_hits = pg_explorer_file_usage_setting_hits($usage_config, $usage_setting_columns, $usage_settings, $usage_name);
        }
    }

    if (count($usage_setting_hits) > 0) {
        $usage_total += count($usage_setting_hits);
        $usage_groups[] = array('label' => lang('Settings'), 'items' => $usage_setting_hits);
    }

    // The Workspace messages that carry the file or tag it, and the
    // tasks that tag it, read by id through idx_file and idx_ref
    // rather than by name. Only channels and tasks the person may
    // read are listed or counted (ws_file_usage()): staff see every
    // channel's folder here, and that does not let them read a
    // private channel's messages.
    if (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED) {
        require_once(PG_FUNCTIONS_DIR . '/includes/workspace/bootstrap.php');

        if (ws_enabled()) {
            $usage_viewer = ws_viewer($user);

            if ($usage_viewer['member']) {
                $usage_workspace = ws_file_usage($usage_viewer, (int) $usage_file['id'], $usage_cap);

                foreach (ws_file_usage_groups($usage_workspace) as $usage_group) {
                    $usage_total += count($usage_group['items']);
                    $usage_groups[] = $usage_group;
                }

                if ($usage_workspace['truncated']) {
                    $usage_truncated = true;
                }
            }
        }
    }

    respond(array(
        'status' => 'success',
        'request' => $type,
        'file' => $usage_name,
        'total' => $usage_total,
        'truncated' => $usage_truncated,
        'groups' => $usage_groups));
}
