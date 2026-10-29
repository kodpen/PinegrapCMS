<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The one date-and-time control on site pages, loaded on demand: a page
 * whose HTML holds a date hook (data-pg-date, data-pg-datetime,
 * data-pg-time, data-pg-date-selects) gets vanilla-calendar-pro's own
 * stylesheet, the site rules of the control, the picker options when the
 * page has none, and the tags of assets/js/pg_datetime.js and
 * assets/js/pg_fields.js, which draw the hooked fields as in the panel.
 * Sites keep Bootstrap 5.3.8 and jQuery UI; a page without a hook gets
 * nothing. Asked once every shared component and system widget is drawn
 * in (get_page_content.php), like pg_fx_inject().
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
 * A date hook on an element, or a bind marker (pg_site_datetime_bind()):
 * the attribute name whole, after a space, so data-pg-datetime-x or text
 * that only mentions the name does not count.
 *
 * @return string
 */
function pg_site_datetime_pattern()
{
    return '/\sdata-pg-(?:date|datetime|time|date-selects|date-bind)(?=[\s=>\/])/i';
}

/**
 * A marker that hooks fields by their id: for a screen whose inputs come
 * from a layout kept in the site's database (a custom form's custom
 * layout), where the markup cannot carry the hook. pg_fields.js gives each
 * input with one of the ids the hook of its kind and draws it. Empty when
 * no id is given.
 *
 * @param array $ids kind ('date', 'datetime', 'time') => list of ids
 * @return string
 */
function pg_site_datetime_bind($ids)
{
    $bind = array();

    foreach (array('date', 'datetime', 'time') as $kind) {
        if (!empty($ids[$kind])) {
            $bind[$kind] = array_values(array_map('strval', (array) $ids[$kind]));
        }
    }

    return $bind ? '<span hidden data-pg-date-bind="' . h(json_encode($bind)) . '"></span>' : '';
}

/**
 * What the control needs from the site on a site page: the clock, words
 * and screen-reader names of pg_calendar_settings(); `site`, which tells
 * pg_datetime.js and pg_fields.js the page has no Bootstrap 6; `button`,
 * the Bootstrap 5.3.8 classes of the Done button.
 *
 * @return array
 */
function pg_site_datetime_settings()
{
    $settings = pg_calendar_settings();
    $settings['site'] = true;
    $settings['button'] = 'btn btn-sm btn-primary';

    return $settings;
}

/**
 * The head part: the library's stylesheet and the control's site rules.
 *
 * @return string
 */
function pg_site_datetime_head()
{
    $path = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;

    return '<link rel="stylesheet" id="pg-datetime-site-css" href="' . $path . '/assets/lib/vanilla-calendar-pro/index.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/lib/vanilla-calendar-pro/index.css') . '">' . "\n"
        . '<link rel="stylesheet" href="' . $path . '/assets/css/pg_datetime_site.css?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/css/pg_datetime_site.css') . '">' . "\n";
}

/**
 * The body part: the picker options when the page printed none (a page
 * with a jQuery UI picker already has them, and its own carry the
 * timepicker's words, so they are not written over), then the control and
 * the field hooks, in that order. The library itself is loaded once by
 * pg_datetime.js from its data-pg-calendar-src.
 *
 * @param bool $options print get_date_picker_format()
 * @return string
 */
function pg_site_datetime_scripts($options = true)
{
    $path = OUTPUT_PATH . OUTPUT_SOFTWARE_DIRECTORY;

    return ($options ? get_date_picker_format() . "\n" : '')
        . '<script src="' . $path . '/assets/js/pg_datetime.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_datetime.js') . '" data-pg-calendar-src="' . $path . '/assets/lib/vanilla-calendar-pro/index.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/lib/vanilla-calendar-pro/index.js') . '" data-pg-calendar="' . h(json_encode(pg_site_datetime_settings())) . '"></script>' . "\n"
        . '<script src="' . $path . '/assets/js/pg_fields.js?v=' . @filemtime(PG_FUNCTIONS_DIR . '/assets/js/pg_fields.js') . '" data-pg-locale-label="' . h(lang(array('info' => ''))) . '"></script>' . "\n";
}

/**
 * The control's stylesheets before </head> (at the start of a fragment) and
 * its scripts before the last </body>, for a page whose HTML holds a date
 * hook (pg_site_datetime_pattern()). Once a page: a second call finds the
 * stylesheet's id and changes nothing.
 *
 * @param string $content page HTML
 * @return string
 */
function pg_site_datetime_inject($content)
{
    if (stripos($content, 'data-pg-') === false || !preg_match(pg_site_datetime_pattern(), $content)) return $content;
    // Once a page; a page that printed the panel's tags (pg_fields_script())
    // draws its fields with those.
    if (strpos($content, 'id="pg-datetime-site-css"') !== false || strpos($content, '/assets/js/pg_datetime.js') !== false) return $content;

    $scripts = pg_site_datetime_scripts(strpos($content, 'datetimepicker_options') === false);

    $head = stripos($content, '</head>');
    $content = ($head !== false) ? substr_replace($content, pg_site_datetime_head(), $head, 0) : pg_site_datetime_head() . $content;

    // The last </body>: an earlier one can belong to markup the page shows
    // as text or carries in an attribute.
    $body = strripos($content, '</body>');
    return ($body !== false) ? substr_replace($content, $scripts, $body, 0) : $content . $scripts;
}
