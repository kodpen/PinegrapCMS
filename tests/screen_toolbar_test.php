<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * pg_screen_toolbar(): the toolbar under a panel screen's heading, drawn from
 * its slots in the fixed order, nothing at all when no slot is filled, and
 * every label and attribute it builds itself escaped.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

function test_screen_toolbar_empty_slots_draw_nothing()
{
	pg_assert_same('', pg_screen_toolbar(array()), 'no slots');
	pg_assert_same('', pg_screen_toolbar(array('primary' => '', 'secondary' => array(), 'grow' => '', 'search' => null, 'view' => array('', null), 'overflow' => '')), 'empty slots');
	pg_assert_same('', pg_screen_toolbar(array('primary' => array('label' => '')), array('label' => 'x')), 'button without a label');
}

function test_screen_toolbar_is_the_button_bar_component()
{
	$html = pg_screen_toolbar(array('primary' => '<a class="btn" href="a.php">A</a>'));
	pg_assert_contains('<nav id="button_bar" class="pg-toolbar"', $html, 'nav#button_bar.pg-toolbar');
	pg_assert_contains('aria-label="', $html, 'named landmark');
	pg_assert_contains('<a class="btn" href="a.php">A</a>', $html, 'markup slot kept as given');
	pg_assert_same(1, substr_count($html, '</nav>'), 'one bar');
}

function test_screen_toolbar_slot_order()
{
	// Passed in reverse; drawn primary, secondary, grow, search, view, overflow.
	$html = pg_screen_toolbar(array(
		'overflow'  => '<i>overflow</i>',
		'view'      => '<i>view</i>',
		'search'    => '<i>search</i>',
		'grow'      => '<i>grow</i>',
		'secondary' => '<i>secondary</i>',
		'primary'   => '<i>primary</i>',
	));

	$last = -1;
	foreach (array('primary', 'secondary', 'grow', 'search', 'view', 'overflow') as $slot) {
		$at = strpos($html, '<i>' . $slot . '</i>');
		pg_assert_true($at !== false && $at > $last, $slot . ' in its place');
		$last = $at;
	}
	pg_assert_contains('<div class="pg-toolbar-grow"><i>grow</i></div>', $html, 'grow wrapped');
}

function test_screen_toolbar_right_cluster_gets_a_spacer()
{
	// A search with nothing before it still sits at the right end.
	$html = pg_screen_toolbar(array('search' => '<i>search</i>'));
	pg_assert_contains('<div class="pg-toolbar-grow"></div><i>search</i>', $html, 'spacer before the right cluster');

	$html = pg_screen_toolbar(array('primary' => '<i>p</i>'));
	pg_assert_false(strpos($html, 'pg-toolbar-grow') !== false, 'no spacer without a right cluster');
}

function test_screen_toolbar_button_recipes()
{
	$html = pg_screen_toolbar(array(
		'primary'   => array('label' => 'New', 'url' => 'add.php', 'icon' => 'bi-plus-lg'),
		'secondary' => array(
			array('label' => 'Import', 'url' => 'import.php'),
			'<a class="btn" href="x.php">X</a>',
		),
	));

	pg_assert_contains('<a class="btn btn-sm btn-solid theme-primary rounded-pill px-5" href="add.php"><i class="bi bi-plus-lg me-2" aria-hidden="true"></i>New</a>', $html, 'primary recipe');
	pg_assert_contains('<a class="btn btn-sm btn-text theme-secondary" href="import.php">Import</a><a class="btn" href="x.php">X</a>', $html, 'secondary recipe, list order kept');
}

function test_screen_toolbar_escaping()
{
	$html = pg_screen_toolbar(
		array('secondary' => array('label' => '<b>"A&B"</b>', 'url' => 'x.php?a=1&b="2"', 'icon' => 'bi-x" onclick="y', 'id' => 'i"d')),
		array('label' => 'Bar "1" <x>', 'class' => 'mb-0" onclick="z', 'id' => 'ignored')
	);

	pg_assert_contains('&lt;b&gt;&quot;A&amp;B&quot;&lt;/b&gt;', $html, 'label escaped');
	pg_assert_contains('href="x.php?a=1&amp;b=&quot;2&quot;"', $html, 'url escaped');
	pg_assert_contains('bi-x&quot; onclick=&quot;y', $html, 'icon escaped');
	pg_assert_contains('id="i&quot;d"', $html, 'button id escaped');
	pg_assert_contains('aria-label="Bar &quot;1&quot; &lt;x&gt;"', $html, 'bar label escaped');
	pg_assert_contains('class="pg-toolbar mb-0&quot; onclick=&quot;z"', $html, 'extra class escaped');
	pg_assert_false(strpos($html, 'onclick="') !== false, 'no attribute injected');
	pg_assert_same(1, substr_count($html, 'id="button_bar"'), 'the bar keeps its id');
}
