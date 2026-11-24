<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The permission picker of Application Access, drawn in New application's
 * sheet and in an application's drawer: a bar with the search, the count
 * and the four bulk buttons, then one section per area (store, content,
 * design, Workspace, accounting, site), each permission a row with its icon,
 * its line and a None / Read / Write control. assets/js/api_app_access.js
 * runs it.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_PANEL')) {
	exit;
}

// The areas the rows are grouped under, in the order the picker shows them:
// key => array(title, Bootstrap icon, .pg-tone-* of the icon tile).
function api_app_permission_areas() {

	return array(
		'store'      => array(lang('Store'),      'bi-bag',             'shop'),
		'content'    => array(lang('Content'),    'bi-file-earmark-text', 'content'),
		'design'     => array(lang('Design'),     'bi-palette',         'violet'),
		'workspace'  => array(lang('Workspace'),  'bi-chat-square-text', 'team'),
		'accounting' => array(lang('Accounting'), 'bi-calculator',      'accounting'),
		'site'       => array(lang('Site'),       'bi-globe2',          'system')
	);

}

// The area of a scope group: the group's own 'area' when a module names one,
// else the table below; a group the table does not know sits under Site.
function api_app_permission_area($key, $group = array()) {

	if (isset($group['area']) && array_key_exists((string)$group['area'], api_app_permission_area_keys())) {

		return (string)$group['area'];

	}

	$keys = api_app_permission_area_keys();

	return isset($keys[$key]) ? $keys[$key] : 'site';

}

// Group key => area, for the core groups and the modules' groups. A test
// keeps every group of api_scope_groups() and of the modules in it.
function api_app_permission_area_keys() {

	return array(
		'products'           => 'store',
		'inventory'          => 'store',
		'orders'             => 'store',
		'store'              => 'store',
		'customers'          => 'store',
		'offers'             => 'store',
		'key_codes'          => 'store',
		'gift_cards'         => 'store',
		'commissions'        => 'store',
		'bookings'           => 'store',
		'queue'              => 'store',
		'pages'              => 'content',
		'comments'           => 'content',
		'files'              => 'content',
		'forms'              => 'content',
		'calendars'          => 'content',
		'content_review'     => 'content',
		'campaigns'          => 'content',
		'contact_groups'     => 'content',
		'fediverse'          => 'content',
		'translations'       => 'content',
		'design'             => 'design',
		'experiments'        => 'design',
		'workspace'          => 'workspace',
		'workspace_commands' => 'workspace',
		'workspace_mail'     => 'workspace',
		'tasks'              => 'workspace',
		'personal_plans'     => 'workspace',
		'erp'                => 'accounting',
		'erp_cash'           => 'accounting',
		'seo'                => 'site',
		'webhooks'           => 'site',
		'meta'               => 'site',
		'system'             => 'site',
		'visitors'           => 'site',
		'users'              => 'site',
		'site_log'           => 'site',
		'privacy'            => 'site',
		'account'            => 'site'
	);

}

// The name, the line saying what the row lets the application do, and the
// icon of a scope group: array(name, line, Bootstrap icon).
function api_settings_group_text($key, $group = array()) {

	$text = array(
		'products'  => array(lang('Products'),   lang('Reading the catalogue; writing products, product groups and product attributes, and deleting them'), 'bi-box-seam'),
		'inventory' => array(lang('Inventory'),  lang('Setting a quantity and adjusting it - reading is under Products'), 'bi-grid-3x3-gap'),
		'orders'    => array(lang('Orders'),     lang('Reading orders and their lines, and the shipping methods, zones, taxes, countries and currencies they are computed with; writing status and notes'), 'bi-cart3'),
		'store'     => array(lang('Store settings'), lang('Writing the shipping methods, shipping zones and tax zones checkout charges by - reading them is under Orders'), 'bi-geo-alt'),
		'customers' => array(lang('Customers'),  lang('The people who buy (address book records); writing includes deleting one'), 'bi-person-lines-fill'),
		'pages'     => array(lang('Pages'),      lang('Page details, comment settings, folders, menus and short links'), 'bi-file-earmark'),
		'design'    => array(lang('Page design'), lang('The layout of the Page Design Studio\'s pages and the site\'s ads; writing means proposing a change a designer applies, adding a page as a draft, setting when a page goes on the site and comes off it, putting back a published revision, sharing the drafts as a prototype by a link, or adding, changing and removing ads and ad regions, which go live at once'), 'bi-palette'),
		'experiments' => array(lang('Experiments'), lang('A/B experiments of the designed pages and their results; writing starts, pauses or ends one'), 'bi-signpost-split'),
		'bookings'  => array(lang('Bookings'),   lang('The appointment bookings of the staff agenda; writing books a free time for a customer, moves a booking\'s status or cancels one'), 'bi-calendar-check'),
		'queue'     => array(lang('Queue'),      lang('The walk-in queue\'s places and numbers; writing takes a number for a visitor, calls the next number to an open desk or finishes one'), 'bi-people'),
		'comments'  => array(lang('Comments'),   lang('What visitors wrote under pages, records, products and events; writing means publishing, holding, featuring or removing one'), 'bi-chat-left-text'),
		'offers'    => array(lang('Offers'),     lang('Reading campaign rules; writing means switching one on or off, moving its dates or removing it, not creating it'), 'bi-file-earmark-text'),
		'key_codes' => array(lang('Key Codes'),  lang('The codes shoppers type instead of an offer\'s own code; writing makes codes for an existing offer, changes or removes one'), 'bi-key'),
		'gift_cards' => array(lang('Gift Cards'), lang('The cards, their codes, balances and where they were spent; writing issues a card, changes its balance or expiry, or removes it'), 'bi-gift'),
		'commissions' => array(lang('Commissions'), lang('What the affiliate program owes each affiliate, with its status and the order it came from - read only'), 'bi-cash-coin'),
		'files'     => array(lang('Files'),      lang('Reading the file list; uploading new files to one folder and describing them'), 'bi-folder'),
		'forms'     => array(lang('Forms'),      lang('Which forms the site has and what visitors filled in; writing marks a submission complete and fills in its text answers'), 'bi-ui-checks'),
		'calendars' => array(lang('Calendars'),  lang('The calendars and the events on them; writing adds, changes, publishes or removes an event, not its repeats or reservations'), 'bi-calendar-event'),
		'seo'       => array(lang('SEO'),        lang('What the analysis found across the site and the addresses visitors did not find, as lists to work through'), 'bi-graph-up-arrow'),
		'content_review' => array(lang('Content review'), lang('Who reads a page or collection record again, and by when; writing sets a page\'s or record\'s review'), 'bi-clipboard-check'),
		'webhooks'  => array(lang('Webhooks'),   lang('Registering, changing and testing an address for event notifications'), 'bi-megaphone'),
		'meta'      => array(lang('Site info'),  lang('Currency, tax and order statuses - read only'), 'bi-info-circle'),
		'system'    => array(lang('System'),     lang('The health score and the checks behind it, for your own monitoring, and the audit trail'), 'bi-activity'),
		'visitors'  => array(lang('Visitors'),   lang('Visits and page views, the most viewed content and where visits came from, as totals - never a single visit'), 'bi-bar-chart'),
		'users'     => array(lang('Users'),      lang('The panel\'s accounts, their roles and rights, never a password; writing opens, changes and closes accounts, held by an administrator only'), 'bi-person-gear'),
		'privacy'   => array(lang('Privacy'),    lang('The personal data inventory with its transfers, and the requests people sent about their data - read only'), 'bi-shield-lock'),
		'site_log'  => array(lang('Site Log'),   lang('Who did what on the site and from which address - read only'), 'bi-journal-text'),
		'fediverse' => array(lang('Fediverse'),  lang('The Fediverse account\'s followers and what was delivered to them - read only'), 'bi-mastodon'),
		'campaigns' => array(lang('Campaigns'),  lang('E-mail campaigns, how far sending has got and who they go to; writing holds, releases, cancels, corrects or removes one, not putting one together'), 'bi-envelope'),
		'contact_groups' => array(lang('Contact Groups'), lang('The lists campaigns go to and who is on them; writing makes, renames and removes empty groups, puts contacts in or takes them out and records their e-mail choice'), 'bi-people'),
		'account'   => array(lang('Own account'), lang('Notifications, signed-in devices and push registration of the account the calls act for'), 'bi-person-badge')
	);

	if (isset($text[$key])) {

		return $text[$key];

	}

	// A group a module contributed (see includes/api/modules.php). It carries
	// its own wording, because this screen cannot know what an ERP permission
	// covers - and "Erp" with an empty line under it is what the operator would
	// otherwise be asked to grant.
	return array(
		isset($group['label']) && ($group['label'] !== '') ? $group['label'] : ucfirst($key),
		isset($group['description']) ? $group['description'] : '',
		isset($group['icon']) && ($group['icon'] !== '') ? $group['icon'] : 'bi-key'
	);

}

// Which level of a row holds a scope that moves money: 'read' when its read
// scope does (then any choice holds it), 'write' when only its write scope
// does, '' when neither. $money_scopes is api_app_money_scopes(). Pure.
function api_app_permission_money($group, $money_scopes) {

	$money_scopes = (array)$money_scopes;

	if (!empty($group['read']) && in_array($group['read'], $money_scopes, true)) {

		return 'read';

	}

	if (!empty($group['write']) && in_array($group['write'], $money_scopes, true)) {

		return 'write';

	}

	return '';

}

// The scopes of every route that moves money, the core's and the switched-on
// modules', worked out once a request.
function api_app_permission_money_scopes() {

	static $scopes = null;

	if ($scopes === null) {

		$routes = api_schema();

		if (function_exists('api_module_contributions')) {

			$routes = array_merge($routes, api_module_contributions('api_routes'));

		}

		$scopes = api_app_money_scopes($routes);

	}

	return $scopes;

}

// One permission row. The posted name is $prefix[group]; the element ids
// start with $id_prefix, so the two copies on the page never share an id.
// $money is api_app_permission_money() of the row.
function api_app_permission_row_html($key, $group, $prefix, $id_prefix, $money) {

	$text = api_settings_group_text($key, $group);

	$name = $prefix . '[' . $key . ']';

	$id = $id_prefix . '_' . $key;

	$segment = function ($value, $label) use ($name, $id) {
		$suffix = ($value === '') ? 'none' : $value;
		return '<label class="pg-seg-item" for="' . $id . '_' . $suffix . '">'
			. '<input type="radio" class="visually-hidden" name="' . h($name) . '" id="' . $id . '_' . $suffix . '" value="' . $value . '"' . (($value === '') ? ' checked' : '') . '>'
			. h($label) . '</label>';
	};

	// A row with one level offers that level alone beside None.
	$control = $segment('', lang('None'));

	if ($group['read'] !== '') {

		$control .= $segment('read', lang('Read'));

	}

	if ($group['write'] !== '') {

		$control .= $segment('write', ($key === 'webhooks') ? lang('Manage') : lang('Write'));

	}

	$pill = ($money !== '')
		? ' <small class="pg-pill pg-tone-amber api-pick-money">' . lang('Moves money') . '</small>'
		: '';

	return '
		<div class="api-perm" data-group="' . h($key) . '" data-read="' . (($group['read'] !== '') ? 1 : 0) . '" data-write="' . (($group['write'] !== '') ? 1 : 0) . '" data-money="' . $money . '">
			<i class="bi ' . h($text[2]) . '" aria-hidden="true"></i>
			<div class="api-perm-text"><b id="' . $id . '_label">' . h($text[0]) . $pill . '</b><span>' . h($text[1]) . '</span></div>
			<div class="pg-seg api-pick-seg" role="radiogroup" aria-labelledby="' . $id . '_label">' . $control . '</div>
		</div>';

}

// The four bulk buttons as plain buttons (the bar) or as menu entries (an
// area's menu). An area's entries carry the area they act on.
function api_app_permission_bulk_html($menu, $area = '') {

	$labels = array('all' => lang('All'), 'none' => lang('None'), 'read' => lang('All read'), 'write' => lang('All write'));

	$html = '';

	foreach ($labels as $which => $label) {

		// All and All write tick the rows that move money; the count of them
		// is written beside the button by the script.
		$count = (($which === 'all') || ($which === 'write'))
			? ' <span class="pg-pill pg-tone-amber api-pick-money" data-money-count hidden title="' . h(lang('Permissions that move money')) . '"><span></span></span>'
			: '';

		$data = ' data-bulk="' . $which . '"' . (($area !== '') ? ' data-area="' . h($area) . '"' : '');

		$html .= $menu
			? '<li><button type="button" class="menu-item"' . $data . '>' . h($label) . $count . '</button></li>'
			: '<button type="button" class="pg-button pg-button-ghost pg-button-sm"' . $data . '>' . h($label) . $count . '</button>';

	}

	return $html;

}

// The whole picker for $groups (api_scope_groups()). $prefix names the posted
// fields (scope[group]), $id_prefix the elements; $money_scopes is
// api_app_money_scopes().
function api_app_permission_picker_html($groups, $prefix, $id_prefix, $money_scopes) {

	$areas = api_app_permission_areas();

	$rows = array_fill_keys(array_keys($areas), '');

	foreach ($groups as $key => $group) {

		$rows[api_app_permission_area($key, $group)] .= api_app_permission_row_html($key, $group, $prefix, $id_prefix, api_app_permission_money($group, $money_scopes));

	}

	$sections = '';

	foreach ($areas as $area => $head) {

		if ($rows[$area] === '') {

			continue;

		}

		$title_id = $id_prefix . '_area_' . $area;

		$sections .= '
	<section class="api-pick-area" data-area="' . $area . '" aria-labelledby="' . $title_id . '">
		<div class="api-pick-head">
			<span class="pg-icon-tile pg-tone-' . $head[2] . '" aria-hidden="true"><i class="bi ' . $head[1] . '"></i></span>
			<b id="' . $title_id . '">' . h($head[0]) . '</b>
			<span class="small fg-2" data-area-count></span>
			<span class="flex-grow-1"></span>
			<div class="pg-menu-wrap">
				<button type="button" class="pg-icon-button" data-bs-toggle="menu" data-bs-display="static" data-bs-placement="bottom-end" aria-expanded="false"
					title="' . h(lang('Choose for this area')) . '" aria-label="' . h(lang('Choose for this area')) . ': ' . h($head[0]) . '"><i class="bi bi-three-dots" aria-hidden="true"></i></button>
				<ul class="menu pg-menu-end">' . api_app_permission_bulk_html(true, $area) . '</ul>
			</div>
		</div>
		' . $rows[$area] . '
	</section>';

	}

	return '
<div class="api-pick" data-pg-app-access
	data-count-text="' . h(lang('{var:1} of {var:2} chosen')) . '"
	data-area-text="' . h(lang('{var:1} / {var:2} chosen')) . '"
	data-money-text="' . h(lang('{var:1} permissions that move money are ticked.')) . '">
	<div class="api-pick-bar">
		<div class="api-pick-search">
			<i class="bi bi-search" aria-hidden="true"></i>
			<input type="search" class="form-control form-control-sm" id="' . $id_prefix . '_search" data-pick-search
				placeholder="' . h(lang('Search permissions')) . '" aria-label="' . h(lang('Search permissions')) . '" autocomplete="off">
		</div>
		<span class="small fg-2 text-nowrap" data-pick-count></span>
		<div class="api-pick-bulk" role="group" aria-label="' . h(lang('Choose for every permission')) . '">' . api_app_permission_bulk_html(false) . '</div>
	</div>
	<p class="small fg-2 mb-0 mt-2" data-pick-note role="status" aria-live="polite"></p>
	' . $sections . '
	<p class="small fg-2 py-5 text-center mb-0" data-pick-empty hidden>' . lang('No permission matches the search.') . '</p>
</div>';

}

// The picker's layout; placing only, the parts are look A's recipes.
function api_app_permission_style() {

	return '
<style>
@layer custom {
	.api-pick-bar { position: sticky; top: 0; z-index: 2; display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
		padding: 8px 0; background: var(--pg-bg-body); }
	.api-pick-search { position: relative; flex: 1 1 12rem; min-width: 0; }
	.api-pick-search > i { position: absolute; inset-inline-start: 10px; top: 50%; transform: translateY(-50%); color: var(--pg-fg-2); }
	.api-pick-search > input { padding-inline-start: 30px; }
	.api-pick-bulk { display: flex; flex-wrap: wrap; gap: 4px; }
	.api-pick-area { margin-top: 12px; border: 1px solid var(--pg-border-color); border-radius: 12px; padding: 4px 12px; }
	.api-pick-head { display: flex; align-items: center; gap: 8px; padding: 8px 0; }
	.api-pick-seg { flex: none; }
	.api-perm-text .api-pick-money { display: inline-flex; margin: 0 0 0 6px; vertical-align: middle; }
	.api-pick-bulk .api-pick-money { margin-inline-start: 4px; }
	@media (max-width: 520px) { .api-pick-seg { margin-left: 30px; } }
}
</style>';

}

// The tag that loads the picker's script, with its file time so a changed
// file is fetched again.
function api_app_permission_script_tag() {

	$file = PG_FUNCTIONS_DIR . '/assets/js/api_app_access.js';

	return '<script defer src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/api_app_access.js?v=' . (int)@filemtime($file) . '"></script>';

}

// The picker as Application Access draws it, for the drawer ('scope',
// 'd_scope') and the sheet ('scope', 'n_scope'). The first copy on the page
// brings the style and the script.
function api_settings_permission_rows($prefix, $id_prefix = '') {

	static $first = true;

	// The rows are drawn in two places on one page. The posted name has to be
	// the same in both, the element ids must not be: a duplicate id makes a
	// label drive the first copy of the control instead of its own.
	if ($id_prefix === '') { $id_prefix = $prefix; }

	$html = api_app_permission_picker_html(api_scope_groups(), $prefix, $id_prefix, api_app_permission_money_scopes());

	if ($first) {

		$first = false;

		$html = api_app_permission_style() . api_app_permission_script_tag() . $html;

	}

	return $html;

}
