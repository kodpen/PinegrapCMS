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
    return array('form item view', 'form list view', 'order form');
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
 * record: the widget's row template).
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
 * holds is the widget's).
 *
 * 'else': the flag the "otherwise" branch of a condition shows under,
 * 'keep' for one drawn as it is.
 * 'loop': a foreach: 'list' (the widget's list, its body the row's
 * wrapper).
 *
 * Optional: 'forms' => 'strip' (the layout's <form> tags go: the widget
 * draws its own form around the tree), 'flag_list' => true (a flag may
 * cover the list: the widget has no empty message in its place),
 * 'controls' => array(name in the layout => name the widget looks for),
 * 'submits' => array(token => control name: a link to that address
 * becomes a submit button of that name), 'media' => array(token => flag:
 * the wrapper an image of that address alone fills is shown under the
 * flag).
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
    if (($node['type'] ?? '') === 'loop_area') return $node;
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
    $drop = function ($piece, $at, $code_) use (&$out) {
        $out['dropped'][] = array('piece' => trim((string)$piece), 'line' => (int)$at, 'code' => (string)$code_);
    };
    $kept = function () use (&$stack) {
        foreach ($stack as $fr) if ($fr['mode'] === 'skip') return false;
        return true;
    };
    $in_tag = function () use (&$html) {
        return strrpos($html, '<') !== false && strrpos($html, '<') > (int)strrpos($html, '>');
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
                if ($in_tag()) break;
                $open = $close = '';
                foreach ((array)$p['flag'] as $f) {
                    $open .= '<pg-vis data-flag="' . h($f) . '">';
                    $close .= '</pg-vis>';
                }
                return array('mode' => 'flag', 'key' => $p['key'], 'open' => $open, 'close' => $close);
            case 'section':
                // The section's node is written whole at the start; what
                // the block holds is the widget's, never read.
                if ($in_tag()) break;
                return array('mode' => 'skip', 'key' => $p['key'], 'lead' => '<pg-section data-section="' . h($p['section']) . '"></pg-section>');
        }
        $drop($raw, $at, 'condition');
        return array('mode' => 'skip', 'key' => $p['key']);
    };
    $write = function ($text) use (&$stack, &$html) {
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if ($stack[$i]['mode'] === 'capture') { $stack[$i]['buf'] .= $text; return; }
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
            if (isset($fr['open'])) $write($fr['open']);
            $stack[] = $fr;
            continue;
        }
        if (preg_match('/^\}?\s*(else\s*if|elseif)\s*\((.+)\)\s*(:|\{)$/s', $src, $m) || preg_match('/^\}?\s*else\s*(:|\{)$/s', $src)) {
            if (!$stack) { if ($open_kept) $drop($src, $at, 'code'); continue; }
            $top = array_pop($stack);
            $outer = $kept();
            if (isset($top['close']) && $outer) $write($top['close']);
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
            if (isset($fr['open'])) $write($fr['open']);
            $stack[] = $fr;
            continue;
        }
        if (preg_match('/^foreach\s*\((.+)\)\s*(:|\{)$/s', $src, $m)) {
            if (!$open_kept) { $stack[] = array('mode' => 'skip'); continue; }
            $key = $norm($m[1]);
            if (isset($loops[$key]) && $loops[$key] === 'list' && !$loop_seen && !$in_tag()) {
                $loop_seen = true;
                $write('<pg-loop>');
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
                if (isset($top['close'])) $write($top['close']);
                if (($top['mode'] ?? '') === 'capture') $out['cfg']['empty_message'] = _pg_lc_words($top['buf']);
            }
            continue;
        }
        // Any other code: a block it opens is skipped whole.
        if ($open_kept) $drop($src, $at, 'code');
        if (preg_match('/(:|\{)$/', $src)) $stack[] = array('mode' => 'skip');
    }

    if (($map['forms'] ?? '') === 'strip') $html = preg_replace('~<form\b[^>]*>|</form\s*>~i', '', $html);
    $nodes = _pg_lfc_parse($html);
    $row = array_values((array)($ctx['row'] ?? array()));
    $nodes = _pg_lc_finish($nodes, $row, $map);
    foreach ((array)($map['media'] ?? array()) as $token => $flag) $nodes = _pg_lc_media($nodes, $token, $flag);
    if (_pg_lc_find_loop(array('children' => $nodes)) === null) {
        // A widget draws its records where its list stands: one at the end.
        $nodes[] = pg_di_mk('loop_area', array(), $row);
    }
    if (isset($out['cfg']['empty_message']) && $out['cfg']['empty_message'] === '') unset($out['cfg']['empty_message']);
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
    }
    // Interface words the template prints: the words themselves, which
    // the page's translation reads like any text.
    if (preg_match('/^(?:h\()?lang\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*\)\)?$/s', $src, $m) && (strpos($src, 'h(') !== 0 || substr($src, -2) === '))')) {
        return h(stripslashes($m[1]));
    }
    $drop($src, $at, 'value');
    return '';
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
            $out[] = pg_di_mk('loop_area', array(), $kids);
            continue;
        }
        if ($tag === 'pg-section') {
            $out[] = pg_di_mk('semantic', array('tag' => 'div', 'cssClass' => '', '_bindings' => array('section' => (string)_pg_lfc_attr($n, 'data-section'))));
            continue;
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
        $n['children'] = $kids;
        $out[] = _pg_lc_bind($n, $map);
    }
    return $out;
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
    if (isset($n['props']['_bindings']['eo_visible_if'])) {
        return pg_di_mk('semantic', array('tag' => 'div', 'cssClass' => '', '_bindings' => array('eo_visible_if' => $flag)), array($n));
    }
    $n['props']['_bindings']['eo_visible_if'] = $flag;
    return $n;
}

function _pg_lc_bind($n, $map = array())
{
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
    if ($name !== '' && isset($controls[$name])) _pg_lfc_set_attr($n, 'name', (string)$controls[$name]);
    return $n;
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
