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

// Splits a date parameter into its parts, or null when it is not one of the
// documented forms: a unix integer, a day (YYYY-MM-DD or the basic YYYYMMDD),
// or a day with a time (T or space, seconds and fraction optional) and an
// optional zone (Z, +hh:mm, +hhmm, +hh). strtotime() alone took relative
// words ("next monday") and rolled a day the calendar lacks (2026-02-30)
// into the next month; eight digits are read as a basic ISO day, never as a
// 1970 unix stamp. Returns array('unix' => int) or array('day' => 'Y-m-d',
// 'time' => 'H:i:s' or null, 'zone' => '' or the zone as written).
function api_time_split($value) {

	$value = trim((string)$value);

	if ($value === '') {

		return null;

	}

	if (ctype_digit($value) && strlen($value) !== 8) {

		return array('unix' => (int)$value);

	}

	if (!preg_match('/^(\d{4})-?(\d{2})-?(\d{2})(?:[T ](\d{2}):?(\d{2})(?::?(\d{2})(?:[.,]\d+)?)?(Z|[+-]\d{2}(?::?\d{2})?)?)?$/i', $value, $parts)) {

		return null;

	}

	// The day is written either with both separators or with none: 2026-1020
	// is no ISO form.
	if (!preg_match('/^(?:\d{4}-\d{2}-\d{2}|\d{8})(?:[T ]|$)/i', $value)) {

		return null;

	}

	if (!checkdate((int)$parts[2], (int)$parts[3], (int)$parts[1])) {

		return null;

	}

	$day = $parts[1] . '-' . $parts[2] . '-' . $parts[3];

	if (!isset($parts[4]) || $parts[4] === '') {

		return array('day' => $day, 'time' => null, 'zone' => '');

	}

	$second = (isset($parts[6]) && $parts[6] !== '') ? $parts[6] : '00';

	if ((int)$parts[4] > 23 || (int)$parts[5] > 59 || (int)$second > 59) {

		return null;

	}

	$zone = isset($parts[7]) ? strtoupper($parts[7]) : '';

	if ($zone !== '' && $zone !== 'Z') {

		$digits = str_replace(':', '', substr($zone, 1));

		if (strlen($digits) === 2) {

			$digits .= '00';

		}

		if ((int)substr($digits, 0, 2) > 14 || (int)substr($digits, 2, 2) > 59) {

			return null;

		}

		$zone = $zone[0] . substr($digits, 0, 2) . ':' . substr($digits, 2, 2);

	}

	return array('day' => $day, 'time' => $parts[4] . ':' . $parts[5] . ':' . $second, 'zone' => $zone);

}

// The unix moment of a split value; a value without a zone is in the site's
// zone (date_default_timezone_get(), set by init.php).
function api_time_split_unix($split, $default_time) {

	if (isset($split['unix'])) {

		return $split['unix'];

	}

	$time = ($split['time'] === null) ? $default_time : $split['time'];

	$parsed = strtotime($split['day'] . ' ' . $time . ($split['zone'] === 'Z' ? ' UTC' : $split['zone']));

	return ($parsed === false) ? null : (int)$parsed;

}

// A start bound or any other moment: a bare day is its 00:00. Returns a unix
// timestamp or null. Used for every date filter so they all behave alike.
function api_time_parse($value) {

	$split = api_time_split($value);

	return ($split === null) ? null : api_time_split_unix($split, '00:00:00');

}

// The unix moment an end bound stands for, or null when it is not a date.
// A bare day counts to its 23:59:59.
function api_time_parse_end_bound($value) {

	$split = api_time_split($value);

	return ($split === null) ? null : api_time_split_unix($split, '23:59:59');

}
