<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pages list (view_pages.php), shared by the screen and the panel action
 * that pages it (page_list_rows, includes/panel/lists.php).
 *
 * A view is one of the filter menu's choices: its condition, the folders the
 * user may see and the access type it narrows to, and the columns it sorts
 * by. The screen draws the toolbar and the headings; the rows come one page
 * at a time. A page that takes its style from its folder has the folder's
 * style worked out once per folder from one read of the folder table, and
 * the style names of a page of rows are read in one query.
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
 * The views the filter menu offers. The SEO views are offered only once the
 * columns they read exist.
 *
 * @param bool $seo       pg_seo_schema_ready()
 * @param bool $structure pg_seo_structure_schema_ready()
 * @param bool $links     the link graph is installed (seo_link)
 * @return array filter => array('label', 'description', 'where' => SQL or '',
 *               'access' => folder access type or '', optional 'join',
 *               'select', 'group_by')
 */
function pg_page_list_filters($seo, $structure, $links)
{
    $type_in = function ($types) {
        return "(page.page_type IN ('" . implode("', '", $types) . "'))";
    };

    $filters = array(
        'all_my_pages' => array('label' => lang('All My Pages'), 'description' => lang('All pages that I can edit &amp; duplicate.')),
        'all_my_archived_pages' => array('label' => lang('All My Archived Pages'), 'description' => lang('All archived pages that I can edit & duplicate.')),
        'my_home_pages' => array('label' => lang('My Home Pages'), 'description' => lang('These pages are rotated as the website\'s home page.'), 'where' => "(page.page_home = 'yes')"),
        'my_searchable_pages' => array('label' => lang('My Searchable Pages'), 'description' => lang('Depending on the page\'s access control, these pages can be found using the built-in site search feature.'), 'where' => "(page.page_search = '1')"),
        'my_unsearchable_pages' => array('label' => lang('My Unsearchable Pages'), 'description' => lang('These pages cannot be found using the built-in site search.'), 'where' => "(page.page_search = '0')"),
        'my_sitemap_pages' => array('label' => lang('My Site Map Pages'), 'description' => lang('These are Public Pages that appear in the sitemap.xml file so search engines can find them.'), 'where' => "(page.sitemap = '1')", 'access' => 'public'),
        'my_rss_enabled_pages' => array(
            'label' => lang('My RSS Enabled Pages'),
            'description' => lang('These are Public Pages that are able to broadcast RSS feeds.'),
            'where' => "(((page.page_type = 'form list view') AND (form_fields.rss_field != '')) OR " . $type_in(array('calendar view', 'catalog', 'catalog detail', 'order form')) . ')',
            'access' => 'public',
            'join' => "LEFT JOIN form_list_view_pages ON (form_list_view_pages.page_id = page.page_id) AND (form_list_view_pages.collection = 'a')
                LEFT JOIN form_fields ON form_fields.page_id = form_list_view_pages.custom_form_page_id",
            'group_by' => 'GROUP BY page.page_id'),
        'my_standard_pages' => array('label' => lang('My Standard Pages'), 'description' => lang('These pages only contain content and do not contain any built-in interactive features.'), 'where' => $type_in(array('standard'))),
        'my_photo_gallery_pages' => array('label' => lang('My Photo Gallery Pages'), 'description' => lang('These pages display a photo gallery slideshow of all photos located in the same folder.'), 'where' => $type_in(array('photo gallery'))),
        'my_calendar_pages' => array('label' => lang('My Calendar Pages'), 'description' => lang('These pages overlay one or more calendars, calendar details, and published events.'), 'where' => $type_in(array('calendar event view', 'calendar view'))),
        'my_custom_form_pages' => array(
            'label' => lang('My Custom Form Pages'),
            'description' => lang('These pages either gather data through customizable forms or display submitted form confirmations.'),
            'where' => $type_in(array('custom form', 'custom form confirmation')),
            'join' => 'LEFT JOIN custom_form_pages ON custom_form_pages.page_id = page.page_id',
            'select' => ', custom_form_pages.form_name, custom_form_pages.enabled AS enabled'),
        'my_form_view_pages' => array('label' => lang('My Form View Pages'), 'description' => lang('These pages display submitted form data in customizable views.'), 'where' => $type_in(array('form list view', 'form item view', 'form view directory'))),
        'my_commerce_pages' => array('label' => lang('My Commerce Pages'), 'description' => lang('These pages provide the built-in ecommerce features.'), 'where' => $type_in(array('order form', 'view order', 'catalog', 'catalog detail', 'express order', 'shopping cart', 'shipping address and arrival', 'shipping method', 'billing information', 'order preview', 'order receipt', 'update address book'))),
        'my_account_pages' => array('label' => lang('My Account Pages'), 'description' => lang('These pages provide the built-in account self-management for all site users.'), 'where' => $type_in(array('my account', 'my account profile', 'email preferences', 'view order', 'update address book', 'change password'))),
        'my_login_pages' => array('label' => lang('My Login Pages'), 'description' => lang('These pages provide the built-in user account creation and login capabilities for all site users.'), 'where' => $type_in(array('registration entrance', 'registration confirmation', 'membership entrance', 'membership confirmation', 'forgot password', 'login', 'logout', 'set password'))),
        'my_affiliate_pages' => array('label' => lang('My Affiliate Pages'), 'description' => lang('These pages provide the built-in affiliate sign up features.'), 'where' => $type_in(array('affiliate sign up form', 'affiliate sign up confirmation', 'affiliate welcome'))),
        'my_miscellaneous_pages' => array('label' => lang('My Miscellaneous Pages'), 'description' => lang('These pages provide responses to other site-wide built-in features.'), 'where' => $type_in(array('email a friend', 'error', 'folder view', 'search results'))),
        'my_public_pages' => array('label' => lang('My Public Access Pages'), 'description' => lang('These pages are visible by any website visitor.'), 'access' => 'public'),
        'my_guest_pages' => array('label' => lang('My Guest Access Pages'), 'description' => lang('These pages are visible to any website visitor after they are offered the option to login or register.'), 'access' => 'guest'),
        'my_registration_pages' => array('label' => lang('My Registration Access Pages'), 'description' => lang('These pages are visible to any website visitor, but only after they login or register.'), 'access' => 'registration'),
        'my_membership_pages' => array('label' => lang('My Member Access Pages'), 'description' => lang('These pages are visible to any website user, but only after they have either registered with a valid member id, purchased a membership product, completed a membership trial custom form, or logged in as an unexpired member.'), 'access' => 'membership'),
        'my_private_pages' => array('label' => lang('My Private Access Pages'), 'description' => lang('These pages are visible only to website users who have been granted view access to the parent folder, or who have purchased a product that grants private access to the parent folder.'), 'access' => 'private'),
    );

    // Uncalculated rows are left out of the score views on purpose: their
    // stored 0 is "unknown", not a real score. The seo_flags bits are the
    // SEO engine's (seo.php).
    if ($seo) {
        $filters['seo_critical'] = array('label' => lang('SEO Score Critical (0-29)'), 'description' => lang('These pages have the most severe SEO problems and should be reviewed first.'), 'where' => '(page.seo_score < 30) AND (page.seo_analysis_current = 1)');
        $filters['seo_weak'] = array('label' => lang('SEO Score Weak (30-54)'), 'description' => lang('These pages have significant SEO problems.'), 'where' => '(page.seo_score BETWEEN 30 AND 54) AND (page.seo_analysis_current = 1)');
        $filters['seo_sitemap_no_title'] = array('label' => lang('In Site Map, Missing Title'), 'description' => lang('These pages are listed in sitemap.xml but have no web browser title, so search engines have to guess one.'), 'where' => "(page.sitemap = '1') AND ((page.seo_flags & 1) != 0)", 'access' => 'public');
        $filters['seo_sitemap_no_description'] = array('label' => lang('In Site Map, Missing Description'), 'description' => lang('These pages are listed in sitemap.xml but have no web browser description, so search engines compose their own snippet.'), 'where' => "(page.sitemap = '1') AND ((page.seo_flags & 32) != 0)", 'access' => 'public');
        $filters['seo_duplicate'] = array('label' => lang('Duplicate Title or Description'), 'description' => lang('These pages share a web browser title or description with another page, which makes them compete with each other in search results.'), 'where' => '((page.seo_flags & 264) != 0)');
        $filters['seo_search_weak_keywords'] = array('label' => lang('In Site Search, Weak Keywords'), 'description' => lang('These pages are included in the built-in site search but have missing or too few keywords, so visitors have a hard time finding them.'), 'where' => "(page.page_search = '1') AND ((page.seo_flags & 1536) != 0)");
        $filters['seo_not_calculated'] = array('label' => lang('SEO Score Not Calculated'), 'description' => lang('The SEO score of these pages is stale or has never been calculated. Scores are refreshed automatically while browsing, by the nightly job, or with the Refresh SEO Scores button.'), 'where' => '((page.seo_checked_at = 0) OR (page.seo_analysis_current = 0))');
    }

    // The structure views read bits only the HTML analysis pass fills in.
    if ($structure) {
        $filters['seo_no_h1'] = array('label' => lang('Missing H1 Heading'), 'description' => lang('The rendered HTML of these pages has no H1 heading, so nothing states what the page is about.'), 'where' => '((page.seo_flags & 131072) != 0)');
        $filters['seo_struct_error'] = array('label' => lang('HTML Structure Errors'), 'description' => lang('The markup of these pages has at least one serious fault, such as invalid nesting or images with no alt text.'), 'where' => '((page.seo_flags & 32768) != 0)');
        $filters['seo_img_no_alt'] = array('label' => lang('Images Without Alt Text'), 'description' => lang('These pages contain images with no alt text, which search engines and screen readers cannot interpret.'), 'where' => '((page.seo_flags & 524288) != 0)');
    }

    // The link views read bits the link graph pass fills in.
    if ($structure && $links) {
        $filters['seo_broken_links'] = array('label' => lang('Pages With Broken Internal Links'), 'description' => lang('These pages link to an address that no longer resolves to a page, file or product.'), 'where' => '((page.seo_flags & 2097152) != 0)');
        $filters['seo_orphan'] = array('label' => lang('Orphan Pages'), 'description' => lang('These pages are listed in sitemap.xml but no other page links to them, so a visitor can only arrive by search or by typing the address.'), 'where' => '((page.seo_flags & 4194304) != 0)', 'access' => 'public');
    }

    return $filters;
}

/**
 * The view a filter value names: one of $filters, else 'default' (All My
 * Pages). A view whose columns are not installed yet is not in $filters, so
 * a bookmarked SEO view never builds a condition on a missing column.
 *
 * @param mixed $value   the filter asked for
 * @param array $filters pg_page_list_filters()'s answer
 * @return string
 */
function pg_page_list_filter_name($value, $filters)
{
    return (is_string($value) && isset($filters[$value])) ? $value : 'default';
}

/**
 * The view's WHERE clause, with the leading WHERE.
 *
 * Archived pages show only in the archive view; a page in no folder (the
 * LEFT JOIN gives NULL) is not archived. A folder carried into the Recycle
 * Bin keeps folder_archived = 0, so the bin's folders count as archived
 * (pg_pages_bin_sql()).
 *
 * @param string     $filter     pg_page_list_filter_name()'s answer
 * @param array      $filters    pg_page_list_filters()'s answer
 * @param array      $bin_ids    the Recycle Bin's folder ids
 * @param array|null $folder_ids the folders the view may show, or null for
 *                               every folder
 * @return string
 */
function pg_page_list_where($filter, $filters, $bin_ids, $folder_ids)
{
    $conditions = array();

    if (($filters[$filter]['where'] ?? '') !== '') {
        $conditions[] = $filters[$filter]['where'];
    }

    if ($filter == 'all_my_archived_pages') {
        $conditions[] = "((folder.folder_archived = '1')" . pg_pages_bin_sql($bin_ids, true) . ')';
    } else {
        $conditions[] = "((folder.folder_archived = '0' OR folder.folder_archived IS NULL)" . pg_pages_bin_sql($bin_ids, false) . ')';
    }

    if ($folder_ids !== null) {
        $ids = array_values(array_unique(array_map('intval', $folder_ids)));
        $conditions[] = $ids ? '(page.page_folder IN (' . implode(', ', $ids) . '))' : '(1 = 0)';
    }

    return 'WHERE ' . implode(' AND ', $conditions);
}

/**
 * The columns a view sorts by, in the order the table shows them. The style
 * column is one code for both device types, so a sort by it carries over
 * when the device toggle is used; it is left out while another theme is
 * previewed (its styles are looked up per page).
 *
 * @param string $filter
 * @param bool   $previewing  a theme other than the activated one is previewed
 * @param string $style_label the style column's heading
 * @return array code => heading
 */
function pg_page_list_sort_columns($filter, $previewing, $style_label)
{
    $columns = array('name' => lang('Name'));

    if ($filter == 'my_custom_form_pages') {
        $columns['form_name'] = lang('Form Name');
        $columns['form_enabled'] = lang('Form Enabled');
    }

    $columns['folder'] = lang('Folder');

    if (!$previewing) {
        $columns['style'] = $style_label;
    }

    $columns['page_type'] = lang('Page Type');
    $columns['searchable'] = lang('Searchable');
    $columns['comments'] = lang('Comments');
    $columns['sitemap'] = lang('Site Map');
    $columns['seo'] = lang('SEO');
    $columns['impact'] = lang('Impact');
    $columns['last_modified'] = lang('Last Modified');

    return $columns;
}

/**
 * The view of the list for this request: filter, condition, joins, sort,
 * the device type and the previewed theme.
 *
 * @param array $user   the signed-in user
 * @param mixed $filter the filter asked for
 * @return array
 */
function pg_page_list_view($user, $filter)
{
    $filters = pg_page_list_filters(pg_seo_schema_ready(), pg_seo_structure_schema_ready(), pg_seo_structure_schema_ready() && pg_schema_has('seo_link'));
    $filter = pg_page_list_filter_name($filter, $filters);
    $device_type = (($_SESSION['software']['device_type'] ?? '') == 'mobile') ? 'mobile' : 'desktop';

    $preview_theme_id = 0;

    if (!empty($_SESSION['software']['preview_theme_id'])) {
        $preview_theme_id = (int) $_SESSION['software']['preview_theme_id'];
        $activated_theme_id = (int) db_value("SELECT id FROM files WHERE activated_" . sql_device_type($device_type) . "_theme = '1'");
        $previewing = ($preview_theme_id != $activated_theme_id);
    } else {
        $previewing = false;
    }

    $style_label = ($device_type == 'mobile') ? lang('Mobile Page Style') : lang('Desktop Page Style');
    $sort_columns = pg_page_list_sort_columns($filter, $previewing, $style_label);
    $sort_default = array('sort' => 'last_modified', 'order' => 'desc');
    $list_state = pg_list_state('view_pages', $sort_columns, $sort_default);

    // A basic user sees the folders given to them; an access view the
    // folders of its access type (inherited, as get_access_control_type()
    // works it out), asked once per folder that holds a page.
    $folder_ids = null;

    if ((int) $user['role'] == 3) {
        $folder_ids = array_map('intval', get_folders_that_user_has_access_to($user['id']));
    }

    $access = $filters[$filter]['access'] ?? '';

    if ($access !== '') {
        $typed = array();

        foreach ((array) db_values('SELECT DISTINCT page_folder FROM page') as $folder_id) {
            if (get_access_control_type($folder_id) == $access) {
                $typed[] = (int) $folder_id;
            }
        }

        $folder_ids = ($folder_ids === null) ? $typed : array_values(array_intersect($folder_ids, $typed));
    }

    $sort_sql = array(
        'name' => 'page.page_name',
        'form_name' => 'custom_form_pages.form_name',
        'form_enabled' => 'custom_form_pages.enabled',
        'folder' => 'folder.folder_name',
        'style' => 'style.style_name',
        'page_type' => pg_list_label_order('page.page_type', get_page_type_names()),
        'searchable' => 'page.page_search',
        'comments' => 'page.comments',
        'sitemap' => 'page.sitemap',
        'seo' => 'page.seo_score',
        'impact' => 'seo_impact',
        'last_modified' => 'page.page_timestamp',
    );

    return array(
        'filter' => $filter,
        'filters' => $filters,
        'heading' => ($filter == 'default') ? lang('All My Pages') : $filters[$filter]['label'],
        'description' => ($filter == 'default') ? lang('All pages that I can edit &amp; duplicate.') : $filters[$filter]['description'],
        'where' => pg_page_list_where($filter, $filters, pg_recycle_bin_folder_ids(), $folder_ids),
        'join' => $filters[$filter]['join'] ?? '',
        'select' => $filters[$filter]['select'] ?? '',
        'group_by' => $filters[$filter]['group_by'] ?? '',
        'device_type' => $device_type,
        'style_join' => ($device_type == 'mobile') ? 'page.mobile_style_id' : 'page.page_style',
        'style_label' => $style_label,
        'preview_theme_id' => $preview_theme_id,
        'previewing' => $previewing,
        'sort_columns' => $sort_columns,
        'sort_default' => $sort_default,
        'sort_levels' => $list_state['sorts'],
        'order_by' => ltrim(pg_list_order_by($list_state['sorts'], $sort_sql) . ', page.page_id asc', ', '),
    );
}

/**
 * Each folder's style as get_style() works it out, from one read of the
 * folder table: the folder's own style in $column, else its parent's, up to
 * the root folder (0 there). A missing parent or a loop ends the walk with 0.
 *
 * Pure: no query.
 *
 * @param array  $folders rows with folder_id, folder_parent, folder_level
 *                        and $column
 * @param string $column  'folder_style' (desktop) or 'mobile_style_id'
 * @return array folder_id => style id
 */
function pg_page_list_folder_styles($folders, $column)
{
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

            if ((int) ($by_id[$id][$column] ?? 0) > 0) {
                $style = (int) $by_id[$id][$column];
                break;
            }

            if ((int) ($by_id[$id]['folder_level'] ?? 0) === 0) {
                break;
            }

            $id = (int) ($by_id[$id]['folder_parent'] ?? 0);
        }

        foreach (array_keys($path) as $seen) {
            $styles[$seen] = $style;
        }
    }

    return $styles;
}

/**
 * The style cell of each page of a page of rows: a previewed theme's style
 * ([P]), the page's own style, or its folder's ("Default: …"); a mobile
 * page with no mobile style anywhere falls back to the desktop one, marked
 * in red. Three queries at most, whatever the number of rows.
 *
 * @param array $view  pg_page_list_view()'s answer
 * @param array $pages the page's rows
 * @return array page_id => array('html', 'class')
 */
function pg_page_list_styles($view, $pages)
{
    $preview = array();

    if ($view['previewing'] && $pages) {
        $ids = implode(', ', array_map(function ($page) { return (int) $page['page_id']; }, $pages));
        $result = db_q(
            'SELECT preview_styles.page_id, style.style_name
            FROM preview_styles
            LEFT JOIN style ON preview_styles.style_id = style.style_id
            WHERE (preview_styles.page_id IN (' . $ids . ')) AND (preview_styles.theme_id = ?) AND (preview_styles.device_type = ?)',
            array((string) $view['preview_theme_id'], $view['device_type']));

        while ($result && ($row = mysqli_fetch_assoc($result))) {
            if ((string) $row['style_name'] !== '') {
                $preview[(int) $row['page_id']] = $row['style_name'];
            }
        }
    }

    // The folders' styles, read only when a page takes its style from them.
    $inherited = array();
    $folder_styles = null;

    foreach ($pages as $page) {
        if (isset($preview[(int) $page['page_id']]) || ((string) $page['style_name'] !== '')) {
            continue;
        }

        if ($folder_styles === null) {
            $folders = (array) db_items('SELECT folder_id, folder_parent, folder_style, mobile_style_id, folder_level FROM folder');
            $folder_styles = array(
                'desktop' => pg_page_list_folder_styles($folders, 'folder_style'),
                'mobile' => pg_page_list_folder_styles($folders, 'mobile_style_id'));
        }

        $folder_id = (int) $page['page_folder'];
        $style_id = $folder_styles[$view['device_type']][$folder_id] ?? 0;
        $fallback = false;

        if (($view['device_type'] == 'mobile') && ($style_id == 0)) {
            $style_id = $folder_styles['desktop'][$folder_id] ?? 0;
            $fallback = true;
        }

        $inherited[(int) $page['page_id']] = array('style_id' => (int) $style_id, 'fallback' => $fallback);
    }

    $names = array();
    $style_ids = array_filter(array_unique(array_map(function ($item) { return $item['style_id']; }, $inherited)));

    if ($style_ids) {
        foreach ((array) db_items('SELECT style_id, style_name FROM style WHERE style_id IN (' . implode(', ', $style_ids) . ')') as $row) {
            $names[(int) $row['style_id']] = $row['style_name'];
        }
    }

    $styles = array();

    foreach ($pages as $page) {
        $page_id = (int) $page['page_id'];
        $html = '';
        $class = '';

        if (isset($preview[$page_id])) {
            $html = '[P] ' . h($preview[$page_id]);
        } else {
            if ((string) $page['style_name'] !== '') {
                $html = h($page['style_name']);
            } elseif (isset($inherited[$page_id])) {
                $class = $inherited[$page_id]['fallback'] ? ' fg-danger' : '';

                if (isset($names[$inherited[$page_id]['style_id']])) {
                    $html = lang(array('string' => 'Default: {var:1}', 'vars' => h($names[$inherited[$page_id]['style_id']])));
                }
            }

            // While a theme is previewed, "[A]" marks the activated style.
            if ($view['preview_theme_id']) {
                $html = '[A] ' . $html;
            }
        }

        $styles[$page_id] = array('html' => $html, 'class' => $class);
    }

    return $styles;
}

/**
 * One row of the list.
 *
 * @param array $view  pg_page_list_view()'s answer
 * @param array $page  the page's row
 * @param array $style pg_page_list_styles()'s item for the page
 * @return array as pg_list_row_html() takes it
 */
function pg_page_list_row($view, $page, $style)
{
    $check = '<span class="material-icons" role="img" aria-label="' . h(lang('Yes')) . '">task_alt</span>';
    $page_id = (int) $page['page_id'];

    // These page types need to know they are opened from the panel.
    $from = in_array($page['page_type'], array('view order', 'custom form', 'custom form confirmation', 'calendar event view', 'catalog detail', 'shipping address and arrival', 'shipping method', 'logout'), true) ? '?from=control_panel' : '';
    $link_url = h(escape_javascript(PATH)) . h(escape_javascript(encode_url_path($page['page_name']))) . $from;
    $seo_url = 'get_seo_analysis.php?type=page&amp;id=' . $page_id . get_token_query_string_field();
    $seo_name = h(pg_seo_link_name($page));
    $home = ($page['page_home'] == 'yes') ? '<span class="material-icons" role="img" aria-label="' . h(lang('Home Page')) . '">home</span>' : '';

    $cells = array(
        pg_list_cell('<input class="check" type="checkbox" name="pages[]" value="' . $page_id . '" aria-label="' . h(lang(array('string' => 'Select {var:1}', 'vars' => array($page['page_name'])))) . '" />', 'select-all align-middle text-start'),
        pg_list_cell(
            '<button type="button" class="m-1 btn-data-control btn btn-outline theme-primary border-2 " title="' . lang('Edit') . '" onclick="window.location.href=\'' . pg_page_edit_url($page) . '\'"><i class="bi bi-pencil"></i></button>'
            . '<button type="button" class="m-1 btn-data-control btn btn-outline theme-secondary border-2 " title="' . lang('Preview') . '" onclick="window.location.href=\'' . $link_url . '\'"><i class="bi bi-link"></i></button>',
            'align-middle text-start action-buttons'),
        pg_list_cell($home . h($page['page_name']) . '<a href="javascript:void(0)" class="pg-seo-open d-block text-decoration-none" data-pg-seo-bar="page:' . $page_id . '" aria-label="' . $seo_name . '" title="' . lang('SEO Detail') . '" data-seo-url="' . $seo_url . '">' . pg_seo_render_bar($page) . '</a>', 'align-middle chart_label position-relative'),
    );

    if ($view['filter'] == 'my_custom_form_pages') {
        $cells[] = pg_list_cell(h($page['form_name']));
        $cells[] = pg_list_cell(($page['enabled'] == 1) ? $check : '', 'align-middle text-center');
    }

    $cells[] = pg_list_cell(pg_folder_list_name($page['folder_name'], $page['folder_access_control_type']), 'align-middle text-start text-wrap ' . $page['folder_access_control_type']);
    $cells[] = pg_list_cell($style['html'], 'align-middle' . $style['class']);
    $cells[] = pg_list_cell(h(get_page_type_name($page['page_type'])));
    $cells[] = pg_list_cell(($page['page_search'] == 1) ? $check : '', 'align-middle text-center');
    $cells[] = pg_list_cell(($page['comments'] == '1') ? $check : '', 'align-middle text-center');
    $cells[] = pg_list_cell(($page['sitemap'] == '1') ? $check : '', 'align-middle text-center');
    $cells[] = pg_list_cell('<a href="javascript:void(0)" class="pg-seo-open text-decoration-none" data-pg-seo-badge="page:' . $page_id . '" aria-label="' . $seo_name . '" title="' . lang('SEO Detail') . '" data-seo-url="' . $seo_url . '">' . pg_seo_render_badge($page) . '</a>', 'align-middle text-center');
    $cells[] = pg_list_cell(pg_seo_render_impact($page), 'align-middle text-center');
    $cells[] = pg_list_cell(pg_list_when($page['page_timestamp'], $page['user_username'] ?? ''));

    return array('class' => '', 'cells' => $cells);
}

/**
 * The FROM clause every read of the list shares.
 *
 * @param array $view
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
 * One page of the list: the counts without and with the search, and the
 * rows of the page window. Stale SEO scores of the rows on the page are
 * worked out first, within a time budget; the rest stays queued for the
 * next page, the nightly job or the refresh button.
 *
 * @param array $view   pg_page_list_view()'s answer
 * @param array $terms  pg_list_search_terms()'s answer
 * @param array $window pg_list_page_window()'s answer
 * @return array|false array('total', 'filtered', 'rows') or false when a
 *                     query failed
 */
function pg_page_list_page($view, $terms, $window)
{
    $columns = array('page.page_name', 'folder.folder_name', 'style.style_name', 'user.user_username');

    if ($view['filter'] == 'my_custom_form_pages') {
        $columns[] = 'custom_form_pages.form_name';
    }

    $search = pg_list_search_where($terms, $columns, array(array('expr' => 'page.page_type', 'labels' => get_page_type_names())));
    $search_sql = ($search['sql'] !== '') ? ' AND ' . $search['sql'] : '';
    $from = pg_page_list_from($view);

    // The view's condition holds no value from the request.
    $result = mysqli_query(db::$con, 'SELECT COUNT(DISTINCT page.page_id) ' . $from . ' ' . $view['where']);

    if (!$result) {
        return false;
    }

    $total = (int) mysqli_fetch_row($result)[0];
    $filtered = $total;

    if ($search_sql !== '') {
        $result = db_q('SELECT COUNT(DISTINCT page.page_id) ' . $from . ' ' . $view['where'] . $search_sql, $search['params']);

        if (!$result) {
            return false;
        }

        $filtered = (int) mysqli_fetch_row($result)[0];
    }

    $result = db_q(
        'SELECT
            page.page_id, page.page_name, page.page_folder, page.page_style, page.layout_type,
            ' . (pg_multi_page_design_ready() ? "(page.page_tree_json IS NOT NULL AND page.page_tree_json <> '') AS has_tree," : "'0' AS has_tree,") . '
            folder.folder_name, folder.folder_access_control_type,
            style.style_name, style.style_type,
            page.page_search, page.page_home, page.page_type, page.comments, page.seo_score,
            ' . (pg_seo_schema_ready() ? 'page.seo_flags, page.seo_checked_at,' : "'0' AS seo_flags, '0' AS seo_checked_at,") . '
            page.seo_analysis_current, page.sitemap, user.user_username,
            ' . pg_seo_impact_select('page.seo_score', 'page.seo_checked_at') . ',
            page.page_timestamp' . $view['select'] . '
        ' . $from . '
        ' . pg_seo_traffic_join('page', 'page.page_id') . '
        ' . $view['where'] . $search_sql . '
        ' . $view['group_by'] . '
        ORDER BY ' . $view['order_by'] . pg_list_limit_sql($window),
        $search['params']);

    if (!$result) {
        return false;
    }

    $pages = array();
    $stale = array();

    while ($row = mysqli_fetch_assoc($result)) {
        $pages[] = $row;

        if (($row['seo_analysis_current'] != '1') && (count($stale) < 200)) {
            $stale[] = $row['page_id'];
        }
    }

    if ($stale && pg_seo_schema_ready()) {
        $run = pg_seo_recalculate('page', $stale, 3);

        foreach ($pages as $key => $page) {
            if (isset($run['items'][$page['page_id']])) {
                $pages[$key]['seo_score'] = $run['items'][$page['page_id']]['score'];
                $pages[$key]['seo_flags'] = $run['items'][$page['page_id']]['flags'];
                $pages[$key]['seo_analysis_current'] = '1';

                // The row was read before it was scored: its timestamp still
                // says "never" and its impact came from the old score.
                $pages[$key]['seo_checked_at'] = time();
                $pages[$key]['seo_impact'] = pg_seo_impact_value($pages[$key]['seo_score'], $pages[$key]['seo_views'] ?? 0);
            }
        }
    }

    $styles = pg_page_list_styles($view, $pages);
    $rows = array();

    foreach ($pages as $page) {
        $rows[] = pg_page_list_row($view, $page, $styles[(int) $page['page_id']]);
    }

    return array('total' => $total, 'filtered' => $filtered, 'rows' => $rows);
}
