<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The privacy engine over the tables of 2026.11.0: a queue number and the
 * visitor journey count as a passive contact's activity, an erased
 * volunteer's coming places go to the waitlist, and the browsers tied to a
 * contact are in the personal data export.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/volunteer_signup.php');

function privacy_new_tables_test_source($name)
{
	$function = new ReflectionFunction($name);
	$lines = file($function->getFileName());

	return implode('', array_slice($lines, $function->getStartLine() - 1, $function->getEndLine() - $function->getStartLine() + 1));
}

// The passive contacts rule reads a queue number taken, a visit tied to the
// contact and a journey row (newsletter, campaign click, download) as
// activity, as it reads an order or a form.
function test_privacy_rule_activity_reads_the_new_tables()
{
	pg_assert_true(function_exists('pg_privacy_rule_activity_checks'), 'the activity sources are one list');

	$scope = array('order_ids' => array(4), 'form_ids' => array(5), 'contact_ids' => array(7), 'emails' => array('a@example.com'), 'phones' => array('905321112233'));
	$checks = array();

	foreach (pg_privacy_rule_activity_checks($scope) as $check) {
		$checks[$check[0]] = $check;
	}

	pg_assert_same('order_date', $checks['orders'][1], 'orders by date');
	pg_assert_same('submitted_timestamp', $checks['forms'][1], 'forms by submission');

	pg_assert_same('joined_at', $checks['waiting_tickets'][1], 'a queue number by when it was taken');
	pg_assert_contains('`contact_id` IN (7)', $checks['waiting_tickets'][2], 'by the contact');
	pg_assert_contains("`email` IN ('a@example.com')", $checks['waiting_tickets'][2], 'by the address');
	pg_assert_contains("`phone` IN ('905321112233')", $checks['waiting_tickets'][2], 'by the number');

	pg_assert_same('started_at', $checks['lead_visits'][1], 'a visit tied to the contact');
	pg_assert_same('`contact_id` IN (7)', $checks['lead_visits'][2]);
	pg_assert_same('at', $checks['contact_journey'][1], 'a journey row');
	pg_assert_same('`contact_id` IN (7)', $checks['contact_journey'][2]);

	$none = pg_privacy_rule_activity_checks(array('order_ids' => array(), 'form_ids' => array(), 'contact_ids' => array(), 'emails' => array(), 'phones' => array()));

	foreach ($none as $check) {
		pg_assert_same('', $check[2], $check[0] . ': nothing to look for');
	}

	$active = privacy_new_tables_test_source('_pg_privacy_rule_active');
	pg_assert_contains('pg_privacy_rule_activity_checks($scope)', $active, 'the rule reads the list');
	pg_assert_contains('pg_schema_has($check[0], $check[1])', $active, 'a table not made yet is skipped');
}
