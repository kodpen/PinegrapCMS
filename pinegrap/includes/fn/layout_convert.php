<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * A classic custom form layout (the PHP view template of a page whose type
 * is 'custom form', data/layouts/<id>.php or the generated one) turned into
 * the design elements of a custom_form widget: the layout's own markup kept,
 * every control named after its field and tied to its row, a radio or check
 * box field drawn as a fieldset, the CAPTCHA block as the CAPTCHA element,
 * and the field labels and information printed as text the translation
 * reads. No PHP reaches the tree: what the converter does not know, and
 * what a designed form has no place for (the comment notification box, the
 * save-for-later button, other rules), is left out and reported.
 *
 * Pure: no query, no file. The caller passes the layout text, the form's
 * field rows (pg_cf_load_page_fields()) and, for a layout that still holds
 * the generated part, generate_form_layout_content()'s text.
 *
 * The same for the other page types a widget stands in for
 * (pg_layout_convert()): a page's own layout file becomes its widget's
 * design elements, each piece of the type's data contract turned into what
 * the widget has for it by the type's piece map (pg_layout_piece_map()),
 * and the pieces with no element reported.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/designer_region_import.php');

/**
 * The widget tree of a classic custom form layout.
 *
 * @param string $code    the layout's PHP text
 * @param array  $fields  the form's rows, as pg_cf_load_page_fields() returns them
 * @param array  $opts    'page_id' (the form's page, keys `_cf.ids`), 'submit_label'
 *                        (the page's button label, '' for the default), 'generated'
 *                        (the text the layout's generate_form_layout_content() call prints)
 * @return array  'ok' (false when the text cannot be read as PHP), 'tree' (root node),
 *                'dropped' (what was left out, in order: 'watcher', 'save', 'condition', 'php',
 *                'signature'), 'missing' (ids of rows the layout does not draw, added at the
 *                end of the form)
 */
function pg_layout_form_convert($code, $fields, $opts = array())
{
    $page_id = (int)(isset($opts['page_id']) ? $opts['page_id'] : 0);
    $submit  = trim((string)(isset($opts['submit_label']) ? $opts['submit_label'] : ''));
    if ($submit === '') $submit = lang('Submit');

    $by_id = array();
    foreach ((array)$fields as $f) {
        if (is_array($f) && isset($f['id'])) $by_id[(int)$f['id']] = $f;
    }

    $st = new stdClass();
    $st->html      = '';
    $st->dropped   = array();
    $st->info      = array();
    $st->ok        = true;
    $st->by_id     = $by_id;
    $st->submit    = $submit;
    $st->generated = (string)(isset($opts['generated']) ? $opts['generated'] : '');
    _pg_lfc_walk((string)$code, $st, 0);

    $out = array('ok' => $st->ok, 'tree' => pg_di_root(array()), 'dropped' => $st->dropped, 'missing' => array());
    if (!$st->ok) return $out;

    // The widget draws the <form> itself.
    $html = preg_replace('~</?form\b[^>]*>~i', '', $st->html);
    $children = _pg_lfc_parse($html);

    $root = array('children' => $children);
    _pg_lfc_captcha($root['children']);

    // Rows the layout does not draw come at the end of the form, before the
    // CAPTCHA and the button, in their order: the first save writes the
    // form from the widget, and a row left out would be deleted with its
    // answers. A signature has no designed control; its row stays.
    $present = $st->info;
    _pg_lfc_present($root['children'], $present);
    $extra = '';
    foreach ($by_id as $fid => $f) {
        if (isset($present[$fid])) continue;
        if ((string)$f['type'] === 'signature') { _pg_lfc_drop($st, 'signature'); continue; }
        $out['missing'][] = $fid;
        $extra .= _pg_lfc_missing_html($f);
        if ((string)$f['type'] === 'information') $st->info[$fid] = true;
    }
    $out['dropped'] = $st->dropped;
    if ($extra !== '') _pg_lfc_insert_before_end($root, _pg_lfc_parse($extra));

    $drawn = $st->info;
    foreach ($by_id as $fid => $f) {
        $type = isset($f['type']) ? (string)$f['type'] : '';
        if ($type === 'radio button' || $type === 'check box') {
            if (_pg_lfc_choice($root, $f, $page_id)) $drawn[$fid] = true;
        }
    }
    $used_ids = array();
    _pg_lfc_collect_ids($root['children'], $used_ids);
    $moved = array();
    _pg_lfc_controls($root['children'], $by_id, $page_id, $used_ids, $moved, $drawn);
    _pg_lfc_labels($root['children'], $moved);
    $for = array();
    _pg_lfc_label_targets($root['children'], $for);
    _pg_lfc_row_labels($root['children'], $by_id, $for);

    $root['children'][] = pg_di_mk('loop_area', array());
    $out['tree'] = pg_di_root($root['children']);
    return $out;
}

// Rows a control of the parsed layout stands for, by its classic name.
function _pg_lfc_present($nodes, &$present)
{
    foreach ($nodes as $n) {
        if (in_array(_pg_lfc_tag($n), array('input', 'select', 'textarea'), true)) {
            $fid = _pg_lfc_field_of($n);
            if ($fid > 0) $present[$fid] = true;
        }
        if (!empty($n['children'])) _pg_lfc_present($n['children'], $present);
    }
}

// A row the layout left out, as the classic markup the passes below turn
// into elements like the rest: named by its id, labelled, Bootstrap 5.
function _pg_lfc_missing_html($f)
{
    $id = (int)$f['id'];
    $label = (string)$f['label'];
    switch ((string)$f['type']) {
        case 'information':
            return '<div class="mb-3">' . (string)$f['information'] . '</div>';
        case 'radio button':
        case 'check box':
            $kind = ($f['type'] === 'radio button') ? 'radio' : 'checkbox';
            $html = '<div class="mb-3"><div>' . $label . '</div>';
            foreach ((array)$f['options'] as $o) {
                $html .= '<div><input type="' . $kind . '" name="' . $id . '" value="' . h((string)$o['value']) . '"></div>';
            }
            return $html . '</div>';
        case 'text area':
            $control = '<textarea name="' . $id . '" id="' . $id . '" class="form-control"></textarea>';
            break;
        case 'pick list':
            $control = '<select name="' . $id . '" id="' . $id . '" class="form-select"></select>';
            break;
        default:
            $control = '<input type="text" name="' . $id . '" id="' . $id . '" class="form-control">';
    }
    return '<div class="mb-3"><label for="' . $id . '" class="form-label">' . $label . '</label>' . $control . '</div>';
}

// Inserts nodes before the form's end: the CAPTCHA element or the first
// submit button, or the outermost wrapper holding only it; at the end when
// there is neither.
function _pg_lfc_insert_before_end(&$root, $nodes)
{
    if (empty($nodes)) return;
    $path = _pg_lfc_end_path($root['children'], array());
    if ($path === null) {
        foreach ($nodes as $n) $root['children'][] = $n;
        return;
    }
    while (count($path) > 1) {
        $holder = _pg_lfc_at($root, array_slice($path, 0, -1));
        if (count($holder['children']) !== 1) break;
        array_pop($path);
    }
    $at = array_pop($path);
    $holder = &_pg_lfc_at($root, $path);
    array_splice($holder['children'], $at, 0, $nodes);
    unset($holder);
}

function _pg_lfc_end_path($nodes, $path)
{
    foreach ($nodes as $i => $n) {
        $p = array_merge($path, array($i));
        $tag = _pg_lfc_tag($n);
        if ((isset($n['props']['_bindings']['section']) && $n['props']['_bindings']['section'] === 'captcha')
            || (($tag === 'button' || $tag === 'input') && strtolower((string)_pg_lfc_attr($n, 'type')) === 'submit')
            || ($tag === 'button' && _pg_lfc_attr($n, 'type') === null)) {
            return $p;
        }
        if (!empty($n['children'])) {
            $hit = _pg_lfc_end_path($n['children'], $p);
            if ($hit !== null) return $hit;
        }
    }
    return null;
}

// One PHP part's code without white space and comments, a closing
// semicolon dropped: what the patterns below compare.
function _pg_lfc_source($tokens)
{
    $s = '';
    foreach ($tokens as $t) {
        if (is_array($t) && in_array($t[0], array(T_WHITESPACE, T_COMMENT, T_DOC_COMMENT), true)) continue;
        $s .= is_array($t) ? $t[1] : $t;
    }
    return rtrim($s, ';');
}

function _pg_lfc_drop($st, $what)
{
    if (!in_array($what, $st->dropped, true)) $st->dropped[] = $what;
}

// Walks the layout's parts into $st->html: markup inside a rule that is not
// kept is skipped, a printed value becomes its text, the CAPTCHA block a
// marker. The generated part is walked the same way, once.
function _pg_lfc_walk($code, $st, $depth)
{
    $parts = pg_di_php_parts($code);
    if ($parts === null) { $st->ok = false; return; }

    // Open rules, innermost last: 'keep' false skips what they hold.
    $stack = array();
    foreach ($parts as $p) {
        $keep = true;
        foreach ($stack as $fr) if (!$fr['keep']) { $keep = false; break; }

        if ($p['kind'] === 'html') {
            if ($keep) $st->html .= $p['text'];
            continue;
        }
        $src = _pg_lfc_source($p['tokens']);
        if ($src === '') continue;

        if (!empty($p['echo'])) {
            if ($keep) $st->html .= _pg_lfc_echo($src, $st, $depth);
            continue;
        }

        if (preg_match('/^if\((.+)\)(:|\{)$/s', $src, $m)) {
            $flags = array('$captcha_question' => 'captcha', '$watcher_option' => 'watcher',
                           '$save' => 'save', '$office_use_only' => 'office');
            $kind = isset($flags[$m[1]]) ? $flags[$m[1]] : 'condition';
            // The generator's guard around an SMS question
            // (isset($field['<id>'])) is kept like the office-use-only one.
            if (preg_match('/^isset\(\$field\[([\'"])\d+\1\]\)$/', $m[1])) $kind = 'office';
            // Office-use-only fields are told apart by their rows: the
            // designed form hides them from visitors itself (an SMS question
            // too, pg_cf_drop_sms_question()). The CAPTCHA
            // block is kept inside a marker; its wrappers stay around the
            // CAPTCHA element (_pg_lfc_captcha()).
            $open = ($kind === 'captcha' && $keep);
            $stack[] = array('kind' => $kind, 'keep' => ($kind === 'office' || $kind === 'captcha'), 'open' => $open);
            if ($open) $st->html .= '<pg-captcha>';
            if ($kind !== 'captcha' && $kind !== 'office' && $keep) _pg_lfc_drop($st, $kind);
            continue;
        }
        if (preg_match('/^\}?(else|elseif\(.+\))(:|\{)$/s', $src)) {
            if (empty($stack)) { _pg_lfc_drop($st, 'php'); continue; }
            // An "otherwise" branch has no element to become: skipped.
            $top = count($stack) - 1;
            $outer = true;
            for ($i = 0; $i < $top; $i++) if (!$stack[$i]['keep']) { $outer = false; break; }
            if ($outer) _pg_lfc_drop($st, 'condition');
            if (!empty($stack[$top]['open'])) { $st->html .= '</pg-captcha>'; $stack[$top]['open'] = false; }
            $stack[$top]['keep'] = false;
            continue;
        }
        if (preg_match('/^(endif|endforeach|endfor|endwhile|endswitch|\})$/', $src)) {
            if (empty($stack)) { _pg_lfc_drop($st, 'php'); continue; }
            $fr = array_pop($stack);
            if (!empty($fr['open'])) $st->html .= '</pg-captcha>';
            continue;
        }
        // Any other code: a loop or block it opens is skipped whole.
        if ($keep) _pg_lfc_drop($st, 'php');
        if (preg_match('/(:|\{)$/', $src)) $stack[] = array('kind' => 'php', 'keep' => false);
    }
}

// The text a printed value stands for.
function _pg_lfc_echo($src, $st, $depth)
{
    if (in_array($src, array('$messages', '$system', '$attributes'), true)) return '';
    if (preg_match('/^\$field\[([\'"])(\d+)\1\]\[([\'"])(label|information)\3\]$/', $src, $m)) {
        $fid = (int)$m[2];
        if (!isset($st->by_id[$fid])) return '';
        if ($m[4] === 'information') {
            $st->info[$fid] = true;
            return (string)$st->by_id[$fid]['information'];
        }
        return (string)$st->by_id[$fid]['label'];
    }
    if ($src === 'h($submit_button_label)' || $src === '$submit_button_label') return h($st->submit);
    // The question is the CAPTCHA element's own.
    if ($src === 'h($captcha_question)' || $src === '$captcha_question') return '';
    if (preg_match('/^pg_signature_field\(/', $src)) {
        _pg_lfc_drop($st, 'signature');
        return '';
    }
    if (preg_match('/^eval\([\'"]\?>[\'"]\.generate_form_layout_content\(/', $src)) {
        if ($depth === 0 && $st->generated !== '') {
            $before = $st->html;
            $st->html = '';
            _pg_lfc_walk($st->generated, $st, 1);
            $st->html = $before . $st->html;
            return '';
        }
    }
    _pg_lfc_drop($st, 'php');
    return '';
}

// The markup as designer nodes, by the HTML converter's own rules.
function _pg_lfc_parse($html)
{
    if (trim($html) === '') return array();
    $ctx = new stdClass();
    $ctx->user_id   = 0;
    $ctx->files     = array();
    $ctx->folders   = array();
    $ctx->warnings  = array();
    $ctx->root_id   = 0;
    $ctx->project   = '';
    $ctx->base_dir  = '';
    $ctx->page_slug = 'layout';
    $ctx->fragment  = true;
    $ctx->svg_raw   = array();
    $html = pg_di_lift_svgs($html, $ctx);

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . '<body>' . $html . '</body>', LIBXML_NONET);
    libxml_clear_errors();
    if (!$loaded) return array();

    $out = array();
    $body = (new DOMXPath($doc))->query('//body')->item(0);
    if ($body) {
        foreach ($body->childNodes as $cn) {
            $node = pg_di_node($cn, $ctx);
            if ($node !== null) $out[] = $node;
        }
    }
    return $out;
}

function _pg_lfc_tag($n)
{
    return (is_array($n) && isset($n['type']) && $n['type'] === 'semantic' && isset($n['props']['tag']))
        ? strtolower((string)$n['props']['tag']) : '';
}

function _pg_lfc_attr($n, $name)
{
    foreach ((array)(isset($n['props']['_attrs']) ? $n['props']['_attrs'] : array()) as $a) {
        if (is_array($a) && isset($a['name']) && strtolower((string)$a['name']) === $name) {
            return isset($a['value']) ? (string)$a['value'] : '';
        }
    }
    return null;
}

// Sets an attribute, or removes it with null.
function _pg_lfc_set_attr(&$n, $name, $value)
{
    $attrs = array();
    $done = false;
    foreach ((array)(isset($n['props']['_attrs']) ? $n['props']['_attrs'] : array()) as $a) {
        if (is_array($a) && isset($a['name']) && strtolower((string)$a['name']) === $name) {
            if ($value !== null && !$done) { $attrs[] = array('name' => $name, 'value' => (string)$value); $done = true; }
            continue;
        }
        $attrs[] = $a;
    }
    if ($value !== null && !$done) $attrs[] = array('name' => $name, 'value' => (string)$value);
    if ($attrs) $n['props']['_attrs'] = $attrs;
    else unset($n['props']['_attrs']);
}

function _pg_lfc_mk_attrs($pairs)
{
    $out = array();
    foreach ($pairs as $k => $v) $out[] = array('name' => $k, 'value' => (string)$v);
    return $out;
}

// The CAPTCHA block as the element the palette's CAPTCHA block is: the
// site-wide question, gone with its container when it is off. The block's
// single wrappers (a column, a form group) stay around it; what they held
// beside it (the classic heading, label and answer box) goes.
function _pg_lfc_captcha(&$nodes)
{
    $out = array();
    foreach ($nodes as $n) {
        if (_pg_lfc_tag($n) !== 'pg-captcha') {
            if (!empty($n['children'])) _pg_lfc_captcha($n['children']);
            $out[] = $n;
            continue;
        }
        $element = pg_di_mk('semantic', array('tag' => 'div', 'cssClass' => 'mb-3', 'customName' => lang('CAPTCHA'),
            '_bindings' => array('section' => 'captcha')));
        $kids = isset($n['children']) ? $n['children'] : array();
        if (count($kids) !== 1 || in_array(_pg_lfc_tag($kids[0]), array('input', 'label', 'h5', ''), true)) {
            $out[] = $element;
            continue;
        }
        $wrapper = $kids[0];
        $at = &$wrapper;
        while (isset($at['children']) && count($at['children']) === 1
               && !in_array(_pg_lfc_tag($at['children'][0]), array('input', 'label', ''), true)
               && !empty($at['children'][0]['children'])) {
            $at = &$at['children'][0];
        }
        $at['children'] = array($element);
        unset($at);
        $out[] = $wrapper;
    }
    $nodes = $out;
}

// The field id a classic control's name carries ("314", "118[]"), 0 for any other.
function _pg_lfc_field_of($n)
{
    $name = _pg_lfc_attr($n, 'name');
    if ($name === null || !preg_match('/^(\d+)(\[\])?$/', trim($name), $m)) return 0;
    return (int)$m[1];
}

// Paths (child indexes from the top) of the boxes of one field.
function _pg_lfc_box_paths($nodes, $fid, $path, &$out)
{
    foreach ($nodes as $i => $n) {
        $p = array_merge($path, array($i));
        if (_pg_lfc_tag($n) === 'input' && _pg_lfc_field_of($n) === $fid) {
            $t = strtolower((string)_pg_lfc_attr($n, 'type'));
            if ($t === 'radio' || $t === 'checkbox') { $out[] = $p; continue; }
        }
        if (!empty($n['children'])) _pg_lfc_box_paths($n['children'], $fid, $p, $out);
    }
}

function &_pg_lfc_at(&$root, $path)
{
    $n = &$root;
    foreach ($path as $i) $n = &$n['children'][$i];
    return $n;
}

function _pg_lfc_has_control($n)
{
    if (in_array(_pg_lfc_tag($n), array('input', 'select', 'textarea'), true)) return true;
    foreach ((array)(isset($n['children']) ? $n['children'] : array()) as $c) {
        if (_pg_lfc_has_control($c)) return true;
    }
    return false;
}

function _pg_lfc_norm($s)
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$s), ENT_QUOTES, 'UTF-8')));
}

// A safe, unused element id from a field name.
function _pg_lfc_new_id($name, &$used)
{
    $base = trim(preg_replace('/[^A-Za-z0-9_-]+/', '_', (string)$name), '_');
    if ($base === '' || !preg_match('/^[A-Za-z]/', $base)) $base = 'f_' . $base;
    $id = $base;
    for ($i = 2; isset($used[$id]); $i++) $id = $base . '_' . $i;
    $used[$id] = true;
    return $id;
}

// What a control keeps of its row that markup cannot say.
function _pg_lfc_cf($f, $page_id)
{
    $cf = array('ids' => array((string)$page_id => (int)$f['id']));
    if (!empty($f['office_use_only'])) $cf['office_use_only'] = true;
    if (!empty($f['rss_field']))       $cf['rss_field'] = (string)$f['rss_field'];
    if (!empty($f['contact_field']))   $cf['contact_field'] = (string)$f['contact_field'];
    if (!empty($f['regex_message']))   $cf['validation_message'] = (string)$f['regex_message'];
    if (!empty($f['upload_folder']))   $cf['upload_folder_id'] = (int)$f['upload_folder'];
    if (!empty($f['quiz_question'])) {
        $cf['quiz_question'] = true;
        $cf['quiz_answer'] = (string)$f['quiz_answer'];
    }
    if (isset($f['type']) && $f['type'] === 'text area') $cf['wysiwyg'] = !empty($f['wysiwyg']);
    if (isset($f['translate'])) $cf['translate'] = !empty($f['translate']);
    return $cf;
}

/**
 * A radio or check box field as a fieldset: the legend is the field label,
 * one Bootstrap box per option of the row (the layout's own boxes when the
 * row has none), each labelled. It takes the place of the layout's boxes;
 * the line that printed the label just before them becomes the legend, also
 * when the boxes sit in a wrapper of their own, which stays.
 */
function _pg_lfc_choice(&$root, $f, $page_id)
{
    $fid = (int)$f['id'];
    $paths = array();
    _pg_lfc_box_paths($root['children'], $fid, array(), $paths);
    if (empty($paths)) return false;

    $options = array();
    foreach ((array)(isset($f['options']) ? $f['options'] : array()) as $o) {
        $options[] = array('value' => (string)$o['value'], 'label' => (string)($o['label'] !== '' ? $o['label'] : $o['value']),
                           'default' => !empty($o['default']));
    }
    if (empty($options)) {
        foreach ($paths as $p) {
            $box = _pg_lfc_at($root, $p);
            $v = (string)_pg_lfc_attr($box, 'value');
            if ($v !== '') $options[] = array('value' => $v, 'label' => $v, 'default' => _pg_lfc_attr($box, 'checked') !== null);
        }
    }

    // The range of siblings holding every box.
    if (count($paths) === 1) {
        $parent = array_slice($paths[0], 0, -1);
        $a = $b = end($paths[0]);
    } else {
        $parent = $paths[0];
        foreach ($paths as $p) {
            $k = 0;
            while ($k < count($parent) && $k < count($p) && $parent[$k] === $p[$k]) $k++;
            $parent = array_slice($parent, 0, $k);
        }
        $d = count($parent);
        $a = PHP_INT_MAX; $b = -1;
        foreach ($paths as $p) { $a = min($a, $p[$d]); $b = max($b, $p[$d]); }
    }

    // The label line: just before the range, or before a wrapper the range fills.
    $label = _pg_lfc_norm($f['label']);
    $is_label = function ($n) use ($label) {
        return $label !== '' && !_pg_lfc_has_control($n) && _pg_lfc_norm(_pg_cf_node_text($n)) === $label;
    };
    $label_path = null;
    $lp = $parent; $la = $a; $lb = $b;
    for ($up = 0; $up < 4; $up++) {
        $holder = _pg_lfc_at($root, $lp);
        if ($la > 0 && $is_label($holder['children'][$la - 1])) { $label_path = array_merge($lp, array($la - 1)); break; }
        if (empty($lp) || $la !== 0 || $lb !== count($holder['children']) - 1) break;
        $la = $lb = end($lp);
        $lp = array_slice($lp, 0, -1);
    }

    $name = (string)$f['name'];
    $kind = ($f['type'] === 'radio button') ? 'radio' : 'checkbox';
    $required = !empty($f['required']) && ($kind === 'radio' || count($options) === 1);
    $cf = _pg_lfc_cf($f, $page_id);
    $used = array();
    _pg_lfc_collect_ids($root['children'], $used);
    $stem = _pg_lfc_new_id($name, $used);

    $set = array(pg_di_mk('semantic', array('tag' => 'legend', 'text' => (string)$f['label'],
        'cssClass' => ($label_path !== null ? 'form-label fs-6' : 'visually-hidden'))));
    foreach ($options as $i => $o) {
        $oid = ($i === 0) ? $stem : _pg_lfc_new_id($stem . '_' . ($i + 1), $used);
        $attrs = array('type' => $kind, 'name' => $name, 'value' => $o['value']);
        if ($o['default']) $attrs['checked'] = '';
        if ($required) $attrs['required'] = '';
        $set[] = pg_di_mk('semantic', array('tag' => 'div', 'cssClass' => 'form-check'), array(
            pg_di_mk('semantic', array('tag' => 'input', 'cssClass' => 'form-check-input', 'id' => $oid,
                '_attrs' => _pg_lfc_mk_attrs($attrs), '_cf' => $cf)),
            pg_di_mk('semantic', array('tag' => 'label', 'cssClass' => 'form-check-label', 'text' => $o['label'],
                '_attrs' => _pg_lfc_mk_attrs(array('for' => $oid)))),
        ));
    }
    $fieldset = pg_di_mk('semantic', array('tag' => 'fieldset', 'cssClass' => 'mb-3'), $set);

    // The range first: the label line is an earlier sibling, so its path
    // does not move.
    $holder = &_pg_lfc_at($root, $parent);
    array_splice($holder['children'], $a, $b - $a + 1, array($fieldset));
    unset($holder);
    if ($label_path !== null) {
        $at = array_pop($label_path);
        $holder = &_pg_lfc_at($root, $label_path);
        array_splice($holder['children'], $at, 1);
        unset($holder);
    }
    return true;
}

function _pg_lfc_collect_ids($nodes, &$used)
{
    foreach ($nodes as $n) {
        if (!empty($n['props']['id'])) $used[(string)$n['props']['id']] = true;
        if (!empty($n['children'])) _pg_lfc_collect_ids($n['children'], $used);
    }
}

// Every other control of a field: its name, id, type and the row's rules.
function _pg_lfc_controls(&$nodes, $by_id, $page_id, &$used, &$moved, &$drawn)
{
    $types = array('email address' => 'email', 'date' => 'date', 'time' => 'time',
                   'date and time' => 'datetime-local', 'file upload' => 'file');
    foreach ($nodes as &$n) {
        $tag = _pg_lfc_tag($n);
        $fid = in_array($tag, array('input', 'select', 'textarea'), true) ? _pg_lfc_field_of($n) : 0;
        if ($fid > 0 && isset($by_id[$fid])) {
            $f = $by_id[$fid];
            $drawn[$fid] = true;
            _pg_lfc_set_attr($n, 'name', (string)$f['name']);
            $old = isset($n['props']['id']) ? (string)$n['props']['id'] : '';
            if ($old === '' || $old === (string)$fid) {
                $new = _pg_lfc_new_id($f['name'], $used);
                if ($old !== '') $moved[$old] = $new;
                $n['props']['id'] = $new;
            }
            if ($tag === 'input' && isset($types[$f['type']])) _pg_lfc_set_attr($n, 'type', $types[$f['type']]);
            if (!empty($f['required'])) _pg_lfc_set_attr($n, 'required', '');
            if (!empty($f['maxlength']) && $tag !== 'select') _pg_lfc_set_attr($n, 'maxlength', (int)$f['maxlength']);
            if (!empty($f['regex']) && $tag === 'input') _pg_lfc_set_attr($n, 'pattern', (string)$f['regex']);
            $default = (string)$f['default_value'];
            if ($tag === 'textarea') {
                if (!empty($f['rows'])) _pg_lfc_set_attr($n, 'rows', (int)$f['rows']);
                if ($default !== '' && empty($n['props']['text'])) $n['props']['text'] = $default;
            } elseif ($tag === 'input') {
                if ($default !== '' && _pg_lfc_attr($n, 'value') === null && ($f['type'] !== 'file upload')) {
                    // The date, date-and-time and time inputs hold ISO only.
                    if (isset($types[$f['type']]) && $f['type'] !== 'email address') {
                        $default = pg_native_date_value($default, $types[$f['type']]);
                    }
                    _pg_lfc_set_attr($n, 'value', $default);
                }
            } else {
                if (!empty($f['multiple'])) _pg_lfc_set_attr($n, 'multiple', '');
                if (empty($n['children'])) {
                    $n['children'] = array();
                    foreach ((array)$f['options'] as $o) {
                        $oa = array('value' => (string)$o['value']);
                        if (!empty($o['default'])) $oa['selected'] = '';
                        $n['children'][] = pg_di_mk('semantic', array('tag' => 'option',
                            'text' => (string)($o['label'] !== '' ? $o['label'] : $o['value']), '_attrs' => _pg_lfc_mk_attrs($oa)));
                    }
                }
            }
            $n['props']['_cf'] = _pg_lfc_cf($f, $page_id);
        }
        if (!empty($n['children'])) _pg_lfc_controls($n['children'], $by_id, $page_id, $used, $moved, $drawn);
    }
    unset($n);
}

// A label pointing at a control's old id follows it to the new one.
function _pg_lfc_labels(&$nodes, $moved)
{
    if (empty($moved)) return;
    foreach ($nodes as &$n) {
        if (_pg_lfc_tag($n) === 'label') {
            $for = _pg_lfc_attr($n, 'for');
            if ($for !== null && isset($moved[$for])) _pg_lfc_set_attr($n, 'for', $moved[$for]);
        }
        if (!empty($n['children'])) _pg_lfc_labels($n['children'], $moved);
    }
    unset($n);
}

function _pg_lfc_label_targets($nodes, &$for)
{
    foreach ($nodes as $n) {
        if (_pg_lfc_tag($n) === 'label') {
            $t = _pg_lfc_attr($n, 'for');
            if ($t !== null && $t !== '') $for[$t] = true;
        }
        if (!empty($n['children'])) _pg_lfc_label_targets($n['children'], $for);
    }
}

// A control no label element names keeps its row's label (`_cf.label`),
// rather than the placeholder the save would read.
function _pg_lfc_row_labels(&$nodes, $by_id, $for)
{
    foreach ($nodes as &$n) {
        if (!empty($n['props']['_cf']['ids']) && in_array(_pg_lfc_tag($n), array('input', 'select', 'textarea'), true)) {
            $t = strtolower((string)_pg_lfc_attr($n, 'type'));
            $id = isset($n['props']['id']) ? (string)$n['props']['id'] : '';
            $fid = (int)reset($n['props']['_cf']['ids']);
            if ($t !== 'radio' && $t !== 'checkbox' && !isset($for[$id]) && isset($by_id[$fid])
                && trim((string)$by_id[$fid]['label']) !== '') {
                $n['props']['_cf']['label'] = trim((string)$by_id[$fid]['label']);
            }
        }
        if (!empty($n['children'])) _pg_lfc_row_labels($n['children'], $by_id, $for);
    }
    unset($n);
}

// ── Page type layouts (pg_layout_convert()) ─────────────────────────────────

/**
 * The page types whose own layout file converts into their widget's design
 * elements by a piece map, in the order of pg_layout_piece_map().
 *
 * @return array
 */
function pg_layout_convert_types()
{
    return array('form item view', 'form list view', 'order form', 'photo gallery', 'catalog', 'catalog detail', 'shopping cart', 'express order', 'billing information', 'shipping address and arrival', 'shipping method', 'order preview', 'view order', 'login', 'forgot password', 'set password', 'change password', 'my account profile', 'email preferences', 'registration entrance', 'membership entrance', 'my account', 'update address book', 'search results');
}

/**
 * What each piece of a page type's data contract (the variables its view
 * template is given) becomes in the widget's tree. Null for a type with no
 * map yet. Keys are PHP sources; white space in them is not compared.
 *
 * 'echo': a printed value: 'none' (the widget draws it: messages, the
 * form's hidden fields), 'token:<name>' (the widget's value of that name,
 * bound where an element holds it alone), 'ctx:<key>' (markup the caller
 * reads from the page's settings, the header and footer), 'row' (the
 * record: the widget's row template), 'text:<words>' (those words, for a
 * control's name), 'form:<action>' (the attributes of a <form>: it is
 * bound to that action, or with 'strip' its tags go), 'section:<name>'
 * (the node bound to that section in its place), 'state:<key>' (the page
 * setting's words, pg_layout_convert_state()), 'drop:<code>' (no element:
 * reported with that code), 'unlink:<code>' (a link's address with no
 * element: the link goes, reported with that code), 'cut:<code>' (the form printing it goes, with
 * the wrappers around it that hold no other form: reported with that code).
 *
 * 'if': a condition: array('becomes' => 'widget' (the widget draws what it
 * holds: skipped), 'flag' (shown only under the widget's flag 'flag'),
 * 'empty' (the widget's empty state: its words become the empty message),
 * 'keep' (drawn as it is) or 'drop' (no element: reported), 'state' => the
 * page setting (pg_layout_convert_state()) without which it is never
 * drawn, 'drop_when' => array(setting => code): a setting under which it
 * holds a part the widget has not, reported with that code).
 *
 * A key 'elseif:<condition>' is read for that condition after an "else"
 * only, before the bare key. 'flag' may list flags: each nested in the
 * one before. 'section' (a block the widget fills with its own markup:
 * the node bound to that section stands in its place, what the block
 * holds is the widget's, unless 'hold' is true: then the block is read and
 * held inside the section's node, its first submit button bound to the
 * action 'button' names; 'drop' takes a 'code' to report it with; 'wrap' => markup holding it at '%s', 'inner' the
 * same inside its flags, 'flag' as for 'token'). A 'flag' inside a tag's
 * attributes is dropped, or with 'in_tag' => 'keep' drawn always.
 * 'token' (a block the widget prints whole as the
 * value 'token': an element holding it stands in its place, under 'flag'
 * when one is given).
 *
 * 'else': the flag the "otherwise" branch of a condition shows under,
 * 'keep' for one drawn as it is, 'widget' for one the widget draws,
 * 'unless' for one drawn as it is only when the condition's own branch is
 * never drawn (a page setting decides between the two).
 * 'loop': a foreach: 'list' (the widget's list, its body the row's
 * wrapper), or 'list:<row flag>' (one of the kinds of records the
 * widget's one list holds: its body is shown in the rows the flag holds
 * for; the loops of a map's kinds side by side are that one list), or
 * 'section:<name>' (a list the widget draws as that section: the node bound
 * to it stands in the place of the list holding the loop; a second loop
 * on the same section writes nothing: the widget draws it once), or
 * 'token:<name>' (rows the widget prints whole as that value: an element
 * holding it stands in the loop's place, the body is not read), or 'keep'
 * (a grouping the widget's list does not have: the body is read once),
 * 'widget' (rows the widget adds itself: the body is not read), or
 * 'source:<list>' (one of the widget's lists: its body is the list's row).
 *
 * Optional: 'forms' => 'strip' (the layout's <form> tags go: the widget
 * draws its own form around the tree), 'flag_list' => true (a flag may
 * cover the list: the widget has no empty message in its place),
 * 'controls' => array(name in the layout => name the widget looks for,
 * '' for none, 'bind:<value>' for the control bound to that value,
 * 'action:<action>' for the button bound to that action, 'eo:<field>' for
 * the field the express order binds; a key '#<id>' names a control with
 * no name; an array('name' => …, 'value' => …) gives the control that
 * name and value),
 * 'submits' => array(token => control name: a link to that address
 * becomes a submit button of that name), 'media' => array(token => flag:
 * the wrapper an image of that address alone fills is shown under the
 * flag), 'cfg_bound' => array(action => settings the widget needs while a
 * node is bound to it), 'opposite' => array(flag => the flag of its
 * opposite: a line of text holding words under the flag is drawn twice),
 * 'record' => true (the widget shows one record and the whole tree is
 * it: no list is added), 'cross_sell' => the id of the classic cross-sell
 * container (its element becomes the widget's cross-sell part), 'lists'
 * => true (every 'list' loop is a place of the widget's list, its rows
 * told apart by row flags, a 'list:<row flag>' loop's body under that
 * flag; otherwise a second one is dropped), 'scripts'
 * => 'keep' (a <script> of the layout is kept as a raw block), 'replace'
 * => array(text => text) (put in the markup before it is read),
 * 'page_links' => array(token => page setting) (a link bound to the token
 * is bound to the page the setting names instead: '__page_link'),
 * 'when' => array(flag => array('tokens' => list, 'op' => operator, 'value'
 * => operand)) (a flag of the map that is no widget flag: what it covers is
 * shown only when a value passes the test, show_if; several tokens are
 * joined by 'any', at most three).
 *
 * @param string $type
 * @return array|null
 */
function pg_layout_piece_map($type)
{
    $none = array('$messages' => 'none', '$system' => 'none', '$attributes' => 'none');
    switch ((string)$type) {
        case 'form list view':
            return array(
                'echo' => $none + array(
                    '$header' => 'ctx:header',
                    '$footer' => 'ctx:footer',
                    "\$form['content']" => 'row',
                    'number_format($total_number_of_forms)' => 'token:__result_count',
                    'pg_format_number($total_number_of_forms, 0)' => 'token:__result_count',
                ),
                'if' => array(
                    // The browse and search form: the widget's search box,
                    // shown when its search is on. A browse it has not.
                    '$browse or $search' => array('becomes' => 'widget', 'state' => array('browse', 'search'), 'drop_when' => array('browse' => 'browse')),
                    // Words asking to browse or search first, while the
                    // list waits for it: the widget always lists.
                    '$browse_or_search_required' => array('becomes' => 'drop', 'state' => array('required')),
                    '$number_of_forms == 0' => array('becomes' => 'empty'),
                    // "Found N results" after a search: no widget flag says
                    // a search is in force.
                    '$browse_active or $search_active' => array('becomes' => 'drop', 'state' => array('browse', 'search')),
                    '$number_of_pages > 1' => array('becomes' => 'widget'),
                ),
                'else' => array('$number_of_forms == 0' => 'has_records'),
                'loop' => array('$forms as $form' => 'list'),
            );

        case 'order form':
            return _pg_lc_order_form_map($none);

        case 'photo gallery':
            return _pg_lc_photo_gallery_map($none);

        case 'catalog':
            return _pg_lc_catalog_map($none);

        case 'catalog detail':
            return _pg_lc_catalog_detail_map($none);

        case 'shopping cart':
            return _pg_lc_cart_map($none);

        case 'express order':
            return _pg_lc_express_order_map($none);

        case 'billing information':
            return _pg_lc_billing_map($none);

        case 'shipping address and arrival':
            return _pg_lc_shipping_address_map($none);

        case 'shipping method':
            return _pg_lc_shipping_method_map($none);

        case 'order preview':
            return _pg_lc_order_preview_map($none);

        case 'view order':
            return _pg_lc_view_order_map($none);

        case 'login':
        case 'forgot password':
        case 'set password':
        case 'change password':
            return _pg_lc_password_map($type, $none);

        case 'my account profile':
        case 'email preferences':
            return _pg_lc_account_form_map($type, $none);

        case 'registration entrance':
        case 'membership entrance':
            return _pg_lc_entrance_map($type, $none);

        case 'update address book':
            return _pg_lc_address_book_map($none);

        case 'my account':
            return _pg_lc_my_account_map($none);

        case 'search results':
            return _pg_lc_search_results_map($none);

        case 'form item view':
            return array(
                'echo' => $none + array(
                    '$content' => 'row',
                    'h($auto_registration_email_address)' => 'token:__new_account_email',
                    'h($auto_registration_set_password_url)' => 'token:__new_account_set_password_url',
                    'h($edit_button_url)' => 'token:__edit_url',
                ),
                'if' => array(
                    '$auto_registration' => array('becomes' => 'flag', 'flag' => 'has_new_account'),
                    '$auto_registration_set_password_url' => array('becomes' => 'flag', 'flag' => 'has_set_password_link'),
                    '$edit_button_url' => array('becomes' => 'flag', 'flag' => 'can_edit'),
                ),
                'else' => array(),
                'loop' => array(),
            );
    }
    return null;
}

// The order form (widgets_order_form.php): the products loop is the
// widget's list with the layout's own row, a product's values its row
// tokens and its conditions its row flags (pg_of_row_flags()); the payment
// schedule the widget's section; the rest under pg_of_flags().
function _pg_lc_order_form_map($none)
{
    return array(
        'echo' => $none + array(
            '$currency_attributes' => 'none',
            '$currency_system' => 'none',
            // The edit button of edit mode: the designer's own.
            "\$product['edit']" => 'none',
            "\$product['id']" => 'token:__id',
            "h(\$product['name'])" => 'token:__name',
            "h(\$product['short_description'])" => 'token:__short_description',
            // The classic page adds the out-of-stock words to the description.
            "\$product['full_description']" => 'token:__description,__out_of_stock_message',
            "h(\$product['image_url'])" => 'token:__image_url',
            "\$product['price_info']" => 'token:__price_formatted',
            '$currency_symbol' => 'token:__currency_symbol',
            'h($currency_code)' => 'token:__currency_code',
            'h($add_button_label)' => 'token:__add_button_label',
            'h($skip_button_label)' => 'token:__skip_button_label',
            'h($skip_button_url)' => 'submit:__skip_button',
        ),
        'if' => array(
            '$available_products' => array('becomes' => 'flag', 'flag' => 'has_products'),
            // No product to order: the skip button alone.
            'elseif:$skip_button_url' => array('becomes' => 'flag', 'flag' => array('no_products', 'has_skip_button')),
            '$skip_button_url' => array('becomes' => 'flag', 'flag' => 'has_skip_button'),
            "\$product_layout == 'list'" => array('becomes' => 'flag', 'flag' => 'is_list_layout'),
            '$available_donations and !$available_non_donations' => array('becomes' => 'flag', 'flag' => 'only_donations'),
            '$checkbox_selections and $quantity_selections' => array('becomes' => 'flag', 'flag' => 'selects_by_checkbox_and_quantity'),
            'elseif:$checkbox_selections' => array('becomes' => 'flag', 'flag' => 'selects_by_checkbox_only'),
            'elseif:$quantity_selections' => array('becomes' => 'flag', 'flag' => 'selects_by_quantity_only'),
            // The image and its column: the wrapper the image alone fills
            // is shown under order_form_has_image ('media').
            "\$product['image_url']" => array('becomes' => 'keep'),
            "\$product['recurring_schedule']" => array('becomes' => 'section', 'section' => 'recurring_schedule'),
            // Every control a row draws is under its selection type's
            // flag, which holds only while the product can be ordered.
            "\$product['available']" => array('becomes' => 'keep'),
            "\$product['selection_type'] == 'donation'" => array('becomes' => 'flag', 'flag' => 'order_form_donation'),
            "\$product['selection_type'] == 'checkbox'" => array('becomes' => 'flag', 'flag' => 'order_form_checkbox'),
            "\$product['selection_type'] == 'quantity'" => array('becomes' => 'flag', 'flag' => 'order_form_quantity'),
            '$currency_code' => array('becomes' => 'flag', 'flag' => 'has_currency_code'),
            '$recipient' => array('becomes' => 'flag', 'flag' => 'has_recipient_choice'),
            '$quantity' => array('becomes' => 'flag', 'flag' => 'has_quantity'),
            '$currency' => array('becomes' => 'flag', 'flag' => 'has_currency_choice'),
        ),
        'else' => array(
            "\$product_layout == 'list'" => 'is_dropdown_layout',
            '$available_donations and !$available_non_donations' => 'not_only_donations',
            // A donation row's price is empty: the price drawn as it is.
            "\$product['selection_type'] == 'donation'" => 'keep',
        ),
        'loop' => array('$products as $product' => 'list'),
        'forms' => 'strip',
        'flag_list' => true,
        'controls' => array('product_^^__id^^' => 'product', 'donation_^^__id^^' => 'donation'),
        'submits' => array('__skip_button' => 'submit_skip'),
        'media' => array('__image_url' => 'order_form_has_image'),
    );
}

// The photo gallery (widgets_photo_gallery.php): the widget's one list
// holds the albums of the folder shown, then its photos; the layout's
// albums loop and photos loop are that list, each body shown in its kind's
// rows (pg_gal_row_flags()). The rest under pg_gal_flags().
function _pg_lc_photo_gallery_map($none)
{
    return array(
        'echo' => $none + array(
            'h($album_name)' => 'token:__album_name',
            'h($back_button_url)' => 'token:__back_url',
            "h(\$album['name'])" => 'token:__gallery_item_name',
            "h(\$album['url'])" => 'token:__gallery_item_url',
            "h(\$album['image_url'])" => 'token:__gallery_item_image_url',
            "number_format(\$album['number_of_photos'])" => 'token:__gallery_item_photo_count',
            "pg_format_number(\$album['number_of_photos'], 0)" => 'token:__gallery_item_photo_count',
            "h((\$album['number_of_photos'] > 1) ? lang(array('string' => '{var:1} Photos', 'vars' => array(pg_format_number(\$album['number_of_photos'], 0)))) : lang(array('string' => '{var:1} Photo', 'vars' => array(pg_format_number(\$album['number_of_photos'], 0)))))" => 'token:__gallery_item_count_label',
            // A photo's address is its picture's too.
            "h(\$photo['url'])" => 'token:__gallery_item_url',
            "h(\$photo['name'])" => 'token:__gallery_item_name',
            "h(\$photo['description'])" => 'token:__gallery_item_description',
        ),
        'if' => array(
            // A branch holding only its kind's loop is that list's rows.
            '$albums' => array('becomes' => 'flag', 'flag' => 'has_albums'),
            '$photos' => array('becomes' => 'flag', 'flag' => 'has_photos'),
            '!$photos' => array('becomes' => 'flag', 'flag' => 'no_photos'),
            '!$albums and $photos' => array('becomes' => 'flag', 'flag' => 'only_photos'),
            '!$albums and !$photos' => array('becomes' => 'flag', 'flag' => 'no_gallery_items'),
            '$back_button_url' => array('becomes' => 'flag', 'flag' => 'in_album'),
            "\$album['number_of_photos'] > 1" => array('becomes' => 'flag', 'flag' => 'gallery_item_many_photos'),
        ),
        'else' => array(
            '$back_button_url' => 'not_in_album',
            '!$photos' => 'has_photos',
            '!$albums and !$photos' => 'has_gallery_items',
        ),
        'loop' => array(
            '$albums as $album' => 'list:gallery_item_is_album',
            '$photos as $photo' => 'list:gallery_item_is_photo',
        ),
        'flag_list' => true,
    );
}

// The catalog (widgets_catalog.php): the items loop is the listing's list
// with the layout's own row, an item's values its row tokens and its
// conditions its row flags (pg_catalog_row_flags()); the categories the
// widget's group tree; the search form its bound search form; the rest
// under pg_catalog_listing_flags().
function _pg_lc_catalog_map($none)
{
    $count = 'token:__result_count';
    return array(
        'echo' => $none + array(
            '$search_attributes' => 'form:search_form',
            '$search_system' => 'none',
            '$currency_attributes' => 'form:strip',
            '$currency_system' => 'none',
            '$page_id' => 'text:page',
            // The edit grids of edit mode: the designer's own.
            '$edit_start' => 'none',
            '$edit_end' => 'none',
            "\$item['edit_start']" => 'none',
            "\$item['edit_end']" => 'none',
            '$full_description' => 'token:__active_cat_description',
            '$code' => 'token:__active_cat_code',
            'h($query)' => 'token:__search_query',
            'number_format($number_of_items)' => $count,
            'pg_format_number($number_of_items, 0)' => $count,
            "h(\$item['url'])" => 'token:__detail_url',
            "h(\$item['image_url'])" => 'token:__image_url',
            "h(\$item['short_description'])" => 'token:__short_description',
            "\$item['price_info']" => 'token:__price_formatted',
        ),
        'if' => array(
            '$items' => array('becomes' => 'flag', 'flag' => 'has_items'),
            "\$mode == 'search'" => array('becomes' => 'flag', 'flag' => 'has_query'),
            "\$mode == 'browse'" => array('becomes' => 'flag', 'flag' => 'no_query'),
            "\$query != ''" => array('becomes' => 'flag', 'flag' => 'has_query'),
            '$number_of_items > 1' => array('becomes' => 'flag', 'flag' => 'many_results'),
            '$currency' => array('becomes' => 'flag', 'flag' => 'has_currency_choice'),
            '!$currency' => array('becomes' => 'flag', 'flag' => 'no_currency_choice'),
            // A link around the picture and the words, opened and closed in
            // two blocks: every row has its address.
            "\$item['url']" => array('becomes' => 'keep'),
            "\$item['image_url']" => array('becomes' => 'flag', 'flag' => 'catalog_item_has_image'),
            "\$item['short_description']" => array('becomes' => 'flag', 'flag' => 'catalog_item_has_short_description'),
            "\$item['price_info']" => array('becomes' => 'flag', 'flag' => 'catalog_item_has_price'),
        ),
        'else' => array(
            '$items' => 'no_items',
            "\$mode == 'browse'" => 'has_query',
            '!$currency' => 'has_currency_choice',
        ),
        'loop' => array(
            '$items as $item' => 'list',
            '$product_groups as $product_group' => 'section:group_tree',
        ),
        'flag_list' => true,
        // "product<s>" in a line of text: the line once for each.
        'opposite' => array('many_results' => 'one_result'),
        // The search box, its button and the clear button (a second, empty
        // search text, which the address reads last).
        'controls' => array('page_query' => 'bind:search_query', 'page_submit' => '', 'page_clear' => 'query'),
        'cfg_bound' => array('search_form' => array('search_enabled' => true)),
    );
}

// The catalog detail (widgets_catalog.php, catalog_item_view): one product,
// the whole tree its record; its values the product's tokens, its
// conditions the flags of pg_civ_layout_flags(); the attributes the
// widget's variant picker, the products of a group without attributes its
// product lines and pick list. The add-to-cart form is the widget's own.
function _pg_lc_catalog_detail_map($none)
{
    $line = 'token:__product_line';
    return array(
        'echo' => $none + array(
            '$form_attributes' => 'form:strip',
            // The attribute script of the classic page: the variant
            // picker's own script stands for it.
            '$footer_system' => 'none',
            'h($image_url)' => 'token:__image_url',
            '$code' => 'token:__code',
            'h($short_description)' => 'token:__short_description',
            '$full_description' => 'token:__description',
            '$price_info' => 'token:__price_formatted',
            '$details' => 'token:__details',
            'h($add_button_label)' => 'token:__add_button_label',
            'h($back_button_url)' => 'token:__back_url',
            'h($back_button_label)' => 'token:__back_label',
            "\$products[0]['description']" => $line,
            "\$product['description']" => $line,
            // The sidebar: the search form (bound, the widget prints the
            // form after its own), the currency form's tags (its select is
            // the widget's currency picker).
            '$search_attributes' => 'form:search_form',
            "\$catalog_page['id']" => 'text:page',
            '$currency_attributes' => 'form:strip',
            '$currency_system' => 'none',
            // The classic cross-sell script (the HTML converter leaves the
            // script out): its container is the widget's cross-sell part.
            'json_encode($page_id)' => 'none',
        ),
        'if' => array(
            '$available_products' => array('becomes' => 'flag', 'flag' => 'has_products'),
            '$product_row' => array('becomes' => 'flag', 'flag' => 'no_variant_choice'),
            '$product_pick_list' => array('becomes' => 'flag', 'flag' => 'has_product_choice'),
            'count($products) == 1' => array('becomes' => 'flag', 'flag' => 'one_product'),
            '$recipient' => array('becomes' => 'flag', 'flag' => 'has_recipient_choice'),
            // The widget takes a quantity for every product it can add.
            '$quantity' => array('becomes' => 'keep'),
            '$back_button_url' => array('becomes' => 'flag', 'flag' => 'has_back_button'),
            '$keywords' => array('becomes' => 'flag', 'flag' => 'has_keywords'),
            '$currency' => array('becomes' => 'flag', 'flag' => 'has_currency_choice'),
            "\$item['type'] == 'product group'" => array('becomes' => 'widget'),
        ),
        'else' => array(
            '$available_products' => 'no_products',
            '$product_pick_list' => 'no_product_choice',
            'count($products) == 1' => 'many_products',
            "\$item['type'] == 'product group'" => 'widget',
        ),
        'loop' => array(
            '$attributes as $attribute' => 'token:__variant_picker',
            '$products as $product' => 'section:product_lines',
            '$product_groups as $product_group' => 'section:group_tree',
            '$keywords as $keyword' => 'section:keywords',
        ),
        // The pick list of a group's products, the recipient picker and the
        // quantity: the widget fills each by its binding. The new
        // recipient's name keeps its plain field, shown as on the classic
        // page (bound, the widget would hide it under its label).
        'controls' => array(
            'product_id' => 'bind:product_choice',
            'ship_to' => 'bind:recipient_select',
            'quantity' => 'bind:quantity',
            'page_query' => 'bind:search_query',
            'page_submit' => '',
        ),
        'record' => true,
        'cross_sell' => 'cross-sell',
    );
}

// The shopping cart (widgets_cart.php): the items of every recipient are
// the widget's one list, drawn in each of the layout's two tables (today's
// charges and the recurring charges, 'lists'), each row shown in a table
// by its row flags (pg_cart_row_flags()); the recipients loop is read once.
// The blocks the widget prints whole (the offers, the quick add box, a
// row's gift card, product form and schedule fields, the offer code box)
// are its values or sections; the rest under pg_cart_layout_flags().
function _pg_lc_cart_map($none)
{
    $qty = 'token:__item_qty';
    $recurring_only = array('cart_item_recurring_only');
    return array(
        'echo' => $none + array(
            '$currency_attributes' => 'form:strip',
            '$currency_system' => 'none',
            "\$item['id']" => 'token:__item_id',
            "h(\$item['name'])" => 'token:__item_name',
            "h(\$item['short_description'])" => 'token:__item_short_description',
            "\$item['full_description']" => 'token:__item_description',
            "h(\$item['image_url'])" => 'token:__item_image',
            "\$item['out_of_stock_message']" => 'token:__item_out_of_stock_message',
            "h(\$item['calendar_event']['name'])" => 'token:__item_calendar_event_name',
            "\$item['calendar_event']['date_and_time_range']" => 'token:__item_calendar_event_dates',
            "number_format(\$item['quantity'])" => $qty,
            "pg_format_number(\$item['quantity'], 0)" => $qty,
            "\$item['price_info']" => 'token:__item_price',
            "\$item['amount_info']" => 'token:__item_total',
            "h(\$item['payment_period'])" => 'token:__item_recurring_period',
            "h(\$item['remove_url'])" => 'token:__item_remove_url',
            '$currency_symbol' => 'token:__currency_symbol',
            'h($currency_code)' => 'token:__currency_code',
            '$subtotal_info' => 'token:__cart_subtotal',
            '$discount_info' => 'token:__cart_discount',
            '$total_info' => 'token:__cart_total',
            'h($shopping_cart_label)' => 'token:__shopping_cart_label',
            'h($update_button_label)' => 'token:__update_button_label',
            'h($checkout_button_label)' => 'token:__checkout_button_label',
            'h($retrieve_order_url)' => 'token:__retrieve_order_url',
        ),
        'if' => array(
            // The offers, the quick add box and the currency form carry
            // their own forms: the widget prints each whole.
            '$number_of_special_offers' => array('becomes' => 'section', 'section' => 'pending_offers'),
            '$quick_add' => array('becomes' => 'section', 'section' => 'quick_add'),
            '$currency and $recipients' => array('becomes' => 'token', 'token' => '__currency_selector'),
            '$currency' => array('becomes' => 'token', 'token' => '__currency_selector'),
            '!$recipients' => array('becomes' => 'empty'),
            '$recurring_items' => array('becomes' => 'flag', 'flag' => 'has_recurring_products'),
            // Opens the items' column in one block and closes it in another:
            // both kept, an empty table shows no row.
            '$nonrecurring_items' => array('becomes' => 'keep'),
            "\$recipient['in_nonrecurring']" => array('becomes' => 'flag', 'flag' => 'has_today_items'),
            "\$recipient['in_recurring']" => array('becomes' => 'flag', 'flag' => 'has_recurring_products'),
            // A heading per recipient: the widget's list is not grouped.
            "\$recipient['ship_to_heading']" => array('becomes' => 'widget', 'drop_when' => array('multi_recipient' => 'recipients')),
            "\$recipient['non_donations_in_nonrecurring']" => array('becomes' => 'flag', 'flag' => 'has_today_non_donations'),
            "\$recipient['non_donations_in_recurring']" => array('becomes' => 'flag', 'flag' => 'has_recurring_non_donations'),
            "\$product_description_type == 'full_description'" => array('becomes' => 'keep', 'state' => array('full_description')),
            "\$item['in_nonrecurring']" => array('becomes' => 'flag', 'flag' => 'cart_item_today'),
            "\$item['in_recurring']" => array('becomes' => 'flag', 'flag' => 'cart_item_recurring'),
            // The image opens a row of two columns and a second block closes
            // it: both kept, the image's column shown under its row flag.
            "\$item['image_url']" => array('becomes' => 'keep'),
            "(\$product_description_type == 'full_description') and (\$item['image_url'])" => array('becomes' => 'keep', 'state' => array('full_description')),
            "\$item['image_url'] and (\$product_description_type == 'full_description')" => array('becomes' => 'keep', 'state' => array('full_description')),
            "\$item['show_out_of_stock_message']" => array('becomes' => 'flag', 'flag' => 'cart_item_out_of_stock'),
            "\$item['calendar_event']" => array('becomes' => 'flag', 'flag' => 'cart_item_has_calendar_event'),
            "\$item['recurring_schedule']" => array('becomes' => 'token', 'token' => '__item_recurring_schedule_html'),
            "\$item['recurring_schedule'] and !\$item['in_nonrecurring']" => array('becomes' => 'token', 'token' => '__item_recurring_schedule_html', 'flag' => $recurring_only),
            "\$item['gift_card']" => array('becomes' => 'token', 'token' => '__item_gift_card_html'),
            "\$item['form']" => array('becomes' => 'token', 'token' => '__item_form_data_html'),
            "\$item['form'] and !\$item['in_nonrecurring']" => array('becomes' => 'token', 'token' => '__item_form_data_html', 'flag' => $recurring_only),
            "\$item['selection_type'] != 'donation'" => array('becomes' => 'flag', 'flag' => 'cart_item_not_donation'),
            "\$item['selection_type'] == 'donation'" => array('becomes' => 'flag', 'flag' => 'cart_item_donation'),
            "\$item['added_by_offer']" => array('becomes' => 'flag', 'flag' => 'cart_item_added_by_offer'),
            "\$item['added_by_offer'] or \$item['in_nonrecurring']" => array('becomes' => 'flag', 'flag' => 'cart_item_recurring_quantity_fixed'),
            "(\$item['selection_type'] == 'donation') and !\$item['in_nonrecurring']" => array('becomes' => 'flag', 'flag' => 'cart_item_recurring_donation'),
            "!\$item['in_nonrecurring']" => array('becomes' => 'flag', 'flag' => $recurring_only),
            '$currency_code' => array('becomes' => 'flag', 'flag' => 'has_currency_code'),
            '$discount_info' => array('becomes' => 'flag', 'flag' => 'has_discount'),
            // The widget's notice names what checkout still adds.
            '$taxable_items or $shippable_items' => array('becomes' => 'token', 'token' => '__tax_shipping_notice'),
            '$show_special_offer_code' => array('becomes' => 'token', 'token' => '__special_offer_code_form', 'flag' => array('has_special_offer_code')),
            '$applied_offers' => array('becomes' => 'section', 'section' => 'applied_offers'),
            '$offline_payment_allowed' => array('becomes' => 'token', 'token' => '__offline_payment_checkbox'),
        ),
        'else' => array(
            // An empty cart: the widget's own empty state, the quick add box
            // after it.
            '!$recipients' => 'keep',
            "\$product_description_type == 'full_description'" => 'unless',
            "\$item['selection_type'] == 'donation'" => 'cart_item_not_donation',
            "\$item['added_by_offer']" => 'cart_item_quantity_editable',
            "\$item['added_by_offer'] or \$item['in_nonrecurring']" => 'cart_item_recurring_quantity_editable',
            "(\$item['selection_type'] == 'donation') and !\$item['in_nonrecurring']" => 'cart_item_recurring_amount',
        ),
        'loop' => array(
            '$recipients as $recipient' => 'keep',
            "\$recipient['items'] as \$item" => 'list',
            '$payment_periods as $payment_period' => 'section:payment_periods',
        ),
        'lists' => true,
        'forms' => 'strip',
        // The classic cross-sell script, as the page ran it.
        'scripts' => 'keep',
        // The quantity and a donation's amount: the widget names and fills
        // the box per row (a donation's becomes its amount box).
        'controls' => array(
            'quantity[^^__item_id^^]' => 'bind:cart_qty',
            'donations[^^__item_id^^]' => 'bind:cart_qty',
            'submit_update' => 'action:cart_update',
            'submit_checkout' => 'action:cart_checkout',
        ),
        'media' => array('__item_image' => 'cart_item_has_image'),
    );
}

// The express order (_render_system_widget_express_order()): its cart part
// is the shopping cart's (the items of every recipient are the widget's one
// list, drawn in today's and the recurring table by row flags), the rest
// the theme's own markup posting to the classic handler under the widget's
// flags, but for the blocks the widget prints whole as its sections: the
// shipping of each recipient, the payment methods, the terms, the applied
// offers, the recurring totals and the messages.
function _pg_lc_express_order_map($none)
{
    $map = _pg_lc_cart_map($none);
    $map['echo'] = array(
        '$messages' => 'section:eo_errors_notices',
        '$pending_system' => 'none',
        '$base_currency_total_info' => 'token:__cart_total_charged',
        '$base_currency_total_with_surcharge_info' => 'token:__cart_total_charged',
        '$tax_info' => 'token:__cart_tax',
        '$shipping_info' => 'token:__cart_shipping',
        '$gift_card_discount_info' => 'token:__cart_gift_card_discount',
        '$surcharge_info' => 'token:__cart_surcharge',
        '$total_with_surcharge_info' => 'token:__cart_total_with_surcharge',
        'h($purchase_now_button_label)' => 'token:__purchase_now_button_label',
        'h($special_offer_code_label)' => 'token:__special_offer_code_label',
        'h($special_offer_code_message)' => 'token:__special_offer_code_message',
        'h($custom_field_1_label)' => 'state:custom_field_1_label',
        'h($custom_field_2_label)' => 'state:custom_field_2_label',
        'h($opt_in_label)' => 'token:__opt_in_label',
        'h($tax_exempt_label)' => 'token:__tax_exempt_label',
        "h(\$gift_card['protected_code'])" => 'token:__gift_card_code',
        "\$gift_card['remaining_balance_info']" => 'token:__gift_card_balance',
        "h(\$gift_card['remove_url'])" => 'token:__gift_card_remove_url',
    ) + $map['echo'];
    // The express order's values carry their own minus sign.
    $map['replace'] = array('-^^__cart_discount^^' => '^^__cart_discount^^', '-^^__cart_gift_card_discount^^' => '^^__cart_gift_card_discount^^');

    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    $keep = array('becomes' => 'keep');
    $widget = array('becomes' => 'widget');
    unset($map['if']['$taxable_items or $shippable_items']);
    $map['if'] = array(
        // Offers the widget lists above its form itself.
        '$number_of_special_offers' => $widget,
        '$quick_add' => array('becomes' => 'widget', 'drop_when' => array('quick_add' => 'quick_add')),
        '!$recipients' => $widget,
        "ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'" => array('becomes' => 'widget', 'drop_when' => array('multi_recipient' => 'recipients')),
        // The address, arrival date and method of every recipient.
        "\$recipient['shipping']" => array('becomes' => 'section', 'section' => 'eo_shipping', 'flag' => array('has_shipping'), 'wrap' => '<tr><td colspan="6">%s</td></tr>'),
        // A shipped recipient's fee: in the totals' shipping line.
        "\$recipient['shipping'] and \$recipient['complete']" => $widget,
        '$show_subtotal' => $flag('has_subtotal_line'),
        '$tax_info' => $flag('has_tax'),
        '$shipping_info' => $flag('has_shipping_cost'),
        '$gift_card_discount_info' => $flag('has_gift_card'),
        '$number_of_applied_gift_cards > 1' => $keep,
        '$show_surcharge' => $flag('has_surcharge'),
        '$base_currency_total_info' => $flag('has_foreign_currency'),
        '$base_currency_total_with_surcharge_info' => $flag('has_foreign_currency'),
        '$total_disclaimer' => array('becomes' => 'token', 'token' => '__currency_disclaimer', 'flag' => array('has_foreign_currency')),
        '$show_special_offer_code' => $flag('has_special_offer_code'),
        '$special_offer_code_label' => $keep,
        '$special_offer_code_message' => $keep,
        '$applied_offers' => array('becomes' => 'section', 'section' => 'eo_applied_offers'),
        '$paypal_express_checkout' => $widget,
        '$purchase_now_button' => $keep,
        '$purchase_now_button and !$recurring_items' => $flag('has_no_recurring_products'),
        '$billing_same_as_shipping' => $flag('has_single_recipient'),
        '$custom_field_1 OR $custom_field_2' => array('becomes' => 'keep', 'state' => array('custom_field_1', 'custom_field_2')),
        '$custom_field_1' => array('becomes' => 'keep', 'state' => array('custom_field_1')),
        '$custom_field_2' => array('becomes' => 'keep', 'state' => array('custom_field_2')),
        '$custom_field_1_required' => array('becomes' => 'keep', 'state' => array('custom_field_1_required')),
        '$custom_field_2_required' => array('becomes' => 'keep', 'state' => array('custom_field_2_required')),
        '$opt_in' => $keep,
        '$opt_in_sms' => $flag('shows_opt_in_sms'),
        '$po_number' => array('becomes' => 'keep', 'state' => array('po_number')),
        '$referral_source' => $flag('has_referral_sources'),
        '$update_contact' => $flag('can_update_contact'),
        '$tax_exempt' => $flag('tax_exempt_allowed'),
        // The page's own billing form: the widget has none.
        '$custom_billing_form' => array('becomes' => 'widget', 'drop_when' => array('billing_form' => 'billing_form')),
        '$applied_gift_cards' => $flag('has_gift_card'),
        '$payment' => $flag('has_payment'),
        '$gift_card_code' => $flag('has_gift_card_code'),
        // Every payment method, the card's fields and the gateway's extras.
        '$credit_debit_card' => array('becomes' => 'section', 'section' => 'eo_payment_methods'),
        '$offline_payment' => $widget,
        '$offline_payment_allowed' => $widget,
        '$terms_url' => array('becomes' => 'section', 'section' => 'eo_terms'),
    ) + $map['if'];
    $map['else']['!$recipients'] = 'keep';
    $map['else']['$number_of_applied_gift_cards > 1'] = 'widget';
    $map['loop'] = array(
        '$payment_periods as $payment_period' => 'section:eo_payment_periods',
        '$applied_gift_cards as $gift_card' => 'source:checkout.gift_cards',
    ) + $map['loop'];
    // The rows' boxes are named as the express order names them (a
    // donation's becomes its amount box); the buttons and the billing
    // fields the widget fills are bound to it.
    $qty = array('name' => 'quantity[^^__item_id^^]', 'value' => '^^__item_qty^^');
    $map['controls'] = array(
        'quantity[^^__item_id^^]' => $qty,
        'donations[^^__item_id^^]' => $qty,
        'submit_update' => 'action:eo_submit_update',
        'submit_purchase_now' => 'action:eo_submit_purchase',
        '#billing_same_as_shipping' => array('name' => 'billing_same_as_shipping', 'value' => '1'),
        'billing_salutation' => 'eo:billing_salutation',
        'billing_country' => 'eo:billing_country',
    );
    return $map;
}

// The billing information step (widgets_billing_information.php): one
// record, its controls by the classic page's names, each optional part
// under pg_bi_flags(); the widget draws its own form around the tree.
function _pg_lc_billing_map($none)
{
    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    return array(
        'echo' => $none + array(
            '$edit_start' => 'none',
            '$edit_end' => 'none',
            'h($custom_field_1_label)' => 'token:__custom_field_1_label',
            'h($custom_field_2_label)' => 'token:__custom_field_2_label',
            'h($opt_in_label)' => 'token:__opt_in_label',
            'h($tax_exempt_label)' => 'token:__tax_exempt_label',
            'h($submit_button_label)' => 'state:submit_button_label',
        ),
        'if' => array(
            '$custom_field_1 OR $custom_field_2' => $flag('has_custom_fields'),
            '$custom_field_1' => $flag('has_custom_field_1'),
            '$custom_field_2' => $flag('has_custom_field_2'),
            // The required mark: the page's setting, as the widget's own.
            '$custom_field_1_required' => array('becomes' => 'keep', 'state' => array('custom_field_1_required')),
            '$custom_field_2_required' => array('becomes' => 'keep', 'state' => array('custom_field_2_required')),
            '$opt_in' => $flag('shows_opt_in'),
            '$opt_in_sms' => $flag('shows_opt_in_sms'),
            '$update_contact' => $flag('can_update_contact'),
            '$po_number' => $flag('has_po_number'),
            '$referral_source' => $flag('has_referral_sources'),
            '$tax_exempt' => $flag('has_tax_exempt'),
            '$custom_billing_form' => array('becomes' => 'section', 'section' => 'custom_billing_form', 'flag' => array('has_custom_form'),
                'inner' => '<h2>^^__custom_form_title^^</h2>%s'),
        ),
        'else' => array(),
        'loop' => array(),
        'forms' => 'strip',
        'record' => true,
    );
}

// The shipping address and arrival step (widgets_shipping_address.php):
// the recipient's address by the classic page's control names, the arrival
// dates the widget's list (a custom day's row under arrival_is_custom), the
// rest under pg_sa_flags().
function _pg_lc_shipping_address_map($none)
{
    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    return array(
        'echo' => $none + array(
            '$edit_start' => 'none',
            '$edit_end' => 'none',
            'h($ship_to_name)' => 'token:__recipient_name',
            'h($address_type_url)' => 'token:__address_type_url',
            "\$arrival_date['id']" => 'token:__arrival_date_id',
            "h(\$arrival_date['name'])" => 'token:__arrival_date_name',
            "\$arrival_date['description']" => 'token:__arrival_date_description',
            'h($submit_button_label)' => 'state:submit_button_label',
        ),
        'if' => array(
            "ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'" => $flag('is_multi_recipient'),
            '$address_type' => $flag('has_address_type'),
            // A link bound to the address goes when there is none.
            '$address_type_url' => array('becomes' => 'keep'),
            '$shipping_same_as_billing' => $flag('offers_same_as_billing'),
            // The page's custom shipping form, its fields drawn by the widget.
            '$custom_shipping_form' => array('becomes' => 'section', 'section' => 'custom_shipping_form', 'flag' => array('has_custom_form'),
                'inner' => '<strong>^^__custom_form_title^^</strong>%s'),
            '$arrival_dates' => $flag('has_arrival_dates'),
            "!\$arrival_date['custom']" => $flag('arrival_is_standard'),
            "\$arrival_date['custom']" => $flag('arrival_is_custom'),
        ),
        'else' => array("\$arrival_date['custom']" => 'arrival_is_standard'),
        'loop' => array('$arrival_dates as $arrival_date' => 'list'),
        'forms' => 'strip',
        'flag_list' => true,
        // The custom day's box, renamed per row by the widget.
        'controls' => array('custom_arrival_date_^^__arrival_date_id^^' => 'custom_arrival_date'),
    );
}

// The shipping method step (widgets_shipping_method.php): the recipient's
// address by its parts, the recipient's items the widget's section, the
// methods the widget's list, the rest under pg_sm_flags().
function _pg_lc_shipping_method_map($none)
{
    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    $echo = array(
        '$currency_attributes' => 'none',
        '$currency_system' => 'none',
        'h($ship_to_name)' => 'token:__recipient_name',
        'h($update_url)' => 'token:__change_address_url',
        "\$arrival_date['date_info']" => 'token:__requested_arrival_date',
        "h(\$arrival_date['name'])" => 'token:__requested_arrival_date',
        'h($no_shipping_methods_message)' => 'token:__no_shipping_methods_message',
        // The widget sets each row's radio value.
        "\$shipping_method['id']" => 'none',
        "h(\$shipping_method['name'])" => 'token:__shipping_method_name',
        "\$shipping_method['cost_info']" => 'token:__shipping_method_cost',
        "h(\$shipping_method['description'])" => 'token:__shipping_method_description',
        'h($submit_button_label)' => 'state:submit_button_label',
    );
    foreach (array('salutation', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'country') as $part) {
        $echo['h($' . $part . ')'] = 'token:__recipient_' . $part;
    }
    $echo['h($zip_code)'] = 'token:__recipient_zip';
    return array(
        'echo' => $none + $echo,
        'if' => array(
            "ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'" => $flag('is_multi_recipient'),
            '$arrival_date' => $flag('has_arrival_date'),
            // A custom day or a named date: the widget's one value.
            "\$arrival_date['custom']" => array('becomes' => 'keep'),
            '$company' => $flag('recipient_has_company'),
            '$address_2' => $flag('recipient_has_address_2'),
            '$number_of_shipping_methods > 1' => $flag('has_several_shipping_methods'),
            '!$shipping_methods' => $flag('no_shipping_methods'),
            // A method only some members get: the widget lists it as any.
            "\$shipping_method['protected']" => array('becomes' => 'widget'),
            '$currency' => $flag('has_currency_choice'),
        ),
        'else' => array(
            "\$arrival_date['custom']" => 'widget',
            '!$shipping_methods' => 'has_shipping_methods',
            "\$shipping_method['protected']" => 'keep',
        ),
        'loop' => array(
            // The recipient's items: the widget's list of them, whole.
            '$items as $item' => 'section:recipient_items',
            '$shipping_methods as $shipping_method' => 'list',
        ),
        'forms' => 'strip',
        'flag_list' => true,
    );
}

// The order preview (widgets_order_preview.php): the recipients and their
// items the widget's section, drawn once (the recurring part's second
// loop over them writes nothing); the offers and gift cards its lists, the
// payment periods its section; the billing address by its parts and the
// payment methods under pg_op_flags(). The widget draws its own form.
function _pg_lc_order_preview_map($none)
{
    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    $keep = array('becomes' => 'keep');
    $echo = array(
        '$currency_attributes' => 'none',
        '$currency_system' => 'none',
        '$subtotal_info' => 'token:__cart_subtotal',
        '$discount_info' => 'token:__cart_discount',
        '$tax_info' => 'token:__cart_tax',
        '$shipping_info' => 'token:__cart_shipping',
        '$gift_card_discount_info' => 'token:__cart_gift_card_discount',
        '$surcharge_info' => 'token:__cart_surcharge',
        'h($surcharge_percentage)' => 'token:__surcharge_percentage',
        '$total_info' => 'token:__cart_total',
        '$total_with_surcharge_info' => 'token:__cart_total_with_surcharge',
        '$base_currency_total_info' => 'token:__cart_total_charged',
        '$base_currency_total_with_surcharge_info' => 'token:__cart_total_with_surcharge_charged',
        "h(\$offer['description'])" => 'token:__offer_text',
        "h(\$gift_card['protected_code'])" => 'token:__gift_card_code',
        "\$gift_card['remaining_balance_info']" => 'token:__gift_card_balance',
        "h(\$gift_card['remove_url'])" => 'token:__gift_card_remove_url',
        'h($billing_update_url)' => 'token:__billing_update_url',
        'h($custom_field_1_label)' => 'token:__custom_field_1_label',
        'h($custom_field_1)' => 'token:__custom_field_1',
        'h($custom_field_2_label)' => 'token:__custom_field_2_label',
        'h($custom_field_2)' => 'token:__custom_field_2',
        'h($billing_company)' => 'token:__billing_company',
        'h($billing_phone_number)' => 'token:__billing_phone',
        'h($billing_fax_number)' => 'token:__billing_fax',
        'h($billing_email_address)' => 'token:__billing_email',
        'h($billing_zip_code)' => 'token:__billing_zip',
        'h($po_number)' => 'token:__po_number',
        'h($billing_form_title)' => 'token:__billing_form_title',
        'h($card_verification_number_url)' => 'token:__card_verification_url',
        'h($paypal_express_checkout_image_url)' => 'token:__paypal_image_url',
        'h($pay_with_iyzico_image_url)' => 'token:__iyzico_image_url',
        'h($offline_payment_label)' => 'token:__offline_payment_label',
        'h($terms_url)' => 'token:__terms_url',
        'h($purchase_now_button_label)' => 'token:__purchase_now_label',
        '($total_info)' => 'token:__cart_total',
        '($total_with_surcharge_info)' => 'token:__cart_total_with_surcharge',
        '($installment_table)' => 'section:installment_table',
        // The widget ticks and locks the box itself.
        '($threedsecure_required)' => 'none',
        // The stock template's plural headings: the widget's lists hold many.
        "h(\$number_of_applied_gift_cards > 1 ? lang('Gift Cards') : lang('Gift Card'))" => 'text:Gift Cards',
        "h(\$number_of_applied_gift_cards > 1 ? lang('Applied Gift Cards') : lang('Applied Gift Card'))" => 'text:Applied Gift Cards',
        "h(\$number_of_applied_offers > 1 ? lang('Applied Offers') : lang('Applied Offer'))" => 'text:Applied Offers',
    );
    foreach (array('salutation', 'first_name', 'last_name', 'address_1', 'address_2', 'city', 'state', 'country') as $part) {
        $echo['h($billing_' . $part . ')'] = 'token:__billing_' . $part;
    }
    return array(
        'echo' => $none + $echo,
        'if' => array(
            '!$recipients' => $flag('cart_empty'),
            '$recurring_items' => $flag('has_recurring'),
            // Opens the items' column in one block and closes it in another.
            '$nonrecurring_items' => $keep,
            '$show_subtotal' => $flag('shows_subtotal'),
            '$discount_info' => $flag('has_discount'),
            '$tax_info' => $flag('has_tax'),
            '$shipping_info' => $flag('has_shipping_cost'),
            '$gift_card_discount_info' => $flag('has_gift_card_discount'),
            '$show_surcharge' => $flag('has_surcharge'),
            '$base_currency_total_info' => $flag('has_currency_disclaimer'),
            '$base_currency_total_with_surcharge_info' => $flag('has_currency_disclaimer'),
            '$total_disclaimer' => array('becomes' => 'token', 'token' => '__currency_disclaimer', 'flag' => array('has_currency_disclaimer')),
            '$applied_offers' => $flag('has_applied_offers'),
            '$applied_gift_cards' => $flag('has_applied_gift_cards'),
            // The plural's "s" and the list: the widget's lists hold many.
            '$number_of_applied_offers > 1' => $keep,
            '$number_of_applied_gift_cards > 1' => $keep,
            '$purchase_now_button and !$recurring_items' => $flag('can_purchase_in_totals'),
            '$purchase_now_button' => $flag('can_purchase'),
            '$custom_field_1_label and $custom_field_1' => $flag('shows_custom_field_1'),
            '$custom_field_2_label and $custom_field_2' => $flag('shows_custom_field_2'),
            '$billing_first_name' => $flag('billing_has_name'),
            '$billing_company' => $flag('billing_has_company'),
            '$billing_address_1' => $flag('billing_has_address_1'),
            '$billing_address_2' => $flag('billing_has_address_2'),
            '$billing_city or $billing_state or $billing_zip_code' => $flag('billing_has_city_line'),
            '$billing_city' => $flag('billing_has_city'),
            '$billing_country' => $flag('billing_has_country'),
            '$billing_phone_number' => $flag('billing_has_phone'),
            '$billing_fax_number' => $flag('billing_has_fax'),
            '$billing_email_address' => $flag('billing_has_email'),
            '$po_number' => $flag('has_po_number'),
            '$tax_exempt' => $flag('is_tax_exempt'),
            '$billing_form' => $flag('has_billing_form'),
            '$billing_form_title' => $flag('has_billing_form_title'),
            '$payment' => $flag('has_payment'),
            '$gift_card_code' => $flag('has_gift_card_code'),
            '$credit_debit_card' => $flag('pays_by_card'),
            // PayPal's words on the button: kept, read only when it is chosen.
            '$paypal_express_checkout' => array('becomes' => 'flag', 'flag' => 'pays_by_paypal', 'in_tag' => 'keep'),
            '$offline_payment' => $flag('pays_offline'),
            '$pay_with_iyzico' => array('becomes' => 'flag', 'flag' => 'pays_by_iyzico', 'in_tag' => 'keep'),
            '$installment' => $flag('has_installments'),
            '$installment_table' => $flag('has_installment_table'),
            '$total_with_surcharge_info' => $flag('has_surcharge'),
            '$threedsecure' => $flag('has_threedsecure'),
            // A link bound to the address goes when there is none.
            "\$billing_update_url !== ''" => $keep,
            '$number_of_payment_methods == 1' => $flag('one_payment_method'),
            '$card_verification_number_url' => $flag('has_card_verification_help'),
            '$surcharge_message' => $flag('surcharge_message'),
            '$terms_url' => $flag('has_terms'),
            '$currency' => $flag('has_currency_choice'),
        ),
        'else' => array(
            '!$recipients' => 'has_items',
            '$number_of_applied_offers > 1' => 'widget',
            '$number_of_applied_gift_cards > 1' => 'widget',
            '$number_of_payment_methods == 1' => 'many_payment_methods',
            '$total_with_surcharge_info' => 'no_surcharge',
        ),
        'loop' => array(
            '$recipients as $recipient' => 'section:order_recipients',
            '$payment_periods as $payment_period' => 'section:recurring_payments',
            '$fields as $field' => 'section:billing_form',
            '$applied_offers as $offer' => 'source:checkout.offers',
            '$applied_gift_cards as $gift_card' => 'source:checkout.gift_cards',
            // The redirect drivers' rows: the widget copies a method's row.
            '$payment_driver_methods as $payment_driver_method => $payment_driver_label' => 'widget',
        ),
        'forms' => 'strip',
        'flag_list' => true,
        'record' => true,
    );
}

// The view order (_render_system_widget_order_view()): one record, so no
// list is added. The recipients are read once, their heading the first
// recipient's values (a site shipping to several reports it); the items
// the widget's order.items list, their event, schedule and shipped
// quantity its row values; the parcels its order.parcels list; the
// product form and gift card blocks the widget's parts. The totals,
// billing and payment under the widget's flags; the recurring charges,
// applied offers and gift cards, the receipt messages and the custom
// forms' answers have no element and are reported.
function _pg_lc_view_order_map($none)
{
    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    $keep = array('becomes' => 'keep');
    $widget = array('becomes' => 'widget');
    $full = array('becomes' => 'keep', 'state' => array('full_description'));
    $echo = array(
        '$order_number' => 'token:__order_no',
        '$reference_code' => 'token:__reference_code',
        "get_absolute_time(array('timestamp' => \$order_date, 'size' => 'long'))" => 'token:__order_date',
        "h(\$recipient['ship_to_name'])" => 'token:__ship_to_name',
        // The one-line address starts with the name.
        "h(\$recipient['name'])" => 'none',
        "h(\$recipient['address'])" => 'token:__shipping_full_address',
        "get_absolute_time(array('timestamp' => strtotime(\$recipient['arrival_date']), 'type' => 'date', 'size' => 'long'))" => 'token:__arrival_date',
        "get_shipping_tracking_url(\$shipping_tracking_number['number'],\$recipient['shipping_method_code'])" => 'token:__tracking_link',
        "h(\$shipping_tracking_number['number'])" => 'token:__tracking_code',
        "h(\$item['name'])" => 'token:__item_name',
        "h(\$item['short_description'])" => 'token:__item_short_description',
        "h(\$item['calendar_event']['name'])" => 'token:__item_event_name',
        "\$item['calendar_event']['date_and_time_range']" => 'token:__item_event_date',
        "h(\$item['recurring_payment_period'])" => 'token:__item_payment_period',
        "pg_format_number(\$item['recurring_number_of_payments'], 0)" => 'token:__item_payment_count',
        "get_absolute_time(array('timestamp' => strtotime(\$item['recurring_start_date']), 'type' => 'date', 'size' => 'long'))" => 'token:__item_first_payment_date',
        "pg_format_number(\$item['quantity'], 0)" => 'token:__item_qty',
        "pg_format_number(\$item['shipped_quantity'], 0)" => 'token:__item_shipped_qty',
        "\$item['price_info']" => 'token:__item_price',
        "\$item['amount_info']" => 'token:__item_total',
        // The shipping row's first cell spans the item and its description.
        '(2+$number_of_shipped_quantity_columns)' => 'text:2',
        "h(\$recipient['shipping_method_name'])" => 'token:__shipping_method',
        "\$recipient['shipping_cost_info']" => 'token:__order_shipping',
        '$subtotal_info' => 'token:__order_subtotal',
        '$discount_info' => 'token:__order_discount',
        '$tax_info' => 'token:__order_tax',
        '$shipping_info' => 'token:__order_shipping',
        '$gift_card_discount_info' => 'token:__order_gift_card_discount',
        '$surcharge_info' => 'token:__order_surcharge',
        'h($number_of_installment)' => 'token:__installment_count',
        '$installment_charges_info' => 'token:__installment_charges',
        '$total_info' => 'token:__order_total',
        'h($custom_field_1_label)' => 'token:__order_custom_field_1_label',
        'h($custom_field_1)' => 'token:__order_custom_field_1',
        'h($custom_field_2_label)' => 'token:__order_custom_field_2_label',
        'h($custom_field_2)' => 'token:__order_custom_field_2',
        'h($billing_zip_code)' => 'token:__billing_zip',
        'h($billing_phone_number)' => 'token:__billing_phone',
        'h($billing_fax_number)' => 'token:__billing_fax',
        'h($billing_email_address)' => 'token:__billing_email',
        'h($po_number)' => 'token:__order_po_number',
        'h($payment_method_label)' => 'token:__payment_method_pretty',
        'h($card_type)' => 'token:__card_type',
        'h($card_number)' => 'token:__card_number_masked',
        'h($my_account_url)' => 'token:__my_account_url',
        // The reorder link's address, put together below ('replace').
        'OUTPUT_PATH' => 'none',
        'OUTPUT_SOFTWARE_DIRECTORY' => 'none',
        '$order_id' => 'token:__order_id',
        'get_token_query_string_field()' => 'none',
    );
    foreach (array('salutation', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'country') as $part) {
        $echo['h($billing_' . $part . ')'] = 'token:__billing_' . $part;
    }
    return array(
        'echo' => $none + $echo,
        'if' => array(
            // The widget shows a completed order: its number, never the
            // reference code of an incomplete one, nor its buttons.
            "\$status != 'incomplete'" => $keep,
            "\$status === 'incomplete'" => $widget,
            // Today's and the recurring charges apart: the widget's items
            // are one list, with no recurring totals.
            '$recurring_items' => array('becomes' => 'drop'),
            // Opens the items' column in one block and closes it in another.
            '$nonrecurring_items' => $keep,
            "\$recipient['in_nonrecurring']" => $keep,
            "\$item['in_nonrecurring']" => $keep,
            // A heading per recipient: the first recipient's values.
            "\$recipient['ship_to_heading']" => array('becomes' => 'flag', 'flag' => 'has_shipping', 'drop_when' => array('multi_recipient' => 'recipients')),
            "ECOMMERCE_RECIPIENT_MODE == 'multi-recipient'" => array('becomes' => 'keep', 'state' => array('multi_recipient')),
            "\$status != 'incomplete' and \$recipient['shipping_tracking_numbers']" => $flag('has_tracking_code'),
            // The plural's "s": the list holds many.
            "count(\$recipient['shipping_tracking_numbers']) > 1" => $keep,
            // The comma between numbers: the list's rows stand side by side.
            '$key != 0' => $widget,
            // The number is the link; an unknown carrier's link is empty.
            "get_shipping_tracking_url(\$shipping_tracking_number['number'],\$recipient['shipping_method_code']) != ''" => $keep,
            "\$recipient['address']" => $keep,
            "\$recipient['name']" => $widget,
            '$arrival_dates' => $flag('has_arrival_date'),
            // The date itself, also for a named arrival date.
            "\$recipient['arrival_date_custom']" => $keep,
            "\$recipient['non_donations_in_nonrecurring']" => $keep,
            '$shipped_quantities_exist == TRUE' => $flag('shows_shipped_quantity'),
            '$shipped_quantities_exist' => $flag('shows_shipped_quantity'),
            "\$item['shipped_quantity'] > 0" => $flag('item_shipped_qty'),
            "\$product_description_type == 'full_description'" => $full,
            "\$item['image_url']" => $keep,
            "\$item['image_url'] and (\$product_description_type == 'full_description')" => $full,
            "\$item['calendar_event']" => $flag('item_event_name'),
            "\$item['recurring_schedule']" => $flag('item_payment_period'),
            "\$item['recurring_number_of_payments']" => $flag('item_payment_count'),
            '$start_date' => $flag('item_first_payment_date'),
            "\$item['gift_card']" => array('becomes' => 'token', 'token' => '__item_gift_card_html'),
            "\$item['form']" => array('becomes' => 'token', 'token' => '__item_form_data_html'),
            // A donation's quantity and price are shown too.
            "\$item['selection_type'] != 'donation'" => $keep,
            "\$recipient['shipping']" => $flag('has_shipping'),
            "\$recipient['shipping_method_description']" => $widget,
            '$show_subtotal' => $keep,
            '$discount_info' => $flag('has_discount'),
            '$tax_info' => $flag('has_tax'),
            '$shipping_info' => $flag('has_shipping_cost'),
            '$gift_card_discount_info' => $flag('has_gift_card_discount'),
            '$number_of_applied_gift_cards > 1' => $keep,
            '$surcharge_info' => $flag('has_surcharge'),
            '$installment_charges_info' => $flag('has_installment_charges'),
            // The widget shows the amounts charged: no converted total.
            '$base_currency_total_info' => $widget,
            '$total_disclaimer' => $widget,
            '$custom_field_1_label and $custom_field_1' => $flag('shows_custom_field_1'),
            '$custom_field_2_label and $custom_field_2' => $flag('shows_custom_field_2'),
            '$billing_company' => $flag('billing_has_company'),
            '$billing_address_2' => $flag('billing_has_address_2'),
            '$billing_fax_number' => $flag('billing_has_fax'),
            '$po_number' => $flag('has_po_number'),
            '$tax_exempt' => $flag('is_tax_exempt'),
            '$payment_method' => $flag('has_payment_method'),
            "\$payment_method == 'Credit/Debit Card'" => $flag('has_card_number'),
        ),
        'else' => array(
            "\$status != 'incomplete'" => 'widget',
            "\$product_description_type == 'full_description'" => 'unless',
            "get_shipping_tracking_url(\$shipping_tracking_number['number'],\$recipient['shipping_method_code']) != ''" => 'widget',
            "\$recipient['arrival_date_custom']" => 'widget',
        ),
        'loop' => array(
            "\$recipient['shipping_tracking_numbers'] as \$key => \$shipping_tracking_number" => 'source:order.parcels',
            '$recipients as $recipient' => 'keep',
            "\$recipient['items'] as \$item" => 'source:order.items',
        ),
        'replace' => array('/order_history_reorder.php?id=^^__order_id^^' => '^^__reorder_url^^'),
        'record' => true,
    );
}

// The login page and the three password pages (login_form,
// forgot_password, set_password, change_password widgets): one form each,
// whose <form> tags go (the widget draws its own, posting to the classic
// handler, so the controls keep their classic names). The widget fills,
// drops and checks the controls itself: a password hint box goes while
// the site asks for none, the current password while the account has
// none. The strong password rules are the widget's password_rules section.
function _pg_lc_password_map($type, $none)
{
    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    $map = array(
        'echo' => $none,
        'if' => array(
            '$strong_password_help' => array('becomes' => 'section', 'section' => 'password_rules'),
            // The widget drops the hint box (and a wrapper left empty)
            // while the site asks for no hint.
            'PASSWORD_HINT' => array('becomes' => 'keep'),
        ),
        'else' => array(),
        'loop' => array(),
        'forms' => 'strip',
        'record' => true,
    );
    switch ((string)$type) {
        case 'login':
            $map['echo'] += array(
                "\$google_signin ?? ''" => 'section:google_signin',
                'h($forgot_password_url)' => 'token:__forgot_password_url',
            );
            // The box's label holds words of its own: a flag, not the
            // widget's drop of the control alone.
            $map['if']['REMEMBER_ME'] = $flag('shows_remember_me');
            $map['if']['$forgot_password_url'] = $flag('has_forgot_password_link');
            break;
        case 'forgot password':
            $map['if']['!$screen'] = array('becomes' => 'keep');
            // The hint screen: forgot_password.php shows it no more (a
            // hint would tell which addresses hold an account), so the
            // setting it needs is never on.
            $map['if']["elseif:\$screen == 'password_hint'"] = array('becomes' => 'drop', 'state' => array('password_hint_screen'));
            break;
        case 'set password':
            $map['echo']['h($email)'] = 'token:__email';
            break;
        case 'change password':
            $map['echo']['h($my_account_url)'] = 'token:__my_account_url';
            // The widget drops a link bound to an empty address.
            $map['if']['$my_account_url'] = array('becomes' => 'keep');
            $map['if']['!empty($password_not_set)'] = $flag('password_not_set');
            $map['else']['!empty($password_not_set)'] = 'has_password';
            break;
    }
    return $map;
}

// The profile and e-mail preferences pages (account_profile,
// email_preferences widgets): the member's own form, its <form> tags gone
// as on the password pages. The widget fills the controls by their classic
// names and drops a link bound to an empty address.
function _pg_lc_account_form_map($type, $none)
{
    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    $map = array(
        'echo' => $none + array('h($my_account_url)' => 'token:__my_account_url'),
        'if' => array('$my_account_url' => array('becomes' => 'keep')),
        'else' => array(),
        'loop' => array(),
        'forms' => 'strip',
        'record' => true,
    );
    if ((string)$type === 'my account profile') {
        // The time zone row: every PHP the site runs on offers it.
        $map['if']['$timezone'] = array('becomes' => 'keep');
        return $map;
    }
    // E-mail preferences: the mailing lists are the widget's list, a row's
    // box named by the widget; the SMS box under the widget's flag (its
    // label holds words of its own).
    $map['echo'] += array(
        'h(OPT_IN_LABEL)' => 'token:__opt_in_label',
        'h(pg_sms_opt_in_label())' => 'token:__opt_in_sms_label',
        "\$contact_group['id']" => 'token:__group_id',
        "h(\$contact_group['name'])" => 'token:__group_name',
        "h(\$contact_group['description'])" => 'token:__group_description',
    );
    $map['if'] += array(
        '$sms_opt_in' => $flag('shows_opt_in_sms'),
        '$contact_groups' => $flag('has_contact_groups'),
        "\$contact_group['description']" => $flag('group_has_description'),
    );
    $map['loop'] = array('$contact_groups as $contact_group' => 'list');
    $map['flag_list'] = true;
    unset($map['record']);
    // The classic page's script shows the lists once the opt-in is ticked;
    // the widget always shows them.
    $map['replace'] = array(' style="display: none"' => '');
    return $map;
}

// The registration and membership entrance pages (registration,
// membership widgets): the sign-up form is the widget, its <form> tags
// gone (the widget draws its own, posting to the classic handler, so the
// controls keep their classic names). The login form beside it is a second
// form the widget never holds (no form inside a form): it goes with the
// wrappers that hold nothing else, reported as the Login widget's, and so
// do its forgot password link and sign-in buttons. The guest block is the
// registration widget's guest section, read and held: the server gives its
// button a small form of its own.
function _pg_lc_entrance_map($type, $none)
{
    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    $login = array('becomes' => 'drop', 'code' => 'login_form');
    $map = array(
        'echo' => $none + array(
            '$login_attributes' => 'form:strip',
            '$register_attributes' => 'form:strip',
            '$guest_attributes' => 'form:strip',
            '$attributes' => 'form:strip',
            '$login_system' => 'cut:login_form',
            '$register_system' => 'none',
            '$guest_system' => 'none',
            "\$google_signin ?? ''" => 'drop:login_form',
            'h($opt_in_label)' => 'token:__opt_in_label',
        ),
        'if' => array(
            '$strong_password_help' => array('becomes' => 'section', 'section' => 'password_rules'),
            // The widget drops the hint box (and a wrapper left empty)
            // while the site asks for no hint.
            'PASSWORD_HINT' => array('becomes' => 'keep'),
            // The box's label holds words of its own: a flag.
            'REMEMBER_ME' => $flag('shows_remember_me'),
            '$forgot_password_url' => $login,
        ),
        'else' => array(),
        'loop' => array(),
        'record' => true,
    );
    if ((string)$type === 'membership entrance') {
        $map['echo']['h(MEMBER_ID_LABEL)'] = 'token:__member_id_label';
        // The membership entrance asks no CAPTCHA question: given empty.
        $map['if']['$captcha_question'] = array('becomes' => 'widget');
        return $map;
    }
    $map['if'] += array(
        '$captcha_question' => array('becomes' => 'section', 'section' => 'captcha'),
        '$guest' => array('becomes' => 'section', 'section' => 'guest', 'hold' => true, 'button' => 'continue_as_guest'),
        // The script that opens the register tab after a refused sign-up:
        // the widget prints its errors where its Messages block stands.
        '$register_form->check_form_errors()' => array('becomes' => 'widget'),
    );
    return $map;
}

// The update address book page (address_book widget): the recipient's
// form, its <form> tags gone (the widget draws its own, posting to
// update_address_book.php, so the controls keep their classic names). The
// address type radios are shown under the widget's flag (its setting
// cfg.address_type, carried from the type row); "What is this?" is a link
// bound to the page the type row names, left out when it names none.
function _pg_lc_address_book_map($none)
{
    return array(
        'echo' => $none + array(
            'h($address_type_url)' => 'token:__address_type_url',
            'h($my_account_url)' => 'token:__my_account_url',
        ),
        'if' => array(
            '$address_type' => array('becomes' => 'flag', 'flag' => 'has_address_type'),
            '$address_type_url' => array('becomes' => 'keep', 'state' => array('address_type_page_id')),
        ),
        'else' => array(),
        'loop' => array(),
        'forms' => 'strip',
        'record' => true,
        'page_links' => array('__address_type_url' => 'address_type_page_id'),
    );
}

// The my account page (my_account widget): the member's values are the
// widget's account tokens, each line shown when its value is there (a value
// condition, not a flag: the widget reads its values after its flags); the
// links to the other account pages its link tokens, a link with nowhere to
// go dropped by the widget. The complete orders are the widget's
// account.orders list. The member's start page, the business address lines
// and fax (not among the account values a widget shows), the affiliate
// block, the incomplete orders and the saved recipients (lists of their
// own) have no element: dropped and reported; so is the Google sign-in
// notice of the stock template (the account security widget's).
function _pg_lc_my_account_map($none)
{
    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    $keep = array('becomes' => 'keep');
    $drop = array('becomes' => 'drop');
    $when = function ($f) { return array('becomes' => 'flag', 'flag' => 'when_' . $f); };
    return array(
        'echo' => $none + array(
            'h(USER_USERNAME)' => 'token:username',
            'h(USER_EMAIL_ADDRESS)' => 'token:email_address',
            'h($first_name)' => 'token:first_name',
            'h($last_name)' => 'token:last_name',
            'h($title)' => 'token:__member_job_title',
            'h($company)' => 'token:__member_company',
            'h($business_city)' => 'token:__member_business_city',
            'h($business_country)' => 'token:__member_business_country',
            'h($business_phone)' => 'token:__member_business_phone',
            'h($mobile_phone)' => 'token:__member_mobile_phone',
            'h($home_phone)' => 'token:__member_home_phone',
            'h($timezone)' => 'token:__member_time_zone',
            'h(MEMBER_ID_LABEL)' => 'token:__member_id_label',
            'h(USER_MEMBER_ID)' => 'token:member_id',
            "get_absolute_time(array('timestamp' => strtotime(USER_EXPIRATION_DATE), 'type' => 'date'))" => 'token:__member_expiry_date',
            'h(number_format($reward_points))' => 'token:__member_reward_points',
            'h(pg_format_number($reward_points, 0))' => 'token:__member_reward_points',
            'h($my_account_profile_url)' => 'token:__profile_url',
            'h($email_preferences_url)' => 'token:__email_preferences_url',
            'h($change_password_url)' => 'token:__change_password_url',
            'h($logout_url)' => 'token:__logout_url',
            'h($update_address_book_url)' => 'token:__address_book_url',
            "h(\$order['order_number'])" => 'token:__order_no',
            "get_relative_time(array('timestamp' => \$order['order_date']))" => 'token:__order_date',
            "\$order['total_info']" => 'token:__order_total',
            "h(\$order['view_url'])" => 'token:__order_url',
            "h(\$privacy_request['url'])" => 'token:__privacy_request_url',
            // The list's rows have no reorder address: the button goes.
            "h(\$order['reorder_url'])" => 'unlink:value',
        ),
        'if' => array(
            // The contact lines: each shows on its own value.
            '$first_name or $last_name or $title or $company or $business_address_1 or $business_address_2 or $business_city or $business_state or $business_zip_code or $business_country' => $keep,
            '$first_name or $last_name' => $when('name'),
            '$title' => $when('job_title'),
            '$company' => $when('company'),
            '$business_address_1' => $drop,
            '$business_address_2' => $drop,
            '$business_state or $business_zip_code' => $drop,
            '$business_state' => $drop,
            '$business_zip_code' => $drop,
            '$business_city' => $when('city'),
            '$business_city or $business_state or $business_zip_code' => $when('city'),
            '$business_country' => $when('country'),
            '$business_phone' => $when('business_phone'),
            '$mobile_phone' => $when('mobile_phone'),
            '$home_phone' => $when('home_phone'),
            '$business_fax' => $drop,
            '$timezone' => $when('time_zone'),
            'USER_MEMBER_ID' => $when('member_id'),
            "defined('USER_MEMBER_ID') && USER_MEMBER_ID" => $when('member_id'),
            "USER_EXPIRATION_DATE == '0000-00-00'" => $when('no_expiry'),
            '$reward_points' => $when('reward_points'),
            // A link the widget drops when it has nowhere to go.
            '$my_account_profile_url' => $keep,
            '$email_preferences_url' => $keep,
            '$change_password_url' => $keep,
            '$logout_url' => $keep,
            '$update_address_book_url' => $keep,
            '$complete_orders or $incomplete_orders' => $flag('has_orders'),
            '$complete_orders' => $flag('has_orders'),
            "\$order['view_url']" => $when('order_url'),
            // The store credit block: the widget's two sections.
            "!empty(\$store_credit['shown'])" => array('becomes' => 'section', 'section' => 'store_credit', 'flag' => array('has_store_credit'),
                'inner' => '<h2>Store credit</h2>%s<pg-section data-section="store_credit_convert"></pg-section>'),
            "!empty(\$privacy_request['shown'])" => $when('privacy_request'),
        ),
        'else' => array("USER_EXPIRATION_DATE == '0000-00-00'" => 'when_expiry'),
        'loop' => array('$complete_orders as $order' => 'source:account.orders'),
        'record' => true,
        'when' => array(
            'when_name' => array('tokens' => array('first_name', 'last_name')),
            'when_job_title' => array('tokens' => array('__member_job_title')),
            'when_company' => array('tokens' => array('__member_company')),
            'when_city' => array('tokens' => array('__member_business_city')),
            'when_country' => array('tokens' => array('__member_business_country')),
            'when_business_phone' => array('tokens' => array('__member_business_phone')),
            'when_mobile_phone' => array('tokens' => array('__member_mobile_phone')),
            'when_home_phone' => array('tokens' => array('__member_home_phone')),
            'when_time_zone' => array('tokens' => array('__member_time_zone')),
            'when_member_id' => array('tokens' => array('member_id')),
            'when_no_expiry' => array('tokens' => array('__member_expiry_date'), 'op' => 'empty'),
            'when_expiry' => array('tokens' => array('__member_expiry_date')),
            'when_reward_points' => array('tokens' => array('__member_reward_points'), 'op' => 'gt', 'value' => '0'),
            'when_order_url' => array('tokens' => array('__order_url'), 'op' => 'neq', 'value' => '#'),
            'when_privacy_request' => array('tokens' => array('__privacy_request_url')),
        ),
    );
}


// The search results page (widgets.php, search_results): the featured, the
// catalog and the other results loops are places of the widget's one list
// ('lists'), each showing its kind of rows (result_is_featured,
// result_is_catalog, result_is_page); a result's values its row tokens; the
// search box the widget's bound search form; the sentences about the search
// its heading; the rest under the widget's flags.
function _pg_lc_search_results_map($none)
{
    $flag = function ($f) { return array('becomes' => 'flag', 'flag' => $f); };
    $heading = 'token:__results_heading';
    $echo = array(
        '$attributes' => 'form:search_form',
        '$page_id' => 'text:page',
        'h($query)' => 'token:__search_query',
        'number_format($number_of_results)' => 'token:__result_count',
        'pg_format_number($number_of_results, 0)' => 'token:__result_count',
        // The stock template's sentences: the widget's heading says them.
        "lang(array('string' => 'No results were found for: {var:1}', 'vars' => array(h(\$query))))" => $heading,
        "lang(array('string' => 'Showing {var:1} of the most relevant results for: {var:2}', 'vars' => array(pg_format_number(\$number_of_results, 0), h(\$query))))" => $heading,
        "(\$number_of_results > 1) ? lang(array('string' => 'Found {var:1} results for: {var:2}', 'vars' => array(pg_format_number(\$number_of_results, 0), h(\$query)))) : lang(array('string' => 'Found {var:1} result for: {var:2}', 'vars' => array(pg_format_number(\$number_of_results, 0), h(\$query))))" => $heading,
    );
    // A featured or catalog item and an other result: the same row values.
    foreach (array('item', 'result') as $v) {
        $echo += array(
            "h(\$" . $v . "['url'])" => 'token:__result_url',
            "h(\$" . $v . "['title'])" => 'token:__result_title',
            "h(\$" . $v . "['full_url'])" => 'token:__result_full_url',
            "h(\$" . $v . "['description'])" => 'token:__result_excerpt',
            "h(\$" . $v . "['image_url'])" => 'token:__result_image',
            "h(\$" . $v . "['short_description'])" => 'token:__result_title',
            "\$" . $v . "['price_info']" => 'token:__result_price',
        );
    }
    $if = array(
        "\$query == ''" => $flag('no_query'),
        '$number_of_results == 0' => $flag('no_results'),
        '$limited' => $flag('is_limited'),
        '$number_of_results > 1' => $flag('many_results'),
        '$featured_items' => $flag('has_featured_results'),
        '$catalog_items' => $flag('has_catalog_results'),
        '$results' => $flag('has_page_results'),
        '$featured_items or $catalog_items' => $flag('has_featured_or_catalog_results'),
        // A link around the picture and the words, opened and closed in two
        // blocks: every row has its address. A catalog row's title is its
        // short description, else its name.
        "\$item['url']" => array('becomes' => 'keep'),
        "\$item['short_description']" => array('becomes' => 'keep'),
        "\$item['image_url']" => $flag('result_has_image'),
        "\$item['price_info']" => $flag('result_has_price'),
        "\$item['description']" => $flag('result_has_excerpt'),
        "\$result['description']" => $flag('result_has_excerpt'),
    );
    return array(
        // The layout's own form is the widget's search form.
        'echo' => array_merge($none, $echo),
        'if' => $if,
        'else' => array(
            '$number_of_results == 0' => 'has_results',
            '$limited' => 'not_limited',
        ),
        'loop' => array(
            '$featured_items as $item' => 'list:result_is_featured',
            '$catalog_items as $item' => 'list:result_is_catalog',
            '$results as $result' => 'list:result_is_page',
        ),
        'lists' => true,
        'flag_list' => true,
        // "result<s>" in a line of text: the line once for each.
        'opposite' => array('many_results' => 'one_result'),
        'controls' => array('page_query' => 'bind:search_query'),
    );
}

/**
 * A widget starter's row template: what its first loop area holds, or the
 * content of the loop area's one column (the layout's own loop body lays
 * the records out). Pure.
 *
 * @param array $starter
 * @return array  nodes
 */
function pg_layout_convert_row($starter)
{
    $loop = _pg_lc_find_loop(is_array($starter) ? $starter : array());
    if ($loop === null) return array();
    $kids = array_values((array)($loop['children'] ?? array()));
    if (count($kids) === 1 && ($kids[0]['type'] ?? '') === 'col') return array_values((array)($kids[0]['children'] ?? array()));
    return $kids;
}

function _pg_lc_find_loop($node)
{
    // One of the widget's further lists (a sourced loop) is not its list.
    if (($node['type'] ?? '') === 'loop_area' && (string)($node['props']['source'] ?? '') === '') return $node;
    foreach ((array)($node['children'] ?? array()) as $c) {
        if (!is_array($c)) continue;
        $hit = _pg_lc_find_loop($c);
        if ($hit !== null) return $hit;
    }
    return null;
}

/**
 * A page's own layout file as the design elements of its page type's
 * widget (pg_layout_widget_kinds()): the custom form converter made
 * general. Parts by pg_di_php_parts(), markup by the HTML converter, so the
 * theme's wrappers and classes stay; each PHP piece becomes what the type's
 * piece map (pg_layout_piece_map()) says, and a piece it does not name is
 * dropped with what it holds and reported. A <script> or <style> stays a
 * raw block.
 *
 * Pure: no query, no file.
 *
 * @param string $type  the page type
 * @param string $code  the layout's PHP text
 * @param array  $ctx   'state' (pg_layout_convert_state()), 'row' (the row
 *                      template, pg_layout_convert_row()), 'header' and
 *                      'footer' (the page's markup for them)
 * @return array  'ok' (false for a type with no map or a text not readable
 *                as PHP), 'tree' (root), 'cfg' (widget settings the layout
 *                sets: 'empty_message'), 'dropped' (list of array('piece'
 *                => the PHP source, 'line' => its line, 'code' =>
 *                'condition', 'value', 'loop', 'code' or a drop_when code))
 */
function pg_layout_convert($type, $code, $ctx = array())
{
    $out = array('ok' => false, 'tree' => pg_di_root(array()), 'cfg' => array(), 'dropped' => array());
    $map = pg_layout_piece_map($type);
    $parts = ($map === null) ? null : pg_di_php_parts((string)$code);
    if ($parts === null) return $out;

    $norm = function ($s) { return preg_replace('/\s+/', '', (string)$s); };
    $echo = array();
    foreach ($map['echo'] as $k => $v) $echo[$norm($k)] = $v;
    $ifs = array();
    foreach ($map['if'] as $k => $v) $ifs[$norm($k)] = $v + array('key' => $norm($k));
    $else = array();
    foreach ($map['else'] as $k => $v) $else[$norm($k)] = $v;
    $loops = array();
    foreach ($map['loop'] as $k => $v) $loops[$norm($k)] = $v;
    $state = (array)($ctx['state'] ?? array());
    $state['required'] = (!empty($state['browse']) || !empty($state['search'])) && empty($state['show_results']);

    $html = '';
    $stack = array();     // open blocks: 'mode' (keep, skip, flag, capture, list), 'key', 'close', 'buf'
    $line = 1;
    $loop_seen = false;
    $lists = array();     // the list loops met, in order: 'piece', 'line'
    $sections = array();  // the section loops written
    $drop = function ($piece, $at, $code_) use (&$out) {
        $out['dropped'][] = array('piece' => trim((string)$piece), 'line' => (int)$at, 'code' => (string)$code_);
    };
    $kept = function () use (&$stack) {
        foreach ($stack as $fr) if ($fr['mode'] === 'skip') return false;
        return true;
    };
    $in_tag = function () use (&$html, &$stack) {
        // The text written so far: a flagged branch's is held until it ends.
        $text = $html;
        foreach ($stack as $fr) if (isset($fr['buf'])) $text .= $fr['buf'];
        $lt = strrpos($text, '<');
        $gt = strrpos($text, '>');
        return $lt !== false && ($gt === false || $lt > $gt);
    };
    // What a condition's branch becomes: an array of the frame's mode and
    // what to write at its start and end.
    $branch = function ($raw, $at, $after_else = false) use ($ifs, $state, $drop, $in_tag, $norm) {
        $cond = $norm($raw);
        if ($after_else && isset($ifs['elseif:' . $cond])) $cond = 'elseif:' . $cond;
        if (!isset($ifs[$cond])) {
            $drop($raw, $at, 'condition');
            return array('mode' => 'skip');
        }
        $p = $ifs[$cond];
        if (isset($p['state'])) {
            $any = false;
            foreach ((array)$p['state'] as $s) if (!empty($state[$s])) $any = true;
            if (!$any) return array('mode' => 'skip', 'key' => $p['key']);
        }
        foreach ((array)($p['drop_when'] ?? array()) as $s => $c) {
            if (!empty($state[$s])) {
                $drop($raw, $at, $c);
                return array('mode' => 'skip', 'key' => $p['key']);
            }
        }
        switch ($p['becomes']) {
            case 'widget':
                return array('mode' => 'skip', 'key' => $p['key']);
            case 'keep':
                return array('mode' => 'keep', 'key' => $p['key']);
            case 'empty':
                return array('mode' => 'capture', 'key' => $p['key'], 'buf' => '');
            case 'flag':
                if ($in_tag()) {
                    // An attribute the widget's flag cannot hold.
                    if (($p['in_tag'] ?? '') === 'keep') return array('mode' => 'keep', 'key' => $p['key']);
                    break;
                }
                $open = $close = '';
                foreach ((array)$p['flag'] as $f) {
                    $open .= '<pg-vis data-flag="' . h($f) . '">';
                    $close .= '</pg-vis>';
                }
                return array('mode' => 'flag', 'key' => $p['key'], 'open' => $open, 'close' => $close);
            case 'section':
                if ($in_tag()) break;
                if (!empty($p['hold'])) {
                    // The block is read and held in the section's node.
                    return array('mode' => 'keep', 'key' => $p['key'],
                        'open' => '<pg-section data-section="' . h($p['section']) . '" data-hold="' . h((string)($p['button'] ?? '')) . '">',
                        'close' => '</pg-section>');
                }
                // The section's node is written whole at the start; what
                // the block holds is the widget's, never read.
                $lead = '<pg-section data-section="' . h($p['section']) . '"></pg-section>';
                if (isset($p['inner'])) $lead = sprintf((string)$p['inner'], $lead);
                foreach (array_reverse((array)($p['flag'] ?? array())) as $f) $lead = '<pg-vis data-flag="' . h($f) . '">' . $lead . '</pg-vis>';
                if (isset($p['wrap'])) $lead = sprintf((string)$p['wrap'], $lead);
                return array('mode' => 'skip', 'key' => $p['key'], 'lead' => $lead);
            case 'token':
                // The widget prints the block whole: its value in its place.
                if ($in_tag()) break;
                $lead = '<div>^^' . h($p['token']) . '^^</div>';
                foreach (array_reverse((array)($p['flag'] ?? array())) as $f) $lead = '<pg-vis data-flag="' . h($f) . '">' . $lead . '</pg-vis>';
                return array('mode' => 'skip', 'key' => $p['key'], 'lead' => $lead);
        }
        $drop($raw, $at, ($p['becomes'] === 'drop' && isset($p['code'])) ? (string)$p['code'] : 'condition');
        return array('mode' => 'skip', 'key' => $p['key']);
    };
    $write = function ($text) use (&$stack, &$html) {
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if (isset($stack[$i]['buf'])) { $stack[$i]['buf'] .= $text; return; }
        }
        $html .= $text;
    };

    foreach ($parts as $p) {
        if ($p['kind'] === 'html') {
            if ($kept()) $write($p['text']);
            $line += substr_count($p['text'], "\n");
            continue;
        }
        $at = $line;
        foreach ($p['tokens'] as $t) {
            if (is_array($t) && $t[0] !== T_WHITESPACE && $t[0] !== T_COMMENT && $t[0] !== T_DOC_COMMENT) { $at = (int)$t[2]; break; }
        }
        foreach ($p['tokens'] as $t) $line = max($line, is_array($t) ? (int)$t[2] + substr_count($t[1], "\n") : $line);
        $src = _pg_lc_source($p['tokens']);
        if ($src === '') continue;
        $open_kept = $kept();

        if (!empty($p['echo'])) {
            if ($open_kept) $write(_pg_lc_echo($src, $echo, $norm, $ctx, $at, $drop));
            continue;
        }
        if (preg_match('/^if\s*\((.+)\)\s*(:|\{)$/s', $src, $m)) {
            if (!$open_kept) { $stack[] = array('mode' => 'skip'); continue; }
            $fr = $branch($m[1], $at);
            $fr['chain'] = array($fr);
            if (isset($fr['lead'])) $write($fr['lead']);
            if (($fr['mode'] ?? '') === 'flag') $fr['buf'] = '';
            elseif (isset($fr['open'])) $write($fr['open']);
            $stack[] = $fr;
            continue;
        }
        if (preg_match('/^\}?\s*(else\s*if|elseif)\s*\((.+)\)\s*(:|\{)$/s', $src, $m) || preg_match('/^\}?\s*else\s*(:|\{)$/s', $src)) {
            if (!$stack) { if ($open_kept) $drop($src, $at, 'code'); continue; }
            $top = array_pop($stack);
            $outer = $kept();
            if (isset($top['close']) && $outer) $write(($top['mode'] ?? '') === 'flag' ? $top['open'] . $top['buf'] . $top['close'] : $top['close']);
            if (($top['mode'] ?? '') === 'capture' && $outer) $out['cfg']['empty_message'] = _pg_lc_words($top['buf']);
            $chain = isset($top['chain']) ? $top['chain'] : array();
            if (!$outer || !$chain) { $stack[] = array('mode' => 'skip'); continue; }
            if (isset($m[2])) {
                $fr = $branch($m[2], $at, true);
            } else {
                $fr = _pg_lc_else($chain, $else, $in_tag());
                if ($fr === null) {
                    $drop('else', $at, 'condition');
                    $fr = array('mode' => 'skip');
                }
            }
            $fr['chain'] = array_merge($chain, array($fr));
            if (isset($fr['lead'])) $write($fr['lead']);
            if (($fr['mode'] ?? '') === 'flag') $fr['buf'] = '';
            elseif (isset($fr['open'])) $write($fr['open']);
            $stack[] = $fr;
            continue;
        }
        if (preg_match('/^foreach\s*\((.+)\)\s*(:|\{)$/s', $src, $m)) {
            if (!$open_kept) { $stack[] = array('mode' => 'skip'); continue; }
            $key = $norm($m[1]);
            $lv = (string)($loops[$key] ?? '');
            $kind = (strpos($lv, 'list:') === 0) ? substr($lv, 5) : '';
            if ($lv === 'widget') {
                // Rows the widget adds itself: the body is not read.
                $stack[] = array('mode' => 'skip');
            } elseif ($lv === 'keep') {
                // A grouping the widget has not: its body once, as it is.
                $stack[] = array('mode' => 'keep');
            } elseif (strpos($lv, 'source:') === 0 && !$in_tag()) {
                // One of the widget's further lists: the body is its row.
                $write('<pg-source data-source="' . h(substr($lv, 7)) . '">');
                $stack[] = array('mode' => 'list', 'close' => '</pg-source>');
            } elseif (strpos($lv, 'token:') === 0 && !$in_tag()) {
                // Rows the widget prints whole: the value in their place.
                $write('<div>^^' . h(substr($lv, 6)) . '^^</div>');
                $stack[] = array('mode' => 'skip');
            } elseif (strpos($lv, 'section:') === 0 && !$in_tag()) {
                // The widget's own list: its node in the loop's place, once.
                if (empty($sections[$lv])) $write('<pg-section data-section="' . h(substr($lv, 8)) . '" data-list="1"></pg-section>');
                $sections[$lv] = true;
                $stack[] = array('mode' => 'skip');
            } elseif ((($lv === 'list' && (!$loop_seen || !empty($map['lists']))) || $kind !== '') && !$in_tag()) {
                $loop_seen = true;
                $lists[] = array('piece' => trim($m[1]), 'line' => $at);
                $write($kind !== '' ? '<pg-loop data-row-flag="' . h($kind) . '">' : '<pg-loop>');
                $stack[] = array('mode' => 'list', 'close' => '</pg-loop>');
            } else {
                $drop($m[1], $at, 'loop');
                $stack[] = array('mode' => 'skip');
            }
            continue;
        }
        if (preg_match('/^(endif|endforeach|endfor|endwhile|endswitch|\})$/', $src)) {
            if (!$stack) { if ($open_kept) $drop($src, $at, 'code'); continue; }
            $top = array_pop($stack);
            if ($kept()) {
                if (isset($top['close'])) $write(($top['mode'] ?? '') === 'flag' ? _pg_lc_flag_wrap($top['buf'], $top['open'], $top['close'], count($top['chain'] ?? array()) === 1) : $top['close']);
                if (($top['mode'] ?? '') === 'capture') $out['cfg']['empty_message'] = _pg_lc_words($top['buf']);
            }
            continue;
        }
        // Any other code: a block it opens is skipped whole. A switch's
        // later "case …:" opens none: endswitch closes the switch once.
        if ($open_kept) $drop($src, $at, 'code');
        if (preg_match('/(:|\{)$/', $src) && !preg_match('/^(case\b.*|default\s*):$/s', $src)) $stack[] = array('mode' => 'skip');
    }
    // A flagged branch the layout never closed: what it holds, flagged.
    while ($stack) {
        $top = array_pop($stack);
        if (($top['mode'] ?? '') === 'flag' && isset($top['buf']) && $kept()) $write($top['open'] . $top['buf'] . $top['close']);
    }

    if (($map['forms'] ?? '') === 'strip') $html = preg_replace('~<form\b[^>]*>|</form\s*>~i', '', $html);
    if (!empty($map['replace'])) $html = strtr($html, (array)$map['replace']);
    $raw = array();
    if (($map['scripts'] ?? '') === 'keep') {
        // A script is no element: it is kept whole as a raw block.
        $html = preg_replace_callback('~<script\b[^>]*>.*?</script\s*>~is', function ($m) use (&$raw) {
            $raw[] = $m[0];
            return '<pg-raw data-i="' . (count($raw) - 1) . '"></pg-raw>';
        }, $html);
    }
    $nodes = _pg_lfc_parse($html);
    if ($raw) $nodes = _pg_lc_raw($nodes, $raw);
    if (strpos($html, '<pg-cut') !== false) $nodes = _pg_lc_cut($nodes);
    $row = array_values((array)($ctx['row'] ?? array()));
    $nodes = _pg_lc_finish($nodes, $row, $map);
    foreach ((array)($map['media'] ?? array()) as $token => $flag) $nodes = _pg_lc_media($nodes, $token, $flag);
    if (!empty($map['when'])) $nodes = _pg_lc_when($nodes, (array)$map['when']);
    if (strpos($html, '^^__pg_unlinked^^') !== false) $nodes = _pg_lc_unlink($nodes);
    foreach ((array)($map['page_links'] ?? array()) as $token => $key) $nodes = _pg_lc_page_link($nodes, $token, (int)($state[$key] ?? 0));
    if ($lists && empty($map['lists'])) {
        // The kinds' loops side by side are the one list; a loop elsewhere
        // has no list of its own to be.
        $nodes = _pg_lc_merge_loops($nodes);
        $seen = 0;
        $nodes = _pg_lc_first_loop($nodes, $seen);
        for ($i = 1; $i < $seen; $i++) $drop($lists[count($lists) - $seen + $i]['piece'], $lists[count($lists) - $seen + $i]['line'], 'loop');
    }
    if (empty($map['record']) && _pg_lc_find_loop(array('children' => $nodes)) === null) {
        // A widget draws its records where its list stands: one at the end.
        $nodes[] = pg_di_mk('loop_area', array(), $row);
    }
    if (isset($out['cfg']['empty_message']) && $out['cfg']['empty_message'] === '') unset($out['cfg']['empty_message']);
    foreach ((array)($map['cfg_bound'] ?? array()) as $action => $set) {
        if (_pg_lc_bound(array('children' => $nodes), 'action', $action)) $out['cfg'] = (array)$set + $out['cfg'];
    }
    $out['ok'] = true;
    $out['tree'] = pg_di_root($nodes);
    return $out;
}

/**
 * A page's own layout file as its widget's design elements, with the row
 * template taken from the widget's starter layout and the page's layout
 * settings put in (pg_layout_widget_tree_apply()): what the move's run and
 * the visual designer's "Convert to design elements" build. Pure.
 *
 * @param array  $facts    the page's pg_classic_type_facts() entry ('type',
 *                         'state', 'tree')
 * @param string $code     the layout file's text
 * @param array  $starter  the widget kind's starter tree
 * @return array  pg_layout_convert()'s answer
 */
function pg_layout_type_file_convert($facts, $code, $starter)
{
    return pg_layout_convert((string)($facts['type'] ?? ''), (string)$code, array(
        'state' => (array)($facts['state'] ?? array()),
        'row'   => pg_layout_convert_row(pg_layout_widget_tree_apply(is_array($starter) ? $starter : array(), (array)($facts['tree'] ?? array()))),
    ));
}

/**
 * The dropped pieces of pg_layout_convert() as the Region panel's and the
 * toast's lines: "line 42 · a condition on $watcher_option · dropped". A
 * piece longer than 60 characters is cut with an ellipsis. Pure apart from
 * lang().
 *
 * @param array $dropped  pg_layout_convert()'s 'dropped'
 * @return array  list of array('line' => int, 'text' => string)
 */
function pg_layout_dropped_rows($dropped)
{
    $rows = array();
    foreach ((array)$dropped as $d) {
        $piece = preg_replace('/\s+/', ' ', trim((string)($d['piece'] ?? '')));
        if (function_exists('mb_strlen') ? mb_strlen($piece, 'UTF-8') > 60 : strlen($piece) > 60) {
            $piece = rtrim(function_exists('mb_substr') ? mb_substr($piece, 0, 59, 'UTF-8') : substr($piece, 0, 59)) . '…';
        }
        switch ((string)($d['code'] ?? '')) {
            case 'condition': $what = lang(array('string' => 'a condition on {var:1}', 'vars' => array($piece))); break;
            case 'value':     $what = lang(array('string' => 'the value {var:1}', 'vars' => array($piece))); break;
            case 'loop':      $what = lang(array('string' => 'a loop over {var:1}', 'vars' => array($piece))); break;
            case 'code':      $what = lang(array('string' => 'PHP code {var:1}', 'vars' => array($piece))); break;
            case 'browse':    $what = lang('the browse form'); break;
            case 'recipients': $what = lang('the ship-to heading of each recipient'); break;
            case 'login_form': $what = lang(array('string' => 'the login form ({var:1}): the Login widget draws it', 'vars' => array($piece))); break;
            default:          $what = $piece;
        }
        $line = (int)($d['line'] ?? 0);
        $rows[] = array('line' => $line, 'text' => lang(array('string' => 'line {var:1} · {var:2} · dropped', 'vars' => array($line, $what))));
    }
    return $rows;
}

// One PHP part's code, comments out and white space kept to one space; a
// closing semicolon dropped.
function _pg_lc_source($tokens)
{
    $s = '';
    foreach ($tokens as $t) {
        if (is_array($t) && in_array($t[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
        $s .= (is_array($t) && $t[0] === T_WHITESPACE) ? ' ' : (is_array($t) ? $t[1] : $t);
    }
    return trim(rtrim(trim($s), ';'));
}

// The text a printed value stands for in the stream.
function _pg_lc_echo($src, $echo, $norm, $ctx, $at, $drop)
{
    $key = $norm($src);
    if (isset($echo[$key])) {
        $v = (string)$echo[$key];
        if ($v === 'none') return '';
        if ($v === 'row') return '<pg-row></pg-row>';
        if (strpos($v, 'token:') === 0) return '^^' . implode('^^ ^^', explode(',', substr($v, 6))) . '^^';
        if (strpos($v, 'submit:') === 0) return '^^' . substr($v, 7) . '^^';
        if (strpos($v, 'ctx:') === 0) return (string)($ctx[substr($v, 4)] ?? '');
        if (strpos($v, 'text:') === 0) return h(substr($v, 5));
        if (strpos($v, 'form:') === 0) return ' data-pg-form="' . h(substr($v, 5)) . '"';
        if (strpos($v, 'section:') === 0) return '<pg-section data-section="' . h(substr($v, 8)) . '"></pg-section>';
        if (strpos($v, 'state:') === 0) return h((string)($ctx['state'][substr($v, 6)] ?? ''));
        if (strpos($v, 'drop:') === 0) {
            $drop($src, $at, substr($v, 5));
            return '';
        }
        if (strpos($v, 'unlink:') === 0) {
            // A link's address the widget has not: reported, and the link
            // goes once the markup is read (_pg_lc_unlink()).
            $drop($src, $at, substr($v, 7));
            return '^^__pg_unlinked^^';
        }
        if (strpos($v, 'cut:') === 0) {
            // Reported here, in line order; the form goes once the markup
            // is read (_pg_lc_cut()).
            $drop($src, $at, substr($v, 4));
            return '<pg-cut></pg-cut>';
        }
    }
    // Interface words the template prints: the words themselves, which
    // the page's translation reads like any text.
    if (preg_match('/^(?:h\()?lang\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*\)\)?$/s', $src, $m) && (strpos($src, 'h(') !== 0 || substr($src, -2) === '))')) {
        return h(stripslashes($m[1]));
    }
    $drop($src, $at, 'value');
    return '';
}

// A flagged branch's text in its flag's markers. A lone branch (an "if"
// with no "else": $alone) that only opens wrappers (its other half closes them in a later branch on the same
// condition) keeps them, drawn always; one that first closes wrappers
// opened before it has those closing tags, and the ones it closes beyond
// what it opens, put outside its markers, so the flag covers what it adds.
function _pg_lc_flag_wrap($text, $open, $close, $alone = true)
{
    $text = (string)$text;
    if (!$alone) return $open . $text . $close;
    $lead = $trail = '';
    if (preg_match('~^(\s*(?:</[a-z][a-z0-9-]*\s*>\s*)+)~i', $text, $m)) {
        $lead = $m[1];
        $text = (string)substr($text, strlen($lead));
    }
    $net = _pg_lc_tag_balance($text);
    if ($lead === '' && $net === 0) return $open . $text . $close;
    if ($net > 0 && trim(strip_tags($text)) === '') return $lead . $text;
    while ($net < 0 && preg_match('~(</[a-z][a-z0-9-]*\s*>\s*)$~i', $text, $m)) {
        $trail = $m[1] . $trail;
        $text = (string)substr($text, 0, -strlen($m[1]));
        $net++;
    }
    return $lead . $open . $text . $close . $trail;
}

// Opening tags less closing tags in $text (void elements and comments not
// counted).
function _pg_lc_tag_balance($text)
{
    $text = preg_replace('~<!--.*?-->~s', '', (string)$text);
    $void = array('area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'source', 'track', 'wbr');
    $net = 0;
    preg_match_all('~<(/?)([a-z][a-z0-9-]*)\b[^>]*?(/?)>~i', $text, $m, PREG_SET_ORDER);
    foreach ($m as $t) {
        if ($t[3] === '/' || in_array(strtolower($t[2]), $void, true)) continue;
        $net += ($t[1] === '/') ? -1 : 1;
    }
    return $net;
}

// The plain words of a captured branch.
function _pg_lc_words($html)
{
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$html), ENT_QUOTES, 'UTF-8')));
}

// The "otherwise" branch after $chain: the flag the map gives the last
// condition's opposite, drawn as it is when every branch before it is
// never drawn (or drawn by the widget), null when it has no element.
function _pg_lc_else($chain, $else, $in_tag)
{
    $last = end($chain);
    $key = isset($last['key']) ? $last['key'] : '';
    if ($key !== '' && isset($else[$key]) && $else[$key] === 'keep') return array('mode' => 'keep', 'key' => '');
    // Both branches are the widget's: neither is read.
    if ($key !== '' && isset($else[$key]) && $else[$key] === 'widget') return array('mode' => 'skip', 'key' => '');
    // One of two a page setting decides: drawn when the other is not.
    if ($key !== '' && isset($else[$key]) && $else[$key] === 'unless') return array('mode' => (($last['mode'] ?? '') === 'skip') ? 'keep' : 'skip', 'key' => '');
    if ($key !== '' && isset($else[$key]) && !$in_tag) {
        return array('mode' => 'flag', 'key' => '', 'open' => '<pg-vis data-flag="' . h($else[$key]) . '" data-around-list="1">', 'close' => '</pg-vis>');
    }
    foreach ($chain as $fr) {
        if (($fr['mode'] ?? '') !== 'skip' || !isset($fr['key'])) return null;
    }
    return array('mode' => 'keep', 'key' => '');
}

// The markers turned into the widget's parts: the list (pg-loop) into its
// loop area, the record (pg-row) into the row template, a flagged branch
// (pg-vis) into its nodes shown under the flag, a section (pg-section)
// into the node bound to it; an address or a text that is one widget value
// alone is bound to it, a control is given the name the widget looks for
// and a link to a submit address becomes that button ($map).
function _pg_lc_finish($nodes, $row, $map = array())
{
    $out = array();
    foreach ($nodes as $n) {
        $tag = _pg_lfc_tag($n);
        $kids = _pg_lc_finish((array)($n['children'] ?? array()), $row, $map);
        if ($tag === 'pg-row') {
            foreach ($row as $r) $out[] = $r;
            continue;
        }
        if ($tag === 'pg-loop') {
            // One kind of the list's records: shown in that kind's rows.
            $kind = (string)_pg_lfc_attr($n, 'data-row-flag');
            if ($kind !== '') foreach ($kids as $i => $k) $kids[$i] = _pg_lc_flag($k, $kind);
            $out[] = pg_di_mk('loop_area', ($kind !== '' && empty($map['lists'])) ? array('_kinds' => true) : array(), $kids);
            continue;
        }
        if ($tag === 'pg-source') {
            $out[] = pg_di_mk('loop_area', array('source' => (string)_pg_lfc_attr($n, 'data-source')), $kids);
            continue;
        }
        if ($tag === 'pg-section' && _pg_lfc_attr($n, 'data-hold') !== null) {
            $out[] = _pg_lc_held_section($kids, (string)_pg_lfc_attr($n, 'data-section'), (string)_pg_lfc_attr($n, 'data-hold'));
            continue;
        }
        if ($tag === 'pg-section') {
            $props = array('tag' => 'div', 'cssClass' => '', '_bindings' => array('section' => (string)_pg_lfc_attr($n, 'data-section')));
            if (_pg_lfc_attr($n, 'data-list') !== null) $props['_list'] = true;
            $out[] = pg_di_mk('semantic', $props);
            continue;
        }
        if (isset($map['cross_sell']) && (string)($n['props']['id'] ?? '') === (string)$map['cross_sell']) {
            // The classic cross-sell container: the widget's own part.
            $out[] = _pg_lc_bind(pg_di_mk('semantic', array('tag' => 'div', 'cssClass' => '', 'text' => '^^__cross_sell_html^^')));
            continue;
        }
        $listed = in_array($tag, array('ul', 'ol', 'dl', 'table', 'tbody'), true) ? _pg_lc_listed($kids) : null;
        if ($listed !== null) {
            // The list the loop filled is the widget's: its node instead
            // (a table's heading row goes with it).
            unset($listed['props']['_list']);
            $out[] = $listed;
            continue;
        }
        $form = ($tag === 'form') ? _pg_lfc_attr($n, 'data-pg-form') : null;
        if ($form !== null) {
            _pg_lfc_set_attr($n, 'data-pg-form', null);
            if ($form === 'strip') {
                foreach ($kids as $k) $out[] = $k;
                continue;
            }
            $n['props']['_bindings']['action'] = $form;
        }
        if ($tag === 'pg-vis') {
            $flag = (string)_pg_lfc_attr($n, 'data-flag');
            // Words alone in the branch: a span holds them.
            if (!$kids && trim((string)($n['props']['text'] ?? '')) !== '') {
                $kids = array(_pg_lc_bind(pg_di_mk('semantic', array('tag' => 'span', 'cssClass' => '', 'text' => trim((string)$n['props']['text']))), $map));
            }
            foreach ($kids as $k) $out[] = _pg_lc_flag($k, $flag, !empty($map['flag_list']));
            continue;
        }
        // A list the widget draws in a place other than a list's own.
        foreach ($kids as $i => $k) unset($kids[$i]['props']['_list']);
        $n['children'] = $kids;
        if (!$kids && strpos((string)($n['props']['text'] ?? ''), '<pg-vis') !== false) {
            foreach (_pg_lc_text_flags($n, $map) as $t) $out[] = $t;
            continue;
        }
        $out[] = _pg_lc_bind($n, $map);
    }
    return $out;
}

// A held section's node: the block's one element bound to the section, or
// a box bound to it holding the block; its first submit button (a button
// or a submit input) bound to $action.
function _pg_lc_held_section($kids, $section, $action)
{
    $kids = array_values($kids);
    if ($action !== '') {
        $done = false;
        $bind = function (&$nodes) use (&$bind, &$done, $action) {
            foreach ($nodes as &$n) {
                if ($done) return;
                $tag = _pg_lfc_tag($n);
                $type = strtolower((string)_pg_lfc_attr($n, 'type'));
                if (($tag === 'button' && $type !== 'button' && $type !== 'reset') || ($tag === 'input' && $type === 'submit')) {
                    $n['props']['_bindings']['action'] = $action;
                    $done = true;
                    return;
                }
                if (!empty($n['children'])) $bind($n['children']);
            }
            unset($n);
        };
        $bind($kids);
    }
    if (count($kids) === 1 && _pg_lfc_tag($kids[0]) !== '' && !isset($kids[0]['props']['_bindings']['section'])) {
        $kids[0]['props']['_bindings']['section'] = $section;
        return $kids[0];
    }
    return pg_di_mk('semantic', array('tag' => 'div', 'cssClass' => '', '_bindings' => array('section' => $section)), $kids);
}

// The form holding a cut marker (pg-cut) goes, with every wrapper around it
// that holds no other form (a column, a tab): a second form beside the
// widget's, which the widget never holds. A marker in no form goes alone.
function _pg_lc_cut($nodes)
{
    $path = _pg_lc_path_to($nodes, 'pg-cut', array());
    while ($path !== null) {
        // The form around the marker, then up while the wrapper holds no
        // other form.
        $at = null;
        for ($i = count($path) - 1; $i >= 0; $i--) {
            if (_pg_lfc_tag(_pg_lc_node_at($nodes, array_slice($path, 0, $i + 1))) === 'form') { $at = $i; break; }
        }
        if ($at === null) {
            $at = count($path) - 1;
        } else {
            while ($at > 0 && _pg_lc_forms_in(_pg_lc_node_at($nodes, array_slice($path, 0, $at))) === 1) $at--;
        }
        $nodes = _pg_lc_remove_at($nodes, array_slice($path, 0, $at + 1));
        $path = _pg_lc_path_to($nodes, 'pg-cut', array());
    }
    return $nodes;
}

// The index path to the first node of tag $tag, or null.
function _pg_lc_path_to($nodes, $tag, $path)
{
    foreach (array_values((array)$nodes) as $i => $n) {
        if (_pg_lfc_tag($n) === $tag) return array_merge($path, array($i));
        $p = _pg_lc_path_to((array)($n['children'] ?? array()), $tag, array_merge($path, array($i)));
        if ($p !== null) return $p;
    }
    return null;
}

function _pg_lc_node_at($nodes, $path)
{
    $n = array('children' => $nodes);
    foreach ($path as $i) $n = array_values((array)($n['children'] ?? array()))[$i];
    return $n;
}

function _pg_lc_remove_at($nodes, $path)
{
    $nodes = array_values((array)$nodes);
    $i = array_shift($path);
    if (!$path) {
        array_splice($nodes, $i, 1);
        return $nodes;
    }
    $nodes[$i]['children'] = _pg_lc_remove_at((array)($nodes[$i]['children'] ?? array()), $path);
    return $nodes;
}

// The <form> elements in a node, itself included.
function _pg_lc_forms_in($n)
{
    $count = (_pg_lfc_tag($n) === 'form') ? 1 : 0;
    foreach ((array)($n['children'] ?? array()) as $c) $count += _pg_lc_forms_in($c);
    return $count;
}

// The widget's list node a list's children are, beside a table's heading
// rows (a row of heading cells only), or null.
function _pg_lc_listed($kids)
{
    $list = null;
    foreach ($kids as $k) {
        if (!empty($k['props']['_list']) && $list === null) { $list = $k; continue; }
        $cells = (array)($k['children'] ?? array());
        if (_pg_lfc_tag($k) !== 'tr' || !$cells) return null;
        foreach ($cells as $c) if (_pg_lfc_tag($c) !== 'th') return null;
    }
    return $list;
}

// Words under a flag inside a line of text ("product<pg-vis>s</pg-vis>
// containing <strong>…</strong>"): the element twice, with the words under
// the flag and without them under its opposite ($map 'opposite'); with no
// opposite, the line in spans, the flagged words' span under the flag.
function _pg_lc_text_flags($n, $map)
{
    $text = preg_replace('~%5[eE]%5[eE](__[a-z0-9_]+)%5[eE]%5[eE]~', '^^$1^^', (string)$n['props']['text']);
    // An "otherwise" branch's marker carries an attribute more.
    $re = '~<pg-vis data-flag="([a-z0-9_]+)"[^>]*>(.*?)</pg-vis>~s';
    preg_match_all($re, $text, $m);
    $flags = array_values(array_unique($m[1]));
    $opposite = (string)($map['opposite'][$flags[0]] ?? '');
    if (count($flags) === 1 && $opposite !== '') {
        $with = $without = $n;
        $with['props']['text'] = preg_replace($re, '$2', $text);
        $without['props']['text'] = preg_replace($re, '', $text);
        return array(_pg_lc_flag(_pg_lc_bind($with, $map), $flags[0]), _pg_lc_flag(_pg_lc_bind($without, $map), $opposite));
    }
    $kids = array();
    foreach (preg_split('~(<pg-vis data-flag="[a-z0-9_]+"[^>]*>.*?</pg-vis>)~s', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $b) {
        $flag = preg_match('~^<pg-vis data-flag="([a-z0-9_]+)"[^>]*>(.*)</pg-vis>$~s', $b, $x) ? $x[1] : '';
        $part = ($flag !== '') ? $x[2] : $b;
        // A span's text is plain: a part holding markup (a line break, an
        // italic word) is read into the span's elements.
        $span = (strpos($part, '<') === false) ? pg_di_mk('semantic', array('tag' => 'span', 'cssClass' => '', 'text' => $part))
            : pg_di_mk('semantic', array('tag' => 'span', 'cssClass' => ''), array_map(function ($k) use ($map) { return _pg_lc_bind($k, $map); }, _pg_lfc_parse($part)));
        $kids[] = ($flag !== '') ? _pg_lc_flag($span, $flag) : _pg_lc_bind($span, $map);
    }
    if (($n['type'] ?? '') === 'content') {
        // A text block draws no children: the element of its tag holds
        // the spans instead, its class and bindings kept.
        $props = array('tag' => (string)($n['props']['tag'] ?? (($n['props']['contentType'] ?? '') === 'paragraph' ? 'p' : 'div')),
            'cssClass' => (string)($n['props']['cssClass'] ?? ''));
        foreach (array('_bindings', '_bindParams', 'id') as $k) {
            if (isset($n['props'][$k])) $props[$k] = $n['props'][$k];
        }
        return array(pg_di_mk('semantic', $props, $kids));
    }
    $n['props']['text'] = '';
    $n['children'] = $kids;
    return array($n);
}

// A node shown only under $flag; one that holds the list is entered
// instead, so the widget's empty message in the list's place is never cut
// with it, unless the widget has none there ($on_list).
function _pg_lc_flag($n, $flag, $on_list = false)
{
    if (($n['type'] ?? '') === 'loop_area') {
        if (!$on_list) return $n;
        return pg_di_mk('semantic', array('tag' => 'div', 'cssClass' => '', '_bindings' => array('eo_visible_if' => $flag)), array($n));
    }
    if (!$on_list && _pg_lc_find_loop($n) !== null) {
        foreach ($n['children'] as $i => $c) $n['children'][$i] = _pg_lc_flag($c, $flag);
        return $n;
    }
    // The same flag again (a condition repeated inside its own branch).
    if ((string)($n['props']['_bindings']['eo_visible_if'] ?? '') === (string)$flag) return $n;
    if (isset($n['props']['_bindings']['eo_visible_if'])) {
        return pg_di_mk('semantic', array('tag' => 'div', 'cssClass' => '', '_bindings' => array('eo_visible_if' => $flag)), array($n));
    }
    $n['props']['_bindings']['eo_visible_if'] = $flag;
    return $n;
}

function _pg_lc_bind($n, $map = array())
{
    // A token the markup reader percent-encoded in a link inside a text.
    if (isset($n['props']['text']) && is_string($n['props']['text']) && stripos($n['props']['text'], '%5E%5E') !== false) {
        $n['props']['text'] = preg_replace('~%5[eE]%5[eE](__[a-z0-9_]+)%5[eE]%5[eE]~', '^^$1^^', $n['props']['text']);
    }
    foreach (array('href', 'text', 'src') as $prop) {
        if (isset($n['props'][$prop]) && is_string($n['props'][$prop]) && preg_match('/^\s*\^\^(__[a-z0-9_]+)\^\^\s*$/', $n['props'][$prop], $m)) {
            $n['props']['_bindings'][$prop] = $m[1];
            $n['props'][$prop] = ($prop === 'href') ? '#' : '';
        }
    }
    $href = (string)($n['props']['_bindings']['href'] ?? '');
    $submits = (array)($map['submits'] ?? array());
    if ($href !== '' && isset($submits[$href])) {
        // The widget's form posts the choice; its handler goes on.
        $b = (array)$n['props']['_bindings'];
        unset($b['href']);
        $props = array('tag' => 'button', 'cssClass' => (string)($n['props']['cssClass'] ?? ''), 'text' => (string)($n['props']['text'] ?? ''),
            '_attrs' => array(array('name' => 'type', 'value' => 'submit'), array('name' => 'name', 'value' => (string)$submits[$href]), array('name' => 'formnovalidate', 'value' => '')));
        if ($b) $props['_bindings'] = $b;
        $n = pg_di_mk('semantic', $props, (array)($n['children'] ?? array()));
    }
    $controls = (array)($map['controls'] ?? array());
    $name = (string)_pg_lfc_attr($n, 'name');
    $id = (string)($n['props']['id'] ?? '');
    if ($name === '' && $id !== '' && isset($controls['#' . $id])) $name = '#' . $id;
    if ($name !== '' && isset($controls[$name]) && is_array($controls[$name])) {
        // The name and value the widget's rows give the control.
        foreach (array('name', 'value') as $attr) {
            if (isset($controls[$name][$attr])) _pg_lfc_set_attr($n, $attr, (string)$controls[$name][$attr]);
        }
    } elseif ($name !== '' && isset($controls[$name])) {
        $to = (string)$controls[$name];
        if (strpos($to, 'eo:') === 0) $n['props']['_bindings']['eo_field'] = substr($to, 3);
        if (strpos($to, 'bind:') === 0) $n['props']['_bindings']['value'] = substr($to, 5);
        if (strpos($to, 'action:') === 0) $n['props']['_bindings']['action'] = substr($to, 7);
        _pg_lfc_set_attr($n, 'name', ($to === '' || strpos($to, 'bind:') === 0 || strpos($to, 'action:') === 0 || strpos($to, 'eo:') === 0) ? null : $to);
    }
    return $n;
}

// Each link bound to ^^$token^^ bound to page $page_id instead (the shape
// of a link to a page, '__page_link' with no values): the widget draws its
// current address. Pure.
function _pg_lc_page_link($nodes, $token, $page_id)
{
    foreach ($nodes as $i => $n) {
        if ((string)($n['props']['_bindings']['href'] ?? '') === $token) {
            $nodes[$i]['props']['_bindings']['href'] = '__page_link';
            $nodes[$i]['props']['_bindParams']['href'] = array('page_id' => (int)$page_id, 'params' => array());
        }
        if (!empty($n['children'])) $nodes[$i]['children'] = _pg_lc_page_link($n['children'], $token, $page_id);
    }
    return $nodes;
}

// The nodes whose link the map unlinked ('unlink:') taken out. Pure.
function _pg_lc_unlink($nodes)
{
    $out = array();
    foreach ($nodes as $n) {
        if ((string)($n['props']['_bindings']['href'] ?? '') === '__pg_unlinked') continue;
        if (!empty($n['children'])) $n['children'] = _pg_lc_unlink($n['children']);
        $out[] = $n;
    }
    return $out;
}

// Each node shown under a flag of $when shown instead when its value passes
// the test (the widget's value conditions, show_if, show_if_2, show_if_3
// joined by 'any'). Pure.
function _pg_lc_when($nodes, $when)
{
    foreach ($nodes as $i => $n) {
        $flag = (string)($n['props']['_bindings']['eo_visible_if'] ?? '');
        if ($flag !== '' && isset($when[$flag])) {
            unset($nodes[$i]['props']['_bindings']['eo_visible_if']);
            $test = array('op' => (string)($when[$flag]['op'] ?? 'not_empty'));
            if (isset($when[$flag]['value'])) $test['value'] = (string)$when[$flag]['value'];
            foreach (array_slice(array_values((array)$when[$flag]['tokens']), 0, 3) as $k => $token) {
                $name = pg_sw_cond_names()[$k];
                $nodes[$i]['props']['_bindings'][$name] = (string)$token;
                $nodes[$i]['props']['_bindParams'][$name] = ($k === 0 && count((array)$when[$flag]['tokens']) > 1) ? $test + array('join' => 'any') : $test;
            }
        }
        if (!empty($n['children'])) $nodes[$i]['children'] = _pg_lc_when($n['children'], $when);
    }
    return $nodes;
}

// The raw blocks put back in place of their pg-raw markers.
function _pg_lc_raw($nodes, $raw)
{
    foreach ($nodes as $i => $n) {
        if (_pg_lfc_tag($n) === 'pg-raw') {
            $nodes[$i] = pg_di_mk('content', array('contentType' => 'custom_html', 'html' => (string)($raw[(int)_pg_lfc_attr($n, 'data-i')] ?? '')));
            continue;
        }
        if (!empty($n['children'])) $nodes[$i]['children'] = _pg_lc_raw($n['children'], $raw);
    }
    return $nodes;
}

// Whether a node of $node's tree is bound to $value as $prop.
function _pg_lc_bound($node, $prop, $value)
{
    foreach ((array)($node['children'] ?? array()) as $c) {
        if (!is_array($c)) continue;
        if ((string)($c['props']['_bindings'][$prop] ?? '') === $value || _pg_lc_bound($c, $prop, $value)) return true;
    }
    return false;
}

// The wrapper an image of ^^$token^^ alone fills (the image, or its
// outermost ancestor holding nothing else) shown only under $flag.
function _pg_lc_media($nodes, $token, $flag)
{
    foreach ($nodes as $i => $n) {
        if (_pg_lc_media_only($n, $token)) {
            $nodes[$i] = _pg_lc_flag($n, $flag);
            continue;
        }
        if (!empty($n['children'])) $nodes[$i]['children'] = _pg_lc_media($n['children'], $token, $flag);
    }
    return $nodes;
}

function _pg_lc_media_only($n, $token)
{
    if (($n['props']['_bindings']['src'] ?? '') === $token) return empty($n['children']);
    if (($n['type'] ?? '') !== 'semantic' || trim((string)($n['props']['text'] ?? '')) !== '') return false;
    $kids = (array)($n['children'] ?? array());
    return count($kids) === 1 && _pg_lc_media_only($kids[0], $token);
}

// The loops of a list's kinds made one: a flag wrapper holding only such a
// loop goes (its rows show only when the flag holds), and loops side by
// side in one parent are joined, their rows in order.
function _pg_lc_merge_loops($nodes)
{
    $out = array();
    foreach ($nodes as $n) {
        if (!empty($n['children'])) $n['children'] = _pg_lc_merge_loops($n['children']);
        $kids = (array)($n['children'] ?? array());
        if (($n['type'] ?? '') === 'semantic' && count($kids) === 1 && !empty($kids[0]['props']['_kinds'])
            && (string)($n['props']['cssClass'] ?? '') === '' && empty($n['props']['_attrs'])
            && array_keys((array)($n['props']['_bindings'] ?? array())) === array('eo_visible_if')) {
            $n = $kids[0];
        }
        $last = count($out) - 1;
        if (!empty($n['props']['_kinds']) && $last >= 0 && !empty($out[$last]['props']['_kinds'])) {
            $out[$last]['children'] = array_merge((array)$out[$last]['children'], (array)$n['children']);
            continue;
        }
        $out[] = $n;
    }
    return $out;
}

// The first loop area kept, every later one removed ($seen counts all).
function _pg_lc_first_loop($nodes, &$seen)
{
    $out = array();
    foreach ($nodes as $n) {
        if (($n['type'] ?? '') === 'loop_area') {
            $seen++;
            unset($n['props']['_kinds']);
            if ($seen > 1) continue;
            $out[] = $n;
            continue;
        }
        if (!empty($n['children'])) $n['children'] = _pg_lc_first_loop($n['children'], $seen);
        $out[] = $n;
    }
    return $out;
}
