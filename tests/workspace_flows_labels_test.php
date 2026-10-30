<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The flow screens' short labels read in the flow sense: the list's state
 * column, the last run flag, the start chips, the approval outcomes, the
 * wait units, the share picker and redo each have their own key instead of
 * a short key shared with the address, calendar or store screens.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(__DIR__ . '/helpers/language_map.php');

// fl_* string => array(English key, Turkish value).
function workspace_flows_labels_expected()
{
	return array(
		'fl_col_state'        => array('Flow state', 'Durum'),
		'fl_flag_last'        => array('Last flow run', 'Son çalıştırma'),
		'fl_flag_done'        => array('Done', 'Bitti'),
		'fl_chip_event'       => array('Event start', 'Olay'),
		'fl_chip_join'        => array('Join start', 'Katılım'),
		'fl_on_approved'      => array('Approval given', 'Onaylandı'),
		'fl_on_expired'       => array('Approval expired', 'Süresi doldu'),
		'fl_delay_unit_hours' => array('Wait hours', 'Saat'),
		'fl_delay_unit_days'  => array('Wait days', 'Gün'),
		'fl_share_people'     => array('People to share with', 'Kişiler'),
		'fl_redo'             => array('Redo change', 'Yinele'),
	);
}

function test_workspace_flows_labels_have_their_own_turkish()
{
	$map = pg_test_language_map();

	foreach (workspace_flows_labels_expected() as $name => $pair) {
		pg_assert_same($pair[1], $map[$pair[0]] ?? null, $name . ' ' . $pair[0]);
	}
}

function test_workspace_flows_labels_use_their_keys()
{
	$source = (string) file_get_contents(dirname(__DIR__) . '/pinegrap/includes/workspace/flows.php');

	foreach (workspace_flows_labels_expected() as $name => $pair) {
		pg_assert_true(
			(bool) preg_match("/'" . $name . "'\\s*=> lang\\('" . preg_quote($pair[0], '/') . "'\\)/", $source),
			$name . ' uses lang(\'' . $pair[0] . '\')'
		);
	}
}
