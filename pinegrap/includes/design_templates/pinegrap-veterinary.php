<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Veterinary": a small-animal veterinary practice. A home
 * page with the services, the pets it treats, the vets, the emergency line
 * and what owners say; the services with a page per service, the pets
 * (dogs, cats, rabbits and small pets, birds), the emergency and
 * out-of-hours page, the new clients' first visit, the pet health plans,
 * the practice, the questions and getting here; the vets with a page each
 * and online booking of a free time; an appointment request and a health
 * plan sign-up request the owner follows from their account, with the
 * status and the reception's answers; the health plans and a few pet care
 * products in the shop when the shop is on; a contact page whose messages
 * become conversations, Pet Care Articles (the blog, filtered by the pet)
 * with its post page, a newsletter, the sign-in and account pages, a staff
 * area (the new vet form, the appointment and health plan requests, the
 * blog and staff directory forms), a help centre that searches the whole
 * site and the e-mails. Built on Bootstrap 5. Read by pg_design_templates()
 * (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-veterinary/
 * (not read as templates of their own): parts.php holds the node builders,
 * the practice's data and the parts every page repeats; mail.php the
 * e-mail builders; pages_public.php, vets.php, appointments.php, shop.php,
 * standard.php and account.php the pages and widgets. Each is included
 * here in this one scope, in that order, and uses the variables the files
 * before it set. parts.php keeps its sample data in $services, $species,
 * $vets, $plans and $testimonials, so no part's return is kept under
 * those names.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the practice sends the newsletter to it by
 * hand. With the shop on it also gets the Health Plans and Pet Care groups
 * and their products ('catalog', _pg_tpl_catalog()); only the shop pages
 * need the shop, and the shared parts never link them.
 *
 * A vet is a record of the Vets form, a collection: the staff add one on
 * the new vet page and the list shows it at once. An appointment request
 * and a health plan sign-up are records of their own forms, requests and
 * never confirmed slots: an owner without an account gets one
 * (auto-registration), so the request's page and the account's list show
 * it at once. A request's Status is an office-use-only field the staff
 * change in the control panel; the messages under it are the page's
 * comments, mailed to the owner and to the practice. A free time is booked
 * on the online booking page with the booking widget. The template keeps
 * no pet record, medical history or vaccination reminders.
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
// is kept under a _part name ($services, $species, $vets, $plans and
// $testimonials are parts.php's sample data).
$veterinary_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-veterinary';
include $veterinary_dir . '/parts.php';
include $veterinary_dir . '/mail.php';
$public_part      = include $veterinary_dir . '/pages_public.php';
$vet_part         = include $veterinary_dir . '/vets.php';
$appointment_part = include $veterinary_dir . '/appointments.php';
$shop_part        = include $veterinary_dir . '/shop.php';
$standard_part    = include $veterinary_dir . '/standard.php';
$account_part     = include $veterinary_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// vets and online booking, the appointment and health plan requests, the
// shop, the standard pages, the account and staff pages, the e-mails last
// of each part.
$pages = array_merge($public_part['pages'], $vet_part['pages'], $appointment_part['pages'],
    $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $vet_part['widgets'], $appointment_part['widgets'],
    $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The services, the pets, the emergency signs, the first visit, the
// health plans, the questions, the vets and the privacy notice are marked
// where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, emergency line, e-mail, opening hours, appointments')),
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
// Two fonts: a rounded sans for the headings, the prices and the
// practice's name, its plainer sister for the rest.
$veterinary_fonts = array(
    array('family' => 'Nunito', 'weights' => array('600', '700', '800'), 'enabled' => true),
    array('family' => 'Nunito Sans', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pvt- (the
// field ids are vt-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$veterinary_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Nunito",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Nunito Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:700;--pg-display-weight:800;--pg-heading-tracking:0;--pg-heading-line-height:1.2}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles, service and vet names: the rounded heading sans.
    '.pvt-display{font-family:var(--pg-font-heading);font-weight:800}',
    // The small line over a title: spaced capitals.
    '.pvt-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The practice's name in the header and the footer.
    '.pvt-brand{font-family:var(--pg-font-heading);font-weight:800;letter-spacing:-.01em}',
    // The home page's opening band: a soft wash of the primary colour.
    '.pvt-hero{background:linear-gradient(180deg,var(--bs-primary-bg-subtle),var(--bs-body-bg))}',
    // A price on a card, a plan or in the shop: figures of one width, so
    // the prices of a column line up.
    '.pvt-price{font-family:var(--pg-font-heading);font-variant-numeric:tabular-nums;white-space:nowrap}',
    // Service, pet, vet, plan and product cards rise a little under the
    // pointer.
    '.pvt-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pvt-lift:hover,.pvt-lift:focus-visible,.pvt-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pvt-lift{transition:none}.pvt-lift:hover,.pvt-lift:focus-visible,.pvt-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.pvt-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
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
    // Pills, tabs and underlined links there (filters, the staff bar) read
    // the link colour: they take the same tint.
    '[data-bs-theme=dark] .bg-body-tertiary .nav{--bs-nav-link-color:var(--bs-primary-text-emphasis)}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){[data-bs-theme=dark] .bg-body-tertiary .nav{--bs-nav-link-color:color-mix(in srgb,var(--bs-primary-text-emphasis) 80%,var(--bs-emphasis-color))}}',
    // The primary's soft tint (the account card, soft bands): a palette's
    // primary and second colours are made readable on the page background
    // and fall under 4.5:1 here with several palettes (Coral 3.41:1, the
    // second colour of Graphite 3.16:1). Text, links and outline buttons
    // take the colour's emphasis tint, Bootstrap's pair for a soft tint.
    '.bg-primary-subtle :is(.text-primary,.link-primary){color:var(--bs-primary-text-emphasis)!important}',
    '.bg-primary-subtle :is(.btn-outline-primary,.btn-link){--bs-btn-color:var(--bs-primary-text-emphasis)}',
    '.bg-primary-subtle .link-secondary{color:var(--bs-secondary-text-emphasis)!important}',
    '.bg-primary-subtle .btn-outline-secondary{--bs-btn-color:var(--bs-secondary-text-emphasis)}',
    // The emergency line (account bar) and the emergency band use
    // Bootstrap's fixed red, which no palette changes: 4.3:1 on the light
    // grey and 3.39:1 on its soft tint, 2.94:1 on the dark grey. In light it
    // is taken a fifth towards black where the browser mixes colours (its
    // emphasis tint, a dark red, where it does not); in dark it takes its
    // emphasis tint.
    '.bg-body-tertiary .link-danger{color:var(--bs-danger-text-emphasis)!important}',
    '.bg-danger-subtle .btn-outline-danger{--bs-btn-color:var(--bs-danger-text-emphasis)}',
    '@supports (color:color-mix(in srgb,red 50%,blue)){'
        . '.bg-body-tertiary .link-danger{color:color-mix(in srgb,var(--bs-danger) 80%,black)!important}'
        . '.bg-danger-subtle .btn-outline-danger{--bs-btn-color:color-mix(in srgb,var(--bs-danger) 80%,black)}}',
    '[data-bs-theme=dark] .bg-body-tertiary .link-danger{color:var(--bs-danger-text-emphasis)!important}',
    '[data-bs-theme=dark] .bg-danger-subtle .btn-outline-danger{--bs-btn-color:var(--bs-danger-text-emphasis)}',
));

return array(
    'name'        => lang('Veterinary'),
    'version'     => '1.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 125,
    'icon'        => 'bi-heart-pulse',
    'description' => lang('A small-animal veterinary practice: the services with a page each, the pets it treats, emergencies and out-of-hours care, the vets, pet health plans, appointment and health plan requests the owner follows from their account, online booking of a free time, health plans and pet care products with the shop on, Pet Care Articles, a newsletter, an inbox, a staff area and a help centre that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'playful',
    'palette'     => 'emerald',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The services with a page each and their prices, the pets the practice treats, and the vets with a page each the staff add from a form.'),
        lang('Appointment requests and health plan sign-ups with their own page, status and the reception\'s answers, and online booking of a free time.'),
        lang('An emergency page with the out-of-hours line on every page, a first visit guide for new clients and the pet health plans.'),
        lang('Health plans and pet care products with the shop on, Pet Care Articles by pet, a newsletter, an inbox, a staff area and a help centre.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_veterinary', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Veterinary Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the veterinary practice.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Veterinary Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Veterinary Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Veterinary Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Veterinary Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Veterinary Staff Bar'), 'tree' => $staff_bar()),
        'booking_band' => array('name' => lang('Veterinary Booking Band'), 'tree' => $booking_band()),
        'email_header' => array('name' => lang('Veterinary E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Veterinary E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its pvt- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $veterinary_css),
        'fonts' => json_encode($veterinary_fonts),
    ),
);
