<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the file the panel never hands out (pg_file_withheld() in
 * includes/fn/files.php): dkim.key, the key that signs the site's mail, which
 * get_file.php never serves either. The file screens show no content for it,
 * leave it out of their archives and neither copy nor rename it; the
 * Workspace's file picker does not copy it into a channel, and the classic
 * page designer's file loader does not read it. Other .key and .pub files are
 * still shown. A basic user or a visitor may not give a file that name
 * (pg_file_name_reserved()), however it is spelled on the way in.
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
	$explorer = (string) file_get_contents(PG_FUNCTIONS_DIR . '/view_folder_and_files_f.php')
		. (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/archives.php')
		. (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_items.php')
		. (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/explorer/handle_zip.php');

	$doors = array(
		"case 'explorer_file_get':"                 => 'file_get_contents(',
		"case 'explorer_file_save':"                => '@rename(',
		'function pg_explorer_handle_rename('     => '@rename(',
		"case 'explorer_paste':"                    => '@copy(',
		'function pg_explorer_handle_zip_create(' => '->addFile(',
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

// Whether $asked comes before $acted in a section of source.
function file_withheld_test_asks_first($section, $asked, $acted)
{
	$asked = strpos($section, $asked);
	$acted = strpos($section, $acted);

	return ($asked !== false) && ($acted !== false) && ($asked < $acted);
}

// The doors outside the file screens: the Workspace's picker and the copy it
// posts into a channel (the file manager's "Send to a channel" and the
// paperclip), and the file loader of the classic page designer.
function test_file_withheld_is_asked_by_the_workspace_and_designer_doors()
{
	$library = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/workspace/library.php');

	pg_assert_true(file_withheld_test_asks_first(file_withheld_test_section($library, 'function ws_library_file_payload('), 'pg_file_withheld($name)', "'refused' =>"), 'the picker shows it as one that cannot be picked');
	pg_assert_true(file_withheld_test_asks_first(file_withheld_test_section($library, 'function ws_library_file('), "pg_file_withheld(\$row['name'])", "'ok' => true"), 'the picker does not hand it on');
	pg_assert_true(file_withheld_test_asks_first(file_withheld_test_section($library, 'function ws_library_send('), 'ws_library_file(', 'file_get_contents('), 'the copy is only read once the picker handed the file on');

	$content = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/panel/content.php');

	pg_assert_true(file_withheld_test_asks_first(file_withheld_test_section($content, 'function pg_panel_get_file('), "pg_file_withheld(\$file['name'])", 'file_get_contents('), 'the page designer\'s file loader does not read it');
}

// The mailer signs with whatever file is called dkim.key, so who may give a
// file that name is part of the rule.
function test_file_withheld_name_is_reserved_from_basic_users_and_visitors()
{
	pg_assert_true(pg_file_name_reserved('dkim.key', null), 'a visitor who is not signed in');
	pg_assert_true(pg_file_name_reserved('dkim.key', 3), 'a basic user');
	pg_assert_true(pg_file_name_reserved('DKIM.KEY', 3), 'whatever its case: a case-insensitive disk keeps it as the same file');
	pg_assert_true(pg_file_name_reserved('dkim.key', '3'), 'the role as the session row holds it');

	pg_assert_false(pg_file_name_reserved('dkim.key', 2), 'a manager may add it');
	pg_assert_false(pg_file_name_reserved('dkim.key', 1), 'a designer may add it');
	pg_assert_false(pg_file_name_reserved('dkim.key', 0), 'an administrator may add it');
	pg_assert_false(pg_file_name_reserved('server.key', 3), 'another .key file');
	pg_assert_false(pg_file_name_reserved('dkim.pub', null), 'the public half');
}

// prepare_file_name() asks the rule on the name as it is stored, after the
// transliteration that turns "dkım.key" into "dkim.key". The runner signs
// nobody in, so it is asked for a visitor here.
function test_file_withheld_prepare_file_name_asks_the_stored_name()
{
	if (defined('USER_LOGGED_IN') && USER_LOGGED_IN) {
		pg_assert_true(false, 'the runner was expected to have nobody signed in');

		return;
	}

	pg_assert_same('dkim.key', pg_ascii_file_name('dkım.key'), 'the dotless i becomes an i on the way');
	pg_assert_same('file', prepare_file_name('dkım.key'), 'a name that becomes dkim.key on the way');
	pg_assert_same('file', prepare_file_name('dkïm.key'), 'another letter that does');
	pg_assert_same('file', prepare_file_name('DKIM.KEY'), 'another case');
	pg_assert_same('file', prepare_file_name(' Dkım.Key '), 'both, with spaces around');
	pg_assert_same('file', prepare_file_name('dkim.key.'), 'a dot at the end, which Windows drops');
	pg_assert_same('dkim.pub', prepare_file_name('dkim.pub'), 'the public half keeps its name');
	pg_assert_same('my-dkim.key', prepare_file_name('my-dkim.key'), 'a different name keeps it');
	pg_assert_same('dkim_key.txt', prepare_file_name('dkim key.txt'), 'an ordinary name is prepared as before');
	pg_assert_same('file', prepare_file_name('dkim.key'), 'the name itself');
}
