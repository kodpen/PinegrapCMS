<?php

/**
 *
 * Pinegrap
 *
 * @package Pinegrap
 * @author Camelback Web Architects, Kodpen
 * @link https://pinegrap.com
 * @copyright 2001-2019 Camelback Consulting, Inc. (DBA Camelback Web Architects)
 * @copyright 2019-2026 Kodpen
 * @license https://opensource.org/licenses/mit-license.html MIT License
 *
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ========================= LIVE PICTURES OF DESIGNS ==============================================
//
// A template or a design is shown as its real page, not as a stored image:
// a 1280x800 frame of the page scaled into a box. The frame is script-free
// (sandbox=""), not focusable and hidden from assistive technology; the
// box carries the label. It is made only when the box comes into view, and
// a box whose page does not arrive in 12 seconds shows its fallback (the
// drawing of pg_design_thumb_svg(), when the caller gives one).
// assets/js/pg_live_picture.js (window.PGLivePicture) does the browser
// side; the box is styled by .pg-live-picture in backend.src.css.
//
// The tour (pg_design_tour_html()) shows every page and e-mail of a
// template full size in a tab of its own, one after the other.

// The box of one live picture. $src: the page's address (unescaped; ''
// when there is none: the fallback shows at once). $opts:
//   label     what the picture shows, read out instead of the frame
//   class     more classes for the box
//   fallback  HTML shown when the page cannot be drawn (trusted markup)
//   width     the width the page is drawn at (1280)
//   height    its height (800)
function pg_design_live_picture($src, $opts = array())
{
    $src = is_scalar($src) ? trim((string)$src) : '';
    $label = isset($opts['label']) && is_scalar($opts['label']) ? (string)$opts['label'] : '';
    $class = isset($opts['class']) && is_scalar($opts['class']) ? trim((string)$opts['class']) : '';
    $fallback = isset($opts['fallback']) && is_string($opts['fallback']) ? $opts['fallback'] : '';
    $width = isset($opts['width']) ? max(320, min(2560, (int)$opts['width'])) : 1280;
    $height = isset($opts['height']) ? max(200, min(2560, (int)$opts['height'])) : 800;

    $classes = 'pg-live-picture' . ($class !== '' ? ' ' . $class : '') . ($src === '' ? ' pg-failed' : '');
    $html = '<div class="' . h($classes) . '" data-pg-live-picture';
    if ($src !== '') $html .= ' data-src="' . h($src) . '"';
    if ($width !== 1280 || $height !== 800) $html .= ' data-width="' . $width . '" data-height="' . $height . '"';
    if ($label !== '') $html .= ' role="img" aria-label="' . h($label) . '"';
    $html .= '>';
    if ($src !== '') $html .= '<div class="pg-live-picture-wait" aria-hidden="true"></div>';
    if ($fallback !== '') {
        $html .= '<div class="pg-live-picture-fallback"' . ($src !== '' ? ' hidden' : '') . '>' . $fallback . '</div>';
    }
    return $html . '</div>';
}

// The address of a template page, or of its tour, in the installer or in
// the panel; '' when the place or the template is not one there can be.
// $where: 'install' (the installer's own page, before a site exists:
// install/index.php?install_action=design_preview|design_tour) or 'panel'
// (design_preview.php). $args:
//   template  the template's id
//   page      the page's key; '{page}' is kept for
//             pg_design_template_preview_html() to fill in
//   look      a look key, dropped unless pg_design_looks() has it
//   palette   a palette key, dropped unless pg_design_palettes() has it
//   tour      true: the tour, starting on the page
//   still     true: the page without its scripts, for a live picture,
//             whose frame runs none (the panel only)
//   local     the installer's language (the installer only)
function pg_design_preview_address($where, $args = array())
{
    $arg = function ($key) use ($args) {
        return (isset($args[$key]) && is_scalar($args[$key])) ? (string)$args[$key] : '';
    };
    $template = $arg('template');
    if (!preg_match('/^[a-z0-9-]{1,64}$/', $template)) return '';

    $query = array();
    if ($where === 'install') {
        $query['install_action'] = !empty($args['tour']) ? 'design_tour' : 'design_preview';
        $prefix = '?';
    } elseif ($where === 'panel') {
        $prefix = ((defined('PATH') && defined('SOFTWARE_DIRECTORY')) ? PATH . SOFTWARE_DIRECTORY . '/' : '') . 'design_preview.php?';
    } else {
        return '';
    }
    $query['template'] = $template;

    $look = $arg('look');
    $looks = function_exists('pg_design_looks') ? pg_design_looks() : array();
    if ($look !== '' && isset($looks[$look])) $query['look'] = $look;
    $palette = $arg('palette');
    $palettes = function_exists('pg_design_palettes') ? pg_design_palettes() : array();
    if ($palette !== '' && isset($palettes[$palette])) $query['palette'] = $palette;

    if ($where === 'panel' && !empty($args['tour'])) $query['tour'] = '1';
    if ($where === 'panel' && !empty($args['still']) && empty($args['tour'])) $query['still'] = '1';
    $local = $arg('local');
    if ($where === 'install' && preg_match('/^[a-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/', $local)) $query['local'] = $local;

    $url = $prefix . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    $page = $arg('page');
    if ($page === '{page}') {
        $url .= '&page={page}';
    } elseif (preg_match('/^[a-z0-9_-]{1,64}$/', $page)) {
        $url .= '&page=' . rawurlencode($page);
    }
    return $url;
}

// The tour of a template: a bar (the template's name and theme, a list of
// its pages with an E-mails group, previous and next, "n / total", desktop
// or phone, the continue button) over a frame showing one page full size.
// The frame's own links open the template's other pages in place; each page
// says which it is (postMessage {pgDesignPreview: key}) and the bar follows.
// The whole HTML document; '' for a template with nothing to show. $opts:
//   where           'install' or 'panel' (pg_design_preview_address())
//   template        the template's id
//   page            the page to start on (else the first)
//   look, palette   the theme; when not given, the template's own
//   local           the installer's language
//   continue_href   where the continue button leads ('' : it closes the tab)
//   continue_label  its label (default "Continue with this template")
function pg_design_tour_html($opts = array())
{
    $opt = function ($key) use ($opts) {
        return (isset($opts[$key]) && is_scalar($opts[$key])) ? (string)$opts[$key] : '';
    };
    $where = $opt('where') === 'panel' ? 'panel' : 'install';
    $tpl = pg_design_template($opt('template'));
    if (!$tpl) return '';
    $summary = pg_design_template_summary($tpl);

    $pages = pg_design_template_preview_pages($tpl);
    $mails = array_diff_key(pg_design_template_email_pages($tpl), $pages);
    if (!$pages && !$mails) return '';
    $keys = array_merge(array_keys($pages), array_keys($mails));
    $start = $opt('page');
    if (!in_array($start, $keys, true)) $start = (string)$keys[0];

    $theme = pg_design_template_theme($tpl);
    $look = array_key_exists('look', $opts) ? $opt('look') : (string)$theme['look'];
    $palette = array_key_exists('palette', $opts) ? $opt('palette') : (string)$theme['palette'];
    $looks = pg_design_looks();
    $palettes = pg_design_palettes();
    if (!isset($looks[$look])) $look = '';
    if (!isset($palettes[$palette])) $palette = '';

    $address = function ($page) use ($where, $summary, $look, $palette, $opt) {
        return pg_design_preview_address($where, array(
            'template' => $summary['id'], 'look' => $look, 'palette' => $palette, 'local' => $opt('local'), 'page' => $page,
        ));
    };

    $about = array(lang('Template preview'));
    if ($look !== '') $about[] = $looks[$look]['name'];
    if ($palette !== '') $about[] = $palettes[$palette]['name'];
    $about[] = lang('sample content');

    $options = function ($list) use ($start) {
        $out = '';
        foreach ($list as $key => $title) {
            $out .= '<option value="' . h((string)$key) . '"' . ((string)$key === $start ? ' selected' : '') . '>' . h($title !== '' ? $title : (string)$key) . '</option>';
        }
        return $out;
    };
    $select = $options($pages);
    if ($mails) $select .= '<optgroup label="' . h(lang('E-mails')) . '">' . $options($mails) . '</optgroup>';

    $continue_label = $opt('continue_label') !== '' ? $opt('continue_label') : lang('Continue with this template');
    $continue_href = $opt('continue_href');
    if ($continue_href !== '') {
        $continue = '<a class="pg-tour-go" id="pg_tour_continue" href="' . h($continue_href) . '" target="_top"><i class="bi bi-check2" aria-hidden="true"></i>' . h($continue_label) . '</a>';
    } else {
        $continue = '<button type="button" class="pg-tour-go" id="pg_tour_continue"><i class="bi bi-check2" aria-hidden="true"></i>' . h($continue_label) . '</button>'
            . '<span class="pg-tour-closed" id="pg_tour_closed" role="status" hidden>' . h(lang('You can close this tab.')) . '</span>';
    }

    $position = array_search($start, $keys, true) + 1;
    $language = lang(array('info' => true));

    return '<!doctype html><html lang="' . h(is_string($language) ? $language : 'en') . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . h($summary['name'] . ' - ' . lang('Template preview')) . '</title>'
        . '<link rel="stylesheet" href="' . h(pg_design_icons_url()) . '">'
        . '<style>' . _pg_design_tour_css() . '</style></head><body>'
        . '<header class="pg-tour-bar">'
        . '<div class="pg-tour-name"><i class="bi ' . h($summary['icon']) . '" aria-hidden="true"></i><div><strong>' . h($summary['name']) . '</strong><small>' . h(implode(' · ', $about)) . '</small></div></div>'
        . '<nav class="pg-tour-nav" aria-label="' . h(lang('Pages')) . '">'
        . '<button type="button" id="pg_tour_prev" title="' . h(lang('Previous page')) . '" aria-label="' . h(lang('Previous page')) . '"' . ($position === 1 ? ' disabled' : '') . '><i class="bi bi-chevron-left" aria-hidden="true"></i></button>'
        . '<select id="pg_tour_page" aria-label="' . h(lang('Page')) . '">' . $select . '</select>'
        . '<button type="button" id="pg_tour_next" title="' . h(lang('Next page')) . '" aria-label="' . h(lang('Next page')) . '"' . ($position === count($keys) ? ' disabled' : '') . '><i class="bi bi-chevron-right" aria-hidden="true"></i></button>'
        . '<span class="pg-tour-count" id="pg_tour_count">' . $position . ' / ' . count($keys) . '</span>'
        . '<span class="pg-tour-device" role="group">'
        . '<button type="button" id="pg_tour_desktop" class="active" aria-pressed="true" title="' . h(lang('Desktop')) . '" aria-label="' . h(lang('Desktop')) . '"><i class="bi bi-display" aria-hidden="true"></i></button>'
        . '<button type="button" id="pg_tour_phone" aria-pressed="false" title="' . h(lang('Phone')) . '" aria-label="' . h(lang('Phone')) . '"><i class="bi bi-phone" aria-hidden="true"></i></button>'
        . '</span></nav>'
        . '<div class="pg-tour-end">' . $continue . '</div>'
        . '</header>'
        . '<main class="pg-tour-stage" id="pg_tour_stage">'
        . '<iframe id="pg_tour_frame" title="' . h($summary['name']) . '" sandbox="allow-scripts" src="' . h($address($start)) . '" data-src="' . h($address('{page}')) . '"></iframe>'
        . '</main>'
        . '<script>' . _pg_design_tour_js() . '</script>'
        . '</body></html>';
}

// The tour's own styles: the page stands alone (the installer has no panel
// stylesheet), light or dark as the system is.
function _pg_design_tour_css()
{
    return ':root{color-scheme:light dark;--b:#fff;--t:#1f2937;--m:#6b7280;--l:#e5e7eb;--s:#f3f4f6;--a:#2563eb;--at:#fff}'
        . '@media (prefers-color-scheme:dark){:root{--b:#1f2328;--t:#e6e8eb;--m:#9aa1a9;--l:#353b42;--s:#2a2f35;--a:#5b8def;--at:#0d1117}}'
        . '*{box-sizing:border-box}html,body{height:100%;margin:0}'
        . 'body{display:flex;flex-direction:column;background:var(--s);color:var(--t);font:14px/1.4 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
        . '.pg-tour-bar{display:flex;flex-wrap:wrap;align-items:center;gap:.5rem 1rem;padding:.5rem 1rem;background:var(--b);border-bottom:1px solid var(--l)}'
        . '.pg-tour-name{display:flex;align-items:center;gap:.6rem;min-width:0;flex:1 1 14rem}.pg-tour-name>i{font-size:1.5rem;color:var(--a)}'
        . '.pg-tour-name div{display:flex;flex-direction:column;min-width:0}.pg-tour-name small{color:var(--m);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
        . '.pg-tour-nav{display:flex;align-items:center;gap:.4rem;flex-wrap:wrap}'
        . '.pg-tour-bar button,.pg-tour-bar select{height:2.1rem;border:1px solid var(--l);border-radius:.5rem;background:var(--b);color:var(--t);font:inherit}'
        . '.pg-tour-bar button{min-width:2.1rem;cursor:pointer}.pg-tour-bar button:disabled{opacity:.45;cursor:default}'
        . '.pg-tour-bar select{max-width:16rem;padding:0 .5rem}'
        . '.pg-tour-count{min-width:3.5rem;text-align:center;color:var(--m);font-variant-numeric:tabular-nums}'
        . '.pg-tour-device{display:inline-flex}.pg-tour-device button{border-radius:0}.pg-tour-device button:first-child{border-radius:.5rem 0 0 .5rem}.pg-tour-device button:last-child{border-radius:0 .5rem .5rem 0;border-left:0}'
        . '.pg-tour-device button.active{background:var(--a);border-color:var(--a);color:var(--at)}'
        . '.pg-tour-end{display:flex;align-items:center;gap:.6rem}'
        . '.pg-tour-go{display:inline-flex;align-items:center;gap:.4rem;height:2.1rem;padding:0 .9rem;border:0!important;border-radius:999px!important;background:var(--a)!important;color:var(--at)!important;text-decoration:none;font-weight:600}'
        . '.pg-tour-closed{color:var(--m)}'
        . ':focus-visible{outline:2px solid var(--a);outline-offset:2px}'
        . '.pg-tour-stage{flex:1 1 auto;display:flex;justify-content:center;min-height:0}'
        . '#pg_tour_frame{width:100%;height:100%;border:0;background:#fff}'
        . '.pg-tour-stage.pg-phone{padding:1rem 0}.pg-tour-stage.pg-phone #pg_tour_frame{width:390px;max-width:100%;border-radius:.75rem;box-shadow:0 0 0 1px var(--l),0 .5rem 1.5rem rgba(0,0,0,.12)}'
        . '@media (max-width:600px){.pg-tour-bar select{max-width:10rem}.pg-tour-count{display:none}}';
}

// The tour's script: the page list, previous and next, the count and the
// device follow the frame; the frame's message is taken only from the frame.
function _pg_design_tour_js()
{
    return '(function(){'
        . 'var f=document.getElementById("pg_tour_frame"),s=document.getElementById("pg_tour_page"),'
        . 'p=document.getElementById("pg_tour_prev"),n=document.getElementById("pg_tour_next"),'
        . 'c=document.getElementById("pg_tour_count"),st=document.getElementById("pg_tour_stage"),'
        . 'd=document.getElementById("pg_tour_desktop"),m=document.getElementById("pg_tour_phone"),'
        . 'go=document.getElementById("pg_tour_continue"),cl=document.getElementById("pg_tour_closed");'
        . 'var keys=[].map.call(s.options,function(o){return o.value;});'
        . 'function mark(k){var i=keys.indexOf(k);if(i<0)return;s.value=k;p.disabled=i===0;n.disabled=i===keys.length-1;c.textContent=(i+1)+" / "+keys.length;}'
        . 'function open(k){mark(k);f.src=f.getAttribute("data-src").replace("{page}",encodeURIComponent(k));}'
        . 's.addEventListener("change",function(){open(s.value);});'
        . 'p.addEventListener("click",function(){var i=keys.indexOf(s.value);if(i>0)open(keys[i-1]);});'
        . 'n.addEventListener("click",function(){var i=keys.indexOf(s.value);if(i<keys.length-1)open(keys[i+1]);});'
        . 'function device(phone){st.classList.toggle("pg-phone",phone);d.classList.toggle("active",!phone);m.classList.toggle("active",phone);'
        . 'd.setAttribute("aria-pressed",String(!phone));m.setAttribute("aria-pressed",String(phone));}'
        . 'd.addEventListener("click",function(){device(false);});m.addEventListener("click",function(){device(true);});'
        . 'window.addEventListener("message",function(e){if(e.source!==f.contentWindow||!e.data||typeof e.data.pgDesignPreview!=="string")return;mark(e.data.pgDesignPreview);});'
        . 'if(cl&&go){go.addEventListener("click",function(){try{window.close();}catch(x){}setTimeout(function(){cl.hidden=false;},300);});}'
        . 'if(window.matchMedia&&window.matchMedia("(max-width:600px)").matches)device(true);'
        . '})();';
}

// ========================= THE PANEL'S PREVIEW ADDRESS ===========================================
//
// design_preview.php draws, for a signed-in operator, a template page (any
// template the site can load, custom ones too), a template's tour, or a
// visual design page as a first-time visitor sees it. The helpers below are
// the pure parts of that file.

// What a request to design_preview.php asks for, from its query ($get:
// $_GET). 'kind': 'page' (page_id), 'tour' (template and tour=1),
// 'template' (template) or '' (nothing it can draw); 'still': a template
// page asked for without its scripts (still=1). Look and palette are
// kept only when pg_design_looks()/pg_design_palettes() know them; template
// and page keys only when they match the patterns the addresses use.
function pg_design_preview_request($get)
{
    $get = is_array($get) ? $get : array();
    $arg = function ($key) use ($get) {
        return (isset($get[$key]) && is_scalar($get[$key])) ? trim((string)$get[$key]) : '';
    };
    $out = array('kind' => '', 'page_id' => 0, 'template' => '', 'page' => '', 'look' => '', 'palette' => '', 'still' => false);

    $page_id = $arg('page_id');
    if ($page_id !== '') {
        if (preg_match('/^[1-9][0-9]{0,9}$/', $page_id)) {
            $out['kind'] = 'page';
            $out['page_id'] = (int)$page_id;
        }
        return $out;
    }

    $template = $arg('template');
    if (!preg_match('/^[a-z0-9-]{1,64}$/', $template)) return $out;
    $out['template'] = $template;
    $out['kind'] = $arg('tour') === '1' ? 'tour' : 'template';
    $out['still'] = ($out['kind'] === 'template' && $arg('still') === '1');

    $page = $arg('page');
    if (preg_match('/^[a-z0-9_-]{1,64}$/', $page)) $out['page'] = $page;
    $look = $arg('look');
    $looks = function_exists('pg_design_looks') ? pg_design_looks() : array();
    if ($look !== '' && isset($looks[$look])) $out['look'] = $look;
    $palette = $arg('palette');
    $palettes = function_exists('pg_design_palettes') ? pg_design_palettes() : array();
    if ($palette !== '' && isset($palettes[$palette])) $out['palette'] = $palette;
    return $out;
}

// Where the tour's "Use This Template" leads: the editor starting from the
// template in the look and palette the tour shows (relative to the
// software directory, as the Choose a Template cards link it).
function pg_design_preview_use_href($template, $look = '', $palette = '')
{
    $query = array('start' => 'template', 'template' => (string)$template);
    if ((string)$look !== '') $query['look'] = (string)$look;
    if ((string)$palette !== '') $query['palette'] = (string)$palette;
    return 'add_system_style.php?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
}

// A page as a picture shows it: no script runs in a live picture's frame,
// so none is sent: no script element (inline, linked, or a module) and no
// event handler attribute (an image's onerror fallback, an onclick). Only
// tags are touched, quoted values read whole, so text that reads like an
// attribute stays.
function pg_design_preview_strip_scripts($html)
{
    $html = (string)$html;
    $out = preg_replace('~<script\b[^>]*>.*?</script\s*>~is', '', $html);
    if ($out === null) return $html;
    $out = preg_replace('~<script\b[^>]*/?>~i', '', $out);
    if ($out === null) return $html;
    $tags = preg_replace_callback('~<[a-zA-Z](?:"[^"]*"|\'[^\']*\'|[^\'">])*>~', function ($m) {
        $tag = preg_replace('~\s+on[a-zA-Z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $m[0]);
        return $tag === null ? $m[0] : $tag;
    }, $out);
    return $tags === null ? $out : $tags;
}

// The ETag of a design page's picture: the page, its last change, its
// design's last change and the software version (a new release may draw the
// same page differently). The cache is private, so the operator is not part
// of it.
function pg_design_preview_etag($page_id, $page_changed, $style_changed, $version = '')
{
    return '"dp-' . substr(sha1((int)$page_id . '|' . (int)$page_changed . '|' . (int)$style_changed . '|' . (string)$version), 0, 20) . '"';
}

// Whether the browser's If-None-Match ($header) names the ETag; '*' and a
// weak form (W/"...") count.
function pg_design_preview_etag_matches($header, $etag)
{
    $header = trim((string)$header);
    if ($header === '' || (string)$etag === '') return false;
    if ($header === '*') return true;
    foreach (explode(',', $header) as $tag) {
        $tag = trim($tag);
        if (strncmp($tag, 'W/', 2) === 0) $tag = substr($tag, 2);
        if ($tag === (string)$etag) return true;
    }
    return false;
}

// The plain page shown when there is nothing to draw: a template page that
// cannot be drawn, a design page that cannot be opened or rendered. Calm in
// both schemes; the text is the only content.
function pg_design_preview_placeholder_html($text)
{
    $language = function_exists('lang') ? lang(array('info' => true)) : 'en';
    return '<!doctype html><html lang="' . h(is_string($language) ? $language : 'en') . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>' . h($text) . '</title>'
        . '<style>:root{color-scheme:light dark}html,body{height:100%;margin:0}'
        . 'body{display:flex;align-items:center;justify-content:center;background:#f3f4f6;color:#6b7280;font:16px/1.4 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
        . '@media (prefers-color-scheme:dark){body{background:#1f2328;color:#9aa1a9}}</style>'
        . '</head><body><p>' . h($text) . '</p></body></html>';
}
