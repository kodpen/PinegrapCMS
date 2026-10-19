<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Product reviews from verified buyers: who may review a product, the rating
 * each product keeps and the aggregateRating its structured data prints.
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
// a product is the store speaking, and it counts in no rating.
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
