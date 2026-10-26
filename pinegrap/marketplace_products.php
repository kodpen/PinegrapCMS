<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// Marketplace products: what a store sells on a marketplace, matched to this
// shop's products. Two views.
//
// The catalogue view, where the connector lists the store's products there: a
// store that has sold on a marketplace for years has hundreds of products
// there, under codes that are not its shop codes, so the catalogue is read
// (includes/api/outbound/remote_products.php), the shop product each one most
// likely is is worked out (matching.php), and the two are shown side by side
// with their pictures. The operator accepts, picks another product from a
// picture grid, or says the shop does not sell it; the sure ones can be
// accepted together.
//
// The shop view, on every marketplace: the shop's products with their state
// on the account (product_matches.php), a code typed in the row, and steps on
// the ticked ones.
//
// GET draws the screen; every POST is the screen's script asking for one step
// and is answered in JSON.

include('init.php');

$user = validate_user();
validate_area_access($user, 'manager');
validate_ecommerce_access($user);

require_once(dirname(__FILE__) . '/includes/api/outbound/product_matches.php');

$account_id = (isset($_REQUEST['account']) && is_scalar($_REQUEST['account'])) ? (int) $_REQUEST['account'] : 0;

$account = $account_id ? mp_account($account_id) : null;

$screen_url = URL_SCHEME . HOSTNAME_SETTING . PATH . SOFTWARE_DIRECTORY . '/marketplace_products.php';

/* ---------------------------------------------------------------------------
   Steps the script asks for
   --------------------------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	header('Content-Type: application/json; charset=utf-8');

	header('Cache-Control: no-store');

	// validate_token_field() answers an expired token with an error page;
	// the script expects JSON, so the token is compared here.
	if (!isset($_POST['token']) || !is_string($_POST['token']) || !isset($_SESSION['software']['token'])
		|| !hash_equals((string) $_SESSION['software']['token'], $_POST['token'])) {

		mp_products_respond(array('ok' => false, 'error' => lang('Your session has expired. Reload the page and try again.')), 403);

	}

	if (!$account) {

		mp_products_respond(array('ok' => false, 'error' => lang('That marketplace account no longer exists.')), 404);

	}

	$action = (isset($_POST['mp_action']) && is_string($_POST['mp_action'])) ? $_POST['mp_action'] : '';

	$id = (isset($_POST['id']) && is_scalar($_POST['id'])) ? (int) $_POST['id'] : 0;

	// The shop view's steps work on every marketplace; the catalogue's need a
	// connector that lists the store's products there.
	if (strpos($action, 'shop_') === 0) {

		mp_products_shop_step($account, $action, $id, $user);

	}

	if (!mp_remote_products_supported($account)) {

		mp_products_respond(array('ok' => false, 'error' => lang(array(
			'string' => '{var:1} cannot do this.',
			'vars'   => array(mp_provider_name($account['provider']))
		))), 400);

	}

	// A read may take a while and is the operator's; a closed tab should not
	// leave a page half stored.
	if ($action === 'read') {

		if (function_exists('ignore_user_abort')) {

			@ignore_user_abort(true);

		}

		if (function_exists('set_time_limit')) {

			@set_time_limit(120);

		}

		$page = (isset($_POST['page']) && is_scalar($_POST['page'])) ? max(0, (int) $_POST['page']) : 0;

		// The read's start, the same for every stretch of one read; a page 0
		// starts a new one. A run from the future or a day ago is not one.
		$run = (isset($_POST['run']) && is_scalar($_POST['run'])) ? (int) $_POST['run'] : 0;

		if (($page === 0) || ($run > time()) || ($run < time() - 86400)) {

			$run = time();

		}

		$result = mp_remote_products_read($account, $page, $run);

		mp_account_outcome($account_id, $result['ok'], $result['ok'] ? '' : $result['error']);

		if (!$result['ok']) {

			mp_products_respond(array('ok' => false, 'error' => $result['error'], 'run' => $run,
				'next' => isset($result['data']['next']) ? (int) $result['data']['next'] : $page));

		}

		if (!empty($result['data']['done'])) {

			log_activity(lang(array(
				'string' => 'Marketplace products read: {var:1} ({var:2} products, {var:3} suggestions).',
				'vars'   => array($account['name'], (int) $result['data']['total'], (int) $result['data']['suggested'])
			)), $user['username']);

		}

		mp_products_respond(array_merge(array('ok' => true, 'run' => $run), $result['data']));

	}

	if ($action === 'accept') {

		$product_id = (isset($_POST['product_id']) && is_scalar($_POST['product_id'])) ? (int) $_POST['product_id'] : 0;

		$result = mp_remote_products_accept($account, $id, $product_id);

		mp_products_respond_row($account, $id, $result);

	}

	if ($action === 'unmatch') {

		mp_products_respond_row($account, $id, mp_remote_products_unmatch($account, $id));

	}

	if (($action === 'dismiss') || ($action === 'restore')) {

		mp_products_respond_row($account, $id, mp_remote_products_dismiss($account, $id, $action === 'dismiss'));

	}

	if (($action === 'accept_many') || ($action === 'accept_sure')) {

		$ids = null;

		if ($action === 'accept_many') {

			$ids = (isset($_POST['ids']) && is_array($_POST['ids'])) ? array_filter($_POST['ids'], 'is_scalar') : array();

		}

		$done = mp_remote_products_accept_suggestions($account, $ids);

		if ($done['matched']) {

			log_activity(lang(array(
				'string' => '{var:1} products matched on {var:2}.',
				'vars'   => array($done['matched'], $account['name'])
			)), $user['username']);

		}

		mp_products_respond(array(
			'ok'      => true,
			'matched' => $done['matched'],
			'refused' => count($done['refused']),
			'message' => $done['refused']
				? lang(array('string' => '{var:1} matched; {var:2} could not be matched and stay in the list.',
					'vars' => array($done['matched'], count($done['refused']))))
				: lang(array('string' => '{var:1} matched.', 'vars' => array($done['matched']))),
			'counts'  => mp_remote_products_counts($account_id)
		));

	}

	if (($action === 'similar') || ($action === 'search')) {

		if ($action === 'similar') {

			$row = mp_remote_product($account_id, $id);

			$ids = $row ? mp_remote_products_similar($account, $row) : array();

		} else {

			$ids = mp_remote_products_search_shop(isset($_POST['q']) && is_string($_POST['q']) ? mb_substr($_POST['q'], 0, 100) : '');

		}

		mp_products_respond(array('ok' => true, 'products' => mp_products_cards($account_id, $ids)));

	}

	mp_products_respond(array('ok' => false, 'error' => lang('Unknown request.')), 400);

}

/* ---------------------------------------------------------------------------
   Helpers
   --------------------------------------------------------------------------- */

function mp_products_respond($payload, $status = 200) {

	set_response_code($status);

	print json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

	exit;

}

// The answer to a step on one row: the row drawn again in its new state, and
// the filters' counts.
function mp_products_respond_row($account, $id, $result) {

	$row = mp_products_row((int) $account['id'], $id);

	mp_products_respond(array(
		'ok'     => $result['ok'],
		'error'  => $result['ok'] ? '' : $result['error'],
		'html'   => $row ? mp_products_row_html($account, $row) : '',
		'counts' => mp_remote_products_counts((int) $account['id'])
	));

}

// One remote row as the list draws it.
function mp_products_row($account_id, $id) {

	$row = db_item("SELECT marketplace_remote_products.*,
			(SELECT MIN(product_id) FROM marketplace_product_map
				WHERE " . mp_remote_products_code_join_sql() . ") AS matched_product_id
		FROM marketplace_remote_products
		WHERE (id = '" . e((int) $id) . "') AND (account_id = '" . e((int) $account_id) . "')");

	if (!$row) {

		return null;

	}

	$product_id = (int) $row['matched_product_id'] ?: (int) $row['suggested_product_id'];

	$products = mp_remote_products_shop_rows(array($product_id));

	$row['product'] = isset($products[$product_id]) ? $products[$product_id] : null;

	return $row;

}

// A shop picture's small copy (get_file.php makes it on the first request).
function mp_products_thumb($image_name) {

	$image_name = trim((string) $image_name);

	return ($image_name === '') ? '' : PATH . encode_url_path($image_name) . '?w=480&f=webp';

}

// The shop's price as the marketplace is sent it, tax included.
function mp_products_price($product) {

	return prepare_price_for_output(mp_product_price($product), false, 0, 'html', false);

}

// The shop's stock in a few words.
function mp_products_stock($product) {

	if (empty($product['inventory'])) {

		return lang('Stock not tracked');

	}

	return lang(array('string' => '{var:1} in stock', 'vars' => array(pg_format_number((int) $product['inventory_quantity'], 0))));

}

// Their status words in the panel's language; one this screen does not know
// is shown as they wrote it.
function mp_products_status_label($status) {

	$labels = array(
		'Active'            => lang('Approved'),
		'On_Sale'           => lang('On sale'),
		'Out_Of_Stock'      => lang('Out of stock'),
		'Suspended'         => lang('On hold'),
		'InApproval'        => lang('Waiting for action'),
		'InCatalogApproval' => lang('In catalogue approval'),
		'CatalogRejected'   => lang('Rejected from the catalogue'),
		'Prohibited'        => lang('Prohibited'),
		'Unlisted'          => lang('Over the listing limit')
	);

	$words = array();

	foreach (explode('/', (string) $status) as $part) {

		if ($part !== '') {

			$words[] = isset($labels[$part]) ? $labels[$part] : $part;

		}

	}

	return implode(' · ', $words);

}

function mp_products_reason_label($reason) {

	switch ($reason) {

		case 'barcode':
			return lang('Same barcode');

		case 'code':
			return lang('Same code');

		case 'code_part':
			return lang('Code inside the other');

		default:
			return lang('Similar title');

	}

}

// A picture, or a frame with an icon when there is none. $gallery groups the
// two pictures of a row for the image viewer.
function mp_products_picture($src, $full, $alt, $gallery, $index, $external) {

	if ($src === '') {

		return '<span class="mpp-img mpp-noimg" aria-hidden="true"><i class="bi bi-image"></i></span>';

	}

	return '<button type="button" class="mpp-img-btn no-submit" data-pg-lb-gallery="' . h($gallery) . '" data-pg-lb-index="' . (int) $index . '"'
		. ' data-pg-lb-src="' . h($full) . '" data-pg-lb-alt="' . h($alt) . '" data-pg-lb-caption="' . h($alt) . '"'
		. ' aria-label="' . h(lang('View images')) . '">'
		. '<img class="mpp-img" src="' . h($src) . '" alt="" loading="lazy"' . ($external ? ' referrerpolicy="no-referrer"' : '') . '>'
		. '</button>';

}

// The shop products a picker shows, as the script draws them.
function mp_products_cards($account_id, $ids) {

	$mapped = array();

	foreach (db_items("SELECT product_id, remote_code FROM marketplace_product_map
		WHERE account_id = '" . e((int) $account_id) . "'") as $map) {

		$mapped[(int) $map['product_id']] = $map['remote_code'];

	}

	$products = mp_remote_products_shop_rows($ids);

	$cards = array();

	foreach ((array) $ids as $id) {

		$id = (int) $id;

		if (!isset($products[$id])) {

			continue;

		}

		$product = $products[$id];

		$cards[] = array(
			'id'      => $id,
			'code'    => (string) $product['name'],
			'title'   => mp_remote_products_shop_name($product),
			'image'   => mp_products_thumb($product['image_name']),
			'price'   => html_entity_decode(strip_tags(mp_products_price($product)), ENT_QUOTES, 'UTF-8'),
			'stock'   => mp_products_stock($product),
			'enabled' => !empty($product['enabled']),
			'matched' => isset($mapped[$id]) ? (string) $mapped[$id] : ''
		);

	}

	return $cards;

}

// One row of the list: their product on the left, the shop's on the right.
function mp_products_row_html($account, $row) {

	$id = (int) $row['id'];

	$matched = (int) $row['matched_product_id'] > 0;

	$dismissed = !$matched && !empty($row['dismissed']);

	$suggested = !$matched && !$dismissed && ((int) $row['suggested_product_id'] > 0) && $row['product'];

	$product = $row['product'];

	$gallery = 'mpp-' . $id;

	$provider = mp_provider_name($account['provider']);

	$class = 'mpp-row' . ($matched ? ' is-matched' : '') . ($dismissed ? ' is-dismissed' : '') . ($suggested && !empty($row['suggestion_sure']) ? ' is-sure' : '');

	$output = '<div class="' . $class . '" data-mp-row="' . $id . '" data-mp-title="' . h($row['title']) . '"'
		. ' data-mp-code="' . h($row['remote_code']) . '" data-mp-image="' . h($row['image_url']) . '">';

	// The tick box, only where there is a suggestion to accept.
	$output .= '<div class="mpp-check">'
		. ($suggested
			? '<input class="form-check-input" type="checkbox" data-mp-select="' . $id . '" aria-label="' . h(lang('Select')) . '">'
			: '')
		. '</div>';

	/* Theirs */

	$meta = array('<code>' . h($row['remote_code']) . '</code>');

	if ($row['barcode'] !== '') {

		$meta[] = '<span><i class="bi bi-upc me-1" aria-hidden="true"></i>' . h($row['barcode']) . '</span>';

	}

	if ((int) $row['price'] > 0) {

		$meta[] = '<span>' . prepare_price_for_output((int) $row['price'], false, 0, 'html', false) . '</span>';

	}

	$meta[] = '<span>' . h(lang(array('string' => '{var:1} in stock', 'vars' => array(pg_format_number((int) $row['quantity'], 0))))) . '</span>';

	$status = mp_products_status_label($row['remote_status']);

	$output .= '<div class="mpp-side">'
		. mp_products_picture((string) $row['image_url'], (string) $row['image_url'], $row['title'], $gallery, 0, true)
		. '<div class="mpp-text">'
		. '<div class="mpp-origin">' . h($provider) . ($status !== '' ? ' · ' . h($status) : '') . '</div>'
		. '<div class="mpp-title" title="' . h($row['title']) . '">' . h($row['title'] !== '' ? $row['title'] : $row['remote_code']) . '</div>'
		. '<div class="mpp-meta">' . implode('', $meta) . '</div>'
		. '</div></div>';

	/* Between */

	if ($matched) {

		$link = '<span class="mpp-score matched"><i class="bi bi-link-45deg" aria-hidden="true"></i><small>' . h(lang('Matched')) . '</small></span>';

	} elseif ($suggested) {

		$link = '<span class="mpp-score' . (!empty($row['suggestion_sure']) ? ' sure' : '') . '"'
			. ' title="' . h(lang('How alike the two are, out of 100')) . '">'
			. '<b>' . (int) $row['suggestion_score'] . '</b>'
			. '<small>' . h(mp_products_reason_label($row['suggestion_reason'])) . '</small>'
			. (!empty($row['suggestion_sure']) ? '<span class="mpp-sure-tag"><i class="bi bi-patch-check-fill me-1" aria-hidden="true"></i>' . h(lang('Sure')) . '</span>' : '')
			. '</span>';

	} else {

		$link = '<span class="mpp-score none"><i class="bi bi-arrow-right" aria-hidden="true"></i></span>';

	}

	$output .= '<div class="mpp-link">' . $link . '</div>';

	/* Ours */

	if (($matched || $suggested) && $product) {

		$thumb = mp_products_thumb($product['image_name']);

		$shop_meta = array(
			'<code>' . h($product['name']) . '</code>',
			'<span>' . mp_products_price($product) . '</span>',
			'<span>' . h(mp_products_stock($product)) . '</span>'
		);

		if (empty($product['enabled'])) {

			$shop_meta[] = '<span class="text-warning">' . h(lang('Disabled')) . '</span>';

		}

		$shop_name = mp_remote_products_shop_name($product);

		$output .= '<div class="mpp-side shop">'
			. mp_products_picture($thumb, ($thumb !== '') ? PATH . encode_url_path($product['image_name']) : '', $shop_name, $gallery, 1, false)
			. '<div class="mpp-text">'
			. '<div class="mpp-origin">' . h(lang('In this shop')) . '</div>'
			. '<a class="mpp-title" href="edit_product.php?id=' . (int) $product['id'] . '" target="_blank" rel="noopener">' . h($shop_name) . '</a>'
			. '<div class="mpp-meta">' . implode('', $shop_meta) . '</div>'
			. '</div></div>';

	} elseif ($matched) {

		$output .= '<div class="mpp-side shop"><div class="mpp-empty"><i class="bi bi-question-circle" aria-hidden="true"></i>'
			. h(lang('Matched to a product that no longer exists.')) . '</div></div>';

	} elseif ($dismissed) {

		$output .= '<div class="mpp-side shop"><div class="mpp-empty"><i class="bi bi-slash-circle" aria-hidden="true"></i>'
			. h(lang('Marked as not sold in this shop.')) . '</div></div>';

	} else {

		$output .= '<div class="mpp-side shop"><div class="mpp-empty"><i class="bi bi-search" aria-hidden="true"></i>'
			. h(lang('No product here looks like this one. Pick one yourself.')) . '</div></div>';

	}

	/* What can be done */

	$buttons = array();

	if ($suggested) {

		$buttons[] = '<button type="button" class="btn btn-sm btn-success" data-mp-act="accept" data-mp-product="' . (int) $product['id'] . '">'
			. '<i class="bi bi-check-lg me-1" aria-hidden="true"></i>' . h(lang('Accept')) . '</button>';

	}

	if (!$matched && !$dismissed) {

		$buttons[] = '<button type="button" class="btn btn-sm ' . ($suggested ? 'btn-outline-secondary' : 'btn-primary') . '" data-mp-act="pick">'
			. '<i class="bi bi-grid-3x3-gap me-1" aria-hidden="true"></i>' . h($suggested ? lang('Another product') : lang('Pick a product')) . '</button>';

		$buttons[] = '<button type="button" class="btn btn-sm btn-ghost" data-mp-act="dismiss" title="' . h(lang('This shop does not sell it')) . '">'
			. '<i class="bi bi-slash-circle me-1" aria-hidden="true"></i>' . h(lang('Not sold here')) . '</button>';

	}

	if ($matched) {

		$buttons[] = '<button type="button" class="btn btn-sm btn-ghost" data-mp-act="unmatch">'
			. '<i class="bi bi-x-lg me-1" aria-hidden="true"></i>' . h(lang('Remove match')) . '</button>';

	}

	if ($dismissed) {

		$buttons[] = '<button type="button" class="btn btn-sm btn-outline-secondary" data-mp-act="restore">'
			. '<i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>' . h(lang('Undo')) . '</button>';

	}

	$output .= '<div class="mpp-actions">' . implode('', $buttons) . '</div>';

	return $output . '</div>';

}

/* ---------------------------------------------------------------------------
   The shop view: every product of the shop and its state on the account
   --------------------------------------------------------------------------- */

// One step of the shop view, answered with the rows it changed drawn again,
// the filters' counts and a sentence for the operator.
function mp_products_shop_step($account, $action, $id, $user) {

	$account_id = (int) $account['id'];

	$ids = isset($_POST['ids']) ? mp_matches_ids($_POST['ids']) : ($id ? array($id) : array());

	$message = '';

	$error = '';

	if ($action === 'shop_code') {

		$result = mp_matches_set_code($account, $id, (isset($_POST['code']) && is_string($_POST['code'])) ? $_POST['code'] : '');

		$error = $result['ok'] ? '' : $result['error'];

	} elseif ($action === 'shop_own') {

		$done = mp_matches_own_codes($account, $ids);

		$message = lang(array('string' => '{var:1} matched.', 'vars' => array($done['matched'])));

		$error = $done['refused'] ? implode(' ', array_unique($done['refused'])) : '';

	} elseif ($action === 'shop_remove') {

		$message = lang(array('string' => '{var:1} match(es) removed. Nothing was changed on the marketplace.',
			'vars' => array(mp_matches_remove($account, $ids))));

	} elseif (($action === 'shop_send') || ($action === 'shop_send_all')) {

		$queued = mp_matches_send($account, ($action === 'shop_send_all') ? null : $ids);

		$message = $queued
			? lang(array('string' => '{var:1} product(s) queued. They go out on the next run.', 'vars' => array($queued)))
			: '';

		if ($account['status'] !== 'active') {

			$error = lang('This account is paused. Nothing is sent while it is, and changes made in the meantime are not queued.');

		} elseif (!$queued) {

			$error = lang('Nothing to send: no product is matched and active on this account.');

		} elseif (($action === 'shop_send') && ($queued < count($ids))) {

			// The rest are not matched, or their match was refused: a refused
			// one is sent again once its reason is put right.
			$error = lang(array('string' => '{var:1} of the ticked products are not matched, or their match was refused, and were not queued.',
				'vars' => array(count($ids) - $queued)));

		}

	} elseif (($action === 'shop_listing_update') && mp_can($account['provider'], 'update_listings')) {

		$queued = mp_matches_listing_update($account_id, $ids);

		$message = $queued ? lang(array('string' => 'The changes of {var:1} listing(s) will be sent on the next run.', 'vars' => array($queued))) : '';

		$error = $queued ? '' : lang('Tick at least one product whose listing is approved.');

	} else {

		mp_products_respond(array('ok' => false, 'error' => lang('Unknown request.')), 400);

	}

	$rows = array();

	foreach ($ids as $product_id) {

		$row = mp_matches_row($account_id, $product_id);

		if ($row) {

			$rows[$product_id] = mp_products_shop_row_html($account, $row);

		}

	}

	mp_products_respond(array(
		'ok'      => ($error === ''),
		'message' => $message,
		'error'   => $error,
		'rows'    => (object) $rows,
		'counts'  => mp_matches_counts($account_id)
	));

}

// The pill that says where a matched product's listing stands.
function mp_products_state_pill($state, $provider_name) {

	$pills = array(
		'on'       => array('success', 'bi-check-circle', lang(array('string' => 'On {var:1}', 'vars' => array($provider_name)))),
		'waiting'  => array('secondary', 'bi-hourglass-split', lang('Awaiting approval')),
		'rejected' => array('danger', 'bi-x-circle', lang('Rejected')),
		'error'    => array('danger', 'bi-exclamation-triangle', lang('Not sent'))
	);

	if (!isset($pills[$state])) {

		return '';

	}

	return '<span class="mpp-pill ' . $pills[$state][0] . '"><i class="bi ' . $pills[$state][1] . ' me-1" aria-hidden="true"></i>'
		. h($pills[$state][2]) . '</span>';

}

// One product of the shop view: the shop's product on the left, what the
// account sends it as on the right.
function mp_products_shop_row_html($account, $row) {

	static $flags = null;

	if ($flags === null) {

		$provider_name = mp_provider_name($account['provider']);

		$flags = array(
			'provider' => $provider_name,
			'status'   => mp_can($account['provider'], 'listing_status'),
			'rejected' => mp_listing_rejected_prefix($provider_name),
			'update'   => mp_can($account['provider'], 'update_listings')
		);

	}

	$id = (int) $row['id'];

	$matched = (int) $row['map_id'] > 0;

	$state = $matched ? mp_matches_listing_state($row, $flags['rejected']) : '';

	$name = mp_remote_products_shop_name($row);

	$thumb = mp_products_thumb($row['image_name']);

	$own = mp_matches_own_code($account, $row);

	$output = '<div class="mpp-row' . ($matched ? ' is-matched' : '') . (($state === 'error' || $state === 'rejected') ? ' is-error' : '') . '"'
		. ' data-mp-row="' . $id . '" data-mp-shop="1" data-mp-code="' . h($matched ? $row['remote_code'] : '') . '" data-mp-own="' . h($own) . '">'
		. '<div class="mpp-check"><input class="form-check-input" type="checkbox" data-mp-select="' . $id . '" aria-label="' . h(lang('Select')) . '"></div>';

	$meta = array(
		'<code>' . h($row['name']) . '</code>',
		'<span>' . mp_products_price($row) . '</span>',
		'<span>' . (empty($row['inventory'])
			? h(lang('Stock not tracked'))
			: h(lang(array('string' => '{var:1} in stock', 'vars' => array(pg_format_number(mp_matches_quantity($row), 0)))))) . '</span>'
	);

	if (empty($row['enabled'])) {

		$meta[] = '<span class="text-warning">' . h(lang('Disabled')) . '</span>';

	}

	$output .= '<div class="mpp-side">'
		. mp_products_picture($thumb, ($thumb !== '') ? PATH . encode_url_path($row['image_name']) : '', $name, 'mpp-shop-' . $id, 0, false)
		. '<div class="mpp-text"><div class="mpp-origin">' . h(lang('In this shop')) . '</div>'
		. '<a class="mpp-title" href="edit_product.php?id=' . $id . '" target="_blank" rel="noopener">' . h($name) . '</a>'
		. '<div class="mpp-meta">' . implode('', $meta) . '</div></div></div>';

	$output .= '<div class="mpp-link"><span class="mpp-score' . ($matched ? ' matched' : ' none') . '">'
		. '<i class="bi ' . ($matched ? 'bi-link-45deg' : 'bi-arrow-right') . '" aria-hidden="true"></i>'
		. ($matched ? '<small>' . h(lang('Matched')) . '</small>' : '') . '</span></div>';

	if ($matched) {

		$details = array();

		if ($flags['status']) {

			$details[] = mp_products_state_pill($state, $flags['provider']);

		} elseif ($state === 'error') {

			$details[] = mp_products_state_pill('error', $flags['provider']);

		}

		if (isset($row['commission']) && is_numeric($row['commission'])) {

			$details[] = '<span>' . h(lang(array('string' => 'Commission {var:1} %', 'vars' => array(pg_format_number((float) $row['commission'], 2))))) . '</span>';

		}

		$details[] = '<span>' . h(lang('Last sent')) . ': ' . ((int) $row['last_pushed'] ? get_relative_time(array('timestamp' => (int) $row['last_pushed'])) : '&mdash;') . '</span>';

		$output .= '<div class="mpp-side shop"><div class="mpp-text">'
			. '<div class="mpp-origin">' . h($flags['provider']) . '</div>'
			. '<div class="mpp-code"><code>' . h($row['remote_code']) . '</code></div>'
			. '<div class="mpp-meta">' . implode('', $details) . '</div>'
			. (((string) $row['map_error'] !== '') ? '<div class="mpp-err">' . h(mb_substr((string) $row['map_error'], 0, 200)) . '</div>' : '')
			. '</div></div>';

	} else {

		$output .= '<div class="mpp-side shop"><div class="mpp-empty"><i class="bi bi-link" aria-hidden="true"></i><span>'
			. h(lang(array('string' => 'Not matched on {var:1}.', 'vars' => array($flags['provider']))))
			. ($own !== '' ? ' ' . h(lang('Its own code would be')) . ' <code>' . h($own) . '</code>' : '')
			. '</span></div></div>';

	}

	$buttons = array();

	if ($matched) {

		$buttons[] = '<button type="button" class="btn btn-sm btn-outline-secondary" data-mp-act="code"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Change code')) . '</button>';

		// Only an active match is sent; a refused one shows its reason above.
		if ($row['map_status'] === 'active') {

			$buttons[] = '<button type="button" class="btn btn-sm btn-outline-secondary" data-mp-act="shop_send"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Send now')) . '</button>';

		}

		$buttons[] = '<button type="button" class="btn btn-sm btn-ghost" data-mp-act="shop_remove"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>' . h(lang('Remove match')) . '</button>';

	} else {

		if ($own !== '') {

			$buttons[] = '<button type="button" class="btn btn-sm btn-primary" data-mp-act="shop_own"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . h(lang('Match with its own code')) . '</button>';

		}

		$buttons[] = '<button type="button" class="btn btn-sm btn-outline-secondary" data-mp-act="code"><i class="bi bi-pencil me-1" aria-hidden="true"></i>' . h(lang('Type a code')) . '</button>';

	}

	return $output . '<div class="mpp-actions">' . implode('', $buttons) . '</div></div>';

}

// The shop view: totals, filters, the products and the bar of steps for the
// ticked ones.
function mp_products_shop_view($account, $filters, $counts, $view, $search, $page, $per_page, $list) {

	$account_id = (int) $account['id'];

	$provider_name = mp_provider_name($account['provider']);

	$matched_share = $counts['all'] ? (int) round(100 * $counts['matched'] / $counts['all']) : 0;

	$query = function ($changes) use ($account_id, $view, $search) {

		$params = array_merge(array('account' => $account_id, 'mode' => 'shop', 'view' => $view, 'q' => $search), $changes);

		return 'marketplace_products.php?' . http_build_query(array_filter($params, function ($value) { return $value !== '' && $value !== null; }));

	};

	echo '
	<div class="card mb-3">
		<div class="card-body">
			<div class="mpp-hero">
				<div class="flex-grow-1">
					<div class="mpp-stats">
						<div class="mpp-stat"><b data-mp-count="all">' . pg_format_number($counts['all'], 0) . '</b><span>' . h(lang('products in this shop')) . '</span></div>
						<div class="mpp-stat good"><b data-mp-count="matched">' . pg_format_number($counts['matched'], 0) . '</b><span>'
							. h(lang(array('string' => 'matched on {var:1}', 'vars' => array($provider_name)))) . '</span></div>
						<div class="mpp-stat"><b data-mp-count="unmatched">' . pg_format_number($counts['unmatched'], 0) . '</b><span>' . h(lang('not matched')) . '</span></div>
						<div class="mpp-stat' . ($counts['errors'] ? ' bad' : '') . '"><b data-mp-count="errors">' . pg_format_number($counts['errors'], 0) . '</b><span>' . h(lang('not sent')) . '</span></div>
					</div>
					<div class="mpp-meter" id="mpp-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $matched_share . '"'
						. ' aria-label="' . h(lang('Matched')) . '"><i style="width:' . $matched_share . '%"></i></div>
					<div class="small mt-2">' . h(lang('Only matched products are sent. Their stock and price go from here; the rest is left alone on the marketplace.')) . '</div>
				</div>
				<div class="mpp-hero-actions">
					<button type="button" class="btn btn-outline-secondary" data-mp-bulk="shop_send_all"' . ($counts['matched'] ? '' : ' disabled') . '>'
						. '<i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Send all now')) . '</button>
				</div>
			</div>'
			. (($account['status'] !== 'active')
				? '<div class="alert alert-warning small mt-3 mb-0"><i class="bi bi-pause-circle me-1" aria-hidden="true"></i>'
					. h(lang('This account is paused. Nothing is sent while it is, and changes made in the meantime are not queued.')) . '</div>'
				: '') . '
		</div>
	</div>
	<div class="card">
		<div class="mpp-list-head">
			<div class="mpp-tabs" role="tablist">';

	foreach ($filters as $key => $label) {

		echo '
				<a class="mpp-tab' . ($key === $view ? ' active' : '') . '" href="' . h($query(array('view' => $key, 'page' => null))) . '"'
					. ($key === $view ? ' aria-current="page"' : '') . '>' . h($label)
					. '<span class="mpp-n" data-mp-count="' . h($key) . '">' . pg_format_number($counts[$key], 0) . '</span></a>';

	}

	echo '
			</div>
			<div class="flex-grow-1"></div>
			<form method="get" action="marketplace_products.php" class="d-flex gap-2 disable_shortcut" role="search">
				<input type="hidden" name="account" value="' . $account_id . '">
				<input type="hidden" name="mode" value="shop">
				<input type="hidden" name="view" value="' . h($view) . '">
				<input type="search" name="q" value="' . h($search) . '" class="form-control form-control-sm" style="min-width:220px"'
					. ' placeholder="' . h(lang('Search by name, code or barcode')) . '" aria-label="' . h(lang('Search')) . '">
				<button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="' . h(lang('Search')) . '"><i class="bi bi-search" aria-hidden="true"></i></button>
			</form>
		</div>'
		. ($list['rows'] ? '
		<div class="mpp-list-head pt-0 small">
			<label class="form-check mb-0"><input class="form-check-input" type="checkbox" id="mpp-select-all"> ' . h(lang('Select all on this page')) . '</label>
		</div>' : '') . '
		<div id="mpp-list">';

	foreach ($list['rows'] as $row) {

		echo mp_products_shop_row_html($account, $row);

	}

	if (!$list['rows']) {

		echo '
			<div class="text-center text-body-secondary py-5 border-top">' . h(lang('Nothing found.')) . '</div>';

	}

	echo '
		</div>';

	$pages = (int) ceil($list['total'] / $per_page);

	if ($pages > 1) {

		echo '
		<nav class="d-flex justify-content-center align-items-center gap-2 py-3 border-top" aria-label="' . h(lang('Pages')) . '">
			<a class="btn btn-sm btn-outline-secondary' . ($page <= 1 ? ' disabled' : '') . '" href="' . h($query(array('page' => max(1, $page - 1)))) . '">'
				. '<i class="bi bi-chevron-left" aria-hidden="true"></i></a>
			<span class="small">' . h(lang(array('string' => 'Page {var:1} of {var:2}', 'vars' => array($page, $pages)))) . '</span>
			<a class="btn btn-sm btn-outline-secondary' . ($page >= $pages ? ' disabled' : '') . '" href="' . h($query(array('page' => min($pages, $page + 1)))) . '">'
				. '<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
		</nav>';

	}

	echo '
	</div>
	<div class="mpp-bulk d-none" id="mpp-bulk">
		<span class="small"><b id="mpp-bulk-n">0</b> ' . h(lang('selected')) . '</span>
		<div class="flex-grow-1"></div>
		<button type="button" class="btn btn-sm btn-ghost" id="mpp-bulk-clear">' . h(lang('Clear')) . '</button>
		<button type="button" class="btn btn-sm btn-primary" data-mp-bulk="shop_own"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>' . h(lang('Match with own codes')) . '</button>
		<button type="button" class="btn btn-sm btn-outline-secondary" data-mp-bulk="shop_send"><i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>' . h(lang('Send now')) . '</button>'
		. (mp_can($account['provider'], 'update_listings')
			? '<button type="button" class="btn btn-sm btn-outline-secondary" data-mp-bulk="shop_listing_update" title="'
				. h(lang('Sends the title, description and pictures of the ticked approved listings. Price and stock go on their own.')) . '">'
				. '<i class="bi bi-cloud-upload me-1" aria-hidden="true"></i>' . h(lang('Send listing changes')) . '</button>'
			: '') . '
		<button type="button" class="btn btn-sm btn-ghost" data-mp-bulk="shop_remove"><i class="bi bi-x-lg me-1" aria-hidden="true"></i>' . h(lang('Remove match')) . '</button>
	</div>';

}

/* ---------------------------------------------------------------------------
   Reads
   --------------------------------------------------------------------------- */

$filters = array(
	'suggested' => lang('Suggestions'),
	'unmatched' => lang('No suggestion'),
	'matched'   => lang('Matched'),
	'dismissed' => lang('Not sold here'),
	'all'       => lang('All')
);

// Checked against the filters of the view it belongs to, below.
$view = (isset($_GET['view']) && is_string($_GET['view'])) ? $_GET['view'] : '';

$search = (isset($_GET['q']) && is_string($_GET['q'])) ? mb_substr(trim($_GET['q']), 0, 100) : '';

$page = (isset($_GET['page']) && is_scalar($_GET['page'])) ? max(1, (int) $_GET['page']) : 1;

$per_page = 40;

$supported = $account && mp_can($account['provider'], 'remote_products');

$ready = mp_remote_products_ready();

// Which view: the marketplace's catalogue where the connector lists it, the
// shop's products otherwise or when asked for.
$mode = ($supported && !(isset($_GET['mode']) && $_GET['mode'] === 'shop')) ? 'remote' : 'shop';

$shop_filters = array(
	'all'       => lang('All'),
	'matched'   => lang('Matched'),
	'unmatched' => lang('Not matched'),
	'errors'    => lang('Not sent')
);

$counts = array();

$read_at = 0;

$list = array('rows' => array(), 'total' => 0);

$shop_counts = array();

if ($account && ($mode === 'shop')) {

	$shop_counts = mp_matches_counts($account_id);

	if (!isset($shop_filters[$view])) {

		$view = 'all';

	}

	$list = mp_matches_page($account_id, $view, $search, $page, $per_page);

}

if ($account && ($mode === 'remote') && $ready) {

	$counts = mp_remote_products_counts($account_id);

	$read_at = mp_remote_products_read_at($account_id);

	// The first filter with something in it, suggestions first.
	if (!isset($filters[$view])) {

		$view = 'suggested';

		foreach (array('suggested', 'unmatched', 'matched') as $candidate) {

			if ($counts[$candidate] > 0) {

				$view = $candidate;

				break;

			}

		}

	}

	$list = mp_remote_products_page($account_id, $view, $search, $page, $per_page);

}

/* ---------------------------------------------------------------------------
   The screen
   --------------------------------------------------------------------------- */

$provider_name = $account ? mp_provider_name($account['provider']) : '';

echo pg_page_shell(array(
	'title'               => lang('Marketplace Products'),
	'extra classes'       => 'mp-screen',
	'icon'                => 'shop',
	// The heading is printed as markup; the account name is the operator's text.
	'heading'             => $account ? h($account['name']) . ' · ' . h($provider_name) : lang('Marketplace Products'),
	'heading_description' => lang('Match what you sell on the marketplace to the products in this shop. Matched products get their stock and price from here.'),
	'breadcrumb'          => array(
		array('label' => lang('Marketplaces'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/marketplace_settings.php' . ($account ? '?account=' . $account_id : '')),
		array('label' => lang('Marketplace Products'))
	)
)) . '

<style>
.mpp-hero { display: flex; flex-wrap: wrap; gap: 20px 32px; align-items: center; }
.mpp-stats { display: flex; flex-wrap: wrap; gap: 14px 32px; flex: 1 1 420px; }
.mpp-stat b { display: block; font-size: 26px; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; }
.mpp-stat span { font-size: 12px; color: var(--pg-fg-2); }
.mpp-stat.good b { color: var(--pg-success); }
.mpp-meter { height: 6px; border-radius: var(--pg-radius-pill); background: var(--pg-bg-2); overflow: hidden; margin-top: 12px; }
.mpp-meter > i { display: block; height: 100%; background: var(--pg-success); transition: width .4s ease; }
.mpp-meter.busy > i { background: var(--pg-primary); }
.mpp-hero-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
.mpp-hero-actions small { color: var(--pg-fg-2); }
.mpp-tabs { display: flex; flex-wrap: wrap; gap: 6px; }
.mpp-tab { display: inline-flex; align-items: center; gap: 8px; padding: 6px 14px; border: 1px solid var(--pg-border-color);
	border-radius: var(--pg-radius-pill); color: inherit; text-decoration: none; font-size: 13px; white-space: nowrap; }
.mpp-tab:hover { border-color: var(--pg-primary); }
.mpp-tab.active { background: var(--pg-primary-bg-subtle); border-color: var(--pg-primary); color: var(--pg-primary-fg-emphasis); font-weight: 600; }
.mpp-tab .mpp-n { font-size: 11px; font-weight: 600; padding: 1px 8px; border-radius: var(--pg-radius-pill); background: var(--pg-bg-2); }
.mpp-list-head { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; padding: 12px 16px; }
.mpp-row { display: grid; grid-template-columns: 22px minmax(0, 1fr) 112px minmax(0, 1fr) 260px; gap: 16px; align-items: center;
	padding: 14px 16px; border-top: 1px solid var(--pg-border-color); transition: background-color .2s; }
.mpp-row.is-matched { background: color-mix(in srgb, var(--pg-success) 6%, transparent); }
.mpp-row.is-dismissed .mpp-side:not(.shop) { opacity: .6; }
.mpp-row.is-busy { opacity: .55; pointer-events: none; }
.mpp-side { display: flex; gap: 14px; align-items: center; min-width: 0; }
.mpp-text { min-width: 0; }
.mpp-img-btn { padding: 0; border: 0; background: none; cursor: zoom-in; flex: 0 0 auto; border-radius: var(--pg-radius-lg); }
.mpp-img { display: block; width: 76px; height: 76px; object-fit: cover; border-radius: var(--pg-radius-lg);
	background: var(--pg-bg-2); border: 1px solid var(--pg-border-color); flex: 0 0 76px; }
.mpp-noimg { display: flex; align-items: center; justify-content: center; color: var(--pg-fg-3); font-size: 24px; }
.mpp-origin { font-size: 10.5px; letter-spacing: .05em; text-transform: uppercase; color: var(--pg-fg-3); margin-bottom: 2px; }
.mpp-title { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
	font-weight: 600; font-size: 13.5px; line-height: 1.35; color: inherit; text-decoration: none; }
a.mpp-title:hover { text-decoration: underline; }
.mpp-meta { display: flex; flex-wrap: wrap; gap: 2px 12px; margin-top: 4px; font-size: 12px; color: var(--pg-fg-2); }
.mpp-meta code { font-size: 11.5px; color: inherit; }
.mpp-link { display: flex; justify-content: center; }
.mpp-score { display: inline-flex; flex-direction: column; align-items: center; gap: 2px; text-align: center; color: var(--pg-fg-2); }
.mpp-score b { font-size: 20px; line-height: 1; color: var(--pg-fg-emphasis); font-variant-numeric: tabular-nums; }
.mpp-score small { font-size: 11px; }
.mpp-score.sure b, .mpp-score.matched { color: var(--pg-success); }
.mpp-score.matched i { font-size: 22px; }
.mpp-score.none i { font-size: 18px; opacity: .5; }
.mpp-sure-tag { font-size: 10.5px; font-weight: 600; color: var(--pg-success); }
.mpp-empty { display: flex; gap: 10px; align-items: center; min-height: 76px; padding: 12px 14px; width: 100%;
	border: 1px dashed var(--pg-border-color); border-radius: var(--pg-radius-lg); font-size: 12.5px; color: var(--pg-fg-2); }
.mpp-actions { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
.mpp-bulk { position: sticky; bottom: 12px; z-index: 5; display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
	margin: 12px auto 0; padding: 10px 14px; max-width: 640px; border-radius: var(--pg-radius-pill);
	background: var(--pg-card-bg); border: 1px solid var(--pg-primary); box-shadow: var(--pg-shadow-lg); }
.mpp-intro { max-width: 640px; margin: 0 auto; text-align: center; padding: 48px 16px; }
.mpp-intro .bi { font-size: 40px; color: var(--pg-primary); }
.mpp-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 24px 0; text-align: left; }
.mpp-steps div { border: 1px solid var(--pg-border-color); border-radius: var(--pg-radius-lg); padding: 12px; font-size: 12.5px; color: var(--pg-fg-2); }
.mpp-steps b { display: block; color: var(--pg-fg-emphasis); margin-bottom: 4px; font-size: 13px; }
.mpp-pick-head { display: flex; gap: 12px; align-items: center; padding-bottom: 12px; margin-bottom: 12px; border-bottom: 1px solid var(--pg-border-color); }
.mpp-pick-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(168px, 1fr)); gap: 12px; margin-top: 12px; }
.mpp-pick { display: flex; flex-direction: column; gap: 6px; padding: 10px; text-align: left; color: inherit; cursor: pointer;
	background: var(--pg-card-bg); border: 1px solid var(--pg-border-color); border-radius: var(--pg-radius-lg);
	transition: border-color .15s, box-shadow .15s, transform .15s; }
.mpp-pick:hover, .mpp-pick:focus-visible { border-color: var(--pg-primary); box-shadow: var(--pg-shadow); transform: translateY(-1px); }
.mpp-pick:disabled { opacity: .5; cursor: not-allowed; transform: none; box-shadow: none; }
.mpp-pick .mpp-img { width: 100%; height: auto; aspect-ratio: 1; flex: none; }
.mpp-pick .mpp-title { font-size: 12.5px; }
.mpp-pick .mpp-meta { font-size: 11.5px; margin-top: 0; }
.mpp-modes { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.mpp-mode { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: var(--pg-radius-lg);
	border: 1px solid var(--pg-border-color); color: inherit; text-decoration: none; font-weight: 600; font-size: 14px; }
.mpp-mode:hover { border-color: var(--pg-primary); }
.mpp-mode.active { background: var(--pg-primary-bg-subtle); border-color: var(--pg-primary); color: var(--pg-primary-fg-emphasis); }
.mpp-stat.bad b { color: var(--pg-danger); }
.mpp-row.is-error { background: color-mix(in srgb, var(--pg-danger) 6%, transparent); }
.mpp-code code { font-size: 14px; font-weight: 600; color: var(--pg-fg-emphasis); }
.mpp-err { margin-top: 4px; font-size: 12px; color: var(--pg-danger); word-break: break-word; }
.mpp-pill { display: inline-flex; align-items: center; padding: 1px 9px; border-radius: var(--pg-radius-pill); font-size: 11px; font-weight: 600;
	border: 1px solid var(--pg-border-color); }
.mpp-pill.success { color: var(--pg-success-fg-emphasis); background: var(--pg-success-bg-subtle); border-color: var(--pg-success-border-subtle); }
.mpp-pill.danger { color: var(--pg-danger-fg-emphasis); background: var(--pg-danger-bg-subtle); border-color: var(--pg-danger-border-subtle); }
.mpp-pill.secondary { color: var(--pg-fg-2); }
.mpp-code-edit { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; width: 100%; }
.mpp-code-edit .form-control { flex: 1 1 160px; min-width: 0; }
.mpp-code-edit small { flex-basis: 100%; color: var(--pg-fg-2); }
@media (max-width: 1199.98px) {
	.mpp-row { grid-template-columns: 22px minmax(0, 1fr) 96px minmax(0, 1fr); }
	.mpp-actions { grid-column: 2 / -1; justify-content: flex-start; }
}
@media (max-width: 767.98px) {
	.mpp-row { grid-template-columns: 22px minmax(0, 1fr); gap: 10px; }
	.mpp-link { grid-column: 2; justify-content: flex-start; }
	.mpp-score { flex-direction: row; gap: 8px; }
	.mpp-side.shop { grid-column: 2; }
	.mpp-actions { grid-column: 1 / -1; }
	.mpp-actions .btn { flex: 1 1 auto; }
	.mpp-img { width: 64px; height: 64px; flex-basis: 64px; }
	.mpp-steps { grid-template-columns: 1fr; }
	.mpp-hero-actions { align-items: stretch; width: 100%; }
}
</style>

<main id="content" class="container-fluid">
	<nav id="button_bar" class="pg-toolbar navigation" aria-label="' . h(lang('Button Bar')) . '">
		<a class="btn btn-sm btn-ghost" href="marketplace_settings.php' . ($account ? '?account=' . $account_id : '') . '">'
			. '<i class="bi bi-arrow-left me-1" aria-hidden="true"></i>' . h(lang('Marketplaces')) . '</a>
	</nav>
	<div id="mpp-flash" aria-live="polite"></div>';

// The two views, where the connector lists the store's products there.
if ($account && $supported) {

	echo '
	<nav class="mpp-modes" aria-label="' . h(lang('View')) . '">
		<a class="mpp-mode' . ($mode === 'remote' ? ' active' : '') . '" href="marketplace_products.php?account=' . $account_id . '"'
			. ($mode === 'remote' ? ' aria-current="page"' : '') . '><i class="bi bi-cloud" aria-hidden="true"></i>'
			. h(lang(array('string' => 'Catalogue on {var:1}', 'vars' => array($provider_name)))) . '</a>
		<a class="mpp-mode' . ($mode === 'shop' ? ' active' : '') . '" href="marketplace_products.php?account=' . $account_id . '&amp;mode=shop"'
			. ($mode === 'shop' ? ' aria-current="page"' : '') . '><i class="bi bi-shop" aria-hidden="true"></i>'
			. h(lang('Products in this shop')) . '</a>
	</nav>';

}

if (!$account) {

	echo '
	<div class="alert alert-warning">' . h(lang('That marketplace account no longer exists.')) . '</div>';

} elseif ($mode === 'shop') {

	mp_products_shop_view($account, $shop_filters, $shop_counts, $view, $search, $page, $per_page, $list);

} elseif (!$ready) {

	echo '
	<div class="alert alert-warning">' . h(lang('The database has not been upgraded for this screen yet. Run the upgrade, then come back.')) . '</div>';

} else {

	$matched_share = $counts['all'] ? (int) round(100 * $counts['matched'] / $counts['all']) : 0;

	$read_label = $read_at ? lang('Read again') : lang(array('string' => 'Read products from {var:1}', 'vars' => array($provider_name)));

	echo '
	<div class="card mb-3">
		<div class="card-body">
			<div class="mpp-hero">
				<div class="flex-grow-1">
					<div class="mpp-stats">
						<div class="mpp-stat"><b data-mp-count="all">' . pg_format_number($counts['all'], 0) . '</b><span>'
							. h(lang(array('string' => 'products on {var:1}', 'vars' => array($provider_name)))) . '</span></div>
						<div class="mpp-stat good"><b data-mp-count="matched">' . pg_format_number($counts['matched'], 0) . '</b><span>' . h(lang('matched')) . '</span></div>
						<div class="mpp-stat"><b data-mp-count="suggested">' . pg_format_number($counts['suggested'], 0) . '</b><span>' . h(lang('waiting for a look')) . '</span></div>
						<div class="mpp-stat"><b data-mp-count="sure">' . pg_format_number($counts['sure'], 0) . '</b><span>' . h(lang('sure suggestions')) . '</span></div>
					</div>
					<div class="mpp-meter" id="mpp-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . $matched_share . '"'
						. ' aria-label="' . h(lang('Matched')) . '"><i style="width:' . $matched_share . '%"></i></div>
					<div class="small mt-2" id="mpp-progress-text">'
						. ($read_at
							// get_relative_time() answers a <time> element.
							? lang(array('string' => '{var:1}% matched. Read {var:2}.', 'vars' => array($matched_share, get_relative_time(array('timestamp' => $read_at)))))
							: h(lang('Nothing has been read yet.'))) . '</div>
				</div>
				<div class="mpp-hero-actions">
					<button type="button" class="btn btn-primary" id="mpp-read"><i class="bi bi-cloud-download me-1" aria-hidden="true"></i>' . h($read_label) . '</button>'
					. '<button type="button" class="btn btn-success' . ($counts['sure'] ? '' : ' d-none') . '" id="mpp-accept-sure">'
						. '<i class="bi bi-check2-all me-1" aria-hidden="true"></i>'
						. h(lang('Accept all sure ones')) . ' (<span data-mp-count="sure">' . pg_format_number($counts['sure'], 0) . '</span>)</button>
				</div>
			</div>
		</div>
	</div>';

	if (!$read_at && !$counts['all']) {

		echo '
	<div class="card">
		<div class="mpp-intro">
			<i class="bi bi-boxes" aria-hidden="true"></i>
			<h2 class="h5 mt-3">' . h(lang(array('string' => 'Bring in what you sell on {var:1}', 'vars' => array($provider_name)))) . '</h2>
			<p class="text-body-secondary">' . h(lang('Your products there are read with their pictures, codes, barcodes and prices. Nothing is changed on the marketplace, and nothing is matched until you say so.')) . '</p>
			<div class="mpp-steps">
				<div><b>1. ' . h(lang('Read')) . '</b>' . h(lang('Every product of yours on the marketplace comes in, a page at a time.')) . '</div>
				<div><b>2. ' . h(lang('Compare')) . '</b>' . h(lang('Each one is put beside the shop product it most likely is: same barcode, same code or a similar title.')) . '</div>
				<div><b>3. ' . h(lang('Accept')) . '</b>' . h(lang('Accept the sure ones together, look at the rest one by one, or pick the product from pictures.')) . '</div>
			</div>
		</div>
	</div>';

	} else {

		$query = function ($changes) use ($account_id, $view, $search) {

			$params = array_merge(array('account' => $account_id, 'view' => $view, 'q' => $search), $changes);

			return 'marketplace_products.php?' . http_build_query(array_filter($params, function ($value) { return $value !== '' && $value !== null; }));

		};

		echo '
	<div class="card">
		<div class="mpp-list-head">
			<div class="mpp-tabs" role="tablist">';

		foreach ($filters as $key => $label) {

			echo '
				<a class="mpp-tab' . ($key === $view ? ' active' : '') . '" href="' . h($query(array('view' => $key, 'page' => null))) . '"'
					. ($key === $view ? ' aria-current="page"' : '') . '>' . h($label)
					. '<span class="mpp-n" data-mp-count="' . h($key) . '">' . pg_format_number($counts[$key], 0) . '</span></a>';

		}

		echo '
			</div>
			<div class="flex-grow-1"></div>
			<form method="get" action="marketplace_products.php" class="d-flex gap-2 disable_shortcut" role="search">
				<input type="hidden" name="account" value="' . $account_id . '">
				<input type="hidden" name="view" value="' . h($view) . '">
				<input type="search" name="q" value="' . h($search) . '" class="form-control form-control-sm" style="min-width:220px"'
					. ' placeholder="' . h(lang('Search by title, code or barcode')) . '" aria-label="' . h(lang('Search')) . '">
				<button type="submit" class="btn btn-sm btn-outline-secondary" aria-label="' . h(lang('Search')) . '"><i class="bi bi-search" aria-hidden="true"></i></button>
			</form>
		</div>';

		if ($view === 'suggested' && $list['rows']) {

			echo '
		<div class="mpp-list-head pt-0 small">
			<label class="form-check mb-0"><input class="form-check-input" type="checkbox" id="mpp-select-all"> '
				. h(lang('Select all on this page')) . '</label>
			<span class="text-body-secondary">' . h(lang('Sure suggestions come first. A sure one scores high and nothing else comes close.')) . '</span>
		</div>';

		}

		echo '
		<div id="mpp-list">';

		foreach ($list['rows'] as $row) {

			echo mp_products_row_html($account, $row);

		}

		if (!$list['rows']) {

			$empty = array(
				'suggested' => lang('No suggestion is waiting. The products left are under "No suggestion".'),
				'unmatched' => lang('Every product has a suggestion or a match.'),
				'matched'   => lang('Nothing is matched yet.'),
				'dismissed' => lang('No product is marked as not sold here.'),
				'all'       => lang('Nothing found.')
			);

			echo '
			<div class="text-center text-body-secondary py-5 border-top">' . h(($search !== '') ? lang('Nothing found.') : $empty[$view]) . '</div>';

		}

		echo '
		</div>';

		$pages = (int) ceil($list['total'] / $per_page);

		if ($pages > 1) {

			echo '
		<nav class="d-flex justify-content-center align-items-center gap-2 py-3 border-top" aria-label="' . h(lang('Pages')) . '">
			<a class="btn btn-sm btn-outline-secondary' . ($page <= 1 ? ' disabled' : '') . '" href="' . h($query(array('page' => max(1, $page - 1)))) . '">'
				. '<i class="bi bi-chevron-left" aria-hidden="true"></i></a>
			<span class="small">' . h(lang(array('string' => 'Page {var:1} of {var:2}', 'vars' => array($page, $pages)))) . '</span>
			<a class="btn btn-sm btn-outline-secondary' . ($page >= $pages ? ' disabled' : '') . '" href="' . h($query(array('page' => min($pages, $page + 1)))) . '">'
				. '<i class="bi bi-chevron-right" aria-hidden="true"></i></a>
		</nav>';

		}

		echo '
	</div>
	<div class="mpp-bulk d-none" id="mpp-bulk">
		<span class="small"><b id="mpp-bulk-n">0</b> ' . h(lang('selected')) . '</span>
		<div class="flex-grow-1"></div>
		<button type="button" class="btn btn-sm btn-ghost" id="mpp-bulk-clear">' . h(lang('Clear')) . '</button>
		<button type="button" class="btn btn-sm btn-success" id="mpp-bulk-accept"><i class="bi bi-check2-all me-1" aria-hidden="true"></i>' . h(lang('Accept selected')) . '</button>
	</div>';

	}

}

if ($account) {

	// What the script needs, handed over once.
	$config = array(
		'url'      => 'marketplace_products.php',
		'account'  => $account_id,
		'token'    => isset($_SESSION['software']['token']) ? $_SESSION['software']['token'] : '',
		'provider' => $provider_name,
		'labels'   => array(
			'reading'      => lang(array('string' => 'Reading from {var:1}…', 'vars' => array($provider_name))),
			'read_progress' => lang('{var:1} of {var:2} products read'),
			'read_done'    => lang('{var:1} products read, {var:2} suggestions ({var:3} sure).'),
			'read_failed'  => lang('Reading stopped: {var:1}'),
			'continue'     => lang('Continue reading'),
			'session'      => lang('Your session has expired. Reload the page and try again.'),
			'failed'       => lang('That did not work. Try again.'),
			'confirm_sure' => lang('Accept all {var:1} sure suggestions?'),
			'pick_title'   => lang('Pick the product in this shop'),
			'search'       => lang('Search by title, code or barcode'),
			'similar'      => lang('Closest products'),
			'results'      => lang('Search results'),
			'nothing'      => lang('Nothing found.'),
			'loading'      => lang('Loading…'),
			'close'        => lang('Close'),
			'matched_to'   => lang('Already matched to {var:1}'),
			'disabled'     => lang('Disabled'),
			'save'         => lang('Save'),
			'cancel'       => lang('Cancel'),
			'code'         => lang(array('string' => 'Code on {var:1}', 'vars' => array($provider_name))),
			'code_hint'    => lang('Left empty, its own code is used: {var:1}'),
			'confirm_remove' => lang('Remove the match of {var:1} product(s)? Nothing is changed on the marketplace.')
		)
	);

	echo '
	<script>window.mpProducts = ' . encode_json($config) . ';</script>
	<script src="' . OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/assets/js/marketplace_products.js?v=' . @filemtime(dirname(__FILE__) . '/assets/js/marketplace_products.js') . '" defer></script>'
	. _pg_lightbox_assets_once();

}

echo '
</main>' . output_footer();
