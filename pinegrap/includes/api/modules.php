<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// What a module adds to the API.
//
// A module that has its own records - the ERP module and its invoices, receipts
// and ageing - needs its own endpoints, its own events and its own objects in
// the description. Without a seam it gets them by editing schema.php,
// webhooks.php, scopes.php and openapi.php, which are the API's own files: two
// people then work in the same four files at once and every release starts with
// a merge.
//
// The seam is one file per module, in the module's own folder, declaring up to
// four things:
//
//   <prefix>api_routes()       route rows, written exactly as schema.php writes
//                              its own, handlers included
//   <prefix>webhook_events()   event name => one line saying what it announces
//   <prefix>openapi_objects()  object name => the function that declares its
//                              fields, like api_openapi_objects() does
//   <prefix>scope_groups()     permission rows for the application screen
//
// Each one is optional and what is missing is simply not merged, so a module can
// start with routes and grow events later without touching anything here.
//
// A module that is installed but switched off contributes nothing: its
// endpoints are not in the description, its events cannot be subscribed to and
// its permissions are not offered. That is deliberate - a key must not be able
// to hold a permission for a module the site is not running.
//
// Nothing a module contributes can replace something the API already has: the
// merge keeps the API's own entry when both use the same name.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL') && !defined('PG_INIT_LOADED')) {

	exit;

}

// The modules that may extend the API, with the switch that says whether the
// site is running them.
function api_modules() {

	return array(

		'erp' => array(
			'enabled' => (defined('ERP_ENABLED') && ERP_ENABLED == true),
			'file'    => dirname(__FILE__) . '/../erp/api.php',
			'prefix'  => 'erp_'
		),

		'workspace' => array(
			'enabled' => (defined('WORKSPACE_ENABLED') && WORKSPACE_ENABLED == true),
			'file'    => dirname(__FILE__) . '/../workspace/api.php',
			'prefix'  => 'ws_'
		)

	);

}

// Loads the contribution files once per request. A module whose file is not
// there yet - the usual state while it is being written - is skipped without a
// word, so this can be wired up before the file on the other side exists.
function api_modules_load() {

	static $loaded = false;

	if ($loaded) {

		return;

	}

	$loaded = true;

	foreach (api_modules() as $module) {

		if (!$module['enabled']) {

			continue;

		}

		if (!file_exists($module['file'])) {

			continue;

		}

		require_once($module['file']);

	}

}

// Everything the enabled modules declare of one kind, in one array.
//
// $kind is the part after the prefix: 'api_routes', 'webhook_events',
// 'openapi_objects', 'scope_groups' or 'owner_scopes'.
//
// $argument is handed to the declaring function when the kind needs one -
// 'owner_scopes' is asked about a particular account, because a module caps its
// scopes at the right that account holds for it.
function api_module_contributions($kind, $argument = null) {

	api_modules_load();

	$out = array();

	foreach (api_modules() as $module) {

		if (!$module['enabled']) {

			continue;

		}

		$declare = $module['prefix'] . $kind;

		if (!function_exists($declare)) {

			continue;

		}

		$contribution = ($argument === null)
			? call_user_func($declare)
			: call_user_func($declare, $argument);

		if (!is_array($contribution)) {

			continue;

		}

		foreach ($contribution as $key => $value) {

			if (is_int($key)) {

				$out[] = $value;

			} elseif (!isset($out[$key])) {

				$out[$key] = $value;

			}

		}

	}

	return $out;

}
