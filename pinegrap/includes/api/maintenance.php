<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Housekeeping for the API tables, run from job.php.
//
// It lives apart from the endpoint's own files because it runs in the cron's
// context rather than a request's: there is no response to fail into, so it uses
// the shared db() helper like every other scheduled task.
//
// Three tables grow with traffic and none of them is worth keeping forever. The
// request log is kept for the window the operator chose. A rate bucket older
// than an hour can never be the current minute again. A stored idempotent answer
// is only useful while the caller might still retry, and a day is far longer
// than any retry policy.

function api_maintenance_purge() {

	// A site whose files are new but whose database has not been upgraded yet
	// has none of these tables.
	if (!@mysqli_query(db::$con, "SELECT 1 FROM api_request_log LIMIT 1")) {

		return false;

	}

	$settings = @mysqli_query(db::$con, "SELECT api_log_retention_days, api_log_last_purge FROM config LIMIT 1");

	if ($settings === false) {

		return false;

	}

	$row = mysqli_fetch_assoc($settings);

	if (!is_array($row)) {

		return false;

	}

	// Once a day is enough, and job.php runs far more often than that: deleting
	// from three tables on every tick would be the most expensive thing this
	// cron does, for no benefit.
	if ((time() - (int)$row['api_log_last_purge']) < 86400) {

		return false;

	}

	$days = (int)$row['api_log_retention_days'];

	if ($days < 1) {

		$days = 30;

	}

	db("DELETE FROM api_request_log WHERE timestamp < '" . (time() - ($days * 86400)) . "'");

	db("DELETE FROM api_rate_bucket WHERE window_start < '" . (time() - 3600) . "'");

	db("DELETE FROM api_idempotency WHERE created_timestamp < '" . (time() - 86400) . "'");

	// The documentation screen's temporary credentials. They stop working when
	// they expire, but there is no reason to keep the rows.
	db("DELETE FROM api_apps
		WHERE name LIKE '\\_\\_test\\_\\_%' AND expires_at > 0 AND expires_at < " . time());

	db("UPDATE config SET api_log_last_purge = '" . time() . "'");

	return true;

}
