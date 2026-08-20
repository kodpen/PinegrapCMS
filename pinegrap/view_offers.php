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

// store all values collected in request to session
foreach ($_REQUEST as $key => $value) {
    // if the value is a string then add it to the session
    // we have to do this check because cookie arrays are sometimes included in the $_REQUEST array,
    // for certain php.ini settings
    if (is_string($value) == TRUE) {
        $_SESSION['software']['ecommerce']['view_offers'][$key] = trim($value);
    }
}


// if the sort is not set yet, then default it to empty so that the switch below falls
// through to its default case
if (isset($_SESSION['software']['ecommerce']['view_offers']['sort']) == false) {
    $_SESSION['software']['ecommerce']['view_offers']['sort'] = '';
}

switch (($_SESSION['software']['ecommerce']['view_offers']['sort'] ?? '')) {
    case lang('Offer Code'):
        $sort_column = 'offers.code';
        break;

    case lang('Message'):
        $sort_column = 'offers.description';
        break;

    case lang('Rule'):
        $sort_column = 'offer_rules.name';
        break;

    case lang('Status'):
        $sort_column = 'offers.status';
        break;

    case lang('Start Date'):
        $sort_column = 'offers.start_date';
        break;

    case lang('End Date'):
        $sort_column = 'offers.end_date';
        break;

    case lang('Require Code'):
        $sort_column = 'offers.require_code';
        break;
        
    case lang('Best'):
        $sort_column = 'offers.only_apply_best_offer';
        break;

    case lang('Last Modified'):
        $sort_column = 'offers.timestamp';
        break;

    default:
        $sort_column = 'offers.timestamp';
        $_SESSION['software']['ecommerce']['view_offers']['sort'] = lang('Last Modified');
        $_SESSION['software']['ecommerce']['view_offers']['order'] = 'desc';
}

// if order is not set, set to ascending
if (isset($_SESSION['software']['ecommerce']['view_offers']['order']) == false) {
    $_SESSION['software']['ecommerce']['view_offers']['order'] = 'asc';
}

$offers = db_items(
    "SELECT
        offers.id,
        offers.code,
        offers.description,
        offer_rules.id as offer_rule_id,
        offer_rules.name as offer_rule_name,
        offers.status,
        offers.start_date,
        offers.end_date,
        offers.require_code,
        offers.only_apply_best_offer,
        last_modified_user.user_username AS last_modified_username,
        offers.timestamp AS last_modified_timestamp
    FROM offers
    LEFT JOIN offer_rules ON offers.offer_rule_id = offer_rules.id
    LEFT JOIN user AS last_modified_user ON offers.user = last_modified_user.user_id
    ORDER BY $sort_column " . e(($_SESSION['software']['ecommerce']['view_offers']['order'] ?? '')) . "");

// Get the current date so that later we can figure out if offers are active.
$current_date = date('Y-m-d');

// Loop through the offers in order to prepare them.
foreach ($offers as $key => $offer) {

    // Default to not active, so that the template can always read this key.
    $offer['status_enabled'] = false;

    // If this offer is active, then store that, so a color can be used to indicate that.
    if (
        $offer['status'] == 'enabled'
        and $offer['start_date'] <= $current_date
        and $offer['end_date'] >= $current_date
    ) {
        $offer['status_enabled'] = true;
    }

    $offer['actions'] = db_items(
        "SELECT
            offer_actions.id,
            offer_actions.name
        FROM offers_offer_actions_xref
        LEFT JOIN offer_actions ON offers_offer_actions_xref.offer_action_id = offer_actions.id
        WHERE offers_offer_actions_xref.offer_id = '" . e($offer['id']) . "'
        ORDER BY offer_actions.name");

    $offers[$key] = $offer;
}

echo pg_page_shell([
        'title'=> lang('All Offers'),
        'extra classes'=>'products',
        'icon'=>'store',
        'heading'=>lang('All Offers'),
        'auto_main'=>false,
    ]);

require('assets/templates/view_offers.php');

echo output_footer();