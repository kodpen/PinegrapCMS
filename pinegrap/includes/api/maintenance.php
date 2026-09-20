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

if (!defined('PG_INIT_LOADED')) {

	exit;

}

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

	// Subscriptions whose application is no longer there.
	//
	// A subscription is owned by an application and is only reachable through
	// it: the panel lists them under their application, so one whose application
	// has gone is invisible - and it used to keep being delivered to, which is a
	// site pushing its orders at an address nobody can see or stop. The paths
	// that delete an application now take its subscriptions with it, and the
	// temporary credential above is replaced rather than deleted every time the
	// documentation screen mints one, so this sweep is the backstop rather than
	// the fix. Ordered queue first: the rows are found through the subscription.
	db("DELETE FROM api_webhook_queue
		WHERE webhook_id IN (
			SELECT api_webhooks.id FROM api_webhooks
			LEFT JOIN api_apps ON api_apps.id = api_webhooks.app_id
			WHERE api_apps.id IS NULL
		)");

	db("DELETE api_webhooks FROM api_webhooks
		LEFT JOIN api_apps ON api_apps.id = api_webhooks.app_id
		WHERE api_apps.id IS NULL");

	db("UPDATE config SET api_log_last_purge = '" . time() . "'");

	return true;

}
