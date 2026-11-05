<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The "browse as a guest" flag ($_SESSION['software']['guest']) is set only
 * once a visitor picks Guest on the registration entrance screen. Every place
 * that asks for it about a guest-access folder (a file, a photo gallery, a
 * page's view access) reads it with a default, so a visitor who never picked
 * it raises no "Undefined array key" warning. get_file.php takes a name that
 * is a string only: an array (?name[]=x) is no file, not a TypeError.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_guest_session_flag_is_read_with_a_default()
{
	foreach (array('get_file.php', 'get_photo_gallery.php', 'includes/authentication.php') as $file) {
		$source = file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_true(strpos($source, "\$_SESSION['software']['guest'] ?? false") !== false, $file . ' reads the flag with a default');
		pg_assert_false((bool) preg_match('/\(\$_SESSION\[\'software\'\]\[\'guest\'\]\s*[!=]=/', $source), $file . ' has no bare read of the flag');
	}
}

function test_get_file_takes_a_string_name_only()
{
	$source = file_get_contents(PG_FUNCTIONS_DIR . '/get_file.php');

	pg_assert_true(strpos($source, "\$file['name'] = (isset(\$_GET['name']) && is_string(\$_GET['name'])) ? \$_GET['name'] : '';") !== false, 'the name is a string or nothing');
	pg_assert_false(strpos($source, "\$file['name'] = \$_GET['name'];") !== false, 'no bare read of the name');
}
