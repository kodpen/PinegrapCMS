<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visitor notifications for an order (kind 3): a browser that pressed
 * "Notify me on this device" on the order's own page is told when the order
 * is shipped and when it is delivered. Transactional, as the order's text
 * message is: it goes to that one device, never to every subscriber, and
 * stays out of the panel's list of sent messages.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

define('PG_SITE_PUSH_KIND_ORDER', 3);

/**
 * The title and text of an order's moment in the subscription's language,
 * cut to the message columns (title 60, text 200).
 *
 * @param string $moment       shipped or delivered
 * @param string $order_number
 * @param string $language     the subscription's language ('' the site's)
 * @return array|null title, body; null for a moment that sends nothing
 */
function pg_site_push_order_text($moment, $order_number, $language)
{
    $texts = array(
        'shipped'   => array('Your order has shipped', 'Order {var:1} is on its way.'),
        'delivered' => array('Your order has been delivered', 'Order {var:1} has been delivered.'),
    );

    if (!isset($texts[(string) $moment])) {
        return null;
    }

    $in = array();

    if ((string) $language !== '') {
        $in['language'] = (string) $language;
    }

    $title = lang(array('string' => $texts[$moment][0]) + $in);
    $body = lang(array('string' => $texts[$moment][1], 'vars' => array((string) $order_number)) + $in);

    return array(
        'title' => mb_substr($title, 0, 60, 'UTF-8'),
        'body'  => mb_substr($body, 0, 200, 'UTF-8'),
    );
}
