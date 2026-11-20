<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Hints on refusals: by error code, one English sentence on what to do next
 * and the guide section to read (includes/fn/agent_guide.php). api_fail()
 * adds both to the error object, so a handler passes none. The hint stays
 * English on every site, like the code beside it: it is read by an agent,
 * while the translated message is read by the person.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
	exit;
}

// The longest hint, in characters.
function pg_agent_guide_hint_max() {

	return 200;

}

// Error code => hint (tools named as {tool:route.id}, as in a topic's lines)
// and guide (topic#section). tests/agent_guide_hints_test.php holds every
// section to one its topic declares and every hint to the length above.
function pg_agent_guide_hints() {

	return array(

		// The designer's refusals (design.proposals.create).
		'design_page_changed' => array(
			'hint'  => 'The page changed after you read it: read it again with {tool:design.pages.get} and write the operations against what it shows now.',
			'guide' => 'designer#page'
		),
		'design_node_unknown' => array(
			'hint'  => 'Use a data-pg id the page shows now: read it again with {tool:design.pages.get}; an element you insert gets a new id.',
			'guide' => 'designer#page'
		),
		'design_keep_inside' => array(
			'hint'  => 'A <pg-keep> part or a bound element is kept whole: change its settings with set_props, or move or remove it.',
			'guide' => 'designer#mistakes'
		),
		'design_op_invalid' => array(
			'hint'  => 'Correct the operation that field names (ops[N]) as the message says; the operations section lists the fields of each.',
			'guide' => 'designer#ops'
		),
		'design_page_invalid' => array(
			'hint'  => 'The operations together make a page that is not valid: send fewer at once with dry_run true to find the one that breaks it.',
			'guide' => 'designer#ops'
		),

		// Validation, dry_run and scope, on every route.
		'validation_failed' => array(
			'hint'  => 'Correct the field the answer names (errors lists every wrong one) and send the call again; the tool\'s input schema gives types and limits.',
			'guide' => 'start#calls'
		),
		'invalid_cursor' => array(
			'hint'  => 'Pass page.next_cursor from the last answer back unchanged, or start the listing again without cursor.',
			'guide' => 'start#calls'
		),
		'invalid_dry_run' => array(
			'hint'  => 'Send dry_run as true or false.',
			'guide' => 'start#writes'
		),
		'dry_run_unsupported' => array(
			'hint'  => 'This call cannot be rehearsed: tell the person what it will do, wait for their yes, then send it without dry_run.',
			'guide' => 'start#writes'
		),
		'insufficient_scope' => array(
			'hint'  => 'The connection lacks this permission: ask the person to grant it to the application, or do the job with a tool it holds.',
			'guide' => 'start#jobs'
		)

	);

}

// The hint and guide section for an error code, or null when it has none.
// The hint is written with its tools' names.
function pg_agent_guide_hint($code) {

	$hints = pg_agent_guide_hints();

	if (!isset($hints[$code])) {

		return null;

	}

	if (!function_exists('pg_agent_guide_line')) {

		require_once(PG_FUNCTIONS_DIR . '/includes/fn/agent_guide.php');

	}

	return array(
		'hint'  => pg_agent_guide_line($hints[$code]['hint']),
		'guide' => $hints[$code]['guide']
	);

}
