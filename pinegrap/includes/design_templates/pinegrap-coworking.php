<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Coworking": a coworking space with hot desks, fixed
 * desks, private offices and meeting rooms. A home page with the kinds of
 * space, the plans, the amenities, the coming community events and what
 * members say; the spaces by kind with a page each, meeting rooms booked
 * by the hour at a free time, the membership plans and the day pass, the
 * amenities, the about page, the questions and getting here; a tour
 * request and a private office enquiry the visitor follows from their
 * account, with the status and the front desk's answers; community events
 * from today on with a sign-up request; memberships and day passes sold in
 * the shop when the shop is on, which open a members' area with the house
 * guide and the perks; a contact page whose messages become
 * conversations, the blog with its post page, a newsletter, the sign-in
 * and account pages, a staff area (the new space and new event forms, the
 * three request lists, the blog and staff directory forms), a help center
 * that searches the whole site and the e-mails. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-coworking/
 * (not read as templates of their own): parts.php holds the node
 * builders, the space's data and the parts every page repeats; mail.php
 * the e-mail builders; pages_public.php, spaces.php, requests.php,
 * events.php, members.php, standard.php and account.php the pages and
 * widgets. Each is included here in this one scope, in that order, and
 * uses the variables the files before it set (events.php reuses the
 * request pieces of requests.php). parts.php keeps the space's data in
 * $spaces, $plans and $events, so no part's return is kept under those
 * names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). With the shop on it also gets the
 * Memberships and Day Passes groups with their products ('catalog',
 * _pg_tpl_catalog()); a product renews the buyer's membership, which opens
 * the members folder. Only the shop pages need the shop, and the shared
 * parts never link them.
 *
 * A space and an event are records of collection forms: the staff add one
 * on the new space or new event page and the lists show it at once, the
 * spaces filtered by the address (?kind=), the events from today on. A
 * tour request, an office enquiry and an event sign-up are records of
 * their own forms, requests and never reservations: a visitor without an
 * account gets one (auto-registration), so the request's page and the
 * account's list show it at once. A request's Status is an
 * office-use-only field the staff change in the control panel; the
 * messages under it are the page's comments, mailed to the visitor and to
 * the space. A meeting room is a booking service of capacity 1; the
 * template keeps no live desk availability.
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
 *   {{contact_group:<key>}}         id of a contact group below
 *   {{product_group:<key>}}         id of a group of the catalog
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
// is kept under a _part name ($spaces, $plans and $events are parts.php's
// data).
$coworking_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-coworking';
include $coworking_dir . '/parts.php';
include $coworking_dir . '/mail.php';
$public_part   = include $coworking_dir . '/pages_public.php';
$space_part    = include $coworking_dir . '/spaces.php';
$request_part  = include $coworking_dir . '/requests.php';
$event_part    = include $coworking_dir . '/events.php';
$member_part   = include $coworking_dir . '/members.php';
$standard_part = include $coworking_dir . '/standard.php';
$account_part  = include $coworking_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// spaces, the requests, the events, the shop and the members' area, the
// standard pages, the account and staff pages, the e-mails last of each
// part.
$pages = array_merge($public_part['pages'], $space_part['pages'], $request_part['pages'], $event_part['pages'],
    $member_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $space_part['widgets'], $request_part['widgets'], $event_part['widgets'],
    $member_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The kinds of space, the plans, the offices, the meeting room prices,
// the amenities, the events and the shop's groups are marked where they
// are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, front desk, opening hours, staffed hours')),
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
// Two fonts: a geometric sans with character for the headings and the
// figures, a plain sans for the rest.
$coworking_fonts = array(
    array('family' => 'Space Grotesk', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pcw- (the
// field ids are cw-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$coworking_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Space Grotesk",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the geometric sans, bold and drawn a little tighter.
    '.pcw-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.02em}',
    // The small line over a title: spaced capitals.
    '.pcw-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The space's name in the header and the footer.
    '.pcw-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // A price, a seat count or an hour: figures of one width.
    '.pcw-price{font-variant-numeric:tabular-nums}',
    // The home page's first section: a soft wash of the primary colour from
    // the lower left corner, where the browser mixes colours.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pcw-hero{background-image:radial-gradient(ellipse 70% 80% at 0% 100%,color-mix(in srgb,var(--bs-primary) 14%,transparent) 0%,transparent 70%)}}',
    // Space, kind, plan, event and staff cards rise a little under the pointer.
    '.pcw-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pcw-lift:hover,.pcw-lift:focus-visible,.pcw-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pcw-lift{transition:none}.pcw-lift:hover,.pcw-lift:focus-visible,.pcw-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Coworking'),
    'version'     => '2.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 119,
    'icon'        => 'bi-laptop',
    'description' => lang('A coworking space: hot desks, fixed desks, private offices and meeting rooms with a page each, meeting rooms booked by the hour, membership plans and day passes, tour requests and office enquiries the visitor follows from their account, community events with sign-ups, memberships sold in the shop with a members\' area, a blog, a newsletter, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline accent follows the palette's primary colour where the mail
    // client reads it. The template's own look travels in its User Styles
    // ('assets'), so it holds under any look and palette.
    'look'        => 'modern-sharp',
    'palette'     => 'coral',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Hot desks, fixed desks, private offices and meeting rooms with a page each, and sample spaces the staff replace from a form.'),
        lang('Tour requests and private office enquiries with their own page, status and the front desk\'s answers, and meeting rooms booked at a free time.'),
        lang('Membership plans and day passes; with the shop on, memberships that open a members\' area with the house guide and the perks.'),
        lang('Community events with sign-ups, a blog, a newsletter, an inbox, a staff area and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The members folder's access is a membership: a plan's product opens it.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_coworking', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        'members'      => array('name' => 'members', 'parent' => 'root', 'access' => 'membership'),
    ),

    'catalog'        => $member_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Coworking Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the coworking space.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Coworking Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Coworking Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Coworking Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Coworking Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Coworking Staff Bar'), 'tree' => $staff_bar()),
        'tour_band'    => array('name' => lang('Coworking Tour Band'), 'tree' => $tour_band()),
        'email_header' => array('name' => lang('Coworking E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Coworking E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the palette blocks its home,
    // plans and amenities pages place, and its pcw- rules, in one User
    // Styles entry; and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('coworking-hero', 'locations-spaces', 'locations-floors'), $coworking_css),
        'fonts' => json_encode($coworking_fonts),
    ),
);
