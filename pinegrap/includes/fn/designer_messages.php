<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual editor's Messages node: the alert boxes it prints for a
 * liveform's errors, notices and warnings, and its two settings.
 *
 * `alertLook` changes the box ('' the standard alert, 'accent' a coloured
 * line on the left, 'solid' a filled box, 'compact' a smaller box);
 * `dismissButton` shows the close button the box always carries hidden.
 * With neither set the markup is the one liveform::output_errors() prints,
 * so a published page keeps its output.
 *
 * Inside a widget the node is drawn by _render_tree_node(); at page level
 * the editor saves `<!--pg-messages-placeholder-->` (or, with a setting,
 * `<!--pg-messages-placeholder:<look>:<0|1>-->`) and get_page_content.php
 * fills it per request. The editor's twin is the `messages` entry of the
 * content registry in assets/js/style_designer.js.
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
 * The known alert looks; anything else reads as the standard alert.
 */
function pg_messages_look_normalize($look)
{
    $look = is_string($look) ? $look : '';
    return in_array($look, array('accent', 'solid', 'compact'), true) ? $look : '';
}

/**
 * The look and the dismiss button of a Messages node's props.
 *
 * @return array('look' => string, 'dismiss' => bool)
 */
function pg_messages_settings($props)
{
    $props = is_array($props) ? $props : array();
    return array(
        'look'    => pg_messages_look_normalize(isset($props['alertLook']) ? $props['alertLook'] : ''),
        'dismiss' => !empty($props['dismissButton']),
    );
}

/**
 * The settings a page-level placeholder carries: `<!--pg-messages-placeholder-->`
 * has none, `<!--pg-messages-placeholder:accent:1-->` a look and the button.
 *
 * @param string $marker the whole comment
 * @return array('look' => string, 'dismiss' => bool)
 */
function pg_messages_marker_settings($marker)
{
    if (!is_string($marker) || !preg_match('/^<!--pg-messages-placeholder(?::([a-z]*):([01]))?-->$/', $marker, $m)) {
        return array('look' => '', 'dismiss' => false);
    }
    return array(
        'look'    => pg_messages_look_normalize(isset($m[1]) ? $m[1] : ''),
        'dismiss' => isset($m[2]) && $m[2] === '1',
    );
}

/**
 * One alert box of the Messages node.
 *
 * @param string $kind        'error', 'notice' or 'warning'
 * @param string $lines_html  the message lines, already made safe
 * @param array  $settings    pg_messages_settings()
 * @param string $extra_class the node's CSS class (escaped here)
 * @return string
 */
function pg_messages_alert_html($kind, $lines_html, $settings = array(), $extra_class = '')
{
    $colours = array('error' => 'danger', 'notice' => 'success', 'warning' => 'warning');
    $kind    = isset($colours[$kind]) ? $kind : 'notice';
    $colour  = $colours[$kind];
    $look    = pg_messages_look_normalize(isset($settings['look']) ? $settings['look'] : '');
    $dismiss = !empty($settings['dismiss']);

    $look_class = '';
    $close_class = 'btn-close no-popover';
    if ($look === 'accent') {
        $look_class = ' border-0 border-start border-4 border-' . $colour;
    } elseif ($look === 'solid') {
        $look_class = ' text-bg-' . $colour;
        if ($colour !== 'warning') $close_class .= ' btn-close-white';
    } elseif ($look === 'compact') {
        $look_class = ' py-2 small';
    }
    $extra = ((string)$extra_class !== '') ? ' ' . h($extra_class) : '';

    return '<div class="software_' . $kind . ' alert alert-' . $colour . ' alert-dismissible show' . $look_class . $extra . '" role="alert">'
        . '<button type="button"' . ($dismiss ? '' : ' style="display:none;"') . ' class="' . $close_class . '" data-bs-dismiss="alert" aria-label="' . h(lang('Close')) . '"></button>'
        . ($kind === 'error' ? '<h5 class="alert-heading description">' . lang('An error occurred') . ':</h5>' : '')
        . $lines_html
        . '</div>';
}
