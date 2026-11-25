<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests that the 2026.11.0 screens fit a 390 px phone with long names: a
 * volunteer place's QR poster button shortens its name, and a queue place's
 * desk and screen rows draw their title as a block that ends in an ellipsis.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_hours_view.php');

function test_phone_fit_volunteer_poster_button_shortens_a_long_place()
{
	$place = 'Barınak ana bina, arka bahçe girişi (Moda Cad. 112)';
	$html = pg_volunteer_posters_html(array('id' => 4), array($place));

	pg_assert_true(strpos($html, 'class="pg-button pg-button-quiet pg-button-sm max-w-100"') !== false, 'the button is no wider than its card');
	pg_assert_true(strpos($html, '<span class="text-truncate">' . h($place) . '</span>') !== false, 'the name ends in an ellipsis');
	pg_assert_true(strpos($html, 'title="' . h($place) . '"') !== false, 'the whole name stays in the tooltip');
}

function test_phone_fit_queue_place_row_titles_are_blocks()
{
	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/edit_queue_place.php');

	pg_assert_same(0, substr_count($source, '<strong class="pg-list-row-title">'), 'an inline title neither wraps nor ends in an ellipsis');
	pg_assert_same(2, substr_count($source, '<strong class="pg-list-row-title d-block">'), 'the desk and screen rows draw a block title');
}

function test_phone_fit_lobby_past_number_stays_whole()
{
	// A long desk name beside it broke "A-004" after its hyphen.
	$page = (string) file_get_contents(PG_FUNCTIONS_DIR . '/queue_lobby.php');
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/queue_lobby.js');

	pg_assert_true(strpos($page, '<strong class="pg-tabular flex-grow-1 text-nowrap"') !== false, 'the page draws the number on one line');
	pg_assert_true(strpos($script, "element('strong', 'pg-tabular flex-grow-1 text-nowrap'") !== false, 'the 5-second redraw too');
}

function test_phone_fit_launch_step_blocker_pill_is_not_cut_off()
{
	// Inside the one-line title the ellipsis hid "Must fix" on a phone.
	$script = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/pg_dash_cards_launch.js');

	pg_assert_true(strpos($script, 'title.appendChild(el("span", "pg-pill pg-tone-rose", labels.blocker))') === false, 'the pill is not inside the title');
	pg_assert_true(strpos($script, 'head.appendChild(el("span", "pg-pill pg-tone-rose text-nowrap", labels.blocker))') !== false, 'the pill stands beside the title');
	pg_assert_true(preg_match('/var head = el\("div", "pg-list-row-head pg-list-row-head-wrap"\);\s*var title = el\("span", "pg-list-row-title", step\.name\);/', $script) === 1, 'the step head wraps the pill to a line of its own');
}
