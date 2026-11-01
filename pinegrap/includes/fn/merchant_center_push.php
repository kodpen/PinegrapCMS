<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Google Merchant Center as a managed marketplace account: the per-product
 * push of price and stock rides the outbound marketplace layer
 * (includes/api/outbound/), with no table of its own.
 *
 * The Product Feeds card writes one marketplace_accounts row of the provider
 * merchant_center once the feed and its supplemental update source are
 * registered, and deletes it when the Merchant Center account is cleared.
 * Its credentials are {connection_id, account, country}: the connected Google
 * account the Merchant API is called through. Every product the Google feed
 * offers has a marketplace_product_map row whose remote_code is the feed's
 * item id (the offerId); the daily feed job keeps that list in step with the
 * feed. Changed products reach the queue through
 * pg_marketplace_product_changed(), and api_sync_job.php sends them with the
 * connector in includes/api/outbound/connectors/merchant_center.php.
 *
 * The pure parts are tested in tests/merchant_center_push_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/* ---------------------------------------------------------------------------
   Pure parts
   --------------------------------------------------------------------------- */

// The item one product is pushed as, with the values the Google feed writes
// for it (pg_product_feed_item()): the stored price, the offer price only when
// it undercuts it, the feed's availability. A product switched off or flagged
// out of stock is offered as out of stock until the next fetch drops it.
function pg_merchant_push_item($product, $sale_price, $currency)
{
    $price = (int) $product['price'];
    $offer = ($sale_price === null) ? 0 : (int) round((float) $sale_price);

    $availability = pg_product_feed_availability($product);

    if (empty($product['enabled']) || !empty($product['out_of_stock'])) {
        $availability = 'out_of_stock';
    }

    return array(
        'id'           => (string) (int) $product['id'],
        'price'        => $price,
        'sale_price'   => (($offer > 0) && ($offer < $price)) ? $offer : null,
        'currency'     => strtoupper(trim((string) $currency)),
        'availability' => $availability,
    );
}

// The productInputs.insert body with only what the account sends: its
// push_price switch keeps the price and sale price, its push_stock switch the
// availability. Null when both are off.
function pg_merchant_push_input($item, $language, $country, $with_price, $with_stock)
{
    if (!$with_price && !$with_stock) {
        return null;
    }

    $input = pg_merchant_product_input($item, $language, $country);

    if (!$with_price) {
        unset($input['productAttributes']['price'], $input['productAttributes']['salePrice']);
    }

    if (!$with_stock) {
        unset($input['productAttributes']['availability']);
    }

    return $input;
}

// What an answer's HTTP status means for the product it was about: 'ok',
// 'refused' (Google will refuse it again: written on the mapping row) or
// 'later' (a rate limit, a timeout, their trouble, an expired token, no
// answer at all: the product is tried again).
function pg_merchant_push_verdict($status)
{
    $status = (int) $status;

    if (($status >= 200) && ($status < 300)) {
        return 'ok';
    }

    if (($status >= 400) && ($status < 500) && !in_array($status, array(401, 408, 429), true)) {
        return 'refused';
    }

    return 'later';
}

// A product as Merchant Center holds it (products.get) in the shape the
// layer's listing_status answers: 'rejected' with the disapproving issues,
// 'approved' with the product key as content id otherwise (a demoting issue
// still shows the product), 'unknown' when Google has no such product yet.
function pg_merchant_listing_state($product, $key)
{
    $read = pg_merchant_product_issues($product);

    if (!$read['found']) {
        return array('state' => 'unknown');
    }

    $reasons = array();

    foreach ($read['issues'] as $issue) {
        if ($issue['severity'] === 'DISAPPROVED') {
            $reasons[] = $issue['description'];
        }
    }

    if ($reasons) {
        return array('state' => 'rejected', 'reasons' => $reasons);
    }

    return array('state' => 'approved', 'content_id' => (string) $key);
}

// The credentials the account row holds, or null while there is nothing to
// push to: no account chosen, or its update source not registered.
function pg_merchant_account_credentials($state)
{
    if (($state['account'] === '') || ($state['supplement'] === '') || ($state['connection_id'] <= 0)) {
        return null;
    }

    return array(
        'connection_id' => (int) $state['connection_id'],
        'account'       => $state['account'],
        'country'       => $state['country'],
    );
}

// The products to map and to unmap so the mapping holds the feed's products.
function pg_merchant_map_diff($mapped, $feed_ids)
{
    $mapped = array_values(array_unique(array_map('intval', (array) $mapped)));
    $feed_ids = array_values(array_unique(array_map('intval', (array) $feed_ids)));

    $add = array_values(array_diff($feed_ids, $mapped));
    $remove = array_values(array_diff($mapped, $feed_ids));
    sort($add);
    sort($remove);

    return array('add' => $add, 'remove' => $remove);
}

/* ---------------------------------------------------------------------------
   The account row
   --------------------------------------------------------------------------- */

// Loads the marketplace layer; false where it is not installed.
function pg_merchant_layer()
{
    $base = PG_FUNCTIONS_DIR . '/includes/api/outbound/connectors/base.php';

    if (!defined('DB_CONNECTED') || !is_file($base)) {
        return false;
    }

    require_once($base);

    return true;
}

// The Merchant Center account row (credentials decoded), or null.
function pg_merchant_account_row()
{
    if (!pg_merchant_layer()) {
        return null;
    }

    $rows = mp_accounts('merchant_center');

    return $rows ? $rows[0] : null;
}

// Writes the account row for the chosen Merchant Center account and maps the
// feed's products. A different Merchant Center account than before starts
// with an empty mapping and queue: their state belonged to the other one.
// Answers the row id, 0 when there is nothing to push to yet.
function pg_merchant_account_write($state)
{
    $credentials = pg_merchant_account_credentials($state);

    if (($credentials === null) || !pg_merchant_layer()) {
        return 0;
    }

    $name = ($state['account_name'] !== '') ? $state['account_name'] : $state['account'];
    $row = pg_merchant_account_row();

    if ($row) {
        $account_id = (int) $row['id'];

        if (!isset($row['credentials']['account']) || ((string) $row['credentials']['account'] !== $credentials['account'])) {
            pg_merchant_account_clear($account_id);
        }

        db("UPDATE marketplace_accounts
            SET credentials = '" . e(mp_credentials_encode($credentials)) . "',
                name = '" . e(mb_substr($name, 0, 100)) . "',
                updated_timestamp = UNIX_TIMESTAMP()
            WHERE id = '" . e($account_id) . "'");
    } else {
        // Active at once: choosing the account on the card is the consent,
        // and its push_stock / push_price switches turn the sending off.
        db("INSERT INTO marketplace_accounts
            (provider, name, credentials, status, push_stock, push_price, pull_orders,
             created_user_id, created_timestamp, updated_timestamp)
            VALUES (
                'merchant_center',
                '" . e(mb_substr($name, 0, 100)) . "',
                '" . e(mp_credentials_encode($credentials)) . "',
                'active', '1', '1', '0',
                '" . e(isset($GLOBALS['user']['id']) ? (int) $GLOBALS['user']['id'] : 0) . "',
                UNIX_TIMESTAMP(),
                UNIX_TIMESTAMP())");

        $account_id = (int) mysqli_insert_id(db::$con);
    }

    if ($account_id > 0) {
        pg_merchant_map_refresh($account_id);
    }

    return $account_id;
}

// Removes the account row with its mapping and queue: the Merchant Center
// account was cleared on the card.
function pg_merchant_account_remove()
{
    if (!pg_merchant_layer()) {
        return;
    }

    foreach (mp_accounts('merchant_center') as $row) {
        pg_merchant_account_clear((int) $row['id']);
        db("DELETE FROM marketplace_accounts WHERE id = '" . e((int) $row['id']) . "'");
    }
}

function pg_merchant_account_clear($account_id)
{
    db("DELETE FROM marketplace_sync_queue WHERE account_id = '" . e((int) $account_id) . "'");
    db("DELETE FROM marketplace_product_map WHERE account_id = '" . e((int) $account_id) . "'");
}

// Keeps one mapping row per product the Google feed offers, with the feed's
// own selection (pg_product_feed_products() and the rows
// pg_product_feed_google_row() accepts). A failed read leaves the mapping as
// it is: an empty answer must not unmap the catalogue.
function pg_merchant_map_refresh($account_id = 0)
{
    $result = array('ok' => false, 'added' => 0, 'removed' => 0);

    if ($account_id <= 0) {
        $row = pg_merchant_account_row();
        $account_id = $row ? (int) $row['id'] : 0;
    }

    if (($account_id <= 0) || !function_exists('pg_product_feed_products')) {
        return $result;
    }

    $feed_ids = array();
    $read = pg_product_feed_products(pg_product_feed_settings_options(pg_product_feed_settings()), function ($items) use (&$feed_ids) {
        foreach ($items as $item) {
            if (pg_product_feed_google_row($item) !== null) {
                $feed_ids[] = (int) $item['id'];
            }
        }
    });

    if ($read['error'] !== '') {
        return $result;
    }

    $mapped = array_map('intval', (array) db_values("SELECT product_id FROM marketplace_product_map
        WHERE account_id = '" . e((int) $account_id) . "'"));
    $diff = pg_merchant_map_diff($mapped, $feed_ids);

    foreach (array_chunk($diff['add'], 500) as $chunk) {
        $values = array();

        foreach ($chunk as $product_id) {
            $values[] = "('" . e((int) $account_id) . "', '" . e($product_id) . "', '" . e($product_id) . "', 'active', UNIX_TIMESTAMP())";
        }

        db("INSERT IGNORE INTO marketplace_product_map (account_id, product_id, remote_code, status, created_timestamp)
            VALUES " . implode(', ', $values));
    }

    foreach (array_chunk($diff['remove'], 500) as $chunk) {
        $ids = implode(',', array_map('intval', $chunk));
        db("DELETE FROM marketplace_sync_queue WHERE (account_id = '" . e((int) $account_id) . "') AND (product_id IN (" . $ids . "))");
        db("DELETE FROM marketplace_product_map WHERE (account_id = '" . e((int) $account_id) . "') AND (product_id IN (" . $ids . "))");
    }

    $result['ok'] = true;
    $result['added'] = count($diff['add']);
    $result['removed'] = count($diff['remove']);

    return $result;
}

/* ---------------------------------------------------------------------------
   The product screen
   --------------------------------------------------------------------------- */

// The product's mapping row on the Merchant Center account with its queue
// state, or null when there is no account; 'map' is null for a product the
// feed does not offer.
function pg_merchant_product_sync($product_id)
{
    $row = pg_merchant_account_row();

    if (!$row) {
        return null;
    }

    $map = db_item("SELECT status, last_pushed, last_error, remote_id FROM marketplace_product_map
        WHERE (account_id = '" . e((int) $row['id']) . "') AND (product_id = '" . e((int) $product_id) . "')");

    $queued = $map ? (bool) db_value("SELECT id FROM marketplace_sync_queue
        WHERE (account_id = '" . e((int) $row['id']) . "') AND (product_id = '" . e((int) $product_id) . "')
          AND (status IN ('waiting', 'sent')) LIMIT 1") : false;

    return array('account' => $row, 'map' => $map ? $map : null, 'queued' => $queued);
}

// "Send to Merchant Center": queued with the first priority, never sent in
// the request. False when the product is not on the account.
function pg_merchant_product_push($product_id)
{
    $sync = pg_merchant_product_sync($product_id);

    if (!$sync || !$sync['map'] || ($sync['map']['status'] !== 'active') || ($sync['account']['status'] !== 'active')) {
        return false;
    }

    mp_enqueue((int) $product_id, 'stock_price', 1);

    return true;
}

// The Merchant Center card of the product screen and the form its button
// posts (drawn outside the product form), or two empty strings when no
// Merchant Center account is chosen.
function pg_merchant_product_card($product_id)
{
    $sync = pg_merchant_product_sync($product_id);

    if (!$sync) {
        return array('card' => '', 'form' => '');
    }

    $map = $sync['map'];
    $lines = array();
    $can_send = false;

    if (!$map) {
        $lines[] = '<p class="mb-0">' . lang('This product is not in the Google feed, so its price and stock are not sent to Merchant Center.') . '</p>';
    } else {
        $can_send = ($map['status'] === 'active') && ($sync['account']['status'] === 'active');

        $lines[] = '<p class="mb-2">' . (((int) $map['last_pushed'] > 0)
            ? lang(array('string' => 'Price and stock last sent: {var:1}', 'vars' => array(h(get_absolute_time(array('timestamp' => (int) $map['last_pushed'], 'format' => 'plain_text'))))))
            : lang('Price and stock have not been sent yet; the daily feed fetch carries them until then.')) . '</p>';

        if ($sync['queued']) {
            $lines[] = '<p class="mb-2"><i class="bi bi-hourglass-split me-1"></i>' . lang('Queued: sent within a few minutes.') . '</p>';
        }

        if ((string) $map['last_error'] !== '') {
            $lines[] = '<div class="alert theme-danger small mb-2"><i class="bi bi-exclamation-triangle me-1"></i>' . h($map['last_error']) . '</div>';
        }

        if ($sync['account']['status'] !== 'active') {
            $lines[] = '<p class="mb-2">' . lang('The Merchant Center account is paused on the Marketplaces screen.') . '</p>';
        }
    }

    $card = '
                    <div class="col-12" id="pg_pb_sec_merchant">
                        <div class="card mb-7">
                            <div class="card-header bg-reset border-0 d-flex align-items-center gap-3">
                                <i class="bi bi-google fg-primary"></i>
                                <span class="h5 mb-0 fg-primary fw-bold">' . lang('Google Merchant Center') . '</span>
                            </div>
                            <div class="card-body">
                                ' . implode('', $lines) . '
                                ' . ($can_send ? '<button type="submit" form="pg_merchant_push_form" class="btn btn-sm btn-outline theme-secondary mt-2"><i class="bi bi-send me-1"></i>' . lang('Send to Merchant Center') . '</button>' : '') . '
                            </div>
                        </div>
                    </div>';

    $form = $can_send
        ? '<form method="post" action="edit_product.php" id="pg_merchant_push_form" class="d-none">' . get_token_field()
            . '<input type="hidden" name="id" value="' . (int) $product_id . '"><input type="hidden" name="merchant_push" value="1"></form>'
        : '';

    return array('card' => $card, 'form' => $form);
}
