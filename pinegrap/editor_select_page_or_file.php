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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();

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

$extras = '&CKEditorFuncNum=' . h(urlencode(($_GET['CKEditorFuncNum'] ?? '')));

// Create an array that we will use to store all items.
$items = array();

// Create an array that will be used to store data for the column
// that will be sorted.
$items_for_sorting = array();

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

        // if the sort is not set yet, then default it to empty so that the switch below falls
        // through to its default case
        if (isset($_SESSION['software']['editor_select_page_or_file']['sort']) == false) {
            $_SESSION['software']['editor_select_page_or_file']['sort'] = '';
        }

        switch (($_SESSION['software']['editor_select_page_or_file']['sort'] ?? '')) {
            case 'URL':
                $sort_column = 'url';
                break;

            case 'Folder':
                $sort_column = 'folder_name';
                break;
                
            case 'Page Type':
                $sort_column = 'page_type';
                break;

            case 'Last Modified':
                $sort_column = 'last_modified_timestamp';
                break;

            default:
                $sort_column = 'last_modified_timestamp';
                $_SESSION['software']['editor_select_page_or_file']['sort'] = 'Last Modified';
                $_SESSION['software']['editor_select_page_or_file']['order'] = 'desc';
                break;
        }

        // If order is not set, set to ascending.
        if (isset($_SESSION['software']['editor_select_page_or_file']['order']) == false) {
            $_SESSION['software']['editor_select_page_or_file']['order'] = 'asc';
        }

        $output_heading_cells =
            '<th>' . get_column_heading(lang('URL'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th>' . get_column_heading(lang('Folder'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th>' . get_column_heading(lang('Page Type'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th class="noVis"></th>';

        $where = "";

        // If there is a search query and it is not blank, then prepare filter.
        if ((isset($_SESSION['software']['editor_select_page_or_file']['query']) == true) && (($_SESSION['software']['editor_select_page_or_file']['query'] ?? '') != '')) {
            $where .= "AND (LOWER(CONCAT_WS(',', page.page_name, page.page_type, folder.folder_name, user.user_username)) LIKE '%" . escape(escape_like(mb_strtolower(($_SESSION['software']['editor_select_page_or_file']['query'] ?? '')))) . "%')";
        }

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

                $items[] = array(
                    'url' => $url,
                    'folder_id' => $page['folder_id'],
                    'folder_name' => $page['folder_name'],
                    'page_type' => $page['type'],
                    'access_control_type' => ($page['access_control_type'] ?? ''),
                    'last_modified_timestamp' => $page['last_modified_timestamp'],
                    'last_modified_username' => $page['last_modified_username']);

                // Store the appropriate value in the sort array.
                switch ($sort_column) {
                    case 'url':
                        $items_for_sorting[] = $url;
                        break;

                    case 'folder_name':
                        $items_for_sorting[] = $page['folder_name'];
                        break;
                        
                    case 'page_type':
                        $items_for_sorting[] = $page['type'];
                        break;

                    case 'last_modified_timestamp':
                        $items_for_sorting[] = $page['last_modified_timestamp'];
                        break;
                }
            }
        }

        break;

    case 'file':
        $output_type_file_class = ' active';

        // if the sort is not set yet, then default it to empty so that the switch below falls
        // through to its default case
        if (isset($_SESSION['software']['editor_select_page_or_file']['sort']) == false) {
            $_SESSION['software']['editor_select_page_or_file']['sort'] = '';
        }

        switch (($_SESSION['software']['editor_select_page_or_file']['sort'] ?? '')) {
            case 'URL':
                $sort_column = 'url';
                break;

            case 'Folder':
                $sort_column = 'folder_name';
                break;
                
            case 'Size':
                $sort_column = 'size';
                break;

            case 'Last Modified':
                $sort_column = 'last_modified_timestamp';
                break;

            default:
                $sort_column = 'last_modified_timestamp';
                $_SESSION['software']['editor_select_page_or_file']['sort'] = 'Last Modified';
                $_SESSION['software']['editor_select_page_or_file']['order'] = 'desc';
                break;
        }

        // If order is not set, set to ascending.
        if (isset($_SESSION['software']['editor_select_page_or_file']['order']) == false) {
            $_SESSION['software']['editor_select_page_or_file']['order'] = 'asc';
        }

        $output_heading_cells =
            '<th>&nbsp;</th>
            <th>' . get_column_heading(lang('URL'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th>' . get_column_heading(lang('Folder'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th>' . get_column_heading(lang('Size'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th class="noVis"></th>';

        $where = "";

        // If there is a search query and it is not blank, then prepare filter.
        if ((isset($_SESSION['software']['editor_select_page_or_file']['query']) == true) && (($_SESSION['software']['editor_select_page_or_file']['query'] ?? '') != '')) {
            $where .= "AND (LOWER(CONCAT_WS(',', files.name, folder.folder_name, user.user_username)) LIKE '%" . escape(escape_like(mb_strtolower(($_SESSION['software']['editor_select_page_or_file']['query'] ?? '')))) . "%')";
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
                    'folder_id' => $file['folder_id'],
                    'folder_name' => $file['folder_name'],
                    'size' => $file['size'],
                    'file_type' => mb_strtolower($file['type']),
                    'access_control_type' => $file['access_control_type'],
                    'last_modified_timestamp' => $file['last_modified_timestamp'],
                    'last_modified_username' => $file['last_modified_username']);

                // Store the appropriate value in the sort array.
                switch ($sort_column) {
                    case 'url':
                        $items_for_sorting[] = $url;
                        break;

                    case 'folder_name':
                        $items_for_sorting[] = $file['folder_name'];
                        break;

                    case 'size':
                        $items_for_sorting[] = $file['size'];
                        break;

                    case 'last_modified_timestamp':
                        $items_for_sorting[] = $file['last_modified_timestamp'];
                        break;
                }
            }
        }

        break;

    case 'short_link':
        $output_type_short_link_class = ' active';

        // if the sort is not set yet, then default it to empty so that the switch below falls
        // through to its default case
        if (isset($_SESSION['software']['editor_select_page_or_file']['sort']) == false) {
            $_SESSION['software']['editor_select_page_or_file']['sort'] = '';
        }

        switch (($_SESSION['software']['editor_select_page_or_file']['sort'] ?? '')) {
            case 'URL':
                $sort_column = 'url';
                break;

            case 'Destination URL':
                $sort_column = 'destination_url';
                break;

            case 'Folder':
                $sort_column = 'folder_name';
                break;

            case 'Last Modified':
                $sort_column = 'last_modified_timestamp';
                break;

            default:
                $sort_column = 'last_modified_timestamp';
                $_SESSION['software']['editor_select_page_or_file']['sort'] = 'Last Modified';
                $_SESSION['software']['editor_select_page_or_file']['order'] = 'desc';
                break;
        }

        // If order is not set, set to ascending.
        if (isset($_SESSION['software']['editor_select_page_or_file']['order']) == false) {
            $_SESSION['software']['editor_select_page_or_file']['order'] = 'asc';
        }

        $output_heading_cells =
            '<th>' . get_column_heading(lang('URL'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th>' . get_column_heading(lang('Destination URL'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th>' . get_column_heading(lang('Folder'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th>' . get_column_heading(lang('Last Modified'), ($_SESSION['software']['editor_select_page_or_file']['sort'] ?? ''), ($_SESSION['software']['editor_select_page_or_file']['order'] ?? ''), $extras) . '</th>
            <th class="noVis"></th>';

        $where = "";

        // If there is a search query and it is not blank, then prepare filter.
        if ((isset($_SESSION['software']['editor_select_page_or_file']['query']) == true) && (($_SESSION['software']['editor_select_page_or_file']['query'] ?? '') != '')) {
            $where .= "WHERE (LOWER(CONCAT_WS(',', short_links.name, short_links.destination_type, page.page_name, product_groups.name, products.name, short_links.url, short_links.tracking_code, last_modified_user.user_username)) LIKE '%" . escape(escape_like(mb_strtolower(($_SESSION['software']['editor_select_page_or_file']['query'] ?? '')))) . "%')";
        }

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

            // If this user has edit access to this short link,
            // and this short link is within the scope of the selected folder,
            // then continue to determine if this short link should be included in results.
            if (
                (
                    (USER_ROLE < 3)
                    ||
                    (
                        ($short_link['destination_type'] != 'url')
                        && (check_folder_access_in_array($short_link['folder_id'], $folders_that_user_has_access_to) == true)
                    )
                    ||
                    (
                        ($short_link['destination_type'] == 'url')
                        && (USER_USERNAME == $short_link['created_username'])
                    )
                )
                &&
                (
                    (($_SESSION['software']['editor_select_page_or_file']['folder_id'] ?? '') == 'all')
                    ||
                    (
                        ($short_link['destination_type'] != 'url')
                        && (in_array($short_link['folder_id'], $folders) == true)
                    )
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
                    'destination_type' => $short_link['destination_type'],
                    'destination_url' => $destination_url,
                    'folder_id' => $folder_id,
                    'folder_name' => $folder_name,
                    'access_control_type' => $short_link['access_control_type'],
                    'last_modified_timestamp' => $short_link['last_modified_timestamp'],
                    'last_modified_username' => $short_link['last_modified_username']);

                // Store the appropriate value in the sort array.
                switch ($sort_column) {
                    case 'url':
                        $items_for_sorting[] = $url;
                        break;

                    case 'destination_url':
                        $items_for_sorting[] = $destination_url;
                        break;

                    case 'folder_name':
                        $items_for_sorting[] = $folder_name;
                        break;

                    case 'last_modified_timestamp':
                        $items_for_sorting[] = $short_link['last_modified_timestamp'];
                        break;
                }
            }
        }

        break;
}

$output_rows = '';

// if there is at least one result to display
if ($items) {
    // If the order is ascending, then sort like that.
    if (($_SESSION['software']['editor_select_page_or_file']['order'] ?? '') == 'asc') {
        array_multisort($items_for_sorting, SORT_ASC, $items);

    // Otherwise the order is descending, so sort like that.
    } else {
        array_multisort($items_for_sorting, SORT_DESC, $items);
    }

    foreach ($items as $item) {
        $output_last_modified_username = '';
        
        if ($item['last_modified_username'] != '') {
            $output_last_modified_username = ' ' . lang(array('string'=>'by {var:1}','vars'=>array( h($item['last_modified_username']) ) ) );
        }

        // Prepare the rows of items differently based on the type.
        switch (($_SESSION['software']['editor_select_page_or_file']['type'] ?? '')) {
            default:
            case 'page':
                // If we did not get the access control type already up above for this page, then get it now.
                if ($item['access_control_type'] == '') {
                    $item['access_control_type'] = get_access_control_type($item['folder_id']);
                }

                $output_rows .=
                    '<tr class="pointer ' . h($item['access_control_type']) . '" onclick="window.opener.CKEDITOR.tools.callFunction(\'' . h(escape_javascript(($_GET['CKEditorFuncNum'] ?? ''))) . '\', \'' . h(escape_javascript($item['url'])) . '\'); window.close();">
                        <td class="align-middle chart_label">' . h($item['url']) . '</td>
                        <td class="align-middle">' . h($item['folder_name']) . '</td>
                        <td class="align-middle">' . h(get_page_type_name($item['page_type'])) . '</td>
                        <td class="align-middle">' . get_relative_time(array('timestamp' => $item['last_modified_timestamp'])) . $output_last_modified_username . '</td>
                        <td></td>
                    </tr>';

                break;

            case 'file':
                // If we did not get the access control type already up above for this file, then get it now.
                if ($item['access_control_type'] == '') {
                    $item['access_control_type'] = get_access_control_type($item['folder_id']);
                }

                $output_thumbnail = '';

                // If this item is an image, then output thumbnail.
                if (
                    ($item['file_type'] == 'bmp')
                    || ($item['file_type'] == 'gif')
                    || ($item['file_type'] == 'jpg')
                    || ($item['file_type'] == 'jpeg')
                    || ($item['file_type'] == 'png')
                    || ($item['file_type'] == 'tif')
                    || ($item['file_type'] == 'tiff')
                    || ($item['file_type'] == 'svg')
                    || ($item['file_type'] == 'webp')
                ) {
                    // Get the dimensions of the image.
                    $image_size = @getimagesize(FILE_DIRECTORY_PATH . '/' . $item['name']);
                    $image_width = $image_size[0];
                    $image_height = $image_size[1];

                    // Set the maximum dimension size for the image.
                    $max_dimension = 75;

                    // Call function to resize image.
                    $thumbnail_dimensions = get_thumbnail_dimensions($image_width, $image_height, $max_dimension);
                    
                    if(!$item['name']){// Output emptythumnail.
                        $output_thumbnail ='<svg class="bd-placeholder-img img-thumbnail" width="50" height="50" xmlns="http://www.w3.org/2000/svg" role="img" ><rect width="100%" height="100%" fill="#868e96"></rect><text x="10%" y="50%" style="font-size: 8px;" fill="#dee2e6" dy=".3em">' . lang(array('string'=>'No Image') ) . '</text></svg>';
                    }else{// Output thumnail.
                        $output_thumbnail ='<img style="width: 50px;height:50px;" class="img-fluid img-thumbnail lazy" src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/images/loading.gif" data-src="' .  PATH  . h(encode_url_path($item['name'])) . '" />';
                    }
                }else{
                    //if there is no thumnail
                    $output_thumbnail ='<svg class="bd-placeholder-img img-thumbnail" width="50" height="50" xmlns="http://www.w3.org/2000/svg" role="img" ><rect width="100%" height="100%" fill="#868e96"></rect><text x="10%" y="50%" style="font-size: 8px;" fill="#dee2e6" dy=".3em">' . lang(array('string'=>'No Image') ) . '</text></svg>';
                }

                $output_rows .=
                    '<tr class="pointer ' . h($item['access_control_type']) . '" onclick="window.opener.CKEDITOR.tools.callFunction(\'' . h(escape_javascript(($_GET['CKEditorFuncNum'] ?? ''))) . '\', \'' . h(escape_javascript($item['url'])) . '\'); window.close();">
                        <td class="align-middle text-center" style="min-width:50px;height:50px;">' . $output_thumbnail . '</td>
                        <td class="align-middle chart_label">' . h($item['url']) . '</td>
                        <td class="align-middle">' . h($item['folder_name']) . '</td>
                        <td class="align-middle">' . h(convert_bytes_to_string($item['size'])) . '</td>
                        <td class="align-middle">' . get_relative_time(array('timestamp' => $item['last_modified_timestamp'])) . $output_last_modified_username . '</td>
                        <td></td>
                    </tr>';

                break;

            case 'short_link':
                $output_access_control_type_class = '';

                // If the destination type is not url, then output access control type.
                if ($item['destination_type'] != 'url') {
                    // If we did not get the access control type already up above for this short link, then get it now.
                    if ($item['access_control_type'] == '') {
                        $item['access_control_type'] = get_access_control_type($item['folder_id']);
                    }

                    $output_access_control_type_class = ' ' . h($item['access_control_type']);
                }

                $output_rows .=
                    '<tr class="pointer' . $output_access_control_type_class . '" onclick="window.opener.CKEDITOR.tools.callFunction(\'' . h(escape_javascript(($_GET['CKEditorFuncNum'] ?? ''))) . '\', \'' . h(escape_javascript($item['url'])) . '\'); window.close();">
                        <td class="align-middle chart_label">' . h($item['url']) . '</td>
                        <td class="align-middle">' . h($item['destination_url']) . '</td>
                        <td class="align-middle">' . h($item['folder_name']) . '</td>
                        <td class="align-middle">' . get_relative_time(array('timestamp' => $item['last_modified_timestamp'])) . $output_last_modified_username . '</td>
                        <td></td>
                    </tr>';

                break;
        }
    }
}

echo 
output_header_secure(array('title'=>lang('Browse Items'),'icon'=>'folder')) . '
<nav id="header" class="navbar sticky-top rounded-0 navbar-expand border-bottom shadow-sm bg-body">
    <div class="container-fluid">
        <span 
            class="navbar-text me-auto" 
            data-bs-content="' . lang('Select the Page, File, or Short Link that you want to link to.') . '" 
            title="' . lang('Browse Items') . '">
            ' . lang('Browse Items') . '
        </span>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item dropdown no-popover"  title="' . lang('Software Theme') . '">
                <button class="nav-link nav-link-sm position-relative dropdown-toggle dropdown-menu-right d-none" data-bs-toggle="dropdown" id="bd-theme" type="button"><span class="bi bi-circle-half"></span></button>
                <ul aria-labelledby="bd-theme" class="dropdown-menu shadow dropdown-menu-end p-1 bg-body backdrop mt-nav-link-sm border-dropdown-menu" data-bs-popper="static" style="--bs-dropdown-min-width: 8rem;">
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="light" type="button"><i class="bi bi-sun-fill m-2"></i>' . lang('Light') . '</button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center active" data-bs-theme-value="dark" type="button"><i class="bi bi-moon-stars-fill m-2"></i>' . lang('Dark') . '</button></li>
                    <li><button class="dropdown-item dropdown-item-sm rounded p-0 my-1 d-flex align-items-center" data-bs-theme-value="auto" type="button"><i class="bi bi-circle-half m-2"></i>' . lang('Auto') . '</button></li>
                </ul>
            </li>
            <li class="nav-item">
                <button title="' . lang('Close') . '" type="button" class="nav-link nav-link-sm position-relative no-popover" onclick="window.close()" aria-label="Close">
                    <span class="bi bi-x-lg"></span>
                </button>
            </li>
        </ul>
    </div>
</nav>
<main class="container">
    <div class="row mb-2 flex-wrap">
        <div class="col-12 col-sm-12 col-md-6 col-xl-9 text-center text-md-start">
            <nav id="button_bar" class="navigation " aria-label="Button Bar">
                <form id="export_form" class="disable_shortcut d-inline-block" method="get">
                    <div class=" btn-group btn-group-sm flex-wrap">

                        <a class="btn btn-outline-secondary my-2' . $output_type_page_class . '" href="editor_select_page_or_file.php?type=page&CKEditorFuncNum=' . h(urlencode(($_GET['CKEditorFuncNum'] ?? ''))) . '"><span class="material-icons me-1">desktop_windows</span>' . lang('Pages') . '</a>
                        <a class="btn btn-outline-secondary my-2' . $output_type_file_class . '" href="editor_select_page_or_file.php?type=file&CKEditorFuncNum=' . h(urlencode(($_GET['CKEditorFuncNum'] ?? ''))) . '"><span class="material-icons me-1">insert_drive_file</span>' . lang('Files') . '</a>
                        <a class="btn btn-outline-secondary my-2' . $output_type_short_link_class . '" href="editor_select_page_or_file.php?type=short_link&CKEditorFuncNum=' . h(urlencode(($_GET['CKEditorFuncNum'] ?? ''))) . '"><span class="material-icons me-1">link</span>' . lang('Short Links') . '</a>
                    </div>
                </form>
            </nav>
        </div>
        <div class="col-12 col-sm-12 col-md-6 col-xl-3 ">
            <div class="row justify-content-center justify-content-md-end">
                <form id="search" action="editor_select_page_or_file.php" method="get" class="search_form col-auto">
                    <input type="hidden" name="CKEditorFuncNum" value="' . h(($_GET['CKEditorFuncNum'] ?? '')) . '" />
                    <div class="input-group input-group-sm">
                        <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Folder') . '" for="filter_select">folder</label>
                        <select id="folder_id" name="folder_id" class="form-select mt-1 mb-1" title="' . lang('Folder') . '" onchange="submit_form(\'search\')"><option value="all">[' . lang('All') . ']</option>' . select_folder(($_SESSION['software']['editor_select_page_or_file']['folder_id'] ?? '')) . '</select>
                    </div>
                    <div class="input-group input-group-sm">
                        <label class="input-group-text mt-1 mb-1 material-icons" title="' . lang('Access') . '" for="access_control_type">verified_user</label>
                        <select id="access_control_type" name="access_control_type" class="form-select mt-1 mb-1 ' . h(($_SESSION['software']['editor_select_page_or_file']['access_control_type'] ?? '')) . '" title="' . lang('Access') . '" onchange="submit_form(\'search\')"><option value="all" class="all">[' . lang('All') . ']</option>' . select_access_control_type(($_SESSION['software']['editor_select_page_or_file']['access_control_type'] ?? ''), false) . '</select>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="card my-4">
        <div class="card-header chart-buttons justify-content-end d-flex flex-wrap"></div>
        <div class="card-body p-0 position-relative">
            <table class="chart table-hover table " style="width:100%;display:none">
                <thead>
                    <tr>
                        ' . $output_heading_cells . '
                    </tr>
                </thead>
                <tbody>
                    ' . $output_rows . '
                </tbody>
            </table>
        </div>
    </div>
</main>' . output_footer_secure() ;