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
$user = validate_user();
validate_area_access($user, 'designer');

// The page is one value; one sent as an array (?page_id[]=x) counts as not
// given: it ended in an error page.
pg_request_single_values(array('page_id'));

$form = new liveform('generate_layout');

$page = db_item("SELECT page_id AS id, page_name AS name, page_type AS type, page_style AS style_id, layout_type FROM page WHERE page_id = '" . e($_GET['page_id'] ?? '') . "'");

// Opened without a page, or for one that no longer exists (stale link,
// hand-typed URL): a "not found" screen instead of PHP notices and an empty
// layout.
if (!$page) {
    output_error(lang('Record not found.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

// A type without a layout template (a standard page, say) has nothing to
// generate; Edit Layout refuses it the same way.
if (!check_if_page_type_supports_layout($page['type'])) {
    output_error(lang('This page does not use a custom layout.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.', 404);
}

require('generate_layout_content.php');

$layout_code = generate_layout_content($_GET['page_id']);
$form->set('layout', "\n" . $layout_code);

$edit_page_url = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/edit_page.php?id=' . (int) $page['id'];

// The Summary: what the code was generated for and how long it is.
$layout_lines = ($layout_code === '') ? 0 : substr_count($layout_code, "\n") + 1;

// A page that already draws a custom layout gets Edit Layout in the save
// bar, the same target as Edit Page's link; a page on the system layout has
// none to paste into until its layout type is switched.
$edit_layout_url = '';
if ($page['layout_type'] === 'custom') {
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/layouts.php');
    $edit_layout_url = pg_layout_edit_url(
        $page['id'],
        $page['style_id'],
        pg_page_is_visual_design(array('page_style' => $page['style_id'])),
        USER_ROLE,
        pg_hosted());
}

echo pg_page_shell([
        'title'=> h($page['name']) . ' | ' . lang('Generate Layout'),
        'extra classes'=>'design',
        'icon'=>'design',
        'heading'=>lang('Generate Layout'),
        'cancel'=>array('enable' => 'true', 'url' => $edit_page_url),
        'breadcrumb' => array(
            array('label' => lang('Pages'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_pages.php'),
            array('label' => $page['name'], 'url' => $edit_page_url),
            array('label' => lang('Generate Layout')),
        ),
    ]);

require('includes/templates/generate_layout.php');

echo output_footer();

$form->remove();