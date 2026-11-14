<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - live pages: an HTML document an agent writes, kept as an
 * .html file of the channel with its versions, shown in the guarded frame
 * (includes/fn/guarded_frame.php, live_page.php). The routes are in
 * api_page.php.
 *
 * What marks a page, with no column of its own: channels refuse an .html
 * upload (ws_upload_refused_extensions()), so an .html file reaches a
 * channel only through these routes, and each of its versions is a
 * file_versions row of format html that the routes wrote. A file with no
 * such row is no page and is never shown with its script.
 *
 * A version row keeps the page's bytes under data/file_versions/ and, as
 * its document, who made it: {"page": 1, "by": {id, kind, handle}}.
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
 * How many versions of a page are kept: the first and the newest after it.
 *
 * @return int
 */
function ws_page_versions_keep()
{
    return 20;
}

/**
 * The name a page's file is kept under: the name given without an
 * extension or the letters a file name cannot carry, else "Page", always
 * .html. Pure but for lang().
 *
 * @param mixed $name
 * @return string
 */
function ws_page_file_name($name)
{
    $base = trim(preg_replace('/\.[a-z0-9]{2,5}$/i', '', is_string($name) ? $name : ''));
    $base = trim(preg_replace('/[\\\\\/:*?"<>|\x00-\x1f]+/', ' ', $base));

    if ($base === '') {
        $base = lang('Page');
    }

    return mb_substr($base, 0, 120) . '.html';
}

/**
 * Why a page's HTML is refused, or ''. Pure.
 *
 * @param mixed $html
 * @return string '' when it may be kept; empty, too_large or not_utf8
 */
function ws_page_html_error($html)
{
    if (!is_string($html) || (trim($html) === '')) {
        return 'empty';
    }

    if (strlen($html) > PG_GUARDED_FRAME_MAX_BYTES) {
        return 'too_large';
    }

    if (!preg_match('//u', $html)) {
        return 'not_utf8';
    }

    return '';
}

/**
 * The refusal's words for ws_page_html_error().
 *
 * @param string $error
 * @return string
 */
function ws_page_html_error_text($error)
{
    if ($error === 'too_large') {
        return lang(array('string' => 'A page is at most {var:1} KB.', 'vars' => (int) (PG_GUARDED_FRAME_MAX_BYTES / 1024)));
    }

    if ($error === 'not_utf8') {
        return lang('A page must be written in UTF-8.');
    }

    return lang('A page needs its HTML.');
}

/**
 * The document a version row keeps: who made it. Pure.
 *
 * @param array    $agent     id, kind, handle
 * @param string[] $libraries the site's libraries it names
 * @return string JSON
 */
function ws_page_version_doc($agent, $libraries = array())
{
    return json_encode(array('page' => 1, 'by' => array(
        'id'     => (int) ($agent['id'] ?? 0),
        'kind'   => (string) ($agent['kind'] ?? ''),
        'handle' => (string) ($agent['handle'] ?? ''),
    ), 'libraries' => array_values($libraries)), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

/**
 * The agent a version's document names, or null. Pure.
 *
 * @param mixed $doc_json
 * @return array|null id, kind, handle
 */
function ws_page_version_by($doc_json)
{
    $doc = json_decode((string) $doc_json, true);
    $by = (is_array($doc) && isset($doc['by']) && is_array($doc['by'])) ? $doc['by'] : null;

    if (!$by || !isset($by['handle']) || !is_string($by['handle']) || ($by['handle'] === '')) {
        return null;
    }

    return array('id' => (int) ($by['id'] ?? 0), 'kind' => (string) ($by['kind'] ?? ''), 'handle' => $by['handle']);
}

/**
 * The version ids the retention removes: all but version 1 and the newest
 * ws_page_versions_keep() - 1, oldest first. Pure.
 *
 * @param array[] $rows version_id, version_no
 * @return int[]
 */
function ws_page_versions_pruned($rows)
{
    $later = array();

    foreach ($rows as $row) {
        if ((int) $row['version_no'] > 1) {
            $later[(int) $row['version_no']] = (int) $row['version_id'];
        }
    }

    krsort($later);
    $removed = array_slice($later, ws_page_versions_keep() - 1, null, true);
    ksort($removed);

    return array_values($removed);
}

/**
 * The versions of a page, oldest first, without their bytes; empty for a
 * file that is no page.
 *
 * @param int $file_id
 * @return array[]
 */
function ws_page_versions($file_id)
{
    if (((int) $file_id <= 0) || !pg_file_versions_ready()) {
        return array();
    }

    $rows = db_items("SELECT version_id, version_no, doc_json, size, user_id, source, created_at, stored_name
        FROM file_versions WHERE file_id = '" . (int) $file_id . "' AND format = 'html' ORDER BY version_no");

    return is_array($rows) ? $rows : array();
}

/**
 * Is this file a page: an .html file with a version the routes wrote?
 *
 * @param int $file_id
 * @return bool
 */
function ws_page_is_page($file_id)
{
    return ((int) $file_id > 0) && pg_file_versions_ready()
        && ((int) db_value("SELECT COUNT(*) FROM file_versions WHERE file_id = '" . (int) $file_id . "' AND format = 'html'") > 0);
}

/**
 * Keeps the page's bytes as its next version.
 *
 * @param int      $file_id
 * @param string   $html
 * @param array    $agent     id, kind, handle
 * @param int      $user_id   the application's owner
 * @param string[] $libraries the site's libraries it names
 * @param string   $source    agent, or restore for an Undo (ws_page_undo())
 * @return array|null the row, or null when it could not be written
 */
function ws_page_version_add($file_id, $html, $agent, $user_id, $libraries = array(), $source = 'agent')
{
    $dir = pg_file_versions_dir();

    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return null;
    }

    $version_no = (int) db_value("SELECT MAX(version_no) FROM file_versions WHERE file_id = '" . (int) $file_id . "'") + 1;
    $name = pg_file_versions_stored_name($file_id, $version_no, 'html', sha1(uniqid((string) mt_rand(), true) . $html));
    $path = $dir . '/' . $name;

    if (($name === '') || !pg_file_write_whole($path, $html)) {
        return null;
    }

    $doc = ws_page_version_doc($agent, $libraries);
    $saved = db_q("INSERT INTO file_versions (file_id, version_no, doc_json, doc_hash, stored_name, format, width, height, size, redacted, user_id, source, created_at)
        VALUES (?, ?, ?, ?, ?, 'html', 0, 0, ?, 0, ?, ?, UNIX_TIMESTAMP())", array(
        (int) $file_id, $version_no, $doc, sha1($html), $name, strlen($html), (int) $user_id, ($source === 'restore') ? 'restore' : 'agent'));

    if (!$saved) {
        @unlink($path);

        return null;
    }

    return pg_file_versions_row($file_id, mysqli_insert_id(db::$con));
}

/**
 * Keeps the first version and the newest after it.
 *
 * @param int $file_id
 */
function ws_page_versions_prune($file_id)
{
    $rows = ws_page_versions($file_id);
    $ids = ws_page_versions_pruned($rows);

    foreach ($rows as $row) {
        if (in_array((int) $row['version_id'], $ids, true)) {
            pg_file_versions_delete_row($row);
        }
    }
}

/**
 * Writes a new version of a page into its file and keeps it.
 *
 * @param array    $message   ws_messages row of the page
 * @param string   $html      checked by ws_page_html_error()
 * @param array    $agent
 * @param int      $user_id
 * @param string[] $libraries the site's libraries it names
 * @param string   $source    agent, or restore for an Undo
 * @return bool
 */
function ws_page_write($message, $html, $agent, $user_id, $libraries = array(), $source = 'agent')
{
    $file_id = (int) $message['file_id'];
    $stored = (string) db_value("SELECT name FROM files WHERE id = '" . $file_id . "'");

    // Every write goes to the file directory, which the general job takes
    // as the file's bytes (pg_file_storage_reconcile_run()).
    $name = basename($stored);
    $path = FILE_DIRECTORY_PATH . '/' . $name;

    if (($stored === '') || !pg_file_write_whole($path, $html)) {
        return false;
    }

    db_q("UPDATE files SET size = ?, timestamp = UNIX_TIMESTAMP() WHERE id = ?", array(strlen($html), $file_id));

    if (function_exists('pg_file_cache_forget')) {
        pg_file_cache_forget($file_id);
    }

    if (!ws_page_version_add($file_id, $html, $agent, $user_id, $libraries, $source)) {
        return false;
    }

    ws_page_versions_prune($file_id);

    if (function_exists('ws_message_touch')) {
        ws_message_touch((int) $message['id']);
    }

    return true;
}

/**
 * A page's versions as the API gives them, oldest first. Pure but for
 * api_time() when given.
 *
 * @param array[]       $rows ws_page_versions()
 * @param callable|null $time formats created_at
 * @return array[] version, source, user_id, agent_id, agent, size, created_at
 */
function ws_page_versions_read($rows, $time = null)
{
    $read = array();

    foreach ($rows as $row) {
        $by = ws_page_version_by($row['doc_json'] ?? '');

        $read[] = array(
            'version'    => (int) $row['version_no'],
            'source'     => (string) $row['source'],
            'user_id'    => ((int) $row['user_id'] > 0) ? (int) $row['user_id'] : null,
            'agent_id'   => ($by && ($by['id'] > 0)) ? $by['id'] : null,
            'agent'      => $by ? $by['handle'] : null,
            'size'       => (int) $row['size'],
            'created_at' => $time ? $time((int) $row['created_at']) : (int) $row['created_at'],
        );
    }

    return $read;
}

/**
 * The libraries a page may name, by the name a call gives: each a copy the
 * site ships, put into the page by the site (ws_page_with_libraries()); a
 * page never loads one from an address. Pure.
 *
 * @return array[] name => path (a file under the software directory) or
 *                 build (a function writing the script), needs (names
 *                 put in ahead of it), title
 */
function ws_page_libraries()
{
    return array(
        'chartjs'     => array('path' => 'assets/lib/chartjs/chart.umd.min.js', 'title' => 'Chart.js 4.4.7'),
        'chart_guide' => array(
            'build' => 'ws_page_chart_guide_script',
            'needs' => array('chartjs'),
            'title' => 'the drawing guide\'s look for Chart.js: series colours, type, bars, lines, donuts and gridlines in a dark and a light set, the set following the reader\'s colour scheme or <html data-theme="dark|light">; it brings chartjs, leave the charts\' colours and fonts unset, use var(--pg-bg), var(--pg-ink), var(--pg-ink2), var(--pg-font) for the page around them and PgChartGuide.series(i) for a colour by hand',
        ),
    );
}

/**
 * A colour mixed into another, as #rrggbb: $share percent of $colour over
 * $base. Pure.
 *
 * @param string $colour #rrggbb
 * @param string $base   #rrggbb
 * @param int    $share  0-100
 * @return string
 */
function ws_page_chart_guide_mix($colour, $base, $share)
{
    $out = '#';

    for ($i = 1; $i < 7; $i += 2) {
        $a = hexdec(substr($colour, $i, 2));
        $b = hexdec(substr($base, $i, 2));
        $out .= sprintf('%02x', (int) round(($a * $share + $b * (100 - $share)) / 100));
    }

    return $out;
}

/**
 * The Chart.js preset's values, taken from the drawing guide
 * (ws_canvas_guide_values()) so a live page's chart and a drawn one share
 * one palette and one type scale. The guide's pixels are for a 1200-wide
 * board a channel shows at about half size; a live page is read at its own
 * size, so its type and marks are the guide's times 2/3, never under 12 for
 * text. Pure.
 *
 * @return array
 */
function ws_page_chart_guide_values()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/workspace/canvas_guide.php');

    $g = ws_canvas_guide_values();
    $m = $g['marks'];
    $px = function ($value, $least = 1) {
        return max($least, (int) round($value * 2 / 3));
    };

    $sets = array();

    foreach ($g['palettes'] as $mode => $p) {
        $sets[$mode] = array(
            'bg'       => $p['background'],
            'ink'      => $p['ink'],
            'ink2'     => $p['ink2'],
            'ink3'     => $p['ink3'],
            'grid'     => $p['grid'],
            'baseline' => $p['baseline'],
            'series'   => $p['series'],
            'area'     => array_map(function ($c) use ($p, $m) {
                return ws_page_chart_guide_mix($c, $p['background'], $m['area_mix']);
            }, $p['series']),
            'other'    => $p['other'],
            'up'       => $p['up'],
            'down'     => $p['down'],
        );
    }

    $type = array();

    foreach ($g['type'] as $role => $row) {
        $type[$role] = array('size' => $px($row['size'], 12), 'weight' => $row['weight']);
    }

    return array(
        'font'  => 'Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
        'type'  => $type,
        'sets'  => $sets,
        'marks' => array(
            'bar_max'    => $m['bar_max'],
            'bar_band'   => $m['bar_band'],
            'bar_radius' => $px($m['bar_radius']),
            'line'       => $px($m['line'], 2),
            'dot'        => $px($m['dot'], 3),
            'ring'       => $px($m['ring'], 2),
            'grid'       => $m['grid'],
            'baseline'   => $m['baseline'],
            'cutout'     => (int) round($m['donut_inner'] / $m['donut_outer'] * 100),
            'slice_gap'  => $m['stack_gap'],
            'ticks_max'  => $m['ticks_max'] + 1,
            'series_max' => $m['series_max'],
            'padding'    => $px($g['grid']['margin'] / 2),
        ),
    );
}

/**
 * The preset's script, put into a page that names chart_guide after
 * Chart.js. It sets Chart.defaults to the guide's look, gives a dataset
 * that names no colour its series colour (a donut's slices one each, past
 * the fifth the guide's other), writes the set's colours as CSS variables
 * on <html>, and follows the reader's colour scheme or the page's own
 * data-theme, recolouring the charts when either changes. Without Chart.js
 * it only writes the variables.
 *
 * @return string
 */
function ws_page_chart_guide_script()
{
    $values = json_encode(ws_page_chart_guide_values(), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

    return '(function(){"use strict";var G=' . $values . ',root=document.documentElement,mode="light",auto="_pgGuide";'
        . 'function pick(){var t=root.getAttribute("data-theme");if(t==="dark"||t==="light"){return t;}'
        . 'return (window.matchMedia&&window.matchMedia("(prefers-color-scheme: dark)").matches)?"dark":"light";}'
        . 'function set(){return G.sets[mode];}'
        . 'function series(i){var s=set();return i<G.marks.series_max?s.series[i%s.series.length]:s.other;}'
        . 'function area(i){var s=set();return i<G.marks.series_max?s.area[i%s.area.length]:s.other;}'
        . 'function vars(){var s=set(),k={bg:s.bg,ink:s.ink,ink2:s.ink2,ink3:s.ink3,grid:s.grid,baseline:s.baseline,up:s.up,down:s.down};'
        . 'for(var n in k){root.style.setProperty("--pg-"+n,k[n]);}root.style.setProperty("--pg-font",G.font);root.style.colorScheme=mode;}'
        . 'function font(role){return {family:G.font,size:G.type[role].size,weight:G.type[role].weight};}'
        . 'function defaults(C){var s=set(),d=C.defaults,m=G.marks;'
        . 'd.font.family=G.font;d.font.size=G.type.axis.size;d.color=s.ink2;d.borderColor=s.grid;d.backgroundColor=s.series[0];'
        . 'if(root.lang){d.locale=root.lang;}d.layout.padding=m.padding;'
        . 'd.plugins.colors&&(d.plugins.colors.enabled=false);d.plugins.filler&&(d.plugins.filler.drawTime="beforeDatasetsDraw");'
        . 'd.plugins.title.align="start";d.plugins.title.color=s.ink;d.plugins.title.font=font("label");d.plugins.title.font.size=G.type.subtitle.size;d.plugins.title.font.weight=700;'
        . 'd.plugins.subtitle.align="start";d.plugins.subtitle.color=s.ink2;d.plugins.subtitle.font=font("axis");'
        . 'var l=d.plugins.legend;l.align="start";l.labels.color=s.ink2;l.labels.font=font("label");l.labels.usePointStyle=true;l.labels.pointStyle="rectRounded";l.labels.boxWidth=G.type.label.size*0.7;l.labels.boxHeight=G.type.label.size*0.7;'
        . 'var t=d.plugins.tooltip;t.backgroundColor=s.ink;t.titleColor=s.bg;t.bodyColor=s.bg;t.footerColor=s.bg;t.titleFont=font("label");t.bodyFont=font("axis");t.cornerRadius=m.bar_radius;t.padding=10;t.boxPadding=4;t.usePointStyle=true;'
        . 'var sc=d.scale;sc.grid.color=s.grid;sc.grid.lineWidth=m.grid;sc.grid.drawTicks=false;sc.border.color=s.baseline;sc.border.width=m.baseline;sc.ticks.color=s.ink3;sc.ticks.font=font("axis");sc.ticks.padding=8;'
        . 'var ss=d.scales;ss.linear&&(ss.linear.ticks.maxTicksLimit=m.ticks_max,ss.linear.border={display:false});'
        . '["category","time","timeseries"].forEach(function(k){ss[k]&&(ss[k].grid={display:false});});'
        . 'var e=d.elements;e.bar.borderRadius=m.bar_radius;e.bar.borderSkipped="start";e.bar.borderWidth=0;'
        . 'e.line.borderWidth=m.line;e.line.borderCapStyle="round";e.line.borderJoinStyle="round";e.line.tension=0;'
        . 'e.point.radius=0;e.point.hoverRadius=m.dot;e.point.hitRadius=m.dot*2;e.point.borderWidth=m.ring;e.point.hoverBorderWidth=m.ring;e.point.borderColor=s.bg;'
        . 'e.arc.borderWidth=m.slice_gap;e.arc.borderColor=s.bg;e.arc.hoverBorderColor=s.bg;'
        . 'var ds=d.datasets;ds.bar.maxBarThickness=m.bar_max;ds.bar.categoryPercentage=0.8;ds.bar.barPercentage=m.bar_band/80;'
        . 'ds.doughnut.cutout=m.cutout+"%";}'
        . 'function own(o,k){return o[k]!==undefined&&!(o[auto]&&o[auto][k]);}'
        . 'function paint(o,k,v){if(!own(o,k)){o[k]=v;o[auto]=o[auto]||{};o[auto][k]=true;}}'
        . 'function colour(chart){var data=chart.config.data||{},type=chart.config.type;(data.datasets||[]).forEach(function(x,i){'
        . 'var t=x.type||type;if(t==="doughnut"||t==="pie"||t==="polarArea"){var n=(x.data||[]).length,c=[];for(var j=0;j<n;j++){c.push(series(j));}paint(x,"backgroundColor",c);paint(x,"hoverBackgroundColor",c);return;}'
        . 'paint(x,"borderColor",series(i));paint(x,"pointBackgroundColor",series(i));paint(x,"pointHoverBackgroundColor",series(i));'
        . 'paint(x,"backgroundColor",(t==="line"||t==="radar")?area(i):series(i));paint(x,"hoverBackgroundColor",(t==="line"||t==="radar")?area(i):series(i));});}'
        . 'function rescale(c,was){var now=set(),map={};["grid","baseline","ink3","ink2"].forEach(function(k){map[was[k]]=now[k];});'
        . 'var sc=(c.config.options&&c.config.options.scales)||{};for(var k in sc){["grid","border","ticks","title"].forEach(function(p){var o=sc[k][p];if(o&&typeof o.color==="string"&&map[o.color]){o.color=map[o.color];}});}}'
        . 'function apply(){var was=G.sets[mode];mode=pick();vars();var C=window.Chart;if(!C||!C.defaults){return;}defaults(C);'
        . 'if(C.instances){for(var id in C.instances){var c=C.instances[id];colour(c);was!==set()&&rescale(c,was);c.update();}}}'
        . 'apply();var C=window.Chart;if(C&&C.register){C.register({id:"pgGuide",beforeInit:colour,beforeUpdate:colour});}'
        . 'if(window.matchMedia){var q=window.matchMedia("(prefers-color-scheme: dark)");q.addEventListener?q.addEventListener("change",apply):q.addListener&&q.addListener(apply);}'
        . 'if(window.MutationObserver){new MutationObserver(apply).observe(root,{attributes:true,attributeFilter:["data-theme"]});}'
        . 'window.PgChartGuide={values:G,mode:function(){return mode;},palette:function(){return set();},series:series,area:area,'
        . 'use:function(m){if(m==="dark"||m==="light"){root.setAttribute("data-theme",m);}else{root.removeAttribute("data-theme");}apply();}};})();';
}

/**
 * The libraries a call names, checked: known names only, each once, in the
 * order given, a name's needs put in ahead of it. Pure.
 *
 * @param mixed $names
 * @return array names, error ('' or the unknown name)
 */
function ws_page_libraries_check($names)
{
    if (($names === null) || ($names === '')) {
        return array('names' => array(), 'error' => '');
    }

    if (!is_array($names)) {
        return array('names' => array(), 'error' => '?');
    }

    $known = ws_page_libraries();
    $picked = array();

    foreach ($names as $name) {
        $name = is_string($name) ? strtolower(trim($name)) : '';

        if (!isset($known[$name])) {
            return array('names' => array(), 'error' => ($name !== '') ? $name : '?');
        }

        foreach (isset($known[$name]['needs']) ? $known[$name]['needs'] : array() as $need) {
            if (!in_array($need, $picked, true)) {
                $picked[] = $need;
            }
        }

        if (!in_array($name, $picked, true)) {
            $picked[] = $name;
        }
    }

    return array('names' => $picked, 'error' => '');
}

/**
 * The libraries a version's document names. Pure.
 *
 * @param mixed $doc_json
 * @return string[]
 */
function ws_page_version_libraries($doc_json)
{
    $doc = json_decode((string) $doc_json, true);
    $checked = ws_page_libraries_check((is_array($doc) && isset($doc['libraries'])) ? $doc['libraries'] : null);

    return $checked['names'];
}

/**
 * The page with the libraries' scripts put in ahead of it: right after its
 * doctype when it has one (a script before <html> lands in the head and the
 * page keeps its standards mode), else first. A source holding "</script"
 * or "<!--" would end or swallow its element, so it is left out. Pure.
 *
 * @param string   $html
 * @param string[] $sources the libraries' code
 * @return string
 */
function ws_page_inline_scripts($html, $sources)
{
    $scripts = '';

    foreach ($sources as $source) {
        if (!is_string($source) || ($source === '') || (stripos($source, '</script') !== false) || (strpos($source, '<!--') !== false)) {
            continue;
        }

        $scripts .= '<script>' . $source . "</script>\n";
    }

    if ($scripts === '') {
        return (string) $html;
    }

    if (preg_match('/^\s*<!DOCTYPE[^>]*>/i', (string) $html, $match)) {
        return $match[0] . "\n" . $scripts . substr((string) $html, strlen($match[0]));
    }

    return $scripts . $html;
}

/**
 * The page as the frame shows it: with the site's own copy of each library
 * it names.
 *
 * @param string   $html
 * @param string[] $names checked by ws_page_libraries_check()
 * @return string
 */
function ws_page_with_libraries($html, $names)
{
    $known = ws_page_libraries();
    $sources = array();

    foreach ($names as $name) {
        if (isset($known[$name])) {
            $code = isset($known[$name]['build'])
                ? call_user_func($known[$name]['build'])
                : @file_get_contents(PG_FUNCTIONS_DIR . '/' . $known[$name]['path']);

            if (is_string($code)) {
                $sources[] = $code;
            }
        }
    }

    return ws_page_inline_scripts($html, $sources);
}

/**
 * The libraries the newest version of a page names.
 *
 * @param int $file_id
 * @return string[]
 */
function ws_page_libraries_of($file_id)
{
    $rows = ws_page_versions($file_id);

    return $rows ? ws_page_version_libraries(end($rows)['doc_json']) : array();
}
