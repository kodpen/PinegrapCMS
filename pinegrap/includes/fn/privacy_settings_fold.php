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
    return array('analytics', 'cookies', 'legal', 'kvkk', 'a11y-statement', 'privacy-centre');
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
        case 'cookies':
            return array('name' => lang('Cookie consent'), 'icon' => 'bi-cookie', 'tone' => 'pg-tone-amber');
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
 * The KVKK notice's line: the page, the consent box, and that both are used
 * only while Legal documents is on (pg_legal_kvkk_settings()).
 *
 * @param bool   $page_chosen a notice page is chosen
 * @param string $consent     the explicit consent sentence
 * @param bool   $legal_on    the Legal documents switch
 * @return array line, pill, attention
 */
function pg_privacy_fold_kvkk($page_chosen, $consent, $legal_on)
{
    $has_consent = (trim((string) $consent) !== '');
    $line = ($page_chosen ? lang('Notice page chosen') : lang('No notice page'))
        . ' · ' . ($has_consent ? lang('a consent box') : lang('no consent box'))
        . ' · ' . lang('used while Legal documents is on');
    $used = $legal_on && ($page_chosen || $has_consent);

    return array(
        'line'      => $line,
        'pill'      => $used ? array(lang('In use'), 'pg-tone-teal') : array(lang('Not used'), 'pg-tone-gray'),
        'attention' => false,
    );
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
 * The Privacy centre's line: what the centre holds.
 *
 * @return array line, pill, attention
 */
function pg_privacy_fold_centre()
{
    return array(
        'line'      => lang('The personal data the site keeps, why and for how long, and the outside services it goes to, with an Excel and a PDF download for your KVKK or GDPR records.'),
        'pill'      => null,
        'attention' => false,
    );
}
