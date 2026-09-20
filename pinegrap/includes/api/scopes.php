<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Every scope the external API knows, and the one place the write-implies-read
// rule is written down.
//
// The previous permission model stored the implication as data: choosing "edit"
// wrote both an edit row and a read row, and the check then implied read from
// edit a second time at request time. The same rule in two places drifts, and
// the stored copy meant the record no longer said what the operator actually
// chose. Here the operator's choice is stored verbatim and the implication is
// applied once, on the way in, by api_scopes_expand().

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {
	exit;
}

// Resource groups, in the order the permission screen shows them. 'read' or
// 'write' set to '' means the resource does not offer that half at all:
// inventory is write-only (stock is read through products), meta is read-only,
// forms are read-only.
// Offers are read and delete: a campaign that has run its course can be taken
// off the shop from outside, but writing one cannot: an offer is a rule with a
// condition tree and a result set behind it, and the editor that builds those
// is where the rules live. offers:write therefore means "may remove", which the
// endpoint's description says in as many words.
function api_scope_groups() {

	$groups = array(

		'products'  => array('label' => 'Products',   'read' => 'products:read',  'write' => 'products:write'),

		'inventory' => array('label' => 'Inventory',  'read' => '',               'write' => 'inventory:write'),

		'orders'    => array('label' => 'Orders',     'read' => 'orders:read',    'write' => 'orders:write'),

		'customers' => array('label' => 'Customers',  'read' => 'customers:read', 'write' => 'customers:write'),

		'pages'     => array('label' => 'Pages',      'read' => 'pages:read',     'write' => 'pages:write'),

		'offers'    => array('label' => 'Offers',     'read' => 'offers:read',    'write' => 'offers:write'),

		'files'     => array('label' => 'Files',      'read' => 'files:read',     'write' => 'files:write'),

		// Read only: a form is drawn in the page designer, with its validation,
		// upload folders and contact mapping. What an integration needs is what
		// came in on it.
		'forms'     => array('label' => 'Forms',      'read' => 'forms:read',     'write' => ''),

		// Read only: a finding is produced by the analyser, and the way to clear
		// one is to fix what it is about through the record's own endpoint.
		'seo'       => array('label' => 'SEO',        'read' => 'seo:read',       'write' => ''),

		'webhooks'  => array('label' => 'Webhooks',   'read' => '',               'write' => 'webhooks:manage'),

		'meta'      => array('label' => 'Site info',  'read' => 'meta:read',      'write' => ''),

		// The health report: the score and the checks behind it, for the
		// operator's own monitoring. Read only, and nothing here is sent
		// anywhere by the site itself.
		'system'    => array('label' => 'System',     'read' => 'system:read',    'write' => '')

	);

	// A module that brings endpoints brings the permission they sit behind. It
	// is offered only while the module is switched on: a key must not be able to
	// hold a permission for a module the site is not running.
	require_once(dirname(__FILE__) . '/modules.php');

	return $groups + api_module_contributions('scope_groups');

}

// Flat list of valid scope strings. Anything not in here is not a scope, no
// matter what arrives in a form post.
function api_scopes_all() {

	$all = array();

	foreach (api_scope_groups() as $group) {

		if ($group['read'] !== '') {

			$all[] = $group['read'];

		}

		if ($group['write'] !== '') {

			$all[] = $group['write'];

		}

	}

	return $all;

}

function api_scope_is_valid($scope) {

	return in_array($scope, api_scopes_all(), true);

}

// What gets stored: exactly what was chosen, minus anything unknown. The
// permission screen posts arbitrary strings like any other form, so this is the
// gate that keeps invented scopes out of the record.
function api_scopes_normalise($scopes) {

	if (!is_array($scopes)) {

		return array();

	}

	$clean = array();

	foreach ($scopes as $scope) {

		$scope = strtolower(trim((string)$scope));

		if (api_scope_is_valid($scope) && !in_array($scope, $clean, true)) {

			$clean[] = $scope;

		}

	}

	sort($clean);

	return $clean;

}

// What gets checked: the stored set plus the reads that each granted write
// implies. Called once per request, never stored.
function api_scopes_expand($scopes) {

	$expanded = api_scopes_normalise($scopes);

	foreach (api_scope_groups() as $group) {

		if ($group['write'] === '' || $group['read'] === '') {

			continue;

		}

		if (in_array($group['write'], $expanded, true) && !in_array($group['read'], $expanded, true)) {

			$expanded[] = $group['read'];

		}

	}

	sort($expanded);

	return $expanded;

}

function api_has_scope($granted, $needed) {

	if ($needed === '') {

		return true;

	}

	return is_array($granted) && in_array($needed, $granted, true);

}

// What the application's owner is allowed to delegate.
//
// An application never carries more than the person who owns it. The rules are
// the panel's own - validate_ecommerce_access() and validate_area_access() -
// deliberately reused rather than restated, so an operator never finds the API
// stricter or looser than the screens they already know. Loose comparison on
// the manage_* flags is that reuse: those columns hold 'yes' in some releases
// and '1' in others, and the panel has always compared them this way.
//
// The intersection is computed on every request, not stored, so demoting a user
// narrows their applications immediately.
function api_owner_scopes($owner) {

	$role = (int)$owner['role'];

	$scopes = array();

	$store_is_on = defined('ECOMMERCE') && ECOMMERCE === true;

	$manages_commerce = ($role < 3) || ($owner['manage_ecommerce'] == true);

	$manages_contacts = ($role < 3) || ($owner['manage_contacts'] == true);

	if ($store_is_on && $manages_commerce) {

		$scopes[] = 'products:read';
		$scopes[] = 'products:write';
		$scopes[] = 'inventory:write';
		$scopes[] = 'orders:read';
		$scopes[] = 'orders:write';
		$scopes[] = 'offers:read';
		$scopes[] = 'offers:write';

	}

	if ($manages_contacts) {

		$scopes[] = 'customers:read';

		// The address book is the same right in both directions: whoever may
		// open a contact in the panel may let a machine add one.
		$scopes[] = 'customers:write';

	}

	// Form submissions are the site's own incoming post: names, e-mail
	// addresses, messages and whatever was uploaded with them. The panel opens
	// them to role < 3 or to the manage-forms right, and what may be handed to a
	// machine is never wider than what the person could open themselves.
	if (($role < 3) || ($owner['manage_forms'] == true)) {

		$scopes[] = 'forms:read';

	}

	// Pages are the site itself: a contributor may hold edit rights on
	// individual pages, but delegating page writes to a machine is a
	// manager-and-above decision: roles 0 to 2, the panel's 'manager' gate.
	if ($role <= 2) {

		$scopes[] = 'pages:read';
		$scopes[] = 'pages:write';

		// Files are the same decision. An upload is the one thing an outside
		// system can leave on the server, and what may be uploaded is decided
		// by the same person who decides what the pages say.
		$scopes[] = 'files:read';
		$scopes[] = 'files:write';

		// The SEO findings are about those same pages and the catalogue beside
		// them, so they are the same decision.
		$scopes[] = 'seo:read';

	}

	// Webhooks hand site events to a third-party address. Only an administrator
	// or a designer (roles 0 to 1, the panel's 'designer' gate) may delegate that;
	// a manager can pause, retry and remove existing subscriptions on the Events
	// tab of api_settings.php but cannot hand the right to an app.
	if ($role <= 1) {

		$scopes[] = 'webhooks:manage';

		// The health report names the firewall's state, which jobs stopped
		// moving, how much disk is gone and which files fail their integrity
		// check. That is the operator's own diagnostic detail, so the right to
		// hand it to a machine sits with the same roles that may hand out
		// events.
		$scopes[] = 'system:read';

	}

	// Site name, currency, tax rate, order status vocabulary. Read-only and
	// needed by every integration, so every owner may delegate it.
	$scopes[] = 'meta:read';

	// What the modules allow this owner, asked of them rather than decided
	// here: the right behind an ERP scope is an ERP right, and this file has
	// never heard of it. See includes/api/modules.php.
	require_once(dirname(__FILE__) . '/modules.php');

	foreach (api_module_contributions('owner_scopes', $owner) as $scope) {

		if (!in_array($scope, $scopes, true)) {

			$scopes[] = $scope;

		}

	}

	sort($scopes);

	return $scopes;

}

// The set actually in force for a request: what the application was granted,
// widened by the write-implies-read rule, then narrowed to what its owner may
// still delegate today.
function api_effective_scopes($granted, $owner) {

	$expanded = api_scopes_expand($granted);

	$allowed  = api_owner_scopes($owner);

	return array_values(array_intersect($expanded, $allowed));

}
