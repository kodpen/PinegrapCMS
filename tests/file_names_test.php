<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the name a file is stored under (prepare_file_name() in
 * includes/fn/files.php) where the file system decides what the name means:
 * Windows drops the dots at the end of a file name, so a name ending in a dot
 * is written over the file without it while its record keeps a name of its
 * own. No stored name ends in a dot. The rule for dkim.key is in
 * file_withheld_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_file_names_drop_trailing_dots()
{
	pg_assert_same('photo.jpg', prepare_file_name('photo.jpg.'), 'one dot');
	pg_assert_same('notes', prepare_file_name('notes...'), 'several');
	pg_assert_same('file', prepare_file_name('...'), 'nothing but dots');
	pg_assert_same('a.b.c', prepare_file_name('a.b.c'), 'dots inside the name stay');
	pg_assert_same('photo[1].jpg', prepare_file_name('photo[1].jpg'), 'the suffix of a name that is taken stays');
}

// The upload rule still defuses what it defused: the dot is dropped after it.
function test_file_names_drop_trailing_dots_after_the_upload_rule()
{
	pg_assert_same('shell_php', prepare_file_name('shell.php.'), 'a refused extension');
	pg_assert_same('file_htaccess', prepare_file_name('.htaccess.'), 'a refused name');
	pg_assert_same('web_config', prepare_file_name('web.config.'), 'the IIS settings file');
}

// The length limit cuts a name without a usable extension at 100 characters,
// which can leave a dot at the end.
function test_file_names_drop_a_trailing_dot_left_by_the_length_limit()
{
	$long = str_repeat('a', 99) . '.' . str_repeat('b', 10);

	pg_assert_same(str_repeat('a', 99), prepare_file_name($long), 'cut at the dot');
}

// Each letter of the transliteration map appears once; a ring above still
// becomes a plain a.
function test_file_names_ascii_map_keeps_the_ring_a()
{
	pg_assert_same('malmo.jpg', pg_ascii_file_name('malmö.jpg'), 'o with diaeresis');
	pg_assert_same('aland.jpg', pg_ascii_file_name('åland.jpg'), 'small a with ring above');
	pg_assert_same('Angstrom.png', pg_ascii_file_name('Ångström.png'), 'capital A with ring above');
}
