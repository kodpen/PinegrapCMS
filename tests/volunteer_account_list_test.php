<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the my account volunteering list (includes/fn/widgets_volunteering.php):
 * a sign-up's state word, the account.volunteering rows of every programme
 * soonest first, their registration, and the designer's twins of the
 * section and the list.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/widgets_volunteering.php');

function _volunteer_account_list_test_signup($shift, $start, $state, $offered = 0, $swap = 0, $asked = false)
{
	return array('id' => $shift * 10, 'shift_id' => $shift, 'role' => 'r1', 'state' => $state, 'offered_at' => $offered, 'swap_from' => $swap, 'asked' => $asked,
		'actions' => array(), 'shift' => array('id' => $shift, 'start' => $start, 'end' => substr($start, 0, 11) . '13:00:00', 'place' => 'Warehouse'));
}

function _volunteer_account_list_test_views()
{
	$food = array('id' => 1, 'name' => 'Food bank', 'roles' => array('r1' => array('name' => 'Driver')), 'call_channels' => array());
	$park = array('id' => 2, 'name' => 'Park clean-up', 'roles' => array(), 'call_channels' => array());

	return array(
		array('programme' => $food, 'mine' => array(
			_volunteer_account_list_test_signup(3, '2026-12-12 09:00:00', 'waiting'),
			_volunteer_account_list_test_signup(4, '2026-12-14 09:00:00', 'signed', 5, 0, true),
		)),
		array('programme' => $park, 'mine' => array(
			_volunteer_account_list_test_signup(7, '2026-12-05 09:00:00', 'signed'),
		)),
	);
}

function test_volunteer_signup_state_words()
{
	pg_assert_same('signed', pg_volunteer_signup_state(array('state' => 'signed', 'offered_at' => 0, 'swap_from' => 0)));
	pg_assert_same('offered', pg_volunteer_signup_state(array('state' => 'signed', 'offered_at' => 5, 'swap_from' => 0)));
	pg_assert_same('asked', pg_volunteer_signup_state(array('state' => 'signed', 'offered_at' => 5, 'swap_from' => 0, 'asked' => true)));
	pg_assert_same('waiting', pg_volunteer_signup_state(array('state' => 'waiting', 'offered_at' => 0, 'swap_from' => 0)));
	pg_assert_same('swap', pg_volunteer_signup_state(array('state' => 'waiting', 'offered_at' => 0, 'swap_from' => 4)));
}

function test_volunteer_account_list_rows()
{
	$filed = pg_sw_list_account_volunteering_rows(7, _volunteer_account_list_test_views());
	pg_assert_same(array(7), array_keys($filed['account.volunteering']), 'filed under the account');
	$rows = $filed['account.volunteering'][7];

	pg_assert_same(array('Park clean-up', 'Food bank', 'Food bank'), array_column($rows, '__volunteering_programme'), 'every programme, soonest first');
	pg_assert_same(array('signed', 'waiting', 'asked'), array_column($rows, '__volunteering_state'));
	pg_assert_same(array('success', 'warning', 'info'), array_column($rows, '__volunteering_tone'), 'Bootstrap colours');
	pg_assert_same('Driver', $rows[1]['__volunteering_role']);
	pg_assert_same('Removed role', $rows[0]['__volunteering_role'], 'a role gone from the programme');
	pg_assert_same('09:00–13:00', $rows[0]['__volunteering_time']);
	pg_assert_same('Warehouse', $rows[0]['__volunteering_place']);
	pg_assert_same(pg_volunteer_shift_when(array('start' => '2026-12-05 09:00:00', 'end' => '2026-12-05 13:00:00', 'place' => 'Warehouse')), $rows[0]['__volunteering_when']);

	pg_assert_same(array('account.volunteering' => array(3 => array())), pg_sw_list_account_volunteering_rows(3, array()), 'no profile, an empty list');
}

function test_volunteer_account_list_wiring()
{
	$lists = pg_sw_list_sources();
	pg_assert_true(isset($lists['account.volunteering']), 'the list is registered');
	pg_assert_same('account_id', $lists['account.volunteering']['key']);

	$account = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets_account.php');
	pg_assert_contains("\$out['volunteering'] = pg_volunteer_account_views(\$volunteer_ids);", $account, 'my account keeps the views');
	$widgets = (string) file_get_contents(PG_FUNCTIONS_DIR . '/includes/fn/widgets.php');
	pg_assert_contains("pg_sw_list_account_volunteering_rows(\$values['account_id'], \$extras['volunteering'])", $widgets, 'and files the rows');

	$js = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	pg_assert_contains("'account.volunteering': {", $js, 'the designer knows the list');
	pg_assert_contains("if (source === 'account.volunteering') return _sdAccountVolunteeringList(bs);", $js, 'and starts a new one');
	pg_assert_contains("if (section === 'volunteering') return _sdAccountVolunteeringHtml(", $js, 'and draws the section\'s twin');
	pg_assert_contains("out.push(['volunteering',", $js, 'which the section picker offers');
}

// The twin draws a next shift as the site's section does: compared on a
// place someone asked to take, which has no buttons.
function test_volunteer_account_section_twin()
{
	$out = array();
	$code = 1;
	@exec('node --version 2>&1', $out, $code);
	if ($code !== 0) {
		return;
	}

	$views = _volunteer_account_list_test_views();
	$views[0]['volunteer'] = array('id' => 1, 'state' => 'left');
	$views[0]['mine'] = array($views[0]['mine'][1]);
	$views[0]['documents'] = array();
	$views[0]['programme']['state'] = 'paused';
	$php = pg_volunteer_page_html(array($views[0]), array('action' => '#', 'hidden' => ''), pg_volunteer_page_site_classes());
	$row = pg_sw_list_account_volunteering_rows(1, array($views[0]))['account.volunteering'][1][0];

	$source = (string) file_get_contents(PG_FUNCTIONS_DIR . '/assets/js/style_designer.src.js');
	$from = strpos($source, '    function _sdAccountVolunteeringHtml(');
	pg_assert_true($from !== false, 'the designer has the twin');
	$twin = substr($source, $from, strpos($source, "\n    function ", $from + 10) - $from);
	$script = "function esc(s) { if (!s && s !== 0) return ''; return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\"/g, '&quot;'); }\n"
		. "function _sdT(s) { return s; }\n" . $twin . "\n"
		. 'console.log(JSON.stringify(_sdAccountVolunteeringHtml(' . json_encode(array($row)) . ')));';
	$file = tempnam(sys_get_temp_dir(), 'pgvol');
	file_put_contents($file, $script);
	$out = array();
	exec('node ' . escapeshellarg($file) . ' 2>&1', $out, $code);
	unlink($file);
	pg_assert_same(0, $code, implode("\n", $out));
	$js = (string) json_decode(implode("\n", $out), true);

	$cut = function ($html) {
		$from = strpos($html, '<h3 class="h6 mb-2">');
		return substr($html, $from, strpos($html, '</div>', strpos($html, '<div class="list-group mb-3">', $from)) + 12 - $from);
	};
	pg_assert_contains('<h2 class="h5 mb-3">Food bank</h2>', $js, 'the programme\'s name');
	pg_assert_same($cut($php), $cut($js), 'the next shift as the section draws it');
}
