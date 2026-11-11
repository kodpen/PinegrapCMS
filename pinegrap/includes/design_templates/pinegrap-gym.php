<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Gym": a neighbourhood gym with group classes. A home
 * page with the week's classes, the trainers, the plans, the facilities
 * and what members say; the classes by focus and level, the weekly
 * timetable, the membership plans, the facilities, the about page, the
 * questions and getting here; the trainers with a page each and personal
 * training booked at a free time; a class booking request and a join
 * request (a free trial pass among the plans) the member follows from
 * their account, with the status and the front desk's answers;
 * memberships sold in the shop when the shop is on, which open a members'
 * area with the workout library; a contact page whose messages become
 * conversations, Training Notes (the blog) with its post page, a
 * newsletter, the sign-in and account pages, a staff area (the new
 * trainer form, the class booking and join requests, the blog and staff
 * directory forms), a member help that searches the whole site and the
 * e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-gym/ (not read
 * as templates of their own): parts.php holds the node builders, the
 * gym's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, trainers.php, classes.php, members.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set. parts.php keeps the gym's data in $classes, $trainers and
 * $plans, so no part's return is kept under those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). With the shop on it also gets the
 * Memberships group and a product per plan ('catalog', _pg_tpl_catalog());
 * a plan's product renews the buyer's membership, which opens the members
 * folder. Only the shop pages need the shop, and the shared parts never
 * link them.
 *
 * A trainer is a record of the Trainers form, a collection: the staff add
 * one on the new trainer page and the lists show it at once, filtered by
 * the address (?focus=). A class booking and a join request are records of
 * their own forms, requests and never reservations: a visitor without an
 * account gets one (auto-registration), so the request's page and the
 * account's list show it at once. A request's Status is an office-use-only
 * field the staff change in the control panel; the messages under it are
 * the page's comments, mailed to the member and to the gym. The template
 * keeps no class capacity and no waiting list.
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
 * The home, the group classes and the timetable place the palette's Gym
 * blocks (Free Pass Hero, Class Finder) and two general ones (Booking:
 * Weekly Timetable, Hero: Image Background) with the gym's own data; their
 * rules ship in User Styles from the block source, and their motion is node
 * interactions (props._fx) only: the template carries no script.
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
// is kept under a _part name ($classes, $trainers and $plans are parts.php's
// data).
$gym_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-gym';
include $gym_dir . '/parts.php';
include $gym_dir . '/mail.php';
$public_part   = include $gym_dir . '/pages_public.php';
$trainer_part  = include $gym_dir . '/trainers.php';
$class_part    = include $gym_dir . '/classes.php';
$member_part   = include $gym_dir . '/members.php';
$standard_part = include $gym_dir . '/standard.php';
$account_part  = include $gym_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// trainers, the requests, the shop and the members' area, the standard
// pages, the account and staff pages, the e-mails last of each part.
$pages = array_merge($public_part['pages'], $trainer_part['pages'], $class_part['pages'], $member_part['pages'],
    $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $trainer_part['widgets'], $class_part['widgets'], $member_part['widgets'],
    $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The classes, the timetable, the plans, the facilities, the about page,
// the questions, getting here and personal training are marked where they
// are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, front desk, opening hours')),
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
// Two fonts: a tall condensed sans for the headings and the figures, a
// plain sans for the rest.
$gym_fonts = array(
    array('family' => 'Oswald', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pgy- (the
// field ids are gy-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$gym_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Oswald","Arial Narrow",Arial,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the condensed sans in capitals.
    '.pgy-display{font-family:var(--pg-font-heading);font-weight:700;text-transform:uppercase;letter-spacing:.01em}',
    // The small line over a title: spaced capitals.
    '.pgy-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The gym's name in the header and the footer.
    '.pgy-brand{font-family:var(--pg-font-heading);font-weight:700;text-transform:uppercase;letter-spacing:.04em}',
    // A price or an hour: figures of one width, so a table's figures line up.
    '.pgy-price{font-variant-numeric:tabular-nums}',
    // The home page's first section: a soft wash of the primary colour in
    // one corner, where the browser mixes colours.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pgy-hero{background-image:radial-gradient(ellipse 70% 80% at 100% 0%,color-mix(in srgb,var(--bs-primary) 14%,transparent) 0%,transparent 70%)}}',
    // Class, trainer, plan and workout cards rise a little under the pointer.
    '.pgy-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pgy-lift:hover,.pgy-lift:focus-visible,.pgy-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pgy-lift{transition:none}.pgy-lift:hover,.pgy-lift:focus-visible,.pgy-lift:focus-within{transform:none}}',
    // A focus section of the class page stops under the focus pills that
    // stay at the top, so its heading is not hidden behind them.
    '.pgy-anchor{scroll-margin-top:4.5rem}',
    // The picture layer of the home's personal training band: taller than
    // the band by a fifth above and below, so it never shows an edge while
    // its parallax (speed 12) moves it.
    '.pgy-depth{position:absolute;top:-20%;bottom:-20%;left:0;right:0;background-size:cover;background-position:center}',
));

return array(
    'name'        => lang('Gym'),
    'version'     => '1.1.0',
    'framework'   => 'bootstrap5',
    'order'       => 79,
    'icon'        => 'bi-lightning-charge',
    'description' => lang('A neighbourhood gym: group classes by focus and level, the weekly timetable, trainers with a page each and personal training booked online, membership plans with a free trial pass, class booking and join requests the member follows from their account, memberships sold in the shop with a members\' area, Training Notes, a newsletter, an inbox, a staff area and a member help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's crimson. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look
    // and palette.
    'look'        => 'bold',
    'palette'     => 'crimson',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Group classes by focus and level and a weekly timetable whose every class opens its booking request.'),
        lang('Trainers with a page each, filtered by focus, and personal training booked at a free time.'),
        lang('Membership plans with a free trial pass and a join request followed from the account; with the shop on, memberships that open a members\' area.'),
        lang('Training Notes, a newsletter, an inbox, a staff area and a member help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    // The members folder's access is a membership: a plan's product opens it.
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_gym', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
        'members'      => array('name' => 'members', 'parent' => 'root', 'access' => 'membership'),
    ),

    'catalog'        => $member_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Gym Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the gym.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Gym Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Gym Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Gym Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Gym Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Gym Staff Bar'), 'tree' => $staff_bar()),
        'join_band'    => array('name' => lang('Gym Join Band'), 'tree' => $join_band()),
        'email_header' => array('name' => lang('Gym E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Gym E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the blocks its pages place and
    // its pgy- rules in one User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('gym-hero', 'gym-classes', 'booking-week', 'hero-image-bg'), $gym_css),
        'fonts' => json_encode($gym_fonts),
    ),
);
