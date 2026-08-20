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
 *              2016–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');

$user = validate_user();
validate_ecommerce_access($user);

$liveform = new liveform('view_offer_rules');

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_offer_rules'][$key] = trim($value);
    }
}

// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_offer_rules']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_offer_rules']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_offer_rules']['sort'] ?? '')) {

    case lang('Name'):
        $sort_column = 'offer_rules.name';
        break;

    case lang('Required Subtotal'):
        $sort_column = 'offer_rules.required_subtotal';
        break;

    case lang('Required Quantity'):
        $sort_column = 'offer_rules.required_quantity';
        break;

    case lang('Last Modified'):
        $sort_column = 'offer_rules.timestamp';
        break;

    default:
        $sort_column = 'offer_rules.timestamp';
        $_SESSION['software']['ecommerce']['view_offer_rules']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_offer_rules']['order'] = 'desc';
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_offer_rules']['order']) == false) {
    $_SESSION['software']['ecommerce']['view_offer_rules']['order'] = 'asc';
}

$offer_rules = db_items(
    "SELECT
        offer_rules.id,
        offer_rules.name,
        offer_rules.required_subtotal / 100 AS required_subtotal,
        offer_rules.required_quantity,
        last_modified_user.user_username AS last_modified_username,
        offer_rules.timestamp as last_modified_timestamp
    FROM offer_rules
    LEFT JOIN user AS last_modified_user ON offer_rules.user = last_modified_user.user_id
    ORDER BY $sort_column " . e(($_SESSION['software']['ecommerce']['view_offer_rules']['order'] ?? '')));

foreach ($offer_rules as $key => $offer_rule) {

    $offer_rule['products'] = db_items("
        SELECT
            products.id,
            products.name,
            products.short_description
        FROM offer_rules_products_xref
        LEFT JOIN products ON offer_rules_products_xref.product_id = products.id
        WHERE offer_rules_products_xref.offer_rule_id = '" . e($offer_rule['id']) . "'
        ORDER BY name, short_description");

    $offer_rules[$key] = $offer_rule;
}

echo pg_page_shell([
        'title'=> lang('All Offer Rules'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Offer Rules'),
        'auto_main'=>false,
    ]);

require('assets/templates/view_offer_rules.php');

echo output_footer();

$liveform->remove();