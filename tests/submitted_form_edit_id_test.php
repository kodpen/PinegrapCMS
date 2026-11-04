<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * edit_submitted_form.php reads, saves and deletes the record whose access it
 * checked: the check took $_REQUEST['id'] and the writes $_POST['id'], so
 * where $_REQUEST also carries cookies (no request_order set) a cookie named
 * id passed the check for one record while the post edited or deleted another.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_edit_submitted_form_uses_the_checked_id_only()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/edit_submitted_form.php');

	pg_assert_contains("\$id = is_scalar(\$_REQUEST['id'] ?? null) ? (int) \$_REQUEST['id'] : 0;", $source, 'the id is read once');
	pg_assert_same(2, substr_count($source, "\$_REQUEST['id']"), 'only the one read takes $_REQUEST id');
	pg_assert_same(false, strpos($source, "\$_POST['id']") !== false, 'no posted id read after the check');
	pg_assert_same(false, strpos($source, "\$_GET['id']") !== false, 'no query-string id read after the check');
	pg_assert_contains('$deleted_files = pg_submitted_form_delete($id);', $source, 'the delete removes the checked record');
}
