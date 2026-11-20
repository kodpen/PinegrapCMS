<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the "Pictures" group of the Translations screen: its owner
 * type, the bound that keeps a picture's texts to the pictures a page in
 * another language has shown (a file_translation row), the same bound in
 * "translate the whole site", the group's extraction and a row's picture.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

// The group is listed with the owner type of a picture's texts.
function test_picture_group_listed()
{
	pg_tr_load();

	$groups = pg_tr_owner_groups();

	pg_assert_true(isset($groups['pictures']), 'the Pictures group is listed');
	pg_assert_same(array('file'), $groups['pictures']['types']);
	pg_assert_same('bi-image', $groups['pictures']['icon']);
	pg_assert_same(lang('Images'), $groups['pictures']['label'], 'named "Images" ("Görseller"), as the group\'s note says "görsel"');
	pg_assert_same(array(array('file', '*')), pg_tr_scope_owners('group:pictures'), "a job's scope names every picture of the group");
}

// A picture no page in another language showed has no file_translation row
// and is neither counted nor sent: the group's owners are the files with a
// row, never every file with words.
function test_picture_group_bound()
{
	$bound = pg_tr_picture_owner_bound_sql(true);

	pg_assert_contains("u.owner_type = 'file'", $bound);
	pg_assert_contains('u.owner_id IN (SELECT ft.file_id FROM file_translation ft)', $bound, 'only files with a row');
	pg_assert_same('0 = 1', pg_tr_picture_owner_bound_sql(false), 'no picture before the table exists');

	// Without a database the table is not there: the group selects nothing.
	pg_assert_same('(0 = 1)', pg_tr_owner_where(pg_tr_scope_owners('group:pictures')));

	// Other groups' types are unchanged.
	pg_assert_same("(u.owner_type = 'product')", pg_tr_owner_where(array(array('product', '*'))));
}

// The whole site counts and sends the same pictures as the group, no more.
function test_picture_group_whole_site_bound()
{
	pg_assert_same('', pg_tr_picture_owner_or_sql(false), 'before the table, the whole site leaves pictures out');
	pg_assert_same(" OR (u.owner_type = 'file' AND u.owner_id IN (SELECT ft.file_id FROM file_translation ft))", pg_tr_picture_owner_or_sql(true));

	$store = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/store.php');
	pg_assert_contains("return \"u.owner_type NOT IN ('ui', 'file')\" . pg_tr_picture_owner_or();", $store, 'the whole site adds the bounded pictures');
}

// Every caller wraps the owner condition in parentheses, so the OR of the
// pictures never escapes into the rest of a WHERE.
function test_picture_group_callers_wrap_the_condition()
{
	foreach (array('includes/translate/jobs.php', 'includes/translate/store.php', 'includes/translate/screen.php', 'includes/translate/api.php', 'translations.php') as $file) {
		$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/' . $file);

		pg_assert_same(0, preg_match('/AND \$owner_where(?![\w)])/', $source), $file . ' never appends the condition bare');
	}
}

// The group's extraction reads the pictures with a row, and the whole site
// takes it as its third group.
function test_picture_group_extraction()
{
	$extract = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/translate/extract.php');

	pg_assert_contains("case 'pictures':\n            return pg_tr_extract_pictures();", $extract);
	pg_assert_contains("\$groups = array('catalog', 'forms', 'pictures');", $extract, 'the whole site extracts the pictures');
	pg_assert_contains('INNER JOIN (SELECT DISTINCT file_id FROM file_translation) ft ON ft.file_id = f.id', $extract, 'only pictures a page showed are read');
	pg_assert_contains("pg_picture_tr_forget(\$file_id, \$reason === 'binned');", $extract, 'a picture no longer live loses its texts and rows');
	pg_assert_same(array('owners' => 0, 'segments' => 0), pg_tr_extract_pictures(), 'nothing before the table exists');
}

// A row of the group names its pictures, read within the same bound.
function test_picture_group_row_files()
{
	pg_assert_same('', pg_tr_picture_files_sql(array(), '1 = 1'));

	$sql = pg_tr_picture_files_sql(array(7, '3', 7, 0), 'BOUND');
	pg_assert_contains('u.string_id IN (7,3)', $sql);
	pg_assert_contains('INNER JOIN files f ON f.id = u.owner_id', $sql);
	pg_assert_contains('AND (BOUND)', $sql, 'within the group bound');
	pg_assert_contains('ORDER BY f.name, f.id', $sql);

	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translations.php');
	pg_assert_contains("\$row_pictures = (\$group === 'pictures') ? pg_tr_picture_files(array_column(\$rows, 'id')) : array();", $screen);
	pg_assert_contains('data-pg-image-edit="', $screen, 'the picture opens in the Artifact Canvas');
	pg_assert_contains("(\$row_pictures ? pg_image_editor_includes() : '')", $screen, 'the editor loads only where a picture is listed');
}

// One current row: All pages only with no page and no group; a page link
// and All pages leave a chosen group.
function test_picture_group_one_current_row()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translations.php');

	pg_assert_contains("(((\$page_id === 0) && (\$group === '')) ? ' active\" aria-current=\"page' : '')", $screen, 'All pages is not current while a group is');
	pg_assert_contains("\$url(array('page_id' => 0, 'group' => '', 'p' => 0))", $screen, 'All pages leaves the group');
	pg_assert_contains("\$url(array('page_id' => \$pid, 'group' => '', 'p' => 0))", $screen, 'a page leaves the group');
}

// The empty group says when a picture is listed, never "press Update".
function test_picture_group_empty_wording()
{
	$screen = (string) file_get_contents(PG_FUNCTIONS_DIR . '/translations.php');

	pg_assert_contains("in_array(\$group_key, array('ui', 'pictures'), true) ? lang('None yet') : lang('Not scanned')", $screen);
	pg_assert_contains("lang('No picture yet. A picture made in the Artifact Canvas whose words are text layers is listed here once a page that shows it is saved in the Visual Page Editor or opened in another language.')", $screen);
	pg_assert_contains("((\$group === 'pictures') ? '<div class=\"small fg-secondary py-3 border-bottom\">", $screen, 'the group has a note of its own');
	pg_assert_contains("\$open_picture_label = lang('Edit this image in the Artifact Canvas');", $screen, 'the badge says what its press does');
}
