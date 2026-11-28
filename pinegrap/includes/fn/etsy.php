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


/* ---------------------------------------------------------------------------
   Listings (connectors/etsy.php): draft, pictures, properties, inventory
   --------------------------------------------------------------------------- */

/**
 * The longest title Etsy takes.
 *
 * @return int
 */
function pg_etsy_title_max()
{
    return 140;
}

/**
 * The most pictures sent to one listing.
 *
 * @return int
 */
function pg_etsy_image_max()
{
    return 10;
}

/**
 * The most a single offering of a listing may hold.
 *
 * @return int
 */
function pg_etsy_quantity_max()
{
    return 999;
}

/**
 * The price a listing is sent at, in the shop's currency: the shop's price
 * (price_major) times the rate from the base currency, rounded half away
 * from zero to the cent. '' when there is no price or no rate. Pure.
 *
 * @param float      $price_major
 * @param float|null $rate
 * @return string "12.34"
 */
function pg_etsy_price($price_major, $rate)
{
    if (!is_numeric($price_major) || !is_numeric($rate) || ((float) $rate <= 0)) {
        return '';
    }

    // Through the minor unit first, so a price of 19.99 is 1999 before the
    // rate and never 19.989999… cut short.
    $cents = round(round((float) $price_major * 100) * (float) $rate);

    if ($cents <= 0) {
        return '';
    }

    return number_format($cents / 100, 2, '.', '');
}

/**
 * A product's description as the plain text Etsy shows: tags dropped, a
 * paragraph or line break kept as a new line, entities decoded, runs of
 * blank lines and spaces folded. Pure.
 *
 * @param string $html
 * @return string
 */
function pg_etsy_plain_text($html)
{
    $text = preg_replace('~<\s*(br|/p|/div|/li|/h[1-6]|/tr)\b[^>]*>~i', "\n", (string) $html);
    $text = preg_replace('~<\s*li\b[^>]*>~i', '- ', $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = preg_replace('/[ \t]+/u', ' ', $text);
    $text = preg_replace('/ *\n */u', "\n", $text);
    $text = preg_replace('/\n{3,}/u', "\n\n", $text);

    return trim((string) $text);
}

/**
 * Why Etsy would refuse an item before anything is sent, or ''. Pure but
 * for lang().
 *
 * @param array $item mp_listing_items() item
 * @return string
 */
function pg_etsy_item_problem($item)
{
    $code = isset($item['code']) ? (string) $item['code'] : '';

    if ($code === '') {
        return lang('A product of this article has no product code.');
    }

    if (empty($item['images'])) {
        return lang(array('string' => '{var:1} has no picture. Etsy publishes a listing only with one.', 'vars' => array($code)));
    }

    return '';
}

/**
 * Why Etsy would refuse the listing's title, or ''. Pure but for lang().
 *
 * @param string $title
 * @return string
 */
function pg_etsy_title_problem($title)
{
    if (trim((string) $title) === '') {
        return lang('The article has no title.');
    }

    if (mb_strlen((string) $title) > pg_etsy_title_max()) {
        return lang(array('string' => 'The title is longer than the {var:1} characters Etsy takes.', 'vars' => array(pg_etsy_title_max())));
    }

    return '';
}

/**
 * The listing's title: the article's, else its first product's. Pure.
 *
 * @param array $items
 * @return string
 */
function pg_etsy_listing_title($items)
{
    $first = reset($items);

    if (!is_array($first)) {
        return '';
    }

    $title = isset($first['group_title']) ? trim((string) $first['group_title']) : '';

    if (($title === '') || (count($items) === 1)) {
        $own = isset($first['title']) ? trim((string) $first['title']) : '';
        $title = ($own !== '') ? $own : $title;
    }

    return $title;
}

/**
 * The pictures of an article, in the order of its first product's, then
 * those only its other products have, at most pg_etsy_image_max(). Pure.
 *
 * @param array $items
 * @return array URLs
 */
function pg_etsy_listing_images($items)
{
    $images = array();

    foreach ((array) $items as $item) {
        foreach ((isset($item['images']) ? (array) $item['images'] : array()) as $url) {
            $url = (string) $url;

            if (($url !== '') && !in_array($url, $images, true)) {
                $images[] = $url;
            }
        }
    }

    return array_slice($images, 0, pg_etsy_image_max());
}

/**
 * One attribute of an item as Etsy's property value: property_id,
 * value_ids, values; null when it carries nothing or its id is not a
 * number. A value from the category's list is sent by its id with its
 * text, a value of the shop's own by its text alone. Pure.
 *
 * @param array $attribute id, value_id, custom_value, value
 * @return array|null
 */
function pg_etsy_property_value($attribute)
{
    $id = isset($attribute['id']) ? trim((string) $attribute['id']) : '';

    if (preg_match('/^[0-9]{1,20}$/', $id) !== 1) {
        return null;
    }

    $value_id = isset($attribute['value_id']) ? trim((string) $attribute['value_id']) : '';
    $text = ($value_id !== '')
        ? (isset($attribute['value']) ? trim((string) $attribute['value']) : '')
        : (isset($attribute['custom_value']) ? trim((string) $attribute['custom_value']) : '');

    if (($value_id === '') && ($text === '')) {
        return null;
    }

    if (($value_id !== '') && (preg_match('/^[0-9]{1,20}$/', $value_id) !== 1)) {
        return null;
    }

    return array(
        'property_id' => (int) $id,
        'value_ids'   => ($value_id !== '') ? array((int) $value_id) : array(),
        'values'      => array(($text !== '') ? $text : $value_id),
    );
}

/**
 * What tells an article's products apart on Etsy: the properties whose
 * values differ between them, at most two (Etsy's limit), and the ones
 * every product shares, which are set on the listing. One product has no
 * variation. Products that differ in nothing, or in more than two
 * properties, are refused. Pure but for lang().
 *
 * @param array $items
 * @return array ok, error, variations (property ids), shared (property_id => value)
 */
function pg_etsy_variations($items)
{
    $items = array_values((array) $items);
    $values = array();
    $order = array();

    foreach ($items as $index => $item) {
        foreach ((isset($item['attributes']) ? (array) $item['attributes'] : array()) as $attribute) {
            $value = pg_etsy_property_value($attribute);

            if ($value === null) {
                continue;
            }

            $id = $value['property_id'];

            if (!isset($values[$id])) {
                $values[$id] = array();
                $order[] = $id;
            }

            $values[$id][$index] = $value;
        }
    }

    $variations = array();
    $shared = array();

    foreach ($order as $id) {
        $keys = array();

        foreach ($items as $index => $item) {
            $keys[] = isset($values[$id][$index])
                ? ($values[$id][$index]['value_ids'] ? 'id:' . $values[$id][$index]['value_ids'][0] : 'text:' . mb_strtolower($values[$id][$index]['values'][0]))
                : '';
        }

        if (count(array_unique($keys)) > 1) {
            $variations[] = $id;
        } else {
            $first = reset($values[$id]);
            $shared[$id] = $first;
        }
    }

    if (count($items) > 1) {
        if (!$variations) {
            return array('ok' => false, 'error' => lang('The products of this article do not differ in any property Etsy knows, so they cannot be one listing\'s options.'), 'variations' => array(), 'shared' => array());
        }

        if (count($variations) > 2) {
            return array('ok' => false, 'error' => lang('The products of this article differ in more than two properties. An Etsy listing\'s options vary by two at most.'), 'variations' => array(), 'shared' => array());
        }

        foreach ($items as $index => $item) {
            foreach ($variations as $id) {
                if (!isset($values[$id][$index])) {
                    return array('ok' => false, 'error' => lang(array('string' => '{var:1} has no value for an option the other products of the article have.', 'vars' => array((string) $item['code']))), 'variations' => array(), 'shared' => array());
                }
            }
        }
    }

    return array('ok' => true, 'error' => '', 'variations' => $variations, 'shared' => $shared);
}

/**
 * The body of updateListingInventory: one product a SKU with its variation
 * values, one offering each with its price, quantity and processing
 * profile. Price, quantity and SKU vary on every variation property; an
 * offering with no stock is sent disabled. Pure.
 *
 * @param array  $items      mp_listing_items() items
 * @param array  $variations property ids (pg_etsy_variations())
 * @param array  $prices     SKU => "12.34"
 * @param string $readiness  readiness_state_id
 * @return array
 */
function pg_etsy_inventory_body($items, $variations, $prices, $readiness)
{
    $products = array();

    foreach (array_values((array) $items) as $item) {
        $sku = (string) $item['code'];
        $own = array();

        foreach ((isset($item['attributes']) ? (array) $item['attributes'] : array()) as $attribute) {
            $value = pg_etsy_property_value($attribute);

            if (($value !== null) && in_array($value['property_id'], $variations, true)) {
                $own[$value['property_id']] = $value;
            }
        }

        $property_values = array();

        foreach ($variations as $id) {
            if (isset($own[$id])) {
                $property_values[] = $own[$id];
            }
        }

        $quantity = max(0, min(pg_etsy_quantity_max(), isset($item['quantity']) ? (int) $item['quantity'] : 0));
        $offering = array(
            'price'      => (float) $prices[$sku],
            'quantity'   => $quantity,
            'is_enabled' => $quantity > 0,
        );

        if ((string) $readiness !== '') {
            $offering['readiness_state_id'] = (int) $readiness;
        }

        $products[] = array(
            'sku'             => $sku,
            'property_values' => $property_values,
            'offerings'       => array($offering),
        );
    }

    $on = array_values(array_map('intval', (array) $variations));

    return array(
        'products'             => $products,
        'price_on_property'    => $on,
        'quantity_on_property' => $on,
        'sku_on_property'      => $on,
    );
}

/**
 * The fields of createDraftListing (and of updateListing, which takes the
 * same but quantity and price): a physical good of the account's shipping
 * and processing profiles, who made it and when, in the paired category.
 * The draft's price and quantity are its first product's; the inventory
 * sent next sets every product's own. Pure.
 *
 * @param array  $items
 * @param array  $choices shipping_profile, readiness_state, who_made, when_made
 * @param string $price   "12.34"
 * @return array
 */
function pg_etsy_draft_fields($items, $choices, $price)
{
    $items = array_values((array) $items);
    $first = $items[0];
    $quantity = 0;

    foreach ($items as $item) {
        $quantity += max(0, isset($item['quantity']) ? (int) $item['quantity'] : 0);
    }

    $description = pg_etsy_plain_text(isset($first['description']) ? $first['description'] : '');
    $title = pg_etsy_listing_title($items);

    return array(
        'quantity'            => max(1, min(pg_etsy_quantity_max(), $quantity)),
        'title'               => $title,
        'description'         => ($description !== '') ? $description : $title,
        'price'               => (float) $price,
        'who_made'            => (string) $choices['who_made'],
        'when_made'           => (string) $choices['when_made'],
        'taxonomy_id'         => (int) $first['category_id'],
        'shipping_profile_id' => (int) $choices['shipping_profile'],
        'readiness_state_id'  => (int) $choices['readiness_state'],
        'type'                => 'physical',
    );
}

/**
 * A multipart/form-data body of plain fields and one file, as
 * uploadListingImage takes a picture. Pure.
 *
 * @param array  $fields    name => value
 * @param string $file_name the file's name
 * @param string $bytes     its content
 * @param string $mime      its type
 * @param string $boundary  a token not found in the content
 * @return array body, content_type
 */
function pg_etsy_multipart($fields, $file_name, $bytes, $mime, $boundary)
{
    $body = '';

    foreach ((array) $fields as $name => $value) {
        $body .= '--' . $boundary . "\r\n"
            . 'Content-Disposition: form-data; name="' . str_replace(array('"', "\r", "\n"), '', (string) $name) . '"' . "\r\n\r\n"
            . (string) $value . "\r\n";
    }

    $body .= '--' . $boundary . "\r\n"
        . 'Content-Disposition: form-data; name="image"; filename="' . str_replace(array('"', "\r", "\n"), '', (string) $file_name) . '"' . "\r\n"
        . 'Content-Type: ' . (string) $mime . "\r\n\r\n"
        . (string) $bytes . "\r\n"
        . '--' . $boundary . "--\r\n";

    return array('body' => $body, 'content_type' => 'multipart/form-data; boundary=' . $boundary);
}

/**
 * The type of a picture from its first bytes: JPEG, PNG, GIF or WebP, or ''
 * for anything Etsy does not take. Pure.
 *
 * @param string $bytes
 * @return string
 */
function pg_etsy_image_type($bytes)
{
    $bytes = (string) $bytes;

    if (strncmp($bytes, "\xFF\xD8\xFF", 3) === 0) {
        return 'image/jpeg';
    }

    if (strncmp($bytes, "\x89PNG\r\n\x1A\n", 8) === 0) {
        return 'image/png';
    }

    if ((strncmp($bytes, 'GIF87a', 6) === 0) || (strncmp($bytes, 'GIF89a', 6) === 0)) {
        return 'image/gif';
    }

    if ((substr($bytes, 0, 4) === 'RIFF') && (substr($bytes, 8, 4) === 'WEBP')) {
        return 'image/webp';
    }

    return '';
}

/**
 * The listing id of a createDraftListing or getListing answer, or ''. Pure.
 *
 * @param mixed $data
 * @return string
 */
function pg_etsy_listing_id($data)
{
    $id = (is_array($data) && isset($data['listing_id']) && is_scalar($data['listing_id'])) ? trim((string) $data['listing_id']) : '';

    return preg_match('/^[1-9][0-9]{0,19}$/D', $id) ? $id : '';
}

/**
 * Where a listing stands, read back from getListing after it was set
 * active: ok when Etsy shows it active, else what Etsy keeps it as. Pure but
 * for lang().
 *
 * @param mixed $data
 * @return array ok, state, error
 */
function pg_etsy_listing_verdict($data)
{
    $state = (is_array($data) && isset($data['state']) && is_scalar($data['state'])) ? strtolower(trim((string) $data['state'])) : '';

    if ($state === 'active') {
        return array('ok' => true, 'state' => $state, 'error' => '');
    }

    $words = array(
        'draft'    => lang('a draft'),
        'inactive' => lang('inactive'),
        'sold_out' => lang('sold out'),
        'expired'  => lang('expired'),
        'removed'  => lang('removed'),
        'edit'     => lang('being edited'),
    );

    return array(
        'ok'    => false,
        'state' => $state,
        'error' => lang(array('string' => 'Etsy keeps the listing as {var:1}. Open it in Shop Manager to see what it still needs.', 'vars' => array(isset($words[$state]) ? $words[$state] : (($state !== '') ? $state : '?')))),
    );
}

/**
 * The file name of one of this site's pictures from its address
 * (mp_listing_images()), or '' when the address is not under $base or names
 * anything but a plain file name. Pure.
 *
 * @param string $url
 * @param string $base the site's address with its path, ending in "/"
 * @return string
 */
function pg_etsy_image_file_name($url, $base)
{
    $url = (string) $url;
    $base = (string) $base;

    if (($base === '') || (strncmp($url, $base, strlen($base)) !== 0)) {
        return '';
    }

    $name = rawurldecode(substr($url, strlen($base)));

    if (($name === '') || ($name !== basename($name)) || ($name[0] === '.') || (strpbrk($name, "\\\0") !== false)) {
        return '';
    }

    return $name;
}

/**
 * The most listings one getListingsByListingIds call reads with their
 * inventory. Etsy takes 100; 25 keeps the answer, every listing with all its
 * products, well inside what is read of a body.
 *
 * @return int
 */
function pg_etsy_inventory_read_max()
{
    return 25;
}

/**
 * The inventories of a getListingsByListingIds answer read with
 * includes=Inventory: listing id => the inventory as getListingInventory
 * answers it. A listing the answer carries without its inventory is left
 * out, so it is read on its own. Pure.
 *
 * @param mixed $data
 * @return array
 */
function pg_etsy_batch_inventories($data)
{
    $inventories = array();
    $results = (is_array($data) && isset($data['results']) && is_array($data['results'])) ? $data['results'] : array();

    foreach ($results as $listing) {
        if (!is_array($listing) || !isset($listing['listing_id']) || !is_scalar($listing['listing_id'])
            || !isset($listing['inventory']) || !is_array($listing['inventory'])) {
            continue;
        }

        $inventories[(string) $listing['listing_id']] = $listing['inventory'];
    }

    return $inventories;
}

/**
 * The currency of a listing's inventory: the first offering's price
 * currency, or ''. Pure.
 *
 * @param array $inventory getListingInventory's answer
 * @return string
 */
function pg_etsy_inventory_currency($inventory)
{
    foreach ((isset($inventory['products']) && is_array($inventory['products'])) ? $inventory['products'] : array() as $product) {
        foreach ((is_array($product) && isset($product['offerings']) && is_array($product['offerings'])) ? $product['offerings'] : array() as $offering) {
            if (isset($offering['price']['currency_code']) && is_string($offering['price']['currency_code'])) {
                $currency = strtoupper(trim($offering['price']['currency_code']));

                if (preg_match('/^[A-Z]{3}$/', $currency) === 1) {
                    return $currency;
                }
            }
        }
    }

    return '';
}

/**
 * The body of updateListingInventory that changes stock and price of some
 * of a listing's products and keeps the others as Etsy has them: the call
 * replaces the whole products array, so every product of the read
 * inventory goes back, with its property values and offerings in the shape
 * the call takes (a price as a decimal number, no ids, nothing deleted).
 * Pure.
 *
 * $changes is SKU => quantity (int, or null to keep Etsy's) and price
 * ("12.34", or '' to keep Etsy's). A changed quantity is capped at
 * pg_etsy_quantity_max() and an offering is enabled only with stock, as a
 * new listing's are. Answers body (null when nothing differs from Etsy's)
 * and found, the SKUs of $changes the listing has.
 *
 * @param array $inventory getListingInventory's answer
 * @param array $changes
 * @return array body, found
 */
function pg_etsy_inventory_update($inventory, $changes)
{
    $products = array();
    $found = array();
    $changed = false;

    foreach ((isset($inventory['products']) && is_array($inventory['products'])) ? $inventory['products'] : array() as $product) {
        if (!is_array($product) || !empty($product['is_deleted'])) {
            continue;
        }

        $sku = isset($product['sku']) && is_scalar($product['sku']) ? (string) $product['sku'] : '';
        $change = (($sku !== '') && isset($changes[$sku])) ? $changes[$sku] : null;

        if ($change !== null) {
            $found[] = $sku;
        }

        $property_values = array();

        foreach ((isset($product['property_values']) && is_array($product['property_values'])) ? $product['property_values'] : array() as $value) {
            $kept = array(
                'property_id' => isset($value['property_id']) ? (int) $value['property_id'] : 0,
                'value_ids'   => isset($value['value_ids']) ? array_values(array_map('intval', (array) $value['value_ids'])) : array(),
                'values'      => isset($value['values']) ? array_values(array_map('strval', (array) $value['values'])) : array(),
            );

            if (isset($value['property_name']) && is_string($value['property_name']) && ($value['property_name'] !== '')) {
                $kept['property_name'] = $value['property_name'];
            }

            if (isset($value['scale_id']) && is_numeric($value['scale_id'])) {
                $kept['scale_id'] = (int) $value['scale_id'];
            }

            $property_values[] = $kept;
        }

        $offerings = array();

        foreach ((isset($product['offerings']) && is_array($product['offerings'])) ? $product['offerings'] : array() as $offering) {
            if (!is_array($offering) || !empty($offering['is_deleted'])) {
                continue;
            }

            $divisor = (isset($offering['price']['divisor']) && ((int) $offering['price']['divisor'] > 0)) ? (int) $offering['price']['divisor'] : 100;
            $amount = isset($offering['price']['amount']) ? (int) $offering['price']['amount'] : 0;
            $quantity = isset($offering['quantity']) ? (int) $offering['quantity'] : 0;
            $enabled = !empty($offering['is_enabled']);
            $price = round($amount / $divisor, 2);

            if ($change !== null) {
                if ($change['quantity'] !== null) {
                    $new = max(0, min(pg_etsy_quantity_max(), (int) $change['quantity']));
                    $changed = $changed || ($new !== $quantity) || ($enabled !== ($new > 0));
                    $quantity = $new;
                    $enabled = $new > 0;
                }

                if ((string) $change['price'] !== '') {
                    $new = (float) $change['price'];
                    $changed = $changed || (abs($new - $price) >= 0.005);
                    $price = $new;
                }
            }

            $kept = array('price' => $price, 'quantity' => $quantity, 'is_enabled' => $enabled);

            if (isset($offering['readiness_state_id']) && is_numeric($offering['readiness_state_id'])) {
                $kept['readiness_state_id'] = (int) $offering['readiness_state_id'];
            }

            $offerings[] = $kept;
        }

        $products[] = array('sku' => $sku, 'property_values' => $property_values, 'offerings' => $offerings);
    }

    if (!$changed) {
        return array('body' => null, 'found' => $found);
    }

    $body = array('products' => $products);

    foreach (array('price_on_property', 'quantity_on_property', 'sku_on_property', 'readiness_state_on_property') as $on) {
        if (isset($inventory[$on]) && is_array($inventory[$on])) {
            $body[$on] = array_values(array_map('intval', $inventory[$on]));
        }
    }

    return array('body' => $body, 'found' => $found);
}

/**
 * What the limits an answer carries ask of the next call (Etsy names them
 * in every answer: x-remaining-this-second, x-remaining-today, and
 * retry-after on a 429). wait: seconds to sleep before the next call, once
 * this second's calls are used up; stop: seconds the rest waits in the
 * queue, for a 429 (its retry-after, else a minute) or a day's calls used
 * up (the queue's longest wait, an hour). Pure.
 *
 * @param array $result ok, status, headers (lower-case names)
 * @return array wait, stop
 */
function pg_etsy_pace($result)
{
    $headers = (isset($result['headers']) && is_array($result['headers'])) ? $result['headers'] : array();
    $header = function ($name) use ($headers) {
        return (isset($headers[$name]) && is_numeric(trim((string) $headers[$name]))) ? (int) trim((string) $headers[$name]) : null;
    };

    $today = $header('x-remaining-today');

    if (($today !== null) && ($today <= 0)) {
        return array('wait' => 0, 'stop' => 3600);
    }

    if (isset($result['status']) && ((int) $result['status'] === 429)) {
        $after = $header('retry-after');

        return array('wait' => 0, 'stop' => ($after === null) ? 60 : max(1, min(3600, $after)));
    }

    $second = $header('x-remaining-this-second');

    return array('wait' => (($second !== null) && ($second <= 0)) ? 1 : 0, 'stop' => 0);
}
