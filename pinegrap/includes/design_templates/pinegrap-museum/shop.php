<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Museum", its shop: the catalog (the Admission group, a
 * product for each ticket of $admission, and the Membership group, a
 * product for each tier of $tiers), the tickets and membership pages, the
 * product, cart, checkout and order pages and the order receipt e-mail.
 * Included after parts.php and mail.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ..., 'catalog' => ...).
 *
 * Every page and widget here needs the shop: the shared parts and the
 * public pages never link them (the visit and support pages say the
 * prices as words), so a site without the shop has none of them and no
 * dead link.
 *
 * The prices are parts.php's own ($admission_prices, $tier_prices): the
 * cards write them with $money(), the products are made from the same
 * figures, so the page and the cart show one amount. A ticket is shown at
 * the desk from the receipt and a membership card is sent by e-mail:
 * nothing is shipped, so the checkout asks for no delivery address.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$shop_pages = array();

// ── The catalog ─────────────────────────────────────────────────────────
// Made once when the template is first opened (_pg_tpl_catalog()): the
// Admission and Membership groups under the site's top group, each
// product found again by its SKU, in the order of the data.
$admission_products = array();
foreach ($admission as $key => $a) {
    $admission_products[] = array(
        'sku'         => 'MUSEUM-TICKET-' . strtoupper($key),
        'title'       => sprintf(lang('Admission Ticket: %s'), $a['title']),
        'description' => $a['who'] . '. ' . $a['includes'] . '.',
        'price'       => $admission_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'adult',
        'keywords'    => lang('ticket, admission, entry, visit, museum'),
    );
}
$membership_products = array();
foreach ($tiers as $key => $t) {
    $membership_products[] = array(
        'sku'         => 'MUSEUM-MEMBER-' . strtoupper($key),
        'title'       => sprintf(lang('Membership: %s'), $t['title']),
        'description' => $t['who'] . '. ' . implode(' · ', $t['benefits']) . '.',
        'price'       => $tier_prices[$key],
        'shippable'   => false,
        'featured'    => $key === 'joint',
        'keywords'    => lang('membership, member, friends, support, free entry'),
    );
}
$catalog = array(
    'folder'  => 'pinegrap_museum_catalog',
    // The Details tab of every product's page.
    'details' => array(
        lang('Show the order receipt on your phone or on paper at the desk on the day of your visit; a ticket is valid for one visit.'),
        lang('A membership starts on the day of the order and lasts a year; your member card comes by e-mail within two working days.'),
        lang('A sample product of the template: change its name and price in the catalog, or remove it.'),
    ),
    'groups'  => array(
        'admission' => array(
            'parent'      => 'root',
            'title'       => lang('Admission'),
            'description' => lang('Tickets for the collection galleries and the ticketed exhibition.'),
            'products'    => $admission_products,
        ),
        'membership' => array(
            'parent'      => 'root',
            'title'       => lang('Membership'),
            'description' => lang('A year of free entry to every exhibition, and more, for you and your family.'),
            'products'    => $membership_products,
        ),
    ),
);

$shop_widgets = array();

return array('pages' => $shop_pages, 'widgets' => $shop_widgets, 'catalog' => $catalog);
