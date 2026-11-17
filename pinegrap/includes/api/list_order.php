<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The newest-first walk of a cursor-paged list: the sort a call asks for, the
 * cursor that remembers it, and the keyset and ORDER BY clauses both walks use.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A list walks oldest first by default, so an integration that pages forward
// never re-reads a row. An automation platform shows its user the newest few
// records as samples, so a list it names takes sort=newest: the same column
// and id, walked the other way. A record added meanwhile has the newest value
// and lands before the first page, never inside a later one.
//
// The two walks resume from different cursors. A newest-first cursor starts
// with "n."; '.' is not in api_cursor_encode()'s alphabet, so an oldest-first
// cursor never carries it, and cursors handed out before this file existed
// stay readable under the default walk. A cursor used under the other walk is
// refused rather than read from the wrong end.

// Loaded by the resource files, which the panel and the ERP module load too:
// the same gate as theirs, so requiring it never ends one of those requests.
if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {
	exit;
}

// The values the sort parameter takes, the default first.
function api_list_sort_values() {

	return array('oldest', 'newest');

}

// Whether the call asks for the newest first.
function api_list_descending($params) {

	return isset($params['sort']) && ($params['sort'] === 'newest');

}

// The cursor for the last row of a page: its sort value and id, marked when
// the walk is newest first.
function api_list_cursor_encode($value, $id, $descending) {

	$cursor = api_cursor_encode($value, $id);

	return $descending ? 'n.' . $cursor : $cursor;

}

// array('v' => int, 'i' => int), or null when the cursor is unreadable or was
// made for the other walk.
function api_list_cursor_decode($cursor, $descending) {

	$cursor = (string)$cursor;

	$marked = (strpos($cursor, 'n.') === 0);

	if ($marked !== (bool)$descending) {

		return null;

	}

	return api_cursor_decode($marked ? (string)substr($cursor, 2) : $cursor);

}

// The cursor of a call, decoded, null without one. An unreadable cursor, or
// one made for the other walk, is answered 400 invalid_cursor.
function api_list_cursor($params, $descending) {

	if (!isset($params['cursor']) || ($params['cursor'] === '')) {

		return null;

	}

	$cursor = api_list_cursor_decode($params['cursor'], $descending);

	if ($cursor === null) {

		api_fail(400, 'invalid_cursor', lang('The cursor is not readable. Start the listing again without one.'), 'cursor');

	}

	return $cursor;

}

// The rows after the cursor: past its value, or on the same value past its id.
// $column '' walks the id alone. Both are compared as integers, as the cursor
// holds them.
function api_list_keyset_sql($column, $id_column, $cursor, $descending) {

	$past = $descending ? '<' : '>';

	$id = "'" . (int)$cursor['i'] . "'";

	if ($column === '') {

		return $id_column . ' ' . $past . ' ' . $id;

	}

	$value = "'" . (int)$cursor['v'] . "'";

	return '(' . $column . ' ' . $past . ' ' . $value
		. ' OR (' . $column . ' = ' . $value . ' AND ' . $id_column . ' ' . $past . ' ' . $id . '))';

}

// The ORDER BY of a walk, without the keywords: the column, then the id, both
// the same way, so rows on the same value keep one order across pages.
function api_list_order_sql($column, $id_column, $descending) {

	$direction = $descending ? 'DESC' : 'ASC';

	return (($column === '') ? '' : $column . ' ' . $direction . ', ') . $id_column . ' ' . $direction;

}

// The same rule as api_list_keyset_sql(), on values: whether a row with
// $value and $id comes after the cursor in the walk.
function api_list_after($value, $id, $cursor, $descending) {

	$value = (int)$value;

	$id = (int)$id;

	$at = (int)$cursor['v'];

	$at_id = (int)$cursor['i'];

	if ($value === $at) {

		return $descending ? ($id < $at_id) : ($id > $at_id);

	}

	return $descending ? ($value < $at) : ($value > $at);

}

// The same order as api_list_order_sql(), for usort().
function api_list_compare($a, $b, $descending) {

	$by = ((int)$a['v'] === (int)$b['v']) ? ((int)$a['i'] <=> (int)$b['i']) : ((int)$a['v'] <=> (int)$b['v']);

	return $descending ? -$by : $by;

}
