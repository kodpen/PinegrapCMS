<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 7 - Recent Updates: the latest changes across content, files and products.
 *
 * Loaded and called by includes/dashboard/widgets.php; see that file for the
 * contract every widget follows.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_DASHBOARD_WIDGETS')) {
    exit;
}

function pg_dashboard_widget_7($request, $user)
{
    $output_rows = '';
    // initialize variable for storing the maximum number of items that should appear in the recent update area
    $maximum_number_of_items = 20;
    // initialize variable for storing the maximum number of items for special items (e.g. files, designer files, and products)
    $special_maximum_number_of_items = 5;
    // initialize array for storing items that might appear in the recent updates area
    $recent_update_items = array();

    // initialize array that will be used for sorting the items for the recent updates area
    $recent_update_item_timestamps = array();

    $folders_that_user_has_access_to = array();

    // if user is a basic user, then get folders that user has access to
    if ($user['role'] == 3) {
        $folders_that_user_has_access_to = get_folders_that_user_has_access_to($user['id']);
    }

    $pages = array();

    // get all pages sorted by last modified descending
    $query = "SELECT
            page.page_name as name,
            page.page_timestamp as timestamp,
            user.user_username as username,
            page.page_folder as folder_id,
            page.page_type
        FROM page
        LEFT JOIN user ON page.page_user = user.user_id
        ORDER BY page.page_timestamp DESC";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // loop through the result in order to prepare array of items
    while ($row = mysqli_fetch_assoc($result)) {
        $pages[] = $row;
    }

    // initialize variable to keep track of how many items have been added
    $count = 0;

    // loop through the items in order to determine which the user has access to
    foreach ($pages as $page) {
        // if user has access to item then add it to arrays
        if (check_folder_access_in_array($page['folder_id'], $folders_that_user_has_access_to) == true) {
            $page['type'] = 'page';
            $recent_update_items[] = $page;
            $recent_update_item_timestamps[] = $page['timestamp'];

            $count++;

            // if the maximum number of items has been added, then we are done, so break out of the loop
            if ($count == $maximum_number_of_items) {
                break;
            }
        }
    }

    $short_links = array();

    // Get all short links sorted by last modified descending
    $query = "SELECT
            short_links.id,
            short_links.name,
            short_links.destination_type,
            short_links.created_user_id,
            short_links.last_modified_timestamp AS timestamp,
            user.user_username AS username,
            page.page_folder AS folder_id
        FROM short_links
        LEFT JOIN user ON short_links.last_modified_user_id = user.user_id
        LEFT JOIN page ON short_links.page_id = page.page_id
        WHERE short_links.name <> ''
        ORDER BY short_links.last_modified_timestamp DESC";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');
    $short_links = mysqli_fetch_items($result);

    // initialize variable to keep track of how many items have been added
    $count = 0;

    // loop through the items in order to determine which the user has access to
    foreach ($short_links as $short_link) {
        // Short links are a manager-and-up area, so only roles 0-2 get them listed here.
        if (USER_ROLE < 3) {
            $short_link['type'] = 'short_link';
            $recent_update_items[] = $short_link;
            $recent_update_item_timestamps[] = $short_link['timestamp'];

            $count++;

            // if the maximum number of items has been added, then we are done, so break out of the loop
            if ($count == $maximum_number_of_items) {
                break;
            }
        }
    }

    $files = array();

    // get all files sorted by last modified descending
    $query = "SELECT
            files.id,
            files.name,
            files.timestamp,
            user.user_username as username,
            files.folder as folder_id
        FROM files
        LEFT JOIN user ON files.user = user.user_id
        WHERE files.design = '0'
        ORDER BY files.timestamp DESC";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // loop through the result in order to prepare array of items
    while ($row = mysqli_fetch_assoc($result)) {
        $files[] = $row;
    }

    // initialize variable to keep track of how many items have been added
    $count = 0;

    // loop through the items in order to determine which the user has access to
    foreach ($files as $file) {
        // if user has access to item then add it to arrays
        if (check_folder_access_in_array($file['folder_id'], $folders_that_user_has_access_to) == true) {
            $file['type'] = 'file';
            $recent_update_items[] = $file;
            $recent_update_item_timestamps[] = $file['timestamp'];

            $count++;

            // if the maximum number of items has been added, then we are done, so break out of the loop
            if ($count == $special_maximum_number_of_items) {
                break;
            }
        }
    }

    $folders = array();

    // get all folders sorted by last modified descending
    $query = "SELECT
            folder.folder_id as id,
            folder.folder_name as name,
            folder.folder_timestamp as timestamp,
            user.user_username as username
        FROM folder
        LEFT JOIN user ON folder.folder_user = user.user_id
        ORDER BY folder.folder_timestamp DESC";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // loop through the result in order to prepare array of items
    while ($row = mysqli_fetch_assoc($result)) {
        $folders[] = $row;
    }

    // initialize variable to keep track of how many items have been added
    $count = 0;

    // loop through the items in order to determine which the user has access to
    foreach ($folders as $folder) {
        // if user has access to item then add it to arrays
        if (check_folder_access_in_array($folder['id'], $folders_that_user_has_access_to) == true) {
            $folder['type'] = 'folder';
            $recent_update_items[] = $folder;
            $recent_update_item_timestamps[] = $folder['timestamp'];

            $count++;

            // if the maximum number of items has been added, then we are done, so break out of the loop
            if ($count == $maximum_number_of_items) {
                break;
            }
        }
    }

    // if calendars is enabled and the user has access to manage calendars, then get calendars and events
    if ((CALENDARS == true) && (($user['role'] < 3) || ($user['manage_calendars'] == true))) {
        $calendars = array();

        // get all calendars sorted by last modified descending
        $query = "SELECT
                calendars.id,
                calendars.name,
                calendars.last_modified_timestamp as timestamp,
                user.user_username as username
            FROM calendars
            LEFT JOIN user ON calendars.last_modified_user_id = user.user_id
            ORDER BY calendars.last_modified_timestamp DESC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $calendars[] = $row;
        }

        // initialize variable to keep track of how many items have been added
        $count = 0;

        // loop through the items in order to determine which the user has access to
        foreach ($calendars as $calendar) {
            // if user has access to item then add it to arrays
            if (validate_calendar_access($calendar['id']) == true) {
                $calendar['type'] = 'calendar';
                $recent_update_items[] = $calendar;
                $recent_update_item_timestamps[] = $calendar['timestamp'];

                $count++;

                // if the maximum number of items has been added, then we are done, so break out of the loop
                if ($count == $maximum_number_of_items) {
                    break;
                }
            }
        }

        $calendar_events = array();

        // get all calendar events sorted by last modified descending
        $query = "SELECT
                calendar_events.id,
                calendar_events.name,
                calendar_events.last_modified_timestamp as timestamp,
                user.user_username as username
            FROM calendar_events
            LEFT JOIN user ON calendar_events.last_modified_user_id = user.user_id
            ORDER BY calendar_events.last_modified_timestamp DESC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $calendar_events[] = $row;
        }

        // initialize variable to keep track of how many items have been added
        $count = 0;

        // loop through the items in order to determine which the user has access to
        foreach ($calendar_events as $calendar_event) {
            // if user has access to item then add it to arrays
            if (validate_calendar_event_access($calendar_event['id']) == true) {
                $calendar_event['type'] = 'calendar_event';
                $recent_update_items[] = $calendar_event;
                $recent_update_item_timestamps[] = $calendar_event['timestamp'];

                $count++;

                // if the maximum number of items has been added, then we are done, so break out of the loop
                if ($count == $maximum_number_of_items) {
                    break;
                }
            }
        }
    }

    // if e-commerce is enabled and the user has access to manage e-commerce, then get e-commerce items
    if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
        $products = array();

        // get all products sorted by last modified descending
        $query = "SELECT
                products.id,
                products.short_description as name,
                products.timestamp,
                user.user_username as username
            FROM products
            LEFT JOIN user ON products.user = user.user_id
            ORDER BY products.timestamp DESC
            LIMIT $special_maximum_number_of_items";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }

        // loop through the items in order to add them to arrays
        foreach ($products as $product) {
            $product['type'] = 'product';
            $recent_update_items[] = $product;
            $recent_update_item_timestamps[] = $product['timestamp'];
        }

        $product_groups = array();

        // get all product groups sorted by last modified descending
        $query = "SELECT
                product_groups.id,
                product_groups.name,
                product_groups.timestamp,
                user.user_username as username
            FROM product_groups
            LEFT JOIN user ON product_groups.user = user.user_id
            ORDER BY product_groups.timestamp DESC
            LIMIT $maximum_number_of_items";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $product_groups[] = $row;
        }

        // loop through the items in order to add them to arrays
        foreach ($product_groups as $product_group) {
            $product_group['type'] = 'product_group';
            $recent_update_items[] = $product_group;
            $recent_update_item_timestamps[] = $product_group['timestamp'];
        }

        $offers = array();

        // get all offers sorted by last modified descending
        $query = "SELECT
                offers.id,
                offers.code as name,
                offers.timestamp,
                user.user_username as username
            FROM offers
            LEFT JOIN user ON offers.user = user.user_id
            ORDER BY offers.timestamp DESC
            LIMIT $maximum_number_of_items";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $offers[] = $row;
        }

        // loop through the items in order to add them to arrays
        foreach ($offers as $offer) {
            $offer['type'] = 'offer';
            $recent_update_items[] = $offer;
            $recent_update_item_timestamps[] = $offer['timestamp'];
        }
    }

    // If ads are enabled, then get them.
    if (ADS === true) {
        $ads = array();

        // get all ads sorted by last modified descending
        $query = "SELECT
                ads.id,
                ads.name,
                ads.last_modified_timestamp as timestamp,
                user.user_username as username,
                ads.ad_region_id
            FROM ads
            LEFT JOIN user ON ads.last_modified_user_id = user.user_id
            ORDER BY ads.last_modified_timestamp DESC";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $ads[] = $row;
        }

        // initialize variable to keep track of how many items have been added
        $count = 0;

        // loop through the items in order to determine which the user has access to
        foreach ($ads as $ad) {
            // if user has access to item then add it to arrays
            if (($user['role'] < 3) || (in_array($ad['ad_region_id'], get_items_user_can_edit('ad_regions', $user['id'])) == true)) {
                $ad['type'] = 'ad';
                $recent_update_items[] = $ad;
                $recent_update_item_timestamps[] = $ad['timestamp'];

                $count++;

                // if the maximum number of items has been added, then we are done, so break out of the loop
                if ($count == $maximum_number_of_items) {
                    break;
                }
            }
        }
    }

    $menus = array();

    // get all menus sorted by last modified descending
    $query = "SELECT
            menus.id,
            menus.name,
            menus.last_modified_timestamp as timestamp,
            user.user_username as username
        FROM menus
        LEFT JOIN user ON menus.last_modified_user_id = user.user_id
        ORDER BY menus.last_modified_timestamp DESC";
    $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

    // loop through the result in order to prepare array of items
    while ($row = mysqli_fetch_assoc($result)) {
        $menus[] = $row;
    }

    // initialize variable to keep track of how many items have been added
    $count = 0;

    // loop through the items in order to determine which the user has access to
    foreach ($menus as $menu) {
        // if user has access to item then add it to arrays
        if (($user['role'] < 3) || (in_array($menu['id'], get_items_user_can_edit('menus', $user['id'])) == true)) {
            $menu['type'] = 'menu';
            $recent_update_items[] = $menu;
            $recent_update_item_timestamps[] = $menu['timestamp'];

            $count++;

            // if the maximum number of items has been added, then we are done, so break out of the loop
            if ($count == $maximum_number_of_items) {
                break;
            }
        }
    }

    // if the user has access to the design tab, then get design items
    if ($user['role'] < 2) {
        $styles = array();

        // get all styles sorted by last modified descending
        $query = "SELECT
                style.style_id as id,
                style.style_name as name,
                style.style_timestamp as timestamp,
                user.user_username as username,
                style.style_type
            FROM style
            LEFT JOIN user ON style.style_user = user.user_id
            ORDER BY style.style_timestamp DESC
            LIMIT $maximum_number_of_items";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $styles[] = $row;
        }

        // loop through the items in order to add them to arrays
        foreach ($styles as $style) {
            $style['type'] = 'style';
            $recent_update_items[] = $style;
            $recent_update_item_timestamps[] = $style['timestamp'];
        }

        $common_regions = array();

        // get all common regions sorted by last modified descending
        $query = "SELECT
                cregion.cregion_id as id,
                cregion.cregion_name as name,
                cregion.cregion_timestamp as timestamp,
                user.user_username as username
            FROM cregion
            LEFT JOIN user ON cregion.cregion_user = user.user_id
            WHERE cregion.cregion_designer_type = 'no'
            ORDER BY cregion.cregion_timestamp DESC
            LIMIT $maximum_number_of_items";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $common_regions[] = $row;
        }

        // loop through the items in order to add them to arrays
        foreach ($common_regions as $common_region) {
            $common_region['type'] = 'common_region';
            $recent_update_items[] = $common_region;
            $recent_update_item_timestamps[] = $common_region['timestamp'];
        }

        $designer_regions = array();

        // get all designer regions sorted by last modified descending
        $query = "SELECT
                cregion.cregion_id as id,
                cregion.cregion_name as name,
                cregion.cregion_timestamp as timestamp,
                user.user_username as username
            FROM cregion
            LEFT JOIN user ON cregion.cregion_user = user.user_id
            WHERE cregion.cregion_designer_type = 'yes'
            ORDER BY cregion.cregion_timestamp DESC
            LIMIT $maximum_number_of_items";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $designer_regions[] = $row;
        }

        // loop through the items in order to add them to arrays
        foreach ($designer_regions as $designer_region) {
            $designer_region['type'] = 'designer_region';
            $recent_update_items[] = $designer_region;
            $recent_update_item_timestamps[] = $designer_region['timestamp'];
        }

        // If ads are enabled, then get ad regions.
        if (ADS === true) {
            $ad_regions = array();

            // get all ad regions sorted by last modified descending
            $query = "SELECT
                    ad_regions.id,
                    ad_regions.name,
                    ad_regions.last_modified_timestamp as timestamp,
                    user.user_username as username
                FROM ad_regions
                LEFT JOIN user ON ad_regions.last_modified_user_id = user.user_id
                ORDER BY ad_regions.last_modified_timestamp DESC
                LIMIT $maximum_number_of_items";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // loop through the result in order to prepare array of items
            while ($row = mysqli_fetch_assoc($result)) {
                $ad_regions[] = $row;
            }

            // loop through the items in order to add them to arrays
            foreach ($ad_regions as $ad_region) {
                $ad_region['type'] = 'ad_region';
                $recent_update_items[] = $ad_region;
                $recent_update_item_timestamps[] = $ad_region['timestamp'];
            }
        }

        // if the user is an administrator and dynamic regions are enabled, then get dynamic regions
        if (($user['role'] == 0) && (defined('DYNAMIC_REGIONS') == true) && (DYNAMIC_REGIONS == true)) {
            $dynamic_regions = array();

            // get all dynamic regions sorted by last modified descending
            $query = "SELECT
                    dregion.dregion_id as id,
                    dregion.dregion_name as name,
                    dregion.dregion_timestamp as timestamp,
                    user.user_username as username
                FROM dregion
                LEFT JOIN user ON dregion.dregion_user = user.user_id
                ORDER BY dregion.dregion_timestamp DESC
                LIMIT $maximum_number_of_items";
            $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

            // loop through the result in order to prepare array of items
            while ($row = mysqli_fetch_assoc($result)) {
                $dynamic_regions[] = $row;
            }

            // loop through the items in order to add them to arrays
            foreach ($dynamic_regions as $dynamic_region) {
                $dynamic_region['type'] = 'dynamic_region';
                $recent_update_items[] = $dynamic_region;
                $recent_update_item_timestamps[] = $dynamic_region['timestamp'];
            }
        }

        $login_regions = array();

        // get all login regions sorted by last modified descending
        $query = "SELECT
                login_regions.id,
                login_regions.name,
                login_regions.last_modified_timestamp as timestamp,
                user.user_username as username
            FROM login_regions
            LEFT JOIN user ON login_regions.last_modified_user_id = user.user_id
            ORDER BY login_regions.last_modified_timestamp DESC
            LIMIT $maximum_number_of_items";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $login_regions[] = $row;
        }

        // loop through the items in order to add them to arrays
        foreach ($login_regions as $login_region) {
            $login_region['type'] = 'login_region';
            $recent_update_items[] = $login_region;
            $recent_update_item_timestamps[] = $login_region['timestamp'];
        }

        $themes = array();

        // get all themes sorted by last modified descending
        $query = "SELECT
                files.id,
                files.name,
                files.timestamp,
                user.user_username as username
            FROM files
            LEFT JOIN user ON files.user = user.user_id
            WHERE (files.type = 'css') AND (files.design = '1')
            ORDER BY files.timestamp DESC
            LIMIT $maximum_number_of_items";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $themes[] = $row;
        }

        // loop through the items in order to add them to arrays
        foreach ($themes as $theme) {
            $theme['type'] = 'theme';
            $recent_update_items[] = $theme;
            $recent_update_item_timestamps[] = $theme['timestamp'];
        }

        $design_files = array();

        // get all design files sorted by last modified descending
        // even though themes are considered design files, we are going to exclude this from this query because we don't want them appear twice (as both a theme and a design file)
        $query = "SELECT
                files.id,
                files.name,
                files.timestamp,
                user.user_username as username,
                files.folder as folder_id
            FROM files
            LEFT JOIN user ON files.user = user.user_id
            WHERE (files.design = '1') AND (files.type != 'css')
            ORDER BY files.timestamp DESC
            LIMIT $special_maximum_number_of_items";
        $result = mysqli_query(db::$con, $query) or output_error('Query failed.');

        // loop through the result in order to prepare array of items
        while ($row = mysqli_fetch_assoc($result)) {
            $design_files[] = $row;
        }

        // loop through the items in order to add them to arrays
        foreach ($design_files as $design_file) {
            $design_file['type'] = 'design_file';
            $recent_update_items[] = $design_file;
            $recent_update_item_timestamps[] = $design_file['timestamp'];
        }
    }

    // sort the recent update items by the timestamp descending
    array_multisort($recent_update_item_timestamps, SORT_DESC, $recent_update_items);

    // update array to only contain the maximum number of items
    $recent_update_items = array_slice($recent_update_items, 0, $maximum_number_of_items);

    if (!empty($recent_update_items)) {
        // loop through the recent update items, in order to output rows
        foreach ($recent_update_items as $recent_update_item) {
            $type_name = '';
            $output_link_url = '';

            // get type name and icon
            switch ($recent_update_item['type']) {
                case 'page':
                    $type_name = lang('Page');
                    $query_string_from = '';
                    switch ($recent_update_item['page_type']) {
                        case 'view order':
                        case 'custom form':
                        case 'custom form confirmation':
                        case 'calendar event view':
                        case 'catalog detail':
                        case 'shipping address and arrival':
                        case 'shipping method':
                        case 'logout':
                            $query_string_from = '?from=control_panel';
                            break;
                    }
                    $output_link_url = h(escape_javascript(PATH)) . h(escape_javascript(encode_url_path($recent_update_item['name']))) . $query_string_from;
                    $type_bi_icon = 'bi-window';
                    $icon_color = 'var(--pages-color)';
                    break;
                case 'short_link':
                    $type_name = lang('Short Link');
                    $output_link_url = 'edit_short_link.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-link-45deg';
                    $icon_color = 'var(--pages-color)';
                    break;
                case 'file':
                    $type_name = lang('File');
                    $output_link_url = 'edit_file.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-file-earmark';
                    $icon_color = 'var(--files-color)';
                    break;
                case 'folder':
                    $type_name = lang('Folder');
                    $output_link_url = 'edit_folder.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-folder';
                    $icon_color = 'var(--folders-color)';
                    break;
                case 'calendar':
                    $type_name = lang('Calendar');
                    $output_link_url = 'calendars.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-calendar3';
                    $icon_color = 'var(--calendars-color)';
                    break;
                case 'calendar_event':
                    $type_name = lang('Event');
                    $output_link_url = 'edit_calendar_event.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-calendar-check';
                    $icon_color = 'var(--calendars-color)';
                    break;
                case 'product':
                    $type_name = lang('Product');
                    $output_link_url = 'edit_product.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-box-seam';
                    $icon_color = 'var(--ecommerce-color)';
                    break;
                case 'product_group':
                    $type_name = lang('Product Group');
                    $output_link_url = 'edit_product_group.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-grid';
                    $icon_color = 'var(--ecommerce-color)';
                    break;
                case 'offer':
                    $type_name = lang('Offer');
                    $output_link_url = 'edit_offer.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-tag';
                    $icon_color = 'var(--ecommerce-color)';
                    break;
                case 'ad':
                    $type_name = lang('Ad');
                    $output_link_url = 'edit_ad.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-megaphone';
                    $icon_color = 'var(--ad-color)';
                    break;
                case 'menu':
                    $type_name = lang('Menu');
                    $output_link_url = 'view_menu_items.php?id=' . $recent_update_item['id'] . '&from=welcome&send_to=' . h(escape_javascript(urlencode(get_request_uri())));
                    $type_bi_icon = 'bi-list';
                    $icon_color = 'var(--design-color)';
                    break;
                case 'style':
                    $type_name = lang('Page Style');
                    $output_link_url = 'edit_' . $recent_update_item['style_type'] . '_style.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-palette';
                    $icon_color = 'var(--design-color)';
                    break;
                case 'common_region':
                    $type_name = lang('Common Region');
                    $output_link_url = 'edit_common_region.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-layout-text-sidebar';
                    $icon_color = 'var(--design-color)';
                    break;
                case 'designer_region':
                    $type_name = lang('Designer Region');
                    $output_link_url = 'edit_designer_region.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-columns';
                    $icon_color = 'var(--design-color)';
                    break;
                case 'ad_region':
                    $type_name = lang('Ad Region');
                    $output_link_url = 'edit_ad_region.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-badge-ad';
                    $icon_color = 'var(--design-color)';
                    break;
                case 'dynamic_region':
                    $type_name = lang('Dynamic Region');
                    $output_link_url = 'edit_dynamic_region.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-code-slash';
                    $icon_color = 'var(--design-color)';
                    break;
                case 'login_region':
                    $type_name = lang('Login Region');
                    $output_link_url = 'edit_login_region.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-person-badge';
                    $icon_color = 'var(--design-color)';
                    break;
                case 'theme':
                    $type_name = lang('Theme');
                    $output_link_url = 'edit_theme_file.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-brush';
                    $icon_color = 'var(--design-color)';
                    break;
                case 'design_file':
                    $type_name = lang('Design File');
                    $output_link_url = 'edit_design_file.php?id=' . $recent_update_item['id'];
                    $type_bi_icon = 'bi-file-code';
                    $icon_color = 'var(--design-color)';
                    break;
                default:
                    $type_bi_icon = 'bi-file-earmark';
                    $icon_color = 'var(--design-color)';
            }

            // The per-type colour the icon used to carry is gone:
            // the tile is the card's accent now. No information is
            // lost, because the glyph already differs per type.
            $output_rows .= pg_widget_row(array(
                'href'  => $output_link_url,
                'badge' => '<i class="bi ' . $type_bi_icon . '"></i>',
                'name'  => h($recent_update_item['name']),
                'aside' => get_relative_time(array('timestamp' => $recent_update_item['timestamp'])),
                'meta'  => h($type_name) . ($recent_update_item['username'] ? ' &middot; ' . h($recent_update_item['username']) : ''),
            ));
        }

    } else {
        $output_rows = pg_widget_empty('bi-clock-history', lang(array('string' => 'There is no {var:1} right now.', 'vars' => lang('Recent Update'))));
    }
    $output_data = '
        <div class="card-body p-0 overflow-x-hidden overflow-y-auto">
            <div class="pg-list">' . $output_rows . '</div>
        </div>';

    //return success json output
    $response = array(
        'status' => 'success',
        'message' => 'Action Success',
        'data' => $output_data,
    );
    return $response;
}
