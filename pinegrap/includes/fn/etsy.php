<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Etsy: the provider of connected accounts (includes/connect/providers/etsy.php)
 * and the parsers of the answers its marketplace connector reads from the
 * seller's shop (includes/api/outbound/connectors/etsy.php).
 *
 * Etsy's Open API v3 signs a seller in with OAuth 2.0 and PKCE: the
 * application is a public client towards the token endpoint, which takes the
 * keystring as client_id and no secret. Every request, the token's
 * included, carries the header x-api-key "keystring:shared_secret", so the
 * shared secret is kept as the application's client secret and only ever
 * sent in that header. An access token lives an hour, its refresh token 90
 * days. Etsy has no sandbox: an application is tried on a test shop.
 *
 * The pure parts are tested in tests/etsy_test.php.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

/**
 * The address every Open API v3 call starts with.
 *
 * @return string
 */
function pg_etsy_api_base()
{
    return 'https://api.etsy.com/v3/application';
}

/**
 * What the seller allows: reading and writing listings, reading the shop
 * (its shipping and processing profiles), and reading orders and reporting
 * their shipment.
 *
 * @return array
 */
function pg_etsy_scopes()
{
    return array('listings_r', 'listings_w', 'shops_r', 'transactions_r', 'transactions_w');
}

/**
 * The x-api-key header of an application: its keystring and shared secret
 * joined by a colon, as Etsy asks of every request since 9 February 2026;
 * none while either is missing. Pure.
 *
 * @param array|null $app pg_connect_app()
 * @return array header lines
 */
function pg_etsy_app_headers($app)
{
    $keystring = is_array($app) && isset($app['client_id']) ? trim((string) $app['client_id']) : '';
    $secret = is_array($app) && isset($app['client_secret']) ? trim((string) $app['client_secret']) : '';

    if (($keystring === '') || ($secret === '')) {
        return array();
    }

    return array('x-api-key: ' . $keystring . ':' . $secret);
}

/**
 * The provider definition of connected accounts. The site's own Etsy
 * application signs in with the authorization code and PKCE; the token
 * request sends no secret ('client_auth' => 'none') and the x-api-key
 * header ('app_headers'). The seller signs in once; the shop is read from
 * the sign-in by the connector's test.
 *
 * @return array
 */
function pg_etsy_provider()
{
    return array(
        'label'            => 'Etsy',
        'icon'             => 'bi-shop',
        'authorize_url'    => 'https://www.etsy.com/oauth/connect',
        'token_url'        => 'https://api.etsy.com/v3/public/oauth/token',
        'issuers'          => array(),
        'identity_scopes'  => array(),
        'identity'         => false,
        'account_label'    => 'Etsy',
        'nonce'            => false,
        'authorize_params' => array(),
        'client_auth'      => 'none',
        'app_headers'      => 'pg_etsy_app_headers',
        'token_per_scope'  => false,
        'default_ttl'      => 3600,
        'connect_purpose'  => 'etsy',
        'console_url'      => 'https://www.etsy.com/developers/your-apps',
        'options'          => array(),
        'redirect_help'    => lang('Enter this address as a callback URL of your Etsy app. It has to match exactly and be an https address.'),
        'setup'            => array(
            lang('Create an app for your own shop under Your apps on Etsy\'s developer site and add the redirect address below as its callback URL.'),
            lang('Copy the keystring as the Client ID and the shared secret as the client secret, save, then press Connect and sign in as the shop\'s owner.'),
            lang('A new app is a personal one, which reaches up to five shops; a commercial one is reviewed by Etsy by hand. Etsy names no fee for the API.'),
            lang('Etsy lets a shop sell what it makes or designs, vintage items and craft supplies; mass-produced goods bought to resell are not allowed. Etsy charges USD 0.20 a listing and 6.5% of a sale.'),
            lang('Etsy has no sandbox: try the app on a test shop before your own.'),
        ),
    );
}


/* ---------------------------------------------------------------------------
   The seller's choices (connectors/etsy.php)
   --------------------------------------------------------------------------- */

/**
 * The message of an Etsy error answer ({"error": "…"}), or the HTTP status.
 * Pure.
 *
 * @param array $result ok, status, data, error
 * @return string
 */
function pg_etsy_error_text($result)
{
    $data = (isset($result['data']) && is_array($result['data'])) ? $result['data'] : array();

    if (isset($data['error']) && is_string($data['error']) && ($data['error'] !== '')) {
        return mb_substr('Etsy: ' . $data['error'], 0, 255);
    }

    if (isset($result['error']) && ((string) $result['error'] !== '')) {
        return mb_substr((string) $result['error'], 0, 255);
    }

    return 'Etsy: HTTP ' . (int) (isset($result['status']) ? $result['status'] : 0);
}

/**
 * Whether a failed call is worth trying again later: no answer, a rate
 * limit or a fault on Etsy's side. Pure.
 *
 * @param array $result
 * @return bool
 */
function pg_etsy_retry($result)
{
    $status = isset($result['status']) ? (int) $result['status'] : 0;

    return ($status === 0) || ($status === 429) || ($status >= 500);
}

/**
 * The shop id of a getMe answer, or ''. Pure.
 *
 * @param mixed $data
 * @return string
 */
function pg_etsy_shop_id($data)
{
    $id = (is_array($data) && isset($data['shop_id']) && is_scalar($data['shop_id'])) ? trim((string) $data['shop_id']) : '';

    return preg_match('/^[1-9][0-9]{0,19}$/D', $id) ? $id : '';
}

/**
 * The shop of a getShop answer as a select's one choice: shop id => "name ·
 * currency". Pure.
 *
 * @param string $shop_id
 * @param mixed  $data
 * @return array
 */
function pg_etsy_shop_choice($shop_id, $data)
{
    $name = (is_array($data) && isset($data['shop_name']) && is_scalar($data['shop_name'])) ? trim((string) $data['shop_name']) : '';
    $currency = (is_array($data) && isset($data['currency_code']) && is_scalar($data['currency_code'])) ? strtoupper(trim((string) $data['currency_code'])) : '';

    $label = ($name !== '') ? $name : (string) $shop_id;

    if (preg_match('/^[A-Z]{3}$/D', $currency)) {
        $label .= ' · ' . $currency;
    }

    return array((string) $shop_id => $label);
}

/**
 * The shipping profiles of a getShopShippingProfiles answer as a select's
 * choices: id => title (the id when it has none). Pure.
 *
 * @param mixed $data
 * @return array
 */
function pg_etsy_shipping_profile_choices($data)
{
    $choices = array();
    $list = (is_array($data) && isset($data['results']) && is_array($data['results'])) ? $data['results'] : array();

    foreach ($list as $profile) {
        $id = (is_array($profile) && isset($profile['shipping_profile_id']) && is_scalar($profile['shipping_profile_id'])) ? trim((string) $profile['shipping_profile_id']) : '';

        if ($id === '') {
            continue;
        }

        $title = isset($profile['title']) && is_scalar($profile['title']) ? trim((string) $profile['title']) : '';
        $choices[$id] = ($title !== '') ? $title : $id;
    }

    return $choices;
}

/**
 * The processing profiles of a getShopReadinessStateDefinitions answer as a
 * select's choices: readiness_state_id => "Ready to ship · 1–3 days". The
 * words are put in the language of the person who tested. Pure but for
 * lang().
 *
 * @param mixed $data
 * @return array
 */
function pg_etsy_readiness_choices($data)
{
    $choices = array();
    $list = (is_array($data) && isset($data['results']) && is_array($data['results'])) ? $data['results'] : array();

    foreach ($list as $state) {
        $id = (is_array($state) && isset($state['readiness_state_id']) && is_scalar($state['readiness_state_id'])) ? trim((string) $state['readiness_state_id']) : '';

        if ($id === '') {
            continue;
        }

        $kind = isset($state['readiness_state']) ? (string) $state['readiness_state'] : '';
        $label = ($kind === 'made_to_order') ? lang('Made to order') : lang('Ready to ship');

        $min = isset($state['min_processing_time']) && is_numeric($state['min_processing_time']) ? (int) $state['min_processing_time'] : 0;
        $max = isset($state['max_processing_time']) && is_numeric($state['max_processing_time']) ? (int) $state['max_processing_time'] : 0;

        if ($max > 0) {
            $unit = (isset($state['processing_time_unit']) && ($state['processing_time_unit'] === 'weeks')) ? lang('weeks') : lang('days');
            $label .= ' · ' . (($min > 0 && $min !== $max) ? $min . '–' . $max : $max) . ' ' . $unit;
        }

        $choices[$id] = $label;
    }

    return $choices;
}

/**
 * Who made the goods a listing offers, as Etsy's who_made values. Pure but
 * for lang().
 *
 * @return array value => label
 */
function pg_etsy_who_made_choices()
{
    return array(
        'i_did'        => lang('I did'),
        'collective'   => lang('A member of my shop'),
        'someone_else' => lang('Another company or person'),
    );
}

/**
 * When the goods a listing offers were made, as Etsy's when_made values,
 * newest first. Etsy moves the first range's end with the year. Pure but
 * for lang().
 *
 * @return array value => label
 */
function pg_etsy_when_made_choices()
{
    $choices = array(
        'made_to_order' => lang('Made to order'),
        '2020_2026'     => '2020–2026',
        '2010_2019'     => '2010–2019',
        '2007_2009'     => '2007–2009',
        'before_2007'   => lang('Before 2007'),
        '2000_2006'     => '2000–2006',
    );

    foreach (array('1990s', '1980s', '1970s', '1960s', '1950s', '1940s', '1930s', '1920s', '1910s', '1900s', '1800s', '1700s') as $decade) {
        $choices[$decade] = $decade;
    }

    $choices['before_1700'] = lang('Before 1700');

    return $choices;
}


/* ---------------------------------------------------------------------------
   Seller taxonomy (connectors/etsy.php): categories and their properties
   --------------------------------------------------------------------------- */

/**
 * A getSellerTaxonomyNodes answer as the rows of the category cache: every
 * node with its parent and its path of names, depth first in Etsy's order.
 * Etsy nests a node's children under it; a node without children is a leaf,
 * the only kind a listing takes as its taxonomy_id. A node whose id is not a
 * number is left out with its branch. Pure.
 *
 * @param mixed $data
 * @return array remote_id, parent_id, name, path, is_leaf
 */
function pg_etsy_taxonomy_rows($data)
{
    $rows = array();
    $list = (is_array($data) && isset($data['results']) && is_array($data['results'])) ? $data['results'] : array();
    $stack = array();
    $seen = array();

    // A stack rather than recursion, pushed in reverse so the rows come out
    // in Etsy's order.
    foreach (array_reverse($list) as $node) {
        $stack[] = array($node, '', '');
    }

    while ($stack) {
        list($node, $parent_id, $parent_path) = array_pop($stack);

        $id = (is_array($node) && isset($node['id']) && is_scalar($node['id'])) ? trim((string) $node['id']) : '';

        if ((preg_match('/^[0-9]{1,20}$/', $id) !== 1) || isset($seen[$id])) {
            continue;
        }

        $seen[$id] = true;
        $name = (isset($node['name']) && is_scalar($node['name'])) ? trim((string) $node['name']) : '';
        $path = ($parent_path === '') ? $name : ($parent_path . ' > ' . $name);
        $children = (isset($node['children']) && is_array($node['children'])) ? $node['children'] : array();

        $rows[] = array(
            'remote_id' => $id,
            'parent_id' => $parent_id,
            'name'      => $name,
            'path'      => $path,
            'is_leaf'   => $children ? 0 : 1,
        );

        foreach (array_reverse($children) as $child) {
            $stack[] = array($child, $id, $path);
        }
    }

    return $rows;
}

/**
 * A getPropertiesByTaxonomyId answer as the attribute rows of the category
 * cache. The property id is the attribute id and each possible value's id
 * its value id, as updateListingProperty takes them. Required is
 * is_required; a property with no possible values takes the shop's own
 * text; one that supports variations is answered by each product's own
 * option, the article's answer being its fallback. A value of a property
 * with more than one scale (sizes: US, UK, EU) carries its scale's name, so
 * "8" of two scales are told apart. A property that is neither an attribute
 * nor a variation is left out. Pure.
 *
 * @param mixed $data
 * @return array attribute_id, name, mandatory, is_variant, custom_allowed, sort_order, values
 */
function pg_etsy_property_rows($data)
{
    $rows = array();
    $list = (is_array($data) && isset($data['results']) && is_array($data['results'])) ? $data['results'] : array();
    $seen = array();

    foreach ($list as $property) {
        $id = (is_array($property) && isset($property['property_id']) && is_scalar($property['property_id'])) ? trim((string) $property['property_id']) : '';

        if ((preg_match('/^[0-9]{1,20}$/', $id) !== 1) || isset($seen[$id])) {
            continue;
        }

        if (empty($property['supports_attributes']) && empty($property['supports_variations'])) {
            continue;
        }

        $seen[$id] = true;
        $name = (isset($property['display_name']) && is_scalar($property['display_name'])) ? trim((string) $property['display_name']) : '';

        if ($name === '') {
            $name = (isset($property['name']) && is_scalar($property['name'])) ? trim((string) $property['name']) : $id;
        }

        $scales = array();

        foreach ((isset($property['scales']) && is_array($property['scales'])) ? $property['scales'] : array() as $scale) {
            if (is_array($scale) && isset($scale['scale_id']) && is_scalar($scale['scale_id']) && isset($scale['display_name']) && is_scalar($scale['display_name'])) {
                $scales[(string) $scale['scale_id']] = trim((string) $scale['display_name']);
            }
        }

        $values = array();
        $known = array();

        foreach ((isset($property['possible_values']) && is_array($property['possible_values'])) ? $property['possible_values'] : array() as $value) {
            $value_id = (is_array($value) && isset($value['value_id']) && is_scalar($value['value_id'])) ? trim((string) $value['value_id']) : '';
            $text = (is_array($value) && isset($value['name']) && is_scalar($value['name'])) ? trim((string) $value['name']) : '';

            if (($value_id === '') || ($text === '') || isset($known[$value_id])) {
                continue;
            }

            $scale_id = (isset($value['scale_id']) && is_scalar($value['scale_id'])) ? (string) $value['scale_id'] : '';

            if ((count($scales) > 1) && isset($scales[$scale_id]) && ($scales[$scale_id] !== '')) {
                $text .= ' (' . $scales[$scale_id] . ')';
            }

            $known[$value_id] = true;
            $values[] = array('id' => $value_id, 'value' => $text);
        }

        $rows[] = array(
            'attribute_id'   => $id,
            'name'           => $name,
            'mandatory'      => !empty($property['is_required']) ? 1 : 0,
            'is_variant'     => !empty($property['supports_variations']) ? 1 : 0,
            'custom_allowed' => $values ? 0 : 1,
            'sort_order'     => count($rows),
            'values'         => $values,
        );
    }

    return $rows;
}
