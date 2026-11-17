<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Coordinates of a collection's places: the record's own two number fields,
 * latitude and longitude, filled from the record's address by "Find from the
 * address" on the record's form and by the bulk action on the record list.
 * The address is asked once, on the server, when the button is pressed;
 * nothing is looked up when a page is viewed and no table keeps coordinates.
 *
 * The fields are found by name: a number field named latitude (lat, enlem)
 * and one named longitude (lng, lon, boylam), and the text fields that
 * make up the address (address, street, city, postcode, country and the
 * like), joined in the form's order.
 *
 * Coordinates always come from Photon (OpenStreetMap data), whatever
 * provider gives the address suggestions: Photon's results may be kept and
 * drawn on any map, Google's and Mapbox's may not, and nothing is asked of
 * those two here. The site's own Photon server is used while its Photon
 * account is on, the public instance otherwise.
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
 * Records one bulk call looks up at most. The public Photon instance asks
 * for light use, and each lookup may take seconds.
 */
define('PG_PLACE_MAP_BULK', 10);

/**
 * Microseconds a bulk call waits between two requests to the public Photon
 * instance: it answers back-to-back calls with HTTP 503, and its usage
 * policy asks for moderate use. Nine pauses keep a press near ten seconds.
 */
define('PG_PLACE_MAP_PUBLIC_PAUSE', 1000000);

/**
 * The longest address text sent to the provider.
 */
define('PG_PLACE_MAP_ADDRESS_MAX', 300);

/**
 * The field names each part is found by, after pg_place_map_name().
 *
 * @return array lat, lng, address => list of names
 */
function pg_place_map_names()
{
    return array(
        'lat'     => array('latitude', 'lat', 'enlem'),
        'lng'     => array('longitude', 'lng', 'lon', 'long', 'boylam'),
        'address' => array(
            'address', 'address_1', 'address_2', 'address_line_1', 'address_line_2', 'street', 'street_address',
            'district', 'city', 'town', 'state', 'province', 'region', 'zip', 'zip_code', 'postcode', 'postal_code', 'country',
            'adres', 'sokak', 'cadde', 'mahalle', 'ilce', 'il', 'sehir', 'posta_kodu', 'ulke',
        ),
    );
}

/**
 * A field name as it is compared: lower case ASCII, words joined by _.
 *
 * @param string $name
 * @return string
 */
function pg_place_map_name($name)
{
    $name = strtr((string) $name, array('ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g', 'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c'));

    return trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name)), '_');
}

/**
 * Which of a form's fields hold the address and the two coordinates. Pure.
 *
 * @param array $fields rows in form order: id, name, type, kind
 *                      (form_fields.collection_kind)
 * @return array|null lat, lng (field ids), address (field ids in order);
 *                    null unless both coordinates and an address are there
 */
function pg_place_map_field_roles($fields)
{
    $names = pg_place_map_names();
    $roles = array('lat' => 0, 'lng' => 0, 'address' => array());

    foreach ((array) $fields as $field) {
        $name = pg_place_map_name(isset($field['name']) ? $field['name'] : '');
        $type = isset($field['type']) ? (string) $field['type'] : '';
        $kind = isset($field['kind']) ? (string) $field['kind'] : '';
        $id = isset($field['id']) ? (int) $field['id'] : 0;

        if (($id <= 0) || ($name === '')) {
            continue;
        }

        if ($kind === 'number') {
            foreach (array('lat', 'lng') as $part) {
                if (($roles[$part] === 0) && in_array($name, $names[$part], true)) {
                    $roles[$part] = $id;
                }
            }

            continue;
        }

        if (($kind === '') && in_array($type, array('text box', 'text area'), true) && in_array($name, $names['address'], true)) {
            $roles['address'][] = $id;
        }
    }

    return (($roles['lat'] > 0) && ($roles['lng'] > 0) && $roles['address']) ? $roles : null;
}

/**
 * The place fields of a custom form, null when it has none.
 *
 * @param int $page_id
 * @return array|null pg_place_map_field_roles()
 */
function pg_place_map_fields($page_id)
{
    $page_id = (int) $page_id;

    if (($page_id <= 0) || !function_exists('pg_coll_ready') || !pg_coll_ready()) {
        return null;
    }

    $fields = array();

    foreach ((array) db_items("SELECT id, name, type, collection_kind FROM form_fields WHERE page_id = '" . $page_id . "' ORDER BY sort_order") as $row) {
        $fields[] = array('id' => $row['id'], 'name' => $row['name'], 'type' => $row['type'], 'kind' => pg_coll_field_kind($row['collection_kind'], $row['type']));
    }

    return pg_place_map_field_roles($fields);
}

/**
 * Where coordinates are asked: the Photon account of the address
 * suggestions while it is on (its host), else the public instance, also
 * while Google Places or Mapbox gives the suggestions. Pure.
 *
 * @param array $photon pg_addr_app('photon'): host, enabled
 * @return array app (pg_addr_app() shape for pg_geocode_photon()), host
 *               (the address the lookup goes to)
 */
function pg_place_map_source($photon)
{
    $app = array('key' => '', 'key_stored' => false, 'host' => '', 'enabled' => false);

    if (is_array($photon) && !empty($photon['enabled'])) {
        $app = $photon;
    }

    return array('app' => $app, 'host' => pg_addr_photon_host(isset($app['host']) ? (string) $app['host'] : ''));
}

/**
 * The site's Photon account, as pg_place_map_source() takes it.
 *
 * @return array
 */
function pg_place_map_photon_app()
{
    return pg_addr_app('photon');
}

/**
 * Whether a user may look up and write coordinates of a form's records: the
 * rule of the record's edit screen.
 *
 * @param array $user        validate_user(): role, manage_forms
 * @param bool  $edit_access check_edit_access() of the form's folder
 * @return bool
 */
function pg_place_map_may($user, $edit_access)
{
    if (!is_array($user) || !isset($user['role'])) {
        return false;
    }

    return ((int) $user['role'] < 3) || (!empty($user['manage_forms']) && $edit_access);
}

/**
 * The address text of the given values, in the fields' order. Pure.
 *
 * @param array $values  field id => value
 * @param array $address field ids
 * @return string
 */
function pg_place_map_address_text($values, $address)
{
    $parts = array();

    foreach ((array) $address as $id) {
        $value = (isset($values[$id]) && is_scalar($values[$id])) ? trim(preg_replace('/\s+/u', ' ', (string) $values[$id])) : '';

        if (($value !== '') && !in_array($value, $parts, true)) {
            $parts[] = $value;
        }
    }

    return mb_substr(implode(', ', $parts), 0, PG_PLACE_MAP_ADDRESS_MAX, 'UTF-8');
}

/**
 * A coordinate as the number field stores it: six decimals (about 10 cm),
 * no trailing zeros. Pure.
 *
 * @param float $value
 * @return string
 */
function pg_place_map_number($value)
{
    $text = rtrim(rtrim(sprintf('%.6F', round((float) $value, 6)), '0'), '.');

    return ($text === '-0') ? '0' : $text;
}

/**
 * What a provider's answer means. Pure.
 *
 * @param array $http pg_addr_http()
 * @return array state (found, not_found, wait, error), lat, lng (strings),
 *               error
 */
function pg_place_map_answer($http)
{
    if (empty($http['ok'])) {
        $status = isset($http['status']) ? (int) $http['status'] : 0;

        // 429 asks to wait; the public instance throttles with a bare 503.
        return array('state' => in_array($status, array(429, 503), true) ? 'wait' : 'error', 'lat' => '', 'lng' => '', 'error' => isset($http['error']) ? (string) $http['error'] : '');
    }

    $point = pg_geocode_photon_point(isset($http['data']) ? $http['data'] : null);

    if ($point === null) {
        return array('state' => 'not_found', 'lat' => '', 'lng' => '', 'error' => '');
    }

    return array('state' => 'found', 'lat' => pg_place_map_number($point[0]), 'lng' => pg_place_map_number($point[1]), 'error' => '');
}

/**
 * Loads the Photon calls: the autocomplete driver holds the host rule
 * (pg_addr_photon_host()), the geocode file the coordinates call.
 */
function pg_place_map_load_driver()
{
    pg_addr_driver('photon');
    require_once(PG_FUNCTIONS_DIR . '/includes/geo/geocode/photon.php');
}

/**
 * Looks up one address. Called only from place_coordinates.php, when a
 * button was pressed.
 *
 * @param string $text
 * @return array pg_place_map_answer(), state also empty
 */
function pg_place_map_lookup($text)
{
    $text = trim((string) $text);

    if ($text === '') {
        return array('state' => 'empty', 'lat' => '', 'lng' => '', 'error' => '');
    }

    pg_place_map_load_driver();

    return pg_place_map_answer(pg_geocode_photon($text, pg_place_map_source(pg_place_map_photon_app())['app']));
}

/**
 * The line a lookup's state is told with.
 *
 * @param array $result pg_place_map_lookup()
 * @return string
 */
function pg_place_map_message($result)
{
    switch ($result['state']) {
        case 'found':
            return lang('Coordinates found. Save the record to keep them.');
        case 'not_found':
            return lang('The address was not found. Check it, or type the coordinates.');
        case 'empty':
            return lang('Enter the address first.');
        case 'wait':
            return lang('The address service asks to wait. Try again in a minute.');
    }

    return lang(array('string' => 'The address service did not answer: {var:1}', 'vars' => array($result['error'])));
}

/**
 * Splits the records still to do into this call's and the rest. Pure.
 *
 * @param array $ids   record ids, in order
 * @param int   $bound
 * @return array now (ids), left (count after this call)
 */
function pg_place_map_bulk_plan($ids, $bound)
{
    $ids = array_values((array) $ids);
    $now = array_slice($ids, 0, max(0, (int) $bound));

    return array('now' => $now, 'left' => count($ids) - count($now));
}

/**
 * The records of a form that have an address but lack a coordinate, in id
 * order, without those the session already found nothing for.
 *
 * @param int   $page_id
 * @param array $roles pg_place_map_field_roles()
 * @param array $skip  record ids
 * @return array ids
 */
function pg_place_map_bulk_ids($page_id, $roles, $skip)
{
    $has = function ($field_id) {
        return "EXISTS (SELECT 1 FROM form_data AS fd WHERE fd.form_id = forms.id AND fd.form_field_id = '" . (int) $field_id . "' AND TRIM(fd.data) != '')";
    };

    $address = array();

    foreach ($roles['address'] as $field_id) {
        $address[] = $has($field_id);
    }

    $ids = db_values(
        "SELECT forms.id FROM forms
         WHERE forms.page_id = '" . (int) $page_id . "'
         AND (" . implode(' OR ', $address) . ")
         AND (NOT " . $has($roles['lat']) . " OR NOT " . $has($roles['lng']) . ")
         ORDER BY forms.id");

    $skip = array_map('intval', (array) $skip);

    return array_values(array_filter(array_map('intval', (array) $ids), function ($id) use ($skip) {
        return !in_array($id, $skip, true);
    }));
}

/**
 * A record's values of the given fields.
 *
 * @param int   $form_id
 * @param array $field_ids
 * @return array field id => value (the first row of each)
 */
function pg_place_map_record_values($form_id, $field_ids)
{
    $ids = implode(',', array_map('intval', $field_ids));
    $values = array();

    foreach ((array) db_items("SELECT form_field_id, data FROM form_data WHERE form_id = '" . (int) $form_id . "' AND form_field_id IN (" . $ids . ") ORDER BY id") as $row) {
        if (!isset($values[(int) $row['form_field_id']])) {
            $values[(int) $row['form_field_id']] = (string) $row['data'];
        }
    }

    return $values;
}

/**
 * Writes one value of a record: its row replaced, or added when the record
 * has none.
 *
 * @param int    $form_id
 * @param int    $field_id
 * @param string $value
 * @return bool
 */
function pg_place_map_write_value($form_id, $field_id, $value)
{
    $form_id = (int) $form_id;
    $field_id = (int) $field_id;
    $existing = (int) db_value("SELECT COUNT(*) FROM form_data WHERE form_id = '" . $form_id . "' AND form_field_id = '" . $field_id . "'");

    if ($existing > 0) {
        return (bool) db_q("UPDATE form_data SET data = ? WHERE form_id = ? AND form_field_id = ?", array((string) $value, (string) $form_id, (string) $field_id));
    }

    $name = (string) db_value("SELECT name FROM form_fields WHERE id = '" . $field_id . "'");

    return (bool) db_q("INSERT INTO form_data (form_id, form_field_id, data, name, type) VALUES (?, ?, ?, ?, 'standard')", array((string) $form_id, (string) $field_id, (string) $value, $name));
}

/**
 * Microseconds to wait before a bulk call's request: none before the first,
 * PG_PLACE_MAP_PUBLIC_PAUSE before the others while the public instance
 * answers. Pure.
 *
 * @param string $host     pg_place_map_source()['host']
 * @param int    $position the request's place in this call, from 0
 * @return int
 */
function pg_place_map_bulk_pause($host, $position)
{
    if ((int) $position < 1 || rtrim((string) $host, '/') !== PG_ADDR_PHOTON_HOST) {
        return 0;
    }

    return PG_PLACE_MAP_PUBLIC_PAUSE;
}

/**
 * Looks up the next records of a form that lack coordinates and writes what
 * is found. Stops at the first answer that is neither found nor not found
 * (the service asks to wait, or fails), so a busy service is not asked again
 * in the same call. Requests to the public instance are spaced by
 * pg_place_map_bulk_pause().
 *
 * @param int   $page_id
 * @param array $roles   pg_place_map_field_roles()
 * @param array $skip    record ids found nothing for earlier in this session
 * @param int   $user_id
 * @return array found (count), not_found (list of reference, address,
 *               id), left (count), state (done, wait, error), error
 */
function pg_place_map_bulk($page_id, $roles, $skip, $user_id)
{
    $summary = array('found' => 0, 'not_found' => array(), 'left' => 0, 'state' => 'done', 'error' => '');
    $plan = pg_place_map_bulk_plan(pg_place_map_bulk_ids($page_id, $roles, $skip), PG_PLACE_MAP_BULK);
    $summary['left'] = $plan['left'];
    pg_place_map_load_driver();
    $host = pg_place_map_source(pg_place_map_photon_app())['host'];
    $asked = 0;

    foreach ($plan['now'] as $position => $form_id) {
        $text = pg_place_map_address_text(pg_place_map_record_values($form_id, $roles['address']), $roles['address']);

        if (trim($text) !== '') {
            $pause = pg_place_map_bulk_pause($host, $asked);

            if ($pause > 0) {
                usleep($pause);
            }

            $asked++;
        }

        $result = pg_place_map_lookup($text);

        if ($result['state'] === 'found') {
            pg_place_map_write_value($form_id, $roles['lat'], $result['lat']);
            pg_place_map_write_value($form_id, $roles['lng'], $result['lng']);
            db_q("UPDATE forms SET last_modified_user_id = ?, last_modified_timestamp = UNIX_TIMESTAMP() WHERE id = ?", array((string) (int) $user_id, (string) $form_id));
            $summary['found']++;
            continue;
        }

        if ($result['state'] === 'not_found') {
            $summary['not_found'][] = array('id' => $form_id, 'reference' => (string) db_value("SELECT reference_code FROM forms WHERE id = '" . (int) $form_id . "'"), 'address' => $text);
            continue;
        }

        // Not asked in this call: they count as left.
        $summary['left'] += count($plan['now']) - $position;
        $summary['state'] = $result['state'];
        $summary['error'] = pg_place_map_message($result);
        break;
    }

    return $summary;
}

/**
 * The bulk call's report, one line. Pure apart from lang().
 *
 * @param array $summary pg_place_map_bulk()
 * @return string
 */
function pg_place_map_bulk_message($summary)
{
    $line = lang(array('string' => 'Coordinates found for {var:1} records. {var:2} not found. {var:3} left.', 'vars' => array((string) $summary['found'], (string) count($summary['not_found']), (string) $summary['left'])));

    if ($summary['error'] !== '') {
        $line .= ' ' . $summary['error'];
    }

    return $line;
}


/* ---------------------------------------------------------------------------
   The buttons
   --------------------------------------------------------------------------- */

/**
 * The help line under the form's button: where the address is sent. Pure
 * apart from lang().
 *
 * @param string $host pg_place_map_source()['host']
 * @return string text
 */
function pg_place_map_help($host)
{
    return lang(array('string' => 'The address is sent to Photon (OpenStreetMap data) at {var:1} to find its coordinates.', 'vars' => array(preg_replace('#^https://#', '', (string) $host))));
}

/**
 * "Find from the address" on the record's add and edit screens, drawn right
 * under the later of the two coordinate fields (its script moves it there:
 * the fields are drawn by one loop shared with other field kinds). It posts
 * the address as it stands in the form, fills the two fields and leaves the
 * save to the user. One answer line shows at a time: when the address is
 * edited while coordinates are there, the warning that they are still those
 * of the address before takes its place. '' for a form without place
 * fields.
 *
 * @param int $page_id
 * @return string HTML
 */
function pg_place_map_form_tools($page_id)
{
    $roles = pg_place_map_fields($page_id);

    if ($roles === null) {
        return '';
    }

    pg_place_map_load_driver();

    $config = array(
        'page' => (int) $page_id,
        'lat' => (string) $roles['lat'],
        'lng' => (string) $roles['lng'],
        'address' => array_map('strval', $roles['address']),
        'changed' => lang('The address changed: the coordinates are still those of the address before. Use Find from the address to update them.'),
        'busy' => lang('Looking up the address…'),
        'failed' => lang('The address service did not answer.'),
    );

    return '
                                        <div class="col-12 my-3" data-pg-place-form="' . h(json_encode($config, JSON_UNESCAPED_UNICODE)) . '">
                                            <button type="button" class="btn btn-sm btn-outline theme-primary" data-pg-place-find><i class="bi bi-geo-alt me-1" aria-hidden="true"></i>' . lang('Find from the address') . '</button>
                                            <div class="form-text mt-3" role="status" aria-live="polite" data-pg-place-status hidden></div>
                                            <div class="form-text mt-3">' . h(pg_place_map_help(pg_place_map_source(pg_place_map_photon_app())['host'])) . '</div>
                                        </div>
                                        <script>' . pg_place_map_form_script() . '</script>';
}

/**
 * The form's script: nothing is asked until the button is pressed.
 *
 * @return string JavaScript
 */
function pg_place_map_form_script()
{
    return '(function(){var box=document.currentScript.previousElementSibling,c=JSON.parse(box.getAttribute("data-pg-place-form")),f=box.closest("form");if(!f)return;'
        . 'var el=function(i){return document.getElementById(i);},st=box.querySelector("[data-pg-place-status]"),la=el(c.lat),lo=el(c.lng);'
        // Under the later coordinate field: after its column in the fields' row.
        . 'var n=(la&&lo&&(la.compareDocumentPosition(lo)&4))?lo:(la||lo);while(n&&n.parentElement&&n.parentElement!==box.parentElement){n=n.parentElement;}if(n&&n.parentElement===box.parentElement&&n!==box){n.after(box);}'
        . 'var show=function(t,w){st.hidden=!t;st.textContent=t||"";st.classList.toggle("fg-warning",!!w);};'
        . 'var addr=function(){return c.address.map(function(i){var e=el(i);return e?e.value.trim():"";}).join("|");},base=addr();'
        . 'var has=function(){return la&&lo&&(la.value.trim()!==""||lo.value.trim()!=="");};'
        . 'var check=function(){if(has()&&addr()!==base){show(c.changed,true);}else if(st.classList.contains("fg-warning")){show("",false);}};'
        . 'c.address.forEach(function(i){var e=el(i);if(e)e.addEventListener("input",check);});'
        . 'var put=function(e,v){if(!e)return;e.value=v;e.dispatchEvent(new Event("input",{bubbles:true}));e.dispatchEvent(new Event("change",{bubbles:true}));};'
        . 'box.querySelector("[data-pg-place-find]").addEventListener("click",function(){var b=this,d=new FormData();d.append("action","lookup");d.append("page_id",c.page);'
        . 'd.append("token",f.elements.token?f.elements.token.value:"");c.address.forEach(function(i){var e=el(i);d.append("values["+i+"]",e?e.value:"");});'
        . 'b.disabled=true;show(c.busy,false);fetch("place_coordinates.php",{method:"POST",body:d,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(a){'
        . 'if(a.state==="found"){put(la,a.lat);put(lo,a.lng);base=addr();}show(a.message||"",false);}).catch(function(){show(c.failed,false);}).then(function(){b.disabled=false;});});'
        . '})();';
}

/**
 * "Find missing coordinates" on the record list, while one form with place
 * fields is chosen. Each press looks up the next PG_PLACE_MAP_BULK records;
 * the endpoint leaves its report as the screen's notice and the page is
 * drawn again, so the report stands above the list, never in the toolbar.
 * '' otherwise.
 *
 * @param int|string $page_id the chosen form, '' for none or several
 * @return string HTML
 */
function pg_place_map_list_tools($page_id)
{
    if (((int) $page_id <= 0) || (pg_place_map_fields($page_id) === null)) {
        return '';
    }

    $config = array(
        'page' => (int) $page_id,
        'busy' => lang('Looking up addresses…'),
        'failed' => lang('The address service did not answer.'),
    );

    return '<button type="button" class="btn btn-sm btn-outline theme-primary m-1" data-pg-place-bulk="' . h(json_encode($config, JSON_UNESCAPED_UNICODE)) . '" title="' . h(lang(array('string' => 'Looks up the next {var:1} records that have an address but no coordinates.', 'vars' => array((string) PG_PLACE_MAP_BULK)))) . '"><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><span>' . lang('Find missing coordinates') . '</span></button>'
        . '<script>' . pg_place_map_list_script() . '</script>';
}

/**
 * The list button's script.
 *
 * @return string JavaScript
 */
function pg_place_map_list_script()
{
    return '(function(){var b=document.currentScript.previousElementSibling,c=JSON.parse(b.getAttribute("data-pg-place-bulk")),l=b.querySelector("span"),label=l.textContent;'
        . 'b.addEventListener("click",function(){var t=document.querySelector("input[name=token]"),d=new FormData();d.append("action","bulk");d.append("page_id",c.page);d.append("token",t?t.value:"");'
        . 'b.disabled=true;l.textContent=c.busy;fetch("place_coordinates.php",{method:"POST",body:d,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(){window.location.reload();})'
        . '.catch(function(){b.disabled=false;l.textContent=label;window.alert(c.failed);});});})();';
}

/**
 * The bulk call's report as the record list's notice: the counts, then the
 * addresses not found, one per line. HTML.
 *
 * @param array $summary pg_place_map_bulk()
 * @return string
 */
function pg_place_map_bulk_notice($summary)
{
    $notice = h(pg_place_map_bulk_message($summary));

    if ($summary['not_found']) {
        $notice .= '<br>' . h(lang('Not found:'));

        foreach ($summary['not_found'] as $missing) {
            $notice .= '<br>' . h($missing['reference'] . ': ' . $missing['address']);
        }
    }

    return $notice;
}
