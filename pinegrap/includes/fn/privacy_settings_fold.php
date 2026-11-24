<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Visitors and privacy as folded rows
 * (includes/settings/fold.php): the rows' order, names and icons, and each
 * part's one line of state with its pill. The pg_privacy_fold_<part>()
 * lines are pure but for lang(); includes/settings/privacy.php reads the
 * facts they take.
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
 * The rows' keys in reading order. A key is its card's id without pgset-.
 *
 * @return string[]
 */
function pg_privacy_fold_order()
{
    return array('analytics', 'retention', 'cookies', 'interest-score', 'legal', 'kvkk', 'a11y-statement', 'privacy-centre');
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of pg_privacy_fold_order()
 * @return array name, icon, tone
 */
function pg_privacy_fold_face($key)
{
    switch ($key) {
        case 'analytics':
            return array('name' => lang('Visitor tracking'), 'icon' => 'bi-graph-up', 'tone' => 'pg-tone-blue');
        case 'retention':
            return array('name' => lang('How long we keep data'), 'icon' => 'bi-hourglass-split', 'tone' => 'pg-tone-violet');
        case 'cookies':
            return array('name' => lang('Cookie consent'), 'icon' => 'bi-cookie', 'tone' => 'pg-tone-amber');
        case 'interest-score':
            return array('name' => lang('Interest score'), 'icon' => 'bi-fire', 'tone' => 'pg-tone-rose');
        case 'legal':
            return array('name' => lang('Legal documents'), 'icon' => 'bi-file-earmark-text', 'tone' => 'pg-tone-shop');
        case 'kvkk':
            return array('name' => lang('KVKK notice'), 'icon' => 'bi-shield-lock', 'tone' => 'pg-tone-violet');
        case 'a11y-statement':
            return array('name' => lang('Accessibility statement'), 'icon' => 'bi-universal-access', 'tone' => 'pg-tone-teal');
        default:
            return array('name' => lang('Privacy centre'), 'icon' => 'bi-person-lock', 'tone' => '');
    }
}

/**
 * The On or Off pill of a part one switch turns on.
 *
 * @param bool $on
 * @return array words, tone class
 */
function pg_privacy_fold_switch_pill($on)
{
    return $on ? array(lang('On'), 'pg-tone-teal') : array(lang('Off'), 'pg-tone-gray');
}

/**
 * Visitor tracking's line: whether visits are recorded, and Google
 * Analytics when it is connected.
 *
 * @param bool $on           the Record visits switch
 * @param bool $ga_connected the Google tag is on with a valid ID
 * @return array line, pill, attention
 */
function pg_privacy_fold_tracking($on, $ga_connected)
{
    $line = $on ? lang('Visits are recorded') : lang('Visits are not recorded');

    if ($ga_connected) {
        $line .= ' · ' . lang('Google Analytics connected');
    }

    return array('line' => $line, 'pill' => pg_privacy_fold_switch_pill($on), 'attention' => false);
}

/**
 * How long we keep data's line: the visitor records' period and when their
 * IP addresses go; without those fields, what the row shows.
 *
 * @param int|null $records_days the visitor records' days (0: no limit), null
 *                               before the column exists
 * @param int|null $ip_days      the days after which IP addresses go (0:
 *                               with the record), null before the column
 * @return array line, pill, attention
 */
function pg_privacy_fold_retention($records_days, $ip_days)
{
    if ($records_days === null) {
        $line = lang('Every period the site applies, and where each is set');
    } else {
        $records_days = (int) $records_days;
        $line = ($records_days > 0)
            ? lang(array('string' => 'Visitor records kept {var:1} day{suffix:1}', 'vars' => $records_days, 'suffix' => array(($records_days === 1) ? '' : 's')))
            : lang('Visitor records kept until deleted');

        if ($ip_days !== null) {
            $ip_days = (int) $ip_days;
            $line .= ' · ' . (($ip_days > 0)
                ? lang(array('string' => 'IP addresses removed after {var:1} day{suffix:1}', 'vars' => $ip_days, 'suffix' => array(($ip_days === 1) ? '' : 's')))
                : lang('IP addresses kept with the record'));
        }
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * Cookie consent's line.
 *
 * @param bool $on
 * @return array line, pill, attention
 */
function pg_privacy_fold_cookies($on)
{
    return array(
        'line'      => $on ? lang('Visitors choose which cookies are set') : lang('Every cookie is set without asking'),
        'pill'      => pg_privacy_fold_switch_pill($on),
        'attention' => false,
    );
}

/**
 * Interest score's line: from which score a contact is hot while the
 * journey is recorded, which needs visitor tracking and the cookie notice
 * (a browser is followed only after its marketing yes).
 *
 * @param bool $tracking  visitor tracking on
 * @param bool $consent   the cookie notice on
 * @param int  $threshold the hot threshold
 * @return array line, pill, attention
 */
function pg_privacy_fold_interest($tracking, $consent, $threshold)
{
    $on = $tracking && $consent;

    return array(
        'line'      => $on ? lang(array('string' => 'Hot from a score of {var:1}', 'vars' => array((int) $threshold))) : lang('Needs visitor tracking and the cookie notice'),
        'pill'      => pg_privacy_fold_switch_pill($on),
        'attention' => false,
    );
}

/**
 * Legal documents' line.
 *
 * @param bool $on
 * @return array line, pill, attention
 */
function pg_privacy_fold_legal($on)
{
    return array(
        'line'      => $on ? lang('Customers accept both documents at checkout') : lang('The checkout asks for no document'),
        'pill'      => pg_privacy_fold_switch_pill($on),
        'attention' => false,
    );
}

/**
 * The KVKK notice's line: the page and the consent box; its own switch
 * decides whether they are shown (pg_legal_kvkk_settings()).
 *
 * @param bool   $on          the Show the KVKK notice switch
 * @param bool   $page_chosen a notice page is chosen
 * @param string $consent     the explicit consent sentence
 * @return array line, pill, attention
 */
function pg_privacy_fold_kvkk($on, $page_chosen, $consent)
{
    $has_consent = (trim((string) $consent) !== '');
    $line = ($page_chosen ? lang('Notice page chosen') : lang('No notice page'))
        . ' · ' . ($has_consent ? lang('a consent box') : lang('no consent box'));

    if (!$on) {
        $pill = pg_privacy_fold_switch_pill(false);
    } elseif ($page_chosen || $has_consent) {
        $pill = array(lang('In use'), 'pg-tone-teal');
    } else {
        $pill = array(lang('Not used'), 'pg-tone-gray');
    }

    return array('line' => $line, 'pill' => $pill, 'attention' => false);
}

/**
 * The accessibility statement's line: its date, or Not filled.
 *
 * @param string $date the statement's date as the site writes dates, '' for none
 * @return array line, pill, attention
 */
function pg_privacy_fold_a11y($date)
{
    $date = (string) $date;

    return array(
        'line'      => ($date !== '') ? lang(array('string' => 'Dated {var:1}', 'vars' => $date)) : lang('Not filled'),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * The Privacy centre's line: the parts the centre holds, named short; the
 * opened row says each in a sentence.
 *
 * @return array line, pill, attention
 */
function pg_privacy_fold_centre()
{
    return array(
        'line'      => lang('Personal data inventory, retention rules and requests'),
        'pill'      => null,
        'attention' => false,
    );
}
