<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for pg_shortcuts_dialog() (includes/fn/output.php): the dialog the
 * ? key and the search box open with the keyboard shortcuts of the panel and
 * of the site's pages.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_shortcuts_dialog_is_one_modal_with_two_groups()
{
	$html = pg_shortcuts_dialog();

	pg_assert_same(1, substr_count($html, 'id="pg_shortcuts"'), 'one dialog');
	pg_assert_contains('aria-labelledby="pg_shortcuts_title"', $html, 'named by its title');
	pg_assert_same(2, substr_count($html, 'class="pg-keys-title"'), 'panel and pages');
	pg_assert_same(10, substr_count($html, 'class="pg-keys-row"'), 'ten shortcuts');
}

function test_shortcuts_dialog_marks_the_modifier_and_lists_its_own_key()
{
	$html = pg_shortcuts_dialog();

	pg_assert_contains('<kbd data-pg-mod>Ctrl</kbd><kbd>K</kbd>', $html, 'Ctrl is marked for the command key');
	pg_assert_contains('<dt><kbd>F1</kbd></dt>', $html, 'a key on its own is not marked');
	pg_assert_contains('<dt><kbd>?</kbd></dt>', $html, 'the key that opens the dialog');
	pg_assert_same(8, substr_count($html, 'data-pg-mod'), 'every Ctrl and nothing else');
}
