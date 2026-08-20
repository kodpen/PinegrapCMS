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

// HTML fragment for the SEO detail panel on the list screens. Fetched into
// an offcanvas, so it renders no page frame - only the heading, the score
// checklist and an edit link.

include('init.php');
require_once(dirname(__FILE__) . '/seo.php');
require_once(dirname(__FILE__) . '/seo_structure.php');

$user = validate_user();
validate_area_access($user, 'user');
validate_token_field();

$type = $_GET['type'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if (!in_array($type, array('page', 'product', 'product_group')) || !$id) {
    exit(lang('Sorry, we could not find that record.'));
}

// Authorization is per record, not per screen. Without this the id is an
// enumeration handle: the panel reports the record's name, its checklist and
// its stored structure findings - whose detail field carries up to 250
// characters of real page content - and it recalculates, so a GET here also
// writes. The list screens this panel is opened from already gate on exactly
// these two checks.
if ($type == 'page') {
    $seo_page_folder = db_value("SELECT page_folder FROM page WHERE page_id = '" . (int) $id . "'");

    if (($seo_page_folder === NULL) || !check_edit_access($seo_page_folder)) {
        log_activity(lang('access denied to SEO detail because user does not have access to page'), $_SESSION['sessionusername']);
        exit(lang('Access denied.'));
    }
} else {
    validate_ecommerce_access($user);
}

// The panel is the one place freshness is worth a synchronous computation:
// one record, a handful of queries, and the operator is explicitly asking
// for its current state.
if (pg_seo_schema_ready()) {
    pg_seo_recalculate($type, array($id));
}

if ($type == 'page') {
    $record = db_item(
        "SELECT
            page_id AS id,
            page_name AS name,
            seo_score,
            " . (pg_seo_schema_ready() ? "seo_flags," : "'0' AS seo_flags,") . "
            seo_analysis,
            seo_analysis_current
        FROM page
        WHERE page_id = '" . (int) $id . "'");
    $edit_url = 'edit_page.php?id=' . (int) $id;
} elseif ($type == 'product') {
    $record = db_item(
        "SELECT
            id,
            name,
            seo_score,
            " . (pg_seo_schema_ready() ? "seo_flags," : "'0' AS seo_flags,") . "
            seo_analysis,
            seo_analysis_current
        FROM products
        WHERE id = '" . (int) $id . "'");
    $edit_url = 'edit_product.php?id=' . (int) $id;
} else {
    $record = db_item(
        "SELECT
            id,
            name,
            seo_score,
            " . (pg_seo_schema_ready() ? "seo_flags," : "'0' AS seo_flags,") . "
            seo_analysis,
            seo_analysis_current
        FROM product_groups
        WHERE id = '" . (int) $id . "'");
    $edit_url = 'edit_product_group.php?id=' . (int) $id;
}

if (!$record) {
    exit(lang('Sorry, we could not find that record.'));
}

echo
    '<div class="d-flex align-items-center mb-2">
        <strong class="text-truncate">' . h($record['name']) . '</strong>
        <a class="btn btn-sm btn-outline-primary ms-auto" href="' . h($edit_url) . '"><i class="bi bi-pencil me-1"></i>' . lang('Edit') . '</a>
    </div>'
    . pg_seo_render_checklist($record, $type, $id);
