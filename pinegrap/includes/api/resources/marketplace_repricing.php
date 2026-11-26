<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A marketplace account's repricing, read and steered from outside.
//
// GET /marketplaces/{account}/repricing lists the account's matched listings
// as the Repricing view shows them: the price each goes out at and what set
// it, the floor, the strategy, the last buybox read and the robot's last
// change. POST /marketplaces/{account}/repricing/{product} sets a listing's
// strategy and own price, as the listing's row on Products in this shop
// does, through the same parse and write (mp_pricing_listing_save()).
//
// Both are the marketplace screens' work, which is the manager role's: an
// owner with the user role is refused even with the products scopes.

if (!defined('PG_API_ENTRY') && !defined('PG_API_PANEL')) {

	exit;

}

require_once(dirname(dirname(__FILE__)) . '/list_order.php');

// The strategies by their stored number, as the API names them.
function api_marketplace_repricing_strategies() {

	return array(0 => 'fixed', 1 => 'win', 2 => 'grow');

}

// One listing as both routes answer it, from what mp_repricing_listings()
// worked out. A listing never read has no rank, buybox price or read time.
function api_marketplace_repricing_present($listing) {

	$strategies = api_marketplace_repricing_strategies();

	$ours = (int)$listing['buybox_ours'];

	$rank = $ours & 127;

	$change = $listing['change'];

	return array(
		'product_id'     => (int)$listing['product_id'],
		'name'           => (string)$listing['title'],
		'code'           => (string)$listing['code'],
		'price'          => api_money($listing['price']),
		'price_from'     => (string)$listing['by'],
		'own_price'      => ((int)$listing['own_price'] > 0) ? api_money($listing['own_price']) : null,
		'strategy'       => isset($strategies[(int)$listing['strategy']]) ? $strategies[(int)$listing['strategy']] : 'fixed',
		'floor'          => ($listing['floor'] !== null) ? api_money($listing['floor']) : null,
		'at_floor'       => ($listing['floor'] !== null) && ((int)$listing['price'] <= (int)$listing['floor']),
		'buybox_rank'    => ($rank > 0) ? $rank : null,
		'winning'        => ($rank === 1),
		'other_sellers'  => (($ours & 128) !== 0),
		'buybox_price'   => ($rank > 0) ? api_money($listing['buybox_price']) : null,
		'buybox_read_at' => api_time($listing['buybox_at']),
		'last_change'    => is_array($change) ? array(
			'old_price' => api_money($change['old_price']),
			'new_price' => api_money($change['new_price']),
			'reason'    => (string)$change['reason'],
			'sent'      => (bool)$change['sent'],
			'at'        => api_time($change['at'])
		) : null
	);

}

// The own price and strategy an update leaves, from the listing's current
// ones and what was sent: a field not sent keeps its value, an own_price
// sent empty clears it (the rules price the listing again). A strategy the
// marketplace does not offer is refused, where the panel's row quietly
// stores Fixed: a client asked for something it would not get.
//
// $params  the validated body: strategy (a name), own_price ('' to clear)
// $current array('own_price' => int, 'strategy' => int)
// $offered the stored numbers the marketplace offers (mp_pricing_strategies())
//
// Answers array('own_price', 'strategy', 'field', 'error'), error '' when
// it may be written.
function api_marketplace_repricing_change($params, $current, $offered) {

	$answer = array(
		'own_price' => (int)$current['own_price'],
		'strategy'  => (int)$current['strategy'],
		'field'     => '',
		'error'     => ''
	);

	if (!array_key_exists('strategy', $params) && !array_key_exists('own_price', $params)) {

		$answer['field'] = 'strategy';

		$answer['error'] = lang('Send strategy, own_price or both.');

		return $answer;

	}

	if (array_key_exists('strategy', $params)) {

		$strategy = array_search((string)$params['strategy'], api_marketplace_repricing_strategies(), true);

		if (($strategy === false) || !in_array($strategy, array_map('intval', (array)$offered), true)) {

			$answer['field'] = 'strategy';

			$answer['error'] = lang('This marketplace does not offer that strategy: win and grow need a buybox read, which only Trendyol answers.');

			return $answer;

		}

		$answer['strategy'] = (int)$strategy;

	}

	if (array_key_exists('own_price', $params)) {

		$answer['own_price'] = ($params['own_price'] === '') ? 0 : (int)$params['own_price'];

	}

	return $answer;

}

// The account an API call names, after the gates both routes share: the
// manager role, the repricer's tables, the account itself.
function api_marketplace_repricing_account($account_id) {

	$app = api_current_app();

	if (!api_product_marketplaces_allowed($app['owner'])) {

		api_fail(403, 'forbidden', lang('Marketplace connections are managed from the manager role up.'));

	}

	require_once(PG_FUNCTIONS_DIR . '/includes/fn/marketplace_repricing.php');

	mp_repricing_load();

	if (!mp_pricing_ready()) {

		api_fail(503, 'service_unavailable', lang('The database has not been upgraded yet. Run the software update first.'));

	}

	$account = mp_account((int)$account_id);

	if (!$account) {

		api_fail_not_found(lang('Marketplace account'));

	}

	return $account;

}

// GET /marketplaces/{account}/repricing
//
// Paged by product id. A filter on the floor or on no cost works every
// listing of the account out first, as the Repricing view's cards do: the
// floor comes from cost, rates and rules, not from a column.
function api_marketplace_repricing_list($params) {

	$account = api_marketplace_repricing_account($params['account']);

	// sort=newest walks the same product id the other way (list_order.php).
	$descending = api_list_descending($params);

	$cursor = api_list_cursor($params, $descending);

	$limit = isset($params['limit']) ? (int)$params['limit'] : 50;

	$filter = isset($params['filter']) ? (string)$params['filter'] : '';

	$ids = (($filter === 'floor') || ($filter === 'no_cost')) ? mp_repricing_card_ids(mp_repricing_listings($account), $filter) : array();

	// One row more than asked for says whether there is another page.
	$page = array_map('intval', array_column((array)api_rows("SELECT map.product_id
		FROM marketplace_product_map AS map
		INNER JOIN products ON products.id = map.product_id
		LEFT JOIN marketplace_price_state AS state
			ON (state.account_id = map.account_id) AND (state.product_id = map.product_id)
		WHERE (map.account_id = '" . (int)$account['id'] . "')
			AND " . (($cursor !== null) ? api_list_keyset_sql('', 'map.product_id', $cursor, $descending) : "(map.product_id > '0')") . "
			AND " . mp_repricing_view_condition(($filter !== '') ? $filter : 'all', $ids) . pg_catalog_bin_live_sql('products') . "
		ORDER BY " . api_list_order_sql('', 'map.product_id', $descending) . "
		LIMIT " . ($limit + 1)), 'product_id'));

	$next_cursor = null;

	if (count($page) > $limit) {

		array_pop($page);

		$next_cursor = api_list_cursor_encode(end($page), end($page), $descending);

	}

	$listings = $page ? mp_repricing_listings($account, $page) : array();

	$out = array();

	foreach ($page as $product_id) {

		if (isset($listings[$product_id])) {

			$out[] = api_marketplace_repricing_present($listings[$product_id]);

		}

	}

	api_ok_list($out, $limit, $next_cursor, null, array(
		'try_first' => mp_repricing_try_first(mp_pricing_account_rules((int)$account['id']))
	));

}

// POST /marketplaces/{account}/repricing/{product}, PATCH accepted
//
// A changed own price, or a strategy that stops or starts sending the robot's
// price, queues the listing on this account, so it goes out on the next run;
// a strategy with no robot price yet waits for the robot's next pass.
function api_marketplace_repricing_update($params) {

	$account = api_marketplace_repricing_account($params['account']);

	$product_id = (int)$params['product'];

	$match = mp_matches_row((int)$account['id'], $product_id);

	if (!$match || !(int)$match['map_id']) {

		api_fail_not_found(lang('Marketplace listing'));

	}

	$before = mp_pricing_product_figures((int)$account['id'], $product_id);

	$change = api_marketplace_repricing_change($params, $before, array_keys(mp_pricing_strategies($account['provider'])));

	if ($change['error'] !== '') {

		api_fail_validation($change['error'], $change['field']);

	}

	api_dry_run_stop('updated', 'marketplace_repricing', array(
		'account' => (int)$account['id'],
		'product_id' => $product_id
	));

	if (!mp_pricing_listing_save((int)$account['id'], $product_id, $change['own_price'], $change['strategy'])) {

		api_fail_server(lang('The pricing could not be saved. Try again.'));

	}

	if (mp_pricing_listing_moves($before, $change['own_price'], $change['strategy'])) {

		mp_matches_send($account, array($product_id));

	}

	$listings = mp_repricing_listings($account, array($product_id));

	if (!isset($listings[$product_id])) {

		api_fail_not_found(lang('Marketplace listing'));

	}

	api_ok(api_marketplace_repricing_present($listings[$product_id]));

}

// What api_marketplace_repricing_present() returns, declared for the
// OpenAPI document.
function api_marketplace_repricing_schema() {

	return array(
		'product_id'     => 'integer',
		'name'           => 'string',
		'code'           => 'string',
		'price'          => 'integer',
		'price_from'     => 'string',
		'own_price'      => 'integer?',
		'strategy'       => 'string',
		'floor'          => 'integer?',
		'at_floor'       => 'boolean',
		'buybox_rank'    => 'integer?',
		'winning'        => 'boolean',
		'other_sellers'  => 'boolean',
		'buybox_price'   => 'integer?',
		'buybox_read_at' => 'datetime?',
		'last_change'    => 'MarketplaceRepricingChange?'
	);

}

// The robot's last change of a listing, as last_change carries it.
function api_marketplace_repricing_change_schema() {

	return array(
		'old_price' => 'integer',
		'new_price' => 'integer',
		'reason'    => 'string',
		'sent'      => 'boolean',
		'at'        => 'datetime'
	);

}
