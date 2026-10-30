<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Dashboard widget 7 - Activity: what happened on the site lately, in one feed
 * that can be filtered by kind.
 *
 * The feed took over six cards that each listed one kind of recent thing --
 * content changes (this id, formerly Recent Updates), submitted forms (13),
 * contacts (10), user accounts (11), the site log (17) and email campaigns
 * (19) -- and adds orders and comments beside them. Six cards of twenty rows
 * were six requests a minute and a third of the dashboard spent on lists that
 * answered one question; here they share one card, one request and one
 * scroll, and the filter on the left says how much of each happened today.
 *
 * Every kind keeps the gate and the scoping of the card it came from, so a
 * reader only ever sees the kinds, and the rows, they saw before.
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
    // Rows per kind. Every kind is fetched to the same depth so a filter
    // always has a full list behind it; the "all" view is the same rows
    // merged by time.
    $limit = 12;

    $kinds = pg_dashboard_activity_kinds($user, $limit);

    $all_rows = array();
    $output_filters = '';
    $output_more = '';
    $today_total = 0;
    $midnight = strtotime(date('Y-m-d'));

    foreach ($kinds as $key => $kind) {

        // A kind without a daily series -- content changes span a dozen
        // tables -- counts today from the rows it fetched, which is exact
        // whenever fewer than $limit changed today and a floor otherwise.
        if ($kind['series'] !== null) {
            $today = (int) end($kind['series']);
        } else {
            $today = 0;
            foreach ($kind['items'] as $item) {
                if ((int) $item['ts'] >= $midnight) {
                    $today++;
                }
            }
        }

        $today_total += $today;

        $output_filters .= pg_widget_filter(array(
            'key'   => $key,
            'label' => $kind['label'],
            'icon'  => $kind['icon'],
            'color' => $kind['color'],
            'count' => ($today > 0) ? pg_format_number($today, 0) : '',
            'title' => lang(array('string' => '{var:1} today', 'vars' => pg_format_number($today, 0))),
            'count_text' => lang(array('string' => '{var:1} today', 'vars' => pg_format_number($today, 0))),
            'bars'  => ($kind['series'] !== null) ? $kind['series'] : null,
        ));

        foreach ($kind['items'] as $item) {
            $item['kind'] = $key;
            $item['color'] = $kind['color'];
            $all_rows[] = $item;
        }

        if ($kind['more_href'] !== '') {
            $output_more .= '<a class="pg-filter-more d-none" data-kind="' . h($key) . '" href="' . h($kind['more_href']) . '">'
                . h($kind['more_label']) . ' <i class="bi bi-arrow-right-short"></i></a>';
        }
    }

    // Newest first across every kind. The id breaks ties so two rows written
    // in the same second keep their order between refreshes.
    usort($all_rows, function ($a, $b) {
        if ((int) $a['ts'] === (int) $b['ts']) {
            return strcmp((string) $a['sort'], (string) $b['sort']);
        }
        return ((int) $a['ts'] > (int) $b['ts']) ? -1 : 1;
    });

    $output_rows = '';

    foreach ($all_rows as $item) {
        $output_rows .= pg_widget_row(array(
            'href'  => $item['href'],
            'badge' => $item['badge'],
            'color' => $item['tile'] ?? $item['color'],
            'name'  => $item['name'],
            'aside' => get_relative_time(array('timestamp' => (int) $item['ts'])),
            'meta'  => $item['meta'],
            'data'  => array('kind' => $item['kind']),
        ));
    }

    $output_all = pg_widget_filter(array(
        'key'   => 'all',
        'label' => lang('All Activity'),
        'icon'  => 'bi-activity',
        'color' => '#8b5cf6',
        'count' => ($today_total > 0) ? pg_format_number($today_total, 0) : '',
        'title' => lang(array('string' => '{var:1} today', 'vars' => pg_format_number($today_total, 0))),
        'count_text' => lang(array('string' => '{var:1} today', 'vars' => pg_format_number($today_total, 0))),
    ));

    $output_data = '
    <div class="card-body p-0 pg-split pg-act" data-pg-filterable="activity" data-pg-default="all">
        <div class="pg-split-half pg-filters" role="group" aria-label="' . h(lang('Filter activity')) . '">
            ' . $output_all . $output_filters . '
        </div>
        <div class="pg-split-half pg-filter-scroll">
            <div class="pg-list">
                ' . $output_rows . '
                <div class="pg-filter-none d-none">' . pg_widget_empty('bi-clock-history', $all_rows ? lang('Nothing of this kind lately.') : lang('No activity recorded yet.')) . '</div>
                ' . $output_more . '
            </div>
        </div>
    </div>';

    return array(
        'status' => 'success',
        'message' => 'Action Success',
        'data' => $output_data,
    );
}

/**
 * The kinds this reader may see, in the order the filter lists them, each
 * with its rows already fetched.
 *
 * @param array $user
 * @param int   $limit rows per kind
 * @return array key => label, icon, color, series (eight daily counts or
 *               null), items, more_href, more_label
 */
function pg_dashboard_activity_kinds($user, $limit)
{
    $kinds = array();

    $kind = function ($label, $icon, $color, $items, $series, $more_href, $more_label) {
        return array(
            'label'      => $label,
            'icon'       => $icon,
            'color'      => $color,
            'items'      => $items,
            'series'     => $series,
            'more_href'  => $more_href,
            'more_label' => $more_label,
        );
    };

    // Content changes: everyone who reaches the dashboard has something they
    // last edited, so this kind has no gate of its own -- each table inside
    // it keeps the one it always had.
    $kinds['update'] = $kind(lang('Content'), 'bi-pencil-square', '#8b5cf6',
        pg_dashboard_activity_updates($user, $limit), null, '', '');

    if (($user['role'] < 3) || ($user['manage_forms'] == true)) {
        $kinds['form'] = $kind(lang('Forms'), 'bi-file-earmark-text', '#0ea5e9',
            pg_dashboard_activity_forms($user, $limit),
            pg_activity_daily('forms', 'forms.submitted_timestamp', " AND (forms.complete = '1')"),
            'view_submitted_forms.php', lang('All submitted forms'));
    }

    if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {
        $kinds['order'] = $kind(lang('Orders'), 'bi-cart4', '#059669',
            pg_dashboard_activity_orders($limit),
            pg_activity_daily('orders', 'orders.order_date', " AND (orders.status != 'incomplete')"),
            'view_orders.php', lang('All Orders'));
    }

    if (($user['role'] < 3) || ($user['manage_contacts'] == true)) {
        $kinds['contact'] = $kind(lang('Contacts'), 'bi-person-lines-fill', '#6366f1',
            pg_dashboard_activity_contacts($user, $limit),
            pg_activity_daily('contacts', 'contacts.timestamp'),
            'view_contacts.php', lang('All contacts'));
    }

    // Same reach as view_comments.php for staff. A contributor's queue is
    // scoped through the commented page's folder; the Comment Moderation
    // card does that, and the feed leaves those readers to it.
    if ($user['role'] < 3) {
        $kinds['comment'] = $kind(lang('Comments'), 'bi-chat-square-quote', '#f59e0b',
            pg_dashboard_activity_comments($limit),
            pg_activity_daily('comments', 'comments.created_timestamp'),
            'view_comments.php', lang('All comments'));
    }

    if ($user['role'] < 3) {

        // A manager sees the accounts below their own role, as the users
        // list does; an administrator sees all of them.
        $user_extra = ($user['role'] > 0) ? " AND (user.user_role > '" . (int) $user['role'] . "')" : '';

        $kinds['user'] = $kind(lang('Users'), 'bi-people', '#a855f7',
            pg_dashboard_activity_users($user, $limit),
            pg_activity_daily('user', 'user.user_timestamp', $user_extra),
            'view_users.php', lang('All users'));

        $kinds['campaign'] = $kind(lang('Campaigns'), 'bi-megaphone', '#e11d48',
            pg_dashboard_activity_campaigns($limit),
            pg_activity_daily('email_campaigns', 'email_campaigns.created_timestamp'),
            'view_email_campaigns.php', lang('All campaigns'));

        $kinds['log'] = $kind(lang('Site Logs'), 'bi-journal-text', '#64748b',
            pg_dashboard_activity_logs($limit),
            pg_activity_daily('log', 'log.log_timestamp'),
            'view_log.php', lang('All log entries'));
    }

    return $kinds;
}

// Content changes across the site: pages, short links, files, folders,
// calendars, the shop, menus and the design layer. Each table is read newest
// first and bounded, and the access test each one always had is applied to
// the rows it returns.
//
// The bound is the change from what this card used to do, which was read
// pages, files, folders, short links, calendars, events, ads and menus whole
// -- every row of every one of them, once a minute, to keep twenty. Staff see
// every row of a table, so $limit rows are enough; a contributor's rows are
// filtered by folder after the read, so their tables are read deeper, to a
// fixed ceiling, before the filter.
function pg_dashboard_activity_updates($user, $limit)
{
    $items = array();
    $basic = ($user['role'] == 3);
    $scan = $basic ? 200 : $limit;
    $few = 5;

    $folders_that_user_has_access_to = $basic ? get_folders_that_user_has_access_to($user['id']) : array();

    $add = function ($rows, $type, $cap, $test = null) use (&$items) {
        $count = 0;
        foreach ($rows as $row) {
            if (($test !== null) && !$test($row)) {
                continue;
            }
            $row['type'] = $type;
            $items[] = $row;
            if (++$count >= $cap) {
                break;
            }
        }
    };

    $in_folder = function ($row) use ($folders_that_user_has_access_to) {
        return check_folder_access_in_array($row['folder_id'], $folders_that_user_has_access_to);
    };

    $add(db_items("SELECT
            page.page_id AS id,
            page.page_name AS name,
            page.page_timestamp AS timestamp,
            user.user_username AS username,
            page.page_folder AS folder_id,
            page.page_type
        FROM page
        LEFT JOIN user ON page.page_user = user.user_id
        ORDER BY page.page_timestamp DESC
        LIMIT " . (int) $scan), 'page', $limit, $in_folder);

    // Short links are a manager-and-up area.
    if ($user['role'] < 3) {
        $add(db_items("SELECT
                short_links.id,
                short_links.name,
                short_links.last_modified_timestamp AS timestamp,
                user.user_username AS username
            FROM short_links
            LEFT JOIN user ON short_links.last_modified_user_id = user.user_id
            WHERE short_links.name <> ''
            ORDER BY short_links.last_modified_timestamp DESC
            LIMIT " . (int) $limit), 'short_link', $limit);
    }

    $add(db_items("SELECT
            files.id,
            files.name,
            files.timestamp,
            user.user_username AS username,
            files.folder AS folder_id
        FROM files
        LEFT JOIN user ON files.user = user.user_id
        WHERE files.design = '0'
        ORDER BY files.timestamp DESC
        LIMIT " . (int) $scan), 'file', $few, $in_folder);

    $add(db_items("SELECT
            folder.folder_id AS id,
            folder.folder_id AS folder_id,
            folder.folder_name AS name,
            folder.folder_timestamp AS timestamp,
            user.user_username AS username
        FROM folder
        LEFT JOIN user ON folder.folder_user = user.user_id
        ORDER BY folder.folder_timestamp DESC
        LIMIT " . (int) $scan), 'folder', $limit, $in_folder);

    if ((CALENDARS == true) && (($user['role'] < 3) || ($user['manage_calendars'] == true))) {

        $add(db_items("SELECT
                calendars.id,
                calendars.name,
                calendars.last_modified_timestamp AS timestamp,
                user.user_username AS username
            FROM calendars
            LEFT JOIN user ON calendars.last_modified_user_id = user.user_id
            ORDER BY calendars.last_modified_timestamp DESC
            LIMIT 50"), 'calendar', $limit, function ($row) {
                return validate_calendar_access($row['id']) == true;
            });

        $add(db_items("SELECT
                calendar_events.id,
                calendar_events.name,
                calendar_events.last_modified_timestamp AS timestamp,
                user.user_username AS username
            FROM calendar_events
            LEFT JOIN user ON calendar_events.last_modified_user_id = user.user_id
            ORDER BY calendar_events.last_modified_timestamp DESC
            LIMIT 50"), 'calendar_event', $limit, function ($row) {
                return validate_calendar_event_access($row['id']) == true;
            });
    }

    if ((ECOMMERCE == true) && (($user['role'] < 3) || ($user['manage_ecommerce'] == true))) {

        $add(db_items("SELECT
                products.id,
                products.short_description AS name,
                products.timestamp,
                user.user_username AS username
            FROM products
            LEFT JOIN user ON products.user = user.user_id
            ORDER BY products.timestamp DESC
            LIMIT " . (int) $few), 'product', $few);

        $add(db_items("SELECT
                product_groups.id,
                product_groups.name,
                product_groups.timestamp,
                user.user_username AS username
            FROM product_groups
            LEFT JOIN user ON product_groups.user = user.user_id
            ORDER BY product_groups.timestamp DESC
            LIMIT " . (int) $limit), 'product_group', $limit);

        $add(db_items("SELECT
                offers.id,
                offers.code AS name,
                offers.timestamp,
                user.user_username AS username
            FROM offers
            LEFT JOIN user ON offers.user = user.user_id
            ORDER BY offers.timestamp DESC
            LIMIT " . (int) $limit), 'offer', $limit);
    }

    if (ADS === true) {

        $ad_regions_user_can_edit = ($user['role'] < 3) ? array() : get_items_user_can_edit('ad_regions', $user['id']);

        $add(db_items("SELECT
                ads.id,
                ads.name,
                ads.last_modified_timestamp AS timestamp,
                user.user_username AS username,
                ads.ad_region_id
            FROM ads
            LEFT JOIN user ON ads.last_modified_user_id = user.user_id
            ORDER BY ads.last_modified_timestamp DESC
            LIMIT 50"), 'ad', $limit, function ($row) use ($user, $ad_regions_user_can_edit) {
                return ($user['role'] < 3) || in_array($row['ad_region_id'], $ad_regions_user_can_edit);
            });
    }

    $menus_user_can_edit = ($user['role'] < 3) ? array() : get_items_user_can_edit('menus', $user['id']);

    $add(db_items("SELECT
            menus.id,
            menus.name,
            menus.last_modified_timestamp AS timestamp,
            user.user_username AS username
        FROM menus
        LEFT JOIN user ON menus.last_modified_user_id = user.user_id
        ORDER BY menus.last_modified_timestamp DESC
        LIMIT 50"), 'menu', $limit, function ($row) use ($user, $menus_user_can_edit) {
            return ($user['role'] < 3) || in_array($row['id'], $menus_user_can_edit);
        });

    // The design layer: designers and administrators.
    if ($user['role'] < 2) {

        $add(db_items("SELECT
                style.style_id AS id,
                style.style_name AS name,
                style.style_timestamp AS timestamp,
                user.user_username AS username,
                style.style_type
            FROM style
            LEFT JOIN user ON style.style_user = user.user_id
            ORDER BY style.style_timestamp DESC
            LIMIT " . (int) $limit), 'style', $limit);

        $add(db_items("SELECT
                cregion.cregion_id AS id,
                cregion.cregion_name AS name,
                cregion.cregion_timestamp AS timestamp,
                user.user_username AS username,
                cregion.cregion_designer_type
            FROM cregion
            LEFT JOIN user ON cregion.cregion_user = user.user_id
            ORDER BY cregion.cregion_timestamp DESC
            LIMIT " . (int) ($limit * 2)), 'cregion', $limit * 2);

        if (ADS === true) {
            $add(db_items("SELECT
                    ad_regions.id,
                    ad_regions.name,
                    ad_regions.last_modified_timestamp AS timestamp,
                    user.user_username AS username
                FROM ad_regions
                LEFT JOIN user ON ad_regions.last_modified_user_id = user.user_id
                ORDER BY ad_regions.last_modified_timestamp DESC
                LIMIT " . (int) $limit), 'ad_region', $limit);
        }

        if (($user['role'] == 0) && (defined('DYNAMIC_REGIONS') == true) && (DYNAMIC_REGIONS == true)) {
            $add(db_items("SELECT
                    dregion.dregion_id AS id,
                    dregion.dregion_name AS name,
                    dregion.dregion_timestamp AS timestamp,
                    user.user_username AS username
                FROM dregion
                LEFT JOIN user ON dregion.dregion_user = user.user_id
                ORDER BY dregion.dregion_timestamp DESC
                LIMIT " . (int) $limit), 'dynamic_region', $limit);
        }

        $add(db_items("SELECT
                login_regions.id,
                login_regions.name,
                login_regions.last_modified_timestamp AS timestamp,
                user.user_username AS username
            FROM login_regions
            LEFT JOIN user ON login_regions.last_modified_user_id = user.user_id
            ORDER BY login_regions.last_modified_timestamp DESC
            LIMIT " . (int) $limit), 'login_region', $limit);

        $add(db_items("SELECT
                files.id,
                files.name,
                files.timestamp,
                user.user_username AS username,
                files.type AS file_type
            FROM files
            LEFT JOIN user ON files.user = user.user_id
            WHERE files.design = '1'
            ORDER BY files.timestamp DESC
            LIMIT " . (int) ($limit + $few)), 'design', $limit + $few);
    }

    // Newest first, cut to the kind's depth, then dressed.
    usort($items, function ($a, $b) {
        return ((int) $b['timestamp']) - ((int) $a['timestamp']);
    });

    $out = array();

    foreach (array_slice($items, 0, $limit) as $item) {
        $out[] = pg_dashboard_activity_update_row($item);
    }

    return $out;
}

// One content change as a feed row: where it goes, what it is.
function pg_dashboard_activity_update_row($item)
{
    $type = $item['type'];
    $id = (int) $item['id'];
    $href = '';
    $icon = 'bi-file-earmark';
    $type_name = '';

    // The two kinds of common region and the two kinds of design file share
    // a table; the row says which one it is.
    if ($type === 'cregion') {
        $type = ($item['cregion_designer_type'] === 'yes') ? 'designer_region' : 'common_region';
    } elseif ($type === 'design') {
        $type = ($item['file_type'] === 'css') ? 'theme' : 'design_file';
    }

    switch ($type) {
        case 'page':
            $type_name = lang('Page');
            $query_string_from = '';
            switch ($item['page_type']) {
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
            $href = h(PATH) . h(encode_url_path($item['name'])) . $query_string_from;
            $icon = 'bi-window';
            break;
        case 'short_link':
            $type_name = lang('Short Link');
            $href = 'edit_short_link.php?id=' . $id;
            $icon = 'bi-link-45deg';
            break;
        case 'file':
            $type_name = lang('File');
            $href = 'edit_file.php?id=' . $id;
            $icon = 'bi-file-earmark';
            break;
        case 'folder':
            $type_name = lang('Folder');
            $href = 'edit_folder.php?id=' . $id;
            $icon = 'bi-folder';
            break;
        case 'calendar':
            $type_name = lang('Calendar');
            $href = 'calendars.php?id=' . $id;
            $icon = 'bi-calendar3';
            break;
        case 'calendar_event':
            $type_name = lang('Event');
            $href = 'edit_calendar_event.php?id=' . $id;
            $icon = 'bi-calendar-check';
            break;
        case 'product':
            $type_name = lang('Product');
            $href = 'edit_product.php?id=' . $id;
            $icon = 'bi-box-seam';
            break;
        case 'product_group':
            $type_name = lang('Product Group');
            $href = 'edit_product_group.php?id=' . $id;
            $icon = 'bi-grid';
            break;
        case 'offer':
            $type_name = lang('Offer');
            $href = 'edit_offer.php?id=' . $id;
            $icon = 'bi-tag';
            break;
        case 'ad':
            $type_name = lang('Ad');
            $href = 'edit_ad.php?id=' . $id;
            $icon = 'bi-megaphone';
            break;
        case 'menu':
            $type_name = lang('Menu');
            $href = 'view_menu_items.php?id=' . $id . '&amp;from=welcome&amp;send_to=' . h(urlencode(PATH . SOFTWARE_DIRECTORY . '/welcome.php'));
            $icon = 'bi-list';
            break;
        case 'style':
            $type_name = lang('Page Style');
            $href = 'edit_' . h($item['style_type']) . '_style.php?id=' . $id;
            $icon = 'bi-palette';
            break;
        case 'common_region':
            $type_name = lang('Common Region');
            $href = 'edit_common_region.php?id=' . $id;
            $icon = 'bi-layout-text-sidebar';
            break;
        case 'designer_region':
            $type_name = lang('Designer Region');
            $href = 'edit_designer_region.php?id=' . $id;
            $icon = 'bi-columns';
            break;
        case 'ad_region':
            $type_name = lang('Ad Region');
            $href = 'edit_ad_region.php?id=' . $id;
            $icon = 'bi-badge-ad';
            break;
        case 'dynamic_region':
            $type_name = lang('Dynamic Region');
            $href = 'edit_dynamic_region.php?id=' . $id;
            $icon = 'bi-code-slash';
            break;
        case 'login_region':
            $type_name = lang('Login Region');
            $href = 'edit_login_region.php?id=' . $id;
            $icon = 'bi-person-badge';
            break;
        case 'theme':
            $type_name = lang('Theme');
            $href = 'edit_theme_file.php?id=' . $id;
            $icon = 'bi-brush';
            break;
        case 'design_file':
            $type_name = lang('Design File');
            $href = 'edit_design_file.php?id=' . $id;
            $icon = 'bi-file-code';
            break;
    }

    return array(
        'ts'    => (int) $item['timestamp'],
        'sort'  => $type . ':' . $id,
        'href'  => $href,
        'badge' => '<i class="bi ' . $icon . '"></i>',
        'name'  => h($item['name']),
        'meta'  => h($type_name) . (!empty($item['username']) ? ' &middot; ' . h($item['username']) : ''),
    );
}

// Submitted forms, newest submission first. Staff see every form; a
// contributor with the forms permission sees the forms on pages in folders
// they may edit, which is what the submitted forms screen shows them too.
function pg_dashboard_activity_forms($user, $limit)
{
    $where = "(forms.complete = '1')";

    if ($user['role'] == 3) {

        $folders = get_folders_that_user_has_access_to($user['id']);
        $page_ids = array();

        foreach (db_items("SELECT page_id, page_folder AS folder_id FROM page WHERE " . pg_form_page_sql('page')) as $row) {
            if (check_folder_access_in_array($row['folder_id'], $folders)) {
                $page_ids[] = (int) $row['page_id'];
            }
        }

        if (empty($page_ids)) {
            return array();
        }

        $where .= " AND (forms.page_id IN (" . implode(',', $page_ids) . "))";
    }

    $items = array();

    foreach (db_items("SELECT
            forms.id,
            forms.reference_code,
            forms.submitted_timestamp,
            custom_form_pages.form_name,
            user.user_username AS username,
            contacts.first_name,
            contacts.last_name
        FROM forms
        LEFT JOIN custom_form_pages ON forms.page_id = custom_form_pages.page_id
        LEFT JOIN user ON forms.user_id = user.user_id
        LEFT JOIN contacts ON forms.contact_id = contacts.id
        WHERE " . $where . "
        ORDER BY forms.submitted_timestamp DESC
        LIMIT " . (int) $limit) as $row) {

        $who = trim(((string) $row['first_name']) . ' ' . ((string) $row['last_name']));

        if ($who === '') {
            $who = ((string) $row['username'] !== '') ? (string) $row['username'] : '[' . lang('Unknown') . ']';
        }

        $items[] = array(
            'ts'    => (int) $row['submitted_timestamp'],
            'sort'  => 'form:' . (int) $row['id'],
            'href'  => 'edit_submitted_form.php?id=' . (int) $row['id'],
            'badge' => '<i class="bi bi-file-earmark-text"></i>',
            'name'  => h($who),
            'meta'  => h($row['form_name']) . (((string) $row['reference_code'] !== '') ? ' &middot; ' . h($row['reference_code']) : ''),
        );
    }

    return $items;
}

// Orders as they come in. The same rows the Orders card lists -- everything
// but the baskets left behind -- with a cancelled order taking the danger
// colour, as it does there.
function pg_dashboard_activity_orders($limit)
{
    $items = array();

    foreach (db_items("SELECT
            orders.id,
            orders.order_number,
            orders.status,
            orders.total,
            orders.order_date,
            orders.billing_first_name,
            orders.billing_last_name
        FROM orders
        WHERE orders.status != 'incomplete'
        ORDER BY orders.order_date DESC
        LIMIT " . (int) $limit) as $row) {

        $who = trim(((string) $row['billing_first_name']) . ' ' . ((string) $row['billing_last_name']));
        $cancelled = ($row['status'] === 'cancelled');

        $items[] = array(
            'ts'    => (int) $row['order_date'],
            'sort'  => 'order:' . (int) $row['id'],
            'href'  => 'view_order.php?id=' . (int) $row['id'],
            'badge' => '<i class="bi ' . ($cancelled ? 'bi-x-circle' : 'bi-bag-check') . '"></i>',
            'tile'  => $cancelled ? '#dc3545' : null,
            'name'  => h(($who !== '') ? $who : '[' . lang('Visitor') . ']'),
            'meta'  => '#' . h($row['order_number']) . ' &middot; ' . prepare_amount($row['total'] / 100)
                . ($cancelled ? ' &middot; <span class="fg-danger">' . lang('Canceled') . '</span>' : ''),
        );
    }

    return $items;
}

// Contacts as they are added. A contributor with the contacts permission sees
// the contact groups they may edit, as the contacts screen does.
function pg_dashboard_activity_contacts($user, $limit)
{
    if ($user['role'] < 3) {
        $rows = db_items("SELECT
                contacts.id,
                contacts.first_name,
                contacts.last_name,
                contacts.email_address,
                contacts.timestamp,
                user.user_username AS username
            FROM contacts
            LEFT JOIN user ON contacts.user = user.user_id
            ORDER BY contacts.timestamp DESC
            LIMIT " . (int) $limit);
    } else {
        $groups = array_map('intval', get_items_user_can_edit('contact_groups', $user['id']));

        if (empty($groups)) {
            return array();
        }

        $rows = db_items("SELECT
                contacts.id,
                MAX(contacts.first_name) AS first_name,
                MAX(contacts.last_name) AS last_name,
                MAX(contacts.email_address) AS email_address,
                MAX(contacts.timestamp) AS timestamp,
                MAX(user.user_username) AS username
            FROM contacts
            LEFT JOIN user ON contacts.user = user.user_id
            INNER JOIN contacts_contact_groups_xref ON contacts.id = contacts_contact_groups_xref.contact_id
            WHERE contacts_contact_groups_xref.contact_group_id IN (" . implode(',', $groups) . ")
            GROUP BY contacts.id
            ORDER BY MAX(contacts.timestamp) DESC
            LIMIT " . (int) $limit);
    }

    $items = array();

    foreach ($rows as $row) {
        $name = trim(((string) $row['first_name']) . ' ' . ((string) $row['last_name']));

        if ($name === '') {
            $name = (string) $row['username'];
        }

        $initials = mb_strtoupper(mb_substr((string) $row['first_name'], 0, 1) . mb_substr((string) $row['last_name'], 0, 1));

        if ($initials === '') {
            $initials = mb_strtoupper(mb_substr((string) $row['username'], 0, 1));
        }

        $items[] = array(
            'ts'    => (int) $row['timestamp'],
            'sort'  => 'contact:' . (int) $row['id'],
            'href'  => 'edit_contact.php?id=' . (int) $row['id'],
            'badge' => h(($initials !== '') ? $initials : '?'),
            'name'  => h(($name !== '') ? $name : (string) $row['email_address']),
            'meta'  => h($row['email_address']),
        );
    }

    return $items;
}

// Comments as they are posted, published or not; the ones still waiting say
// so, because that is the one thing about a comment worth acting on.
function pg_dashboard_activity_comments($limit)
{
    $items = array();

    foreach (db_items("SELECT
            comments.id,
            comments.name,
            comments.message,
            comments.published,
            comments.created_timestamp,
            page.page_name
        FROM comments
        LEFT JOIN page ON comments.page_id = page.page_id
        ORDER BY comments.created_timestamp DESC
        LIMIT " . (int) $limit) as $row) {

        // The body is rich text from the comment form; a preview strips it
        // rather than rendering it inside a card.
        $preview = trim(preg_replace('/\s+/', ' ', strip_tags((string) $row['message'])));

        if (mb_strlen($preview) > 80) {
            $preview = mb_substr($preview, 0, 79) . '…';
        }

        $waiting = ((string) $row['published'] !== '1');

        $items[] = array(
            'ts'    => (int) $row['created_timestamp'],
            'sort'  => 'comment:' . (int) $row['id'],
            'href'  => 'edit_comment.php?id=' . (int) $row['id'] . '&amp;send_to=' . h(urlencode(PATH . SOFTWARE_DIRECTORY . '/welcome.php')),
            'badge' => '<i class="bi ' . ($waiting ? 'bi-hourglass-split' : 'bi-chat-square-quote') . '"></i>',
            'name'  => h(pg_comment_display_name($row['name'])),
            'meta'  => ($waiting ? '<span class="fg-emphasis-warning">' . lang('Awaiting approval') . '</span> &middot; ' : '')
                . h($preview !== '' ? $preview : (string) $row['page_name']),
        );
    }

    return $items;
}

// User accounts, newest change first. A manager sees the accounts below their
// own role, as the users list does.
function pg_dashboard_activity_users($user, $limit)
{
    $where = ($user['role'] > 0) ? "WHERE user.user_role > '" . (int) $user['role'] . "'" : '';
    $items = array();

    foreach (db_items("SELECT
            user.user_id AS id,
            user.user_username AS username,
            user.user_email AS email_address,
            user.user_timestamp AS timestamp
        FROM user
        " . $where . "
        ORDER BY user.user_timestamp DESC
        LIMIT " . (int) $limit) as $row) {

        $initial = mb_strtoupper(mb_substr((string) $row['username'], 0, 1));

        $items[] = array(
            'ts'    => (int) $row['timestamp'],
            'sort'  => 'user:' . (int) $row['id'],
            'href'  => 'edit_user.php?id=' . (int) $row['id'],
            'badge' => h(($initial !== '') ? $initial : '?'),
            'name'  => h($row['username']),
            'meta'  => h($row['email_address']),
        );
    }

    return $items;
}

// Email campaigns, newest change first, with how far each has got.
//
// Progress comes out of one grouped count over the recipients of all the
// listed campaigns. The card this replaced ran two counts per campaign --
// forty queries for twenty rows -- on every refresh.
function pg_dashboard_activity_campaigns($limit)
{
    $rows = db_items("SELECT
            email_campaigns.id,
            email_campaigns.subject,
            email_campaigns.status,
            email_campaigns.created_timestamp,
            email_campaigns.last_modified_timestamp
        FROM email_campaigns
        ORDER BY email_campaigns.last_modified_timestamp DESC
        LIMIT " . (int) $limit);

    if (empty($rows)) {
        return array();
    }

    $ids = array();

    foreach ($rows as $row) {
        $ids[] = (int) $row['id'];
    }

    $progress = array();

    foreach (db_items("SELECT
            email_campaign_id,
            COUNT(*) AS total,
            SUM(CASE WHEN complete = '1' THEN 1 ELSE 0 END) AS done
        FROM email_recipients
        WHERE email_campaign_id IN (" . implode(',', $ids) . ")
        GROUP BY email_campaign_id") as $count) {
        $progress[(int) $count['email_campaign_id']] = $count;
    }

    $status_colors = array(
        'complete' => '#10b981',
        'sending'  => '#3b82f6',
        'paused'   => '#f59e0b',
    );

    $items = array();

    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $total = isset($progress[$id]) ? (int) $progress[$id]['total'] : 0;
        $done = isset($progress[$id]) ? (int) $progress[$id]['done'] : 0;
        $percent = ($total > 0) ? (int) floor(($done / $total) * 100) : 100;
        $color = $status_colors[$row['status']] ?? '#a1a1a1';

        $items[] = array(
            'ts'    => (int) $row['last_modified_timestamp'],
            'sort'  => 'campaign:' . $id,
            'href'  => 'edit_email_campaign.php?id=' . $id . '&amp;send_to=' . h(urlencode(PATH . SOFTWARE_DIRECTORY . '/welcome.php')),
            'badge' => '<i class="bi bi-megaphone"></i>',
            'name'  => h($row['subject']),
            'meta'  => '<span style="color:' . $color . '">' . h(get_email_campaign_status_name($row['status'])) . '</span>'
                . ' &middot; ' . h(lang(array('string' => '{var:1}%', 'vars' => $percent)))
                . ' &middot; ' . h(lang(array(
                    'string' => '{var:1} recipient{suffix:1}',
                    'vars'   => pg_format_number($total, 0),
                    'suffix' => array(($total == 1) ? '' : 's')))),
        );
    }

    return $items;
}

// The site log, newest first. The row is the link, so a URL inside a message
// stays text: an anchor nested in an anchor is not valid markup, and browsers
// split the row apart to repair it.
function pg_dashboard_activity_logs($limit)
{
    $items = array();

    foreach (db_items("SELECT
            log_id,
            log_description,
            log_ip,
            log_user,
            log_timestamp
        FROM log
        ORDER BY log_timestamp DESC
        LIMIT " . (int) $limit) as $row) {

        $items[] = array(
            'ts'    => (int) $row['log_timestamp'],
            'sort'  => 'log:' . (int) $row['log_id'],
            'href'  => 'view_log.php',
            'badge' => '<i class="bi bi-journal-text"></i>',
            'name'  => h(pg_log_actor($row['log_user'], $row['log_ip'])),
            'meta'  => pg_dashboard_log_meta($row['log_description'], $row['log_ip']),
        );
    }

    return $items;
}
