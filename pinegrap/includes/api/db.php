<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Database access for the API.
//
// The shared db() / db_item() / db_items() helpers answer a failed query with
// output_error(), which prints an HTML error page. Inside a JSON endpoint that
// produces a response no client can parse and, with DEBUG on, puts the failing
// SQL in front of a stranger. These four wrappers do the same work and fail the
// way the API contract says to fail: a 500 with a request id, and the database
// message only when the site is in debug mode.

if (!defined('PG_API_ENTRY')) {
	exit;
}

function api_query($sql) {

	if (!isset(db::$con) || !db::$con) {

		api_fail_server(lang('No database connection.'));

	}

	// Caught, not just tested for false.
	//
	// mysqli reports errors as exceptions from PHP 8.1 onwards, and that is the
	// default for a connection nobody configured otherwise. A failing statement
	// therefore did not return false here - it threw, past every handler, and
	// the caller received an empty 500 with no request id and nothing in the
	// body: the one shape the API contract promises never to produce. Both are
	// handled, because the software still runs on PHP 7 installations where the
	// old return value is what arrives.
	try {

		$result = @mysqli_query(db::$con, $sql);

	} catch (Exception $exception) {

		$result = false;

	}

	if ($result === false) {

		$message = lang('A system error occurred.');

		if (defined('DEBUG') && DEBUG) {

			$message .= ' ' . (isset($exception) ? $exception->getMessage() : mysqli_error(db::$con));

		}

		api_fail_server($message);

	}

	return $result;

}

// One row as an associative array, or null.
function api_row($sql) {

	$result = api_query($sql);

	if ($result === true) {

		return null;

	}

	$row = mysqli_fetch_assoc($result);

	mysqli_free_result($result);

	return ($row === null) ? null : $row;

}

// Every row as a list of associative arrays.
function api_rows($sql) {

	$result = api_query($sql);

	if ($result === true) {

		return array();

	}

	$rows = array();

	while ($row = mysqli_fetch_assoc($result)) {

		$rows[] = $row;

	}

	mysqli_free_result($result);

	return $rows;

}

// A statement that returns no rows. Answers the number of rows it changed.
function api_exec($sql) {

	api_query($sql);

	return mysqli_affected_rows(db::$con);

}

function api_insert_id() {

	return (int)mysqli_insert_id(db::$con);

}

// A single scalar, or null when the query matched nothing.
function api_value($sql) {

	$result = api_query($sql);

	if ($result === true) {

		return null;

	}

	$row = mysqli_fetch_row($result);

	mysqli_free_result($result);

	return ($row === null) ? null : $row[0];

}
