<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Site Settings > Appearance as folded rows (includes/settings/fold.php):
 * the rows' order, names and icons, and each part's one line of state. The
 * pg_appearance_fold_<part>() lines are pure but for lang();
 * includes/settings/appearance.php reads the facts they take.
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
function pg_appearance_fold_order()
{
    return array('theme', 'dashboard', 'editor', 'private-label');
}

/**
 * A row's name, icon and tone.
 *
 * @param string $key one of pg_appearance_fold_order()
 * @return array name, icon, tone
 */
function pg_appearance_fold_face($key)
{
    switch ($key) {
        case 'theme':
            return array('name' => lang('Panel look'), 'icon' => 'bi-palette', 'tone' => 'pg-tone-violet');
        case 'dashboard':
            return array('name' => lang('Dashboard'), 'icon' => 'bi-grid-1x2', 'tone' => 'pg-tone-blue');
        case 'editor':
            return array('name' => lang('Rich-text editor'), 'icon' => 'bi-type', 'tone' => 'pg-tone-content');
        default:
            return array('name' => lang('Private label branding'), 'icon' => 'bi-tag', 'tone' => 'pg-tone-gray');
    }
}

/**
 * Panel look's line: the acrylic effect and whether panel CSS is set.
 *
 * @param bool   $acrylic the Transparent acrylic effect switch
 * @param string $css     the panel CSS
 * @return array line, pill, attention
 */
function pg_appearance_fold_theme($acrylic, $css)
{
    return array(
        'line'      => ($acrylic ? lang('Acrylic on') : lang('Acrylic off'))
            . ', ' . ((trim((string) $css) !== '') ? lang('panel CSS set') : lang('no panel CSS')),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * The Dashboard row's line: the card style, and this sign-in's refresh time.
 *
 * @param string $style   the card style's name, '' before the upgrade that adds it
 * @param int    $refresh seconds
 * @return array line, pill, attention
 */
function pg_appearance_fold_dashboard($style, $refresh)
{
    $seconds = (string) (int) $refresh;
    $style = (string) $style;

    return array(
        'line'      => ($style !== '')
            ? lang(array('string' => '{var:1} cards', 'vars' => $style)) . ' · ' . lang(array('string' => 'refresh every {var:1} seconds', 'vars' => $seconds))
            : lang(array('string' => 'Refresh every {var:1} seconds', 'vars' => $seconds)),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * The Rich-text editor row's line: which editor version.
 *
 * @param string $version latest or previous
 * @return array line, pill, attention
 */
function pg_appearance_fold_editor($version)
{
    return array(
        'line'      => ((string) $version === 'previous') ? lang('Previous editor') : lang('Latest editor'),
        'pill'      => null,
        'attention' => false,
    );
}

/**
 * Private label branding's line: on or off.
 *
 * @param bool $on
 * @return array line, pill, attention
 */
function pg_appearance_fold_private_label($on)
{
    return array(
        'line'      => $on ? lang('On') : lang('Off'),
        'pill'      => null,
        'attention' => false,
    );
}
