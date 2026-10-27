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
            // Office-use-only fields are told apart by their rows: the
            // designed form hides them from visitors itself. The CAPTCHA
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
