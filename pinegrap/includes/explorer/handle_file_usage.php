<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The file manager's "Where used" sub-action: the places content lives that
 * name a file, the settings that hold it, the Workspace messages and tasks
 * that carry it, and the submitted forms, comments and contacts bound to it
 * by id. The handler answers through respond(), which ends the
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

// Whether the edit screen a hit would link to admits the viewer, so the
// list keeps every name but links only where the screen will open; a hit
// without a link is drawn as plain text. 'page' takes whether the page's
// folder is editable (edit_page.php, check_edit_access()), 'commerce'
// whether the viewer holds the commerce right (validate_ecommerce_access()),
// 'ad' whether a role-3 viewer edits the ad's region (edit_ad.php);
// 'designer' and 'administrator' are the screens' validate_area_access().
function pg_explorer_file_usage_screen_open($screen, $role, $allowed)
{
    $role = (int) $role;

    switch ($screen) {
        case 'page':
            return (bool) $allowed;
        case 'designer':
            return $role <= 1;
        case 'administrator':
            return $role === 0;
        case 'commerce':
        case 'ad':
            return ($role < 3) || (bool) $allowed;
    }

    return false;
}

// Products and product groups keep every photo but the cover by file name
// in their gallery tables (Edit Product, Edit Product Group). Each source
// names the table, the column holding the owner's id, the owner's table and
// the screen that edits it.
function pg_explorer_file_usage_gallery_sources()
{
    return array(
        array(
            'xref' => 'products_images_xref',
            'owner_column' => 'product',
            'owner_table' => 'products',
            'url' => 'edit_product.php?id=',
            'label' => lang('Product Gallery Photos')),
        array(
            'xref' => 'product_groups_images_xref',
            'owner_column' => 'product_group',
            'owner_table' => 'product_groups',
            'url' => 'edit_product_group.php?id=',
            'label' => lang('Product Group Gallery Photos')));
}

// A column that holds one file name is matched whole: a LIKE would also
// list photo.jpg.bak for photo.jpg. The caller passes the name escaped
// (e()).
function pg_explorer_file_usage_exact_where($table, $column, $escaped_name)
{
    return $table . '.' . $column . " = '" . $escaped_name . "'";
}

// One gallery hit: the owner's name, linked only when its screen admits
// the viewer.
function pg_explorer_file_usage_gallery_item($row, $source, $can_open)
{
    return array(
        'name' => (string) $row['name'],
        'note' => '',
        'url' => $can_open ? ($source['url'] . (int) $row['id']) : '');
}

// One group of records bound to the file by id. Rows the viewer may not
// open ($can_open) are dropped before anything is counted, so a person
// without the right sees neither the rows nor a number that gives them
// away; the cap applies to what is left. 'group' is null when nothing is
// left.
function pg_explorer_file_usage_bound_group($label, $rows, $item, $can_open, $cap)
{
    $items = array();
    $truncated = false;

    foreach ((is_array($rows) ? $rows : array()) as $row) {
        if (!$can_open($row)) {
            continue;
        }

        if (count($items) >= $cap) {
            $truncated = true;
            break;
        }

        $items[] = $item($row);
    }

    return array(
        'group' => $items ? array('label' => $label, 'items' => $items) : null,
        'truncated' => $truncated);
}

// Edit Submitted Form admits a Manager and above, or a user with the forms
// right who edits the folder of the form's page (edit_submitted_form.php).
function pg_explorer_file_usage_form_open($role, $manage_forms, $folder_editable)
{
    return ((int) $role <= 2) || ($manage_forms && $folder_editable);
}

// A submitted form: its form's name, its reference code as the note.
function pg_explorer_file_usage_form_item($row)
{
    $name = (string) $row['form_name'];

    return array(
        'name' => ($name !== '') ? $name : ('#' . (int) $row['id']),
        'note' => (string) $row['reference_code'],
        'url' => 'edit_submitted_form.php?id=' . (int) $row['id']);
}

// A comment: who wrote it, and the page it sits on.
function pg_explorer_file_usage_comment_item($row)
{
    $name = trim((string) $row['name']);

    return array(
        'name' => ($name !== '') ? $name : ('#' . (int) $row['id']),
        'note' => pg_comment_page_label($row['page_name'], $row['page_id']),
        'url' => 'edit_comment.php?id=' . (int) $row['id']);
}

// A contact: the name, or the e-mail address when there is no name.
function pg_explorer_file_usage_contact_item($row)
{
    $email = (string) $row['email_address'];
    $name = trim(trim((string) $row['first_name']) . ' ' . trim((string) $row['last_name']));

    if ($name === '') {
        $name = ($email !== '') ? $email : ('#' . (int) $row['id']);
        $email = ($name === $email) ? '' : $email;
    }

    return array(
        'name' => $name,
        'note' => $email,
        'url' => 'edit_contact.php?id=' . (int) $row['id']);
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

    $usage_role = (int) $user['role'];

    $usage_open = function ($screen, $allowed) use ($usage_role) {
        return pg_explorer_file_usage_screen_open($screen, $usage_role, $allowed);
    };

    // The folder answer is asked once per folder: many hits share one.
    $usage_folder_answers = array();

    $usage_page_folder_editable = function ($folder_id) use (&$usage_folder_answers) {
        $folder_id = (int) $folder_id;

        if (!isset($usage_folder_answers[$folder_id])) {
            $usage_folder_answers[$folder_id] = (bool) check_edit_access($folder_id);
        }

        return $usage_folder_answers[$folder_id];
    };

    $usage_commerce = defined('USER_MANAGE_ECOMMERCE') && USER_MANAGE_ECOMMERCE;

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
        'pregion.pregion_id, pregion.pregion_name, page.page_id, page.page_name, page.page_folder',
        lang('Page Regions'),
        function ($row) use ($usage_open, $usage_page_folder_editable) {
            return array(
                'name' => ($row['page_name'] !== null) ? (string) $row['page_name'] : (string) $row['pregion_name'],
                'note' => (string) $row['pregion_name'],
                'url' => (($row['page_id'] > 0) && $usage_open('page', $usage_page_folder_editable($row['page_folder']))) ? ('edit_page.php?id=' . (int) $row['page_id']) : '');
        });

    $usage_scan(
        'cregion',
        $usage_columns('cregion', array('cregion_content')),
        'cregion_id, cregion_name, cregion_designer_type',
        lang('Common Regions'),
        function ($row) use ($usage_open) {
            return array(
                'name' => (string) $row['cregion_name'],
                'note' => '',
                'url' => $usage_open('designer', false) ? ((($row['cregion_designer_type'] == 'yes') ? 'edit_designer_region.php?id=' : 'edit_common_region.php?id=') . (int) $row['cregion_id']) : '');
        });

    $usage_scan(
        'dregion',
        $usage_columns('dregion', array('dregion_code')),
        'dregion_id, dregion_name',
        lang('Dynamic Regions'),
        function ($row) use ($usage_open) {
            return array('name' => (string) $row['dregion_name'], 'note' => '', 'url' => $usage_open('administrator', false) ? ('edit_dynamic_region.php?id=' . (int) $row['dregion_id']) : '');
        });

    // The visual designer keeps a page's markup on the page itself and
    // the design's assets on the style; both columns arrived in 4.4.
    $usage_scan(
        'page',
        $usage_columns('page', array('page_tree_code', 'page_tree_json')),
        'page_id, page_name, page_folder',
        lang('Pages'),
        function ($row) use ($usage_open, $usage_page_folder_editable) {
            return array('name' => (string) $row['page_name'], 'note' => '', 'url' => $usage_open('page', $usage_page_folder_editable($row['page_folder'])) ? ('edit_page.php?id=' . (int) $row['page_id']) : '');
        });

    $usage_scan(
        'style',
        $usage_columns('style', array('style_code', 'style_head', 'style_tree_json', 'style_custom_css', 'style_custom_js')),
        'style_id, style_name, style_layout',
        lang('Styles'),
        function ($row) use ($usage_open) {
            // The visual designer admits Managers and users at content
            // level (pg_designer_access()); a classic style is Designer's.
            if ($row['style_layout'] == 'visual_designer') {
                $url = 'edit_system_style.php?id=' . (int) $row['style_id'];
            } else {
                $url = $usage_open('designer', false) ? ('edit_custom_style.php?id=' . (int) $row['style_id']) : '';
            }

            return array(
                'name' => (string) $row['style_name'],
                'note' => '',
                'url' => $url);
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
            function ($row) use ($usage_open, $usage_commerce) {
                return array(
                    'name' => (string) $row['name'],
                    'note' => (string) $row['short_description'],
                    'url' => $usage_open('commerce', $usage_commerce) ? ('edit_product.php?id=' . (int) $row['id']) : '');
            });

        $usage_scan(
            'product_groups',
            $usage_columns('product_groups', array('image_name', 'description', 'short_description')),
            'id, name',
            lang('Product Groups'),
            function ($row) use ($usage_open, $usage_commerce) {
                return array('name' => (string) $row['name'], 'note' => '', 'url' => $usage_open('commerce', $usage_commerce) ? ('edit_product_group.php?id=' . (int) $row['id']) : '');
            });

        // Gallery photos: every photo but the cover, kept by file name, so
        // renaming or converting one drops it from the gallery.
        foreach (pg_explorer_file_usage_gallery_sources() as $usage_gallery) {

            if (count($usage_columns($usage_gallery['xref'], array('file_name', $usage_gallery['owner_column']))) < 2) {
                continue;
            }

            $usage_gallery_rows = db_items(
                "SELECT DISTINCT " . $usage_gallery['owner_table'] . ".id, " . $usage_gallery['owner_table'] . ".name
                FROM " . $usage_gallery['xref'] . "
                INNER JOIN " . $usage_gallery['owner_table'] . " ON " . $usage_gallery['owner_table'] . ".id = " . $usage_gallery['xref'] . "." . $usage_gallery['owner_column'] . "
                WHERE " . pg_explorer_file_usage_exact_where($usage_gallery['xref'], 'file_name', e($usage_name)) . "
                ORDER BY " . $usage_gallery['owner_table'] . ".name
                LIMIT " . ($usage_cap + 1));

            if (!$usage_gallery_rows) {
                continue;
            }

            if (count($usage_gallery_rows) > $usage_cap) {
                $usage_truncated = true;
                $usage_gallery_rows = array_slice($usage_gallery_rows, 0, $usage_cap);
            }

            $usage_gallery_items = array();

            foreach ($usage_gallery_rows as $usage_gallery_row) {
                $usage_gallery_items[] = pg_explorer_file_usage_gallery_item($usage_gallery_row, $usage_gallery, $usage_open('commerce', $usage_commerce));
            }

            $usage_total += count($usage_gallery_items);
            $usage_groups[] = array('label' => $usage_gallery['label'], 'items' => $usage_gallery_items);
        }
    }

    if (defined('ADS') && ADS === true) {

        // A role-3 user opens an ad only in a region they edit (edit_ad.php).
        $usage_ad_regions = array();

        if ($usage_role == 3) {
            foreach ((array) get_items_user_can_edit('ad_regions', $user['id']) as $usage_ad_region) {
                $usage_ad_regions[] = (int) $usage_ad_region;
            }
        }

        $usage_scan(
            'ads',
            $usage_columns('ads', array('content')),
            'id, name, ad_region_id',
            lang('Ads'),
            function ($row) use ($usage_open, $usage_ad_regions) {
                return array('name' => (string) $row['name'], 'note' => '', 'url' => $usage_open('ad', in_array((int) $row['ad_region_id'], $usage_ad_regions, true)) ? ('edit_ad.php?id=' . (int) $row['id']) : '');
            });
    }

    // Records that hold the file by id rather than by name: submitted form
    // attachments, comment attachments and contact pictures. Deleting the
    // file takes it away from them, so they count. Each group keeps only
    // the records whose edit screen admits the viewer (the same checks
    // those screens make); a wider window than the cap is read so rows
    // the viewer may not open do not crowd out the ones they may.
    $usage_file_id = (int) $usage_file['id'];
    $usage_window = 200;
    $usage_bound = array();

    if (count($usage_columns('form_data', array('file_id', 'form_id'))) == 2) {
        $usage_manage_forms = !empty($user['manage_forms']);

        $usage_bound[] = array(
            lang('Submitted Forms'),
            db_q(
                "SELECT DISTINCT forms.id, forms.reference_code, custom_form_pages.form_name, page.page_folder
                FROM form_data
                INNER JOIN forms ON forms.id = form_data.form_id
                LEFT JOIN custom_form_pages ON custom_form_pages.page_id = forms.page_id
                LEFT JOIN page ON page.page_id = forms.page_id
                WHERE form_data.file_id = ?
                ORDER BY forms.id DESC
                LIMIT " . $usage_window,
                array($usage_file_id)),
            'pg_explorer_file_usage_form_item',
            function ($row) use ($usage_role, $usage_manage_forms, $usage_page_folder_editable) {
                return pg_explorer_file_usage_form_open($usage_role, $usage_manage_forms, ($usage_role > 2) && $usage_manage_forms && $usage_page_folder_editable($row['page_folder']));
            });
    }

    if (count($usage_columns('comments', array('file_id'))) == 1) {
        $usage_bound[] = array(
            lang('Comments'),
            db_q(
                "SELECT comments.id, comments.name, comments.page_id, page.page_name, page.page_folder
                FROM comments
                LEFT JOIN page ON page.page_id = comments.page_id
                WHERE comments.file_id = ?
                ORDER BY comments.id DESC
                LIMIT " . $usage_window,
                array($usage_file_id)),
            'pg_explorer_file_usage_comment_item',
            function ($row) use ($usage_page_folder_editable) {
                return $usage_page_folder_editable($row['page_folder']);
            });
    }

    if ((count($usage_columns('contacts', array('file_id'))) == 1) && validate_contacts_access($user, true)) {
        $usage_bound[] = array(
            lang('Contacts'),
            db_q(
                "SELECT id, first_name, last_name, email_address
                FROM contacts
                WHERE contacts.file_id = ?
                ORDER BY id DESC
                LIMIT " . $usage_window,
                array($usage_file_id)),
            'pg_explorer_file_usage_contact_item',
            function ($row) use ($user) {
                return validate_contact_access($user, (int) $row['id']);
            });
    }

    foreach ($usage_bound as $usage_source) {
        $usage_rows = array();

        if ($usage_source[1]) {
            while ($usage_row = mysqli_fetch_assoc($usage_source[1])) {
                $usage_rows[] = $usage_row;
            }
        }

        $usage_bound_group = pg_explorer_file_usage_bound_group($usage_source[0], $usage_rows, $usage_source[2], $usage_source[3], $usage_cap);

        if ($usage_bound_group['group']) {
            $usage_total += count($usage_bound_group['group']['items']);
            $usage_groups[] = $usage_bound_group['group'];
        }

        if ($usage_bound_group['truncated']) {
            $usage_truncated = true;
        }
    }

    // A photo gallery page shows the images of its folder and of the
    // albums below it, chosen by folder rather than by name, so the
    // pages whose folder holds the file, or a folder above it, use it.
    // A Photo Gallery widget shows the folder its settings choose, or its
    // page's folder when none is chosen (pg_gal_widget_pages_showing()).
    // A page in the Recycle Bin shows nothing.
    if (in_array(strtolower((string) $usage_file['type']), pg_gal_image_types(), true) && ((int) $usage_file['design'] == 0)) {
        $usage_gallery_folders = pg_gal_folders_showing($usage_file['folder'], function ($folder_id) {
            return (int) db_value("SELECT folder_parent FROM folder WHERE folder_id = '" . (int) $folder_id . "'");
        });
        $usage_gallery_folders = array_values(array_diff($usage_gallery_folders, pg_recycle_bin_folder_ids()));

        if ($usage_gallery_folders) {
            $usage_gallery_pages = db_items(
                "SELECT page_id, page_name, page_folder
                FROM page
                WHERE page_type = 'photo gallery'
                AND page_folder IN (" . implode(', ', array_map('intval', $usage_gallery_folders)) . ")
                ORDER BY page_name
                LIMIT " . ($usage_cap + 1));
            $usage_gallery_pages = is_array($usage_gallery_pages) ? $usage_gallery_pages : array();

            // The designed pages, each page once, all in name order.
            $usage_gallery_by_id = array();

            foreach (array_merge($usage_gallery_pages, pg_gal_widget_pages_showing($usage_gallery_folders)) as $usage_row) {
                $usage_gallery_by_id[(int) $usage_row['page_id']] = $usage_row;
            }

            $usage_gallery_pages = array_values($usage_gallery_by_id);
            usort($usage_gallery_pages, function ($a, $b) {
                return strcmp((string) $a['page_name'], (string) $b['page_name']);
            });

            if (count($usage_gallery_pages) > $usage_cap) {
                $usage_truncated = true;
                $usage_gallery_pages = array_slice($usage_gallery_pages, 0, $usage_cap);
            }

            $usage_gallery_page_items = array();

            foreach ($usage_gallery_pages as $usage_row) {
                $usage_gallery_page_items[] = array(
                    'name' => (string) $usage_row['page_name'],
                    'note' => '',
                    'url' => $usage_open('page', $usage_page_folder_editable($usage_row['page_folder'])) ? ('edit_page.php?id=' . (int) $usage_row['page_id']) : '');
            }

            if ($usage_gallery_page_items) {
                $usage_total += count($usage_gallery_page_items);
                $usage_groups[] = array('label' => lang('Photo Gallery'), 'items' => $usage_gallery_page_items);
            }
        }
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

    // The site icon is a settings text (includes/fn/site_icon.php), not a
    // config column.
    if (function_exists('pg_site_icon_name') && (pg_site_icon_name() === $usage_name)) {
        $usage_setting_hits[] = array('name' => lang('Site Icon'), 'note' => '', 'url' => '');
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
