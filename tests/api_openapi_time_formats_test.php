<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Tests for the type words a field declaration may use in the OpenAPI
 * document: a moment (api_time(), ISO-8601 in UTC) is declared datetime and
 * says format: date-time, a day (YYYY-MM-DD) is declared date and says
 * format: date, and every word a declaration uses is one the document can
 * say. The day-or-moment choice of each field below was read from its
 * presenter, not from its name.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_TEST_RUNNER')) {
	exit;
}

if (!defined('PG_API_PANEL')) {
	define('PG_API_PANEL', true);
}

require_once(PG_FUNCTIONS_DIR . '/includes/api/schema.php');
require_once(PG_FUNCTIONS_DIR . '/includes/api/openapi.php');
require_once(PG_FUNCTIONS_DIR . '/includes/erp/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/workspace/api.php');
require_once(PG_FUNCTIONS_DIR . '/includes/translate/api.php');

// The schema a field of an object answers with, by a dotted path; a list's
// items are reached through [].
function api_openapi_time_formats_test_field($object, $path)
{
	api_openapi_load_declarations();

	$objects = api_openapi_objects();

	$schema = api_openapi_type_spec(call_user_func($objects[$object]));

	foreach (explode('.', $path) as $part) {

		$list = (substr($part, -2) === '[]');

		$part = $list ? substr($part, 0, -2) : $part;

		$schema = $schema['properties'][$part];

		if ($list) {

			$schema = $schema['items'];

		}

	}

	return $schema;
}

function test_api_openapi_time_formats_words_say_a_format()
{
	pg_assert_same(array('type' => 'string', 'format' => 'date-time'), api_openapi_type_spec('datetime'));
	pg_assert_same(array('type' => 'string', 'format' => 'date'), api_openapi_type_spec('date'));
	pg_assert_same(array('type' => 'string', 'format' => 'date-time', 'nullable' => true), api_openapi_type_spec('datetime?'));
	pg_assert_same(array('type' => 'string', 'format' => 'date', 'nullable' => true), api_openapi_type_spec('date?'));
	pg_assert_same(array('type' => 'array', 'items' => array('type' => 'string', 'format' => 'date')), api_openapi_type_spec('date[]'));
	pg_assert_same(array('type' => 'string'), api_openapi_type_spec('string'));
	pg_assert_same(array('type' => 'integer', 'nullable' => true), api_openapi_type_spec('integer?'));
}

// OpenAPI has no type datetime: the three User times said so until the word
// was mapped.
function test_api_openapi_time_formats_user_times_are_moments()
{
	foreach (array('last_online_at', 'created_at', 'updated_at') as $field) {
		$schema = api_openapi_time_formats_test_field('User', $field);
		pg_assert_same('string', $schema['type'], 'User.' . $field);
		pg_assert_same('date-time', $schema['format'], 'User.' . $field);
	}
}

// A sample of each presenter kind: api_time(), a helper that writes the same
// ISO form, a DATE column, and a Y-m-d day built in PHP.
function test_api_openapi_time_formats_core_fields_follow_their_presenters()
{
	$moments = array(
		'Order'              => array('placed_at', 'cancelled_at'),
		'Product'            => array('updated_at'),
		'Webhook'            => array('created_at'),
		'CartQuote'          => array('valid_until'),
		'ShortLink'          => array('expires_at', 'last_used_at'),
		'Experiment'         => array('started_at'),
		'Booking'            => array('start_at'),
		'StoreShippingMethod' => array('available_from', 'available_until'),
		'Form'               => array('last_submission_at'),
		'SystemStatus'       => array('maintenance.until'),
	);

	$days = array(
		'Product'          => array('preorder_date'),
		'Order'            => array('shipments[].ship_date', 'shipments[].delivery_date'),
		'GiftCard'         => array('expires_on', 'delivery_date'),
		'KeyCode'          => array('expires_on'),
		'Offer'            => array('start_date', 'end_date'),
		'StoreArrivalDate' => array('arrival_date', 'start_date', 'end_date', 'custom_maximum_arrival_date'),
		'StoreCreditCard'  => array('last_day'),
		'SearchReport'     => array('latest_day', 'days[].date'),
		'PagesReport'      => array('summary_to'),
	);

	foreach (array('date-time' => $moments, 'date' => $days) as $format => $fields) {
		foreach ($fields as $object => $paths) {
			foreach ($paths as $path) {
				$schema = api_openapi_time_formats_test_field($object, $path);
				pg_assert_same($format, isset($schema['format']) ? $schema['format'] : null, $object . '.' . $path);
			}
		}
	}

	// A time of day and a minute without a zone are neither, and stay strings.
	pg_assert_false(isset(api_openapi_time_formats_test_field('StoreShippingMethod', 'end_of_day')['format']), 'end_of_day is HH:MM');
	pg_assert_false(isset(api_openapi_time_formats_test_field('CampaignProfile', 'schedule_time')['format']), 'schedule_time is HH:MM');
}

// Every word a declaration uses is one api_openapi_field_words() maps: a word
// it does not know would reach the document as a type OpenAPI does not have.
function test_api_openapi_time_formats_every_word_is_known()
{
	api_openapi_load_declarations();

	$words = api_openapi_field_words();

	$unknown = array();

	$walk = function ($spec, $where) use (&$walk, &$unknown, $words) {
		if (is_array($spec)) {
			foreach ($spec as $key => $value) {
				$walk($value, $where . '.' . $key);
			}
			return;
		}
		$word = rtrim(trim((string)$spec), '?');
		while (substr($word, -2) === '[]') {
			$word = substr($word, 0, -2);
		}
		if (($word === '') || ctype_upper(substr($word, 0, 1))) {
			return;
		}
		if (!isset($words[$word])) {
			$unknown[] = $where . ' => ' . $spec;
		}
	};

	$objects = api_openapi_objects() + erp_openapi_objects() + ws_openapi_objects() + translate_openapi_objects();

	foreach ($objects as $name => $declare) {
		if (function_exists($declare)) {
			$walk(call_user_func($declare), $name);
		}
	}

	pg_assert_same(array(), $unknown);
}
