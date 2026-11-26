<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

// A module of functions.php: the four lists of a design's "Clean up"
// window (style rules, files, shared components, variables nothing uses).
//
// Loaded with require_once by the designer endpoint, never on its own.
//
// pg_cleanup_design_lists() reads the site once: every page, region, style,
// shared component and the records whose text may name a class, a file or
// a variable. The list builders after it are pure. Like the finder
// (includes/fn/design_cleanup.php) every list errs towards "used": a name
// that cannot be matched plainly is never listed. Nothing here removes.
if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/design_cleanup.php');

/**
 * Adds the file names and the design variables a text refers to. A file
 * name is read without its folder and in lower case, as written and
 * URL-decoded; a variable by its own token (--pg-var-c-<key>).
 *
 * @param array  $refs  array('files' => name => true, 'vars' => token => true)
 * @param string $text
 * @return void
 */
function pg_cleanup_refs_add(&$refs, $text)
{
    $text = (string) $text;
    if ($text === '') {
        return;
    }
    if (preg_match_all('~[A-Za-z0-9_%.+\-]+\.[A-Za-z0-9]{2,5}\b~', $text, $m)) {
        foreach (array_unique($m[0]) as $name) {
            $name = strtolower($name);
            $refs['files'][$name] = true;
            $refs['files'][strtolower(rawurldecode($name))] = true;
        }
    }
    if (strpos($text, '--pg-var-c-') !== false && preg_match_all('~--pg-var-c-[a-z0-9\-]+~', $text, $m)) {
        foreach ($m[0] as $token) {
            $refs['vars'][$token] = true;
        }
    }
}

/**
 * The kinds of file whose text may name another file of the site: a
 * stylesheet's url() and @import, a script, an SVG's image, a page of
 * markup.
 *
 * @return array extensions, lower case
 */
function pg_cleanup_text_file_types()
{
    return array('css', 'js', 'mjs', 'json', 'svg', 'html', 'htm', 'xml', 'txt');
}

/**
 * Adds the file names the site's own text files refer to (a design
 * stylesheet's background picture or font), read from $dir one at a time.
 * A name with a folder, of another kind, missing or over 8 MB is skipped.
 *
 * @param array  $refs  pg_cleanup_refs_add()
 * @param array  $names file names
 * @param string $dir   the files directory
 * @return void
 */
function pg_cleanup_refs_add_files(&$refs, $names, $dir)
{
    $types = array_flip(pg_cleanup_text_file_types());
    foreach ((array) $names as $name) {
        $name = (string) $name;
        if ($name === '' || $name !== basename($name) || $name[0] === '.'
            || !isset($types[strtolower(pathinfo($name, PATHINFO_EXTENSION))])) {
            continue;
        }
        $path = rtrim((string) $dir, '/') . '/' . $name;
        if (!is_file($path) || filesize($path) > 8388608) {
            continue;
        }
        pg_cleanup_refs_add($refs, (string) file_get_contents($path));
    }
}

/**
 * Adds one page: its tree and code as classes, and the file names and
 * variables they, its meta tags and its structured data refer to (a share
 * picture, a logo). The last two are read when the row carries them.
 *
 * @param array $state
 * @param array $refs
 * @param array $row page_name, page_tree_json, page_tree_code, optional page_meta_tags, custom_jsonld
 * @return void
 */
function pg_cleanup_add_page_row(&$state, &$refs, $row)
{
    $name = isset($row['page_name']) ? (string) $row['page_name'] : '';
    $json = isset($row['page_tree_json']) ? (string) $row['page_tree_json'] : '';
    $code = isset($row['page_tree_code']) ? (string) $row['page_tree_code'] : '';
    pg_cleanup_add_tree($state, json_decode($json, true), $name);
    pg_cleanup_add_markup($state, $code, $name);
    $text = array($json, $code);
    foreach (array('page_meta_tags', 'custom_jsonld') as $col) {
        if (isset($row[$col])) {
            $text[] = (string) $row[$col];
        }
    }
    pg_cleanup_refs_add($refs, implode("\n", $text));
}

/**
 * The classes a framework stylesheet defines: never counted as unused, as a
 * widget or a node setting draws them without naming them in a tree.
 *
 * @param string $css
 * @return array
 */
function pg_cleanup_framework_classes($css)
{
    if (!preg_match_all('~\.(-?[A-Za-z_][A-Za-z0-9_\-]*)~', (string) $css, $m)) {
        return array();
    }
    return array_values(array_unique($m[1]));
}

/**
 * The design's own stylesheets: the inline CSS rows of its asset list and
 * its handwritten Styles, never a linked sheet (a library's, the look's)
 * nor a framework row.
 *
 * @param string $json style_custom_css
 * @return array of array('name', 'css', 'index'): index is the row's place in the list
 */
function pg_cleanup_own_sheets($json)
{
    $rows = json_decode((string) $json, true);
    $out = array();
    foreach (is_array($rows) ? array_values($rows) : array() as $index => $row) {
        if (!is_array($row) || !isset($row['content']) || !is_string($row['content']) || trim($row['content']) === '') {
            continue;
        }
        $meta = isset($row['meta']) ? (string) $row['meta'] : '';
        $type = isset($row['type']) ? (string) $row['type'] : 'css';
        if (($meta !== '' && $meta !== 'user-styles') || $type !== 'css') {
            continue;
        }
        $name = isset($row['name']) && is_scalar($row['name']) && trim((string) $row['name']) !== '' ? trim((string) $row['name']) : '';
        if ($meta === 'user-styles') {
            $name = lang('Styles');
        }
        $out[] = array('name' => $name !== '' ? $name : lang('Stylesheet'), 'css' => $row['content'], 'index' => $index);
    }
    return $out;
}

/**
 * A rule's id in the window: its sheet's place, its start and its text, so
 * a rule that moved or changed since the list was read is never matched.
 *
 * @param int    $index the sheet's place in style_custom_css
 * @param int    $start
 * @param string $text  the whole rule
 * @return string
 */
function pg_cleanup_rule_id($index, $start, $text)
{
    return (int) $index . '-' . (int) $start . '-' . substr(md5((string) $text), 0, 10);
}

/**
 * The style rules list: every sheet's rules nothing uses, the largest
 * first, each with its sheet, the classes nothing uses, its media
 * condition, its size, its text (for "Show") and its id and place (for
 * the removal, design_cleanup_remove.php). A sheet the finder could
 * not tell about lists nothing and gets a note: its name, the reason
 * (bound_class, dynamic_class, unreadable, script_unread) and where.
 *
 * @param array $sheets pg_cleanup_own_sheets()
 * @param array $state  pg_cleanup_state(), filled
 * @return array rows, bytes, notes
 */
function pg_cleanup_rule_list($sheets, $state)
{
    $out = array('rows' => array(), 'bytes' => 0, 'notes' => array());
    foreach ($sheets as $sheet) {
        $found = pg_cleanup_unused_rules($sheet['css'], $state);
        if ($found['blocked'] !== '') {
            $out['notes'][] = array(
                'sheet'  => $sheet['name'],
                'reason' => $found['blocked'],
                'at'     => $found['blocked'] === 'unreadable' ? '' : (string) $state['blocked_at'],
            );
            continue;
        }
        foreach ($found['rows'] as $row) {
            $text = substr($sheet['css'], $row['start'], $row['end'] - $row['start']);
            $index = isset($sheet['index']) ? (int) $sheet['index'] : 0;
            $out['rows'][] = array(
                'id'       => pg_cleanup_rule_id($index, $row['start'], $text),
                'index'    => $index,
                'start'    => $row['start'],
                'end'      => $row['end'],
                'sheet'    => $sheet['name'],
                'selector' => $row['selector'],
                'classes'  => $row['classes'],
                'media'    => $row['media'],
                'bytes'    => $row['bytes'],
                'text'     => strlen($text) > 4000 ? substr($text, 0, 4000) . "\n…" : $text,
            );
            $out['bytes'] += $row['bytes'];
        }
    }
    usort($out['rows'], function ($a, $b) {
        return $b['bytes'] - $a['bytes'];
    });
    return $out;
}

/**
 * The files list: design files no text refers to, the largest first. A
 * name with any character beyond letters, digits, dot, dash and underscore
 * cannot be matched plainly and is never listed, nor is a name in $skip
 * (the design's variables file, a custom look or palette: found by name
 * or by id, not by a reference).
 *
 * @param array $files rows id, name, size, timestamp
 * @param array $refs  pg_cleanup_refs_add()
 * @param array $skip  lower-case names
 * @return array rows, bytes
 */
function pg_cleanup_file_list($files, $refs, $skip = array())
{
    $skip = array_flip($skip);
    $out = array('rows' => array(), 'bytes' => 0);
    foreach ($files as $f) {
        $name = (string) $f['name'];
        $low = strtolower($name);
        if (!preg_match('~^[A-Za-z0-9_.\-]+\.[A-Za-z0-9]{2,5}$~', $name) || isset($skip[$low]) || isset($refs['files'][$low])) {
            continue;
        }
        $out['rows'][] = array(
            'id'    => (int) $f['id'],
            'name'  => $name,
            'bytes' => (int) $f['size'],
            'time'  => (int) $f['timestamp'],
            'image' => (bool) preg_match('~\.(jpe?g|png|gif|webp|avif|svg)$~i', $name),
        );
        $out['bytes'] += (int) $f['size'];
    }
    usort($out['rows'], function ($a, $b) {
        return $b['bytes'] - $a['bytes'];
    });
    return $out;
}

/**
 * The shared components list: those placed on no page (pg_shared_component_usage(),
 * the Recycle Bin and region tags included) and inside no other component,
 * by name. A tree up to 64 KB comes along for "Show".
 *
 * @param array $components rows id, name, tree_json
 * @param array $usage      id => places
 * @param array $nested     id => true for every id another component places
 * @return array rows
 */
function pg_cleanup_component_list($components, $usage, $nested)
{
    $out = array('rows' => array());
    foreach ($components as $c) {
        $id = (int) $c['id'];
        if (!empty($usage[$id]) || isset($nested[$id]) || !isset($usage[$id])) {
            continue;
        }
        $json = (string) $c['tree_json'];
        $tree = strlen($json) <= 65536 ? json_decode($json, true) : null;
        $out['rows'][] = array('id' => $id, 'name' => (string) $c['name'], 'tree' => is_array($tree) ? $tree : null);
    }
    usort($out['rows'], function ($a, $b) {
        return strnatcasecmp($a['name'], $b['name']);
    });
    return $out;
}

/**
 * The variables list: the design's own variables ("Your own") whose token
 * nothing reads, by name. The built-in ones drive the look's tokens, so
 * they are always read.
 *
 * @param array $custom the model's custom rows (key, name, kind, light, dark, value)
 * @param array $refs   pg_cleanup_refs_add()
 * @return array rows
 */
function pg_cleanup_variable_list($custom, $refs)
{
    $out = array('rows' => array());
    foreach (is_array($custom) ? $custom : array() as $c) {
        if (!is_array($c) || !isset($c['key'], $c['kind']) || !is_scalar($c['key'])) {
            continue;
        }
        $token = '--pg-var-c-' . str_replace('_', '-', (string) $c['key']);
        if (isset($refs['vars'][$token])) {
            continue;
        }
        $out['rows'][] = array(
            'key'   => (string) $c['key'],
            'name'  => isset($c['name']) && is_scalar($c['name']) ? (string) $c['name'] : (string) $c['key'],
            'token' => $token,
            'kind'  => (string) $c['kind'],
            'value' => $c['kind'] === 'color' ? (isset($c['light']) ? (string) $c['light'] : '') : (isset($c['value']) ? (string) $c['value'] : ''),
        );
    }
    usort($out['rows'], function ($a, $b) {
        return strnatcasecmp($a['name'], $b['name']);
    });
    return $out;
}

/**
 * The texts of the records outside the designer that may name a class or a
 * file: table, id column, columns and how a column is read (markup or
 * script). A table or column the site lacks is left out.
 *
 * @return array
 */
function pg_cleanup_text_sources()
{
    return array(
        array('pregion', 'pregion_id', array('pregion_content'), 'markup'),
        array('cregion', 'cregion_id', array('cregion_content'), 'markup'),
        array('dregion', 'dregion_id', array('dregion_code'), 'script'),
        array('products', 'id', array('image_name', 'full_description', 'details', 'short_description'), 'markup'),
        array('product_groups', 'id', array('image_name', 'description', 'short_description'), 'markup'),
        array('calendar_events', 'id', array('full_description'), 'markup'),
        array('email_campaigns', 'id', array('body'), 'markup'),
        array('ads', 'id', array('content'), 'markup'),
    );
}

/**
 * Calls $fn on every row of a table, 200 rows a query, so a large site is
 * never held in memory at once.
 *
 * @param string   $table
 * @param string   $id
 * @param array    $columns
 * @param callable $fn
 * @param string   $from    the FROM part when it is more than the table
 * @return void
 */
function pg_cleanup_each_row($table, $id, $columns, $fn, $from = '')
{
    $last = 0;
    $from = $from !== '' ? $from : $table;
    do {
        $rows = db_items("SELECT $table.$id AS row_id, " . implode(', ', $columns) . " FROM $from"
            . " WHERE $table.$id > '" . (int) $last . "' ORDER BY $table.$id LIMIT 200");
        $rows = is_array($rows) ? $rows : array();
        foreach ($rows as $row) {
            $last = (int) $row['row_id'];
            $fn($row);
        }
    } while (count($rows) === 200);
}

/**
 * The four lists of a design's "Clean up" window, read from the saved
 * design; null for a design that is not a visual designer one.
 *
 * @param int $style_id
 * @return array|null design, rules, files, components, variables, model
 */
function pg_cleanup_design_lists($style_id)
{
    $style_id = (int) $style_id;
    $style = db_item("SELECT style_id, style_name, style_custom_css, style_custom_js FROM style"
        . " WHERE style_id = '$style_id' AND style_layout = 'visual_designer' LIMIT 1");
    if (!$style) {
        return null;
    }

    $fw = PG_FUNCTIONS_DIR . '/assets/lib/bootstrap-5.3.8/css/bootstrap.min.css';
    $state = pg_cleanup_state(array('known' => pg_cleanup_framework_classes(is_file($fw) ? file_get_contents($fw) : '')));
    $refs = array('files' => array(), 'vars' => array());

    // The site's own script that sets classes on the design's elements.
    $fx = PG_FUNCTIONS_DIR . '/assets/js/pg_fx.src.js';
    pg_cleanup_add_script($state, is_file($fx) ? file_get_contents($fx) : '', 'pg_fx.js');

    // Every page's tree and code: another design's page counts too.
    // A page's meta tags and structured data may name a share picture.
    if (pg_schema_has('page', 'page_tree_json')) {
        $page_cols = array('page_name', 'page_tree_json', 'page_tree_code');
        foreach (array('page_meta_tags', 'custom_jsonld') as $col) {
            if (pg_schema_has('page', $col)) {
                $page_cols[] = $col;
            }
        }
        pg_cleanup_each_row('page', 'page_id', $page_cols, function ($row) use (&$state, &$refs) {
            pg_cleanup_add_page_row($state, $refs, $row);
        });
    }

    $columns = array('style_name', 'style_code', 'style_head', 'style_tree_json', 'style_custom_css', 'style_custom_js');
    pg_cleanup_each_row('style', 'style_id', $columns, function ($row) use (&$state, &$refs, $style_id) {
        $source = (string) $row['style_name'];
        pg_cleanup_add_tree($state, json_decode((string) $row['style_tree_json'], true), $source);
        pg_cleanup_add_markup($state, $row['style_code'] . "\n" . $row['style_head'], $source);
        pg_cleanup_refs_add($refs, implode("\n", array($row['style_code'], $row['style_head'], $row['style_tree_json'], $row['style_custom_css'], $row['style_custom_js'])));
        if ((int) $row['row_id'] === $style_id) {
            pg_cleanup_add_design_scripts($state, (string) $row['style_custom_js']);
        }
    });

    $components = array();
    $nested = array();
    pg_cleanup_each_row('shared_components', 'id', array('name', 'tree_json'), function ($row) use (&$state, &$refs, &$components, &$nested) {
        $json = (string) $row['tree_json'];
        pg_cleanup_add_tree($state, json_decode($json, true), (string) $row['name']);
        pg_cleanup_refs_add($refs, $json);
        if (preg_match_all('/"sharedId":\s*"?(\d+)/', $json, $m)) {
            foreach ($m[1] as $sid) {
                if ((int) $sid !== (int) $row['row_id']) {
                    $nested[(int) $sid] = true;
                }
            }
        }
        $components[] = array('id' => (int) $row['row_id'], 'name' => (string) $row['name'], 'tree_json' => $json);
    });

    foreach (pg_cleanup_text_sources() as $src) {
        $cols = array();
        foreach ($src[2] as $col) {
            if (pg_schema_has($src[0], $col)) {
                $cols[] = $col;
            }
        }
        if (!$cols || !pg_schema_has($src[0], $src[1])) {
            continue;
        }
        pg_cleanup_each_row($src[0], $src[1], $cols, function ($row) use (&$state, &$refs, $cols, $src) {
            foreach ($cols as $col) {
                if ($src[3] === 'script') {
                    pg_cleanup_add_script($state, (string) $row[$col], $src[0]);
                } else {
                    pg_cleanup_add_markup($state, (string) $row[$col], $src[0]);
                }
                pg_cleanup_refs_add($refs, (string) $row[$col]);
            }
        });
    }

    // A system widget draws its default layout when its own tree is empty.
    if (function_exists('pg_sw_starter_kinds') && function_exists('pg_sw_starter_layouts')) {
        foreach (pg_sw_starter_kinds() as $kind) {
            foreach (pg_sw_starter_layouts($kind) as $layout) {
                pg_cleanup_add_tree($state, array('children' => array_merge($layout['body'], $layout['loop'])), '');
                pg_cleanup_add_tree($state, $layout['wrap'], '');
            }
        }
    }

    // Settings that hold a file name (a share image, a logo, an icon).
    $config = db_item("SELECT * FROM config LIMIT 1");
    pg_cleanup_refs_add($refs, is_array($config) ? implode("\n", array_map('strval', $config)) : '');

    // The site's own text files: a design stylesheet's background picture
    // or font is named only inside it.
    if (defined('FILE_DIRECTORY_PATH')) {
        $likes = array();
        foreach (pg_cleanup_text_file_types() as $ext) {
            $likes[] = "files.name LIKE '%." . $ext . "'";
        }
        $text_files = db_values("SELECT files.name FROM files WHERE " . implode(' OR ', $likes));
        pg_cleanup_refs_add_files($refs, is_array($text_files) ? $text_files : array(), FILE_DIRECTORY_PATH);
    }

    $usage = pg_shared_component_usage(array_map(function ($c) {
        return $c['id'];
    }, $components));

    $skip = array(strtolower(pg_design_variables_file_name($style_id)));
    foreach (pg_design_theme_files() as $kind) {
        foreach ($kind as $f) {
            $skip[] = strtolower($f['file']);
        }
    }
    $files = db_items("SELECT files.id, files.name, files.size, files.timestamp, files.folder FROM files WHERE files.design = '1'");
    $bin = array_flip(pg_recycle_bin_folder_ids());
    $files = array_values(array_filter(is_array($files) ? $files : array(), function ($f) use ($bin) {
        return !isset($bin[(int) $f['folder']]);
    }));

    $stored = pg_design_variables_stored($style_id);

    return array(
        'design'     => (string) $style['style_name'],
        'rules'      => pg_cleanup_rule_list(pg_cleanup_own_sheets($style['style_custom_css']), $state),
        'files'      => pg_cleanup_file_list($files, $refs, $skip),
        'components' => pg_cleanup_component_list($components, $usage, $nested),
        'variables'  => pg_cleanup_variable_list(is_array($stored) && isset($stored['custom']) ? $stored['custom'] : array(), $refs),
        // The stored variables model, kept in the snapshot "Before clean-up".
        'model'      => is_array($stored) ? $stored : null,
    );
}

/**
 * Adds the design's own scripts: an inline one by its words, a linked file
 * of the site by its text. A script that cannot be read (another site's,
 * a file gone) may set any class, so nothing can be told.
 *
 * @param array  $state
 * @param string $json style_custom_js
 * @return void
 */
function pg_cleanup_add_design_scripts(&$state, $json)
{
    $rows = json_decode((string) $json, true);
    foreach (is_array($rows) ? $rows : array() as $row) {
        if (!is_array($row) || !empty($row['meta']) || (isset($row['enabled']) && $row['enabled'] === false)) {
            continue;
        }
        $content = isset($row['content']) && is_string($row['content']) ? $row['content'] : '';
        $type = isset($row['type']) ? (string) $row['type'] : 'js';
        if (strpos($type, 'external') !== 0) {
            pg_cleanup_add_script($state, $content, '');
            continue;
        }
        $name = basename((string) parse_url(trim($content), PHP_URL_PATH));
        $local = (strpos(trim($content), '/') === 0 && strpos(trim($content), '//') !== 0)
            && $name !== '' && is_file(FILE_DIRECTORY_PATH . '/' . $name);
        if ($local) {
            pg_cleanup_add_script($state, (string) file_get_contents(FILE_DIRECTORY_PATH . '/' . $name), $name);
        } else {
            pg_cleanup_block($state, 'script_unread', $name !== '' ? $name : trim($content));
        }
    }
}
