<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the language switcher system widget: the languages
 * the site is served in, each linking to the page the visitor is on in that
 * language.
 *
 * Names or codes, no flags: a flag is a country, and a language is spoken
 * in many. The loop_area is one language; the links carry hreflang so the
 * page's last pass leaves their addresses alone.
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
 * The languages the switcher lists, in display order: the source language
 * first, then every enabled target. Each with the address of the current
 * page in it.
 *
 * @return array of array(code, label, url, current)
 */
function pg_sw_language_rows()
{
    if (!pg_tr_ready()) {
        return array();
    }

    pg_tr_load();

    $source = pg_tr_source_language();
    $current = pg_tr_active() ? pg_tr_language() : $source;

    // The page's address in the source language: the request without the
    // language directory it may carry.
    $path = (string) strtok((string) get_request_uri(), '?');
    $query = (string) strtok('?');

    if (defined('LANGUAGE_PATH') && (strpos($path, (string) LANGUAGE_PATH) === 0)) {
        $path = PATH . substr($path, strlen((string) LANGUAGE_PATH));
    } elseif (defined('LANGUAGE_PATH') && (rtrim($path, '/') === rtrim((string) LANGUAGE_PATH, '/'))) {
        $path = PATH;
    }

    $path = rawurldecode($path);

    // pg_lang travels on the software's own requests only.
    if ($query !== '') {
        parse_str($query, $params);
        unset($params['pg_lang']);
        $query = http_build_query($params);
    }

    $rows = array();
    $languages = array($source => pg_tr_language_row($source));

    foreach (pg_tr_languages() as $code => $row) {
        if ($code !== $source) {
            $languages[$code] = $row;
        }
    }

    foreach ($languages as $code => $row) {
        $url = ($code === $source) ? $path : pg_tr_prefix_path($path, pg_tr_prefix($code));

        if (($code !== $source) && ($url === $path) && ($path !== PATH)) {
            // Not a page address: the language's home page instead.
            $url = PATH . pg_tr_prefix($code) . '/';
        }

        $rows[] = array(
            'code'    => $code,
            'label'   => pg_tr_language_label($code),
            'url'     => pg_tr_encode_path($url) . (($query !== '') ? '?' . $query : ''),
            'current' => ($code === $current),
        );
    }

    return $rows;
}

/**
 * Stamps the hreflang and lang attributes on every link bound to the
 * language address, so the page's last pass (pg_tr_finalize()) leaves the
 * address alone and assistive technology knows the language of the label.
 */
function _pg_sw_language_stamp_links(&$node)
{
    if (!is_array($node)) {
        return;
    }

    if (isset($node['props']['_bindings']['href']) && ($node['props']['_bindings']['href'] === '__language_url')) {
        if (!isset($node['props']['_attrs']) || !is_array($node['props']['_attrs'])) {
            $node['props']['_attrs'] = array();
        }

        $has = array();

        foreach ($node['props']['_attrs'] as $attr) {
            if (is_array($attr) && isset($attr['name'])) {
                $has[strtolower((string) $attr['name'])] = true;
            }
        }

        if (!isset($has['hreflang'])) {
            $node['props']['_attrs'][] = array('name' => 'hreflang', 'value' => '^^__language_code^^');
        }

        if (!isset($has['lang'])) {
            $node['props']['_attrs'][] = array('name' => 'lang', 'value' => '^^__language_code^^');
        }
    }

    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$child) {
            _pg_sw_language_stamp_links($child);
        }

        unset($child);
    }
}

// Render a 'language_switcher' system widget.
//
// Static tokens: ^^__current_language_code^^, ^^__current_language_label^^,
// ^^__language_count^^. Loop tokens (one row per language):
// ^^__language_code^^, ^^__language_label^^, ^^__language_url^^,
// ^^__language_active_class^^ ("active" on the current language's row),
// ^^__language_aria_current^^ ("page" on the current language's row).
//
// Visibility flags: has_languages (more than one language to choose from).
//
// cfg: show_codes (labels are the codes, upper-cased), hide_current (the
// current language's row is left out).
function _render_system_widget_language_switcher($tree_json, $widget_id, $cfg = array(), $mode = 'preview')
{
    static $placement = 0;
    $widget_id = (int) $widget_id;

    if ($tree_json === '' || $tree_json === null) return '';
    if (!is_array($cfg)) $cfg = array();

    $tree = json_decode($tree_json, true);
    if (!is_array($tree)) return '';

    $rows = pg_sw_language_rows();
    $current = null;

    foreach ($rows as $row) {
        if ($row['current']) {
            $current = $row;
        }
    }

    if (!empty($cfg['hide_current'])) {
        $rows = array_values(array_filter($rows, function ($row) { return !$row['current']; }));
    }

    _eo_apply_visibility_bindings($tree, array(
        'has_languages' => (count($rows) > (empty($cfg['hide_current']) ? 1 : 0)),
    ));

    _pg_sw_language_stamp_links($tree);
    // Stamped with a form name nothing posts to, as the cart link is: an
    // unnamed Messages block would use up every alert waiting for the page.
    _pg_inject_messages_node($tree, 'language_switcher');

    $split = _split_widget_tree($tree);

    if ($split['loop_children'] === null) {
        $loop_template = '';
        $static_html = trim(_render_tree_node($tree, 0, 0));
    } else {
        $loop_template = '';
        foreach ($split['loop_children'] as $child) $loop_template .= _render_tree_node($child, 0, 0);
        $loop_template = trim($loop_template);
        $static_html = trim(_render_tree_node($split['static_tree'], 0, 0));
    }

    $show_codes = !empty($cfg['show_codes']);
    $loop_rendered = '';

    if ($loop_template !== '') {
        foreach ($rows as $i => $row) {
            $values = pg_sw_escape_token_values(array(
                '__language_code'         => $row['code'],
                '__language_label'        => $show_codes ? mb_strtoupper($row['code']) : $row['label'],
                '__language_url'          => $row['url'],
                '__language_active_class' => $row['current'] ? 'active' : '',
                '__language_aria_current' => $row['current'] ? 'page' : '',
            ));
            $row_html = pg_sw_sweep_tokens(_pg_sw_fill_tokens($loop_template, $values));
            $loop_rendered .= pg_sw_uniquify_row_ids($row_html, $widget_id, $i);
        }
    }

    $static_values = pg_sw_escape_token_values(array(
        '__current_language_code'  => $current ? $current['code'] : '',
        '__current_language_label' => $current ? ($show_codes ? mb_strtoupper($current['code']) : $current['label']) : '',
        '__language_count'         => (string) count($rows),
    ));

    // A header and an offcanvas menu may both carry it.
    $static_html = pg_sw_uniquify_row_ids(pg_sw_sweep_tokens(_pg_sw_fill_tokens($static_html, $static_values)), $widget_id, 1000 + (++$placement));

    return str_replace('<!--pg-loop-slot-->', $loop_rendered, $static_html);
}

/* ---------------------------------------------------------------------------
   The language switcher component (componentType 'language_switcher')
   ---------------------------------------------------------------------------
   A Bootstrap dropdown: the button names the language the page is shown in,
   the menu lists the site's languages, each linking to this page in it. The
   editor draws it with the site's languages (COMPONENTS.language_switcher in
   style_designer.js); a page body is drawn when the page is saved, before
   anybody asks for it, so it keeps a placeholder with the settings and the
   page's last pass (pg_tr_finalize()) draws the switcher for the request:
   its language, its address, its query. With one language there is nothing
   to switch to and the placeholder goes. */

/**
 * The settings a language switcher is drawn with: known values only, the
 * operator's own attributes as name/value pairs (no event handler).
 *
 * @param array $props a node's props, or settings read back from a placeholder
 * @return array
 */
function pg_language_switcher_options($props)
{
    $props = is_array($props) ? $props : array();
    $pick = function ($key, $allowed, $default) use ($props) {
        $value = isset($props[$key]) ? (string) $props[$key] : $default;

        return in_array($value, $allowed, true) ? $value : $default;
    };

    $attrs = array();
    $pairs = array();

    if (!empty($props['id'])) {
        $pairs[] = array('id', (string) $props['id']);
    }

    foreach ((isset($props['_attrs']) && is_array($props['_attrs'])) ? $props['_attrs'] : array() as $attr) {
        if (is_array($attr) && isset($attr['name'])) {
            $pairs[] = array((string) $attr['name'], isset($attr['value']) ? (string) $attr['value'] : '');
        } elseif (is_array($attr) && isset($attr[0])) {
            $pairs[] = array((string) $attr[0], isset($attr[1]) ? (string) $attr[1] : '');
        }
    }

    $style = '';

    foreach ($pairs as $pair) {
        $name = strtolower($pair[0]);

        if ($name === 'style') {
            $style = $pair[1];
            continue;
        }

        if (($name === 'class') || (strpos($name, 'on') === 0) || !preg_match('/^[a-z_:][a-z0-9_:.-]*$/', $name)) {
            continue;
        }

        $attrs[] = array($name, $pair[1]);
    }

    if (!array_key_exists('style', $props)) {
        // A node's own settings: the font and the inline style as the other
        // components write them (_compose_inline_style()), then the operator's.
        $parts = array();

        if (!empty($props['fontFamily'])) {
            $family = (string) $props['fontFamily'];
            $parts[] = 'font-family:' . (((strpos($family, ' ') !== false) || preg_match('/^[0-9]/', $family)) ? "'" . str_replace("'", '', $family) . "'" : $family) . ',sans-serif';
        }

        if (!empty($props['inlineStyle'])) {
            $parts[] = rtrim((string) $props['inlineStyle'], '; ');
        }

        if (trim($style, '; ') !== '') {
            $parts[] = trim($style, '; ');
        }

        $style = implode(';', $parts);
    }

    return array(
        'variant' => $pick('variant', array('primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark', 'link'), 'secondary'),
        'outline' => !array_key_exists('outline', $props) || !empty($props['outline']),
        'size'    => $pick('size', array('', 'sm', 'lg'), 'sm'),
        'align'   => $pick('align', array('start', 'end'), 'end'),
        'display' => $pick('display', array('name', 'code'), 'name'),
        'icon'    => $pick('icon', array('translate', 'globe', 'none'), 'translate'),
        'class'   => isset($props['cssClass']) ? trim((string) $props['cssClass']) : (isset($props['class']) ? trim((string) $props['class']) : ''),
        'style'   => array_key_exists('style', $props) ? (string) $props['style'] : $style,
        'attrs'   => $attrs,
    );
}

/**
 * What a saved page body holds in the switcher's place.
 *
 * @param array $props
 * @return string
 */
function pg_language_switcher_placeholder($props)
{
    $options = pg_language_switcher_options($props);

    return '<div hidden data-pg-language-switcher="' . h(json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '"></div>';
}

/**
 * The switcher's markup for the request, '' with fewer than two languages.
 *
 * @param array $options pg_language_switcher_options()
 * @param array $rows    pg_sw_language_rows()
 * @return string
 */
function pg_language_switcher_html($options, $rows)
{
    if (count($rows) < 2) {
        return '';
    }

    $current = $rows[0];

    foreach ($rows as $row) {
        if ($row['current']) {
            $current = $row;
        }
    }

    $label = function ($row) use ($options) {
        return ($options['display'] === 'code') ? mb_strtoupper($row['code']) : $row['label'];
    };

    $button_class = 'btn btn-' . (($options['outline'] && ($options['variant'] !== 'link')) ? 'outline-' : '') . $options['variant']
        . (($options['size'] !== '') ? ' btn-' . $options['size'] : '') . ' dropdown-toggle';
    $icon = ($options['icon'] === 'none') ? '' : '<i class="bi bi-' . (($options['icon'] === 'globe') ? 'globe2' : 'translate') . ' me-1" aria-hidden="true"></i>';

    $attributes = '';

    foreach ($options['attrs'] as $pair) {
        $attributes .= ' ' . h($pair[0]) . '="' . h($pair[1]) . '"';
    }

    $items = '';

    foreach ($rows as $row) {
        $items .= '<li><a class="dropdown-item' . ($row['current'] ? ' active' : '') . '" href="' . h($row['url']) . '" hreflang="' . h($row['code']) . '" lang="' . h($row['code']) . '"'
            . ($row['current'] ? ' aria-current="page"' : '') . '>' . h($label($row)) . '</a></li>';
    }

    return '<div class="dropdown' . (($options['class'] !== '') ? ' ' . h($options['class']) : '') . '"'
        . (($options['style'] !== '') ? ' style="' . h($options['style']) . '"' : '') . $attributes . '>'
        . '<button type="button" class="' . $button_class . '" data-bs-toggle="dropdown" aria-expanded="false"'
        . ' aria-label="' . h(lang(array('string' => 'Language: {var:1}', 'vars' => array($current['label'])))) . '">'
        . $icon . '<span lang="' . h($current['code']) . '" translate="no">' . h($label($current)) . '</span></button>'
        . '<ul class="dropdown-menu' . (($options['align'] === 'end') ? ' dropdown-menu-end' : '') . '" translate="no">' . $items . '</ul>'
        . '</div>';
}

/**
 * The page's language switchers drawn for the request (pg_tr_finalize()).
 * The settings are read through pg_language_switcher_options() again, so
 * whatever a placeholder says, only known values are drawn.
 *
 * @param string $content
 * @return string
 */
function pg_language_switcher_expand($content)
{
    if (strpos($content, 'data-pg-language-switcher=') === false) {
        return $content;
    }

    $rows = null;

    // Whatever form the attributes took on the way (hidden="", another order).
    return preg_replace_callback('~<div\b[^>]*\sdata-pg-language-switcher="([^"]*)"[^>]*>\s*</div>~i', function ($match) use (&$rows) {
        $saved = json_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);

        if (!is_array($saved)) {
            return '';
        }

        if ($rows === null) {
            $rows = pg_sw_language_rows();
        }

        return pg_language_switcher_html(pg_language_switcher_options(array(
            'variant' => isset($saved['variant']) ? $saved['variant'] : '',
            'outline' => !empty($saved['outline']),
            'size'    => isset($saved['size']) ? $saved['size'] : '',
            'align'   => isset($saved['align']) ? $saved['align'] : '',
            'display' => isset($saved['display']) ? $saved['display'] : '',
            'icon'    => isset($saved['icon']) ? $saved['icon'] : '',
            'class'   => isset($saved['class']) ? (string) $saved['class'] : '',
            'style'   => isset($saved['style']) ? (string) $saved['style'] : '',
            '_attrs'  => (isset($saved['attrs']) && is_array($saved['attrs'])) ? $saved['attrs'] : array(),
        )), $rows);
    }, $content);
}

/**
 * The languages the editor draws a switcher with: the source language, then
 * every enabled target, as the site lists them.
 *
 * @return array of array(code, label)
 */
function pg_language_switcher_editor_languages()
{
    $source = function_exists('pg_tr_source_language') ? pg_tr_source_language() : 'en';

    if (!pg_tr_ready()) {
        return array(array('code' => $source, 'label' => strtoupper($source)));
    }

    pg_tr_load();

    $out = array(array('code' => $source, 'label' => pg_tr_language_label($source)));

    foreach (pg_tr_languages() as $code => $row) {
        if ($code !== $source) {
            $out[] = array('code' => (string) $code, 'label' => pg_tr_language_label($code));
        }
    }

    return $out;
}
