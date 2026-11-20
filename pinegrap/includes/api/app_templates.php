<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * New application's starting points: the cards of the sheet's first step,
 * each with what it is for, who the connection acts as, the kind of
 * application it makes and the permission rows it ticks.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_API_PANEL')) {
	exit;
}

// The scopes under which at least one route is marked 'moves_money', once
// each, in the order first met. $routes is api_schema() and the modules'
// route lists. Pure.
function api_app_money_scopes($routes) {

	$scopes = array();

	foreach ((array)$routes as $route) {

		if (empty($route['moves_money']) || empty($route['scope'])) {
			continue;
		}

		$scope = (string)$route['scope'];

		if (!in_array($scope, $scopes, true)) {
			$scopes[] = $scope;
		}

	}

	return $scopes;

}

// Drops every choice whose scope, or the read a write holds, has a route that
// moves money: no starting point ticks such a row; a person ticks it by hand.
function api_app_template_safe_choices($choices, $groups, $money_scopes) {

	$safe = array();

	foreach ((array)$choices as $key => $choice) {

		if (!isset($groups[$key][$choice])) {
			continue;
		}

		$held = array((string)$groups[$key][$choice]);

		if (($choice === 'write') && !empty($groups[$key]['read'])) {
			$held[] = (string)$groups[$key]['read'];
		}

		if (array_intersect($held, (array)$money_scopes)) {
			continue;
		}

		$safe[$key] = $choice;

	}

	return $safe;

}

// The starting points of New application, id => card, in the order the sheet
// shows them. Each card:
//   id, icon (a Bootstrap icon class), title, text (what it is for), acts_as
//   (the "Acts as" line), kind (api_apps.kind it makes: server, device or
//   oauth), secret (whether the application is given one), choices (group
//   key => 'read' or 'write', the rows ticked), guide (the connection guide
//   of the sheet's last step), existing (the application the card opens
//   instead of making one, 0 for none) and badge (a word on the card, '' for
//   none).
// $groups is api_scope_groups(), $contract_scopes
// pg_automation_contract_scopes(), $money_scopes api_app_money_scopes() of
// every route, $oauth_app the site's OAuth application (id, name) or null.
// Pure but for lang().
function api_app_templates($groups, $contract_scopes, $money_scopes, $oauth_app = null) {

	$groups = (array)$groups;

	$reads = array();

	foreach ($groups as $key => $group) {

		if (!empty($group['read'])) {
			$reads[$key] = 'read';
		}

	}

	$oauth_id = (is_array($oauth_app) && !empty($oauth_app['id'])) ? (int)$oauth_app['id'] : 0;

	$cards = array(

		'signin' => array(
			'icon'    => 'bi-person-check',
			'title'   => lang('AI assistant with sign-in'),
			'text'    => lang('Claude, ChatGPT or another MCP client. A person signs in on this site and agrees to what it may do; there is no secret.'),
			'acts_as' => lang('Acts as: the person who signs in'),
			'kind'    => 'oauth',
			'choices' => array(),
			'guide'   => 'mcp_signin',
			// The site serves one OAuth application (pg_oauth_app()): once it
			// exists the card opens it rather than making a second.
			'existing' => $oauth_id,
			'badge'    => $oauth_id ? lang('Set up already') : ''
		),

		'automation' => array(
			'icon'    => 'bi-lightning-charge',
			'title'   => lang('Automation platform'),
			'text'    => lang('An automation platform connects to the site with this application. The permissions the platform uses are ticked.'),
			'acts_as' => lang('Acts as: the application, with the rights of the account that creates it'),
			'kind'    => 'server',
			'choices' => api_app_start_choices($contract_scopes, $groups),
			'guide'   => 'key_secret'
		),

		'own' => array(
			'icon'    => 'bi-code-slash',
			'title'   => lang('Own integration'),
			'text'    => lang('A server or a script calls the API with a key and a secret. No permission comes ticked; choose what it may reach.'),
			'acts_as' => lang('Acts as: the application, with the rights of the account that creates it'),
			'kind'    => 'server',
			'choices' => array(),
			'guide'   => 'key_secret'
		),

		'reporting' => array(
			'icon'    => 'bi-graph-up',
			'title'   => lang('Read-only reporting'),
			'text'    => lang('An integration that only reads, such as a report or a dashboard. Every read is ticked and no write.'),
			'acts_as' => lang('Acts as: the application, with the rights of the account that creates it'),
			'kind'    => 'server',
			'choices' => $reads,
			'guide'   => 'key_secret'
		),

		'devices' => array(
			'icon'    => 'bi-phone',
			'title'   => lang('Mobile app and team devices'),
			'text'    => lang('Staff sign in on a mobile app with their own panel account. There is no secret; the permissions are the most any device may reach.'),
			'acts_as' => lang('Acts as: the staff member who signs in'),
			'kind'    => 'device',
			'choices' => array(),
			'guide'   => 'devices'
		)

	);

	foreach ($cards as $id => $card) {

		$cards[$id] = array_merge(array('id' => $id), $card, array(
			'secret'   => ($card['kind'] === 'server'),
			'choices'  => api_app_template_safe_choices($card['choices'], $groups, $money_scopes),
			'existing' => isset($card['existing']) ? $card['existing'] : 0,
			'badge'    => isset($card['badge']) ? $card['badge'] : ''
		));

	}

	return $cards;

}

// The posted starting point when it names a card of $cards, '' otherwise.
function api_app_template_posted($value, $cards) {

	if (!is_string($value) || ($value === '') || !isset($cards[$value])) {
		return '';
	}

	return $value;

}
