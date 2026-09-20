<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The site's own health, as data.
//
// The dashboard draws a gauge and a card per check from get_system_status_checks();
// this hands the same results to whoever holds the key. The point is that the
// operator can put their own monitoring in front of it - an uptime service, a
// cron with curl, a panel of their own sites - instead of opening each site's
// dashboard to find out that a scheduled job stopped moving three days ago.
//
// Nothing is sent anywhere by the software itself: this is a read, answered to
// a request that carried a key. There is no address in the code and no call
// home.
//
// The answer is the cached one - the checks behind it are expensive (file
// integrity, storage, a certificate) and the dashboard has always read them
// from a ten-minute cache. A monitor polling every minute gets the same answer
// the panel would show, which is the honest one; it does not get a freshly
// computed site scan every minute, which no site would survive.

if (!defined('PG_API_ENTRY')) {

	exit;

}

function api_system_status($params) {

	if (!function_exists('get_system_status_checks')) {

		api_fail(503, 'service_unavailable', lang('The status checks are not available on this installation.'));

	}

	$status = get_system_status_checks();

	$checks = array();

	foreach ((array)$status['checks'] as $check) {

		$row = array(
			// The English name of the check, stable across languages: title and
			// value are translated for a person to read, key is what a monitor
			// should branch on.
			'key'     => isset($check['key']) ? (string)$check['key'] : '',
			'group'   => isset($check['group']) ? (string)$check['group'] : '',
			'state'   => isset($check['state']) ? (string)$check['state'] : 'info',
			'title'   => isset($check['title']) ? (string)$check['title'] : '',
			'value'   => isset($check['value']) ? (string)$check['value'] : '',
			'message' => isset($check['message']) ? (string)$check['message'] : '',
			'detail'  => array()
		);

		// The rows behind a check that counts things - which three jobs are
		// stale, which subscriptions are stopped.
		if (!empty($check['detail']) && is_array($check['detail'])) {

			foreach ($check['detail'] as $detail) {

				$row['detail'][] = array(
					'label' => isset($detail['label']) ? (string)$detail['label'] : '',
					'state' => isset($detail['state']) ? (string)$detail['state'] : '',
					'note'  => isset($detail['when']) ? (string)$detail['when'] : ''
				);

			}

		}

		$checks[] = $row;

	}

	$storage = isset($status['storage']) ? (array)$status['storage'] : array();

	api_ok(array(
		'score'      => (int)$status['score'],
		// When the checks were last computed, not when this call was made: the
		// difference is the age of the answer and a monitor should be able to
		// see it.
		'checked_at' => api_time(isset($status['ts']) ? $status['ts'] : 0),
		'checks'     => $checks,
		'storage'    => array(
			'database_bytes' => isset($storage['database']) ? (int)$storage['database'] : 0,
			'backup_bytes'   => isset($storage['backups']) ? (int)$storage['backups'] : 0,
			'file_bytes'     => isset($storage['files']) ? (int)$storage['files'] : 0,
			'total_bytes'    => isset($storage['total']) ? (int)$storage['total'] : 0,
			'measured_at'    => api_time(isset($storage['ts']) ? $storage['ts'] : 0)
		)
	));

}

// What api_system_status() answers with, declared for the OpenAPI document.
function api_system_status_schema() {

	return array(
		'score'      => 'integer',
		'checked_at' => 'string?',
		'checks'     => array(array(
			'key'     => 'string',
			'group'   => 'string',
			'state'   => 'string',
			'title'   => 'string',
			'value'   => 'string',
			'message' => 'string',
			'detail'  => array(array(
				'label' => 'string',
				'state' => 'string',
				'note'  => 'string'
			))
		)),
		'storage' => array(
			'database_bytes' => 'integer',
			'backup_bytes'   => 'integer',
			'file_bytes'     => 'integer',
			'total_bytes'    => 'integer',
			'measured_at'    => 'string?'
		)
	);

}
