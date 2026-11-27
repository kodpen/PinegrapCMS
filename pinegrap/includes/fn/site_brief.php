<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * The brief of the "Describe it" start of the New design window: the
 * business's name, what it does, its kind (the set-up card's purposes),
 * its customers, the tone, the pages wanted and an optional brand colour.
 * The pages are written in the site's language. The brief lives in the
 * browser; nothing here is stored on the server.
 *
 * The start needs Pinegrap AI and counts against the daily drafts of
 * "Write with AI": without either it is listed disabled with the reason.
 *
 * The checks are pure and have a twin in assets/js/designer_describe.js
 * (sdStartParts.describe.check); tests/site_brief_test.php runs both on
 * the same briefs.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

require_once(PG_FUNCTIONS_DIR . '/includes/fn/launch_journey.php');

/**
 * The lengths a brief's fields take. "What do you do?" takes what
 * "Describe the page" takes. Pure.
 *
 * @return array name, what, what_min, customers, page, pages
 */
function pg_site_brief_limits()
{
    return array(
        'name'      => 80,
        'what'      => 2000,
        'what_min'  => 20,
        'customers' => 300,
        'page'      => 40,
        'pages'     => 12,
    );
}

/**
 * The tones a brief offers, in order; the first is the default. Pure.
 *
 * @return string[]
 */
function pg_site_brief_tones()
{
    return array('friendly', 'formal', 'plain');
}

/**
 * The pages the chips offer, in order, by their keys. Pure.
 *
 * @return string[]
 */
function pg_site_brief_page_keys()
{
    return array('home', 'about', 'services', 'products', 'portfolio', 'team', 'courses', 'events', 'booking', 'pricing', 'blog', 'faq', 'donate', 'contact');
}

/**
 * The pages a kind of site starts with, in menu order. An unknown kind is
 * 'other'. Pure.
 *
 * @param mixed $purpose a key of pg_launch_journey_purposes()
 * @return string[] keys of pg_site_brief_page_keys()
 */
function pg_site_brief_pages_for($purpose)
{
    $pages = array(
        'shop'        => array('home', 'products', 'about', 'faq', 'contact'),
        'company'     => array('home', 'about', 'services', 'team', 'contact'),
        'association' => array('home', 'about', 'events', 'donate', 'contact'),
        'school'      => array('home', 'about', 'courses', 'team', 'contact'),
        'clinic'      => array('home', 'services', 'team', 'booking', 'contact'),
        'agency'      => array('home', 'services', 'portfolio', 'about', 'contact'),
        'blog'        => array('home', 'blog', 'about', 'contact'),
        'other'       => array('home', 'about', 'services', 'contact'),
    );

    return $pages[pg_launch_journey_purpose($purpose)];
}

/**
 * A brand colour as #rrggbb in lower case, '' for none or for anything
 * that is not six hex digits (the # may be left out). Pure.
 *
 * @param mixed $value
 * @return string
 */
function pg_site_brief_colour($value)
{
    $value = is_string($value) ? trim($value) : '';

    if ($value !== '' && $value[0] !== '#') {
        $value = '#' . $value;
    }

    return preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : '';
}

/**
 * Text of a field, its spaces joined and trimmed, '' for anything but text.
 * Line breaks stay in a long text. Pure.
 *
 * @param mixed $value
 * @param bool  $lines whether line breaks are kept
 * @return string
 */
function pg_site_brief_text($value, $lines = false)
{
    if (!is_string($value)) {
        return '';
    }

    // The same classes as the browser's twin: no Unicode classes, so both
    // sides read a text alike.
    $value = str_replace(array("\r\n", "\r"), "\n", $value);

    if ($lines) {
        $value = preg_replace('/[ \t\x0B\f]+/', ' ', $value);
        $value = preg_replace('/ ?\n ?/', "\n", $value);
        $value = preg_replace('/\n{3,}/', "\n\n", $value);
        $value = preg_replace('/^[ \n]+|[ \n]+$/', '', $value);
    } else {
        $value = preg_replace('/[ \t\n\x0B\f]+/', ' ', $value);
        $value = preg_replace('/^ +| +$/', '', $value);
    }

    return (string) $value;
}

/**
 * The length of a text in characters, as the browser counts them. Pure.
 *
 * @param string $value
 * @return int
 */
function pg_site_brief_length($value)
{
    return (int) preg_match_all('/./us', (string) $value);
}

/**
 * A brief as sent or kept, made clean, and what the person must change.
 * A text over its length is refused, never cut, so the person sees what
 * was too long. The kind falls back to 'other' and the tone to the first.
 * Pages: a known key or a name of its own, duplicates (any case) and
 * empty ones dropped, in the order given. Pure.
 *
 * @param mixed $in name, what, kind, customers, tone, pages, colour
 * @return array('brief' => array(...), 'errors' => array(field => text),
 *               'codes' => array(field => code of pg_site_brief_texts()'s errors))
 */
function pg_site_brief_check($in)
{
    $in = is_array($in) ? $in : array();
    $limits = pg_site_brief_limits();
    $get = function ($key) use ($in) {
        return isset($in[$key]) ? $in[$key] : '';
    };

    $tone = $get('tone');
    $brief = array(
        'name'      => pg_site_brief_text($get('name')),
        'what'      => pg_site_brief_text($get('what'), true),
        'kind'      => pg_launch_journey_purpose($get('kind')),
        'customers' => pg_site_brief_text($get('customers')),
        'tone'      => (is_string($tone) && in_array($tone, pg_site_brief_tones(), true)) ? $tone : 'friendly',
        'pages'     => array(),
        'colour'    => pg_site_brief_colour($get('colour')),
    );

    $errors = array();
    $codes = array();
    $long = false;
    $seen = array();
    $pages = is_array($get('pages')) ? array_values($get('pages')) : array();

    foreach ($pages as $page) {
        $page = pg_site_brief_text($page);
        $fold = mb_strtolower($page, 'UTF-8');

        if ($page === '' || isset($seen[$fold])) {
            continue;
        }
        $seen[$fold] = true;

        if (pg_site_brief_length($page) > $limits['page']) {
            $long = true;
        }
        $brief['pages'][] = $page;
    }

    // A code a field, the same as the browser's twin gives; the words are
    // pg_site_brief_texts()'s errors.
    if ($brief['name'] === '') {
        $codes['name'] = 'name';
    } elseif (pg_site_brief_length($brief['name']) > $limits['name']) {
        $codes['name'] = 'nameLong';
    }

    $what = pg_site_brief_length($brief['what']);

    if ($what < $limits['what_min']) {
        $codes['what'] = 'what';
    } elseif ($what > $limits['what']) {
        $codes['what'] = 'whatLong';
    }

    if (pg_site_brief_length($brief['customers']) > $limits['customers']) {
        $codes['customers'] = 'customers';
    }

    if (!$brief['pages']) {
        $codes['pages'] = 'pages';
    } elseif (count($brief['pages']) > $limits['pages']) {
        $codes['pages'] = 'pagesMany';
    } elseif ($long) {
        $codes['pages'] = 'pageLong';
    }

    $colour = $get('colour');

    if (is_string($colour) && trim($colour) !== '' && $brief['colour'] === '') {
        $codes['colour'] = 'colour';
    }

    if ($codes) {
        $words = pg_site_brief_texts();

        foreach ($codes as $field => $code) {
            $errors[$field] = $words['errors'][$code];
        }
    }

    return array('brief' => $brief, 'errors' => $errors, 'codes' => $codes);
}

/**
 * Why the start cannot be offered, '' when it can: it needs Pinegrap AI and
 * the daily drafts of "Write with AI". The browser's model is not used.
 *
 * @return string
 */
function pg_site_brief_problem()
{
    require_once(PG_FUNCTIONS_DIR . '/includes/fn/ai_write.php');

    return pg_ai_write_problem();
}

/**
 * The chips' names in the panel's language, by key. Pure.
 *
 * @return array key => name
 */
function pg_site_brief_page_names()
{
    return array(
        'home'      => lang('Home'),
        'about'     => lang('About'),
        'services'  => lang('Services'),
        'products'  => lang('Products'),
        'portfolio' => lang('Our work'),
        'team'      => lang('Team'),
        'courses'   => lang('Courses'),
        'events'    => lang('Events'),
        'booking'   => lang('Booking'),
        'pricing'   => lang('Prices'),
        'blog'      => lang('Blog'),
        'faq'       => lang('Questions'),
        'donate'    => lang('Donate'),
        'contact'   => lang('Contact'),
    );
}

/**
 * The words the start's part uses, translated.
 *
 * @return array
 */
function pg_site_brief_texts()
{
    $limits = pg_site_brief_limits();

    return array(
        'plan'     => lang('Plan my site'),
        'kept'     => lang('Your description is kept in this browser.'),
        'fix'      => lang('Check the fields marked in red.'),
        'chars'    => lang('{var:1} of {var:2}'),
        'remove'   => lang('Remove {var:1}'),
        'pageAdd'  => lang('Add'),
        'clear'    => lang('Clear'),
        'errors'   => array(
            'name'      => lang('Give the name of the business or the site.'),
            'what'      => lang(array('string' => 'Say what you do in a sentence or two: {var:1} characters at least.', 'vars' => $limits['what_min'])),
            'whatLong'  => lang(array('string' => 'This takes {var:1} characters at most.', 'vars' => $limits['what'])),
            'nameLong'  => lang(array('string' => 'The name takes {var:1} characters at most.', 'vars' => $limits['name'])),
            'customers' => lang(array('string' => 'This takes {var:1} characters at most.', 'vars' => $limits['customers'])),
            'pages'     => lang('Choose one page at least.'),
            'pagesMany' => lang(array('string' => 'A site from a description has {var:1} pages at most.', 'vars' => $limits['pages'])),
            'pageLong'  => lang(array('string' => 'A page\'s name takes {var:1} characters at most.', 'vars' => $limits['page'])),
            'colour'    => lang('Write the colour as six hex digits, like #2563eb, or leave it empty.'),
        ),
    );
}

/**
 * The start's pane: the brief's fields, drawn whole here; the part
 * (assets/js/designer_describe.js) presses the chips and the tone, checks
 * the fields, keeps the brief in the browser and draws the foot's button.
 *
 * @param string $site     the site's name, the first answer for the name
 * @param string $purpose  the set-up card's purpose, '' for none chosen
 * @param string $language the pages' language, by name
 * @return string
 */
function pg_site_brief_pane($site, $purpose, $language)
{
    $limits = pg_site_brief_limits();
    $kind = pg_launch_journey_purpose($purpose);
    $start = pg_site_brief_pages_for($kind);
    $names = pg_site_brief_page_names();
    $for = array();

    foreach (pg_launch_journey_purposes() as $p) {
        $for[$p] = pg_site_brief_pages_for($p);
    }

    $field = function ($id, $label, $control, $help = '', $optional = false) {
        return '<div class="form-field">'
            . '<label class="pg-field-label" for="sd-sb-' . $id . '">' . h($label)
            . ($optional ? ' <span class="pg-text-meta fw-normal">' . h(lang('optional')) . '</span>' : '') . '</label>'
            . $control
            . '<div class="pg-field-help pg-field-error" id="sd-sb-' . $id . '-error" hidden></div>'
            . ($help !== '' ? '<div class="pg-field-help" id="sd-sb-' . $id . '-help">' . h($help) . '</div>' : '')
            . '</div>';
    };
    $described = function ($id, $help) {
        return ' aria-describedby="sd-sb-' . $id . '-error' . ($help ? ' sd-sb-' . $id . '-help' : '') . '"';
    };

    $kinds = '';
    foreach (pg_launch_journey_purpose_cards() as $key => $card) {
        $kinds .= '<option value="' . h($key) . '"' . ($key === $kind ? ' selected' : '') . '>' . h($card['name']) . '</option>';
    }

    $tones = '';
    $tone_names = array('friendly' => lang('Friendly'), 'formal' => lang('Formal'), 'plain' => lang('Plain'));
    foreach (pg_site_brief_tones() as $i => $tone) {
        $tones .= '<button type="button" class="pg-seg-item" data-tone="' . $tone . '" aria-pressed="' . ($i === 0 ? 'true' : 'false') . '">' . h($tone_names[$tone]) . '</button>';
    }

    $chips = '';
    foreach (pg_site_brief_page_keys() as $key) {
        $chips .= '<button type="button" class="pg-button pg-button-quiet pg-button-sm sd-sb-chip" data-page="' . $key . '" aria-pressed="' . (in_array($key, $start, true) ? 'true' : 'false') . '">'
            . '<i class="bi bi-check-lg" aria-hidden="true"></i>' . h($names[$key]) . '</button>';
    }

    return '<form class="sd-sb" novalidate data-texts="' . h(json_encode(pg_site_brief_texts(), JSON_UNESCAPED_UNICODE)) . '"'
        . ' data-limits="' . h(json_encode($limits)) . '" data-for="' . h(json_encode($for)) . '" data-site="' . h($site) . '">'
        . '<div class="row g-4">'
        . '<div class="col-12 sm:col-6">' . $field('name', lang('Business name'),
            '<input type="text" class="pg-input" id="sd-sb-name" name="name" value="' . h($site) . '" maxlength="' . ($limits['name'] + 20) . '" autocomplete="organization"' . $described('name', false) . '>') . '</div>'
        . '<div class="col-12 sm:col-6">' . $field('kind', lang('Kind of site'),
            '<select class="pg-input" id="sd-sb-kind" name="kind"' . $described('kind', false) . '>' . $kinds . '</select>') . '</div>'
        . '<div class="col-12">' . $field('what', lang('What do you do?'),
            '<textarea class="pg-input" id="sd-sb-what" name="what" rows="4" placeholder="' . h(lang('For example: a family bakery, sourdough bread and cakes to order, open since 1998.')) . '"' . $described('what', true) . '></textarea>',
            lang('What you offer and what makes it yours. The pages are planned from these words.')) . '</div>'
        . '<div class="col-12">' . $field('customers', lang('Who are your customers?'),
            '<input type="text" class="pg-input" id="sd-sb-customers" name="customers" placeholder="' . h(lang('For example: families nearby and cafés')) . '"' . $described('customers', false) . '>', '', true) . '</div>'
        . '<div class="col-12 sm:col-6"><div class="form-field"><span class="pg-field-label" id="sd-sb-tone-label">' . h(lang('Tone')) . '</span>'
        .   '<span class="pg-seg sd-sb-tone" role="group" aria-labelledby="sd-sb-tone-label">' . $tones . '</span></div></div>'
        . '<div class="col-12 sm:col-6">' . $field('colour', lang('Brand colour'),
            '<span class="pg-input sd-sb-colour"><span class="sd-sb-swatch" aria-hidden="true"></span>'
            . '<input type="text" id="sd-sb-colour" name="colour" maxlength="7" spellcheck="false" placeholder="#2563eb"' . $described('colour', false) . '>'
            . '<label class="pg-input-end sd-sb-pick"><i class="bi bi-eyedropper" aria-hidden="true"></i><span class="visually-hidden">' . h(lang('Pick a colour')) . '</span><input type="color" value="#2563eb" tabindex="-1"></label></span>',
            '', true) . '</div>'
        . '<div class="col-12"><fieldset class="form-field" aria-describedby="sd-sb-pages-error sd-sb-pages-help"><legend class="pg-field-label">' . h(lang('Pages you want')) . '</legend>'
        .   '<div class="sd-sb-chips" role="group">' . $chips . '</div>'
        .   '<div class="sd-sb-add mt-3"><input type="text" class="pg-input" id="sd-sb-page" maxlength="' . ($limits['page'] + 20) . '" placeholder="' . h(lang('Another page')) . '" aria-label="' . h(lang('Another page')) . '">'
        .   '<button type="button" class="pg-button pg-button-quiet sd-sb-page-add"><i class="bi bi-plus-lg" aria-hidden="true"></i>' . h(lang('Add')) . '</button></div>'
        .   '<div class="pg-field-help pg-field-error" id="sd-sb-pages-error" hidden></div>'
        .   '<div class="pg-field-help" id="sd-sb-pages-help">' . h(lang('The kind of site chooses a first set. The plan can still change them.')) . '</div>'
        . '</fieldset></div>'
        . '</div>'
        . '<p class="pg-text-meta mt-4 mb-0"><i class="bi bi-translate me-1" aria-hidden="true"></i>'
        .   h(lang(array('string' => 'The pages are written in {var:1}, the site\'s language.', 'vars' => $language))) . '</p>'
        . '</form>';
}
