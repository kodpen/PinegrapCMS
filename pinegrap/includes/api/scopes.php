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
// inventory is write-only (stock is read through products), meta is read-only.
// offers:write covers the offer's own row - switched on or off, its message,
// its dates - and its removal; building one cannot be done from outside: an
// offer is a rule with a condition tree and a result set behind it, and the
// editor that builds those is where the rules live. The endpoint descriptions
// say so in as many words.
function api_scope_groups() {

	$groups = array(

		'products'  => array('label' => 'Products',   'read' => 'products:read',  'write' => 'products:write'),

		'inventory' => array('label' => 'Inventory',  'read' => '',               'write' => 'inventory:write'),

		'orders'    => array('label' => 'Orders',     'read' => 'orders:read',    'write' => 'orders:write'),

		'customers' => array('label' => 'Customers',  'read' => 'customers:read', 'write' => 'customers:write'),

		'pages'     => array('label' => 'Pages',      'read' => 'pages:read',     'write' => 'pages:write'),

		// The layout of the Visual Page Editor's pages. Writing a layout means
		// proposing: a change waits until a designer applies it, so an
		// application never rewrites a layout by itself. It may add a page as a
		// draft, set when a page goes on the site and comes off it, and put back
		// a layout a person published before (a revision).
		'design'    => array('label' => 'Page design', 'read' => 'design:read',   'write' => 'design:write'),

		// What visitors wrote under pages, records, products and events.
		// Writing is moderation: publishing, holding, featuring, removing.
		'comments'  => array('label' => 'Comments',   'read' => 'comments:read',  'write' => 'comments:write'),

		'offers'    => array('label' => 'Offers',     'read' => 'offers:read',    'write' => 'offers:write'),

		// A key code gets its offer at checkout like the offer's own code, so
		// minting one is handing out a discount, and reading them is holding
		// codes a shopper could redeem: neither rides on offers. Writing makes
		// codes for an existing offer, changes or removes one.
		'key_codes' => array('label' => 'Key Codes',  'read' => 'key_codes:read', 'write' => 'key_codes:write'),

		// A gift card's code is spendable money, so it is not read under
		// orders: an integration reporting sales has no business holding the
		// codes. Writing issues a card, changes its balance or expiry, or
		// removes it.
		'gift_cards' => array('label' => 'Gift Cards', 'read' => 'gift_cards:read', 'write' => 'gift_cards:write'),

		// What the affiliate program owes its affiliates. Read only: the
		// status is moved on the commissions screen, and the paying happens
		// outside the site.
		'commissions' => array('label' => 'Commissions', 'read' => 'commissions:read', 'write' => ''),

		'files'     => array('label' => 'Files',      'read' => 'files:read',     'write' => 'files:write'),

		// Writing is an event's words, times, calendars and publishing. Its
		// repeats, reservation spots, rooms and the product they are sold
		// through stay on its own screen.
		'calendars' => array('label' => 'Calendars',  'read' => 'calendars:read', 'write' => 'calendars:write'),

		// A form is drawn in the page designer, with its validation, upload
		// folders and contact mapping, and that stays there. What an
		// integration needs is what came in on it; writing means what the
		// submitted-form screen writes - complete or not, and the text answers.
		'forms'     => array('label' => 'Forms',      'read' => 'forms:read',     'write' => 'forms:write'),

		// Read only: a finding is produced by the analyser, and the way to clear
		// one is to fix what it is about through the record's own endpoint. The
		// 404 log is read the same way; a redirect for an address in it is a
		// short link (POST /short-links, pages:write).
		'seo'       => array('label' => 'SEO',        'read' => 'seo:read',       'write' => ''),

		'webhooks'  => array('label' => 'Webhooks',   'read' => '',               'write' => 'webhooks:manage'),

		'meta'      => array('label' => 'Site info',  'read' => 'meta:read',      'write' => ''),

		// The health report: the score and the checks behind it, for the
		// operator's own monitoring. Read only, and nothing here is sent
		// anywhere by the site itself.
		'system'    => array('label' => 'System',     'read' => 'system:read',    'write' => ''),

		// Traffic, added up: visits, page views, what was viewed most and where
		// visits came from. Never a single visit, which carries an address and
		// a place.
		'visitors'  => array('label' => 'Visitors',   'read' => 'visitors:read',  'write' => ''),

		// E-mail campaigns and who they go to. Writing holds, releases,
		// cancels, corrects or removes one; putting one together, recipients
		// and all, stays on its own screen.
		'campaigns' => array('label' => 'Campaigns',  'read' => 'campaigns:read', 'write' => 'campaigns:write'),

		// The lists campaigns go to and who is on them. Writing makes, changes
		// and removes a group (an empty one only), puts contacts in or takes
		// them out and records their choice for the group's e-mail.
		'contact_groups' => array('label' => 'Contact Groups', 'read' => 'contact_groups:read', 'write' => 'contact_groups:write'),

		// Who did what on the site, from which address. Read only.
		'site_log'  => array('label' => 'Site Log',   'read' => 'site_log:read',  'write' => ''),

		// The caller's own account: its notifications, the devices it is signed
		// in on and its push registration. A device signed in by a person always
		// holds it; an application only when the operator gives it, because an
		// integration syncing stock has no business reading its owner's bell.
		'account'   => array('label' => 'Own account', 'read' => 'account:read', 'write' => 'account:write')

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

		// The gift cards screen opens to the same people.
		$scopes[] = 'gift_cards:read';
		$scopes[] = 'gift_cards:write';

		// And the key code screens.
		$scopes[] = 'key_codes:read';
		$scopes[] = 'key_codes:write';

		// The commissions screen opens to the same people.
		$scopes[] = 'commissions:read';

	}

	if ($manages_contacts) {

		$scopes[] = 'customers:read';

		// The address book is the same right in both directions: whoever may
		// open a contact in the panel may let a machine add one.
		$scopes[] = 'customers:write';

		// The contact group screens open to the same people; below the
		// manager role a user reaches only the groups given to it, which the
		// endpoints filter for.
		$scopes[] = 'contact_groups:read';
		$scopes[] = 'contact_groups:write';

	}

	// Form submissions are the site's own incoming post: names, e-mail
	// addresses, messages and whatever was uploaded with them. The panel opens
	// them to role < 3 or to the manage-forms right, and what may be handed to a
	// machine is never wider than what the person could open themselves.
	if (($role < 3) || ($owner['manage_forms'] == true)) {

		$scopes[] = 'forms:read';

		// The submitted-form screen opens its edit to the same right.
		$scopes[] = 'forms:write';

	}

	// The calendars, to whoever the calendar screens open them to: roles 0 to
	// 2, or a user given the calendars right - who then reads and writes only
	// the calendars assigned to them, which the endpoints filter for, and
	// publishes only with the publishing right. Not offered where the site
	// does not run its calendars.
	$manages_calendars = ($role < 3)
		|| (isset($owner['manage_calendars']) && in_array(strtolower(trim((string)$owner['manage_calendars'])), array('yes', '1', 'true'), true));

	if ((!defined('CALENDARS') || CALENDARS === true) && $manages_calendars) {

		$scopes[] = 'calendars:read';

		// The event screens open to the same people.
		$scopes[] = 'calendars:write';

	}

	// The visitor screens open to roles 0 to 2 or the visitors right
	// (validate_visitors_access()).
	if (($role < 3)
		|| (isset($owner['manage_visitors']) && in_array(strtolower(trim((string)$owner['manage_visitors'])), array('yes', '1', 'true'), true))) {

		$scopes[] = 'visitors:read';

	}

	// The campaign screens open to roles 0 to 2 or the e-mail right
	// (validate_email_access()); below the manager role a user reaches only
	// the campaigns it created, which the endpoints filter for.
	if (($role < 3)
		|| (isset($owner['manage_emails']) && in_array(strtolower(trim((string)$owner['manage_emails'])), array('yes', '1', 'true'), true))) {

		$scopes[] = 'campaigns:read';
		$scopes[] = 'campaigns:write';

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

		// Moderating what visitors wrote under those pages is the comments
		// screen's work, which the same people do.
		$scopes[] = 'comments:read';
		$scopes[] = 'comments:write';

		// The Site Log screen opens to the same roles.
		$scopes[] = 'site_log:read';

	}

	// Webhooks hand site events to a third-party address. Only an administrator
	// or a designer (roles 0 to 1, the panel's 'designer' gate) may delegate that;
	// a manager can pause, retry and remove existing subscriptions on the Events
	// tab of api_settings.php but cannot hand the right to an app.
	if ($role <= 1) {

		$scopes[] = 'webhooks:manage';

		// A page's layout is the designers' own work (the editor opens its
		// structure to roles 0 and 1 only), so only they may let a machine
		// read it and put changes in front of them.
		$scopes[] = 'design:read';
		$scopes[] = 'design:write';

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

	// Everybody's own bell and devices are their own to hand over.
	$scopes[] = 'account:read';
	$scopes[] = 'account:write';

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

// What the key an assistant's card makes is granted, one choice per group the
// way the permission screen stores it: the write for the Workspace and the
// Tasks, whose reads the write includes, and the read of every other group
// unless it is left out. No other write, and never webhooks:manage: the
// assistant changes records only through drafts a person applies.
//
// $groups is api_scope_groups(); the caller still narrows the result to what
// the person making the key may delegate (api_owner_scopes()), and every call
// is capped again by api_effective_scopes().
function api_assistant_scopes(array $groups, array $leave_out = array()) {

	$scopes = array();

	foreach ($groups as $group) {

		$read  = isset($group['read']) ? (string)$group['read'] : '';
		$write = isset($group['write']) ? (string)$group['write'] : '';

		if (in_array($write, array('workspace:write', 'tasks:write'), true)) {

			$scopes[] = $write;

		} elseif (($read !== '') && !in_array($read, $leave_out, true)) {

			$scopes[] = $read;

		}

	}

	$scopes = array_values(array_unique($scopes));

	sort($scopes);

	return $scopes;

}

// The reads an assistant's key is made without. Neither assistant needs them:
// the owner's own bell and devices, codes a shopper could redeem, spendable
// gift card codes, who did what from which address, and the firewall and
// file integrity state. An administrator can still add any of them to the key
// in Application Access.
function api_assistant_scopes_left_out() {

	return array('account:read', 'key_codes:read', 'gift_cards:read', 'site_log:read', 'system:read');

}

// What of an assistant key's scopes ($scopes, api_assistant_scopes()) the
// person making it may hand on ($owner_scopes, api_owner_scopes() of that
// person): the scopes both lists hold, in the key's order. An empty list when
// that leaves out writing in the Workspace or the Tasks, which an assistant
// cannot work without, so the card makes no key at all.
function api_assistant_scopes_handed_on(array $scopes, array $owner_scopes) {

	$handed = array_values(array_intersect($scopes, $owner_scopes));

	if (!in_array('workspace:write', $handed, true) || !in_array('tasks:write', $handed, true)) {

		return array();

	}

	return $handed;

}
