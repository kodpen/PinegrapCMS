<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the agency summary's file lines (includes/fn/launch_summary.php):
 * a line's kind, the file ids its answer holds, the type read from a file's
 * first bytes (svg and scripts refused, whatever their name), the stored
 * name, the upload gate (an expired or withdrawn link, a text line, the 21st
 * file, a second file on a one-file line), the multiple field's entries, and
 * the schema step that adds answer_kind.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/launch_summary.php');

function test_launch_summary_kind_clean()
{
	pg_assert_same(0, pg_launch_summary_kind_clean(0));
	pg_assert_same(1, pg_launch_summary_kind_clean('1'));
	pg_assert_same(2, pg_launch_summary_kind_clean(2));
	pg_assert_same(0, pg_launch_summary_kind_clean(3), 'an unknown kind is text');
	pg_assert_same(0, pg_launch_summary_kind_clean('several'), 'a word is text');
	pg_assert_same(0, pg_launch_summary_kind_clean(null));

	pg_assert_same(0, pg_launch_summary_kind_files_max(0), 'a text line takes no file');
	pg_assert_same(1, pg_launch_summary_kind_files_max(1));
	pg_assert_same(20, pg_launch_summary_kind_files_max(2));
}

function test_launch_summary_file_ids()
{
	pg_assert_same(array(4, 9), pg_launch_summary_file_ids('[4,9]'));
	pg_assert_same(array(4, 9), pg_launch_summary_file_ids('[4,"9",4,0,-2,"x",1.5]'), 'positive whole ids, each once');
	pg_assert_same(array(), pg_launch_summary_file_ids('My logo is on the drive'), 'a text answer holds none');
	pg_assert_same(array(), pg_launch_summary_file_ids(null));
	pg_assert_same(array(), pg_launch_summary_file_ids('{"a":{"b":1}}'));
}

function test_launch_summary_file_type_reads_the_bytes()
{
	pg_assert_same('jpg', pg_launch_summary_file_type("\xFF\xD8\xFF\xE0\x00\x10JFIF", 'logo.png'), 'the bytes win over the name');
	pg_assert_same('png', pg_launch_summary_file_type("\x89PNG\r\n\x1A\n\x00\x00", 'logo'));
	pg_assert_same('gif', pg_launch_summary_file_type('GIF89a....', 'a.gif'));
	pg_assert_same('webp', pg_launch_summary_file_type('RIFF0000WEBPVP8 ', 'a.webp'));
	pg_assert_same('pdf', pg_launch_summary_file_type('%PDF-1.7', 'brief.pdf'));
	pg_assert_same('zip', pg_launch_summary_file_type("PK\x03\x04", 'photos.zip'));
	pg_assert_same('docx', pg_launch_summary_file_type("PK\x03\x04", 'texts.DOCX'), 'an office document in a zip keeps its kind');
	pg_assert_same('zip', pg_launch_summary_file_type("PK\x03\x04", 'run.php'), 'a zip named otherwise is a zip');
	pg_assert_same('xls', pg_launch_summary_file_type("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 'prices.xls'));
	pg_assert_same('', pg_launch_summary_file_type("\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 'setup.msi'), 'a compound file that is no office document');
}

function test_launch_summary_file_type_refuses_svg_and_scripts()
{
	pg_assert_same('', pg_launch_summary_file_type('<?xml version="1.0"?><svg', 'logo.svg'), 'svg is refused');
	pg_assert_same('', pg_launch_summary_file_type('<svg xmlns="http://www', 'logo.png'), 'svg named as a picture is refused');
	pg_assert_same('', pg_launch_summary_file_type('<?php echo 1; ?>', 'logo.jpg'), 'a script named as a picture is refused');
	pg_assert_same('', pg_launch_summary_file_type('<html><body>', 'brief.pdf'));
	pg_assert_same('', pg_launch_summary_file_type("MZ\x90\x00", 'tool.exe'));
	pg_assert_same('', pg_launch_summary_file_type('', 'empty.png'), 'an empty file is refused');
}

function test_launch_summary_file_name()
{
	pg_assert_same('Our-logo-0a1b2c3d.png', pg_launch_summary_file_name('Our logo.svg', 'png', '0a1b2c3d'), 'the extension is the one read from the bytes');
	pg_assert_same('shell-0a1b2c3d.jpg', pg_launch_summary_file_name('../../shell.php', 'jpg', '0a1b2c3d'), 'no path, no executable extension');
	pg_assert_same('Şirket-logosu-0a1b2c3d.pdf', pg_launch_summary_file_name('C:\\Users\\a\\Şirket logosu.pdf', 'pdf', '0a1b2c3d'));
	pg_assert_same('file-0a1b2c3d.zip', pg_launch_summary_file_name('.zip', 'zip', '0a1b2c3d'));
	pg_assert_same(60 + 1 + 8 + 4, mb_strlen(pg_launch_summary_file_name(str_repeat('a', 200) . '.pdf', 'pdf', '0a1b2c3d')), 'the name is cut');
	pg_assert_same(1, preg_match('/^logo-[0-9a-f]{8}\.png$/', pg_launch_summary_file_name('logo.png', 'png')), 'a random part is made');
	pg_assert_false(pg_launch_summary_file_name('logo.png', 'png') === pg_launch_summary_file_name('logo.png', 'png'), 'two sends of one name get two addresses');
}

function test_launch_summary_upload_gate_checks_the_link()
{
	pg_assert_same('', pg_launch_summary_upload_gate('open', 2, 0, 3));
	pg_assert_same('link', pg_launch_summary_upload_gate('expired', 2, 0, 1), 'an expired link takes no file');
	pg_assert_same('link', pg_launch_summary_upload_gate('withdrawn', 2, 0, 1));
	pg_assert_same('link', pg_launch_summary_upload_gate('invalid', 2, 0, 1));
}

function test_launch_summary_upload_gate_checks_the_line()
{
	pg_assert_same('kind', pg_launch_summary_upload_gate('open', 0, 0, 1), 'a text line takes no file');
	pg_assert_same('none', pg_launch_summary_upload_gate('open', 2, 0, 0), 'nothing chosen');
	pg_assert_same('', pg_launch_summary_upload_gate('open', 1, 0, 1));
	pg_assert_same('count', pg_launch_summary_upload_gate('open', 1, 1, 1), 'a one-file line holds one');
	pg_assert_same('count', pg_launch_summary_upload_gate('open', 1, 0, 2));
}

function test_launch_summary_upload_gate_refuses_the_21st_file()
{
	pg_assert_same('', pg_launch_summary_upload_gate('open', 2, 19, 1), 'the 20th file');
	pg_assert_same('count', pg_launch_summary_upload_gate('open', 2, 20, 1), 'the 21st file');
	pg_assert_same('count', pg_launch_summary_upload_gate('open', 2, 0, 21), 'twenty-one at once');
}

function test_launch_summary_uploads_from_a_multiple_field()
{
	$field = array(
		'name'     => array('a.png', '', 'b.pdf'),
		'tmp_name' => array('/tmp/1', '', '/tmp/3'),
		'size'     => array(10, 0, 30),
		'error'    => array(UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE, UPLOAD_ERR_INI_SIZE),
	);

	pg_assert_same(array(
		array('name' => 'a.png', 'tmp_name' => '/tmp/1', 'size' => 10, 'error' => UPLOAD_ERR_OK),
		array('name' => 'b.pdf', 'tmp_name' => '/tmp/3', 'size' => 30, 'error' => UPLOAD_ERR_INI_SIZE),
	), pg_launch_summary_uploads($field), 'an empty slot is left out, an oversize one kept for its message');

	pg_assert_same(array(), pg_launch_summary_uploads(null));
	pg_assert_same(array(), pg_launch_summary_uploads(array('name' => array(array('x')), 'tmp_name' => array(array('y')), 'size' => array(1), 'error' => array(0))), 'a nested field is not a file');
	pg_assert_same(1, count(pg_launch_summary_uploads(array('name' => 'a.png', 'tmp_name' => '/tmp/1', 'size' => 10, 'error' => 0))), 'a single field');
}

function test_launch_summary_answer_kind_schema_step()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/migrations/2026.11.0.php');

	pg_assert_same(1, preg_match('/\tupgrade_2026_11_0_launch_summary_answer_kind\(\);\s+\/\/ 11\.0\.278\n/', $source), 'called once under its label');
	pg_assert_same(1, substr_count($source, "\tupgrade_2026_11_0_launch_summary_answer_kind();"));

	preg_match('/function upgrade_2026_11_0_launch_summary_answer_kind\(\) \{(.*?)\n\}\n/s', $source, $m);
	$step = isset($m[1]) ? $m[1] : '';

	pg_assert_same(1, substr_count($step, "install_add_column('launch_summary_lines', 'answer_kind', \"TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER line_text\");"), 'the decided column, existing rows 0');
	pg_assert_same(0, preg_match('/db\(\s*"ALTER/i', $step), 'no hand-written ALTER');
}

function test_launch_summary_text_answer_refuses_a_file_line_in_source()
{
	// pg_launch_summary_answer_save() needs a database; its kind check is
	// read from its body.
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/launch_summary.php');
	preg_match('/function pg_launch_summary_answer_save\(\$id, \$answer\)\s*\{(.*?)\n\}\n/s', $source, $m);

	pg_assert_same(1, preg_match("/!== PG_LAUNCH_SUMMARY_KIND_TEXT\) \{\s+return 'kind';/", isset($m[1]) ? $m[1] : ''), 'a file line takes no text');
}
