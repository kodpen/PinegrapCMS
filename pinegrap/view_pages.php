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

// A cell of the list changed in place (pg_list_edit_request() in
// includes/fn/output.php). Answered in JSON, before anything below draws the
// screen; the write follows the page screen's and Modify Selected's rules
// (pg_page_quick_update()). The fields depend on the user's rights, so
// they are read for the signed-in user; without one the request is
// refused below.
if (pg_list_edit_requested()) {
    require_once(dirname(__FILE__) . '/includes/fn/page_list.php');

    $quick_user = (defined('USER_LOGGED_IN') && USER_LOGGED_IN) ? validate_user() : null;

    $edit = pg_list_edit_request(pg_page_quick_edit_columns($quick_user), function ($user) {
        // The list's own gate (pg_page_list_source()); each write checks
        // the page's folder.
        return ((int) $user['role'] < 3) || (((int) $user['role'] == 3) && no_acl_check($user['id']));
    });

    $saved = pg_page_quick_update($edit['user'], $edit['id'], $edit['field'], $edit['value'], (isset($_GET['filter']) && is_string($_GET['filter'])) ? $_GET['filter'] : '');

    pg_list_edit_reply($saved, $saved['ok'] ? 200 : 422);
}

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

require_once(dirname(__FILE__) . '/includes/fn/page_list.php');

// The view: its condition, columns and sort (includes/fn/page_list.php). The
// list table driver prints the first rows; the next ones come from its
// list_table_rows action (includes/panel/lists.php) as the list scrolls.
$view = pg_page_list_view($user, $_GET['filter'] ?? '');
$filter = $view['filter'];
$sort_columns = $view['sort_columns'];
$sort_levels = $view['sort_levels'];
$sort_default = $view['sort_default'];
$output_filter_for_links = h('&filter=' . $filter);
$list_source = pg_page_list_source($user, array('filter' => $filter));

if ($list_source === null) {
    output_error(lang('Access denied'));
}

$filters_in_array = array();

foreach (pg_page_list_filters() as $filter_name => $filter_view) {
    $filters_in_array[$filter_name] = $filter_view['label'];
}

// The toggle between the desktop and the mobile styles the style column shows.
if ($view['device_type'] === 'mobile') {
    $output_device_type_toggle = '<a class="btn btn-sm btn-text theme-secondary" href="update_device_type.php?device_type=desktop&amp;send_to=' . h(urlencode(get_request_uri())) . get_token_query_string_field() . '" title="' . lang('Show desktop Page Styles') . '" aria-label="' . lang('Show desktop Page Styles') . '"><span class="material-icons" aria-hidden="true">computer</span></a>';
} else {
    $output_device_type_toggle = '<a class="btn btn-sm btn-text theme-secondary" href="update_device_type.php?device_type=mobile&amp;send_to=' . h(urlencode(get_request_uri())) . get_token_query_string_field() . '" title="' . lang('Show mobile Page Styles') . '" aria-label="' . lang('Show mobile Page Styles') . '"><span class="material-icons" aria-hidden="true">phone_iphone</span></a>';
}


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
        '<label class="form-control form-control-sm form-adorn rounded-pill pg-toolbar-search">
            <span class="form-adorn-text" aria-hidden="true"><i class="bi bi-search"></i></span>
            <input type="search" class="form-ghost" data-pg-list-search="view_pages" placeholder="' . h(lang('Search')) . '" aria-label="' . h(lang('Search')) . '">
        </label>',
        $output_device_type_toggle,
        pg_list_sort_menu('view_pages.php', $sort_columns, $sort_levels, $sort_default, array('filter' => $filter)),
        pg_filter_select('filter', $filters_in_array, $filter, array('label' => lang('Content that viewed'), 'icon' => 'eye', 'id' => 'filter_select')),
    ),
));

$heading = $view['heading'];
$subheading = $view['description'];

// Refresh stale SEO scores of this view before the list is fetched, the
// last changed first since those are the likeliest to be on the first
// page, capped and on a time budget so a large site cannot stall the
// screen; the rest is left to the nightly job or the refresh button.
if (pg_seo_schema_ready()) {
    $stale_page_ids = array();
    $stale_result = db_q(
        'SELECT DISTINCT page.page_id, page.page_timestamp ' . pg_page_list_from($view)
        . ' WHERE ' . $view['where'] . ' AND (page.seo_analysis_current = 0) ORDER BY page.page_timestamp DESC LIMIT 200');

    while ($stale_result && ($stale_row = mysqli_fetch_assoc($stale_result))) {
        $stale_page_ids[] = $stale_row['page_id'];
    }

    if ($stale_page_ids) {
        pg_seo_recalculate('page', $stale_page_ids, 3);
    }
}


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

// The bulk actions sit in the list's selection bar, which shows while rows
// are selected. Both post the selection to edit_pages.php; Modify Selected
// first asks for the changes in its dialog, whose Save submits the form.
require_once(dirname(__FILE__) . '/includes/fn/page_bulk.php');
$output_bulk_buttons = '<button type="button" class="no-submit btn btn-sm btn-solid theme-primary" data-pg-page-bulk-open><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . lang('Modify Selected') . '</button>';

// if the user is at least a manager or has access to delete pages, then output the delete selected button
if (($user['role'] < '3') || ($user['delete_pages'] == TRUE)) {
    // With the recycle bin installed the pages go there (edit_pages.php).
    require_once(dirname(__FILE__) . '/view_folder_and_files_f.php');
    $delete_confirmation = pg_recycle_ready()
        ? lang('The selected pages will be moved to the Recycle Bin. Those already in it will be deleted permanently.')
        : lang(array('string'=>'WARNING: Selected {var:1} will be permanently deleted.','vars'=>array(lang('pages'))));
    $output_bulk_buttons .= '<button type="button" value="Delete Selected" class="btn btn-sm btn-solid theme-danger" data-confirm-content="' . h($delete_confirmation) . '"><i class="bi bi-trash me-1" aria-hidden="true"></i>' . lang('Delete Selected') . '</button>';
}

// The first rows are printed with the page; the driver loads the rest.
$list_page = pg_list_table_page($list_source, array(), null);

if ($list_page === false) {
    output_error('Query failed.');
}

echo
pg_page_shell(
    array(
        'title'=> lang('Pages'),
        'extra classes'=>'page pg-measure-list',
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
            <form name="form" id="view_pages_form" action="edit_pages.php" method="post">
                ' . get_token_field() . '
                <input type="hidden" name="action" />
                <input type="hidden" name="selection" />
                <input type="hidden" name="list_params" value="' . h(encode_json(array('filter' => $filter))) . '" />
                <input type="hidden" name="send_to" value="' . h(get_request_uri()) . '" />
                ' . pg_list_table_html($list_source, $list_page, lang('Pages'), $output_bulk_buttons) . '
            </form>
        </div>
    </div>
    ' . pg_seo_render_detail_offcanvas() . '
    ' . pg_page_bulk_edit_dialog($user, 'view_pages_form') . '
</main>
<script>
    // The bulk actions post what the list has selected (edit_pages.php
    // resolves it with the list\'s own query and the user\'s rights).
    (function () {
        var root = document.querySelector(\'.pg-list-table[data-pg-list-table="view_pages"]\');
        var form = document.getElementById(\'view_pages_form\');
        var dialog = document.getElementById(\'page_bulk_edit_dialog\');

        if (root) {
            root.addEventListener(\'pg-list-table:selection\', function (event) {
                form.elements.selection.value = JSON.stringify(event.detail.payload);
            });
        }

        // Modify Selected: the dialog opens with every pick list empty, so
        // a choice left from an earlier opening is never posted.
        document.addEventListener(\'click\', function (event) {
            if (event.target.closest(\'[data-pg-page-bulk-open]\')) {
                dialog.querySelectorAll(\'select\').forEach(function (select) {
                    select.value = \'\';
                });
                pgUI.dialog.show(dialog);
            } else if (event.target.closest(\'[data-pg-page-bulk-save]\')) {
                form.elements.action.value = \'edit\';
                form.submit();
            }
        });
    }());
</script>
' . pg_js_bundle_tags('assets/js/pg_list_table.min.js') . '
' . ($user['role'] < 2 ? pg_designer_target_modal() : '') . '
' .
output_footer();

$liveform->remove_form('view_pages');