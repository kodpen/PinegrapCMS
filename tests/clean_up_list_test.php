<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_clean_up_list_row() in includes/fn/files.php: one line of the
 * Cleanup tool's list of what it is about to delete.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// A file left in data/files outside the uploader (FTP, an unpacked archive)
// keeps whatever name it was given; markup in it is printed as text.
function test_clean_up_list_row_escapes_the_name()
{
	$row = pg_clean_up_list_row('insert_drive_file', '<img src=x onerror=alert(1)>.jpg');

	pg_assert_false(strpos($row, '<img') !== false, 'no tag');
	pg_assert_true(strpos($row, '&lt;img src=x onerror=alert(1)&gt;.jpg') !== false, 'name as text');
}

// The icon names the Material icon; the row keeps its classes.
function test_clean_up_list_row_markup()
{
	pg_assert_same(
		'<li class="list-group-item bg-reset border-0 fg-danger"><span class="material-icons me-5">folder</span>assets/lazy</li>',
		pg_clean_up_list_row('folder', 'assets/lazy'),
		'row'
	);
}
