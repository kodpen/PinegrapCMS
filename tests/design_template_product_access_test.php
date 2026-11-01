<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the access a design template's product gives once its order is
 * paid (_pg_tpl_product_access() in includes/fn/designer.php): a product
 * that names a folder of the template grants that folder, for its days or
 * with no end; a folder key the template did not make grants nothing; a
 * product with membership days renews the membership; a plain product gets
 * no access columns at all. The template folders accept the membership
 * access, and the folders are made before the catalog that names them.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_tpl_product_access_grants_template_folder()
{
	$folders = array('course_a' => 41, 'members' => 42);
	pg_assert_same(
		array('grant_private_access' => '1', 'private_folder' => 41, 'private_days' => 0),
		_pg_tpl_product_access(array('sku' => 'C-A', 'private_folder' => 'course_a'), $folders),
		'a course grants its folder with no end');
	pg_assert_same(
		array('grant_private_access' => '1', 'private_folder' => 41, 'private_days' => 365),
		_pg_tpl_product_access(array('private_folder' => 'course_a', 'private_days' => '365'), $folders),
		'the days are kept as a number');
	pg_assert_same(0,
		_pg_tpl_product_access(array('private_folder' => 'course_a', 'private_days' => -5), $folders)['private_days'],
		'negative days are no end');
}

function test_tpl_product_access_unknown_folder_and_plain_product()
{
	pg_assert_same(array(), _pg_tpl_product_access(array('private_folder' => 'missing'), array('course_a' => 41)), 'unknown folder key grants nothing');
	pg_assert_same(array(), _pg_tpl_product_access(array('private_folder' => 'course_a'), array('course_a' => 0)), 'a folder not made grants nothing');
	pg_assert_same(array(), _pg_tpl_product_access(array('sku' => 'X', 'shippable' => false), array('course_a' => 41)), 'plain product: no access columns');
}

function test_tpl_product_access_membership()
{
	pg_assert_same(array('membership_renewal' => 365), _pg_tpl_product_access(array('membership_days' => 365), array()), 'membership days');
	pg_assert_same(array(), _pg_tpl_product_access(array('membership_days' => 0), array()), 'zero days renew nothing');
	pg_assert_same(
		array('grant_private_access' => '1', 'private_folder' => 7, 'private_days' => 30, 'membership_renewal' => 30),
		_pg_tpl_product_access(array('private_folder' => 'k', 'private_days' => 30, 'membership_days' => 30), array('k' => 7)),
		'both at once');
}

// Source reading: the folders accept membership access, and prepare makes
// them before the catalog, which receives them.
function test_tpl_folders_before_catalog_and_membership_access()
{
	$src = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/designer.php');
	pg_assert_true(strpos($src, "array('public', 'private', 'registration', 'membership')") !== false, 'membership folder access accepted');
	$folders = strpos($src, '$folders = _pg_tpl_folders($tpl');
	$catalog = strpos($src, '$catalog = _pg_tpl_catalog($tpl, (int)$user[\'id\'], $root_group, $folders)');
	pg_assert_true($folders !== false && $catalog !== false && $folders < $catalog, 'folders made before the catalog and passed to it');
}
