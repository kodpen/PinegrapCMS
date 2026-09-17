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

// Downloading products as a file, from the File Manager rather than from the
// products screen's own filter bar.
//
// It writes nothing itself: the table comes from export_products_f.php, which
// is the same table the products screen exports, so the two can never differ.
// What this address adds is the choice of which products -- all of them, or
// the ones somebody has picked out of the grid, products and whole product
// groups alike -- and the choice of file.
//
// A POST rather than a link, for two reasons: a selection of several thousand
// products does not fit in a query string, and a download that changes nothing
// still ought to be asked for with a token.

ini_set('max_execution_time', '900');

include('init.php');

$user = validate_user();
validate_ecommerce_access($user);
validate_token_field();

require_once(dirname(__FILE__) . '/export_products_f.php');

$format = (isset($_POST['format']) && ($_POST['format'] == 'xlsx')) ? 'xlsx' : 'csv';
$scope = isset($_POST['scope']) ? (string) $_POST['scope'] : 'all';

$where = '';
$name = 'products';

// The ids of one field, as integers, ignoring anything that is not one. The
// grid posts a comma separated list because a selection of several thousand
// rows is a list and nothing more.
function pg_export_id_list($raw)
{
    $out = array();

    foreach (explode(',', (string) $raw) as $id) {

        $id = (int) trim($id);

        if (($id > 0) && (!in_array($id, $out))) {
            $out[] = $id;
        }
    }

    return $out;
}

// Anything other than "all" is a selection out of the grid: some products,
// some product groups, or both at once. A product stands for itself; a group
// stands for everything under it, because browsing a group shows its direct
// products but exporting one is asked for by somebody who means the branch.
if ($scope != 'all') {

    $ids = pg_export_id_list(isset($_POST['ids']) ? $_POST['ids'] : '');
    $group_ids = pg_export_id_list(isset($_POST['group_ids']) ? $_POST['group_ids'] : '');

    if ((count($ids) == 0) && (count($group_ids) == 0)) {
        output_error(lang('Nothing is selected.') . ' <a href="javascript:history.go(-1)">' . lang('Go back') . '</a>.');
    }

    $parts = array();

    if (count($ids) > 0) {
        $parts[] = 'id IN (' . implode(',', $ids) . ')';
    }

    if (count($group_ids) > 0) {

        require_once(dirname(__FILE__) . '/view_folder_and_files_f.php');

        $deep = array();

        foreach ($group_ids as $group_id) {

            $deep[$group_id] = true;

            foreach (pg_catalog_subtree_ids($group_id) as $child) {
                $deep[(int) $child] = true;
            }
        }

        $parts[] = 'id IN (
            SELECT product
            FROM products_groups_xref
            WHERE product_group IN (' . implode(',', array_keys($deep)) . '))';
    }

    $where = 'WHERE (' . implode(' OR ', $parts) . ')';
    $name = 'products-selected';

    // One group on its own is named after the group: an operator exporting a
    // single branch wants to know which one the file holds without opening it.
    if ((count($ids) == 0) && (count($group_ids) == 1)) {

        $group_name = db_value("SELECT name FROM product_groups WHERE id = '" . e($group_ids[0]) . "'");

        // Only what a file name cannot hold is taken out. The accents stay:
        // the header carries the name twice, and the form that keeps them is
        // the one the browser uses (see pg_products_export_disposition).
        if ($group_name != '') {
            $name = 'products-' . preg_replace('#[/\\\\:*?"<>|\x00-\x1F]+#u', '_', (string) $group_name);
        }
    }
}

$table = pg_products_export_table($where, 'name ASC');

log_activity(
    lang(array('string' => '{var:1} product(s) were exported', 'vars' => count($table['rows']))),
    $_SESSION['sessionusername']);

if ($format == 'xlsx') {
    pg_products_export_xlsx($table, $name . '.xlsx');
} else {
    pg_products_export_csv($table, $name . '.csv');
}

exit();
