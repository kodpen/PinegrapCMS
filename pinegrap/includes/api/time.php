<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// End bounds of a window: to, date_to, issued_to and every other datetime
// parameter named to or *_to. A bare day (YYYY-MM-DD) there means "up to and
// including that day", so it counts to 23:59:59 of that day in the site's time
// zone, the way GET /reports/traffic always counted its last day. Read as
// strtotime() reads it, the day's 00:00 would leave the whole day out of an
// order_date <= to comparison. A value with a time, or a unix stamp, stays
// that exact moment. Start bounds (from, *_since) and exclusive ones
// (*_before) are untouched.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

function api_param_is_end_bound($name) {

	$name = (string)$name;

	return ($name === 'to') || (substr($name, -3) === '_to');

}

// The unix moment an end bound stands for, or null when it is not a date.
// date_default_timezone_get() is the site's zone once init.php has set it.
function api_time_parse_end_bound($value) {

	$value = trim((string)$value);

	if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {

		$parsed = strtotime($value . ' 23:59:59');

		return ($parsed === false) ? null : (int)$parsed;

	}

	if ($value === '') {

		return null;

	}

	if (ctype_digit($value)) {

		return (int)$value;

	}

	$parsed = strtotime($value);

	return ($parsed === false) ? null : (int)$parsed;

}
