<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Product reviews from verified buyers: who may review a product, the e-mail
 * that asks a buyer for a review, the rating each product keeps and the
 * aggregateRating its structured data prints.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: product reviews.
//
// A review is a comment on a product (comments.item_type 'product'), or on a
// product group that is sold as one product with options (display type
// 'select'). A browsing group is a category: what is written under it is about
// the category, not a review of something bought.
//
// Since 1 August 2026 the Turkish advertising rules let only people who bought
// a product review it, and forbid using a review whose purchase was not
// verified in advertising (Ticari Reklam ve Haksız Ticari Uygulamalar
// Yönetmeliği 28/B, RG 33297). So the comment form and add_comment.php ask for
// the purchase first: a finished order of the signed-in customer with the
// product in it, paid for or put on their account, that no review of the item
// names yet. Its id is kept on the comment (comments.verified_order_id), so
// one purchase opens one review and the next order of it opens the next.
//
// Staff - anyone who may edit the page - are not asked: what they write under
// a product is the store speaking, and it counts in no rating. The store's
// answer to a review itself is written in Comment Moderation
// (comments.seller_reply, seller_reply_at) and shown under the review.
//
// products.rating_avg and rating_count are worked out from the published,
// starred reviews of verified purchases only; a comment from before 2026.11.0
// is not one. A rating printed beside the product in search results is
// advertising, which is where an unverified review may not go.
// pg_review_refresh_products() works them out again after a write, and
// pg_review_refresh_all() catches what no write path told it about (a page
// deleted with its comments), once a day from the general job.
//
// Nothing here does anything until the 11.0.14 columns exist.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * Whether the review columns of 2026.11.0 (11.0.14) are there.
 *
 * @return bool
 */
function pg_reviews_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has')
            && pg_schema_has('comments', 'verified_order_id')
            && pg_schema_has('products', 'rating_avg')
            && pg_schema_has('products', 'rating_count');
    }

    return $ready;
}

/**
 * Whether a comment on this item is a product review.
 *
 * @param string $item_type          comments.item_type
 * @param string $group_display_type product_groups.display_type, for a group
 * @return bool
 */
function pg_review_item_is_product($item_type, $group_display_type = '')
{
    if ((string) $item_type === 'product') {
        return true;
    }

    return ((string) $item_type === 'product_group') && ((string) $group_display_type === 'select');
}

/**
 * Whether a comment on this item has to come from a verified purchase: a
 * product review, on a site where the review columns exist.
 *
 * @param string     $item_type comments.item_type
 * @param int|string $item_id   comments.item_id
 * @return bool
 */
function pg_review_gate_applies($item_type, $item_id)
{
    $item_type = (string) $item_type;
    $item_id = (int) $item_id;

    if (($item_id <= 0) || !in_array($item_type, array('product', 'product_group'), true) || !pg_reviews_ready()) {
        return false;
    }

    $display_type = '';

    if ($item_type === 'product_group') {
        $display_type = (string) db_value("SELECT display_type FROM product_groups WHERE id = '" . $item_id . "'");
    }

    return pg_review_item_is_product($item_type, $display_type);
}

/**
 * The SQL condition an orders row meets when its goods were bought: a finished
 * order (complete, or complete and exported) that is not a bank transfer still
 * waiting for its money. An order put on the customer's account is a sale on
 * credit and counts. A cancelled order, a checkout that never finished and a
 * cart do not.
 *
 * @return string
 */
function pg_review_bought_sql()
{
    return "(orders.status IN ('complete', 'exported'))
        AND ((orders.payment_method <> 'Offline Payment') OR (orders.paid_at > 0) OR (orders.erp_on_account = 1))";
}

/**
 * The SQL condition order_items rows meet for the reviewed item: the product,
 * or any product of the group. A line saved for later in the cart was not
 * bought.
 *
 * @param string $item_type 'product' or 'product_group'
 * @param int    $item_id
 * @return string '' for any other item type
 */
function pg_review_item_lines_sql($item_type, $item_id)
{
    $item_id = (int) $item_id;

    if ((string) $item_type === 'product') {
        $products = "(order_items.product_id = '" . $item_id . "')";
    } elseif ((string) $item_type === 'product_group') {
        $products = "(order_items.product_id IN (SELECT products_groups_xref.product FROM products_groups_xref
            WHERE products_groups_xref.product_group = '" . $item_id . "'))";
    } else {
        return '';
    }

    return $products . " AND (order_items.saved_for_later = 0)";
}

/**
 * What a reader may do about reviewing an item, from what is known of them.
 *
 * @param bool $signed_in     whether the visitor is signed in
 * @param int  $bought        how many bought orders of theirs hold the item
 * @param int  $free_order_id one of them that no review of the item names yet, 0 when none
 * @return string '' (may review), 'sign_in', 'not_bought' or 'reviewed'
 */
function pg_review_refusal($signed_in, $bought, $free_order_id)
{
    if (!$signed_in) {
        return 'sign_in';
    }

    if ((int) $bought <= 0) {
        return 'not_bought';
    }

    return ((int) $free_order_id > 0) ? '' : 'reviewed';
}

/**
 * Whether this person may review the item now, and the order that lets them.
 *
 * The order is the newest of theirs that holds the item, is bought (see
 * pg_review_bought_sql()) and that no review of the item names yet.
 *
 * @param int        $user_id   the signed-in person, 0 for a visitor
 * @param string     $item_type comments.item_type
 * @param int|string $item_id   comments.item_id
 * @return array refusal (see pg_review_refusal()), order_id
 */
function pg_review_eligibility($user_id, $item_type, $item_id)
{
    $user_id = (int) $user_id;
    $lines = pg_review_item_lines_sql($item_type, $item_id);

    if (($user_id <= 0) || ($lines === '')) {
        return array('refusal' => pg_review_refusal($user_id > 0, 0, 0), 'order_id' => 0);
    }

    $orders = db_items(
        "SELECT
            orders.id,
            (SELECT COUNT(*) FROM comments
                WHERE (comments.verified_order_id = orders.id)
                    AND (comments.item_type = '" . e((string) $item_type) . "')
                    AND (comments.item_id = '" . (int) $item_id . "')) AS reviews
        FROM orders
        WHERE
            (orders.user_id = '" . $user_id . "')
            AND " . pg_review_bought_sql() . "
            AND EXISTS (SELECT 1 FROM order_items WHERE (order_items.order_id = orders.id) AND " . $lines . ")
        ORDER BY orders.order_date DESC, orders.id DESC");

    $free = 0;

    foreach ((array) $orders as $order) {
        if ((int) $order['reviews'] === 0) {
            $free = (int) $order['id'];
            break;
        }
    }

    return array('refusal' => pg_review_refusal(true, count((array) $orders), $free), 'order_id' => $free);
}

/**
 * The sentence a reader is told when they may not review the item.
 *
 * @param string $refusal see pg_review_refusal()
 * @return string '' when they may
 */
function pg_review_refusal_message($refusal)
{
    switch ((string) $refusal) {
        case 'sign_in':
            return lang('Sign in with the account you ordered with to review this product.');
        case 'not_bought':
            return lang('Only customers who bought this product can review it.');
        case 'reviewed':
            return lang('You have already reviewed this product. You can review it again after your next order of it.');
    }

    return '';
}

/**
 * The SQL condition of the comments a product's rating counts: published,
 * from a verified purchase, with one to five stars.
 *
 * @return string
 */
function pg_review_rating_sql()
{
    return "(comments.item_type = 'product')
        AND (comments.published = '1')
        AND (comments.verified_order_id > 0)
        AND (comments.rating BETWEEN 1 AND 5)";
}

/**
 * Works out the rating of these products again from their reviews.
 *
 * @param int|int[] $product_ids
 * @return void
 */
function pg_review_refresh_products($product_ids)
{
    if (!pg_reviews_ready()) {
        return;
    }

    $ids = array();

    foreach ((array) $product_ids as $product_id) {
        if ((int) $product_id > 0) {
            $ids[(int) $product_id] = (int) $product_id;
        }
    }

    foreach (array_chunk(array_values($ids), 500) as $chunk) {
        $list = implode(',', $chunk);

        db("UPDATE products
            LEFT JOIN (
                SELECT comments.item_id, AVG(comments.rating) AS average, COUNT(*) AS number
                FROM comments
                WHERE " . pg_review_rating_sql() . " AND (comments.item_id IN (" . $list . "))
                GROUP BY comments.item_id
            ) AS rated ON rated.item_id = products.id
            SET
                products.rating_avg = ROUND(COALESCE(rated.average, 0), 2),
                products.rating_count = COALESCE(rated.number, 0)
            WHERE products.id IN (" . $list . ")");
    }
}

/**
 * After a comment was written, published, held back, changed or removed: the
 * rating of the product it is about. A comment on anything else changes no
 * rating.
 *
 * @param string     $item_type comments.item_type
 * @param int|string $item_id   comments.item_id
 * @return void
 */
function pg_review_comment_changed($item_type, $item_id)
{
    if (((string) $item_type === 'product') && ((int) $item_id > 0)) {
        pg_review_refresh_products(array((int) $item_id));
    }
}

/**
 * Works out the rating of every product again, writing only the rows that
 * differ: one statement over the catalogue.
 *
 * @return int the products whose rating changed
 */
function pg_review_refresh_all()
{
    if (!pg_reviews_ready()) {
        return 0;
    }

    db("UPDATE products
        LEFT JOIN (
            SELECT comments.item_id, ROUND(AVG(comments.rating), 2) AS average, COUNT(*) AS number
            FROM comments
            WHERE " . pg_review_rating_sql() . "
            GROUP BY comments.item_id
        ) AS rated ON rated.item_id = products.id
        SET
            products.rating_avg = COALESCE(rated.average, 0),
            products.rating_count = COALESCE(rated.number, 0)
        WHERE
            (products.rating_count <> COALESCE(rated.number, 0))
            OR (products.rating_avg <> COALESCE(rated.average, 0))");

    return max(0, (int) mysqli_affected_rows(db::$con));
}

/**
 * Where the time of the last full pass is kept. A small file under data/temp:
 * swept with the rest of it, the next tick just runs the pass again.
 *
 * @return string
 */
function pg_review_refresh_state_path()
{
    return PG_FUNCTIONS_DIR . '/data/temp/review_ratings_refreshed.txt';
}

/**
 * Whether the daily pass of pg_review_refresh_all() is due.
 *
 * @param int $last when it last ran, 0 for never
 * @param int $now
 * @return bool
 */
function pg_review_refresh_due($last, $now)
{
    return ((int) $now - (int) $last) >= 86400;
}

/**
 * The general job's daily pass: the rating of every product from its reviews.
 * A tick between passes reads one small file.
 *
 * @return int the products whose rating changed
 */
function pg_review_refresh_daily()
{
    if (!pg_reviews_ready()) {
        return 0;
    }

    $path = pg_review_refresh_state_path();
    $last = is_file($path) ? (int) @file_get_contents($path) : 0;

    if (!pg_review_refresh_due($last, time())) {
        return 0;
    }

    @file_put_contents($path, (string) time(), LOCK_EX);

    return pg_review_refresh_all();
}

/**
 * schema.org AggregateRating for a product, from the rating its row keeps, or
 * null when it has no verified review with stars: a rating is never written
 * without real reviews behind it.
 *
 * @param float|string $average products.rating_avg
 * @param int|string   $count   products.rating_count
 * @return array|null
 */
function pg_review_aggregate_jsonld($average, $count)
{
    $count = (int) $count;
    $average = round((float) $average, 2);

    if (($count <= 0) || ($average < 1) || ($average > 5)) {
        return null;
    }

    return array(
        '@type'       => 'AggregateRating',
        'ratingValue' => $average,
        'reviewCount' => $count,
        'bestRating'  => 5,
        'worstRating' => 1,
    );
}

/**
 * Whether the store's reply columns (11.0.14) are there.
 *
 * @return bool
 */
function pg_review_replies_ready()
{
    static $ready = null;

    if ($ready === null) {
        $ready = function_exists('pg_schema_has')
            && pg_schema_has('comments', 'seller_reply')
            && pg_schema_has('comments', 'seller_reply_at');
    }

    return $ready;
}

/**
 * The store's reply as it is kept: trimmed, at most 5000 characters.
 *
 * @param string|null $reply
 * @return string
 */
function pg_review_reply_text($reply)
{
    return mb_substr(trim((string) $reply), 0, 5000);
}

/**
 * When the store's reply was written, after a save of the moderation screen:
 * a new or changed reply now, an unchanged one when it was first written, and
 * none once the reply is taken away.
 *
 * @param string|null $old_reply   comments.seller_reply before the save
 * @param string      $new_reply   what is kept now (pg_review_reply_text())
 * @param int|string  $old_time    comments.seller_reply_at before the save
 * @param int         $now
 * @return int
 */
function pg_review_reply_time($old_reply, $new_reply, $old_time, $now)
{
    $new_reply = (string) $new_reply;

    if ($new_reply === '') {
        return 0;
    }

    if (($new_reply === pg_review_reply_text($old_reply)) && ((int) $old_time > 0)) {
        return (int) $old_time;
    }

    return (int) $now;
}

/**
 * The store's reply under a review, in the comment list of the page.
 *
 * @param string $reply     comments.seller_reply
 * @param string $when      when it was written, as get_relative_time() prints it
 *                          (a <time> element; '' for none)
 * @param bool   $bootstrap whether the page draws its comments in Bootstrap's markup
 * @return string '' when there is no reply
 */
function pg_review_reply_html($reply, $when, $bootstrap)
{
    $reply = trim((string) $reply);

    if ($reply === '') {
        return '';
    }

    $heading = h(lang('Reply from the store'));
    $text = convert_text_to_html($reply);

    if ($bootstrap) {
        return '<div class="software_seller_reply border-start border-3 ps-3 mt-3">'
            . '<div class="small fw-semibold">' . $heading
            . (((string) $when !== '') ? ' <span class="fw-normal text-body-secondary">' . $when . '</span>' : '') . '</div>'
            . '<div class="message mt-1">' . $text . '</div>'
            . '</div>';
    }

    return '<div class="software_seller_reply" style="margin-top: 1em; padding-left: 1em; border-left: 3px solid #ccc">'
        . '<div class="seller_reply_heading" style="font-weight: bold">' . $heading
        . (((string) $when !== '') ? ' <span class="date_and_time" style="font-weight: normal">' . $when . '</span>' : '') . '</div>'
        . '<div class="message">' . $text . '</div>'
        . '</div>';
}

/**
 * The AggregateRating of one product, for its Product markup.
 *
 * @param int|string $product_id
 * @return array|null
 */
function pg_review_product_aggregate_jsonld($product_id)
{
    if (((int) $product_id <= 0) || !pg_reviews_ready()) {
        return null;
    }

    $row = db_item("SELECT rating_avg, rating_count FROM products WHERE id = '" . (int) $product_id . "'");

    if (!is_array($row)) {
        return null;
    }

    return pg_review_aggregate_jsonld($row['rating_avg'], $row['rating_count']);
}

// Photo reviews (2026.11.0, 11.0.57; decided in #2426 and #2818).
//
// A review takes one file, as any comment does (comments.file_id), but only
// while the site setting is on (config.review_photos, REVIEW_PHOTOS, off by
// default) and only an image. The page's own attachment switch
// (comments_allow_file_attachments) is not asked on a review thread; every
// other thread keeps it. Files already attached stay.

/**
 * The file name endings a review photo may have.
 *
 * @return array
 */
function pg_review_photo_extensions()
{
    return array('jpg', 'jpeg', 'png', 'gif', 'webp');
}

/**
 * Whether a file name ends like a review photo. Pure.
 *
 * @param mixed $file_name
 * @return bool
 */
function pg_comment_file_is_image($file_name)
{
    if (!is_scalar($file_name)) {
        return false;
    }

    $dot = strrpos((string) $file_name, '.');

    return ($dot !== false) && in_array(strtolower(substr((string) $file_name, $dot + 1)), pg_review_photo_extensions(), true);
}

/**
 * Whether the photo review setting is on (REVIEW_PHOTOS, false before the
 * column exists).
 *
 * @return bool
 */
function pg_review_photos_on()
{
    return defined('REVIEW_PHOTOS') && (REVIEW_PHOTOS === true);
}

/**
 * Whether a thread's comment form takes a file. Pure: a review thread
 * ($review, pg_review_gate_applies()) by the site setting ($photos_on)
 * alone, any other thread by the page's switch ($page_switch).
 *
 * @param bool $page_switch
 * @param bool $review
 * @param bool $photos_on
 * @return bool
 */
function pg_comment_attachments_allowed($page_switch, $review, $photos_on)
{
    return $review ? (bool) $photos_on : (bool) $page_switch;
}

/**
 * Whether an uploaded file may be a review photo: an image name ending
 * (pg_comment_file_is_image()) and content PHP reads as one of those image
 * types, so a renamed file is refused. Pure but for reading $tmp_path.
 *
 * @param mixed $file_name the name the visitor's browser sent
 * @param mixed $tmp_path  the uploaded file
 * @return bool
 */
function pg_review_photo_ok($file_name, $tmp_path)
{
    if (!pg_comment_file_is_image($file_name) || !is_string($tmp_path) || ($tmp_path === '') || !is_file($tmp_path)) {
        return false;
    }

    $info = @getimagesize($tmp_path);
    $types = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF);

    if (defined('IMAGETYPE_WEBP')) {
        $types[] = IMAGETYPE_WEBP;
    }

    return is_array($info) && isset($info[2]) && in_array($info[2], $types, true);
}

/**
 * A review photo as a thumbnail that opens the full image, for the classic
 * comments block and Comment Moderation. '' for a file that is no image.
 *
 * @param mixed $file_name files.name
 * @return string
 */
function pg_comment_image_thumbnail_html($file_name)
{
    if (!pg_comment_file_is_image($file_name)) {
        return '';
    }

    $url = h(OUTPUT_PATH . encode_url_path((string) $file_name));

    return '<a class="software_attachment_image d-inline-block" href="' . $url . '" target="_blank" rel="noopener">'
        . '<img src="' . $url . '" alt="' . h(lang('Photo')) . '" loading="lazy" class="img-thumbnail" style="max-width: 160px; max-height: 160px">'
        . '</a>';
}

// The review request e-mail.
//
// When an order ships, update_order() starts the auto campaigns of the
// 'order_review_request' action for it: an e-mail that asks the customer to
// review what they bought, with a link to each item's review form. It goes
// only where a review could be written - an order placed with an account
// (the form asks the reader to sign in with it) and bought by the same rule
// the form checks - and only once per order, whatever came of the first one.
// Cancelling the order cancels a request that has not been sent yet.
//
// Comment Moderation lists verified purchases, or everything else, apart.

/**
 * The address of an item's review form: the item on the page products open
 * on, at the form's anchor.
 *
 * @param string $base_url     the site's address, e.g. https://example.com/
 * @param string $detail_page  the page products open on (page.page_name)
 * @param string $address_name products.address_name or product_groups.address_name
 * @return string '' when there is no page or no address to link to
 */
function pg_review_request_url($base_url, $detail_page, $address_name)
{
    $detail_page = (string) $detail_page;
    $address_name = trim((string) $address_name);

    if (($detail_page === '') || ($address_name === '')) {
        return '';
    }

    return rtrim((string) $base_url, '/') . '/' . encode_url_path($detail_page) . '/' . encode_url_path($address_name) . '#software_add_comment';
}

/**
 * The mail-merge fields of the review request: ^^order_number^^,
 * ^^review_url^^ (the first item's review form) and ^^review_links^^ (one
 * line per item: its name and the address of its form).
 *
 * @param string|int $order_number
 * @param array      $targets list of array('name' => ..., 'url' => ...); one
 *                            without an address is left out
 * @return array the fields for create_auto_email_campaigns()
 */
function pg_review_request_fields($order_number, $targets)
{
    $first_url = '';
    $lines = array();

    foreach ((array) $targets as $target) {
        $url = isset($target['url']) ? (string) $target['url'] : '';

        if ($url === '') {
            continue;
        }

        if ($first_url === '') {
            $first_url = $url;
        }

        $name = isset($target['name']) ? trim((string) $target['name']) : '';
        $lines[] = ($name !== '') ? $name . ': ' . $url : $url;
    }

    return array(
        array('name' => 'order_number', 'data' => (string) $order_number),
        array('name' => 'review_url', 'data' => $first_url),
        array('name' => 'review_links', 'data' => implode("\n", $lines)),
    );
}

/**
 * What to ask a review of, from an order's bought lines as
 * pg_review_request_targets() reads them: the group of options that sells
 * the product (where the page takes its reviews), or else the product. Each
 * item is asked about once. An item whose page cannot be opened is left out:
 * one without an address, and a product taken off sale that no group sells.
 *
 * @param array|null $rows product_id, product_name, product_short_description,
 *                         product_address_name, product_enabled, group_id,
 *                         group_name, group_short_description, group_address_name
 * @return array list of array('item_type', 'item_id', 'name', 'address_name')
 */
function pg_review_request_targets_from_rows($rows)
{
    $targets = array();

    foreach ((array) $rows as $row) {
        if ((int) $row['group_id'] > 0) {
            $item_type = 'product_group';
            $item_id = (int) $row['group_id'];
            $name = (string) $row['group_short_description'];
            $fallback_name = (string) $row['group_name'];
            $address_name = trim((string) $row['group_address_name']);
        } else {
            if ((string) $row['product_enabled'] !== '1') {
                continue;
            }

            $item_type = 'product';
            $item_id = (int) $row['product_id'];
            $name = (string) $row['product_short_description'];
            $fallback_name = (string) $row['product_name'];
            $address_name = trim((string) $row['product_address_name']);
        }

        $key = $item_type . ':' . $item_id;

        if (($item_id <= 0) || ($address_name === '') || isset($targets[$key])) {
            continue;
        }

        // The name shoppers know an item by: products.name is the SKU.
        $targets[$key] = array(
            'item_type'    => $item_type,
            'item_id'      => $item_id,
            'name'         => (trim($name) !== '') ? $name : $fallback_name,
            'address_name' => $address_name,
        );
    }

    return array_values($targets);
}

/**
 * What to ask a review of for an order: the items of its bought lines (a
 * line saved for later was not bought), in the order they were added. A
 * product sold in more than one group of options is reviewed on the oldest
 * of them (the lowest id).
 *
 * @param int $order_id
 * @return array see pg_review_request_targets_from_rows()
 */
function pg_review_request_targets($order_id)
{
    $order_id = (int) $order_id;

    if ($order_id <= 0) {
        return array();
    }

    // The groups of options are looked up for this order's products only,
    // in one statement with the lines.
    $rows = db_items(
        "SELECT
            order_items.product_id,
            products.name AS product_name,
            products.short_description AS product_short_description,
            products.address_name AS product_address_name,
            products.enabled AS product_enabled,
            product_groups.id AS group_id,
            product_groups.name AS group_name,
            product_groups.short_description AS group_short_description,
            product_groups.address_name AS group_address_name
        FROM order_items
        INNER JOIN products ON products.id = order_items.product_id
        LEFT JOIN (
            SELECT products_groups_xref.product, MIN(product_groups.id) AS id
            FROM products_groups_xref
            INNER JOIN product_groups ON product_groups.id = products_groups_xref.product_group
            WHERE
                (product_groups.display_type = 'select')
                AND (product_groups.enabled = '1')
                AND (products_groups_xref.product IN (SELECT order_items.product_id FROM order_items WHERE order_items.order_id = '" . $order_id . "'))
            GROUP BY products_groups_xref.product
        ) AS select_groups ON select_groups.product = order_items.product_id
        LEFT JOIN product_groups ON product_groups.id = select_groups.id
        WHERE
            (order_items.order_id = '" . $order_id . "')
            AND (order_items.product_id > 0)" . pg_order_item_active_sql() . "
        ORDER BY order_items.id ASC");

    return pg_review_request_targets_from_rows($rows);
}

/**
 * The name of the lock that keeps two saves of one order from both passing
 * the once-per-order check of its review request. GET_LOCK names belong to
 * the whole database server, so the name carries the database.
 *
 * @param int $order_id
 * @return string
 */
function pg_review_request_lock_name($order_id)
{
    $database = defined('DB_DATABASE') ? DB_DATABASE . '@' . (defined('DB_HOST') ? DB_HOST : '') : (string) db_value("SELECT DATABASE()");

    return 'pg_review_request_' . md5($database) . '_' . (int) $order_id;
}

/**
 * Starts the review request e-mail of an order that has just shipped.
 *
 * Nothing is started for an order placed without an account or not bought
 * (pg_review_bought_sql()), for one that already has a review request in any
 * state (sent, waiting or cancelled), or when no item of it has a review form
 * to link to. A shipment while no profile of the action is enabled starts
 * nothing either, so a later shipment of the same order may still start it
 * once one is. An order that never ships (downloads, services) gets no
 * request.
 *
 * The check for an earlier request and the start run under a lock of the
 * order: two saves of one order at once (parallel shipment posts, a retried
 * call) would otherwise both find none and both start one.
 *
 * @param int $order_id
 * @return bool whether create_auto_email_campaigns() was asked to start it
 */
function pg_review_request_start($order_id)
{
    $order_id = (int) $order_id;

    if (($order_id <= 0) || !pg_reviews_ready()) {
        return false;
    }

    $order = db_item(
        "SELECT
            orders.id,
            orders.order_number,
            orders.user_id,
            orders.contact_id,
            orders.billing_email_address
        FROM orders
        WHERE
            (orders.id = '" . $order_id . "')
            AND (orders.user_id > 0)
            AND " . pg_review_bought_sql());

    if (!is_array($order)) {
        return false;
    }

    $detail_page = function_exists('pg_sw_catalog_detail_page_name') ? (string) pg_sw_catalog_detail_page_name(0) : '';

    if ($detail_page === '') {
        return false;
    }

    // PATH, not OUTPUT_PATH: the fields are escaped for the e-mail's format
    // when they are filled in, and plain text takes the address as it is.
    $base_url = URL_SCHEME . HOSTNAME_SETTING . PATH;
    $targets = pg_review_request_targets($order_id);
    $links = array();

    foreach ($targets as $target) {
        $url = pg_review_request_url($base_url, $detail_page, $target['address_name']);

        if ($url !== '') {
            $links[] = array('name' => $target['name'], 'url' => $url);
        }
    }

    if (!$links) {
        return false;
    }

    $lock = pg_review_request_lock_name($order_id);

    if ((int) db_value("SELECT GET_LOCK('" . e($lock) . "', 5)") !== 1) {
        return false;
    }

    try {
        $requested = db_value(
            "SELECT 1 FROM email_campaigns
            WHERE
                (email_campaigns.action = 'order_review_request')
                AND (email_campaigns.order_id = '" . $order_id . "')
            LIMIT 1");

        if ($requested) {
            return false;
        }

        create_auto_email_campaigns(array(
            'action'        => 'order_review_request',
            'order_id'      => $order_id,
            'contact_id'    => $order['contact_id'],
            'email_address' => $order['billing_email_address'],
            'fields'        => pg_review_request_fields($order['order_number'], $links),
            'data'          => array(
                'order_id'       => $order_id,
                'review_targets' => $targets,
            ),
        ));

        return true;
    } finally {
        db_value("SELECT RELEASE_LOCK('" . e($lock) . "')");
    }
}

/**
 * Cancels the review request of an order that has not been sent yet (ready or
 * paused): the order was cancelled, and the review form would tell its
 * customer the order was not bought. The cancelled row stays, so the order
 * gets no new request, not even once it is restored (once per order).
 *
 * @param int $order_id
 * @return void
 */
function pg_review_request_cancel($order_id)
{
    $order_id = (int) $order_id;

    if ($order_id <= 0) {
        return;
    }

    db(
        "UPDATE email_campaigns
        SET
            email_campaigns.status = 'cancelled',
            email_campaigns.last_modified_user_id = '" . (defined('USER_ID') ? (int) USER_ID : 0) . "',
            email_campaigns.last_modified_timestamp = UNIX_TIMESTAMP()
        WHERE
            (email_campaigns.action = 'order_review_request')
            AND
            (
                (email_campaigns.status = 'ready')
                OR (email_campaigns.status = 'paused')
            )
            AND (email_campaigns.order_id = '" . $order_id . "')");
}

/**
 * What product.review.created carries: the comment, what it reviews, its
 * stars, whether it is a verified purchase and whether it is published,
 * held for moderation (pending) or scheduled, the states GET /comments
 * names.
 *
 * @param int        $comment_id
 * @param string     $item_type             'product' or 'product_group'
 * @param int        $item_id
 * @param int        $rating                0 for none
 * @param int        $verified_order_id     0 for none
 * @param string|int $published             comments.published
 * @param string     $publish_date_and_time comments.publish_date_and_time
 * @return array
 */
function pg_review_created_event($comment_id, $item_type, $item_id, $rating, $verified_order_id, $published, $publish_date_and_time)
{
    $scheduled = ((string) $publish_date_and_time !== '') && (strpos((string) $publish_date_and_time, '0000-00-00') !== 0);

    return array(
        'id'        => (int) $comment_id,
        'item_type' => (string) $item_type,
        'item_id'   => (int) $item_id,
        'rating'    => ((int) $rating > 0) ? (int) $rating : null,
        'verified'  => ((int) $verified_order_id > 0),
        'status'    => ((string) $published === '1') ? 'published' : ($scheduled ? 'scheduled' : 'pending'),
    );
}

/**
 * The SQL condition of Comment Moderation's verified filter.
 *
 * @param string $verified 'yes' (verified purchases), 'no' (everything else)
 * @return string '' for all comments, and for anything but the two choices
 */
function pg_review_verified_filter_sql($verified)
{
    if (!is_string($verified)) {
        return '';
    }

    switch ($verified) {
        case 'yes':
            return 'comments.verified_order_id > 0';
        case 'no':
            return 'comments.verified_order_id = 0';
    }

    return '';
}
