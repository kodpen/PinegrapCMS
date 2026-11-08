<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Marina": a marina and yacht harbour at the head of a
 * bay. A home page with the kinds of berth, the featured charter boats,
 * the coming events and the News; the berths and moorings with a page per
 * kind (visitor berths, seasonal and annual berths, swinging moorings, dry
 * stack) and the rates as words, the harbour services, the facilities,
 * arriving by sea and by land, the harbour rules, the about page and the
 * questions; the Charter Boats collection with a filter by type and a page
 * per boat; the Events collection (regattas, open days, courses) with a
 * filter by kind and a page per event; a berth request, a charter enquiry
 * and an event entry the skipper follows from their account, with the
 * status and the harbour's answers; a contact page whose messages become
 * conversations, the News (the blog) with its post page, a newsletter, the
 * sign-in and account pages, a staff area (the new boat and new event
 * forms, the three request lists, the blog and staff directory forms), a
 * help that searches the whole site and the e-mails. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-marina/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * harbour's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, charters.php, requests.php, events.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set. parts.php keeps the sample data in $kinds, $boats,
 * $seasons and the like, and its page shell in $page, so no part's return
 * is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the harbour sends the newsletter to it by
 * hand. There is no catalog: the harbour office takes payment on arrival,
 * and only the address book needs the shop.
 *
 * A charter boat and an event are records of collection forms: the staff
 * add one on the new boat or new event page and the lists show it at
 * once, filtered by the address (?type=, ?kind=). A berth request, a
 * charter enquiry and an event entry are records of their own forms,
 * requests and never reservations: a skipper without an account gets one
 * (auto-registration), so the request's page and the account's list show
 * it at once. A request's Status is an office-use-only field the staff
 * change in the control panel; the messages under it are the page's
 * comments, mailed to the skipper and to the harbour. The template keeps
 * no berth availability and no prices per date: the rates are words.
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
// is kept under a _part name ($kinds, $boats and $seasons are parts.php's
// sample data, $page its page shell). charters.php comes before
// requests.php and events.php: they use its list and link pieces.
$marina_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-marina';
include $marina_dir . '/parts.php';
include $marina_dir . '/mail.php';
$public_part   = include $marina_dir . '/pages_public.php';
$charter_part  = include $marina_dir . '/charters.php';
$request_part  = include $marina_dir . '/requests.php';
$event_part    = include $marina_dir . '/events.php';
$standard_part = include $marina_dir . '/standard.php';
$account_part  = include $marina_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// charter boats, the berth requests and charter enquiries, the events and
// their entries, the standard pages, the account and staff pages, the
// e-mails last of each part.
$marina_parts = array($public_part, $charter_part, $request_part, $event_part, $standard_part, $account_part);
$pages   = array();
$widgets = array();
foreach ($marina_parts as $part) {
    $pages   = array_merge($pages, $part['pages']);
    $widgets = array_merge($widgets, $part['widgets']);
}

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The kinds of berth, the rates, the services, the facilities, arriving,
// the rules, the questions and the privacy notice are marked where they
// are built (mn- ids).
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, VHF, harbour office, opening hours')),
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
// Two fonts: a clean geometric sans for the headings, the harbour's name
// and the figures, a quiet humanist sans for the rest.
$marina_fonts = array(
    array('family' => 'Josefin Sans', 'weights' => array('400', '600', '700'), 'enabled' => true),
    array('family' => 'Nunito Sans', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pmn- (the
// field ids are mn-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$marina_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Josefin Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Nunito Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:600;--pg-heading-tracking:.01em;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the geometric sans, a touch of spacing.
    '.pmn-display{font-family:var(--pg-font-heading);font-weight:600;letter-spacing:.01em}',
    // The small line over a title: spaced capitals, like a chart's labels.
    '.pmn-eyebrow{letter-spacing:.18em;font-size:.75rem}',
    // The harbour's name in the header and the footer, in spaced capitals.
    '.pmn-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.06em;text-transform:uppercase}',
    // A rate: figures of one width, so the rate table's figures line up.
    '.pmn-price{font-variant-numeric:tabular-nums}',
    // A key figure: the geometric sans, figures of one width.
    '.pmn-stat{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums}',
    // What a skipper says: the heading sans, a little larger.
    '.pmn-quote{font-family:var(--pg-font-heading);font-size:1.125rem;line-height:1.6}',
    // The home page's way in: the palette's colour fading into the page,
    // like water under a clear sky.
    '.pmn-hero{background:linear-gradient(180deg,var(--bs-body-bg),var(--bs-primary-bg-subtle))}',
    // Berth, boat and event cards rise a little under the pointer.
    '.pmn-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pmn-lift:hover,.pmn-lift:focus-visible,.pmn-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pmn-lift{transition:none}.pmn-lift:hover,.pmn-lift:focus-visible,.pmn-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Marina'),
    'version'     => '2.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 105,
    'icon'        => 'bi-life-preserver',
    'description' => lang('A marina or yacht harbour: visitor, seasonal and annual berths, swinging moorings and dry stack with a page per kind and the rates as words, harbour services and facilities, arriving by sea and by land, the harbour rules, charter boats and events with a page each, berth requests, charter enquiries and event entries the skipper follows from their account with the harbour\'s answers, News, a newsletter, an inbox, a staff area and a help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's navy, readable as white button
    // text. The template's own look travels in its User Styles ('assets'),
    // so it holds under any look and palette.
    'look'        => 'minimal',
    'palette'     => 'midnight',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Visitor, seasonal and annual berths, swinging moorings and dry stack with a page per kind, and the rates per season as words.'),
        lang('Charter boats and events as collections: a page per boat and per event, a filter by type and kind, and samples the staff replace from a form.'),
        lang('Berth requests, charter enquiries and event entries, each with its own page, status and the harbour\'s answers, followed from the skipper\'s account.'),
        lang('Harbour services and facilities, arriving by sea and by land, the harbour rules, News, a newsletter, an inbox, a staff area and a help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_marina', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Marina Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the marina.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Marina Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Marina Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Marina Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Marina Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Marina Staff Bar'), 'tree' => $staff_bar()),
        'berth_band'   => array('name' => lang('Marina Berth Band'), 'tree' => $berth_band()),
        'email_header' => array('name' => lang('Marina E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Marina E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the Marina blocks its pages
    // place and its pmn- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('marina-pontoons', 'marina-arrival'), $marina_css),
        'fonts' => json_encode($marina_fonts),
    ),
);
