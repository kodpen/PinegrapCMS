<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// What an integration needs to know before it sends anything.
//
// Chiefly the currency and its minor unit, because every money field in this API
// is an integer count of minor units and a client that assumes two decimal
// places is wrong in several currencies; and the order status vocabulary, so
// nobody hard codes a list that a later release adds to.
//
// The old surface exposed the whole settings row here, writable - a key could
// change the site's hostname and its payment gateway. Nothing about a
// marketplace integration needs that, so this replacement is read-only and
// narrow on purpose.

if (!defined('PG_API_ENTRY')) {
	exit;
}

function api_meta_read($params) {

	$currency_code = defined('BASE_CURRENCY_CODE') ? BASE_CURRENCY_CODE : 'USD';

	api_ok(array(
		'api_version'    => api_version(),
		'software'       => 'Pinegrap',
		'site' => array(
			'title'    => defined('TITLE') ? TITLE : '',
			'hostname' => defined('HOSTNAME_SETTING') ? HOSTNAME_SETTING : ''
		),
		'ecommerce' => array(
			'enabled'  => (defined('ECOMMERCE') && ECOMMERCE === true),
			'taxable'  => (defined('ECOMMERCE_TAX') && ECOMMERCE_TAX == true),
			// What a product with no rate of its own is charged at in this
			// shop's own country. A listing needs a rate per article, so a
			// connector reads product.tax_rate and falls back to this when it
			// is null; without the figure here that fallback is a guess.
			//
			// Null when the site's country sits in no tax zone, which is the
			// same as saying no tax is charged there.
			'default_tax_rate' => (function_exists('get_default_tax_rate') && get_default_tax_rate() !== false)
				? (float)get_default_tax_rate()
				: null,
			'currency' => array(
				'code'          => $currency_code,
				'symbol'        => defined('BASE_CURRENCY_SYMBOL') ? BASE_CURRENCY_SYMBOL : '',
				'minor_units'   => api_currency_minor_units($currency_code),
				// Spelled out because it is the single most common way to get an
				// integration wrong: 1999 is 19.99, not 1999.00.
				'money_format'  => 'Integer count of minor units.'
			)
		),
		'order_statuses' => api_order_statuses(),
		'version'        => api_meta_version(),
		'features'       => api_meta_features(),
		'security'       => api_meta_security(),
		'jobs'           => api_meta_jobs(),
		'uploads'        => api_meta_uploads(),
		'scopes'         => api_current_scopes(),
		'server_time'    => api_time(time())
	));

}

// Which software this is answering, and whether the operator has an upgrade
// waiting. An integration that has to work against several installations reads
// this instead of probing for the endpoints it hopes are there.
function api_meta_version() {

	return array(
		'number'           => defined('VERSION') ? VERSION : '',
		'channel'          => defined('SOFTWARE_UPDATE_CHANNEL') ? SOFTWARE_UPDATE_CHANNEL : 'stable',
		'update_available' => (defined('SOFTWARE_UPDATE_AVAILABLE') && SOFTWARE_UPDATE_AVAILABLE)
	);

}

// The modules an operator can switch on, as plain on and off. A client that
// finds forms switched off knows the empty answer it just received is the
// site's configuration rather than a fault.
//
// The shop is not in this list: it has a block of its own above, with the
// currency and the tax setting a connector actually needs.
function api_meta_features() {

	return array(
		'forms'               => (defined('FORMS') && FORMS == true),
		'calendars'           => (defined('CALENDARS') && CALENDARS == true),
		'ads'                 => (defined('ADS') && ADS == true),
		'affiliate_program'   => (defined('AFFILIATE_PROGRAM') && AFFILIATE_PROGRAM == true),
		'visitor_tracking'    => (defined('VISITOR_TRACKING') && VISITOR_TRACKING == true),
		'chat'                => (defined('CHAT_ENABLED') && CHAT_ENABLED == true),
		'barcode'             => (defined('BARCODE_ENABLED') && BARCODE_ENABLED == true),
		'erp'                 => (defined('ERP_ENABLED') && ERP_ENABLED == true),
		'mobile'              => (defined('MOBILE') && MOBILE == true),
		'captcha'             => (defined('CAPTCHA') && CAPTCHA == true),
		'performance_monitor' => (defined('PERF_MONITOR_ENABLED') && PERF_MONITOR_ENABLED == true)
	);

}

// Whether the site is defending itself, and nothing about how.
//
// The firewall's own settings - its sensitivity, the request ceilings, the
// exclusion and trusted-proxy lists, the automatic ban threshold - are a map of
// the defence and stay inside. What an integration legitimately needs is the
// posture: a request of its own that disappears is explained by 'block' here,
// and by nothing else it can see.
function api_meta_security() {

	$settings = api_settings();

	return array(
		'firewall'           => function_exists('waf_mode') ? waf_mode() : 'off',
		'security_headers'   => (function_exists('waf_setting') && (waf_setting('security_headers', '1') == true)),
		'api_requires_https' => !empty($settings['require_https'])
	);

}

// The scheduled jobs and whether they are actually running.
//
// Half of what this API reports is produced by those jobs rather than by a
// request: the structure half of an SEO score, the webhook queue, the
// marketplace sync. A client that sees a score that never moves has no way to
// tell a healthy site with nothing to fix from an installation whose cron was
// never set up, and this is that answer.
function api_meta_jobs() {

	if (!function_exists('pg_cron_jobs')) {

		return array();

	}

	$runs = pg_cron_last_runs();

	// null means the table the answer is read from does not exist, which is an
	// installation that has not run the upgrade. Reported as "not known" rather
	// than as "never ran".
	$known = is_array($runs);

	$now = time();

	$out = array();

	foreach (pg_cron_jobs() as $name => $job) {

		// The general job is not selected from a list: it is the dispatcher, and
		// the server's own scheduler is what starts it. There is no switch here
		// to report for it, so it answers null rather than a false that would
		// read as "switched off".
		$dispatched = (!empty($job['dispatch']) || !empty($job['inline']));

		$enabled = $dispatched
			? (function_exists('pg_cron_job_is_enabled') && pg_cron_job_is_enabled($name))
			: null;

		$last = ($known && isset($runs[$name])) ? (int)$runs[$name] : 0;

		// A job the operator has not switched on is not late; it is off.
		$stale = false;

		if ($known && ($enabled !== false)) {

			$stale = ($last === 0) || (($now - $last) > (int)$job['stale_after']);

		}

		$out[] = array(
			'name'        => $name,
			'label'       => $job['label'],
			'enabled'     => $enabled,
			'last_run_at' => ($known && ($last > 0)) ? api_time($last) : null,
			'stale'       => $stale
		);

	}

	return $out;

}

// What a file sent to this site may weigh, and what happens to an image when it
// arrives. Read before an upload rather than after a refusal.
function api_meta_uploads() {

	$out = array(
		'max_file_bytes'    => null,
		'max_request_bytes' => null,
		'max_json_bytes'    => null,
		'image'             => null
	);

	if (function_exists('pg_upload_limits')) {

		$limits = pg_upload_limits();

		$out['max_file_bytes']    = isset($limits['file_max']) ? (int)$limits['file_max'] : null;
		$out['max_request_bytes'] = isset($limits['request_max']) ? (int)$limits['request_max'] : null;
		$out['max_json_bytes']    = isset($limits['json_max']) ? (int)$limits['json_max'] : null;

	}

	if (function_exists('pg_image_settings')) {

		$image = pg_image_settings();

		$out['image'] = array(
			// Pixels on the longest side. An image sent larger than this is
			// scaled down on arrival when the product profile is used.
			'product_max_dimension' => isset($image['product_max_dimension']) ? (int)$image['product_max_dimension'] : null,
			'file_max_dimension'    => isset($image['file_max_dimension']) ? (int)$image['file_max_dimension'] : null,
			'quality'               => isset($image['resize_quality']) ? (int)$image['resize_quality'] : null
		);

	}

	return $out;

}

// How many minor units make one major unit. Almost every currency uses two
// decimal places; the exceptions matter because a client that divides by a
// hundred in Japan is out by a factor of a hundred.
function api_currency_minor_units($code) {

	$code = strtoupper(trim((string)$code));

	$zero_decimal = array('JPY', 'KRW', 'VND', 'CLP', 'ISK', 'XAF', 'XOF', 'XPF', 'RWF', 'UGX', 'PYG', 'DJF', 'GNF', 'KMF', 'MGA', 'BIF', 'VUV');

	$three_decimal = array('BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND');

	if (in_array($code, $zero_decimal, true)) {

		return 0;

	}

	if (in_array($code, $three_decimal, true)) {

		return 3;

	}

	return 2;

}

// What this endpoint returns, declared for the OpenAPI document. It is the one
// object with no presenter of its own - the handler builds it inline - so this
// is written against that handler and the four helpers it calls.
function api_meta_schema() {

	return array(
		'api_version' => 'integer',
		'software'    => 'string',
		'site' => array(
			'title'    => 'string',
			'hostname' => 'string'
		),
		'ecommerce' => array(
			'enabled'          => 'boolean',
			'taxable'          => 'boolean',
			'default_tax_rate' => 'number',
			'currency' => array(
				'code'         => 'string',
				'symbol'       => 'string',
				'minor_units'  => 'integer',
				'money_format' => 'string'
			)
		),
		'order_statuses' => 'string[]',
		'version' => array(
			'number'           => 'string',
			'channel'          => 'string',
			'update_available' => 'boolean'
		),
		'features' => array(
			'forms'               => 'boolean',
			'calendars'           => 'boolean',
			'ads'                 => 'boolean',
			'affiliate_program'   => 'boolean',
			'visitor_tracking'    => 'boolean',
			'chat'                => 'boolean',
			'barcode'             => 'boolean',
			'erp'                 => 'boolean',
			'mobile'              => 'boolean',
			'captcha'             => 'boolean',
			'performance_monitor' => 'boolean'
		),
		'security' => array(
			'firewall'          => 'string',
			'security_headers'  => 'boolean',
			'api_requires_https' => 'boolean'
		),
		'jobs' => array(array(
			'name'        => 'string',
			'label'       => 'string',
			'enabled'     => 'boolean?',
			'last_run_at' => 'string?',
			'stale'       => 'boolean'
		)),
		'uploads' => array(
			'max_file_bytes'    => 'integer',
			'max_request_bytes' => 'integer',
			'max_json_bytes'    => 'integer',
			'image' => array(
				'product_max_dimension' => 'integer',
				'file_max_dimension'    => 'integer',
				'quality'               => 'integer'
			)
		),
		'scopes'      => 'string[]',
		'server_time' => 'string'
	);

}
