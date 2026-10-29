<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Contrast of the File and Catalog Manager (view_folders.php): unpublished
 * names, tree counts, upload sizes, page marks, the rename preview and the
 * blue glyphs on a solid ink instead of a pastel tone or an opacity.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function file_manager_contrast_source()
{
	return (string) file_get_contents(dirname(__DIR__) . '/pinegrap/view_folders.php');
}

// The body of the inline rule whose selector list holds $selector, or ''.
function file_manager_contrast_rule($source, $selector)
{
	$pattern = '/^((?:[^{}\n]+,\n)*[^{}\n]+) \{([^}]*)\}/m';

	if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) === false) {
		return '';
	}

	foreach ($matches as $match) {
		$selectors = array_map('trim', explode(',', $match[1]));

		if (in_array($selector, $selectors, true)) {
			return $match[2];
		}
	}

	return '';
}

// Unpublished groups and products read the danger ink at full strength.
function test_file_manager_contrast_catalog_off()
{
	$source = file_manager_contrast_source();

	foreach (array('.explorer-item.catalog-off .item-name', '.explorer-item.catalog-off .list-name',
		'.explorer-item.catalog-off .item-icon', '.explorer-item.catalog-off .list-icon',
		'#explorer_tree_pane .tree-row.catalog-off .tree-label',
		'#explorer_tree_pane .tree-row.catalog-off > .bi') as $selector) {
		$rule = file_manager_contrast_rule($source, $selector);
		pg_assert_true(strpos($rule, 'color: var(--pg-danger-fg-emphasis)') !== false, $selector . ' reads the danger ink');
		pg_assert_true(strpos($rule, 'opacity') === false, $selector . ' is not faded');
	}

	pg_assert_true(file_manager_contrast_rule($source, '.explorer-item.catalog-off .item-card') === '',
		'an unpublished tile is not faded');
}

// Tree counts and empty ERP folders take the secondary text colour.
function test_file_manager_contrast_tree()
{
	$source = file_manager_contrast_source();

	foreach (array('.tree-count', '#explorer_tree_pane .erp-tree-row.is-empty .tree-label',
		'#explorer_tree_pane .erp-tree-row.is-empty > .bi') as $selector) {
		$rule = file_manager_contrast_rule($source, $selector);
		pg_assert_true(strpos($rule, 'color: var(--pg-fg-2)') !== false, $selector . ' reads the secondary text colour');
		pg_assert_true(strpos($rule, 'opacity') === false, $selector . ' is not faded');
	}
}

// A file too big or not allowed shows its reason in the danger ink.
function test_file_manager_contrast_upload_size()
{
	$source = file_manager_contrast_source();

	foreach (array('#upload_picked .upload-row.is-too-big .upload-size',
		'#upload_picked .upload-row.is-blocked .upload-size') as $selector) {
		pg_assert_true(strpos(file_manager_contrast_rule($source, $selector), 'color: var(--pg-danger-fg-emphasis)') !== false,
			$selector . ' reads the danger ink');
	}
}

// The short-link glyph and the list's sort caret read the primary ink.
function test_file_manager_contrast_blue_glyphs()
{
	$source = file_manager_contrast_source();

	foreach (array('.short-link-icon', '#explorer_list_table th .th-caret') as $selector) {
		pg_assert_true(strpos(file_manager_contrast_rule($source, $selector), 'color: var(--pg-primary-fg-emphasis)') !== false,
			$selector . ' reads the primary ink');
	}
}

// Off page marks and unchanged rename rows are drawn in a solid tone.
function test_file_manager_contrast_script_marks()
{
	$source = file_manager_contrast_source();

	pg_assert_true(strpos($source, "(on ? 'fg-success' : 'fg-3')") !== false, 'an off page mark reads the tertiary text colour');
	pg_assert_true(strpos($source, 'opacity-25') === false, 'no mark sits at quarter opacity');
	pg_assert_true(strpos($source, "(row.unchanged ? ' opacity-50' : '')") === false, 'an unchanged rename row is not faded');
	pg_assert_true(strpos($source, "(row.unchanged ? ' fg-2' : '')") !== false, 'an unchanged rename row prints its names in the secondary text colour');
}
