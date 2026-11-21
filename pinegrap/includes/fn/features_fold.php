<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Features below the Modules board: the folded rows
 * (pg_settings_fold()), their order, names, icons and the line of state
 * each says. Pure but for lang(); includes/settings/features.php reads the
 * facts, draws each row's card and hands both to pg_settings_fold().
 *
 * A row's key is its fold id without pg-fold-; the card inside keeps its
 * own pgset- id, so anchors, the search index and the save read it as
 * before.
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
 * The rows' keys in reading order.
 *
 * @return string[]
 */
function pg_features_fold_order()
{
    return array('search', 'sharing', 'site-push', 'maps', 'page-clicks', 'content-approval', 'speed', 'images', 'revisions', 'signature', 'badge');
}

/**
 * A row's name, icon and tone. The rows of a module keep its board tile.
 *
 * @param string $key one of pg_features_fold_order()
 * @return array name, icon, tone
 */
function pg_features_fold_face($key)
{
    switch ($key) {
        case 'search':
            return array('name' => lang('Site search'), 'icon' => 'bi-search', 'tone' => 'pg-tone-blue');
        case 'sharing':
            return array('name' => lang('Sharing buttons'), 'icon' => 'bi-share', 'tone' => 'pg-tone-violet');
        case 'site-push':
            return array('name' => lang('Visitor notifications'), 'icon' => 'bi-bell', 'tone' => 'pg-tone-amber');
        case 'maps':
            return array('name' => lang('Maps'), 'icon' => 'bi-geo-alt', 'tone' => 'pg-tone-teal');
        case 'page-clicks':
            return array('name' => lang('Clicks and scrolling'), 'icon' => 'bi-cursor', 'tone' => 'pg-tone-reports');
        case 'content-approval':
            return array('name' => lang('Content approval'), 'icon' => 'bi-check2-square', 'tone' => 'pg-tone-system');
        case 'speed':
            return array('name' => lang('Speed'), 'icon' => 'bi-speedometer2', 'tone' => 'pg-tone-teal');
        case 'images':
            return array('name' => lang('Images'), 'icon' => 'bi-image', 'tone' => 'pg-tone-content');
        case 'revisions':
            return array('name' => lang('Page revisions'), 'icon' => 'bi-clock-history', 'tone' => 'pg-tone-blue');
        case 'signature':
            return array('name' => lang('Signature time stamp'), 'icon' => 'bi-pen', 'tone' => 'pg-tone-accounting');
        default:
            return array('name' => lang('Badge'), 'icon' => 'bi-person-badge', 'tone' => 'pg-tone-gray');
    }
}

/**
 * The On or Off pill of a row whose module the board switches.
 *
 * @param bool $on
 * @return array words, tone class
 */
function pg_features_fold_pill($on)
{
    return $on ? array(lang('On'), 'pg-tone-teal') : array(lang('Off'), 'pg-tone-gray');
}

/**
 * Site search's line: the search type, and the synonym groups and promoted
 * results once their table exists.
 *
 * @param bool     $advanced the Advanced search type is chosen
 * @param int|null $synonyms synonym groups, null before config_text exists
 * @param int|null $promoted promoted results, null before config_text exists
 * @return array line, pill, attention
 */
function pg_features_fold_search($advanced, $synonyms, $promoted)
{
    $line = $advanced ? lang('Advanced search') : lang('Simple search');

    if ($synonyms !== null) {
        $synonyms = (int) $synonyms;
        $line .= ' · ' . lang(array('string' => '{var:1} synonym group{suffix:1}', 'vars' => $synonyms, 'suffix' => array(($synonyms === 1) ? '' : 's')));
    }

    if ($promoted !== null) {
        $promoted = (int) $promoted;
        $line .= ' · ' . lang(array('string' => '{var:1} promoted result{suffix:1}', 'vars' => $promoted, 'suffix' => array(($promoted === 1) ? '' : 's')));
    }

    return array('line' => $line, 'pill' => null, 'attention' => false);
}

/**
 * Sharing buttons' line: the services chosen, or the own code.
 *
 * @param bool     $on       the board's Sharing buttons switch
 * @param bool     $advanced Setup is Advanced (the site's own code)
 * @param string[] $services the names of the services ticked
 * @return array line, pill, attention
 */
function pg_features_fold_sharing($on, $advanced, $services)
{
    if ($advanced) {
        $line = lang('Your own code');
    } elseif (empty($services)) {
        $line = lang('No service chosen');
    } else {
        $line = implode(', ', (array) $services);
    }

    return array('line' => $line, 'pill' => pg_features_fold_pill($on), 'attention' => false);
}

/**
 * Visitor notifications' line.
 *
 * @param bool $on  the board's switch
 * @param bool $app App mode
 * @return array line, pill, attention
 */
function pg_features_fold_site_push($on, $app)
{
    $line = ($on ? lang('Visitors may subscribe') : lang('No new subscription is taken'))
        . ' · ' . ($app ? lang('app mode on') : lang('app mode off'));

    return array('line' => $line, 'pill' => pg_features_fold_pill($on), 'attention' => false);
}

/**
 * Maps' line: whose tiles, and that the fields are kept while it is off.
 *
 * @param bool $on        the board's switch
 * @param bool $own_tiles a tile address other than OpenStreetMap's is set
 * @return array line, pill, attention
 */
function pg_features_fold_maps($on, $own_tiles)
{
    $line = $own_tiles ? lang('Your own tile address') : lang('OpenStreetMap tiles');

    if (!$on) {
        $line = lang('Off: the Map of places shows its list alone') . ' · ' . $line;
    }

    return array('line' => $line, 'pill' => pg_features_fold_pill($on), 'attention' => false);
}

/**
 * Clicks and scrolling's line.
 *
 * @param bool $on the board's switch
 * @return array line, pill, attention
 */
function pg_features_fold_page_clicks($on)
{
    return array(
        'line'      => $on ? lang('Clicks and scroll depth are counted') : lang('Nothing new is counted'),
        'pill'      => pg_features_fold_pill($on),
        'attention' => false,
    );
}

/**
 * Content approval's line.
 *
 * @param bool $on      the board's switch
 * @param bool $channel a channel is chosen to ask in too
 * @return array line, pill, attention
 */
function pg_features_fold_content_approval($on, $channel)
{
    $line = $on ? lang('Changes to published pages wait for approval') : lang('Changes are published as they are saved');

    if ($on && $channel) {
        $line .= ' · ' . lang('also asked in a channel');
    }

    return array('line' => $line, 'pill' => pg_features_fold_pill($on), 'attention' => false);
}

/**
 * Speed's line: the page cache, Prefetch Menu Links and Performance
 * Monitoring. The page cache does not run while the mobile view is on
 * (pg_page_cache_ttl()), so the line says so whatever its switch.
 *
 * @param int|null $ttl      the page lifetime in seconds, 0 off, null where
 *                           this install has no page cache
 * @param bool     $mobile   the board's Mobile view switch
 * @param bool     $prefetch Prefetch Menu Links
 * @param bool     $monitor  Performance Monitoring
 * @return array line, pill, attention
 */
function pg_features_fold_speed($ttl, $mobile, $prefetch, $monitor)
{
    $parts = array();

    if ($ttl !== null) {
        $ttl = (int) $ttl;

        if (($ttl > 0) && $mobile) {
            $parts[] = lang('Page cache off while Mobile view is on');
        } elseif ($ttl > 0) {
            $parts[] = lang(array('string' => 'Pages kept {var:1} second{suffix:1}', 'vars' => $ttl, 'suffix' => array(($ttl === 1) ? '' : 's')));
        } else {
            $parts[] = lang('Page cache off');
        }
    }

    if ($prefetch) {
        $parts[] = lang('menu links fetched ahead');
    }

    if ($monitor) {
        $parts[] = lang('requests timed');
    }

    return array('line' => $parts ? implode(' · ', $parts) : lang('Nothing is sped up'), 'pill' => null, 'attention' => false);
}

/**
 * Images' line: the formats offered and the library doing the work, or that
 * the server has none.
 *
 * @param string[] $formats the modern formats offered and written (WebP, AVIF)
 * @param string   $library the image library in use, '' for none
 * @return array line, pill, attention
 */
function pg_features_fold_images($formats, $library)
{
    if ((string) $library === '') {
        return array('line' => lang('No image library on this server'), 'pill' => array(lang('Not ready'), 'pg-tone-amber'), 'attention' => false);
    }

    $line = empty($formats)
        ? lang('Pictures sent as uploaded')
        : lang(array('string' => 'Offered as {var:1}', 'vars' => array(implode(', ', (array) $formats))));

    return array('line' => $line . ' · ' . $library, 'pill' => null, 'attention' => false);
}

/**
 * Page revisions' line.
 *
 * @param int $keep the newest kept per page or item
 * @param int $days every one younger than this is kept
 * @return array line, pill, attention
 */
function pg_features_fold_revisions($keep, $days)
{
    $days = (int) $days;

    return array(
        'line'      => lang(array('string' => 'The last {var:1}, and every one from the last {var:2} day{suffix:1}', 'vars' => array((int) $keep, $days), 'suffix' => array(($days === 1) ? '' : 's'))),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * Signature time stamp's line: the authority's host, or none.
 *
 * @param string $url the time stamp authority's address, '' for none
 * @return array line, pill, attention
 */
function pg_features_fold_signature($url)
{
    $url = trim((string) $url);

    if ($url === '') {
        return array('line' => lang('No time stamp is taken'), 'pill' => null, 'attention' => false);
    }

    $host = parse_url($url, PHP_URL_HOST);

    return array(
        'line'      => lang(array('string' => 'Stamped by {var:1}', 'vars' => array((is_string($host) && ($host !== '')) ? $host : $url))),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * Badge's line: the label members get by default.
 *
 * @param string $label Default Badge Label
 * @return array line, pill, attention
 */
function pg_features_fold_badge($label)
{
    $label = trim((string) $label);

    return array(
        'line'      => ($label !== '') ? lang(array('string' => 'Members are marked "{var:1}"', 'vars' => $label)) : lang('No default label'),
        'pill'      => null,
        'attention' => false,
    );
}
