<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Rental": a car rental company with desks at the
 * airport and in town. A home page drawn on the Car Rental blocks (a hero
 * with a trip slip, the class line-up, a sample week's quote and the cover
 * levels) with the featured cars, the desks, the deals, what renters say
 * and the journal; the fleet by class,
 * gearbox and fuel with a page for each car, shown "or similar"; the
 * pick-up desks, the extras, the deals, the rental terms, the about page
 * and the questions; a booking request the renter follows from their
 * account, with the status and the desk's answers and price; business and
 * long-term rental enquiries the same way; a contact page whose messages
 * become conversations, the Road Trip Journal (the blog) with its post
 * page, a newsletter, the sign-in and account pages, a staff area (the new
 * car form, the two request lists, the journal and staff directory forms),
 * a help center that searches the whole site and the e-mails. Built on
 * Bootstrap 5. Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-carrental/
 * (not read as templates of their own): parts.php holds the node
 * builders, the company's data and the parts every page repeats; mail.php
 * the e-mail builders; pages_public.php, fleet.php, bookings.php,
 * business.php, standard.php and account.php the pages and widgets. Each
 * is included here in this one scope, in that order, and uses the
 * variables the files before it set (business.php and standard.php reuse
 * the request pieces of bookings.php). parts.php keeps the company's data
 * in $classes, $cars, $locations, $extras, $deals and others, so every
 * part's return is kept under a _part name.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). The rental is paid at the desk: no page but
 * the address book needs the shop.
 *
 * A car is a record of the Fleet collection form: the staff add one on the
 * new car page and the lists show it at once, filtered by the address
 * (?class=, ?gearbox=, ?fuel=). A booking request and a business enquiry
 * are records of their own forms, requests and never reservations: a
 * visitor without an account gets one (auto-registration), so the
 * request's page and the account's list show it at once. A request's
 * Status is an office-use-only field the staff change in the control
 * panel; the messages under it are the page's comments, mailed to the
 * renter and to the company. The template keeps no live availability and
 * calculates no price.
 *
 * The parts of the pages the site search points a result at (the desks,
 * the extras, the deals, the terms, the questions, the contact details)
 * are marked where they are built ($searchable in parts.php).
 *
 * Placeholders, filled when the template is opened (pg_design_template_prepare()):
 *   {{page:<key>}}                  address of the template page with that key
 *   {{tab:<key>}}                   that page in a widget setting; its id once published
 *   {{folder:<key>}}                id of that template folder
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

// The parts set variables of their own in this scope, so what each returns
// is kept under a _part name.
$carrental_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-carrental';
include $carrental_dir . '/parts.php';
$public_part   = include $carrental_dir . '/pages_public.php';
$fleet_part    = include $carrental_dir . '/fleet.php';
include $carrental_dir . '/mail.php';
$booking_part  = include $carrental_dir . '/bookings.php';
$business_part = include $carrental_dir . '/business.php';
$standard_part = include $carrental_dir . '/standard.php';
$account_part  = include $carrental_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// fleet, the booking requests, the business rentals, the standard pages,
// the account and staff pages, the e-mails last of each part.
// pages_public.php returns its pages only; its widgets (the home's cars,
// posts and Google reviews) are built by fleet.php and standard.php.
$pages = array_merge($public_part, $fleet_part['pages'], $booking_part['pages'], $business_part['pages'],
    $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($fleet_part['widgets'], $booking_part['widgets'], $business_part['widgets'],
    $standard_part['widgets'], $account_part['widgets']);

// ── The template's own look ─────────────────────────────────────────────
// Two fonts: a sturdy grotesque for the headings and the figures, a plain
// sans for the rest.
$carrental_fonts = array(
    array('family' => 'Archivo', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Inter', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pcr- (the
// field ids are cr-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$carrental_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Archivo",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-font-body:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:700;--pg-heading-tracking:-.01em;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the grotesque, bold and drawn a little tighter.
    '.pcr-display{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.02em}',
    // The small line over a title: spaced capitals.
    '.pcr-eyebrow{letter-spacing:.12em;font-size:.75rem}',
    // The company's name in the header and the footer.
    '.pcr-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:-.01em}',
    // A daily rate: figures of one width.
    '.pcr-price{font-variant-numeric:tabular-nums}',
    // The home page's first section: a soft wash of the primary colour from
    // the upper right corner, where the browser mixes colours.
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.pcr-hero{background-image:radial-gradient(ellipse 70% 80% at 100% 0%,color-mix(in srgb,var(--bs-primary) 14%,transparent) 0%,transparent 70%)}}',
    // Car, deal, contact and staff cards rise a little under the pointer.
    '.pcr-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pcr-lift:hover,.pcr-lift:focus-visible,.pcr-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pcr-lift{transition:none}.pcr-lift:hover,.pcr-lift:focus-visible,.pcr-lift:focus-within{transform:none}}',
    // From 1200 to 1400 px the header's links and buttons share one row with
    // the name: the links are set a size smaller and closer together.
    '@media (min-width:1200px) and (max-width:1399.98px){.pcr-nav{--bs-nav-link-font-size:.875rem;--bs-navbar-nav-link-padding-x:.375rem}}',
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
    'name'        => lang('Car Rental'),
    'version'     => '2.0.1',
    'framework'   => 'bootstrap5',
    'order'       => 131,
    'icon'        => 'bi-key',
    'description' => lang('A car rental company: the fleet by class, gearbox and fuel with a page for each car, pick-up desks, extras, deals and rental terms, booking requests and business rental enquiries the renter follows from their account, a blog, a newsletter, an inbox, a staff area and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline accent follows the palette's primary colour where the mail
    // client reads it. The template's own look travels in its User Styles
    // ('assets'), so it holds under any look and palette.
    'look'        => 'modern-soft',
    'palette'     => 'amber',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('The fleet by class, gearbox and fuel with a page for each car, and sample cars the staff replace from a form.'),
        lang('Booking requests with pick-up and return desks, dates, extras and deals, followed from the account with the desk\'s answers and price.'),
        lang('Business and long-term rental enquiries with their own page, status and offers.'),
        lang('Pick-up desks, extras, deals and rental terms, a blog, a newsletter, an inbox, a staff area and a help center.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_carrental', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Car Rental Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the car rental company.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Car Rental Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Car Rental Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Car Rental Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Car Rental Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Car Rental Staff Bar'), 'tree' => $staff_bar()),
        'booking_band' => array('name' => lang('Car Rental Booking Band'), 'tree' => $booking_band()),
        'email_header' => array('name' => lang('Car Rental E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Car Rental E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the Car Rental blocks its home
    // and extras pages are drawn on (the hero, the class line-up, the cover
    // levels and the sample quote) and its pcr- rules in one User Styles
    // entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('carrental-hero', 'carrental-lineup', 'carrental-cover', 'carrental-quote'), $carrental_css),
        'fonts' => json_encode($carrental_fonts),
    ),
);
