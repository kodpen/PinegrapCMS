<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Reports the calling agent's model and plan usage to a site with its own
 * application key (POST /workspace/agent-report); the site's Agents card
 * shows the last report with its age. How the session reads its plan's
 * limits is its own: this tool only sends what it is given.
 *
 * Usage:  php tools/ws_profile.php [--model=NAME]
 *                                  [--windows=NAME:PERCENT:RESETS[,…]]
 *                                  [--skip-missing]
 *         NAME of a window is five_hour, daily, weekly, weekly_model or
 *         monthly; PERCENT a whole number 0-100; RESETS ISO-8601 or a unix
 *         timestamp, e.g. --windows=five_hour:42:2026-11-13T22:00:00Z,weekly:61:1795000000
 *         A report needs --model or --windows; leave out what cannot be read.
 * Exit:   0 reported (or skipped with --skip-missing while settings are
 *         missing); 1 the site refused or could not be reached; 2 usage or
 *         missing settings.
 *
 * Settings come from the environment (tools/ws_client.php): PG_WS_SITE,
 * PG_WS_KEY, PG_WS_SECRET; no channel is needed. Nothing secret is printed.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Forbidden');
}

require_once(__DIR__ . '/ws_client.php');

/**
 * The request body of a report, or an error line. The site checks the
 * values again and refuses the whole report on any fault; this only shapes
 * them and catches what is plainly wrong before a call.
 *
 * @param array<string,string|bool> $options
 * @return array{body: array<string,mixed>|null, error: string}
 */
function ws_profile_body($options)
{
	$body = array();
	$model = isset($options['model']) && is_string($options['model']) ? trim($options['model']) : '';

	if ($model !== '') {
		// The route's own limit (80 characters).
		if ((function_exists('mb_strlen') ? mb_strlen($model, 'UTF-8') : strlen($model)) > 80) {
			return array('body' => null, 'error' => 'the model is longer than 80 characters');
		}

		$body['model'] = $model;
	}

	$given = isset($options['windows']) && is_string($options['windows']) ? trim($options['windows']) : '';

	if ($given !== '') {
		$windows = array();

		foreach (explode(',', $given) as $part) {
			// NAME:PERCENT:RESETS; an ISO time keeps its own colons.
			$fields = explode(':', trim($part), 3);

			if (count($fields) !== 3 || !preg_match('/^[a-z_]+$/', $fields[0]) || !ctype_digit($fields[1]) || (int) $fields[1] > 100 || trim($fields[2]) === '') {
				return array('body' => null, 'error' => 'a window is NAME:PERCENT:RESETS, e.g. weekly:61:2026-11-18T09:00:00Z');
			}

			$resets = trim($fields[2]);
			$windows[] = array('name' => $fields[0], 'percent' => (int) $fields[1], 'resets_at' => ctype_digit($resets) ? (int) $resets : $resets);
		}

		if (count($windows) > 4) {
			return array('body' => null, 'error' => 'at most four windows');
		}

		$body['windows'] = $windows;
	}

	if (!$body) {
		return array('body' => null, 'error' => 'give --model, --windows or both');
	}

	return array('body' => $body, 'error' => '');
}

function ws_profile_main($argv)
{
	list($options) = ws_client_options($argv);
	$settings = ws_client_settings($options, getenv(), false);

	if ($settings['missing']) {
		if (!empty($options['skip-missing'])) {
			fwrite(STDOUT, 'ws_profile: skipped, not set: ' . implode(', ', $settings['missing']) . "\n");
			return WS_CLIENT_EXIT_OK;
		}

		fwrite(STDERR, 'ws_profile: not set: ' . implode(', ', $settings['missing']) . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$built = ws_profile_body($options);

	if ($built['body'] === null) {
		fwrite(STDERR, 'ws_profile: ' . $built['error'] . "\n");
		return WS_CLIENT_EXIT_USAGE;
	}

	$answer = ws_client_request('POST', $settings['site'] . '/workspace/agent-report', $settings, $built['body']);

	if ($answer['status'] < 200 || $answer['status'] >= 300) {
		fwrite(STDERR, 'ws_profile: ' . ws_client_failure($answer) . "\n");
		return WS_CLIENT_EXIT_HTTP;
	}

	fwrite(STDOUT, "reported\n");

	return WS_CLIENT_EXIT_OK;
}

if (!defined('PG_TEST_RUNNER')) {
	exit(ws_profile_main(array_slice($argv, 1)));
}
