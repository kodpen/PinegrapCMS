<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Events and Conferences": an organiser of meetups,
 * workshops and an annual conference. A home page with the conference on
 * top, its tracks, speakers, figures, what attendees say and the coming
 * events; the programme by day and track, the speakers with their talks,
 * the venue with travel and hotels, the about page with the sponsors, the
 * coming events with each event's own page, a registration the attendee
 * follows from their account (the registration's own page with its
 * messages, and My Registrations), the passes on a tickets page (with the
 * product, cart, checkout and order pages when the shop is on), a contact
 * page whose messages become conversations, news (the blog with its post
 * page), the sign-in and account pages, a staff area (the registrations,
 * the events' and the news' forms, the staff directory), a help center
 * that searches the whole site and a newsletter. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-events/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * conference's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, events.php, tickets.php, standard.php and
 * account.php the pages and widgets. Each is included here in this one
 * scope, in that order, and uses the variables the files before it set.
 *
 * Opening the template gives the site the Tickets group of the catalog
 * with the passes as products ('catalog', from tickets.php) and the
 * newsletter's contact group ('contact_groups'), both made once and found
 * again by their names the next time (_pg_tpl_catalog(),
 * _pg_tpl_contact_groups()). The shop pages need the shop ('requires'
 * on each page); the tickets page is always there.
 *
 * An event is a record of the Events form, dated when the template is
 * opened and listed from today on. A registration is a record of the
 * Registrations form: the event comes from the address of the event's
 * Register button, a visitor without an account gets one
 * (auto-registration), and its Status is an office-use-only field the
 * staff change in the control panel (Registered, Confirmed, Waitlisted,
 * Cancelled, Attended); the messages under it are the page's comments.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{product_group:<key>}}         id of a group of the catalog ('root': the site's top group)
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{site_name}}, {{site_email}}, {{year}}
 * See includes/design_templates/hello-pinegrap.php for widgets, shared
 * components and 'requires'.
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

$events_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-events';
include $events_dir . '/parts.php';
include $events_dir . '/mail.php';
$public   = include $events_dir . '/pages_public.php';
$events   = include $events_dir . '/events.php';
$tickets  = include $events_dir . '/tickets.php';
$standard = include $events_dir . '/standard.php';
$account  = include $events_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// events and registration, the tickets, the standard pages, the account
// and staff pages, the e-mails last of each part.
$pages   = array_merge($public['pages'], $events['pages'], $tickets['pages'], $standard['pages'], $account['pages']);
$widgets = array_merge($public['widgets'], $events['widgets'], $tickets['widgets'], $standard['widgets'], $account['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The days, sessions, speakers, tracks, venue, story, sponsors, passes,
// questions and the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, organisers, office')),
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
// Two fonts: a confident grotesk for the headings, a plain sans for the
// rest.
$events_fonts = array(
    array('family' => 'Space Grotesk', 'weights' => array('500', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pev- (Bootstrap
// owns pe-, its padding-end utilities); no block rule's class is named here.
// Selectors use descendants and pseudo-classes only, so they hold on the
// editor's canvas too. Colours are tokens only, so they follow any palette
// in both themes; a tint mixed with color-mix() comes after a plain
// fallback.
$events_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Space Grotesk",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:700;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // The small line over a section's title: spaced capitals.
    '.pev-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The organiser's name in the header, in the heading grotesk.
    '.pev-brand{font-family:var(--pg-font-heading);letter-spacing:-.01em}',
    // The home page's first section: a wash of the palette's two colours.
    '.pev-hero{background-color:var(--bs-tertiary-bg)}',
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pev-hero{background-image:linear-gradient(135deg,color-mix(in srgb,var(--bs-primary) 12%,var(--bs-body-bg)) 0%,var(--bs-body-bg) 55%,color-mix(in srgb,var(--bs-secondary) 8%,var(--bs-body-bg)) 100%);background-color:var(--bs-body-bg)}}',
    // Cards that lead somewhere rise a little under the pointer.
    '.pev-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pev-lift:hover,.pev-lift:focus-visible,.pev-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pev-lift{transition:none}.pev-lift:hover,.pev-lift:focus-visible,.pev-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Events and Conferences'),
    'version'     => '1.0.3',
    'framework'   => 'bootstrap5',
    'order'       => 65,
    'icon'        => 'bi-calendar2-event',
    'description' => lang('An events and conference organiser: the annual conference\'s programme by day and track, the speakers, the venue, coming events with online registration the attendee follows from their account, passes on a tickets page, news, an inbox, a staff area with the registrations and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'modern-soft',
    'palette'     => 'aurora',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The conference: a programme by day and track, the speakers with their talks, the venue with travel and hotels, and the sponsors.'),
        lang('Coming events added from the panel, each with its own page, and a registration the attendee follows with its status and messages from their account.'),
        lang('A tickets page with what each pass includes; with the shop on, the passes are sold as products with cart and checkout.'),
        lang('A staff area with the registrations, the conversations with attendees and the forms for events and news, and a help center that searches the whole site.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_events', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $tickets['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Events Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the events site.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'   => array('name' => lang('Events Account Bar'), 'tree' => $account_bar_tree()),
        'notice'        => array('name' => lang('Events Notice'), 'tree' => $notice()),
        'header'        => array('name' => lang('Events Header'), 'tree' => $header()),
        'footer'        => array('name' => lang('Events Footer'), 'tree' => $footer()),
        'staff_bar'     => array('name' => lang('Events Staff Bar'), 'tree' => $staff_bar()),
        'register_band' => array('name' => lang('Events Register Band'), 'tree' => $register_band()),
        'email_header'  => array('name' => lang('Events E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer'  => array('name' => lang('Events E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pev- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $events_css),
        'fonts' => json_encode($events_fonts),
    ),
);
