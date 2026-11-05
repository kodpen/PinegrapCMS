<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
require_once(PG_FUNCTIONS_DIR . '/includes/fn/list_table.php');
$user = validate_user();

// The editor's callback is one value; one sent as an array
// (?CKEditorFuncNum[]=x) counts as not given: it ended in an error page.
pg_request_single_values(array('CKEditorFuncNum'));

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['editor_select_page_or_file'][$key] = trim($value);
    }
}

if (isset($_SESSION['software']['editor_select_page_or_file']['type']) == false) {
    $_SESSION['software']['editor_select_page_or_file']['type'] = 'page';
}

// Short links are a manager-and-up area, so a user (role 3) gets the pages tab instead.
if ((USER_ROLE == 3) && ($_SESSION['software']['editor_select_page_or_file']['type'] == 'short_link')) {
    $_SESSION['software']['editor_select_page_or_file']['type'] = 'page';
}

// If folder is not set yet, set to "all".
if (isset($_SESSION['software']['editor_select_page_or_file']['folder_id']) == false) {
    $_SESSION['software']['editor_select_page_or_file']['folder_id'] = 'all';
}

// If access control type is not set yet, set to "all".
if (isset($_SESSION['software']['editor_select_page_or_file']['access_control_type']) == false) {
    $_SESSION['software']['editor_select_page_or_file']['access_control_type'] = 'all';
}

// if the user clicked on the clear button, then clear the search
if (isset($_GET['clear']) == true) {
    $_SESSION['software']['editor_select_page_or_file']['query'] = '';
}


// If a folder was selected, then store that folder and all child folders
// in an array so that we can later determine if items are in the selected folder scope.
if (($_SESSION['software']['editor_select_page_or_file']['folder_id'] ?? '') != 'all') {
    $folders = array();

    // Start the folders off with the selected folder.
    $folders[] = ($_SESSION['software']['editor_select_page_or_file']['folder_id'] ?? '');

    // Get all folders in order to add child folders to array.
    $all_folders = db_items(
        "SELECT
            folder_id AS id,
            folder_parent AS parent_folder_id
        FROM folder");

    // Get child folders under the selected folder.
    $child_folders = get_child_folders(($_SESSION['software']['editor_select_page_or_file']['folder_id'] ?? ''), $all_folders);

    // Add child folders to array.
    $folders = array_merge($folders, $child_folders);
}


// Create an array that we will use to store all items.
$items = array();

$folders_that_user_has_access_to = array();

if (USER_ROLE == 3) {
    $folders_that_user_has_access_to = get_folders_that_user_has_access_to(USER_ID);
}

$output_type_page_class = '';
$output_type_file_class = '';
$output_type_short_link_class = '';

// Prepare info differently based on the selected type.
switch (($_SESSION['software']['editor_select_page_or_file']['type'] ?? '')) {
    default:
    case 'page':
        $output_type_page_class = ' active';

        // The columns this list sorts by, in the order the table shows them.
        // pg_list_state() keeps the choice in the session as a code, so it survives
        // a language change, and falls back to the default for anything else.
        $sort_columns = array(
            'url'           => lang('URL'),
            'folder'        => lang('Folder'),
            'page_type'     => lang('Page Type'),
            'last_modified' => lang('Last Modified'),
        );

        $sort_default = array('sort' => 'last_modified', 'order' => 'desc');

        $list_state = pg_list_state('editor_select_page_or_file_page', $sort_columns, $sort_default);
        $sort_levels = $list_state['sorts'];

        // The item value each sort code orders by.
        $sort_keys = array(
            'url'           => 'url',
            'folder'        => 'folder_name',
            'page_type'     => 'page_type',
            'last_modified' => 'last_modified_timestamp',
        );

        $list_name = 'editor_select_page_or_file_page';
        $list_columns = array(
            'url'           => array('heading' => lang('URL')),
            'folder'        => array('heading' => lang('Folder')),
            'page_type'     => array('heading' => lang('Page Type')),
            'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
        );

        $where = "";

        // If there is a search query and it is not blank, then prepare filter.
        if ((isset($_SESSION['software']['editor_select_page_or_file']['query']) == true) && (($_SESSION['software']['editor_select_page_or_file']['query'] ?? '') != '')) {
            $where .= "AND (LOWER(CONCAT_WS(',', page.page_name, page.page_type, folder.folder_name, user.user_username)) LIKE '%" . escape(escape_like(pg_search_lower($_SESSION['software']['editor_select_page_or_file']['query'] ?? ''))) . "%')";
        }

        // Pages in the Recycle Bin are not offered: a link to one stops working.
        $where .= function_exists('pg_designer_not_binned_sql') ? pg_designer_not_binned_sql('page.page_folder') : '';

        // Get all pages.
        $pages = db_items(
            "SELECT
                page.page_name AS name,
                page.page_folder AS folder_id,
                folder.folder_name,
                page.page_type AS type,
                page.page_timestamp AS last_modified_timestamp,
                user.user_username AS last_modified_username
            FROM page
            LEFT JOIN folder ON page.page_folder = folder.folder_id
            LEFT JOIN user ON page.page_user = user.user_id
            WHERE
                (folder.folder_archived = '0')
                $where
            ORDER BY page_name ASC");

        // Loop through the pages in order to decide which we want to include.
        foreach ($pages as $page) {
            // Assume that this page should not be included until we find out otherwise.
            $include = false;

            // If this user has edit access to this page's folder,
            // and this page is within the scope of the selected folder,
            // then continue to determine if this page should be included in results.
            if (
                (
                    (USER_ROLE < 3)
                    || (check_folder_access_in_array($page['folder_id'], $folders_that_user_has_access_to) == true)
                )
                &&
                (
                    (($_SESSION['software']['editor_select_page_or_file']['folder_id'] ?? '') == 'all')
                    || (in_array($page['folder_id'], $folders) == true)
                )
            ) {
                // If an access control type has been selected, then get access control type for page,
                // in order to determine if page should be included in results.
                if (($_SESSION['software']['editor_select_page_or_file']['access_control_type'] ?? '') != 'all') {
                    $page['access_control_type'] = get_access_control_type($page['folder_id']);

                    // If the access control type for this page is the same as the selected access
                    // control type, then include page in results.
                    if ($page['access_control_type'] == ($_SESSION['software']['editor_select_page_or_file']['access_control_type'] ?? '')) {
                        $include = true;
                    }

                // Otherwise an access control type has not been selected,
                // so include page in results.
                } else {
                    $include = true;
                }
            }

            // If this page should be included in results, then include it.
            if ($include == true) {
                $url = PATH . $page['name'];

                // url is what the list shows and sorts by; href, the encoded
                // form, is what goes into the link (a name with a space, "#"
                // or "?" would otherwise end the path early).
                $items[] = array(
                    'url' => $url,
                    'href' => PATH . encode_url_path($page['name']),
                    'folder_id' => $page['folder_id'],
                    'folder_name' => $page['folder_name'],
                    'page_type' => $page['type'],
                    'access_control_type' => ($page['access_control_type'] ?? ''),
                    'last_modified_timestamp' => $page['last_modified_timestamp'],
                    'last_modified_username' => $page['last_modified_username']);
            }
        }

        break;

    case 'file':
        $output_type_file_class = ' active';

        // The columns this list sorts by, in the order the table shows them.
        // pg_list_state() keeps the choice in the session as a code, so it survives
        // a language change, and falls back to the default for anything else.
        $sort_columns = array(
            'url'           => lang('URL'),
            'folder'        => lang('Folder'),
            'size'          => lang('Size'),
            'last_modified' => lang('Last Modified'),
        );

        $sort_default = array('sort' => 'last_modified', 'order' => 'desc');

        $list_state = pg_list_state('editor_select_page_or_file_file', $sort_columns, $sort_default);
        $sort_levels = $list_state['sorts'];

        // The item value each sort code orders by.
        $sort_keys = array(
            'url'           => 'url',
            'folder'        => 'folder_name',
            'size'          => 'size',
            'last_modified' => 'last_modified_timestamp',
        );

        $list_name = 'editor_select_page_or_file_file';
        $list_columns = array(
            'url'           => array('heading' => lang('URL')),
            'thumbnail'     => array('heading' => lang('Image'), 'kind' => 'image'),
            'folder'        => array('heading' => lang('Folder')),
            'size'          => array('heading' => lang('Size'), 'kind' => 'number'),
            'last_modified' => array('heading' => lang('Last Modified'), 'kind' => 'date'),
        );

        $where = "";

        // If there is a search query and it is not blank, then prepare filter.
        if ((isset($_SESSION['software']['editor_select_page_or_file']['query']) == true) && (($_SESSION['software']['editor_select_page_or_file']['query'] ?? '') != '')) {
            $where .= "AND (LOWER(CONCAT_WS(',', files.name, folder.folder_name, user.user_username)) LIKE '%" . escape(escape_like(pg_search_lower($_SESSION['software']['editor_select_page_or_file']['query'] ?? ''))) . "%')";
        }

        // Get all files.
        $files = db_items(
            "SELECT
                files.name,
                files.folder AS folder_id,
                folder.folder_name,
                files.size,
                files.type,
                files.timestamp AS last_modified_timestamp,
                user.user_username AS last_modified_username
            FROM files
            LEFT JOIN folder ON files.folder = folder.folder_id
            LEFT JOIN user ON files.user = user.user_id
            WHERE
                (files.design = 0)
                AND (files.attachment = 0)
                AND (folder.folder_archived = '0')
                $where
            ORDER BY files.name ASC");

        // Loop through the files in order to decide which we want to include.
        foreach ($files as $file) {
            // Assume that this files should not be included until we find out otherwise.
            $include = false;

            // If this user has edit access to this files's folder,
            // and this files is within the scope of the selected folder,
            // then continue to determine if this files should be included in results.
            if (
                (
                    (USER_ROLE < 3)
                    || (check_folder_access_in_array($file['folder_id'], $folders_that_user_has_access_to) == true)
                )
                &&
                (
                    (($_SESSION['software']['editor_select_page_or_file']['folder_id'] ?? '') == 'all')
                    || (in_array($file['folder_id'], $folders) == true)
                )
            ) {
                // If an access control type has been selected, then get access control type for file,
                // in order to determine if file should be included in results.
                if (($_SESSION['software']['editor_select_page_or_file']['access_control_type'] ?? '') != 'all') {
                    $file['access_control_type'] = get_access_control_type($file['folder_id']);

                    // If the access control type for this file is the same as the selected access
                    // control type, then include file in results.
                    if ($file['access_control_type'] == ($_SESSION['software']['editor_select_page_or_file']['access_control_type'] ?? '')) {
                        $include = true;
                    }

                // Otherwise an access control type has not been selected,
                // so include file in results.
                } else {
                    $include = true;
                }
            }

            // If this file should be included in results, then include it.
            if ($include == true) {
                $url = PATH . $file['name'];

                $items[] = array(
                    'name' => $file['name'],
                    'url' => $url,
                    'href' => PATH . encode_url_path($file['name']),
                    'folder_id' => $file['folder_id'],
                    'folder_name' => $file['folder_name'],
                    'size' => $file['size'],
                    'file_type' => mb_strtolower($file['type']),
                    'access_control_type' => ($file['access_control_type'] ?? ''),
                    'last_modified_timestamp' => $file['last_modified_timestamp'],
                    'last_modified_username' => $file['last_modified_username']);
            }
        }

        break;

    case 'short_link':
        $output_type_short_link_class = ' active';

        // The columns this list sorts by, in the order the table shows them.
        // pg_list_state() keeps the choice in the session as a code, so it survives
        // a language change, and falls back to the default for anything else.
        $sort_columns = array(
            'url'             => lang('URL'),
            'destination_url' => lang('Destination URL'),
            'folder'          => lang('Folder'),
            'last_modified'   => lang('Last Modified'),
        );

        $sort_default = array('sort' => 'last_modified', 'order' => 'desc');

        $list_state = pg_list_state('editor_select_page_or_file_short_link', $sort_columns, $sort_default);
        $sort_levels = $list_state['sorts'];

        // The item value each sort code orders by.
        $sort_keys = array(
            'url'             => 'url',
            'destination_url' => 'destination_url',
            'folder'          => 'folder_name',
            'last_modified'   => 'last_modified_timestamp',
        );

        $list_name = 'editor_select_page_or_file_short_link';
        $list_columns = array(
            'url'             => array('heading' => lang('URL')),
            'destination_url' => array('heading' => lang('Destination URL')),
            'folder'          => array('heading' => lang('Folder')),
            'last_modified'   => array('heading' => lang('Last Modified'), 'kind' => 'date'),
        );

        $where = "";

        // If there is a search query and it is not blank, then prepare filter.
        if ((isset($_SESSION['software']['editor_select_page_or_file']['query']) == true) && (($_SESSION['software']['editor_select_page_or_file']['query'] ?? '') != '')) {
            $where .= "WHERE (LOWER(CONCAT_WS(',', short_links.name, short_links.destination_type, page.page_name, product_groups.name, products.name, short_links.url, short_links.tracking_code, last_modified_user.user_username)) LIKE '%" . escape(escape_like(pg_search_lower($_SESSION['software']['editor_select_page_or_file']['query'] ?? ''))) . "%')";
        }

        // A link with a token for its address has no name to link to, a
        // name with a * stands for many addresses rather than one, and a link
        // in the Recycle Bin no longer answers (router.php), as with pages.
        $where .= (($where == '') ? 'WHERE ' : ' AND ') . "(short_links.name <> '') AND (short_links.name NOT LIKE '%*%')"
            . (pg_schema_has('short_links', 'recycled') ? " AND (short_links.recycled = '0')" : '');

        // Get all short links.
        $short_links = db_items(
            "SELECT
                short_links.name,
                short_links.destination_type,
                page.page_name,
                page.page_folder AS folder_id,
                folder.folder_name,
                product_groups.address_name AS product_group_address_name,
                products.address_name AS product_address_name,
                short_links.tracking_code,
                short_links.url,
                created_user.user_username AS created_username,
                last_modified_user.user_username AS last_modified_username,
                short_links.last_modified_timestamp
            FROM short_links
            LEFT JOIN page ON short_links.page_id = page.page_id
            LEFT JOIN folder ON page.page_folder = folder.folder_id
            LEFT JOIN product_groups ON short_links.product_group_id = product_groups.id
            LEFT JOIN products ON short_links.product_id = products.id
            LEFT JOIN user AS created_user ON short_links.created_user_id = created_user.user_id
            LEFT JOIN user AS last_modified_user ON short_links.last_modified_user_id = last_modified_user.user_id
            $where
            ORDER BY short_links.name ASC");

        // Loop through the short links in order to decide which we want to include.
        foreach ($short_links as $short_link) {
            // Assume that this short link should not be included until we find out otherwise.
            $include = false;

            // If this short link is within the scope of the selected folder,
            // then continue to determine if this short link should be included in results.
            if (
                (($_SESSION['software']['editor_select_page_or_file']['folder_id'] ?? '') == 'all')
                ||
                (
                    ($short_link['destination_type'] != 'url')
                    && (in_array($short_link['folder_id'], $folders) == true)
                )
            ) {
                // If an access control type has been selected, then determine if short link
                // should be included in results.
                if (($_SESSION['software']['editor_select_page_or_file']['access_control_type'] ?? '') != 'all') {
                    // If the destination type for this short link is not url,
                    // then continue to check if short link should be included.
                    // When a user selects an access control type, other than "all",
                    // then we do not include short links with url destination type.
                    if ($short_link['destination_type'] != 'url') {
                        $short_link['access_control_type'] = get_access_control_type($short_link['folder_id']);

                        // If the access control type for this short link is the same as the selected access
                        // control type, then include short link in results.
                        if ($short_link['access_control_type'] == ($_SESSION['software']['editor_select_page_or_file']['access_control_type'] ?? '')) {
                            $include = true;
                        }
                    }

                // Otherwise an access control type has not been selected,
                // so include short link in results.
                } else {
                    $include = true;
                }
            }

            // If this short link should be included in results, then include it.
            if ($include == true) {
                $url = PATH . $short_link['name'];

                switch ($short_link['destination_type']) {
                    case 'page':
                        $destination_url = PATH . $short_link['page_name'];
                        break;

                    case 'product_group':
                        $destination_url = PATH . $short_link['page_name'] . '/' . $short_link['product_group_address_name'];
                        break;

                    case 'product':
                        $destination_url = PATH . $short_link['page_name'] . '/' . $short_link['product_address_name'];
                        break;

                    case 'url':
                        $destination_url = $short_link['url'];
                        break;
                }

                // If there is a tracking code and the destination type is a certain type,
                // then add tracking code to destination.
                if (
                    ($short_link['tracking_code'] != '')
                    &&
                    (
                        ($short_link['destination_type'] == 'page')
                        || ($short_link['destination_type'] == 'product_group')
                        || ($short_link['destination_type'] == 'product')
                    )
                ) {
                    $destination_url .= '?t=' . $short_link['tracking_code'];
                }

                $folder_id = '';
                $folder_name = '';

                // If the destination type is not url, then prepare some properties.
                if ($short_link['destination_type'] != 'url') {
                    $folder_id = $short_link['folder_id'];
                    $folder_name = $short_link['folder_name'];
                }

                $items[] = array(
                    'url' => $url,
                    'href' => PATH . encode_url_path($short_link['name']),
                    'destination_type' => $short_link['destination_type'],
                    'destination_url' => $destination_url,
                    'folder_id' => $folder_id,
                    'folder_name' => $folder_name,
                    'access_control_type' => ($short_link['access_control_type'] ?? ''),
                    'last_modified_timestamp' => $short_link['last_modified_timestamp'],
                    'last_modified_username' => $short_link['last_modified_username']);
            }
        }

        break;
}

$list_rows = array();

// if there is at least one result to display
if ($items) {
    // Sorted by each level in turn, the way the database lists sort: a size
    // or a time as a number, a page type by the name it is shown under, text
    // in the panel language's order. The driver sorts them again in the
    // browser by a heading click, and keeps that sort in the session.
    $items = pg_list_sort_items($items, $sort_levels, $sort_keys, array('size', 'last_modified_timestamp'), array('page_type' => get_page_type_names()));

    $type = ($_SESSION['software']['editor_select_page_or_file']['type'] ?? '');

    foreach ($items as $index => $item) {
        // The address goes back to the editor that opened this window: a
        // click on it (data-pg-pick, the script below), or a double click
        // or Enter on its row (data-pg-open).
        $cells = array(
            'url' => pg_list_cell('<a href="' . h($item['href']) . '" data-pg-open data-pg-pick="' . h($item['href']) . '">' . h($item['url']) . '</a>'),
            'folder' => pg_list_cell(h($item['folder_name'])),
            'last_modified' => pg_list_table_when_cell($item['last_modified_timestamp'], $item['last_modified_username']),
        );

        // The access control type colours the row; a short link to a URL
        // has no folder and so none.
        if (($type != 'short_link') || ($item['destination_type'] != 'url')) {
            if ($item['access_control_type'] == '') {
                $item['access_control_type'] = get_access_control_type($item['folder_id']);
            }
        } else {
            $item['access_control_type'] = '';
        }

        switch ($type) {
            default:
            case 'page':
                $cells['page_type'] = pg_list_cell(h(get_page_type_name($item['page_type'])));
                break;

            case 'file':
                $thumbnail = '<svg class="bd-placeholder-img img-thumbnail" width="50" height="50" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . h(lang('No Image')) . '"><rect width="100%" height="100%" fill="#868e96"></rect><text x="10%" y="50%" style="font-size: 8px;" fill="#dee2e6" dy=".3em">' . h(lang('No Image')) . '</text></svg>';

                // An image shows its thumbnail once its row is in sight.
                if (($item['name'] != '') && in_array(strtolower((string) $item['file_type']), array('bmp', 'gif', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'svg', 'webp'), true)) {
                    $thumbnail = '<img style="width: 50px;height:50px;" class="img-fluid img-thumbnail" alt="" data-pg-thumb="' . h(pg_image_panel_thumb_url(PATH . encode_url_path($item['name']))) . '" />';
                }

                $cells['thumbnail'] = pg_list_cell($thumbnail, 'align-middle text-center');
                $cells['size'] = pg_list_cell(h(convert_bytes_to_string($item['size'], 'auto')), 'align-middle', array('data-pg-sort-value' => (int) $item['size']));
                break;

            case 'short_link':
                $cells['destination_url'] = pg_list_cell(h($item['destination_url']));
                break;
        }

        $list_rows[] = array('id' => $index + 1, 'class' => $item['access_control_type'], 'cells' => $cells);
    }
}

$list = pg_list_table_printed(array(
    'list' => $list_name,
    'columns' => $list_columns,
    'levels' => $sort_levels,
    'rows' => $list_rows,
));

echo 
output_header_secure(array('title'=>lang('Browse Items'),'icon'=>'folder')) . '
<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
    <div class="container-fluid">
        <span class="navbar-text me-auto">' . lang('Browse Items') . '</span>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item pg-menu-wrap no-popover"  title="' . lang('Software Theme') . '">
                <button class="nav-link nav-link-sm position-relative pg-menu-caret d-none" data-bs-toggle="menu" data-bs-placement="bottom-end" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
                <ul aria-labelledby="bd-theme" class="menu shadow pg-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-menu-min-width: 8rem;">
                    <li><button class="menu-item pg-menu-item-sm rounded-4 p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-3"></i>' . lang('Light') . '</button></li>
                    <li><button class="menu-item pg-menu-item-sm rounded-4 p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-3"></i>' . lang('Dark') . '</button></li>
                    <li><button class="menu-item pg-menu-item-sm rounded-4 p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-3"></i>' . lang('Auto') . '</button></li>
                </ul>
            </li>
            <li class="nav-item">
                <button title="' . lang('Close') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="window.close()" aria-label="' . h(lang('Close')) . '">
                    <span class="bi bi-x-lg"></span>
                </button>
            </li>
        </ul>
    </div>
</nav>
<main class="container">
    <p class="form-text mt-5 mb-0 text-center md:text-start">' . lang('Select the Page, File, or Short Link that you want to link to.') . '</p>
    ' . pg_screen_toolbar(array(
        'search' => array(
            pg_list_table_search_box($list_name),
            pg_list_sort_menu('editor_select_page_or_file.php', $sort_columns, $sort_levels, $sort_default, array('CKEditorFuncNum' => ($_GET['CKEditorFuncNum'] ?? '')), $list_name),
            // Folder and access narrow the list together: one form, so a change
            // of one keeps the other.
            '<form id="search" action="editor_select_page_or_file.php" method="get" class="pg-toolbar-filter disable_shortcut flex-wrap row-gap-2">
                <input type="hidden" name="CKEditorFuncNum" value="' . h(($_GET['CKEditorFuncNum'] ?? '')) . '" />
                <div class="input-group input-group-sm w-auto flex-nowrap">
                    <label class="input-group-text" title="' . h(lang('Folder')) . '" for="folder_id"><i class="bi bi-folder" aria-hidden="true"></i></label>
                    <select id="folder_id" aria-label="' . h(lang('Folder')) . '" name="folder_id" class="form-control form-control-sm w-auto" onchange="this.form.submit()"><option value="all">[' . lang('All') . ']</option>' . select_folder(($_SESSION['software']['editor_select_page_or_file']['folder_id'] ?? '')) . '</select>
                </div>
                <div class="input-group input-group-sm w-auto flex-nowrap">
                    <label class="input-group-text" title="' . h(lang('Access')) . '" for="access_control_type"><i class="bi bi-shield-check" aria-hidden="true"></i></label>
                    <select id="access_control_type" aria-label="' . h(lang('Access')) . '" name="access_control_type" class="form-control form-control-sm w-auto ' . h(($_SESSION['software']['editor_select_page_or_file']['access_control_type'] ?? '')) . '" onchange="this.form.submit()"><option value="all" class="all">[' . lang('All') . ']</option>' . select_access_control_type(($_SESSION['software']['editor_select_page_or_file']['access_control_type'] ?? ''), false) . '</select>
                </div>
                <noscript><button type="submit" class="btn btn-sm btn-outline theme-secondary">' . h(lang('Apply')) . '</button></noscript>
            </form>',
        ),
        'view' => '<div class="btn-group btn-group-sm" role="group" aria-label="' . h(lang('View')) . '">
            <a class="btn btn-text theme-secondary' . $output_type_page_class . '" href="editor_select_page_or_file.php?type=page&amp;CKEditorFuncNum=' . h(urlencode(($_GET['CKEditorFuncNum'] ?? ''))) . '"><i class="bi bi-window me-1" aria-hidden="true"></i>' . lang('Pages') . '</a>
            <a class="btn btn-text theme-secondary' . $output_type_file_class . '" href="editor_select_page_or_file.php?type=file&amp;CKEditorFuncNum=' . h(urlencode(($_GET['CKEditorFuncNum'] ?? ''))) . '"><i class="bi bi-file-earmark me-1" aria-hidden="true"></i>' . lang('Files') . '</a>
            ' . ((USER_ROLE < 3) ? '<a class="btn btn-text theme-secondary' . $output_type_short_link_class . '" href="editor_select_page_or_file.php?type=short_link&amp;CKEditorFuncNum=' . h(urlencode(($_GET['CKEditorFuncNum'] ?? ''))) . '"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . lang('Short Links') . '</a>' : '') . '
        </div>',
    )) . '
    <div class="my-7">
        ' . pg_list_table_html($list['source'], $list['page'], lang('Browse Items')) . '
    </div>
    <script>
    // A picked address goes back to the editor that opened this window.
    document.addEventListener("click", function (event) {
        var link = event.target && event.target.closest ? event.target.closest("a[data-pg-pick]") : null;
        if (!link || !window.opener || !window.opener.CKEDITOR) { return; }
        event.preventDefault();
        window.opener.CKEDITOR.tools.callFunction(' . json_encode((string) ($_GET['CKEditorFuncNum'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ', link.getAttribute("data-pg-pick"));
        window.close();
    });
    </script>
</main>' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . output_footer_secure() ;