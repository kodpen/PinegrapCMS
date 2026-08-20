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

/**
 * Row and bulk actions for the variant set list.
 *
 * One entry point for enable / disable / delete, whether the operator clicked a
 * single row or ticked twenty. A per-row path and a bulk path written
 * separately is how the two end up answering differently for the same
 * user — see CLAUDE.md, "Sipariş İptal — Tek Akış + Onarım (2026.1.29)", where
 * exactly that produced a row button that silently did nothing while the bulk
 * button worked.
 *
 * Development screen. view_product_groups.php and edit_product_group.php are
 * untouched.
 */

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);

include_once('liveform.class.php');
include_once('product_builder.php');

validate_token_field();

$liveform = new liveform('view_products');

$back = PATH . SOFTWARE_DIRECTORY . '/view_products.php?mode=variant_sets';

$action = isset($_POST['action']) ? $_POST['action'] : '';

// The ids come from the browser, so every one of them is re-checked against
// "is this actually a variant set" before anything is written. Without that,
// a crafted post reaches any product group, including browse categories that
// this screen never lists.
$group_ids = array();

if (!empty($_POST['group_ids']) && is_array($_POST['group_ids'])) {

    foreach ($_POST['group_ids'] as $group_id) {

        $group_id = (int) $group_id;

        if (!$group_id) {
            continue;
        }

        $is_variant_set = db_value(
            "SELECT COUNT(*)
            FROM product_groups
            WHERE id = '$group_id' AND display_type = 'select'");

        if ($is_variant_set) {
            $group_ids[] = $group_id;
        }
    }
}

if (!$group_ids) {
    $liveform->mark_error('', lang('Please select at least one item.'));
    go($back);
}

switch ($action) {

    /* ------------------------------------------------- enable / disable */

    case 'status':

        $status = (isset($_POST['status']) && $_POST['status'] === 'enabled') ? 'enabled' : 'disabled';

        require_once(dirname(__FILE__) . '/update_product_group_status.php');

        foreach ($group_ids as $group_id) {
            // This helper already walks child groups and the products inside
            // them, which is exactly what a variant set needs: disabling the
            // set has to disable the variants or the catalog keeps selling
            // them.
            update_product_group_status(array('id' => $group_id, 'status' => $status));
        }

        log_activity(
            lang(array(
                'string' => '{var:1} variant set{suffix:1} were updated',
                'vars'   => array(count($group_ids)),
                'suffix' => (count($group_ids) === 1) ? '' : 's')),
            $_SESSION['sessionusername']);

        $liveform->add_notice(
            lang(array(
                'string' => '{var:1} variant set{suffix:1} were updated',
                'vars'   => array(count($group_ids)),
                'suffix' => (count($group_ids) === 1) ? '' : 's')));

        break;

    /* -------------------------------------------------------- deleting */

    case 'delete':

        $deleted_sets     = 0;
        $deleted_products = 0;
        $refused          = 0;

        foreach ($group_ids as $group_id) {

            $result = pg_pb_delete_variant_set($group_id, TRUE);

            if ($result['group']) {
                $deleted_sets++;
                $deleted_products += $result['products'];
                log_activity(
                    lang(array('string' => 'product group ({var:1}) was deleted', 'vars' => array($result['name']))),
                    $_SESSION['sessionusername']);
            } else {
                $refused++;
            }
        }

        if ($deleted_sets) {
            $liveform->add_notice(
                lang(array(
                    'string' => '{var:1} variant set{suffix:1} and {var:2} product{suffix:2} were deleted.',
                    'vars'   => array($deleted_sets, $deleted_products),
                    'suffix' => array(($deleted_sets === 1) ? '' : 's', ($deleted_products === 1) ? '' : 's'))));
        }

        // Silence here would read as "it worked". The root group refusal is
        // inherited from edit_product_group.php and the operator has to be told
        // which of their selections did nothing.
        if ($refused) {
            $liveform->mark_error('', lang('This product group could not be deleted because it is the root product group.'));
        }

        break;

    default:
        $liveform->mark_error('', lang('Page not found.'));
}

go($back);
