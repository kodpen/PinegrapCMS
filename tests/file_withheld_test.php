<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the file the panel never hands out (pg_file_withheld() in
 * includes/fn/files.php): dkim.key, the key that signs the site's mail, which
 * get_file.php never serves either. The file screens show no content for it,
 * leave it out of their archives and neither copy nor rename it; other .key
 * and .pub files are still shown.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

function test_file_withheld_is_the_dkim_key()
{
	pg_assert_true(pg_file_withheld('dkim.key'), 'the signing key');
	pg_assert_true(pg_file_withheld('DKIM.KEY'), 'whatever its case');
	pg_assert_true(pg_file_withheld('Dkim.Key'), 'mixed case');
}

function test_file_withheld_leaves_other_keys_viewable()
{
	pg_assert_false(pg_file_withheld('dkim.pub'), 'the public half is published in DNS anyway');
	pg_assert_false(pg_file_withheld('server.key'), 'another .key file');
	pg_assert_false(pg_file_withheld('my-dkim.key'), 'a different name');
	pg_assert_false(pg_file_withheld('dkim.key.txt'), 'a different extension');
	pg_assert_false(pg_file_withheld(''), 'no name');
	pg_assert_false(pg_file_withheld(null), 'no record');

	pg_assert_true(in_array('key', pg_explorer_viewable_formats(), true), '.key files stay viewable');
	pg_assert_true(in_array('pub', pg_explorer_viewable_formats(), true), '.pub files stay viewable');
}

// The source of one explorer action (from its case label to the next one) or
// of one function (to the next top-level function).
function file_withheld_test_section($source, $start)
{
	$from = strpos($source, $start);

	if ($from === false) {
		return '';
	}

	$next = (strpos($start, 'case ') === 0)
		? preg_match('/\n\s*case \'explorer_[a-z_]+\':/', $source, $match, PREG_OFFSET_CAPTURE, $from + strlen($start))
		: preg_match('/\nfunction [a-z_]+\(/', $source, $match, PREG_OFFSET_CAPTURE, $from + strlen($start));

	return $next ? substr($source, $from, $match[0][1] - $from) : substr($source, $from);
}

// Every door of the file screens that hands out a file's bytes, copies it or
// gives it another name asks the rule before it does so; a new door has to as
// well.
function test_file_withheld_is_asked_by_the_file_doors()
{
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php');

	$doors = array(
		"case 'explorer_file_get':"                 => 'file_get_contents(',
		"case 'explorer_file_save':"                => '@rename(',
		"case 'explorer_rename':"                   => '@rename(',
		"case 'explorer_paste':"                    => '@copy(',
		"case 'explorer_zip_create':"               => '->addFile(',
		'function pg_explorer_copy_folder('         => '@copy(',
		'function pg_explorer_zip_add_folder('      => '->addFile('
	);

	foreach ($doors as $door => $act) {

		$section = file_withheld_test_section($explorer, $door);
		$asked = strpos($section, "pg_file_withheld(\$file['name'])");
		$acted = strpos($section, $act);

		pg_assert_true($section !== '' && $acted !== false, $door . ' is found');
		pg_assert_true($asked !== false && $asked < $acted, $door . ' asks pg_file_withheld() before ' . $act);

	}

	$edit_file = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_file.php');

	pg_assert_true(preg_match('/if \(pg_file_withheld\(\$file_name\)\) \{\s*\$viewable_formats = \$editable_formats;\s*\}\s*\/\/ Get file content/', $edit_file) === 1, 'edit_file.php reads no content for it');

	$asks_first = function ($section, $asked, $acted) {
		$asked = strpos($section, $asked);
		$acted = strpos($section, $acted);

		return ($asked !== false) && ($acted !== false) && ($asked < $acted);
	};

	pg_assert_true($asks_first(file_withheld_test_section($edit_file, '// SAVE / SAVE & RETURN'), "pg_file_withheld(\$row['name'])", '@rename('), 'edit_file.php does not rename it');
	pg_assert_true($asks_first(file_withheld_test_section($edit_file, '// DUPLICATE'), 'pg_file_withheld($original_name)', 'copy(FILE_DIRECTORY_PATH'), 'edit_file.php does not copy it');
}
