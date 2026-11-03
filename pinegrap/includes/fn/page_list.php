<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pages list (view_pages.php), shared by the screen and the panel action
 * that pages it (page_list_rows, includes/panel/lists.php).
 *
 * Loaded where it is needed (require_once), not by functions.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The style each folder hands down to a page without a style of its own:
 * the folder's style for the device type, else its nearest parent's, as
 * get_style() walks the chain, but for all folders from one read. A root
 * folder (level 0) without a style, a missing parent or a loop ends at 0.
 *
 * @param array  $folders     rows: folder_id, folder_parent, folder_level,
 *                            folder_style, mobile_style_id
 * @param string $device_type 'desktop' or 'mobile'
 * @return array folder id => style id (0 for none)
 */
function pg_page_list_folder_styles($folders, $device_type)
{
    $column = ($device_type === 'mobile') ? 'mobile_style_id' : 'folder_style';
    $by_id = array();

    foreach ((array) $folders as $folder) {
        if (is_array($folder) && isset($folder['folder_id'])) {
            $by_id[(int) $folder['folder_id']] = $folder;
        }
    }

    $styles = array();

    foreach (array_keys($by_id) as $start) {
        $path = array();
        $id = $start;
        $style = 0;

        while (true) {
            if (isset($styles[$id])) {
                $style = $styles[$id];
                break;
            }

            if (!isset($by_id[$id]) || isset($path[$id])) {
                break;
            }

            $path[$id] = true;
            $folder = $by_id[$id];

            if ((int) ($folder[$column] ?? 0) > 0) {
                $style = (int) $folder[$column];
                break;
            }

            if ((int) ($folder['folder_level'] ?? 0) === 0) {
                break;
            }

            $id = (int) ($folder['folder_parent'] ?? 0);
        }

        foreach (array_keys($path) as $seen) {
            $styles[$seen] = $style;
        }
    }

    ksort($styles);

    return $styles;
}

/**
 * The views the filter menu offers: label (also the screen's heading),
 * description, condition, the folder access type it keeps to, and what
 * the custom form and RSS views join. The SEO views are offered only once
 * the columns they read exist.
 *
 * @return array filter => array('label', 'description', 'where' => SQL or
 *               '', 'access' => access control type or '', 'join' => SQL
 *               or '', 'select' => SQL or '', 'group' => bool)
 */
function pg_page_list_filters()
{
    $types = function ($types) {
        $parts = array();

        foreach ($types as $type) {
            $parts[] = "page.page_type = '" . $type . "'";
        }

        return '(' . implode(' OR ', $parts) . ')';
    };

    $filters = array(
        'all_my_pages' => array(lang('All My Pages'), lang('All pages that I can edit &amp; duplicate.'), ''),
        'all_my_archived_pages' => array(lang('All My Archived Pages'), lang('All archived pages that I can edit & duplicate.'), ''),
        'my_home_pages' => array(lang('My Home Pages'), lang('These pages are rotated as the website\'s home page.'), "(page.page_home = 'yes')"),
        'my_searchable_pages' => array(lang('My Searchable Pages'), lang('Depending on the page\'s access control, these pages can be found using the built-in site search feature.'), "(page.page_search = '1')"),
        'my_unsearchable_pages' => array(lang('My Unsearchable Pages'), lang('These pages cannot be found using the built-in site search.'), "(page.page_search = '0')"),
        'my_sitemap_pages' => array(lang('My Site Map Pages'), lang('These are Public Pages that appear in the sitemap.xml file so search engines can find them.'), "(page.sitemap = '1')", 'public'),
        'my_rss_enabled_pages' => array(lang('My RSS Enabled Pages'), lang('These are Public Pages that are able to broadcast RSS feeds.'),
            "(((page.page_type = 'form list view') AND (form_fields.rss_field != '')) OR " . substr($types(array('calendar view', 'catalog', 'catalog detail', 'order form')), 1, -1) . ')', 'public'),
        'my_standard_pages' => array(lang('My Standard Pages'), lang('These pages only contain content and do not contain any built-in interactive features.'), $types(array('standard'))),
        'my_photo_gallery_pages' => array(lang('My Photo Gallery Pages'), lang('These pages display a photo gallery slideshow of all photos located in the same folder.'), $types(array('photo gallery'))),
        'my_calendar_pages' => array(lang('My Calendar Pages'), lang('These pages overlay one or more calendars, calendar details, and published events.'), $types(array('calendar event view', 'calendar view'))),
        'my_custom_form_pages' => array(lang('My Custom Form Pages'), lang('These pages either gather data through customizable forms or display submitted form confirmations.'), $types(array('custom form', 'custom form confirmation'))),
        'my_form_view_pages' => array(lang('My Form View Pages'), lang('These pages display submitted form data in customizable views.'), $types(array('form list view', 'form item view', 'form view directory'))),
        'my_commerce_pages' => array(lang('My Commerce Pages'), lang('These pages provide the built-in ecommerce features.'),
            $types(array('order form', 'view order', 'catalog', 'catalog detail', 'express order', 'shopping cart', 'shipping address and arrival', 'shipping method', 'billing information', 'order preview', 'order receipt', 'update address book'))),
        'my_account_pages' => array(lang('My Account Pages'), lang('These pages provide the built-in account self-management for all site users.'),
            $types(array('my account', 'my account profile', 'email preferences', 'view order', 'update address book', 'change password'))),
        'my_login_pages' => array(lang('My Login Pages'), lang('These pages provide the built-in user account creation and login capabilities for all site users.'),
            $types(array('registration entrance', 'registration confirmation', 'membership entrance', 'membership confirmation', 'forgot password', 'login', 'logout', 'set password'))),
        'my_affiliate_pages' => array(lang('My Affiliate Pages'), lang('These pages provide the built-in affiliate sign up features.'), $types(array('affiliate sign up form', 'affiliate sign up confirmation', 'affiliate welcome'))),
        'my_miscellaneous_pages' => array(lang('My Miscellaneous Pages'), lang('These pages provide responses to other site-wide built-in features.'), $types(array('email a friend', 'error', 'folder view', 'search results'))),
        'my_public_pages' => array(lang('My Public Access Pages'), lang('These pages are visible by any website visitor.'), '', 'public'),
        'my_guest_pages' => array(lang('My Guest Access Pages'), lang('These pages are visible to any website visitor after they are offered the option to login or register.'), '', 'guest'),
        'my_registration_pages' => array(lang('My Registration Access Pages'), lang('These pages are visible to any website visitor, but only after they login or register.'), '', 'registration'),
        'my_membership_pages' => array(lang('My Member Access Pages'), lang('These pages are visible to any website user, but only after they have either registered with a valid member id, purchased a membership product, completed a membership trial custom form, or logged in as an unexpired member.'), '', 'membership'),
        'my_private_pages' => array(lang('My Private Access Pages'), lang('These pages are visible only to website users who have been granted view access to the parent folder, or who have purchased a product that grants private access to the parent folder.'), '', 'private'),
    );

    // Uncalculated rows are left out of the score views on purpose: their
    // stored 0 means unknown, not a real score. The seo_flags bits are the
    // ones pg_seo_evaluate() sets.
    if (pg_seo_schema_ready()) {
        $filters['seo_critical'] = array(lang('SEO Score Critical (0-29)'), lang('These pages have the most severe SEO problems and should be reviewed first.'), '(page.seo_score < 30) AND (page.seo_analysis_current = 1)');
        $filters['seo_weak'] = array(lang('SEO Score Weak (30-54)'), lang('These pages have significant SEO problems.'), '(page.seo_score BETWEEN 30 AND 54) AND (page.seo_analysis_current = 1)');
        $filters['seo_sitemap_no_title'] = array(lang('In Site Map, Missing Title'), lang('These pages are listed in sitemap.xml but have no web browser title, so search engines have to guess one.'), "(page.sitemap = '1') AND ((page.seo_flags & 1) != 0)", 'public');
        $filters['seo_sitemap_no_description'] = array(lang('In Site Map, Missing Description'), lang('These pages are listed in sitemap.xml but have no web browser description, so search engines compose their own snippet.'), "(page.sitemap = '1') AND ((page.seo_flags & 32) != 0)", 'public');
        $filters['seo_duplicate'] = array(lang('Duplicate Title or Description'), lang('These pages share a web browser title or description with another page, which makes them compete with each other in search results.'), '((page.seo_flags & 264) != 0)');
        $filters['seo_search_weak_keywords'] = array(lang('In Site Search, Weak Keywords'), lang('These pages are included in the built-in site search but have missing or too few keywords, so visitors have a hard time finding them.'), "(page.page_search = '1') AND ((page.seo_flags & 1536) != 0)");
        $filters['seo_not_calculated'] = array(lang('SEO Score Not Calculated'), lang('The SEO score of these pages is stale or has never been calculated. Scores are refreshed automatically while browsing, by the nightly job, or with the Refresh SEO Scores button.'), '((page.seo_checked_at = 0) OR (page.seo_analysis_current = 0))');
    }

    // The structure views read bits only the HTML analysis pass fills in.
    if (pg_seo_structure_schema_ready()) {
        $filters['seo_no_h1'] = array(lang('Missing H1 Heading'), lang('The rendered HTML of these pages has no H1 heading, so nothing states what the page is about.'), '((page.seo_flags & 131072) != 0)');
        $filters['seo_struct_error'] = array(lang('HTML Structure Errors'), lang('The markup of these pages has at least one serious fault, such as invalid nesting or images with no alt text.'), '((page.seo_flags & 32768) != 0)');
        $filters['seo_img_no_alt'] = array(lang('Images Without Alt Text'), lang('These pages contain images with no alt text, which search engines and screen readers cannot interpret.'), '((page.seo_flags & 524288) != 0)');
    }

    // The link views read bits the graph pass fills in (2026.4.2 columns).
    if (pg_seo_structure_schema_ready() && pg_schema_has('seo_link')) {
        $filters['seo_broken_links'] = array(lang('Pages With Broken Internal Links'), lang('These pages link to an address that no longer resolves to a page, file or product.'), '((page.seo_flags & 2097152) != 0)');
        $filters['seo_orphan'] = array(lang('Orphan Pages'), lang('These pages are listed in sitemap.xml but no other page links to them, so a visitor can only arrive by search or by typing the address.'), '((page.seo_flags & 4194304) != 0)', 'public');
    }

    $views = array();

    foreach ($filters as $name => $filter) {
        $views[$name] = array(
            'label' => $filter[0],
            'description' => $filter[1],
            'where' => $filter[2],
            'access' => $filter[3] ?? '',
            'join' => '',
            'select' => '',
            'group' => false,
        );
    }

    $views['my_rss_enabled_pages']['join'] =
        "LEFT JOIN form_list_view_pages ON (form_list_view_pages.page_id = page.page_id) AND (form_list_view_pages.collection = 'a')
        LEFT JOIN form_fields ON form_fields.page_id = form_list_view_pages.custom_form_page_id";
    $views['my_rss_enabled_pages']['group'] = true;

    $views['my_custom_form_pages']['join'] = 'LEFT JOIN custom_form_pages ON custom_form_pages.page_id = page.page_id';
    $views['my_custom_form_pages']['select'] = ', custom_form_pages.form_name, custom_form_pages.enabled AS enabled';

    return $views;
}

/**
 * A view name from the request: a known view, else 'default' (all pages
 * outside the archive, under the All My Pages heading). A view the
 * upgrade has not made available yet, or one sent as an array, is unknown.
 *
 * @param mixed $value
 * @param array|null $filters pg_page_list_filters(), read when null
 * @return string
 */
function pg_page_list_filter_name($value, $filters = null)
{
    if ($filters === null) {
        $filters = pg_page_list_filters();
    }

    if (is_string($value) && isset($filters[$value])) {
        return $value;
    }

    return 'default';
}

/**
 * The folders whose pages the view may list, or null when every folder's
 * may. A basic user (role 3) sees the folders they can edit; a view kept
 * to an access type (public, private...) sees the folders of that type.
 *
 * @param array  $user   the signed-in user (id, role)
 * @param string $access access control type the view keeps to, or ''
 * @return array|null folder ids
 */
function pg_page_list_folder_ids($user, $access)
{
    $basic = ((int) $user['role'] >= 3);

    if (!$basic && ($access === '')) {
        return null;
    }

    if ($basic) {
        $folder_ids = get_folders_that_user_has_access_to($user['id']);
    } else {
        $folder_ids = array();
        $result = db_q('SELECT folder_id FROM folder', array());

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            $folder_ids[] = $row['folder_id'];
        }
    }

    $ids = array();

    foreach ($folder_ids as $folder_id) {
        // get_access_control_type() reads the folder tree once per request.
        if (($access === '') || (get_access_control_type($folder_id) == $access)) {
            $ids[] = (int) $folder_id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * The condition every read of the view shares (without WHERE).
 *
 * @param string     $where      the view's own condition, or ''
 * @param bool       $archive    true for the archive view
 * @param array      $bin        pg_recycle_bin_folder_ids()
 * @param array|null $folder_ids pg_page_list_folder_ids()
 * @return string
 */
function pg_page_list_where($where, $archive, $bin, $folder_ids)
{
    $parts = array();

    if ($where !== '') {
        $parts[] = $where;
    }

    // A page in no folder has NULL folder columns through the LEFT JOIN.
    // A folder carried into the Recycle Bin keeps folder_archived = 0, so
    // the bin's folders count as archived here (pg_pages_bin_sql()).
    if ($archive) {
        $parts[] = "((folder.folder_archived = '1')" . pg_pages_bin_sql($bin, true) . ')';
    } else {
        $parts[] = "((folder.folder_archived = '0' OR folder.folder_archived IS NULL)" . pg_pages_bin_sql($bin, false) . ')';
    }

    if ($folder_ids !== null) {
        $parts[] = $folder_ids ? '(page.page_folder IN (' . implode(', ', array_map('intval', $folder_ids)) . '))' : '(1 = 0)';
    }

    return implode(' AND ', $parts);
}

/**
 * The device type whose styles the list shows ('desktop' or 'mobile').
 *
 * @return string
 */
function pg_page_list_device_type()
{
    return (($_SESSION['software']['device_type'] ?? '') === 'mobile') ? 'mobile' : 'desktop';
}

/**
 * The view: its name, heading and description, columns and sort, the
 * condition and joins, and the theme preview the style column follows.
 *
 * @param array  $user   the signed-in user
 * @param string $filter pg_page_list_filter_name()'s answer
 * @return array
 */
function pg_page_list_view($user, $filter)
{
    $filters = pg_page_list_filters();
    $filter = pg_page_list_filter_name($filter, $filters);
    $definition = $filters[($filter === 'default') ? 'all_my_pages' : $filter];
    $device_type = pg_page_list_device_type();

    // A theme other than the activated one is being previewed: its styles
    // come from preview_styles row by row, so the style column is not sorted.
    $preview_theme_id = (int) ($_SESSION['software']['preview_theme_id'] ?? 0);
    $activated_theme_id = 0;

    if ($preview_theme_id) {
        $activated_theme_id = (int) db_value("SELECT id FROM files WHERE activated_" . sql_device_type($device_type) . "_theme = '1'");
    }

    $previewing_other_theme = ($preview_theme_id && ($preview_theme_id != $activated_theme_id));
    $style_heading = ($device_type === 'mobile') ? lang('Mobile Page Style') : lang('Desktop Page Style');
    $custom_form = ($filter === 'my_custom_form_pages');

    // The columns this view sorts by, in the order the table shows them. The
    // style column is one code for both device types, so a sort by it
    // carries over when the device toggle is used.
    $sort_columns = array('name' => lang('Name'));

    if ($custom_form) {
        $sort_columns['form_name'] = lang('Form Name');
        $sort_columns['form_enabled'] = lang('Form Enabled');
    }

    $sort_columns['folder'] = lang('Folder');

    if (!$previewing_other_theme) {
        $sort_columns['style'] = $style_heading;
    }

    $sort_columns['page_type'] = lang('Page Type');
    $sort_columns['searchable'] = lang('Searchable');
    $sort_columns['comments'] = lang('Comments');
    $sort_columns['sitemap'] = lang('Site Map');
    $sort_columns['seo'] = lang('SEO');
    $sort_columns['impact'] = lang('Impact');
    $sort_columns['last_modified'] = lang('Last Modified');

    $sort_default = array('sort' => 'last_modified', 'order' => 'desc');
    $list_state = pg_list_state('view_pages', $sort_columns, $sort_default);

    // Only codes pg_list_state() let through reach pg_list_order_by(). The
    // page id comes last, so pages of the list never overlap.
    $order_by = pg_list_order_by($list_state['sorts'], array(
        'name'          => 'page.page_name',
        'form_name'     => 'custom_form_pages.form_name',
        'form_enabled'  => 'custom_form_pages.enabled',
        'folder'        => 'folder.folder_name',
        'style'         => 'style.style_name',
        'page_type'     => pg_list_label_order('page.page_type', get_page_type_names()),
        'searchable'    => 'page.page_search',
        'comments'      => 'page.comments',
        'sitemap'       => 'page.sitemap',
        'seo'           => 'page.seo_score',
        'impact'        => 'seo_impact',
        'last_modified' => 'page.page_timestamp',
    ));
    $order_by = (($order_by !== '') ? $order_by . ', ' : '') . 'page.page_id DESC';

    return array(
        'filter' => $filter,
        'heading' => $definition['label'],
        'description' => $definition['description'],
        'custom_form' => $custom_form,
        'device_type' => $device_type,
        'style_join' => ($device_type === 'mobile') ? 'page.mobile_style_id' : 'page.page_style',
        'style_heading' => $style_heading,
        'preview_theme_id' => $preview_theme_id,
        'previewing_other_theme' => $previewing_other_theme,
        'sort_columns' => $sort_columns,
        'sort_levels' => $list_state['sorts'],
        'sort_default' => $sort_default,
        'order_by' => $order_by,
        'join' => $definition['join'],
        'select' => $definition['select'],
        'group' => $definition['group'],
        'where' => pg_page_list_where(
            $definition['where'],
            ($filter === 'all_my_archived_pages'),
            pg_recycle_bin_folder_ids(),
            pg_page_list_folder_ids($user, $definition['access'])),
    );
}

/**
 * The FROM part every read of the view shares.
 *
 * @param array $view pg_page_list_view()
 * @return string
 */
function pg_page_list_from($view)
{
    return 'FROM page
        LEFT JOIN folder ON page.page_folder = folder.folder_id
        LEFT JOIN style ON ' . $view['style_join'] . ' = style.style_id
        LEFT JOIN user ON page.page_user = user.user_id
        ' . $view['join'];
}

/**
 * The columns the search box looks in: name, folder, the page's own style,
 * the user who last changed it, the form name on the custom form view, and
 * the page type by its label.
 *
 * @param array $view pg_page_list_view()
 * @return array array('columns', 'labels') for pg_list_search_where()
 */
function pg_page_list_search_columns($view)
{
    $columns = array('page.page_name', 'folder.folder_name', 'style.style_name', 'user.user_username');

    if ($view['custom_form']) {
        $columns[] = 'custom_form_pages.form_name';
    }

    return array(
        'columns' => $columns,
        'labels' => array(array('expr' => 'page.page_type', 'labels' => get_page_type_names())),
    );
}

/**
 * One page of the list: the counts without and with the search, and the
 * rows of the page window.
 *
 * @param array $view   pg_page_list_view()
 * @param array $terms  pg_list_search_terms()
 * @param array $window pg_list_page_window()
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_page_list_page($view, $terms, $window)
{
    $from = pg_page_list_from($view);
    $columns = pg_page_list_search_columns($view);
    $search = pg_list_search_where($terms, $columns['columns'], $columns['labels']);
    $where = ' WHERE ' . $view['where'];
    $search_sql = ($search['sql'] !== '') ? ' AND ' . $search['sql'] : '';

    // DISTINCT: the RSS view's joins give a page one row per form field.
    $result = db_q('SELECT COUNT(DISTINCT page.page_id) ' . $from . $where, array());

    if (!$result) {
        return false;
    }

    $total = (int) mysqli_fetch_row($result)[0];
    $filtered = $total;

    if ($search_sql !== '') {
        $result = db_q('SELECT COUNT(DISTINCT page.page_id) ' . $from . $where . $search_sql, $search['params']);

        if (!$result) {
            return false;
        }

        $filtered = (int) mysqli_fetch_row($result)[0];
    }

    // The traffic behind the impact figure joins only the page's read.
    $result = db_q(
        'SELECT
            page.page_id,
            page.page_name,
            page.page_folder,
            page.page_style,
            page.layout_type,
            ' . (pg_multi_page_design_ready() ? "(page.page_tree_json IS NOT NULL AND page.page_tree_json <> '') AS has_tree," : "'0' AS has_tree,") . '
            folder.folder_name,
            folder.folder_access_control_type,
            style.style_name,
            page.page_search,
            page.page_home,
            page.page_type,
            page.comments,
            page.seo_score,
            ' . (pg_seo_schema_ready() ? 'page.seo_flags, page.seo_checked_at,' : "'0' AS seo_flags, '0' AS seo_checked_at,") . '
            page.seo_analysis_current,
            page.sitemap,
            user.user_username,
            ' . pg_seo_impact_select('page.seo_score', 'page.seo_checked_at') . ',
            page.page_timestamp' . $view['select'] . '
        ' . $from . ' ' . pg_seo_traffic_join('page', 'page.page_id') . $where . $search_sql
        . ($view['group'] ? ' GROUP BY page.page_id' : '')
        . ' ORDER BY ' . $view['order_by'] . pg_list_limit_sql($window),
        $search['params']);

    if (!$result) {
        return false;
    }

    $pages = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $pages[] = $row;
    }

    return array('total' => $total, 'filtered' => $filtered, 'rows' => pg_page_list_rows($view, $pages));
}

/**
 * Style names by id, one query for all of them.
 *
 * @param array $ids style ids
 * @return array id => name
 */
function pg_page_list_style_names($ids)
{
    $ids = array_values(array_filter(array_unique(array_map('intval', (array) $ids))));
    $names = array();

    if (!$ids) {
        return $names;
    }

    $result = db_q('SELECT style_id, style_name FROM style WHERE style_id IN (' . implode(', ', $ids) . ')', array());

    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $names[(int) $row['style_id']] = $row['style_name'];
    }

    return $names;
}

/**
 * The styles folders hand down, for the desktop and mobile device types,
 * read once per request (pg_page_list_folder_styles()).
 *
 * @return array 'desktop' and 'mobile' => folder id => style id
 */
function pg_page_list_inherited_styles()
{
    static $styles = null;

    if ($styles === null) {
        $folders = db_items('SELECT folder_id, folder_parent, folder_level, folder_style, mobile_style_id FROM folder');
        $folders = is_array($folders) ? $folders : array();

        $styles = array(
            'desktop' => pg_page_list_folder_styles($folders, 'desktop'),
            'mobile' => pg_page_list_folder_styles($folders, 'mobile'),
        );
    }

    return $styles;
}

/**
 * The style column of each page: a previewed theme's style ([P]), the
 * page's own style, or its folder's ("Default: …"; red on the mobile view
 * when only a desktop style was found), with [A] while a theme is previewed.
 *
 * @param array $view  pg_page_list_view()
 * @param array $pages rows of pg_page_list_page()'s read
 * @return array page id => array('html', 'class')
 */
function pg_page_list_style_cells($view, $pages)
{
    $preview = array();

    if ($view['previewing_other_theme'] && $pages) {
        $ids = array();

        foreach ($pages as $page) {
            $ids[] = (int) $page['page_id'];
        }

        $result = db_q(
            'SELECT preview_styles.page_id, style.style_name
            FROM preview_styles
            LEFT JOIN style ON preview_styles.style_id = style.style_id
            WHERE
                (preview_styles.page_id IN (' . implode(', ', $ids) . '))
                AND (preview_styles.theme_id = ?)
                AND (preview_styles.device_type = ?)',
            array((string) $view['preview_theme_id'], $view['device_type']));

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            if ((string) $row['style_name'] !== '') {
                $preview[(int) $row['page_id']] = $row['style_name'];
            }
        }
    }

    // The folders' styles, and their names, only for pages without one.
    $inherited = array();

    foreach ($pages as $page) {
        $page_id = (int) $page['page_id'];

        if (!isset($preview[$page_id]) && ((string) $page['style_name'] === '')) {
            $styles = pg_page_list_inherited_styles();
            $folder_id = (int) $page['page_folder'];
            $style_id = (int) ($styles[$view['device_type']][$folder_id] ?? 0);
            $fallback = false;

            // A page without a mobile style is shown in the desktop one.
            if (($view['device_type'] === 'mobile') && !$style_id) {
                $style_id = (int) ($styles['desktop'][$folder_id] ?? 0);
                $fallback = true;
            }

            $inherited[$page_id] = array('id' => $style_id, 'fallback' => $fallback);
        }
    }

    $names = pg_page_list_style_names(array_column($inherited, 'id'));
    $cells = array();

    foreach ($pages as $page) {
        $page_id = (int) $page['page_id'];
        $class = 'align-middle';

        if (isset($preview[$page_id])) {
            $cells[$page_id] = array('html' => '[P] ' . h($preview[$page_id]), 'class' => $class);
            continue;
        }

        $html = '';

        if ((string) $page['style_name'] !== '') {
            $html = h($page['style_name']);
        } else {
            if ($inherited[$page_id]['fallback']) {
                $class .= ' fg-danger';
            }

            if (isset($names[$inherited[$page_id]['id']])) {
                $html = lang(array('string' => 'Default: {var:1}', 'vars' => h($names[$inherited[$page_id]['id']])));
            }
        }

        if ($view['preview_theme_id']) {
            $html = '[A] ' . $html;
        }

        $cells[$page_id] = array('html' => $html, 'class' => $class);
    }

    return $cells;
}

/**
 * The list's rows for a page of pages.
 *
 * @param array $view  pg_page_list_view()
 * @param array $pages rows of pg_page_list_page()'s read
 * @return array rows as pg_list_rows_answer() takes them
 */
function pg_page_list_rows($view, $pages)
{
    $styles = pg_page_list_style_cells($view, $pages);
    $check = '<span class="material-icons" role="img" aria-label="' . h(lang('Yes')) . '">task_alt</span>';
    $rows = array();

    // Experiments: a chip on a page with an open one and on its variant
    // draft; the sheet's button for designers on a designed page.
    $experiment_states = pg_experiment_page_states(array_column($pages, 'page_id'));
    $experiments_open = pg_experiment_sheet_ready() && defined('USER_ROLE') && ((int) USER_ROLE <= 1);

    foreach ($pages as $page) {
        $id = (int) $page['page_id'];

        // These page types show their form or record only with ?from=control_panel.
        $from = in_array($page['page_type'], array('view order', 'custom form', 'custom form confirmation', 'calendar event view', 'catalog detail', 'shipping address and arrival', 'shipping method', 'logout'), true)
            ? '?from=control_panel' : '';
        $link_url = PATH . encode_url_path($page['page_name']) . $from;

        // A visual-editor page opens in the visual editor; everything else
        // (custom style, folder default) in the page screen.
        $edit_url = pg_page_edit_url($page);
        $seo_url = 'get_seo_analysis.php?type=page&amp;id=' . $id . get_token_query_string_field();
        $home = ($page['page_home'] == 'yes') ? '<span class="material-icons" role="img" aria-label="' . h(lang('Home Page')) . '">home</span>' : '';

        $cells = array(
            pg_list_cell('<input class="check" type="checkbox" name="pages[]" value="' . $id . '" aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => array($page['page_name'])))) . '" />', 'select-all align-middle text-start'),
            pg_list_cell(
                '<button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2" title="' . lang('Edit') . '" onclick="window.location.href=\'' . h(escape_javascript($edit_url)) . '\'"><i class="bi bi-pencil"></i></button>'
                . '<button type="button" class="m-1 btn-data-control btn btn-outline theme-secondary border-2" title="' . lang('Preview') . '" onclick="window.location.href=\'' . h(escape_javascript($link_url)) . '\'"><i class="bi bi-link"></i></button>'
                . (($experiments_open && pg_page_is_visual_design($page)) ? '<a class="m-1 btn-data-control btn btn-outline theme-secondary border-2" title="' . h(lang('Experiments')) . '" aria-label="' . h(lang('Experiments')) . '" href="page_experiments.php?page_id=' . $id . '"><i class="bi bi-signpost-split" aria-hidden="true"></i></a>' : ''),
                'align-middle text-start action-buttons'),
            pg_list_cell($home . h($page['page_name']) . pg_experiment_chip_html($experiment_states[$id] ?? null) . '<a href="javascript:void(0)" class="pg-seo-open d-block text-decoration-none" data-pg-seo-bar="page:' . (int) $page['page_id'] . '" aria-label="' . h(pg_seo_link_name($page)) . '" title="' . lang('SEO Detail') . '" data-seo-url="' . $seo_url . '">' . pg_seo_render_bar($page) . '</a>', 'align-middle chart_label position-relative'),
        );

        if ($view['custom_form']) {
            $cells[] = pg_list_cell(h($page['form_name']));
            $cells[] = pg_list_cell(($page['enabled'] == 1) ? $check : '', 'align-middle text-center');
        }

        $cells[] = pg_list_cell(pg_folder_list_name($page['folder_name'], $page['folder_access_control_type']), 'align-middle text-start text-wrap ' . $page['folder_access_control_type']);
        $cells[] = pg_list_cell($styles[$id]['html'], $styles[$id]['class']);
        $cells[] = pg_list_cell(h(get_page_type_name($page['page_type'])));
        $cells[] = pg_list_cell(($page['page_search'] == 1) ? $check : '', 'align-middle text-center');
        $cells[] = pg_list_cell(($page['comments'] == '1') ? $check : '', 'align-middle text-center');
        $cells[] = pg_list_cell(($page['sitemap'] == '1') ? $check : '', 'align-middle text-center');
        $cells[] = pg_list_cell('<a href="javascript:void(0)" class="pg-seo-open text-decoration-none" data-pg-seo-badge="page:' . (int) $page['page_id'] . '" aria-label="' . h(pg_seo_link_name($page)) . '" title="' . lang('SEO Detail') . '" data-seo-url="' . $seo_url . '">' . pg_seo_render_badge($page) . '</a>', 'align-middle text-center');
        $cells[] = pg_list_cell(pg_seo_render_impact($page), 'align-middle text-center');
        $cells[] = pg_list_cell(pg_list_when($page['page_timestamp'], $page['user_username'] ?? ''));

        $rows[] = array('class' => '', 'cells' => $cells);
    }

    return $rows;
}
