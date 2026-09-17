<?php
/**
 * PineGrap - Enterprise Website Platform
 *
 * Originally developed as LiveSite by Camelback Web Architects.
 * Since 2017, maintained and evolved by Erdal Güral (Kodpen) under the name PineGrap.
 * The final LiveSite update (2019) has been integrated into PineGrap.
 * LiveSite remains available as a separate downloadable legacy version.
 *
 * @author      Camelback Web Architects
 *              Erdal Güral (Kodpen)
 * @link        https://livesite.com
 *              https://kodpen.com
 * @copyright   2001–2019 Camelback Consulting, Inc.
 *              2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// The offer editor: one screen for the offer, its conditions and its results.
// Without an id it creates; with ?duplicate=N it opens a copy of offer N that
// is not written until saved. Saving goes through api.php (action
// "offer_editor"), so this file only draws the screen.

include('init.php');
$user = validate_user();
validate_ecommerce_access($user);
require_once('edit_offer_f.php');

$offer_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$duplicate_of = isset($_GET['duplicate']) ? (int) $_GET['duplicate'] : 0;
$is_new = ($offer_id <= 0);

if (!$is_new) {
    $state = _pg_offer_load($offer_id);
    if (!$state) {
        output_error(lang('The offer could not be found.') . ' <a href="view_offers.php">' . lang('Go back') . '</a>.');
    }
} else {
    $state = _pg_offer_new_state($duplicate_of);
}

// Everything offer_editor.js needs, handed over once as JSON. The script
// cannot call lang(), so every string it shows is translated here.
$editor = array(
    'token'            => $_SESSION['software']['token'],
    'state'            => $state,
    'context'          => _pg_offer_editor_context($state),
    'products'         => _pg_offer_products(),
    'shipping_methods' => _pg_offer_shipping_methods(),
    'product_groups'   => _pg_offer_product_groups(),
    'condition_types'  => _pg_offer_condition_types(),
    'action_types'     => _pg_offer_action_types(),
    'pages_html'       => select_page($state['offer']['upsell']['page_id']),
    'labels'           => array(
        'save'                   => lang('Save'),
        'create'                 => lang('Create'),
        'saveIncomplete'         => lang('Save · {var:1} to fix'),
        'incomplete'             => lang('{var:1} to fix'),
        'saved'                  => lang('The offer has been saved.'),
        'notSaved'               => lang('Not saved yet'),
        'unsavedChanges'         => lang('unsaved changes'),
        'statusActive'           => lang('Active'),
        'statusScheduled'        => lang('Planned'),
        'statusExpired'          => lang('Expired'),
        'statusDisabled'         => lang('Disabled'),
        'automatic'              => lang('Automatic'),
        'whenCode'               => lang('When the customer enters {var:1}'),
        'everyOrder'             => lang('every order'),
        'and'                    => lang('and'),
        'nothingHappens'         => lang('nothing happens'),
        'openEnded'              => lang('open-ended'),
        'enabledWord'            => lang('enabled'),
        'disabledWord'           => lang('disabled'),
        'upsellOn'               => lang('up-sell message on'),
        'upsellOff'              => lang('up-sell message off'),
        'bestOffer'              => lang('best offer only'),
        'subtotalText'           => lang('cart is {var:1} or more'),
        'productsText'           => lang('cart has at least {var:1} of {var:2}'),
        'orderText'              => lang('{var:1} off the order'),
        'tiersText'              => lang('off the order, by tier: {var:1}'),
        'flatRate'               => lang('single rate'),
        'tiered'                 => lang('tiered'),
        'tierFrom'               => lang('from'),
        'addTier'                => lang('+ tier'),
        'tierHint'               => lang('the highest tier the cart reaches wins'),
        'tierPercent'            => lang('Please enter a percentage between 1 and 100 for every tier.'),
        'productText'            => lang('{var:1} off {var:2}'),
        'giftFree'               => lang('{var:1} × {var:2} added as a gift'),
        'giftFull'               => lang('{var:1} × {var:2} added to the cart'),
        'giftDiscount'           => lang('{var:1} × {var:2} added with {var:3} off'),
        'shippingText'           => lang('%{var:1} off shipping ({var:2})'),
        'noConditions'           => lang('No conditions — the offer applies to every order. Add a condition on the right to narrow it down.'),
        'noActions'              => lang('No results yet — this offer does nothing. Add one on the right.'),
        'requireCodeOn'          => lang('applies to customers who enter this code at checkout'),
        'requireCodeOff'         => lang('off: the offer applies by itself once the conditions are met'),
        'keyCodes'               => lang('{var:1} key code(s) linked to this code'),
        'orderCount'             => lang('{var:1} order(s) placed with this code'),
        'groupsText'             => lang('cart has at least {var:1} from {var:2}'),
        'cartQuantityText'       => lang('cart holds at least {var:1} item(s)'),
        'usageTotalText'         => lang('used at most {var:1} time(s) in total'),
        'usageCustomerText'      => lang('at most {var:1} time(s) per customer'),
        'noUsageLimit'           => lang('no usage limit'),
        'usageTotalLabel'        => lang('in total'),
        'usageCustomerLabel'     => lang('per customer'),
        'usageHint'              => lang('counts finished orders the offer discounted — by code, by key code or automatically; leave a box empty for no limit'),
        'enterLimit'             => lang('Please enter at least one limit.'),
        'targetProduct'          => lang('product'),
        'targetGroup'            => lang('group'),
        'targetCheapest'         => lang('cheapest'),
        'cheapestFree'           => lang('the cheapest item in the cart is free'),
        'cheapestText'           => lang('{var:1} off the cheapest item in the cart'),
        'cheapestHint'           => lang('the least expensive line the customer is paying for; a gift an offer added does not count'),
        'groupProductText'       => lang('{var:1} off everything in {var:2}'),
        'selectGroup'            => lang('Please select a product group.'),
        'selectGroupPlaceholder' => '– ' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('group')))) . ' –',
        'addGroup'               => lang('+ group'),
        'selectGroups'           => lang('Please select at least one product group.'),
        'noGroups'               => lang('No product groups have been created yet.'),
        'newCustomerNever'       => lang('the customer has never ordered'),
        'newCustomerDays'        => lang('the customer registered in the last {var:1} day(s)'),
        'newCustomerBoth'        => lang('the customer registered in the last {var:1} day(s) and has never ordered'),
        'newCustomerModes'       => array(
            'no_orders'  => lang('has never ordered'),
            'registered' => lang('registered recently'),
            'both'       => lang('both')),
        'days'                   => lang('days'),
        'enterDays'              => lang('Please enter a number of days of at least 1.'),
        'maxDays'                => lang('Please enter at most 3650 days.'),
        'atLeast'                => lang('at least'),
        'pieces'                 => lang('pcs'),
        'quantity'               => lang('quantity'),
        'discount'               => lang('discount'),
        'giftHint'               => lang('100% = gift'),
        'methods'                => lang('methods:'),
        'addProduct'             => lang('+ product'),
        'addMethod'              => lang('+ method'),
        'selectProductPlaceholder' => '– ' . lang(array('string' => 'Select {var:1}', 'vars' => array(lang('product')))) . ' –',
        'disabledProduct'        => lang('DISABLED'),
        'added'                  => lang('added'),
        'remove'                 => lang('Remove'),
        'removeCondition'        => lang('Remove condition'),
        'removeResult'           => lang('Remove result'),
        'enterSubtotal'          => lang('Please enter a subtotal.'),
        'selectProducts'         => lang('Please select at least one product.'),
        'enterQuantity'          => lang('Please enter a quantity of at least 1.'),
        'selectProduct'          => lang('Please select a product.'),
        'enterValue'             => lang('Please enter a value.'),
        'percentMax'             => lang('A percentage cannot be more than 100.'),
        'selectMethod'           => lang('Please select at least one shipping method.'),
        'enterDates'             => lang('Please enter a start date and an end date.'),
        'dateOrder'              => lang('The end date must not be before the start date.'),
        'requestFailed'          => lang('Sorry, we could not accept your request.'),
        'keyCodesDeleteTitle'    => lang('Delete Key Codes'),
        'keyCodesDeleteConfirm'  => lang('WARNING: The {var:1} key code(s) of this offer will be permanently deleted.'),
        'deleteTitle'            => lang('Delete Offer'),
        'deleteConfirm'          => lang(array('string' => 'WARNING: This {var:1} will be permanently deleted.', 'vars' => array(lang('offer')))),
        'deleteButton'           => lang('Delete'),
        'cancelButton'           => lang('Cancel')));

$heading = $is_new ? lang('Create Offer') : lang('Edit Offer');

echo pg_page_shell(array(
    'title'               => $heading,
    'extra classes'       => 'products',
    'icon'                => 'store',
    'heading'             => $heading,
    'heading_description' => lang('One screen: the offer, the conditions the cart must meet and the results that apply.'),
    'cancel'              => array('enable' => 'true', 'url' => 'view_offers.php'),
    'breadcrumb'          => array(
        array('label' => lang('All Offers'), 'url' => OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY . '/view_offers.php'),
        array('label' => $heading))));

require('includes/templates/edit_offer.php');

echo output_footer();
