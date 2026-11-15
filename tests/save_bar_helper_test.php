<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests pg_save_bar() (includes/fn/save_bar.php): the framed column around
 * the buttons, its options, the stylesheet's frame for .pg-savebar, and that
 * no panel screen draws a bare sticky button row any more.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/save_bar.php');

function test_save_bar_helper_frames_the_buttons()
{
	$html = pg_save_bar('<button type="submit" class="btn">Save</button>');

	pg_assert_contains('<div class="col-12 order-3 position-sticky pg-savebar" style="bottom:0; z-index:3;">', $html, 'sticky order-3 column with the frame class');
	pg_assert_contains('<nav class="buttons navigation text-center mb-7" aria-label="', $html, 'the shared bar, not sticky itself');
	pg_assert_contains('<div class="btn-group flex-wrap justify-content-center"><button type="submit" class="btn">Save</button>', $html, 'buttons in the default group, as given');
	pg_assert_true(strpos($html, 'bottom:.5rem') === false, 'no bare sticky offset on the bar');
	pg_assert_true(substr_count($html, '<div') === substr_count($html, '</div>'), 'boxes balanced');
}

function test_save_bar_helper_options()
{
	$html = pg_save_bar('<a class="btn">X</a>', array('group' => '', 'id' => 'my"bar', 'hidden' => true, 'label' => 'Reorder & go', 'after' => '<p id="status"></p>'));

	pg_assert_contains('id="my&quot;bar"', $html, 'id escaped');
	pg_assert_contains('style="display:none"', $html, 'hidden by an inline display for .show()');
	pg_assert_contains('aria-label="Reorder &amp; go"', $html, 'label escaped');
	pg_assert_contains('<div class="container"><a class="btn">X</a><p id="status"></p>', $html, 'no group box, the after markup follows the buttons');
	pg_assert_true(strpos($html, 'btn-group') === false, 'empty group prints no box');

	$plain = pg_save_bar('<a class="btn">X</a>', array('group' => 'd-flex gap-3'));

	pg_assert_contains('<div class="d-flex gap-3"><a class="btn">X</a>', $plain, 'own group classes');
	pg_assert_true(strpos($plain, 'display:none') === false, 'shown by default');
}

function test_save_bar_helper_frame_rules_exist()
{
	$css = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/css/backend.src.css');

	foreach (array(
		'.pg-savebar > nav.buttons.navigation,',
		'.pg-savebar > nav.buttons.navigation > .container,',
		'body:has(#pg-chat-root) .pg-savebar > nav.buttons.navigation > .container,',
		'.pg-savebar > nav.buttons.navigation .btn-solid:is(.theme-success, .theme-primary),',
		'.pg-savebar > nav.buttons.navigation .btn.theme-danger,',
	) as $selector) {
		pg_assert_contains($selector, $css, $selector . ' is drawn');
	}

	// The column keeps the bar's 16 px bottom margin inside it, so the bar
	// floats above the window's edge on screens where it is not a flex item.
	pg_assert_contains(".pg-savebar {\n  display: flow-root;\n}", $css, 'the column holds the bar\'s bottom margin');
}

function test_save_bar_helper_no_bare_sticky_rows()
{
	// edit_offer stays as it is (an owner's kept screen); the settings page
	// frames its own bar (#pg_settings_page_form > nav).
	$kept = array('includes/templates/edit_offer.php', 'includes/settings/screen.php');
	$files = array_merge(glob(PG_FUNCTIONS_DIR . '/*.php'), glob(PG_FUNCTIONS_DIR . '/includes/*/*.php'));

	foreach ($files as $file) {
		$relative = substr($file, strlen(PG_FUNCTIONS_DIR) + 1);

		if (in_array($relative, $kept, true)) {
			continue;
		}

		$source = (string) file_get_contents($file);

		pg_assert_true(preg_match('/<nav class="buttons navigation[^"]*position-sticky[^"]*"[^>]*bottom:\.5rem/', $source) !== 1, $relative . ' draws its sticky bar through pg_save_bar()');
	}
}
