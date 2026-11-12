<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Ski Resort": a mountain ski resort. A home page with
 * today's snow, the featured slopes, the coming events and the newest
 * News; today's conditions with every slope and lift; the slopes with a
 * page each, filtered by difficulty and area, and the lifts, both open or
 * closed as the staff set them each morning; lift passes, season passes
 * and rental as words and, when the shop is on, as products; the ski
 * school with lesson requests by lesson, level and age group, and lodging
 * with stay and group enquiries, which the visitor follows from their
 * account with the status and the resort's answers; the events by date
 * from today on; the resort, the questions; a contact page whose messages
 * become conversations, News (the blog) with its post page, a newsletter,
 * the sign-in and account pages, a staff area (the new slope, lift and
 * event forms, the lesson requests and enquiries, the News and staff
 * directory forms), a help page that searches the whole site and the
 * e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-skiresort/
 * (not read as templates of their own): parts.php holds the node builders,
 * the resort's data and the small helpers; cards.php the shared parts, the
 * cards and the form fields; mail.php the e-mail builders;
 * pages_public.php, slopes.php, lessons.php, enquiries.php, events.php,
 * shop.php, standard.php and account.php the pages and widgets. Each is
 * included here in this one scope and uses the variables the files before
 * it set (enquiries.php reads lessons.php's request pieces, events.php
 * slopes.php's list pieces). parts.php keeps its sample data in $resort,
 * $slopes, $lifts, $lessons, $lodging, $events, $rental and the rest, so
 * no part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the resort sends the newsletter to it by
 * hand. With the shop on it also gets the Lift Passes, Season Passes and
 * Rental groups and their products ('catalog', _pg_tpl_catalog()); only
 * the shop pages need the shop, and the shared parts never link them.
 *
 * A slope, a lift and an event are records of the Slopes, Lifts and Events
 * forms, collections: the staff add one on its new record page and the
 * lists show it at once; a slope's and a lift's status are pick lists the
 * staff change. A lesson request and a stay or group enquiry are records
 * of their own forms, never a booking: a visitor without an account gets
 * one (auto-registration), so the request's page and the account's list
 * show it at once. A request's Status is an office-use-only field the
 * staff change in the control panel; its messages are the page's
 * comments, mailed to the visitor and to the resort. The template keeps no
 * live lift feed, trail map, dated pass, instructor schedule or room
 * availability.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{site_name}}, {{site_email}}, {{year}}
 * See includes/design_templates/hello-pinegrap.php for widgets, shared
 * components, 'catalog' and 'requires'.
 *
 * The version follows the template, not the software: raise it whenever the
 * pages change, so a design records which edition it started from.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// The parts set variables of their own in this scope, so what each returns
// is kept under a _part name ($slopes, $lifts, $lessons, $lodging, $events
// and $rental are parts.php's sample data).
$skiresort_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-skiresort';
include $skiresort_dir . '/parts.php';
include $skiresort_dir . '/cards.php';
$public_part   = include $skiresort_dir . '/pages_public.php';
$slope_part    = include $skiresort_dir . '/slopes.php';
include $skiresort_dir . '/mail.php';
$lesson_part   = include $skiresort_dir . '/lessons.php';
$enquiry_part  = include $skiresort_dir . '/enquiries.php';
$event_part    = include $skiresort_dir . '/events.php';
$shop_part     = include $skiresort_dir . '/shop.php';
$standard_part = include $skiresort_dir . '/standard.php';
$account_part  = include $skiresort_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// slopes and lifts, the lesson requests, the stay and group enquiries, the
// events, the shop, the standard pages, the account and staff pages, the
// e-mails last of each part.
$skiresort_parts = array($public_part, $slope_part, $lesson_part, $enquiry_part, $event_part,
    $shop_part, $standard_part, $account_part);
$pages = array();
$widgets = array();
foreach ($skiresort_parts as $part) {
    $pages = array_merge($pages, $part['pages']);
    $widgets = array_merge($widgets, $part['widgets']);
}

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The slopes, the lifts, the conditions, the passes, rental, the ski
// school, lodging, the events, the questions, the shop, News and the
// privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, lift hours, ticket office, ski school, lodging')),
        lang('Inbox Note')      => array('inbox-note', lang('answer, reply, conversation, inbox')),
    ),
);
$searchable_tree = function ($node, $marks, &$used) use (&$searchable_tree) {
    if (!is_array($node)) return $node;
    if (isset($node['props']) && is_array($node['props'])) {
        $p = $node['props'];
        $name = isset($p['customName']) ? (string)$p['customName'] : (isset($p['_label']) ? (string)$p['_label'] : '');
        $key = null;
        if (!empty($p['id']) && isset($marks['#' . $p['id']]) && !isset($used['#' . $p['id']])) {
            $key = '#' . $p['id'];
        } elseif ($name !== '' && isset($marks[$name]) && !isset($used[$name])) {
            $key = $name;
        }
        if ($key !== null) {
            $used[$key] = true;
            if (empty($p['id'])) $node['props']['id'] = $marks[$key][0];
            $node['props']['searchSection'] = true;
            if ($marks[$key][1] !== '') $node['props']['searchKeywords'] = $marks[$key][1];
        }
    }
    if (!empty($node['children']) && is_array($node['children'])) {
        foreach ($node['children'] as $i => $child) $node['children'][$i] = $searchable_tree($child, $marks, $used);
    }
    return $node;
};
foreach ($pages as $i => $p) {
    if (!isset($search_sections[$p['key']])) continue;
    $used = array();
    $pages[$i]['tree'] = $searchable_tree($p['tree'], $search_sections[$p['key']], $used);
}

// ── The template's own look ─────────────────────────────────────────────
// Two fonts: a crisp geometric sans for the headings, the figures and the
// resort's name, a friendly humanist sans for the rest.
$skiresort_fonts = array(
    array('family' => 'Outfit', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Nunito Sans', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named psk- (the
// field ids are skr-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$skiresort_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Outfit",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Nunito Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading);font-weight:600}',
    'body{font-family:var(--pg-font-body)}',
    // Titles, slope, lift and event names: the geometric sans, a little
    // tighter.
    '.psk-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.015em}',
    // The small line over a title: spaced capitals.
    '.psk-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The resort's name in the header and the footer.
    '.psk-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.01em}',
    // The home page's opening band: a cold sky fading into the page, a
    // line of snow under it.
    '.psk-hero{background:linear-gradient(180deg,var(--bs-primary-bg-subtle) 0%,var(--bs-body-bg) 100%);border-bottom:1px solid var(--bs-border-color)}',
    // A figure of the mountain or of today's snow: large tabular digits.
    '.psk-stat{font-family:var(--pg-font-heading);font-weight:700;font-size:2rem;line-height:1.1;font-variant-numeric:tabular-nums}',
    // A guest's words.
    '.psk-quote{font-size:1.125rem;line-height:1.5;font-style:italic}',
    // A price on a card or in the shop: figures of one width, so the prices
    // of a column line up.
    '.psk-price{font-variant-numeric:tabular-nums;white-space:nowrap}',
    // Slope, event, pass and product cards rise a little under the pointer.
    '.psk-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.psk-lift:hover,.psk-lift:focus-visible,.psk-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow-sm)}',
    '@media (prefers-reduced-motion:reduce){.psk-lift{transition:none}.psk-lift:hover,.psk-lift:focus-visible,.psk-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.psk-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
    // Dark theme, grey sections (the account bar, page headers, grey bands):
    // the palette's primary and second colours are made readable on the
    // page background and fall to about 4:1 on this lighter grey. Links,
    // eyebrows and outline buttons there take the primary's emphasis tint,
    // lightened a fifth towards the text colour where the browser mixes
    // colours (some palettes' tint is as dark as the primary itself), and
    // the second colour gives way to the body's second text colour.
    '[data-bs-theme=dark] .bg-body-tertiary :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}',
    '[data-bs-theme=dark] .bg-body-tertiary .btn-outline-primary{--bs-btn-color:var(--bs-primary-text-emphasis)}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){'
        . '[data-bs-theme=dark] .bg-body-tertiary :is(.text-primary,.link-primary){color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))!important}'
        . '[data-bs-theme=dark] .bg-body-tertiary .btn-outline-primary{--bs-btn-color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))}}',
    '[data-bs-theme=dark] .bg-body-tertiary .link-secondary{color:var(--bs-secondary-color)!important}',
    '[data-bs-theme=dark] .bg-body-tertiary .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-color)}',
));

return array(
    'name'        => lang('Ski Resort'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 135,
    'icon'        => 'bi-snow',
    'description' => lang('A mountain ski resort: today\'s conditions, the slopes by difficulty and area with a page each, the lifts open or closed, lift passes, season passes and rental as products with the shop on, ski school lesson requests by level and age, lodging and group enquiries the visitor follows from their account, events by date, News, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'minimal',
    'palette'     => 'sky',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Today\'s conditions, and the slopes and lifts with their difficulty, area and status, kept by the staff from a form.'),
        lang('Lift passes, season passes and rental as words, and as products with the shop on.'),
        lang('Ski school lesson requests and lodging and group enquiries with their own page, status and the resort\'s answers.'),
        lang('Events by date, News, a newsletter, an inbox, a staff area and a help page that searches the whole site.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_skiresort', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Ski Resort Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the ski resort.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Ski Resort Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Ski Resort Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Ski Resort Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Ski Resort Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Ski Resort Staff Bar'), 'tree' => $staff_bar()),
        'snow_band'    => array('name' => lang('Ski Resort Snow Band'), 'tree' => $snow_band()),
        'email_header' => array('name' => lang('Ski Resort E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Ski Resort E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the skiresort- blocks its pages
    // place and its psk- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('skiresort-conditions', 'skiresort-lifts', 'skiresort-snow',
            'skiresort-passes', 'skiresort-slope-map'), $skiresort_css),
        'fonts' => json_encode($skiresort_fonts),
    ),
);
