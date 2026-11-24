<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The launch journey as data: GET /launch answers what the set-up card and
 * the go-live check show, read from the same settings at the same moment
 * (includes/fn/launch_journey_site.php reads the facts,
 * includes/fn/launch_journey.php judges them). It changes nothing: going
 * live stays a person's press in the panel.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

function api_launch_read($params) {

	$stored = pg_launch_journey_stored();
	$purpose = ($stored['purpose'] !== '') ? $stored['purpose'] : 'other';

	// The owner's own sign-in does not count as the team's, as on the card.
	$report = pg_launch_journey_report($stored, pg_launch_journey_facts(api_current_app()['owner'], $purpose));
	$report['live_at'] = api_time($report['live_at']);

	api_ok($report);

}

// One step or check row of api_launch_read().
function api_launch_step_schema() {

	return array(
		'key'    => 'string',
		'name'   => 'string',
		'state'  => 'string',
		'level'  => 'string',
		'reason' => 'string',
		'line'   => 'string',
		'hidden' => 'boolean'
	);

}

// What api_launch_read() returns.
function api_launch_schema() {

	return array(
		'purpose'         => 'string',
		'purpose_chosen'  => 'boolean',
		'status'          => 'string',
		'done'            => 'integer',
		'total'           => 'integer',
		'finished'        => 'boolean',
		'live_at'         => 'datetime?',
		'steps'           => 'LaunchStep[]',
		'blockers'        => 'LaunchStep[]',
		'warnings'        => 'LaunchStep[]',
		'go_live_changes' => 'string[]'
	);

}
