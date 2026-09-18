<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * HTML / ZIP import for the visual designer.
 *
 * Turns a plain HTML file, or a whole HTML project in a ZIP, into visual
 * designer pages: every .html/.htm becomes a page with its own layout tree,
 * every stylesheet, script, image and font referenced by them is stored in
 * the file manager, and the pages' <head> is thrown away in favour of the
 * designer's own — Bootstrap from our sentinels, imported stylesheets and
 * scripts as design assets, Google Fonts in the fonts list.
 *
 * Two callers share this: the designer's "Sayfa Ekle → HTML / ZIP İçe Aktar"
 * (adds pages to the open design; the operator reviews and saves) and the
 * file manager's Import ZIP screen (creates a new design outright).
 *
 * What is deliberately NOT carried over:
 *   • <head> markup other than title, meta description, stylesheets, scripts
 *     and Google Fonts — canonical/og/charset/viewport are the renderer's job
 *   • bootstrap*.css / bootstrap*.js / jquery*.js, local or CDN — the design
 *     ships its own via the assets panel sentinels; a second copy would fight
 *   • the original folder layout of the URLs — files are flat on disk (that
 *     is how files.name works), so assets/css/site.css becomes site.css. The
 *     file manager keeps the folders (Import/<project>/assets/css/…) so the
 *     project stays browsable.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Public entry points ─────────────────────────────────────────────────────

/**
 * Import a ZIP archive.
 *
 * @param string $zip_path     Path to the uploaded archive (tmp file is fine)
 * @param string $project_name Folder name under Import/ and default design name
 * @param array  $user         Validated user row
 * @param array  $opts         'skip_names' => page names already in use by the
 *                             caller (open tabs), so new names avoid them
 * @return array  see pg_designer_import_result()
 */
function pg_designer_import_zip($zip_path, $project_name, $user, $opts = array())
{
    $res = pg_designer_import_result();

    if (!class_exists('PclZip')) {
        require_once(dirname(__FILE__) . '/../pclzip.lib.php');
    }
    $archive = new PclZip($zip_path);
    $items   = $archive->listContent();
    if (!$items) {
        $res['errors'][] = lang('The zip file cannot be extracted, because it is not a valid zip file.');
        return $res;
    }

    // First pass: classify every entry. Nothing is written yet — the final
    // file names have to be known before any HTML or CSS can be rewritten.
    $entries = array();   // path => array('type' => html|css|js|asset|skip, 'name' => basename)
    foreach ($items as $it) {
        $path = (string)$it['filename'];
        if ($path === '' || substr($path, -1) === '/') continue;
        $norm = pg_di_norm_path('', $path);
        if ($norm === '' || pg_di_should_skip($norm)) continue;
        $ext = strtolower(pathinfo($norm, PATHINFO_EXTENSION));
        if ($ext === 'html' || $ext === 'htm' || $ext === 'xhtml') {
            $entries[$norm] = array('type' => 'html');
        } elseif ($ext === 'css') {
            $entries[$norm] = array('type' => 'css');
        } elseif ($ext === 'js') {
            $entries[$norm] = array('type' => 'js');
        } else {
            $entries[$norm] = array('type' => 'asset');
        }
    }
    if (empty($entries)) {
        $res['errors'][] = lang('The archive contains nothing that can be imported.');
        return $res;
    }

    // Strip a common leading directory ("mysite/index.html" → "index.html")
    // so folders in the file manager start at the project, not at the ZIP's
    // wrapper directory.
    $entries = pg_di_strip_common_prefix($entries);

    // Bootstrap / jQuery bundles are replaced by the design's own sentinels;
    // do not even upload them, they would sit unused in the file manager.
    foreach ($entries as $path => $e) {
        if (($e['type'] === 'css' || $e['type'] === 'js') && pg_di_is_framework_file($path)) {
            $entries[$path]['type'] = 'skip';
            $res['warnings'][] = lang(array('string' => '{var:1} was not imported: the design uses its own Bootstrap / jQuery.', 'vars' => $path));
        }
    }

    // Folder tree in the file manager: Import / <project> / <relative dir>
    $ctx = new stdClass();
    $ctx->user_id    = (int)$user['id'];
    $ctx->folders    = array();
    $ctx->root_id    = pg_di_folder_path(array('Import', $project_name), $ctx);
    $ctx->project    = $project_name;
    $ctx->files      = array();  // archive path => array('name','url','type')
    $ctx->warnings   = array();
    $ctx->read       = function ($path) use ($archive, $entries) {
        // PclZip wants the ORIGINAL entry name; entries were re-keyed after
        // the common prefix was stripped, so map back through the list.
        static $lookup = null;
        if ($lookup === null) {
            $lookup = array();
            foreach ($archive->listContent() as $it) {
                $n = pg_di_norm_path('', (string)$it['filename']);
                $lookup[$n] = (string)$it['filename'];
            }
        }
        $orig = null;
        foreach ($lookup as $n => $o) {
            if ($n === $path || substr($n, -strlen('/' . $path)) === '/' . $path) { $orig = $o; break; }
        }
        if ($orig === null) return null;
        $x = $archive->extract(PCLZIP_OPT_BY_NAME, $orig, PCLZIP_OPT_EXTRACT_AS_STRING);
        return (is_array($x) && isset($x[0]['content'])) ? $x[0]['content'] : null;
    };

    // Second pass: reserve a flat, unique file name for every non-HTML entry
    // so cross-references can be rewritten before anything is written.
    foreach ($entries as $path => $e) {
        if ($e['type'] === 'html' || $e['type'] === 'skip') continue;
        $ctx->files[$path] = array(
            'name' => pg_di_reserve_file_name(basename($path), $ctx),
            'type' => $e['type'],
            'ext'  => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
        );
        $ctx->files[$path]['url'] = OUTPUT_PATH . $ctx->files[$path]['name'];
    }

    // Third pass: write files. CSS gets its url() references rewritten first
    // — a stylesheet pointing at ../img/bg.png must point at the file's new
    // flat name, or every background vanishes on import.
    foreach ($entries as $path => $e) {
        if ($e['type'] === 'html' || $e['type'] === 'skip') continue;
        $content = call_user_func($ctx->read, $path);
        if ($content === null) { $res['warnings'][] = lang(array('string' => '{var:1} could not be read from the archive.', 'vars' => $path)); continue; }
        if ($e['type'] === 'css') {
            $content = pg_di_rewrite_css_urls($content, dirname($path), $ctx);
        }
        pg_di_store_file($path, $content, $ctx);
    }

    // Fourth pass: pages. Names are settled for ALL pages first — a link
    // from index.html to hakkinda/index.html has to know what that second
    // file will be called before the first one is converted.
    $taken = isset($opts['skip_names']) && is_array($opts['skip_names']) ? $opts['skip_names'] : array();
    $seen  = array();
    $ctx->page_names = array();
    foreach ($entries as $path => $e) {
        if ($e['type'] !== 'html') continue;
        $ctx->page_names[$path] = pg_di_page_name($path, $taken, $seen);
        $seen[] = $ctx->page_names[$path];
    }
    foreach ($entries as $path => $e) {
        if ($e['type'] !== 'html') continue;
        $html = call_user_func($ctx->read, $path);
        if ($html === null) { $res['warnings'][] = lang(array('string' => '{var:1} could not be read from the archive.', 'vars' => $path)); continue; }
        $page = pg_designer_import_html($html, $path, $ctx);
        $page['page_name'] = $ctx->page_names[$path];
        foreach ($page['css'] as $a)   $res['css'][]   = $a;
        foreach ($page['js'] as $a)    $res['js'][]    = $a;
        foreach ($page['fonts'] as $f) $res['fonts'][] = $f;
        foreach ($page['warnings'] as $w) $res['warnings'][] = $w;
        unset($page['css'], $page['js'], $page['fonts'], $page['warnings']);
        $res['pages'][] = $page;
    }
    foreach ($ctx->warnings as $w) $res['warnings'][] = $w;
    $res['css']   = pg_di_dedupe_assets($res['css']);
    $res['js']    = pg_di_dedupe_assets($res['js']);
    $res['fonts'] = pg_di_dedupe_fonts($res['fonts']);
    $res['folder_id']    = $ctx->root_id;
    $res['folder_label'] = 'Import / ' . $project_name;
    if (empty($res['pages'])) {
        $res['errors'][] = lang('The archive contains no HTML page.');
    }
    return $res;
}

/**
 * Import a single HTML document that arrived on its own (no archive).
 * Local stylesheet / script / image references cannot be resolved — there
 * is nothing to resolve them against — so they are reported as warnings
 * and left as-is. CDN references and inline <style>/<script> come through.
 */
function pg_designer_import_single_html($html, $file_name, $user, $opts = array())
{
    $res = pg_designer_import_result();
    $ctx = new stdClass();
    $ctx->user_id  = (int)$user['id'];
    $ctx->files    = array();
    $ctx->folders  = array();
    $ctx->warnings = array();
    $ctx->root_id  = 0;
    $ctx->project  = pathinfo($file_name, PATHINFO_FILENAME);
    $taken = isset($opts['skip_names']) && is_array($opts['skip_names']) ? $opts['skip_names'] : array();
    $seen  = array();
    $page  = pg_designer_import_html($html, $file_name, $ctx);
    $page['page_name'] = pg_di_page_name($file_name, $taken, $seen);
    $res['css'] = $page['css']; $res['js'] = $page['js']; $res['fonts'] = $page['fonts'];
    $res['warnings'] = array_merge($page['warnings'], $ctx->warnings);
    unset($page['css'], $page['js'], $page['fonts'], $page['warnings']);
    $res['pages'][] = $page;
    return $res;
}

/**
 * A pasted HTML FRAGMENT as designer nodes.
 *
 * The same DOM walker the file import uses, with three deliberate
 * differences, because a paste is not an import:
 *
 *   - Nothing is written to disk. An import brings a project and its assets;
 *     a paste is markup off somebody's clipboard, and it must not leave
 *     files behind in the file manager.
 *   - <style> and <script> are counted and dropped, never folded into the
 *     design's shared assets. Ctrl+V changing every page of a design is not
 *     something anyone asks for, and the undo stack does not cover assets.
 *   - Unresolvable references are left exactly as written. The archive that
 *     would say what "images/hero.png" or "about.html" means is not here, so
 *     guessing produces a link that looks right and goes nowhere.
 *
 * Returns array('children' => [...], 'warnings' => [...]).
 */
function pg_designer_import_fragment($html, $user, $opts = array())
{
    $out = array('children' => array(), 'warnings' => array());
    $html = (string)$html;
    if (trim($html) === '') return $out;

    $ctx = new stdClass();
    $ctx->user_id   = (int)$user['id'];
    $ctx->files     = array();
    $ctx->folders   = array();
    $ctx->warnings  = array();
    $ctx->root_id   = 0;
    $ctx->project   = '';
    $ctx->base_dir  = '';
    $ctx->page_slug = 'paste';
    $ctx->fragment  = true;              // read by pg_di_svg_node / pg_di_rewrite_ref

    // Count what is being dropped before it is dropped, so the operator is
    // told rather than left wondering why the paste looks unstyled.
    $n_style  = preg_match_all('/<style\b/i', $html);
    $n_script = preg_match_all('/<script\b/i', $html);

    $html = preg_replace('/<!--\s*BEGIN\s+DYNAMIC\s+CODE.*?END\s+DYNAMIC\s+CODE\s*-->/is', '', $html);

    // Same reason as the page importer: libxml does not know SVG's
    // self-closing elements and turns "<path/><path/>" into nested paths.
    $ctx->svg_raw = array();
    $html = preg_replace_callback('/<svg\b[^>]*>.*?<\/svg\s*>/is', function ($m) use ($ctx) {
        $ctx->svg_raw[] = $m[0];
        return '<pg-svg data-i="' . (count($ctx->svg_raw) - 1) . '"></pg-svg>';
    }, $html);

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . '<body>' . $html . '</body>', LIBXML_NONET);
    libxml_clear_errors();
    if (!$loaded) {
        $out['warnings'][] = lang('The pasted content could not be read as HTML.');
        return $out;
    }
    foreach ($doc->childNodes as $cn) {
        if ($cn->nodeType === XML_PI_NODE) { $doc->removeChild($cn); break; }
    }

    $xp   = new DOMXPath($doc);
    $body = $xp->query('//body')->item(0);
    if ($body) {
        foreach ($body->childNodes as $cn) {
            $n = pg_di_node($cn, $ctx);
            if ($n !== null) $out['children'][] = $n;
        }
    }
    $out['warnings'] = array_merge($out['warnings'], $ctx->warnings);
    if ($n_style) {
        $out['warnings'][] = lang(array(
            'string' => '{var:1} <style> block{suffix:1} left out. Paste brings markup only; use Import HTML for a page with its stylesheets.',
            'vars'   => $n_style,
            'suffix' => ($n_style == 1 ? '' : 's'),
        ));
    }
    if ($n_script) {
        $out['warnings'][] = lang(array(
            'string' => '{var:1} <script> block{suffix:1} left out.',
            'vars'   => $n_script,
            'suffix' => ($n_script == 1 ? '' : 's'),
        ));
    }
    return $out;
}

/**
 * Turn an import result into a SAVED design: one style row carrying the
 * imported stylesheets / scripts / fonts, one page row per imported page,
 * every page in the project's Import/<name> folder. This is the file
 * manager's path — no editor is open to hand unsaved tabs to — and it ends
 * with the browser sent to the editor on the new design.
 *
 * The Bootstrap / jQuery / User Styles sentinels are NOT written here: the
 * assets panel seeds any that are missing the first time the design opens,
 * and the renderer injects Bootstrap on its own when no sentinel is stored.
 *
 * Returns array('style_id' => int, 'page_ids' => [...], 'warnings' => [...], 'errors' => [...]).
 */
function pg_designer_create_design_from_import($res, $design_name, $user)
{
    $out = array('style_id' => 0, 'page_ids' => array(), 'warnings' => isset($res['warnings']) ? $res['warnings'] : array(), 'errors' => array());
    if (empty($res['pages'])) {
        $out['errors'][] = lang('The archive contains no HTML page.');
        return $out;
    }
    $design_name = trim((string)$design_name);
    if ($design_name === '') $design_name = (string)$res['pages'][0]['page_name'];
    $design_name = get_unique_name(array('name' => $design_name, 'type' => 'style'));

    $style_id = (int)save_system_style(array(
        'style_id'                          => 0,
        'name'                              => $design_name,
        'theme_id'                          => 0,
        'additional_body_classes'           => '',
        'collection'                        => 'a',
        'social_networking_position'        => (SOCIAL_NETWORKING == TRUE) ? 'bottom_left' : '',
        'style_head'                        => '',
        'style_empty_cell_width_percentage' => '',
        'user_id'                           => (int)$user['id'],
        'style_custom_css'                  => json_encode(array_values($res['css']),   JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'style_custom_js'                   => json_encode(array_values($res['js']),    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'style_custom_fonts'                => json_encode(array_values($res['fonts']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ));
    if ($style_id <= 0) {
        $out['errors'][] = lang('The style could not be created.');
        return $out;
    }
    $out['style_id'] = $style_id;

    $folder_id = isset($res['folder_id']) ? (int)$res['folder_id'] : 0;
    $seq = 0;
    foreach ($res['pages'] as $p) {
        $tree = isset($p['tree']) && is_array($p['tree']) ? $p['tree'] : array('type' => 'root', 'props' => array(), 'children' => array());
        pg_di_assign_ids($tree, $seq);
        $r = pg_designer_save_page($style_id, array(
            'page_id'               => 0,
            'page_name'             => (string)$p['page_name'],
            'tree_json'             => pg_designer_tree_encode($tree),
            'page_folder'           => $folder_id,
            'page_title'            => isset($p['page_title']) ? (string)$p['page_title'] : '',
            'page_meta_description' => isset($p['page_meta_description']) ? (string)$p['page_meta_description'] : '',
            'page_search'           => 1,
            'page_sitemap'          => 1,
            'page_home'             => 0,
            'pg_comments_allow_new' => 1,
        ), $user);
        if (!empty($r['ok'])) {
            $out['page_ids'][] = (int)$r['page_id'];
        } else {
            foreach ((isset($r['errors']) ? $r['errors'] : array()) as $e) {
                $out['warnings'][] = $p['page_name'] . ': ' . $e;
            }
        }
        foreach ((isset($r['warnings']) ? $r['warnings'] : array()) as $w) $out['warnings'][] = $w;
    }
    if (empty($out['page_ids'])) {
        db("DELETE FROM style WHERE style_id = '$style_id'");
        $out['style_id'] = 0;
        $out['errors'][] = lang('None of the pages could be saved.');
    }
    return $out;
}

// The editor keys DOM ↔ node by _id and backfills missing ones on load;
// giving the saved tree ids up front keeps the first load and the first
// save identical.
function pg_di_assign_ids(&$node, &$seq)
{
    if (!is_array($node)) return;
    if (empty($node['_id'])) $node['_id'] = 'sd_' . (++$seq);
    if (isset($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as &$c) pg_di_assign_ids($c, $seq);
        unset($c);
    }
}

function pg_designer_import_result()
{
    return array(
        'pages'     => array(),   // [{page_name, page_title, page_meta_description, tree, source}]
        'css'       => array(),   // assets-panel entries
        'js'        => array(),
        'fonts'     => array(),   // [{family, weights[], enabled, movedToCss}]
        'warnings'  => array(),
        'errors'    => array(),
        'folder_id' => 0,         // Import/<project> folder the pages belong in
        'folder_label' => '',
    );
}

// ── HTML → page ─────────────────────────────────────────────────────────────

/**
 * One HTML document → page descriptor + the assets its head referenced.
 *
 * @param string   $html
 * @param string   $html_path  Path inside the archive (for relative refs)
 * @param stdClass $ctx        files map, folder ids, warnings sink
 */
function pg_designer_import_html($html, $html_path, $ctx)
{
    $out = array(
        'page_name' => '', 'page_title' => '', 'page_meta_description' => '',
        'tree' => null, 'source' => $html_path,
        'css' => array(), 'js' => array(), 'fonts' => array(), 'warnings' => array(),
    );
    $base_dir = str_replace('\\', '/', dirname($html_path));
    if ($base_dir === '.' || $base_dir === '/') $base_dir = '';
    $ctx->base_dir = $base_dir;
    $ctx->page_slug = pg_di_slug(pathinfo($html_path, PATHINFO_FILENAME));

    // Strip Pinegrap's own dynamic-code markers left by a previous export.
    $html = preg_replace('/<!--\s*BEGIN\s+DYNAMIC\s+CODE.*?END\s+DYNAMIC\s+CODE\s*-->/is', '', $html);

    // Inline SVG is lifted out BEFORE parsing. libxml's HTML parser does not
    // know SVG's self-closing elements, so "<path/><path/>" comes back as
    // nested paths and the drawing is gone. Each <svg> is kept verbatim and
    // stands in the document as a placeholder element.
    $ctx->svg_raw = array();
    $html = preg_replace_callback('/<svg\b[^>]*>.*?<\/svg\s*>/is', function ($m) use ($ctx) {
        $ctx->svg_raw[] = $m[0];
        return '<pg-svg data-i="' . (count($ctx->svg_raw) - 1) . '"></pg-svg>';
    }, $html);

    $doc = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    // The XML prolog tells libxml the bytes are UTF-8; without it a document
    // with no <meta charset> is read as ISO-8859-1 and every ş/ğ/ı breaks.
    $loaded = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors();
    if (!$loaded) {
        $out['warnings'][] = lang(array('string' => '{var:1} could not be parsed as HTML.', 'vars' => $html_path));
        $out['tree'] = pg_di_root(array());
        return $out;
    }
    // Drop the prolog node libxml inserted for the encoding hint.
    foreach ($doc->childNodes as $cn) {
        if ($cn->nodeType === XML_PI_NODE) { $doc->removeChild($cn); break; }
    }

    $xp = new DOMXPath($doc);

    // ── <head> ────────────────────────────────────────────────────────
    $t = $xp->query('//head/title')->item(0);
    if ($t) $out['page_title'] = trim($t->textContent);
    $m = $xp->query('//head/meta[translate(@name,"DESCRIPTION","description")="description"]')->item(0);
    if ($m) $out['page_meta_description'] = trim((string)$m->getAttribute('content'));

    foreach ($xp->query('//link[@href]') as $ln) {
        $rel  = strtolower((string)$ln->getAttribute('rel'));
        $href = trim((string)$ln->getAttribute('href'));
        if ($href === '') continue;
        if (strpos($rel, 'stylesheet') === false) {
            // Google Fonts links sometimes carry rel="preload" too; only the
            // stylesheet form matters.
            continue;
        }
        if (pg_di_is_google_fonts($href)) {
            foreach (pg_di_parse_google_fonts($href) as $f) $out['fonts'][] = $f;
            continue;
        }
        if (pg_di_is_framework_url($href)) continue;           // our sentinels cover it
        if (pg_di_is_absolute($href)) {
            $out['css'][] = pg_di_asset_entry('external-css', basename(parse_url($href, PHP_URL_PATH) ?: 'external.css'), $href, false);
            continue;
        }
        $url = pg_di_resolve_ref($href, $ctx);
        if ($url !== null) {
            $out['css'][] = pg_di_asset_entry('external-css', basename($url), $url, true);
        } else {
            $out['warnings'][] = lang(array('string' => '{var:1}: stylesheet "{var:2}" is not in the archive and was left out.', 'vars' => array($html_path, $href)));
        }
    }
    $inline_css = '';
    foreach ($xp->query('//style') as $st) {
        $inline_css .= trim($st->textContent) . "\n";
    }
    if (trim($inline_css) !== '') {
        $out['css'][] = pg_di_asset_entry('css', $ctx->page_slug . '-inline.css', pg_di_rewrite_css_urls($inline_css, $base_dir, $ctx), false);
    }

    $inline_js = '';
    foreach ($xp->query('//script') as $sc) {
        $src  = trim((string)$sc->getAttribute('src'));
        $type = strtolower(trim((string)$sc->getAttribute('type')));
        if ($src !== '') {
            if (pg_di_is_framework_url($src)) continue;
            if (pg_di_is_absolute($src)) {
                $out['js'][] = pg_di_asset_entry('external-js', basename(parse_url($src, PHP_URL_PATH) ?: 'external.js'), $src, false);
            } else {
                $url = pg_di_resolve_ref($src, $ctx);
                if ($url !== null) $out['js'][] = pg_di_asset_entry('external-js', basename($url), $url, true);
                else $out['warnings'][] = lang(array('string' => '{var:1}: script "{var:2}" is not in the archive and was left out.', 'vars' => array($html_path, $src)));
            }
            continue;
        }
        // Inline script — only real JavaScript. JSON-LD, templates and the
        // like stay out; they would only run as errors.
        if ($type !== '' && $type !== 'text/javascript' && $type !== 'module' && $type !== 'application/javascript') continue;
        $code = trim($sc->textContent);
        if ($code !== '') $inline_js .= $code . "\n";
    }
    if (trim($inline_js) !== '') {
        $out['js'][] = pg_di_asset_entry('js', $ctx->page_slug . '-inline.js', $inline_js, false);
    }

    // ── <body> → tree ─────────────────────────────────────────────────
    $body = $xp->query('//body')->item(0);
    $root_props = array();
    $children = array();
    if ($body) {
        if ($body->hasAttribute('class')) $root_props['cssClass'] = trim((string)$body->getAttribute('class'));
        if ($body->hasAttribute('id'))    $root_props['id'] = trim((string)$body->getAttribute('id'));
        if ($body->hasAttribute('style')) $root_props['inlineStyle'] = trim((string)$body->getAttribute('style'));
        $extra = array();
        foreach ($body->attributes as $a) {
            if (in_array($a->name, array('class', 'id', 'style'), true)) continue;
            $extra[] = array('name' => $a->name, 'value' => (string)$a->value);
        }
        if ($extra) $root_props['_attrs'] = $extra;
        foreach ($body->childNodes as $cn) {
            $n = pg_di_node($cn, $ctx);
            if ($n !== null) $children[] = $n;
        }
    }
    $out['tree'] = pg_di_root($children, $root_props);
    return $out;
}

// ── DOM → tree ──────────────────────────────────────────────────────────────

function pg_di_root($children, $props = array())
{
    return array('type' => 'root', 'props' => $props, 'children' => array_values($children));
}

function pg_di_mk($type, $props, $children = array())
{
    return array('type' => $type, 'props' => $props, 'children' => array_values($children));
}

// Tags whose content is kept verbatim as custom_html: too structured or too
// opaque to be worth exploding into semantic nodes.
function pg_di_opaque_tags()
{
    static $t = array('math' => 1, 'video' => 1, 'audio' => 1, 'iframe' => 1, 'canvas' => 1,
                      'object' => 1, 'embed' => 1, 'map' => 1, 'template' => 1);
    return $t;
}

/**
 * Inline <svg> → an ordinary node, never custom_html.
 *
 * Bootstrap Icons markup (class="bi bi-alarm") becomes the icon node the
 * palette makes. Any other drawing is written to the project's assets as an
 * .svg file and placed through an image node, so the page tree stays made
 * of things the designer can select, move and restyle.
 */
function pg_di_svg_node($raw, $ctx)
{
    $open = '';
    if (preg_match('/^<svg\b([^>]*)>/i', $raw, $m)) $open = $m[1];
    $attr = function ($name) use ($open) {
        return preg_match('/\b' . preg_quote($name, '/') . '\s*=\s*(["\'])(.*?)\1/is', $open, $m) ? html_entity_decode($m[2], ENT_QUOTES, 'UTF-8') : '';
    };
    $cls = trim($attr('class'));
    if (preg_match('/(?:^|\s)(bi-[a-z0-9-]+)(?:\s|$)/', $cls, $m)) {
        $rest  = trim(preg_replace('/\s+/', ' ', preg_replace('/(?:^|\s)bi(?:-[a-z0-9-]+)?(?=\s|$)/', ' ', $cls)));
        $props = array('contentType' => 'icon', 'iconName' => $m[1], 'cssClass' => $rest);
        $size  = trim($attr('width'));
        if (ctype_digit($size)) $props['fontSize'] = $size;
        return pg_di_mk('content', $props);
    }

    $markup = $raw;
    if (stripos($open, 'xmlns=') === false) {
        $markup = preg_replace('/^<svg\b/i', '<svg xmlns="http://www.w3.org/2000/svg"', $markup, 1);
    }
    // A paste writes no files, so the drawing stays as markup. The node is
    // still selectable and movable; it just is not a separate .svg.
    if (!empty($ctx->fragment)) {
        return pg_di_mk('content', array('contentType' => 'custom_html', 'html' => $markup, 'cssClass' => $cls));
    }
    $ctx->svg_seq = isset($ctx->svg_seq) ? $ctx->svg_seq + 1 : 1;
    $vpath = 'assets/svg/' . $ctx->page_slug . '-' . $ctx->svg_seq . '.svg';
    $url   = pg_di_store_generated($vpath, $markup, $ctx);
    if ($url === null) return null;
    if (stripos($markup, 'currentColor') !== false && empty($ctx->svg_color_warned)) {
        $ctx->svg_color_warned = true;
        $ctx->warnings[] = lang('Inline SVG drawings were saved as files. Colours that relied on currentColor now need to be set in the file itself.');
    }
    $alt = trim($attr('aria-label'));
    if ($alt === '' && preg_match('/<title[^>]*>(.*?)<\/title>/is', $raw, $t)) $alt = trim(strip_tags($t[1]));
    return pg_di_mk('content', array(
        'contentType' => 'image', 'src' => $url, 'alt' => $alt,
        'width' => trim($attr('width')), 'height' => trim($attr('height')),
        'fluid' => false, 'rounded' => false, 'objectPosition' => '', 'aspectRatio' => '', 'cssClass' => $cls,
    ));
}

// A file the importer made itself (an inline SVG, say): reserve a name,
// register it like an archive entry, write it. Returns the URL, or null.
function pg_di_store_generated($vpath, $content, $ctx)
{
    if (!defined('FILE_DIRECTORY_PATH')) return null;
    $ctx->files[$vpath] = array(
        'name' => pg_di_reserve_file_name(basename($vpath), $ctx),
        'type' => 'asset',
        'ext'  => strtolower(pathinfo($vpath, PATHINFO_EXTENSION)),
    );
    $ctx->files[$vpath]['url'] = OUTPUT_PATH . $ctx->files[$vpath]['name'];
    pg_di_store_file($vpath, $content, $ctx);
    return $ctx->files[$vpath]['url'];
}

// Inline formatting that lives INSIDE a heading / paragraph / link text.
function pg_di_inline_tags()
{
    static $t = array('b' => 1, 'strong' => 1, 'i' => 1, 'em' => 1, 'u' => 1, 's' => 1, 'small' => 1,
                      'span' => 1, 'sup' => 1, 'sub' => 1, 'code' => 1, 'mark' => 1, 'abbr' => 1,
                      'br' => 1, 'wbr' => 1, 'a' => 1, 'time' => 1, 'kbd' => 1, 'q' => 1, 'cite' => 1,
                      'pg-svg' => 1);
    return $t;
}

function pg_di_node($node, $ctx)
{
    if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
        $txt = preg_replace('/\s+/u', ' ', (string)$node->nodeValue);
        if (trim($txt) === '') return null;
        return pg_di_mk('content', array('contentType' => 'span', 'text' => trim($txt), 'cssClass' => ''));
    }
    if ($node->nodeType !== XML_ELEMENT_NODE) return null;

    $tag = strtolower($node->nodeName);
    $opaque = pg_di_opaque_tags();

    // Never part of the layout.
    if (in_array($tag, array('script', 'style', 'link', 'meta', 'title', 'noscript', 'base'), true)) return null;

    // Inline SVG placeholder (see pg_designer_import_html).
    if ($tag === 'pg-svg') {
        $i = (int)$node->getAttribute('data-i');
        return isset($ctx->svg_raw[$i]) ? pg_di_svg_node($ctx->svg_raw[$i], $ctx) : null;
    }
    // <picture> is its <img>; the <source> variants have no home in a
    // single-src image node.
    if ($tag === 'picture') {
        foreach ($node->getElementsByTagName('img') as $im) return pg_di_node($im, $ctx);
        return null;
    }

    // Opaque blocks keep their markup, with local references rewritten.
    if (isset($opaque[$tag])) {
        $html = pg_di_outer_html($node);
        return pg_di_mk('content', array('contentType' => 'custom_html', 'html' => pg_di_rewrite_html_refs($html, $ctx), 'cssClass' => ''));
    }

    $cls   = trim((string)$node->getAttribute('class'));
    $id    = trim((string)$node->getAttribute('id'));
    $attrs = pg_di_attrs($node, $ctx, array('class', 'id'));

    // Headings and paragraphs keep rich inline markup — their `text` is
    // rendered as innerHTML by the designer.
    if (preg_match('/^h[1-6]$/', $tag)) {
        return pg_di_mk('content', array_merge(array(
            'contentType' => 'heading', 'tag' => $tag,
            'text' => pg_di_inner_html($node, $ctx), 'align' => '', 'cssClass' => $cls,
        ), $id !== '' ? array('id' => $id) : array(), $attrs ? array('_attrs' => $attrs) : array()));
    }
    if ($tag === 'p') {
        return pg_di_mk('content', array_merge(array(
            'contentType' => 'paragraph', 'text' => pg_di_inner_html($node, $ctx),
            'lead' => false, 'align' => '', 'cssClass' => $cls,
        ), $id !== '' ? array('id' => $id) : array(), $attrs ? array('_attrs' => $attrs) : array()));
    }
    if ($tag === 'img') {
        $src = pg_di_rewrite_ref((string)$node->getAttribute('src'), $ctx);
        // srcset/sizes are dropped: the image node has one source, and a
        // srcset left in the raw attributes would beat whatever the picker
        // sets later (the browser prefers it over src).
        $attrs = pg_di_attrs($node, $ctx, array('class', 'id', 'src', 'alt', 'width', 'height', 'srcset', 'sizes'));
        return pg_di_mk('content', array_merge(array(
            'contentType' => 'image', 'src' => $src, 'alt' => (string)$node->getAttribute('alt'),
            'width' => (string)$node->getAttribute('width'), 'height' => (string)$node->getAttribute('height'),
            'fluid' => false, 'rounded' => false, 'objectPosition' => '', 'aspectRatio' => '', 'cssClass' => $cls,
        ), $id !== '' ? array('id' => $id) : array(), $attrs ? array('_attrs' => $attrs) : array()));
    }
    if ($tag === 'a' && pg_di_only_inline_children($node)) {
        $attrs = pg_di_attrs($node, $ctx, array('class', 'id', 'href', 'target', 'rel'));
        return pg_di_mk('content', array_merge(array(
            'contentType' => 'link', 'text' => pg_di_inner_html($node, $ctx),
            'href' => pg_di_rewrite_ref((string)$node->getAttribute('href'), $ctx),
            'target' => (string)$node->getAttribute('target'), 'rel' => (string)$node->getAttribute('rel'),
            'cssClass' => $cls,
        ), $id !== '' ? array('id' => $id) : array(), $attrs ? array('_attrs' => $attrs) : array()));
    }

    // Everything else is a semantic element. Text-only content goes on
    // `text`; mixed content becomes children, with text runs as spans so
    // their order against sibling elements survives (semantic `text` is
    // rendered BEFORE children).
    $props = array('tag' => $tag, 'cssClass' => $cls);
    if ($id !== '') $props['id'] = $id;
    if ($tag === 'a') {
        $props['href']   = pg_di_rewrite_ref((string)$node->getAttribute('href'), $ctx);
        $props['target'] = (string)$node->getAttribute('target');
        $props['rel']    = (string)$node->getAttribute('rel');
        $attrs = pg_di_attrs($node, $ctx, array('class', 'id', 'href', 'target', 'rel'));
    }
    if ($attrs) $props['_attrs'] = $attrs;

    $children = array();
    if (pg_di_only_text_children($node)) {
        $txt = preg_replace('/\s+/u', ' ', (string)$node->textContent);
        if (trim($txt) !== '') $props['text'] = trim($txt);
    } else {
        foreach ($node->childNodes as $cn) {
            $n = pg_di_node($cn, $ctx);
            if ($n !== null) $children[] = $n;
        }
    }
    return pg_di_mk('semantic', $props, $children);
}

function pg_di_only_text_children($node)
{
    foreach ($node->childNodes as $cn) {
        if ($cn->nodeType === XML_ELEMENT_NODE) return false;
    }
    return true;
}

function pg_di_only_inline_children($node)
{
    $inline = pg_di_inline_tags();
    foreach ($node->childNodes as $cn) {
        if ($cn->nodeType === XML_ELEMENT_NODE) {
            $t = strtolower($cn->nodeName);
            if (!isset($inline[$t]) || $t === 'a') return false;
            if (!pg_di_only_inline_children($cn)) return false;
        }
    }
    return true;
}

// Attributes other than the ones the node shape already owns, with any
// URL-bearing value rewritten to the imported file.
function pg_di_attrs($node, $ctx, $skip)
{
    $out = array();
    foreach ($node->attributes as $a) {
        $n = $a->name;
        if (in_array($n, $skip, true)) continue;
        $v = (string)$a->value;
        if (in_array($n, array('src', 'href', 'poster', 'data-src', 'data-bg', 'data-background'), true)) {
            $v = pg_di_rewrite_ref($v, $ctx);
        } elseif ($n === 'srcset' || $n === 'data-srcset') {
            $v = pg_di_rewrite_srcset($v, $ctx);
        } elseif ($n === 'style') {
            $v = pg_di_rewrite_css_urls($v, $ctx->base_dir, $ctx);
        }
        $out[] = array('name' => $n, 'value' => $v);
    }
    return $out;
}

function pg_di_inner_html($node, $ctx)
{
    $html = '';
    foreach ($node->childNodes as $cn) $html .= $node->ownerDocument->saveHTML($cn);
    // An icon inside a heading or link text stays inline markup; the
    // placeholder gives way to the original <svg>.
    if (!empty($ctx->svg_raw) && strpos($html, '<pg-svg') !== false) {
        $html = preg_replace_callback('/<pg-svg data-i="(\d+)"><\/pg-svg>/', function ($m) use ($ctx) {
            return isset($ctx->svg_raw[(int)$m[1]]) ? $ctx->svg_raw[(int)$m[1]] : '';
        }, $html);
    }
    return pg_di_rewrite_html_refs(trim($html), $ctx);
}

function pg_di_outer_html($node)
{
    return $node->ownerDocument->saveHTML($node);
}

// ── References ──────────────────────────────────────────────────────────────

function pg_di_is_absolute($url)
{
    return (bool)preg_match('#^(?:[a-z][a-z0-9+.\-]*:|//)#i', $url);
}

function pg_di_is_google_fonts($url)
{
    return stripos($url, 'fonts.googleapis.com') !== false;
}

// bootstrap / bootstrap-icons / jquery / popper, from a CDN or the archive.
function pg_di_is_framework_url($url)
{
    $p = strtolower((string)parse_url($url, PHP_URL_PATH));
    if ($p === '') $p = strtolower($url);
    return (bool)preg_match('#(?:^|/)(?:bootstrap(?:\.bundle|\.min|-icons|-grid|-reboot|-utilities)*|jquery(?:\.slim|\.min|-\d[\d.]*)*|popper(?:\.min)?)(?:\.\d+)*\.(?:css|js)$#', $p)
        || (bool)preg_match('#/npm/bootstrap(?:-icons)?@|/bootstrap/\d|/jquery/\d|/jquery-\d|cdn\.jsdelivr\.net/npm/@popperjs#i', $url);
}

function pg_di_is_framework_file($path)
{
    return pg_di_is_framework_url($path);
}

// Skip archive noise: OS metadata, VCS, editor caches, source files that are
// not web assets.
function pg_di_should_skip($path)
{
    $p = strtolower($path);
    if (strpos($p, '__macosx/') !== false) return true;
    if (preg_match('#(?:^|/)\.(?:ds_store|git|svn|hg|idea|vscode)#', $p)) return true;
    if (preg_match('#(?:^|/)(?:node_modules|\.sass-cache|bower_components)/#', $p)) return true;
    if (preg_match('#\.(?:psd|ai|sketch|fig|xd|scss|sass|less|map|md|txt|log|zip|rar|7z|exe|php|bat|sh)$#', $p)) return true;
    if (preg_match('#(?:^|/)(?:package(?:-lock)?\.json|composer\.(?:json|lock)|gulpfile\.js|webpack\.config\.js|\.htaccess)$#', $p)) return true;
    return false;
}

// Normalise an archive path: forward slashes, no leading ./ or /, ../ resolved.
function pg_di_norm_path($base_dir, $rel)
{
    $rel = str_replace('\\', '/', (string)$rel);
    $rel = preg_replace('#[?\#].*$#', '', $rel);     // drop query / fragment
    $rel = ltrim($rel, '/');
    $joined = ($base_dir !== '' && $base_dir !== '.') ? $base_dir . '/' . $rel : $rel;
    $out = array();
    foreach (explode('/', $joined) as $seg) {
        if ($seg === '' || $seg === '.') continue;
        if ($seg === '..') { array_pop($out); continue; }
        $out[] = $seg;
    }
    return implode('/', $out);
}

// A relative reference from the page's directory → imported file URL, or
// null when the file is not in the archive.
function pg_di_resolve_ref($ref, $ctx)
{
    $ref = trim($ref);
    if ($ref === '' || pg_di_is_absolute($ref) || $ref[0] === '#') return null;
    if (stripos($ref, 'data:') === 0 || stripos($ref, 'javascript:') === 0 || stripos($ref, 'mailto:') === 0 || stripos($ref, 'tel:') === 0) return null;
    $path = pg_di_norm_path(isset($ctx->base_dir) ? $ctx->base_dir : '', $ref);
    if (isset($ctx->files[$path])) return $ctx->files[$path]['url'];
    // Root-relative reference that the archive satisfies from its top.
    $alt = ltrim(str_replace('\\', '/', preg_replace('#[?\#].*$#', '', $ref)), '/');
    if (isset($ctx->files[$alt])) return $ctx->files[$alt]['url'];
    return null;
}

// Rewrite one URL-bearing value. Links to other pages in the archive become
// links to the imported pages (index.html → /index). Anything we can't map
// is left exactly as written.
function pg_di_rewrite_ref($ref, $ctx)
{
    $ref = trim((string)$ref);
    if ($ref === '') return '';
    if (pg_di_is_absolute($ref) || $ref[0] === '#') return $ref;
    if (stripos($ref, 'data:') === 0 || stripos($ref, 'javascript:') === 0 || stripos($ref, 'mailto:') === 0 || stripos($ref, 'tel:') === 0) return $ref;

    $url = pg_di_resolve_ref($ref, $ctx);
    if ($url !== null) return $url;

    // A paste has no archive to resolve against. Rewriting "about.html" to
    // "/about" would invent a page that may not exist, and the operator
    // would have no way to tell it apart from a link that works.
    if (!empty($ctx->fragment)) return $ref;

    // Page-to-page link? Resolved against the archive's page list when there
    // is one (hakkinda/index.html → whatever THAT page was named), else by
    // the file's own name.
    $clean = preg_replace('#[?\#].*$#', '', $ref);
    $ext = strtolower(pathinfo($clean, PATHINFO_EXTENSION));
    if ($ext === 'html' || $ext === 'htm' || $ext === 'xhtml') {
        $frag = '';
        if (preg_match('#([?\#].*)$#', $ref, $m)) $frag = $m[1];
        $name = null;
        if (!empty($ctx->page_names)) {
            $path = pg_di_norm_path(isset($ctx->base_dir) ? $ctx->base_dir : '', $clean);
            if (isset($ctx->page_names[$path])) $name = $ctx->page_names[$path];
            else {
                $alt = ltrim(str_replace('\\', '/', $clean), '/');
                if (isset($ctx->page_names[$alt])) $name = $ctx->page_names[$alt];
            }
        }
        if ($name === null) $name = pg_di_slug(pathinfo($clean, PATHINFO_FILENAME));
        return OUTPUT_PATH . $name . $frag;
    }
    return $ref;
}

function pg_di_rewrite_srcset($val, $ctx)
{
    $parts = array_map('trim', explode(',', $val));
    foreach ($parts as $i => $p) {
        if ($p === '') continue;
        $bits = preg_split('/\s+/', $p, 2);
        $bits[0] = pg_di_rewrite_ref($bits[0], $ctx);
        $parts[$i] = implode(' ', $bits);
    }
    return implode(', ', $parts);
}

// src= / href= / poster= / srcset= inside a markup string (heading and
// paragraph innerHTML, custom_html blocks).
function pg_di_rewrite_html_refs($html, $ctx)
{
    if ($html === '' || strpos($html, '=') === false) return $html;
    $html = preg_replace_callback('/\b(src|href|poster|data-src)\s*=\s*(["\'])(.*?)\2/i', function ($m) use ($ctx) {
        return $m[1] . '=' . $m[2] . pg_di_rewrite_ref(html_entity_decode($m[3], ENT_QUOTES, 'UTF-8'), $ctx) . $m[2];
    }, $html);
    $html = preg_replace_callback('/\b(srcset)\s*=\s*(["\'])(.*?)\2/i', function ($m) use ($ctx) {
        return $m[1] . '=' . $m[2] . pg_di_rewrite_srcset(html_entity_decode($m[3], ENT_QUOTES, 'UTF-8'), $ctx) . $m[2];
    }, $html);
    return $html;
}

// url(...) and @import inside CSS text, relative to the stylesheet's own
// directory in the archive.
function pg_di_rewrite_css_urls($css, $css_dir, $ctx)
{
    if ($css === '' || stripos($css, 'url(') === false && stripos($css, '@import') === false) return $css;
    $saved = isset($ctx->base_dir) ? $ctx->base_dir : '';
    $ctx->base_dir = ($css_dir === '.' || $css_dir === '/') ? '' : $css_dir;
    $css = preg_replace_callback('/url\(\s*(["\']?)([^"\')]+)\1\s*\)/i', function ($m) use ($ctx) {
        $r = pg_di_rewrite_ref($m[2], $ctx);
        return 'url(' . $m[1] . $r . $m[1] . ')';
    }, $css);
    $css = preg_replace_callback('/@import\s+(["\'])([^"\']+)\1/i', function ($m) use ($ctx) {
        return '@import ' . $m[1] . pg_di_rewrite_ref($m[2], $ctx) . $m[1];
    }, $css);
    $ctx->base_dir = $saved;
    return $css;
}

// ── Google Fonts ────────────────────────────────────────────────────────────

// https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=Lora
// https://fonts.googleapis.com/css?family=Roboto:300,400|Open+Sans
function pg_di_parse_google_fonts($url)
{
    $out = array();
    $q = (string)parse_url($url, PHP_URL_QUERY);
    if ($q === '') return $out;
    foreach (explode('&', $q) as $kv) {
        if (strpos($kv, 'family=') !== 0) continue;
        $val = urldecode(substr($kv, 7));
        foreach (explode('|', $val) as $fam) {
            $fam = trim($fam);
            if ($fam === '') continue;
            $name = $fam; $weights = array();
            if (strpos($fam, ':') !== false) {
                list($name, $spec) = explode(':', $fam, 2);
                if (preg_match('/wght@([\d;]+)/', $spec, $m)) {
                    foreach (explode(';', $m[1]) as $w) if ($w !== '') $weights[] = (int)$w;
                } elseif (preg_match('/ital,wght@([^&]+)/', $spec, $m)) {
                    foreach (explode(';', $m[1]) as $pair) { $p = explode(',', $pair); if (isset($p[1])) $weights[] = (int)$p[1]; }
                } else {
                    foreach (preg_split('/[,;]/', $spec) as $w) if (preg_match('/^\d{3}$/', $w)) $weights[] = (int)$w;
                }
            }
            $weights = array_values(array_unique($weights));
            sort($weights);
            if (!$weights) $weights = array(400);
            $out[] = array('family' => str_replace('+', ' ', trim($name)), 'weights' => $weights, 'enabled' => true, 'movedToCss' => false);
        }
    }
    return $out;
}

// ── Assets-panel entries ────────────────────────────────────────────────────

function pg_di_asset_entry($type, $name, $content, $is_file)
{
    return array(
        'id'      => 'imp_' . substr(md5($type . '|' . $name . '|' . $content), 0, 10),
        'name'    => $name,
        'type'    => $type,
        'content' => $content,
        'enabled' => true,
        '_file'   => (bool)$is_file,
        'locked'  => false,
        'meta'    => '',
    );
}

function pg_di_dedupe_assets($list)
{
    $seen = array(); $out = array();
    foreach ($list as $a) {
        $k = $a['type'] . '|' . ($a['type'] === 'css' || $a['type'] === 'js' ? $a['name'] . '|' . md5($a['content']) : $a['content']);
        if (isset($seen[$k])) continue;
        $seen[$k] = true; $out[] = $a;
    }
    return $out;
}

function pg_di_dedupe_fonts($list)
{
    $by = array();
    foreach ($list as $f) {
        $k = strtolower($f['family']);
        if (!isset($by[$k])) { $by[$k] = $f; continue; }
        $by[$k]['weights'] = array_values(array_unique(array_merge($by[$k]['weights'], $f['weights'])));
        sort($by[$k]['weights']);
    }
    return array_values($by);
}

// ── Names ───────────────────────────────────────────────────────────────────

function pg_di_slug($s)
{
    $s = function_exists('pg_transliterate_to_ascii') ? pg_transliterate_to_ascii($s) : $s;
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return $s === '' ? 'page' : $s;
}

// index.html → index; blog/post.html → post (or blog-post when "post" is
// taken, first by the caller's open tabs, then by the archive, then by the
// database).
function pg_di_page_name($path, $taken, &$seen)
{
    $base = pg_di_slug(pathinfo($path, PATHINFO_FILENAME));
    $dir  = str_replace('\\', '/', dirname($path));
    $cands = array($base);
    if ($dir !== '.' && $dir !== '') {
        $cands[] = pg_di_slug(str_replace('/', '-', $dir) . '-' . $base);
    }
    foreach ($cands as $c) {
        if (in_array($c, $taken, true) || in_array($c, $seen, true)) continue;
        $exists = (int)db_value("SELECT page_id FROM page WHERE page_name = '" . e($c) . "' LIMIT 1");
        if ($exists) continue;
        return $c;
    }
    $c = $cands[count($cands) - 1];
    for ($i = 2; $i < 500; $i++) {
        $try = $c . '-' . $i;
        if (in_array($try, $taken, true) || in_array($try, $seen, true)) continue;
        if ((int)db_value("SELECT page_id FROM page WHERE page_name = '" . e($try) . "' LIMIT 1")) continue;
        return $try;
    }
    return $c . '-' . uniqid();
}

// ── File manager ────────────────────────────────────────────────────────────

// Find-or-create a chain of folders under the root: array('Import','mysite','assets','css').
function pg_di_folder_path($segments, $ctx)
{
    $parent = (int)db_value("SELECT folder_id FROM folder WHERE folder_parent = '0' ORDER BY folder_id ASC LIMIT 1");
    $level  = (int)db_value("SELECT folder_level FROM folder WHERE folder_id = '$parent' LIMIT 1");
    $key    = '';
    foreach ($segments as $seg) {
        $seg = trim((string)$seg);
        if ($seg === '') continue;
        $key .= '/' . $seg;
        if (isset($ctx->folders[$key])) { $parent = $ctx->folders[$key]; $level++; continue; }
        $found = (int)db_value(
            "SELECT folder_id FROM folder WHERE folder_parent = '$parent' AND folder_name = '" . e($seg) . "' LIMIT 1");
        if (!$found) {
            $level++;
            db("INSERT INTO folder (folder_name, folder_parent, folder_level, folder_order, folder_user, folder_timestamp)
                VALUES ('" . e(mb_substr($seg, 0, 100)) . "', '$parent', '$level', '0', '" . (int)$ctx->user_id . "', UNIX_TIMESTAMP())");
            $found = (int)mysqli_insert_id(db::$con);
        } else {
            $level++;
        }
        $ctx->folders[$key] = $found;
        $parent = $found;
    }
    return $parent;
}

// Flat, unique, URL-safe name for an archive file. Reserved up front so
// references can be rewritten before the file is written.
function pg_di_reserve_file_name($basename, $ctx)
{
    static $reserved = array();
    $name = prepare_file_name($basename);
    $name = get_unique_name(array('name' => $name, 'type' => 'file'));
    // get_unique_name only checks the database; two archive entries with the
    // same basename in different directories would otherwise collide here.
    $i = 1; $base = $name;
    while (isset($reserved[$name])) {
        $ext = pathinfo($base, PATHINFO_EXTENSION);
        $stem = $ext !== '' ? substr($base, 0, -strlen($ext) - 1) : $base;
        $name = $stem . '[' . $i . ']' . ($ext !== '' ? '.' . $ext : '');
        $i++;
    }
    $reserved[$name] = true;
    return $name;
}

function pg_di_store_file($path, $content, $ctx)
{
    $rec  = $ctx->files[$path];
    $name = $rec['name'];
    $ext  = $rec['ext'];
    $dir  = str_replace('\\', '/', dirname($path));
    $segs = array('Import', $ctx->project);
    if ($dir !== '.' && $dir !== '') foreach (explode('/', $dir) as $s) $segs[] = $s;
    $folder_id = pg_di_folder_path($segs, $ctx);

    if (@file_put_contents(FILE_DIRECTORY_PATH . '/' . $name, $content) === false) {
        $ctx->warnings[] = lang(array('string' => '{var:1} could not be written to disk.', 'vars' => $path));
        return;
    }
    $design = in_array($ext, array('css', 'js', 'svg', 'ttf', 'otf', 'eot', 'woff', 'woff2', 'ico'), true) ? 1 : 0;
    // files.type is VARCHAR(4) — "woff2" is stored as "woff", same as the
    // upload screen does.
    db("INSERT INTO files (name, folder, description, type, size, design, user, timestamp)
        VALUES ('" . e($name) . "', '" . (int)$folder_id . "', '', '" . e(substr($ext, 0, 4)) . "',
                '" . (int)strlen($content) . "', '$design', '" . (int)$ctx->user_id . "', UNIX_TIMESTAMP())");
}

// Peel a wrapper directory shared by every entry ("site/", "dist/").
function pg_di_strip_common_prefix($entries)
{
    $paths = array_keys($entries);
    if (count($paths) < 1) return $entries;
    $first = explode('/', $paths[0]);
    $prefix = array();
    for ($i = 0; $i < count($first) - 1; $i++) {
        $seg = $first[$i];
        $all = true;
        foreach ($paths as $p) {
            $parts = explode('/', $p);
            if (count($parts) <= $i + 1 || $parts[$i] !== $seg) { $all = false; break; }
        }
        if (!$all) break;
        $prefix[] = $seg;
    }
    if (!$prefix) return $entries;
    $cut = strlen(implode('/', $prefix)) + 1;
    $out = array();
    foreach ($entries as $p => $e) $out[substr($p, $cut)] = $e;
    return $out;
}
