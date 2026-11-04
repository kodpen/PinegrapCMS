<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Place lists of a country: the districts of each province, read from
 * includes/geo/places/<CC>.json (Turkey: TR-01…TR-81, 973 districts). A
 * Turkish address is asked as İl (state box) and İlçe (city box); with the
 * list the city box suggests "Kadıköy / İstanbul" and fills the İl, the
 * server writes a typed district in its listed spelling ("kadikoy" ->
 * "Kadıköy"), and the order screen notes a district the list does not hold.
 * A district not in the list is never refused.
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
 * The countries that have a place list: each includes/geo/places/<CC>.json
 * (a country pack, format in docs/memory/commerce/place-packs.md).
 *
 * @return array two-letter codes, sorted
 */
function pg_places_countries()
{
    static $codes = null;

    if ($codes === null) {
        $codes = array();

        foreach ((array) glob(PG_FUNCTIONS_DIR . '/includes/geo/places/*.json') as $file) {
            $code = basename((string) $file, '.json');

            if (preg_match('/^[A-Z]{2}$/', $code)) {
                $codes[] = $code;
            }
        }

        sort($codes);
    }

    return $codes;
}

/**
 * The pack country of an address's country, read the way the sales map reads
 * a countries row (code, else name), or '' when that country has no pack.
 *
 * @param  string $country countries.code or countries.name
 * @param  string $name    countries.name when $country is the code
 * @return string two-letter code or ''
 */
function pg_places_country($country, $name = '')
{
    $country = is_scalar($country) ? trim((string) $country) : '';
    $name = is_scalar($name) ? trim((string) $name) : '';

    if (($country === '') && ($name === '')) {
        return '';
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    $code = (string) pg_sales_map_canonical_country($country, ($name !== '') ? $name : $country);

    return in_array($code, pg_places_countries(), true) ? $code : '';
}

/**
 * A place list file's content as province code => name and districts, or an
 * empty array for content that is not a list. Pure.
 *
 * @param  string $json the file's content
 * @return array  'TR-34' => array('name' => 'İstanbul', 'districts' => array(…))
 */
function pg_places_parse($json)
{
    $data = json_decode((string) $json, true);

    if (!is_array($data) || !isset($data['places']) || !is_array($data['places'])) {
        return array();
    }

    $places = array();

    foreach ($data['places'] as $code => $place) {
        if (!is_array($place) || !isset($place['name'], $place['districts']) || !is_array($place['districts'])) {
            continue;
        }

        $districts = array();

        foreach ($place['districts'] as $district) {
            if (is_string($district) && (trim($district) !== '')) {
                $districts[] = trim($district);
            }
        }

        $places[(string) $code] = array('name' => trim((string) $place['name']), 'districts' => $districts);
    }

    return $places;
}

/**
 * A country's place list, read once per request, or an empty array when the
 * country has none.
 *
 * @param  string $country two-letter code
 * @return array  pg_places_parse()
 */
function pg_places_pack($country)
{
    static $packs = array();

    $country = strtoupper(trim((string) $country));

    if (!in_array($country, pg_places_countries(), true)) {
        return array();
    }

    if (!isset($packs[$country])) {
        $file = PG_FUNCTIONS_DIR . '/includes/geo/places/' . $country . '.json';
        $packs[$country] = is_file($file) ? pg_places_parse((string) file_get_contents($file)) : array();
    }

    return $packs[$country];
}

/**
 * A place name folded for comparison: accents, case, spaces and punctuation
 * ignored, the way the sales map folds a province ("Kadıköy", "KADIKOY" and
 * "kadikoy" are one key). Pure.
 *
 * @param  string $value
 * @return string
 */
function pg_place_key($value)
{
    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    return pg_sales_map_normalize(is_scalar($value) ? (string) $value : '');
}

/**
 * The province code of a Turkish state box: by its plate number when one is
 * given (states.plate_code), else by its name, older spellings included
 * ("Afyon", "İçel"). Pure.
 *
 * @param  string $state the state box (name or code)
 * @param  string $plate states.plate_code of the matching row, '' when unknown
 * @return string 'TR-34' or ''
 */
function pg_place_tr_province($state, $plate = '')
{
    $plate = is_scalar($plate) ? trim((string) $plate) : '';

    if (preg_match('/^[0-9]{1,2}$/', $plate) && ((int) $plate >= 1) && ((int) $plate <= 81)) {
        return 'TR-' . sprintf('%02d', (int) $plate);
    }

    require_once(PG_FUNCTIONS_DIR . '/includes/sales_map.php');

    return pg_sales_map_match_region('TR', is_scalar($state) ? (string) $state : '');
}

/**
 * The province code of a state box in a country's pack: Turkey through
 * pg_place_tr_province(); any other country by the province's name or the
 * part of its code after "<CC>-" ("CA" for "US-CA"). Pure.
 *
 * @param  string $country two-letter code of the pack
 * @param  array  $pack    pg_places_parse()
 * @param  string $state   the state box (name or code)
 * @param  string $plate   states.plate_code (Turkey only), '' when unknown
 * @return string a code of the pack or ''
 */
function pg_place_province($country, $pack, $state, $plate = '')
{
    $country = strtoupper((string) $country);

    if ($country === 'TR') {
        $code = pg_place_tr_province($state, $plate);

        return isset($pack[$code]) ? $code : '';
    }

    $key = pg_place_key($state);

    if ($key === '') {
        return '';
    }

    foreach ((array) $pack as $code => $place) {
        if ((pg_place_key($place['name']) === $key) || (pg_place_key(substr((string) $code, strlen($country) + 1)) === $key)) {
            return (string) $code;
        }
    }

    return '';
}

/**
 * A typed district looked up in a place list. Inside a known province only
 * its districts count; the province's own name counts as its central
 * district where the list has a "Merkez". With no province any province's
 * district counts. Pure.
 *
 * @param  array  $pack     pg_places_parse()
 * @param  string $province a province code of the list, '' when unknown
 * @param  string $district the city box as typed
 * @return array  'known' => bool, 'name' => the listed spelling ('' when not
 *                listed or the province's own name), 'province' => code
 */
function pg_place_district($pack, $province, $district)
{
    $result = array('known' => false, 'name' => '', 'province' => (string) $province);
    $key = pg_place_key($district);

    if (($key === '') || !is_array($pack) || !$pack) {
        return $result;
    }

    $province = (string) $province;
    $codes = (($province !== '') && isset($pack[$province])) ? array($province) : array_keys($pack);
    $found = array();

    foreach ($codes as $code) {
        foreach ($pack[$code]['districts'] as $name) {
            if (pg_place_key($name) === $key) {
                $found[$code] = $name;
                break;
            }
        }
    }

    if ($found) {
        // A name of several provinces (Gölbaşı, Kemer) with no province
        // given: listed, in the spelling they share, in no one province.
        $names = array_unique(array_values($found));

        return array(
            'known'    => true,
            'name'     => (count($names) === 1) ? reset($names) : '',
            'province' => (count($found) === 1) ? (string) key($found) : $province);
    }

    // "Adıyaman" typed as the district of Adıyaman: the central district,
    // kept as typed.
    if ((count($codes) === 1)
        && in_array('Merkez', $pack[$province]['districts'], true)
        && ((pg_place_key($pack[$province]['name']) === $key) || (pg_place_tr_province($district) === $province))) {
        $result['known'] = true;
    }

    return $result;
}

/**
 * The plate number of the shop's states row a state box names, read once per
 * request, or '' (no such row, or step 11.0.134 not run yet).
 *
 * @param  string $state the state box
 * @return string
 */
function pg_place_state_plate($state)
{
    static $plates = null;

    $state = is_scalar($state) ? trim((string) $state) : '';

    if ($state === '') {
        return '';
    }

    if ($plates === null) {
        $plates = array();

        if (function_exists('pg_schema_has') && pg_schema_has('states', 'plate_code')) {
            foreach ((array) db_items("SELECT code, name, plate_code FROM states WHERE plate_code <> ''") as $row) {
                $plates[pg_place_key($row['code'])] = (string) $row['plate_code'];
                $plates[pg_place_key($row['name'])] = (string) $row['plate_code'];
            }
        }
    }

    $key = pg_place_key($state);

    return isset($plates[$key]) ? $plates[$key] : '';
}

/**
 * An address's city box checked against its country's pack: null when the
 * city box is empty or the country has no pack.
 *
 * @param  string $country countries.code or countries.name
 * @param  string $state   the state box (İl for Turkey)
 * @param  string $city    the city box (İlçe for Turkey)
 * @return array|null pg_place_district() plus 'country' => the pack's code
 */
function pg_place_check($country, $state, $city)
{
    $code = pg_places_country($country);

    if ((trim((string) $city) === '') || ($code === '')) {
        return null;
    }

    $pack = pg_places_pack($code);

    if (!$pack) {
        return null;
    }

    $plate = ($code === 'TR') ? pg_place_state_plate($state) : '';
    $check = pg_place_district($pack, pg_place_province($code, $pack, $state, $plate), $city);
    $check['country'] = $code;

    return $check;
}

/**
 * The city box of an address as it is saved: a Turkish district in its
 * listed spelling when the typed one matches it, else as typed.
 *
 * @param  string $country
 * @param  string $state
 * @param  string $city
 * @return string
 */
function pg_place_city_fix($country, $state, $city)
{
    $check = pg_place_check($country, $state, $city);

    return ($check && ($check['name'] !== '')) ? $check['name'] : (string) $city;
}

/**
 * Writes the listed spelling of a Turkish district into a posted form's
 * city box before it is saved (fields <prefix>country, state, city).
 *
 * @param  object $liveform
 * @param  string $prefix 'shipping_', 'billing_', '' …
 */
function pg_place_liveform_fix($liveform, $prefix = '')
{
    $city = (string) $liveform->get_field_value($prefix . 'city');
    $fixed = pg_place_city_fix((string) $liveform->get_field_value($prefix . 'country'), (string) $liveform->get_field_value($prefix . 'state'), $city);

    if ($fixed !== $city) {
        $liveform->assign_field_value($prefix . 'city', $fixed);
    }
}

/**
 * The note under a Turkish address whose district the list does not hold,
 * or '' (listed, not Turkish, nothing typed). The order is never refused.
 *
 * @param  string $country
 * @param  string $state
 * @param  string $city
 * @return string plain text
 */
function pg_place_district_note($country, $state, $city)
{
    $check = pg_place_check($country, $state, $city);

    if (!$check || $check['known']) {
        return '';
    }

    $pack = pg_places_pack($check['country']);

    return (($check['province'] !== '') && isset($pack[$check['province']]))
        ? lang(array('string' => 'This district is not in the district list of {var:1}. Check the address before shipping.', 'vars' => array($pack[$check['province']]['name'])))
        : lang('This district is not in the district list. Check the address before shipping.');
}

/**
 * The list as the browser takes it: per province (its code after "<CC>-",
 * the plate number for Turkey) the province's name, the shop's states row
 * for it (code and name, to pick the state option) and its districts joined
 * by "|". Pure.
 *
 * @param  array  $pack    pg_places_parse()
 * @param  array  $rows    the shop's states rows of that country: code, name, plate_code
 * @param  string $country two-letter code of the pack
 * @return array  '34' => array('n' => 'İstanbul', 'c' => code, 's' => name, 'd' => 'Adalar|…')
 */
function pg_places_client_list($pack, $rows, $country = 'TR')
{
    $country = strtoupper((string) $country);
    $states = array();

    foreach ((array) $rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $plate = isset($row['plate_code']) ? $row['plate_code'] : '';
        $code = pg_place_province($country, $pack, isset($row['name']) ? $row['name'] : '', $plate);

        if ($code === '') {
            $code = pg_place_province($country, $pack, isset($row['code']) ? $row['code'] : '', $plate);
        }

        if (($code !== '') && !isset($states[$code])) {
            $states[$code] = array('c' => (string) ($row['code'] ?? ''), 's' => (string) ($row['name'] ?? ''));
        }
    }

    $list = array();

    foreach ((array) $pack as $code => $place) {
        $list[substr((string) $code, strlen($country) + 1)] = array(
            'n' => $place['name'],
            'c' => isset($states[$code]) ? $states[$code]['c'] : '',
            's' => isset($states[$code]) ? $states[$code]['s'] : '',
            'd' => implode('|', $place['districts']));
    }

    return $list;
}

/**
 * The script that suggests a pack's districts while an address's country has
 * one: "Kadıköy / İstanbul"; a pick writes the district and sets the
 * province, so the order of the two boxes does not matter. Once per page, ''
 * without a connection or a pack. Printed by pg_ship_to_district_script() and
 * by the forms that mark their box (pg_places_input_attrs()).
 *
 * It hooks every <prefix>city text box with a <prefix>country control
 * (<prefix>state or <prefix>state_id the province), unless its form marks a
 * box with data-pg-place-country (on the box or an element around it): then
 * only marked boxes, whose
 * data-pg-place-country names the country control or is a pack's code (on
 * only while the province box names one of its provinces), and whose
 * data-pg-place-state names the province box.
 *
 * @return string
 */
function pg_places_suggest_script()
{
    static $done = false;

    if ($done) {
        return '';
    }

    $done = true;

    // No connection (a test, an install screen): no states to read.
    if (!function_exists('pg_schema_has') || !pg_schema_has('states')) {
        return '';
    }

    $packs = array();

    foreach (pg_places_countries() as $code) {
        if (pg_places_pack($code)) {
            $packs[$code] = array('codes' => array($code), 'rows' => array());
        }
    }

    if (!$packs) {
        return '';
    }

    foreach ((array) db_items("SELECT code, name FROM countries") as $row) {
        $code = pg_places_country((string) $row['code'], (string) $row['name']);

        if (isset($packs[$code]) && ((string) $row['code'] !== '') && !in_array((string) $row['code'], $packs[$code]['codes'], true)) {
            $packs[$code]['codes'][] = (string) $row['code'];
        }
    }

    $plate = pg_schema_has('states', 'plate_code') ? 's.plate_code' : "''";

    foreach ((array) db_items("SELECT s.code, s.name, $plate AS plate_code, c.code AS country_code, c.name AS country_name FROM states s JOIN countries c ON c.id = s.country_id") as $row) {
        $code = pg_places_country((string) $row['country_code'], (string) $row['country_name']);

        if (isset($packs[$code])) {
            $packs[$code]['rows'][] = $row;
        }
    }

    foreach ($packs as $code => $pack) {
        $packs[$code] = array('codes' => $pack['codes'], 'list' => pg_places_client_list(pg_places_pack($code), $pack['rows'], $code));
    }

    $config = str_replace('</', '<\/', encode_json(array('label' => lang('District suggestions'), 'packs' => $packs)));

    return '<script>(function(){var C=' . $config . ';' . <<<'JS'
var idx={},n=0;
function fold(s){return String(s||"").replace(/[İIı]/g,"i").normalize("NFD").replace(/[̀-ͯ]/g,"").toLowerCase().replace(/[^a-z0-9]+/g,"");}
function fire(el,t){el.dispatchEvent(new Event(t,{bubbles:true}));}
function items(cc){if(!idx[cc]){var a=[],L=C.packs[cc].list;Object.keys(L).forEach(function(k){L[k].d.split("|").forEach(function(d){a.push({d:d,k:k,f:fold(d)});});});idx[cc]=a;}return idx[cc];}
function packOf(v){for(var cc in C.packs){if(C.packs[cc].codes.indexOf(v)!==-1)return cc;}return "";}
function field(f,name){var s=name?f.elements[name]:null;if(s&&!s.tagName&&s.length){s=Array.prototype.filter.call(s,function(x){return !x.hidden&&!x.disabled;})[0];}return s||null;}
function prov(L,s){if(!s||!s.value)return "";var v=fold(s.tagName==="SELECT"&&s.selectedIndex>=0?s.options[s.selectedIndex].text:s.value),w=fold(s.value),k;
for(k in L){var p=L[k];if(fold(p.n)===v||(p.s&&fold(p.s)===v)||(p.c&&fold(p.c)===w)||fold(k)===w)return k;}return "";}
function setState(s,p){var i,o;if(!s||!p)return;
if(s.tagName==="SELECT"){for(i=0;i<s.options.length;i++){o=s.options[i];if(o.value!==""&&((p.c&&o.value===p.c)||fold(o.text)===fold(p.s||p.n)||fold(o.text)===fold(p.n))){if(s.value!==o.value){s.value=o.value;fire(s,"change");}return;}}}
else if(fold(s.value)!==fold(p.n)&&(!p.s||fold(s.value)!==fold(p.s))){s.value=p.n;fire(s,"input");fire(s,"change");}}
function hook(inp,state,cc){if(inp.getAttribute("data-pg-place-hooked"))return;
inp.setAttribute("data-pg-place-hooked","1");var id="pg-place-list-"+(++n),list=document.createElement("ul"),shown=[],act=-1,pk="";
list.id=id;list.setAttribute("role","listbox");list.setAttribute("aria-label",C.label);list.hidden=true;
list.style.cssText="position:absolute;z-index:1050;left:0;right:0;margin:2px 0 0;padding:4px 0;list-style:none;max-height:16rem;overflow:auto;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,inherit);border:1px solid var(--bs-border-color,#ccc);border-radius:.375rem;box-shadow:0 .5rem 1rem rgba(0,0,0,.15)";
var wrap=inp.parentNode;if(getComputedStyle(wrap).position==="static")wrap.style.position="relative";inp.insertAdjacentElement("afterend",list);
function close(){list.hidden=true;act=-1;inp.setAttribute("aria-expanded","false");inp.removeAttribute("aria-activedescendant");}
function mark(i){act=i;Array.prototype.forEach.call(list.children,function(li,k){li.setAttribute("aria-selected",k===i?"true":"false");li.style.background=k===i?"var(--bs-tertiary-bg,#eee)":"";});
if(i>=0){inp.setAttribute("aria-activedescendant",list.children[i].id);list.children[i].scrollIntoView({block:"nearest"});}else inp.removeAttribute("aria-activedescendant");}
function choose(k){var it=shown[k];close();if(!it)return;inp.value=it.d;fire(inp,"input");fire(inp,"change");setState(state(),C.packs[pk].list[it.k]);}
function show(){var q=fold(inp.value);pk=cc();if(!pk||q.length<2){close();return;}var L=C.packs[pk].list,own=prov(L,state()),a=[],b=[];
items(pk).forEach(function(it){var at=it.f.indexOf(q);if(at===-1||it.d==="Merkez"&&it.k!==own)return;(at===0?a:b).push(it);});
var rank=function(x,y){return (x.k===own?0:1)-(y.k===own?0:1)||x.d.localeCompare(y.d,document.documentElement.lang||undefined);};shown=a.sort(rank).concat(b.sort(rank)).slice(0,8);
list.innerHTML="";if(!shown.length){close();return;}
shown.forEach(function(it,k){var li=document.createElement("li");li.id=id+"-"+k;li.setAttribute("role","option");li.textContent=it.d+" / "+L[it.k].n;li.style.cssText="padding:.375rem .75rem;cursor:pointer";
li.addEventListener("mousedown",function(ev){ev.preventDefault();choose(k);});list.appendChild(li);});
inp.setAttribute("role","combobox");inp.setAttribute("aria-autocomplete","list");inp.setAttribute("aria-controls",id);
list.hidden=false;inp.setAttribute("aria-expanded","true");mark(-1);}
inp.addEventListener("input",function(ev){if(ev.isTrusted)show();});
inp.addEventListener("keydown",function(ev){if(list.hidden)return;var len=list.children.length;
if(ev.key==="ArrowDown"){ev.preventDefault();mark((act+1)%len);}else if(ev.key==="ArrowUp"){ev.preventDefault();mark(act<=0?len-1:act-1);}
else if(ev.key==="Enter"&&act>=0){ev.preventDefault();choose(act);}else if(ev.key==="Escape"){close();}});
inp.addEventListener("blur",function(){setTimeout(close,150);});}
function text(i){return i.type==="text"||i.type==="search"||!i.type;}
function run(){
document.querySelectorAll("form [data-pg-place-country]").forEach(function(el){var i=el.tagName==="INPUT"?el:el.querySelector("input");if(!i||!i.form||!text(i))return;
var f=i.form,a=el.getAttribute("data-pg-place-country"),c=f.elements[a],sn=el.getAttribute("data-pg-place-state");
var state=function(){return field(f,sn);};
if(c&&c.tagName)hook(i,state,function(){return packOf(c.value);});
else if(C.packs[a])hook(i,state,function(){return prov(C.packs[a].list,state())?a:"";});});
document.querySelectorAll("form input[name$=city]").forEach(function(i){var f=i.form,m=/^(.*)city$/.exec(i.name||""),c;
if(!f||!text(i)||!m||f.querySelector("[data-pg-place-country]"))return;c=f.elements[m[1]+"country"];if(!c||!c.tagName)return;
hook(i,function(){return field(f,m[1]+"state")||field(f,m[1]+"state_id");},function(){return packOf(c.value);});});}
if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",run);else run();
JS
        . '})();</script>';
}

/**
 * The attributes that mark a form's district box for the suggester, written
 * on the box or an element around it, when the form's own names do not
 * follow <prefix>city / <prefix>country: the ERP account card and delivery
 * note (district box, İl in the city box) and the panel's verified address
 * (no country control). Pure.
 *
 * @param  string $country the country control's name, or a pack's code
 * @param  string $state   the province box's name
 * @return string HTML attributes with a leading space
 */
function pg_places_input_attrs($country, $state)
{
    return ' data-pg-place-country="' . h((string) $country) . '" data-pg-place-state="' . h((string) $state) . '"';
}
