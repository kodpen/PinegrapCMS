<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The Bootstrap 3 to 5.3.8 converter of a Legacy design: one class map and
 * pure functions that turn a page tree, a class list or a stylesheet's
 * selectors from Bootstrap 3 names to their 5.3.8 names, and count what was
 * converted, what stayed and what needs a designer's hand.
 *
 * Nothing here reads or writes the database: the card, the trial design and
 * the page move call these functions on rows they read themselves.
 *
 * Breakpoints are mapped by name (xs to the base, sm, md, lg), as the grid's
 * col-sm-* and col-md-* keep their names in 5.3.8.
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
 * The class map: a Bootstrap 3 name (or a theme name 5.3.8 took over) and
 * its 5.3.8 classes, space separated; '' drops the class. Visibility
 * classes, push/pull and icons are worked by their own rules
 * (pg_lbs_classes()) and are not listed here.
 *
 * @return array  name => replacement
 */
function pg_lbs_class_map()
{
    static $map = null;
    if ($map !== null) return $map;

    $map = array(
        // Helpers and text.
        'pull-left' => 'float-start', 'pull-right' => 'float-end',
        'text-left' => 'text-start', 'text-right' => 'text-end',
        'center-block' => 'd-block mx-auto',
        'sr-only' => 'visually-hidden', 'sr-only-focusable' => 'visually-hidden-focusable',
        'blockquote-reverse' => 'text-end',
        // Buttons.
        'btn-default' => 'btn-light', 'btn-xs' => 'btn-sm', 'btn-block' => 'w-100',
        'btn-group-xs' => 'btn-group-sm', 'btn-group-justified' => 'd-flex',
        // Images and embeds.
        'img-responsive' => 'img-fluid', 'img-circle' => 'rounded-circle', 'img-rounded' => 'rounded',
        'embed-responsive' => 'ratio', 'embed-responsive-16by9' => 'ratio-16x9',
        'embed-responsive-4by3' => 'ratio-4x3', 'embed-responsive-item' => '',
        // Panels and wells to cards, labels to badges.
        'panel' => 'card', 'panel-heading' => 'card-header', 'panel-body' => 'card-body',
        'panel-footer' => 'card-footer', 'panel-title' => 'card-title', 'panel-default' => '',
        'panel-primary' => 'border-primary', 'panel-success' => 'border-success', 'panel-info' => 'border-info',
        'panel-warning' => 'border-warning', 'panel-danger' => 'border-danger', 'panel-group' => '',
        'well' => 'card card-body', 'well-sm' => '', 'well-lg' => '', 'thumbnail' => 'card',
        'label' => 'badge', 'label-default' => 'text-bg-secondary', 'label-primary' => 'text-bg-primary',
        'label-success' => 'text-bg-success', 'label-info' => 'text-bg-info',
        'label-warning' => 'text-bg-warning', 'label-danger' => 'text-bg-danger',
        'jumbotron' => 'p-5 mb-4 bg-body-tertiary rounded-3', 'page-header' => 'pb-2 mt-4 mb-2 border-bottom',
        // Forms.
        'form-group' => 'mb-3', 'control-label' => 'form-label', 'help-block' => 'form-text',
        'form-control-static' => 'form-control-plaintext', 'input-group-addon' => 'input-group-text',
        'input-lg' => 'form-control-lg', 'input-sm' => 'form-control-sm',
        'form-group-lg' => '', 'form-group-sm' => '', 'form-horizontal' => '',
        'checkbox' => 'form-check', 'radio' => 'form-check',
        'checkbox-inline' => 'form-check form-check-inline', 'radio-inline' => 'form-check form-check-inline',
        // Navs, navbars, dropdowns, lists, tables, alerts, progress, media.
        'nav-stacked' => 'flex-column', 'nav-tabs-justified' => 'nav-justified',
        'navbar-default' => 'bg-body-tertiary', 'navbar-inverse' => 'navbar-dark text-bg-dark',
        'navbar-toggle' => 'navbar-toggler', 'navbar-header' => '', 'navbar-right' => 'ms-auto',
        'navbar-left' => 'me-auto', 'navbar-fixed-top' => 'fixed-top', 'navbar-fixed-bottom' => 'fixed-bottom',
        'navbar-static-top' => '', 'navbar-form' => 'd-flex', 'navbar-btn' => '', 'navbar-link' => '',
        'dropdown-menu-right' => 'dropdown-menu-end', 'dropdown-menu-left' => 'dropdown-menu-start',
        'caret' => '', 'table-condensed' => 'table-sm', 'alert-dismissable' => 'alert-dismissible',
        'progress-bar-success' => 'bg-success', 'progress-bar-info' => 'bg-info',
        'progress-bar-warning' => 'bg-warning', 'progress-bar-danger' => 'bg-danger',
        'media' => 'd-flex', 'media-body' => 'flex-grow-1', 'media-left' => 'flex-shrink-0 me-3',
        'media-right' => 'flex-shrink-0 ms-3', 'media-middle' => 'align-self-center',
        'media-bottom' => 'align-self-end', 'media-heading' => 'mt-0', 'media-object' => '',
        'media-list' => 'list-unstyled',
    );

    // The grid: col-xs-N to col-N, offsets to offset-*, the reset of a push
    // or pull (-0) dropped; 5.3.8 has no offset-12 and no base offset-0.
    foreach (array('xs', 'sm', 'md', 'lg') as $bp) {
        $infix = ($bp === 'xs') ? '' : $bp . '-';
        for ($n = 0; $n <= 12; $n++) {
            if ($bp === 'xs' && $n > 0) $map['col-xs-' . $n] = 'col-' . $n;
            $map['col-' . $bp . '-offset-' . $n] = ($n === 12 || ($n === 0 && $bp === 'xs')) ? '' : 'offset-' . $infix . $n;
        }
        $map['col-' . $bp . '-push-0'] = '';
        $map['col-' . $bp . '-pull-0'] = '';
    }

    // Theme names that 5.3.8 owns as !important utilities: the theme's rule
    // would lose to them, so the markup and the sheet copies both get the
    // theme's own name. Never a name this map writes, or a second pass would
    // rename the map's own output.
    foreach (pg_lbs_theme_names() as $name) {
        $map[$name] = 'legacy-' . $name;
    }
    return $map;
}

/**
 * The theme names 5.3.8 took over as !important utilities, found in the
 * starting sites' themes (census, docs/memory/designer/legacy-bootstrap-census.md).
 */
function pg_lbs_theme_names()
{
    return array('bg-primary', 'bg-secondary', 'bg-dark', 'bg-light', 'bg-white',
                 'overflow-hidden', 'align-top', 'align-bottom');
}

/**
 * Glyphicons and Themify icons with a Bootstrap Icons twin.
 *
 * @return array  icon class => Bootstrap Icons name (without bi-)
 */
function pg_lbs_icon_map()
{
    return array(
        'glyphicon-ok' => 'check-lg', 'glyphicon-remove' => 'x-lg', 'glyphicon-search' => 'search',
        'glyphicon-user' => 'person', 'glyphicon-shopping-cart' => 'cart', 'glyphicon-envelope' => 'envelope',
        'glyphicon-home' => 'house', 'glyphicon-star' => 'star-fill', 'glyphicon-star-empty' => 'star',
        'glyphicon-heart' => 'heart-fill', 'glyphicon-heart-empty' => 'heart', 'glyphicon-trash' => 'trash',
        'glyphicon-pencil' => 'pencil', 'glyphicon-plus' => 'plus-lg', 'glyphicon-minus' => 'dash-lg',
        'glyphicon-chevron-left' => 'chevron-left', 'glyphicon-chevron-right' => 'chevron-right',
        'glyphicon-chevron-up' => 'chevron-up', 'glyphicon-chevron-down' => 'chevron-down',
        'glyphicon-arrow-left' => 'arrow-left', 'glyphicon-arrow-right' => 'arrow-right',
        'glyphicon-arrow-up' => 'arrow-up', 'glyphicon-arrow-down' => 'arrow-down',
        'glyphicon-calendar' => 'calendar', 'glyphicon-time' => 'clock', 'glyphicon-lock' => 'lock-fill',
        'glyphicon-info-sign' => 'info-circle-fill', 'glyphicon-question-sign' => 'question-circle-fill',
        'glyphicon-exclamation-sign' => 'exclamation-circle-fill', 'glyphicon-warning-sign' => 'exclamation-triangle-fill',
        'glyphicon-ok-sign' => 'check-circle-fill', 'glyphicon-remove-sign' => 'x-circle-fill',
        'glyphicon-ok-circle' => 'check-circle', 'glyphicon-remove-circle' => 'x-circle',
        'glyphicon-menu-hamburger' => 'list', 'glyphicon-map-marker' => 'geo-alt-fill', 'glyphicon-phone' => 'phone',
        'glyphicon-earphone' => 'telephone-fill', 'glyphicon-download-alt' => 'download', 'glyphicon-upload' => 'upload',
        'glyphicon-refresh' => 'arrow-clockwise', 'glyphicon-cog' => 'gear-fill', 'glyphicon-print' => 'printer',
        'glyphicon-camera' => 'camera-fill', 'glyphicon-picture' => 'image', 'glyphicon-film' => 'film',
        'glyphicon-tag' => 'tag-fill', 'glyphicon-tags' => 'tags-fill', 'glyphicon-comment' => 'chat-fill',
        'glyphicon-log-in' => 'box-arrow-in-right', 'glyphicon-log-out' => 'box-arrow-right',
        'glyphicon-th' => 'grid-3x3-gap-fill', 'glyphicon-th-list' => 'list-ul', 'glyphicon-list' => 'list',
        'glyphicon-globe' => 'globe', 'glyphicon-link' => 'link-45deg', 'glyphicon-eye-open' => 'eye',
        'glyphicon-eye-close' => 'eye-slash', 'glyphicon-thumbs-up' => 'hand-thumbs-up',
        'glyphicon-thumbs-down' => 'hand-thumbs-down', 'glyphicon-file' => 'file-earmark',
        'glyphicon-folder-open' => 'folder2-open', 'glyphicon-folder-close' => 'folder-fill', 'glyphicon-flag' => 'flag-fill',
        'glyphicon-bell' => 'bell-fill', 'glyphicon-gift' => 'gift-fill', 'glyphicon-credit-card' => 'credit-card',
        'glyphicon-share' => 'share', 'glyphicon-share-alt' => 'share-fill', 'glyphicon-play' => 'play-fill',
        'glyphicon-pause' => 'pause-fill', 'glyphicon-stop' => 'stop-fill',
        'ti-facebook' => 'facebook', 'ti-twitter' => 'twitter', 'ti-twitter-alt' => 'twitter', 'ti-youtube' => 'youtube',
        'ti-vimeo' => 'vimeo', 'ti-vimeo-alt' => 'vimeo', 'ti-linkedin' => 'linkedin', 'ti-pinterest' => 'pinterest',
        'ti-pinterest-alt' => 'pinterest', 'ti-dribbble' => 'dribbble', 'ti-instagram' => 'instagram',
        'ti-github' => 'github', 'ti-google' => 'google', 'ti-skype' => 'skype', 'ti-wordpress' => 'wordpress',
        'ti-email' => 'envelope', 'ti-search' => 'search', 'ti-menu' => 'list', 'ti-user' => 'person',
        'ti-heart' => 'heart', 'ti-gift' => 'gift', 'ti-shopping-cart' => 'cart', 'ti-shopping-cart-full' => 'cart-fill',
        'ti-settings' => 'gear', 'ti-lock' => 'lock', 'ti-cloud' => 'cloud', 'ti-palette' => 'palette',
        'ti-layers' => 'layers', 'ti-layers-alt' => 'stack', 'ti-location-arrow' => 'cursor',
        'ti-location-pin' => 'geo-alt', 'ti-comments' => 'chat-dots', 'ti-comment' => 'chat', 'ti-book' => 'book',
        'ti-archive' => 'archive', 'ti-receipt' => 'receipt', 'ti-shield' => 'shield', 'ti-stats-up' => 'graph-up-arrow',
        'ti-world' => 'globe', 'ti-rocket' => 'rocket', 'ti-pulse' => 'activity', 'ti-help-alt' => 'question-circle',
        'ti-id-badge' => 'person-badge', 'ti-notepad' => 'journal-text', 'ti-package' => 'box-seam',
        'ti-panel' => 'sliders', 'ti-infinite' => 'infinity', 'ti-medall' => 'award', 'ti-harddrives' => 'hdd-stack',
        'ti-ruler-pencil' => 'rulers', 'ti-layout-grid4' => 'grid-3x3', 'ti-time' => 'clock', 'ti-check' => 'check-lg',
        'ti-close' => 'x-lg', 'ti-angle-left' => 'chevron-left', 'ti-angle-right' => 'chevron-right',
        'ti-angle-up' => 'chevron-up', 'ti-angle-down' => 'chevron-down', 'ti-arrow-left' => 'arrow-left',
        'ti-arrow-right' => 'arrow-right', 'ti-plus' => 'plus-lg', 'ti-minus' => 'dash-lg', 'ti-mobile' => 'phone',
        'ti-headphone-alt' => 'headset', 'ti-video-camera' => 'camera-video', 'ti-camera' => 'camera',
        'ti-music' => 'music-note', 'ti-calendar' => 'calendar', 'ti-star' => 'star', 'ti-bag' => 'bag',
        'ti-home' => 'house', 'ti-map' => 'map', 'ti-tag' => 'tag', 'ti-download' => 'download', 'ti-upload' => 'upload',
        'ti-share' => 'share', 'ti-link' => 'link-45deg',
    );
}

/**
 * Bootstrap 3 names that 5.3.8 does not know and the map leaves: they stay
 * and are counted, so the report can say what a designer finishes by hand.
 * Push and pull (but -0) and icons with no twin are counted the same way.
 */
function pg_lbs_by_hand_names()
{
    return array('affix', 'carousel-control', 'close', 'dl-horizontal', 'form-control-feedback', 'form-inline',
                 'has-error', 'has-feedback', 'has-success', 'has-warning', 'icon-bar', 'icon-next', 'icon-prev',
                 'input-group-btn', 'list-group-item-heading', 'list-group-item-text', 'nav-divider', 'pager',
                 'popover-content', 'popover-title', 'pre-scrollable', 'progress-striped', 'text-hide', 'text-justify');
}

/**
 * One element's class list converted.
 *
 * @param string $class  the class attribute's value
 * @param array  $rules  for markup no Legacy theme styles (a page type's
 *                       layout): 'theme_names' => false leaves the names of
 *                       pg_lbs_theme_names() alone, 'keep' (names) leaves
 *                       each as written and counts it as kept, 'beside'
 *                       (names) writes each after its 5.3.8 names, 'display'
 *                       is the element's own display (pg_lbs_visibility())
 * @return array  'class' (string), 'converted', 'kept', 'by_hand'
 *                (name => count each)
 */
function pg_lbs_classes($class, $rules = array())
{
    $out = array('class' => '', 'converted' => array(), 'kept' => array(), 'by_hand' => array());
    $tokens = preg_split('/\s+/', trim((string)$class), -1, PREG_SPLIT_NO_EMPTY);
    if (!$tokens) {
        $out['class'] = (string)$class;
        return $out;
    }
    $map = pg_lbs_class_map();
    $icons = pg_lbs_icon_map();
    $by_hand = array_flip(pg_lbs_by_hand_names());
    $have = array_flip($tokens);
    $count = function (&$list, $name) {
        $list[$name] = (isset($list[$name]) ? $list[$name] : 0) + 1;
    };
    $keep = array_flip((array)($rules['keep'] ?? array()));
    $beside = array_flip((array)($rules['beside'] ?? array()));
    // Without a Legacy theme its names are 5.3.8's own utilities.
    $own = (($rules['theme_names'] ?? true) === false) ? array_flip(pg_lbs_theme_names()) : array();

    // The visibility classes of the element become one display set, placed
    // where the first of them stood.
    $visibility = array();
    foreach ($tokens as $t) {
        if (!isset($keep[$t]) && pg_lbs_is_visibility($t)) $visibility[] = $t;
    }
    $display = $visibility ? pg_lbs_visibility($visibility, isset($rules['display']) ? $rules['display'] : 'block') : array();

    $icon_twin = false;
    $result = array();
    $placed = false;
    foreach ($tokens as $t) {
        if (isset($keep[$t])) {
            $count($out['kept'], $t);
            $result[] = $t;
            continue;
        }
        if (isset($own[$t])) {
            $result[] = $t;
            continue;
        }
        if (pg_lbs_is_visibility($t)) {
            if (!$placed) {
                foreach ($display as $d) $result[] = $d;
                $placed = true;
            }
            $count($out['converted'], $t);
            continue;
        }
        if ($t === 'in' && array_intersect(array('fade', 'collapse', 'panel-collapse', 'modal', 'tooltip', 'popover'), $tokens)) {
            $result[] = 'show';
            $count($out['converted'], $t);
            continue;
        }
        if (isset($icons[$t])) {
            $result[] = 'bi';
            $result[] = 'bi-' . $icons[$t];
            $icon_twin = true;
            $count($out['converted'], $t);
            if (isset($beside[$t])) $result[] = $t;
            continue;
        }
        if (isset($map[$t])) {
            $to = preg_split('/\s+/', $map[$t], -1, PREG_SPLIT_NO_EMPTY);
            // What the element already says in its own name is not written
            // over it: 5.3.8 would let btn-light beat the btn-primary beside
            // btn-default, and mb-3 the margin named beside form-group.
            if (($t === 'btn-default' && preg_grep('/^btn-(?:primary|secondary|success|info|warning|danger|light|dark|link|outline-[a-z]+)$/', $tokens))
                || ($t === 'form-group' && preg_grep('/^(?:m|mb|my)-?(?:\d+|auto)$/', $tokens))) {
                $to = array();
            }
            // A name kept beside its 5.3.8 names is not converted twice.
            if (!isset($beside[$t]) || array_diff($to, $tokens)) {
                foreach ($to as $r) $result[] = $r;
                $count($out['converted'], $t);
            }
            if (isset($beside[$t])) $result[] = $t;
            continue;
        }
        if (preg_match('/^col-(xs|sm|md|lg)-(push|pull)-\d+$/', $t) || isset($by_hand[$t])) {
            $count($out['by_hand'], $t);
        } elseif (preg_match('/^glyphicon-/', $t)) {
            $count($out['kept'], $t);
        }
        $result[] = $t;
    }

    // The base class of an icon set goes with its last twin; it stays when
    // an icon of the element has none.
    if ($icon_twin) {
        $twinless = false;
        foreach ($tokens as $t) {
            if ((strpos($t, 'glyphicon-') === 0 || strpos($t, 'ti-') === 0) && !isset($icons[$t])) $twinless = true;
        }
        if (!$twinless && isset($have['glyphicon']) && !isset($keep['glyphicon']) && !isset($beside['glyphicon'])) {
            $result = array_values(array_diff($result, array('glyphicon')));
            $count($out['converted'], 'glyphicon');
        }
    }

    $out['class'] = implode(' ', array_values(array_unique($result)));
    return $out;
}

// A Bootstrap 3 visibility class: hidden-*, visible-*, hidden, hide.
function pg_lbs_is_visibility($t)
{
    return $t === 'hidden' || $t === 'hide'
        || (bool)preg_match('/^(hidden-(xs|sm|md|lg|print)|visible-(xs|sm|md|lg|print)(-block|-inline|-inline-block)?)$/', $t);
}

/**
 * One element's Bootstrap 3 visibility classes as one 5.3.8 display set.
 * visible-* shows the element only at its breakpoints, hidden-* hides it at
 * its own; a breakpoint no class names keeps the element's own display,
 * written d-*-<display> where a hidden one comes before it.
 *
 * @param array  $classes
 * @param string $display  the element's own display, where the caller knows
 *                         its tag: a table cell shown again as a block
 *                         leaves its row. A bare visible-* is a block but on
 *                         a table, a row or a cell, as in Bootstrap 3.
 * @return array  5.3.8 classes in breakpoint order
 */
function pg_lbs_visibility($classes, $display = 'block')
{
    $bps = array('xs', 'sm', 'md', 'lg');
    $state = array_fill_keys($bps, null);
    $print = null;
    $visible = false;
    $shown = (strpos($display, 'table') === 0) ? $display : 'block';
    foreach ($classes as $c) {
        if (preg_match('/^visible-(xs|sm|md|lg)(?:-(block|inline|inline-block))?$/', $c)) $visible = true;
    }
    if ($visible) $state = array_fill_keys($bps, 'none');
    foreach ($classes as $c) {
        if (preg_match('/^visible-(xs|sm|md|lg)(?:-(block|inline|inline-block))?$/', $c, $m)) {
            $state[$m[1]] = isset($m[2]) ? $m[2] : $shown;
        } elseif (preg_match('/^visible-print(?:-(block|inline|inline-block))?$/', $c, $m)) {
            $print = isset($m[1]) ? $m[1] : $shown;
            if (!$visible) $state = array_fill_keys($bps, 'none');
        }
    }
    foreach ($classes as $c) {
        if (preg_match('/^hidden-(xs|sm|md|lg)$/', $c, $m)) $state[$m[1]] = 'none';
        if ($c === 'hidden' || $c === 'hide') $state = array_fill_keys($bps, 'none');
        if ($c === 'hidden-print') $print = 'none';
    }

    $out = array();
    $prev = null;
    foreach ($bps as $bp) {
        $now = $state[$bp];
        if ($now !== $prev) {
            $infix = ($bp === 'xs') ? '' : $bp . '-';
            if ($now !== null) {
                $out[] = 'd-' . $infix . $now;
            } elseif ($bp !== 'xs') {
                $out[] = 'd-' . $infix . $display;
            }
        }
        $prev = $now;
    }
    if ($print !== null) $out[] = 'd-print-' . $print;
    return $out;
}

/**
 * The data attributes Bootstrap's scripts read (without data-): the ones
 * that start a plugin first, then the options beside them.
 */
function pg_lbs_data_names()
{
    return array('toggle', 'dismiss', 'ride', 'slide', 'slide-to', 'spy', 'target', 'parent', 'offset', 'placement',
                 'container', 'trigger', 'backdrop', 'keyboard', 'interval', 'pause', 'wrap');
}

/**
 * The data attributes Bootstrap's scripts read, 3 to 5.3.8.
 */
function pg_lbs_data_attribute($name)
{
    $name = (string)$name;
    if (strncasecmp($name, 'data-', 5) === 0 && in_array(strtolower(substr($name, 5)), pg_lbs_data_names(), true)) {
        return 'data-bs-' . strtolower(substr($name, 5));
    }
    return $name;
}

/**
 * The class and data attributes inside a piece of HTML (a custom HTML
 * node, a heading's text), the rest byte for byte.
 *
 * @return array  'html', 'converted', 'kept', 'by_hand'
 */
function pg_lbs_html($html)
{
    $acc = array('converted' => array(), 'kept' => array(), 'by_hand' => array());
    $out = preg_replace_callback('/<[a-zA-Z][^<>]*>/', function ($tag) use (&$acc) {
        $t = preg_replace_callback('/(\sclass\s*=\s*)(["\'])(.*?)\2/s', function ($m) use (&$acc) {
            $c = pg_lbs_classes($m[3]);
            pg_lbs_add_counts($acc, $c);
            return $m[1] . $m[2] . $c['class'] . $m[2];
        }, $tag[0]);
        return preg_replace_callback('/(\s)(data-[a-zA-Z-]+)(?=\s*=)/', function ($m) use (&$acc) {
            $new = pg_lbs_data_attribute($m[2]);
            if ($new !== $m[2]) $acc['converted'][$m[2]] = (isset($acc['converted'][$m[2]]) ? $acc['converted'][$m[2]] : 0) + 1;
            return $m[1] . $new;
        }, $t);
    }, (string)$html);
    $acc['html'] = ($out === null) ? (string)$html : $out;
    return $acc;
}

// Adds one result's counts to an accumulator of the same keys.
function pg_lbs_add_counts(&$acc, $r)
{
    foreach (array('converted', 'kept', 'by_hand') as $k) {
        foreach ((array)($r[$k] ?? array()) as $name => $n) {
            $acc[$k][$name] = (isset($acc[$k][$name]) ? $acc[$k][$name] : 0) + $n;
        }
    }
}

/**
 * A page tree (or a shared part's tree) converted: each node's class, the
 * class and data attributes in its HTML text and its _attrs; the li items of
 * a list-inline get list-inline-item. A placed part (shared_ref) is left as
 * it is: its own tree is converted on its own.
 *
 * @param array $tree
 * @return array  'tree', 'converted', 'kept', 'by_hand'
 */
function pg_lbs_tree($tree)
{
    $acc = array('converted' => array(), 'kept' => array(), 'by_hand' => array());
    $walk = function ($node) use (&$walk, &$acc) {
        if (!is_array($node) || ($node['type'] ?? '') === 'shared_ref') return $node;
        if (isset($node['props']) && is_array($node['props'])) {
            foreach ($node['props'] as $k => $v) {
                if ($k === 'cssClass' && is_string($v)) {
                    $c = pg_lbs_classes($v);
                    pg_lbs_add_counts($acc, $c);
                    $node['props'][$k] = $c['class'];
                } elseif ($k === '_attrs' && is_array($v)) {
                    foreach ($v as $i => $a) {
                        if (!is_array($a) || !isset($a['name'])) continue;
                        if ($a['name'] === 'class' && is_string($a['value'] ?? null)) {
                            $c = pg_lbs_classes($a['value']);
                            pg_lbs_add_counts($acc, $c);
                            $node['props'][$k][$i]['value'] = $c['class'];
                            continue;
                        }
                        $new = pg_lbs_data_attribute($a['name']);
                        if ($new !== $a['name']) {
                            $acc['converted'][$a['name']] = (isset($acc['converted'][$a['name']]) ? $acc['converted'][$a['name']] : 0) + 1;
                            $node['props'][$k][$i]['name'] = $new;
                        }
                    }
                } elseif (is_string($v) && strpos($v, '<') !== false) {
                    $h = pg_lbs_html($v);
                    pg_lbs_add_counts($acc, $h);
                    $node['props'][$k] = $h['html'];
                }
            }
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            $inline = preg_match('/(^|\s)list-inline(\s|$)/', (string)($node['props']['cssClass'] ?? '')) === 1;
            foreach ($node['children'] as $i => $child) {
                $child = $walk($child);
                if ($inline && is_array($child) && ($child['type'] ?? '') === 'semantic' && strtolower((string)($child['props']['tag'] ?? '')) === 'li') {
                    $cls = trim((string)($child['props']['cssClass'] ?? ''));
                    if (!preg_match('/(^|\s)list-inline-item(\s|$)/', $cls)) {
                        $child['props']['cssClass'] = trim('list-inline-item ' . $cls);
                        $acc['converted']['list-inline'] = (isset($acc['converted']['list-inline']) ? $acc['converted']['list-inline'] : 0) + 1;
                    }
                }
                $node['children'][$i] = $child;
            }
        }
        return $node;
    };
    $acc['tree'] = $walk($tree);
    return $acc;
}

/**
 * A stylesheet's selectors renamed by the map, the rest byte for byte:
 * declarations, comments, strings, @font-face and @keyframes are not read.
 * A class the map drops, and the visibility, push/pull and icon classes, are
 * left in the selector and counted as kept.
 *
 * @param string $css
 * @return array  'css', 'converted', 'kept'
 */
function pg_lbs_css($css)
{
    $css = (string)$css;
    $map = pg_lbs_class_map();
    $acc = array('converted' => array(), 'kept' => array());

    $rename = function ($prelude) use ($map, &$acc) {
        // Comments and the quoted strings of attribute selectors are left as
        // they are.
        $parts = preg_split('/(\/\*.*?\*\/|"(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')/s', $prelude, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($parts as $i => $p) {
            if ($i % 2 === 1) continue;
            $parts[$i] = preg_replace_callback('/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/', function ($m) use ($map, &$acc) {
                $name = $m[1];
                if (isset($map[$name]) && $map[$name] !== '') {
                    $acc['converted'][$name] = (isset($acc['converted'][$name]) ? $acc['converted'][$name] : 0) + 1;
                    return '.' . implode('.', preg_split('/\s+/', $map[$name], -1, PREG_SPLIT_NO_EMPTY));
                }
                if (isset($map[$name]) || pg_lbs_is_visibility($name) || preg_match('/^col-(xs|sm|md|lg)-(push|pull)-\d+$/', $name)) {
                    $acc['kept'][$name] = (isset($acc['kept'][$name]) ? $acc['kept'][$name] : 0) + 1;
                }
                return $m[0];
            }, $p);
        }
        return implode('', $parts);
    };

    $out = '';
    $n = strlen($css);
    $i = 0;
    $start = 0;          // where the current prelude starts
    $stack = array();    // 'rules' (top level, @media...) or 'skip'
    $context = function () use (&$stack) {
        return $stack ? end($stack) : 'rules';
    };
    while ($i < $n) {
        $c = $css[$i];
        if ($c === '/' && $i + 1 < $n && $css[$i + 1] === '*') {
            $end = strpos($css, '*/', $i + 2);
            $i = ($end === false) ? $n : $end + 2;
            continue;
        }
        if ($c === '"' || $c === "'") {
            for ($i++; $i < $n && $css[$i] !== $c; $i++) {
                if ($css[$i] === '\\') $i++;
            }
            $i++;
            continue;
        }
        if ($c === '{') {
            if ($context() === 'rules') {
                $prelude = substr($css, $start, $i - $start);
                $trim = ltrim(preg_replace('#/\*.*?\*/#s', '', $prelude));
                if ($trim !== '' && $trim[0] === '@') {
                    $stack[] = preg_match('/^@(media|supports|layer|container|document|-moz-document)\b/i', $trim) ? 'rules' : 'skip';
                    $out .= $prelude;
                } else {
                    $stack[] = 'skip';
                    $out .= $rename($prelude);
                }
            } else {
                $stack[] = 'skip';
                $out .= substr($css, $start, $i - $start);
            }
            $out .= '{';
            $i++;
            $start = $i;
            continue;
        }
        if ($c === '}') {
            $out .= substr($css, $start, $i + 1 - $start);
            array_pop($stack);
            $i++;
            $start = $i;
            continue;
        }
        if ($c === ';' && $context() === 'rules') {
            $out .= substr($css, $start, $i + 1 - $start);
            $i++;
            $start = $i;
            continue;
        }
        $i++;
    }
    $out .= substr($css, $start);
    $acc['css'] = $out;
    return $acc;
}

/**
 * The counts of several conversions (trees, sheets) as one report.
 *
 * @param array $results  each with 'converted', 'kept', 'by_hand'
 * @return array  'converted', 'kept', 'by_hand' (name => count, most first)
 *                and 'totals' (the three sums)
 */
function pg_lbs_report($results)
{
    $acc = array('converted' => array(), 'kept' => array(), 'by_hand' => array());
    foreach ((array)$results as $r) pg_lbs_add_counts($acc, (array)$r);
    $totals = array();
    foreach (array('converted', 'kept', 'by_hand') as $k) {
        arsort($acc[$k]);
        $totals[$k] = array_sum($acc[$k]);
    }
    $acc['totals'] = $totals;
    return $acc;
}

/**
 * Bootstrap 3 names a site also gives its own meaning. In a page type's
 * markup they are converted only where Bootstrap 3's shape shows
 * (pg_lbs_layout_classes()); a bare one stays and is counted as kept.
 */
function pg_lbs_layout_ambiguous_names()
{
    return array('label', 'checkbox', 'radio', 'panel', 'well', 'media', 'thumbnail', 'caret');
}

/**
 * The values with which a data attribute starts one of Bootstrap 3's
 * plugins that 5.3.8 still has; an empty list takes any value. Other
 * libraries use the same attribute names (data-toggle="lightbox").
 */
function pg_lbs_layout_triggers()
{
    return array(
        'data-toggle' => array('modal', 'dropdown', 'tab', 'pill', 'collapse', 'tooltip', 'popover', 'button', 'buttons'),
        'data-dismiss' => array('modal', 'alert'),
        'data-ride' => array('carousel'),
        'data-slide' => array('prev', 'next'),
        'data-slide-to' => array(),
        'data-spy' => array('scroll'),
    );
}

/**
 * One tag's class list by the rules of a page type's markup: the map
 * without the theme names, an ambiguous name only in Bootstrap 3's shape,
 * and no name taken from a script that uses it.
 *
 * @param string $class
 * @param bool   $block     the element is Bootstrap 3's checkbox or radio
 *                          block (pg_lbs_layout_choice_block())
 * @param array  $scripted  class names the markup's own scripts select or
 *                          change (name => true)
 * @param string $display   the element's own display (pg_lbs_layout_display())
 * @return array  as pg_lbs_classes()
 */
function pg_lbs_layout_classes($class, $block = false, $scripted = array(), $display = 'block')
{
    $tokens = preg_split('/\s+/', trim((string)$class), -1, PREG_SPLIT_NO_EMPTY);
    $map = pg_lbs_class_map();
    $ambiguous = array_flip(pg_lbs_layout_ambiguous_names());
    $keep = array();
    $beside = array();
    foreach ($tokens as $t) {
        if (isset($ambiguous[$t]) && !($block && ($t === 'checkbox' || $t === 'radio'))) {
            // Bootstrap 3's shape on the element itself: a partner name of
            // the map (label label-success, panel panel-default, well well-sm).
            $partner = false;
            foreach ($tokens as $p) {
                if (isset($map[$p]) && strpos($p, $t . '-') === 0) $partner = true;
            }
            if (!$partner) {
                $keep[] = $t;
                continue;
            }
        }
        if (isset($scripted[$t])) {
            // A script that takes "hidden" or "in" off would leave the 5.3.8
            // name behind, so a state name stays alone; any other name is
            // found beside its 5.3.8 names.
            if ($t === 'in' || pg_lbs_is_visibility($t)) $keep[] = $t; else $beside[] = $t;
        }
    }
    return pg_lbs_classes($class, array('theme_names' => false, 'keep' => $keep, 'beside' => $beside, 'display' => $display));
}

/**
 * The display a tag has by itself, as far as 5.3.8 has a d-* name for it:
 * what an element hidden at one width is shown as at the next.
 */
function pg_lbs_layout_display($tag)
{
    static $displays = null;
    if ($displays === null) {
        $displays = array('table' => 'table', 'tr' => 'table-row', 'td' => 'table-cell', 'th' => 'table-cell')
            + array_fill_keys(array('img', 'button', 'input', 'select', 'textarea'), 'inline-block')
            + array_fill_keys(array('a', 'abbr', 'b', 'cite', 'code', 'em', 'i', 'label', 'mark', 'q', 's', 'small', 'span',
                                    'strong', 'sub', 'sup', 'time', 'u'), 'inline');
    }
    return isset($displays[$tag]) ? $displays[$tag] : 'block';
}

/**
 * The names the inline scripts of a piece of markup use: the classes they
 * select or change and the Bootstrap data attributes they read. The pass
 * leaves each findable.
 *
 * @return array|null  'classes' and 'attributes' (name => true each); null
 *                     when the scripts cannot be read
 */
function pg_lbs_layout_script_names($html)
{
    $names = array('classes' => array(), 'attributes' => array());
    if (stripos($html, '<script') === false) return $names;
    if (preg_match_all('~<script\b[^>]*+>(.*?)</script\b~is', $html, $scripts) === false) return null;
    $literal = '~"(?:[^"\\\\\n]|\\\\.)*+"|\'(?:[^\'\\\\\n]|\\\\.)*+\'~';
    $family = implode('|', array_map('preg_quote', pg_lbs_data_names()));
    foreach ($scripts[1] as $js) {
        if (trim($js) === '') continue;
        // Class selectors and data attributes inside the string literals.
        if (preg_match_all($literal, $js, $literals) === false) return null;
        foreach ($literals[0] as $text) {
            if (preg_match_all('~\.(-?[a-zA-Z_][\w-]*)~', $text, $m)) $names['classes'] += array_fill_keys($m[1], true);
            if (preg_match_all('~data-(?:' . $family . ')(?![\w-])~i', $text, $m)) $names['attributes'] += array_fill_keys(array_map('strtolower', $m[0]), true);
        }
        // The names a class method is given.
        if (preg_match_all('~(?:addClass|removeClass|toggleClass|hasClass|classList\s*\.\s*(?:add|remove|toggle|contains|replace))\s*\(([^()]*)~', $js, $calls) === false) return null;
        foreach ($calls[1] as $arguments) {
            if (!preg_match_all($literal, $arguments, $m)) continue;
            foreach ($m[0] as $text) {
                $names['classes'] += array_fill_keys(preg_split('/\s+/', substr($text, 1, -1), -1, PREG_SPLIT_NO_EMPTY), true);
            }
        }
        // jQuery's .data('target') reads data-target.
        if (preg_match_all('~\.data\(\s*["\'](' . $family . '|slideTo)["\']~', $js, $m)) {
            foreach ($m[1] as $name) $names['attributes']['data-' . ($name === 'slideTo' ? 'slide-to' : $name)] = true;
        }
    }
    return $names;
}

/**
 * The patterns of the layout pass: a start tag and one attribute, read as a
 * browser reads them. A value may hold ">", and a class= inside another
 * attribute's value is no attribute. An attribute whose quote never closes
 * matches nothing, so its tag fails and the pass with it.
 */
function pg_lbs_layout_patterns()
{
    static $patterns = null;
    if ($patterns === null) {
        $s = ' \t\n\f\r';
        $value = '"[^"]*+"|\'[^\']*+\'|[^' . $s . '>"\'][^' . $s . '>]*+';
        $name = '[^' . $s . '/>][^' . $s . '/>=]*+';
        $patterns = array(
            'tag' => '~\G<([a-zA-Z][^' . $s . '/>]*+)((?:[' . $s . '/]++|' . $name
                . '(?:[' . $s . ']*+=[' . $s . ']*+(?:' . $value . '|(?=>))|(?![' . $s . ']*+=)))*+)>~',
            'attribute' => '~(' . $name . ')(?:([' . $s . ']*+=[' . $s . ']*+)(' . $value . ')?+)?+~',
        );
    }
    return $patterns;
}

/**
 * Whether the element opened right before $after is one of Bootstrap 3's
 * checkbox or radio blocks: its first tag is an input of the type, or a
 * label that opens with one. A site's own .radio around other markup is not.
 * In a layout file's text the marks of PHP blocks count as white space.
 */
function pg_lbs_layout_choice_block($html, $after, $type)
{
    $gap = '(?:[ \t\n\f\r]|\x01\d+\x01)*+';
    return preg_match('~\G' . $gap . '(?:<label\b[^>]*+>' . $gap . ')?+<input\b[^>]*\stype\s*=\s*["\']?' . $type . '(?![\w-])~i', $html, $m, 0, $after) === 1;
}

/**
 * One start tag's attributes by the layout rules: its class attribute and,
 * where Bootstrap 3's scripts read the tag, the data-toggle family. Every
 * other attribute is returned byte for byte.
 *
 * @param string $tag       the tag's name, lower case
 * @param string $attrs     the text between the name and ">"
 * @param string $html      the markup, for a look past the tag
 * @param int    $after     where the tag ends in it
 * @param array  $scripted  pg_lbs_layout_script_names()
 * @param array  $acc       counts, added to
 * @param array  $memo      class lists converted so far
 * @return string|null  the attribute text; null when it cannot be read
 */
function pg_lbs_layout_attributes($tag, $attrs, $html, $after, $scripted, &$acc, &$memo)
{
    $patterns = pg_lbs_layout_patterns();
    if (preg_match_all($patterns['attribute'], $attrs, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) return null;

    // A browser reads the first of a repeated attribute.
    $values = array();
    $class = null;
    $family = array();
    foreach ($found as $k => $a) {
        $name = strtolower($a[1][0]);
        if (isset($values[$name])) continue;
        $value = isset($a[3]) ? $a[3][0] : '';
        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) $value = substr($value, 1, -1);
        $values[$name] = $value;
        if ($name === 'class') {
            if (isset($a[3])) $class = $k;
        } elseif (strncmp($name, 'data-', 5) === 0 && pg_lbs_data_attribute($name) !== $name) {
            $family[$k] = pg_lbs_data_attribute($name);
        }
    }

    $edits = array();   // offset => array(length, text)
    if ($class !== null) {
        $value = $values['class'];
        // A class list PHP writes in part, in a layout file's text.
        if (isset($acc['php']) && strpos($value, "\x01") !== false) $acc['php']++;
        $block = $tag !== 'input' && preg_match('/(?:^|\s)(checkbox|radio)(?:\s|$)/', $value, $m)
            && pg_lbs_layout_choice_block($html, $after, $m[1]);
        $display = pg_lbs_layout_display($tag);
        $key = ($block ? '1' : '0') . $display . ' ' . $value;
        if (!isset($memo[$key])) $memo[$key] = pg_lbs_layout_classes($value, $block, $scripted['classes'], $display);
        pg_lbs_add_counts($acc, $memo[$key]);
        // A list with nothing to convert keeps its bytes, white space too.
        if ($memo[$key]['converted']) {
            $written = $found[$class][3][0];
            $new = $memo[$key]['class'];
            $quote = ($written[0] === '"' || $written[0] === "'") ? $written[0] : (($new === '' || strpos($new, ' ') !== false) ? '"' : '');
            $edits[$found[$class][3][1]] = array(strlen($written), $quote . $new . $quote);
        }
    }

    if ($family) {
        // Bootstrap's scripts read the tag: it starts a plugin, or it is the
        // modal or the carousel that carries its own options.
        $reads = (bool)array_intersect(array('modal', 'carousel'), preg_split('/\s+/', isset($values['class']) ? $values['class'] : '', -1, PREG_SPLIT_NO_EMPTY));
        foreach (pg_lbs_layout_triggers() as $trigger => $starts) {
            if (isset($values[$trigger]) && (!$starts || in_array($values[$trigger], $starts, true))) $reads = true;
        }
        foreach ($reads ? $family : array() as $k => $new) {
            $old = strtolower($found[$k][1][0]);
            // Already written under its 5.3.8 name too.
            if (isset($values[$new])) continue;
            $acc['converted'][$old] = (isset($acc['converted'][$old]) ? $acc['converted'][$old] : 0) + 1;
            if (isset($scripted['attributes'][$old])) {
                // A script reads the old name: the attribute is written twice.
                $edits[$found[$k][0][1] + strlen($found[$k][0][0])] = array(0, ' ' . $new . substr($found[$k][0][0], strlen($found[$k][1][0])));
            } else {
                $edits[$found[$k][1][1]] = array(strlen($found[$k][1][0]), $new);
            }
        }
    }

    krsort($edits);
    foreach ($edits as $at => $edit) {
        $attrs = substr_replace($attrs, $edit[1], $at, $edit[0]);
    }
    return $attrs;
}

/**
 * The layout pass over a piece of markup: every start tag's attributes by
 * pg_lbs_layout_attributes(), the rest byte for byte.
 *
 * @param string $html
 * @param array  $acc   counts, added to
 * @return string|null  null when the pass cannot finish
 */
function pg_lbs_layout_pass($html, &$acc)
{
    static $raw = array('script' => 1, 'style' => 1, 'textarea' => 1, 'pre' => 1, 'title' => 1, 'xmp' => 1,
                        'iframe' => 1, 'noembed' => 1, 'noframes' => 1);
    $scripted = pg_lbs_layout_script_names($html);
    if ($scripted === null) return null;
    $patterns = pg_lbs_layout_patterns();
    $memo = array();
    $n = strlen($html);
    $out = '';
    $from = 0;   // the start of what is not yet in $out
    $i = 0;
    while ($i < $n && ($lt = strpos($html, '<', $i)) !== false) {
        $c = ($lt + 1 < $n) ? $html[$lt + 1] : '';
        if ($c === '!' || $c === '?' || $c === '/') {
            // A comment, a declaration or an end tag: nothing to convert. A
            // comment that never closes runs to the end, as a browser reads
            // it; an end tag that never closes is a broken tag.
            $comment = substr_compare($html, '<!--', $lt, 4) === 0;
            $end = strpos($html, $comment ? '-->' : '>', $lt + 2);
            if ($end === false) {
                if ($c === '/' && $lt + 2 < $n && ctype_alpha($html[$lt + 2])) return null;
                break;
            }
            $i = $end + ($comment ? 3 : 1);
            continue;
        }
        if (!ctype_alpha($c)) {
            $i = $lt + 1;
            continue;
        }
        if (preg_match($patterns['tag'], $html, $m, 0, $lt) !== 1) return null;
        $i = $lt + strlen($m[0]);
        $tag = strtolower($m[1]);
        if (stripos($m[2], 'class') !== false || stripos($m[2], 'data-') !== false) {
            $attrs = pg_lbs_layout_attributes($tag, $m[2], $html, $i, $scripted, $acc, $memo);
            if ($attrs === null) return null;
            if ($attrs !== $m[2]) {
                $out .= substr($html, $from, $lt - $from) . '<' . $m[1] . $attrs . '>';
                $from = $i;
            }
        }
        if (isset($raw[$tag])) {
            // What such an element holds is not markup: on to its end tag.
            $ends = preg_match('~</' . $tag . '(?=[ \t\n\f\r/>])~i', $html, $e, PREG_OFFSET_CAPTURE, $i);
            if ($ends === false) return null;
            if ($ends === 0) break;
            $i = $e[0][1];
        }
    }
    return $out . substr($html, $from);
}

/**
 * A page type's rendered markup with its Bootstrap 3 names printed as their
 * 5.3.8 names: the class attributes and the data-toggle family of its tags,
 * by the one map without the theme names. Text between tags, comments, the
 * inside of script, style, textarea and pre and every other attribute stay
 * byte for byte, so a visitor's escaped text never changes; 5.3.8 markup
 * passes untouched and a second pass changes nothing.
 *
 * When the pass cannot finish (a tag that never ends, a failed match) the
 * markup comes back as it was given and 'done' is false.
 *
 * @param string $html
 * @return array  'html', 'converted', 'kept', 'by_hand' (name => count
 *                each), 'done'
 */
function pg_lbs_layout_html($html)
{
    $html = (string)$html;
    $acc = array('converted' => array(), 'kept' => array(), 'by_hand' => array());
    $out = pg_lbs_layout_pass($html, $acc);
    if ($out === null) {
        return array('html' => $html, 'converted' => array(), 'kept' => array(), 'by_hand' => array(), 'done' => false);
    }
    return array('html' => $out) + $acc + array('done' => true);
}

/**
 * The same pass over the text of a layout file or a template, for a count
 * before any page is drawn: PHP blocks stay byte for byte and a class list
 * PHP writes in part is counted in 'php', since only the output shows its
 * names.
 *
 * @param string $code
 * @return array  'code', 'converted', 'kept', 'by_hand', 'php', 'done'
 */
function pg_lbs_layout_source($code)
{
    $code = (string)$code;
    $failed = array('code' => $code, 'converted' => array(), 'kept' => array(), 'by_hand' => array(), 'php' => 0, 'done' => false);
    // Each PHP block becomes a mark no rule of the pass reads: not white
    // space, not a quote, and nothing a class name or a tag is made of.
    if (strpos($code, "\x01") !== false) return $failed;
    $blocks = array();
    $marked = preg_replace_callback('/<\?.*?(?:\?>|\z)/s', function ($m) use (&$blocks) {
        $blocks[] = $m[0];
        return "\x01" . (count($blocks) - 1) . "\x01";
    }, $code);
    if ($marked === null) return $failed;
    $acc = array('converted' => array(), 'kept' => array(), 'by_hand' => array(), 'php' => 0);
    $out = pg_lbs_layout_pass($marked, $acc);
    if ($out !== null) {
        $out = preg_replace_callback('/\x01(\d+)\x01/', function ($m) use ($blocks) {
            return $blocks[(int)$m[1]];
        }, $out);
    }
    if ($out === null) return $failed;
    return array('code' => $out) + $acc + array('done' => true);
}
