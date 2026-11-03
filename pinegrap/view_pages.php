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
require_once(dirname(__FILE__) . '/seo.php');

$liveform = new liveform('view_pages');
$user = validate_user();
validate_area_access($user, 'user');

// Where the SEO tools return: the list in the view they were started from.
// Only a plain filter name is carried; the list checks it against its known
// filters again before using it.
$seo_return = PATH . SOFTWARE_DIRECTORY . '/view_pages.php';

if (isset($_GET['filter']) && is_string($_GET['filter']) && preg_match('/^[a-z_]+$/', $_GET['filter'])) {
    $seo_return = PATH . SOFTWARE_DIRECTORY . '/view_pages.php?filter=' . urlencode($_GET['filter']);
}

// Manual SEO score refresh from the button bar. A GET link with a token, like
// the other state-changing links on this screen. When nothing is stale the
// whole site is queued first (a refresh means "recompute everything"); when
// stale rows already exist the run continues them instead, so repeated clicks
// converge on a large site rather than starting over each time. The time
// budget keeps a single click under the PHP execution limit.
//
// Queuing marks rows stale but does not clear seo_checked_at, so the scores
// stay on screen while the backlog drains rather than blanking site-wide.
if (isset($_GET['recalculate_seo']) && (USER_ROLE < 3)) {
    validate_token_field();

    if (!pg_seo_schema_ready()) {
        $liveform->add_notice(lang('Please run the software upgrade to enable SEO scores.'));

        go($seo_return);
    }

    if (!db_value("SELECT COUNT(*) FROM page WHERE seo_analysis_current = 0")) {
        db("UPDATE page SET seo_analysis_current = 0");
    }

    $seo_run = pg_seo_recalculate('page', null, 20);

    if ($seo_run['remaining'] > 0) {
        $liveform->add_notice(lang(array(
            'string' => 'SEO scores were updated for {var:1} page{suffix:1}. {var:2} page{suffix:2} still need to be calculated - click Refresh SEO Scores again to continue.',
            'vars' => array($seo_run['processed'], $seo_run['remaining']),
            'suffix' => array((($seo_run['processed'] == 1) ? '' : 's'), (($seo_run['remaining'] == 1) ? '' : 's')))));
    } else {
        $liveform->add_notice(lang(array(
            'string' => 'SEO scores were updated for {var:1} page{suffix:1}.',
            'vars' => $seo_run['processed'],
            'suffix' => (($seo_run['processed'] == 1) ? '' : 's'))));
    }

    log_activity(lang('recalculated SEO scores'), $_SESSION['sessionusername']);

    go($seo_return);
}

// The HTML structure pass on demand (Analyze HTML Structure) runs in
// audit_run.php, which renders the pages as a visitor who is not signed in,
// as the nightly job does, and returns here with what it did.

// The view: filter, condition, sort and style column. The rows come one page
// at a time from the page_list_rows action (includes/panel/lists.php).
require_once(PG_FUNCTIONS_DIR . '/includes/fn/page_list.php');

$view = pg_page_list_view($user, $_GET['filter'] ?? '');
$filter = $view['filter'];
$sort_levels = $view['sort_levels'];
$output_filter_for_links = h('&filter=' . $filter);
$heading = $view['heading'];
$subheading = $view['description'];

$filters_in_array = array();

foreach ($view['filters'] as $filter_name => $filter_view) {
    $filters_in_array[$filter_name] = $filter_view['label'];
}

// The toggle between the desktop and the mobile page styles.
$device_toggle_to = ($view['device_type'] == 'mobile') ? 'desktop' : 'mobile';
$device_toggle_label = ($device_toggle_to == 'mobile') ? lang('Show mobile Page Styles') : lang('Show desktop Page Styles');
$output_device_type_toggle = '<a class="btn btn-sm btn-solid theme-secondary p-1" href="update_device_type.php?device_type=' . $device_toggle_to . '&amp;send_to=' . h(urlencode(get_request_uri())) . get_token_query_string_field() . '" title="' . $device_toggle_label . '" aria-label="' . $device_toggle_label . '"><span class="material-icons" aria-hidden="true">' . (($device_toggle_to == 'mobile') ? 'phone_iphone' : 'computer') . '</span></a>';

$output_button_bar = '';

// The screen toolbar: Add Page in slot 1, the page tools in one menu beside
// it, the sort menu and the content filter at the right end.
$view_pages_primary = '';
$view_pages_tools = array();

// if the user is at least a manager or has create pages turned on, then output the create page button
if (($user['role'] < '3') || ($user['create_pages'] == TRUE)) {
    if ($user['role'] < 2) {
        // A designer adds a visual page to a design of their choosing
        // (pg_designer_target_modal()); the classic page screen stays in the
        // tools menu.
        $view_pages_primary = '<button type="button" class="btn btn-sm btn-solid theme-primary rounded-pill px-5" data-pg-design-target="add" data-from="pages"><i class="bi bi-plus-circle me-2" aria-hidden="true"></i>' . lang('Add Page') . '</button>';
        $view_pages_tools[] = '<li><a class="menu-item pg-link-body-emphasis" href="add_page.php"><i class="bi bi-plus-circle me-3" aria-hidden="true"></i>' . lang('Add Page (Legacy)') . '</a></li>';
    } else {
        $view_pages_primary = array('label' => lang('Add Page'), 'url' => 'add_page.php', 'icon' => 'bi-plus-circle');
    }

    // The search index, SEO scores, structure and accessibility passes on one
    // screen (search_audits.php), managers and above.
    if (USER_ROLE < 3) {
        $view_pages_tools[] = '<li><a class="menu-item pg-link-body-emphasis" href="search_audits.php"><i class="bi bi-activity me-3" aria-hidden="true"></i>' . lang('Search and audits') . '</a></li>';
    }

    // SEO score refresh sits with the other maintenance tools. Managers and
    // above, same threshold the scores themselves matter to.
    if (USER_ROLE < 3) {
        $view_pages_tools[] = '<li><a class="menu-item pg-link-body-emphasis" href="view_pages.php?recalculate_seo=1' . $output_filter_for_links . get_token_query_string_field() . '"><i class="bi bi-speedometer2 me-3" aria-hidden="true"></i>' . lang('Refresh SEO Scores') . '</a></li>';
        $view_pages_tools[] = '<li><a class="menu-item pg-link-body-emphasis" href="audit_run.php?pass=structure' . $output_filter_for_links . get_token_query_string_field() . '" title="' . lang('Renders each page and examines the resulting HTML. Covers products and product groups too. Slower than a score refresh, and it may take several clicks on a large site.') . '"><i class="bi bi-code-slash me-3" aria-hidden="true"></i>' . lang('Analyze HTML Structure') . '</a></li>';
        $view_pages_tools[] = '<li><a class="menu-item pg-link-body-emphasis" href="view_accessibility.php"><i class="bi bi-universal-access me-3" aria-hidden="true"></i>' . lang('Accessibility report') . '</a></li>';
    }
}

$view_pages_tools_menu = '';

if ($view_pages_tools) {
    $view_pages_tools_menu = '<div class="pg-menu-wrap">
        <button type="button" class="btn btn-sm btn-text theme-secondary" data-bs-toggle="menu" data-bs-display="static" aria-expanded="false"><i class="bi bi-tools me-1" aria-hidden="true"></i>' . lang('Tools') . '</button>
        <ul class="menu">' . implode('', $view_pages_tools) . '</ul>
    </div>';
}

$output_button_bar = pg_screen_toolbar(array(
    'primary'   => $view_pages_primary,
    'secondary' => $view_pages_tools_menu,
    'search'    => array(
        pg_list_sort_menu('view_pages.php', $view['sort_columns'], $sort_levels, $view['sort_default'], array('filter' => $filter)),
        pg_filter_select('filter', $filters_in_array, $filter, array('label' => lang('Content that viewed'), 'icon' => 'eye', 'id' => 'filter_select')),
    ),
));

$output_form_name_heading = '';
$output_form_enabled_heading = '';

if ($filter == 'my_custom_form_pages') {
    $output_form_name_heading = '<th>' . get_column_heading(lang('Form Name'), $sort_levels, '', $output_filter_for_links, 'form_name') . '</th>';
    $output_form_enabled_heading = '<th>' . get_column_heading(lang('Form Enabled'), $sort_levels, '', $output_filter_for_links, 'form_enabled') . '</th>';
}

// While another theme is previewed the style column cannot be sorted.
if ($view['previewing']) {
    $output_style_column_heading = $view['style_label'];
} else {
    $output_style_column_heading = get_column_heading($view['style_label'], $sort_levels, '', $output_filter_for_links, 'style');
}

$output_list_attributes = ' data-pg-list-action="page_list_rows" data-pg-list-params="' . h(encode_json(array('filter' => $filter))) . '"';

// Site-wide SEO summary for the strip under the heading. Each count uses the
// condition of the list it links to: archived pages are left out everywhere,
// as every list but the archive leaves them out, and the site map counts
// only public pages, as its lists do (only those can be in sitemap.xml).
// The access type is read once per folder.
//
// Not shown to basic users. Their list below is filtered to the folders they
// can reach, so a site-wide total would both disagree with what they see and
// tell them about pages they have no access to - and the refresh button that
// acts on it is hidden from them anyway.
$seo_summary = null;

if ((USER_ROLE < 3) && pg_seo_schema_ready()) {
    $seo_summary_rows = db_items(
        "SELECT
            page.page_folder,
            page.sitemap,
            page.page_search,
            page.seo_flags,
            page.seo_checked_at,
            page.seo_analysis_current
        FROM page
        LEFT JOIN folder ON page.page_folder = folder.folder_id
        WHERE ((folder.folder_archived = '0') OR (folder.folder_archived IS NULL))" . pg_pages_bin_sql(pg_recycle_bin_folder_ids(), false));

    $seo_summary_public_folders = array();

    foreach ((array) $seo_summary_rows as $key => $row) {
        $folder_id = (int) $row['page_folder'];

        if (!isset($seo_summary_public_folders[$folder_id])) {
            $seo_summary_public_folders[$folder_id] = (get_access_control_type($folder_id) == 'public');
        }

        $seo_summary_rows[$key]['public'] = $seo_summary_public_folders[$folder_id];
    }

    $seo_summary = pg_seo_pages_summary((array) $seo_summary_rows);
}

$output_seo_summary = '';

if ($seo_summary && (($seo_summary['in_sitemap'] > 0) || ($seo_summary['in_search'] > 0) || ($seo_summary['not_calculated'] > 0))) {
    $seo_summary_parts = array();

    if ($seo_summary['in_sitemap'] > 0) {
        $seo_summary_part =
            '<a class="pg-link-secondary" href="view_pages.php?filter=my_sitemap_pages">'
            . lang(array('string' => '{var:1} page{suffix:1} in the site map', 'vars' => (int) $seo_summary['in_sitemap'], 'suffix' => (($seo_summary['in_sitemap'] == 1) ? '' : 's')))
            . '</a>';

        if ($seo_summary['sitemap_no_title'] > 0) {
            $seo_summary_part .= ' · <a class="pg-link-danger" href="view_pages.php?filter=seo_sitemap_no_title">'
                . lang(array('string' => '{var:1} missing a title', 'vars' => (int) $seo_summary['sitemap_no_title']))
                . '</a>';
        }

        if ($seo_summary['sitemap_no_description'] > 0) {
            $seo_summary_part .= ' · <a class="pg-link-danger" href="view_pages.php?filter=seo_sitemap_no_description">'
                . lang(array('string' => '{var:1} missing a description', 'vars' => (int) $seo_summary['sitemap_no_description']))
                . '</a>';
        }

        $seo_summary_parts[] = $seo_summary_part;
    }

    if ($seo_summary['in_search'] > 0) {
        $seo_summary_part =
            '<a class="pg-link-secondary" href="view_pages.php?filter=my_searchable_pages">'
            . lang(array('string' => '{var:1} page{suffix:1} in the site search', 'vars' => (int) $seo_summary['in_search'], 'suffix' => (($seo_summary['in_search'] == 1) ? '' : 's')))
            . '</a>';

        if ($seo_summary['search_weak_keywords'] > 0) {
            $seo_summary_part .= ' · <a class="pg-link-danger" href="view_pages.php?filter=seo_search_weak_keywords">'
                . lang(array('string' => '{var:1} with weak or missing keywords', 'vars' => (int) $seo_summary['search_weak_keywords']))
                . '</a>';
        }

        $seo_summary_parts[] = $seo_summary_part;
    }

    // Structure counts only appear once the analysis half has produced them;
    // a row of zeroes would read as "no problems" on an installation where
    // the pass has simply never run.
    if (($seo_summary['no_h1'] > 0) || ($seo_summary['img_no_alt'] > 0)) {
        $seo_summary_part = lang('HTML') . ': ';
        $seo_structure_bits = array();

        if ($seo_summary['no_h1'] > 0) {
            $seo_structure_bits[] = '<a class="pg-link-danger" href="view_pages.php?filter=seo_no_h1">'
                . lang(array('string' => '{var:1} without an H1 heading', 'vars' => (int) $seo_summary['no_h1']))
                . '</a>';
        }

        if ($seo_summary['img_no_alt'] > 0) {
            $seo_structure_bits[] = '<a class="pg-link-danger" href="view_pages.php?filter=seo_img_no_alt">'
                . lang(array('string' => '{var:1} with images missing alt text', 'vars' => (int) $seo_summary['img_no_alt']))
                . '</a>';
        }

        $seo_summary_parts[] = $seo_summary_part . implode(' · ', $seo_structure_bits);
    }

    if ($seo_summary['not_calculated'] > 0) {
        $seo_summary_parts[] =
            '<a class="pg-link-secondary" href="view_pages.php?filter=seo_not_calculated">'
            . lang(array('string' => '{var:1} not calculated or out of date', 'vars' => (int) $seo_summary['not_calculated']))
            . '</a>';
    }

    $output_seo_summary =
        '<div class="col-12 mb-3 small fg-secondary">
            <span class="bi bi-graph-up-arrow me-1"></span>' . implode(' &nbsp;|&nbsp; ', $seo_summary_parts) . '
        </div>';
}

$output_delete_selected_button = '';

// if the user is at least a manager or has access to delete pages, then output the delete selected button
if (($user['role'] < '3') || ($user['delete_pages'] == TRUE)) {
    // With the recycle bin installed the pages go there (edit_pages.php).
    require_once(dirname(__FILE__) . '/view_folder_and_files_f.php');
    $delete_confirmation = pg_recycle_ready()
        ? lang('The selected pages will be moved to the Recycle Bin. Those already in it will be deleted permanently.')
        : lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('pages'))));
    $output_delete_selected_button = '<button type="button" value="Delete Selected" class=" btn mb-1 mt-1 btn-solid theme-danger disabled" data-confirm-content="' . $delete_confirmation . '"><span class="material-icons me-3">delete</span>' . lang(array('string'=>'Delete Selected') ) . '</button>';
}

echo
pg_page_shell(
    array(
        'title'=> lang('Pages'),
        'extra classes'=>'page',
        'icon'=>'page', 
        'heading'=>($heading ?? lang('Pages')),
        'heading_description' => ($subheading ?? lang('Create, edit and publish site pages')),
        
    )
) . '
<main id="content" class="container-fluid">
    <div class="row">
        <div class="col-12">
            ' . $liveform->output_errors() . '
            ' . $liveform->get_warnings() . '
            ' . $liveform->output_notices() . '
            ' . $output_button_bar . '
            <div class="row">
                ' . $output_seo_summary . '
            </div>
            <div class="card my-7">
                <div class="card-body p-0 position-relative">
                    <form name="form"  action="edit_pages.php" method="post">
                        ' . get_token_field() . '
                        <input type="hidden" name="action" />
                        <input type="hidden" name="move_to_folder" />
                        <input type="hidden" name="edit_page_style" />
                        <input type="hidden" name="edit_mobile_style_id" />
                        <input type="hidden" name="edit_site_search" />
                        <input type="hidden" name="edit_sitemap" />
                        <input type="hidden" name="send_to" value="' . h(get_request_uri()) . '" />
                        <table class="chart table-hover table " style="width:100%;display:none"' . $output_list_attributes . '>
                            <thead>
                                <tr>
                                    <th class="noVis">
                                        <div class="form-field">
                                            <div class="switch"><input class="" title="' . lang(array('string'=>'Select/Deselect All') ) . '" type="checkbox" id="select_all"></div>
                                        </div>
                                    </th>
                                    <th class="noVis">' . lang(array('string'=>'Action') ) . '</th> 
                                    <th>' . get_column_heading(lang('Name'), $sort_levels, '', $output_filter_for_links, 'name') . '</th>
                                    ' . $output_form_name_heading . '
                                    ' . $output_form_enabled_heading . '
                                    <th>' . get_column_heading(lang('Folder'), $sort_levels, '', $output_filter_for_links, 'folder') . '</th>
                                    <th>' . $output_style_column_heading . '' . $output_device_type_toggle . '</th>
                                    <th>' . get_column_heading(lang('Page Type'), $sort_levels, '', $output_filter_for_links, 'page_type') . '</th>
                                    <th  style="text-align: center;">' . get_column_heading(lang('Searchable'), $sort_levels, '', $output_filter_for_links, 'searchable') . '</th>
                                    <th style="text-align: center;">' . get_column_heading(lang('Comments'), $sort_levels, '', $output_filter_for_links, 'comments') . '</th>
                                    <th style="text-align: center;">' . get_column_heading(lang('Site Map'), $sort_levels, '', $output_filter_for_links, 'sitemap') . '</th>
                                    <th style="text-align: center;">' . get_column_heading(lang('SEO'), $sort_levels, '', $output_filter_for_links, 'seo') . '</th>
                                    <th style="text-align: center;" title="' . h(lang('How much a weak score costs, weighed against how many people see the page')) . '">' . get_column_heading(lang('Impact'), $sort_levels, '', $output_filter_for_links, 'impact') . '</th>
                                    <th>' . get_column_heading(lang('Last Modified'), $sort_levels, '', $output_filter_for_links, 'last_modified') . '</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>

                        <nav class="buttons navigation text-center position-sticky" style="bottom:.5rem;" aria-label="' . h(lang('data edit buttons')) . '">
                            <div class="container">
                                <div class=" btn-group btn-group-sm flex-wrap justify-content-center mb-0 enable-on-selected">                                   
                                    <button type="button" value="Modify Selected" class=" btn mb-1 mt-1 btn-solid theme-primary disabled" onclick="window.open(\'edit_pages.php\', \'popup\', \'toolbar=no,location=no,directories=no,status=yes,menubar=no,resizable=yes,copyhistory=no,scrollbars=yes,width=500,height=500\'); edit_chart_content(\'edit\',\'product\')"><span class="material-icons me-3">edit</span>' . lang(array('string'=>'Modify Selected') ) . '</button>
                                    ' . $output_delete_selected_button . '
                                </div>
                            </div>
                        </nav>
                    </form>
                </div>
            </div>
        </div>
    </div>
    ' . pg_seo_render_detail_offcanvas() . '
</main>
' . ($user['role'] < 2 ? pg_designer_target_modal() : '') . '
' .
output_footer();

$liveform->remove_form('view_pages');