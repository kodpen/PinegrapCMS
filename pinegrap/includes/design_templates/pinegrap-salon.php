<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Beauty Salon": a hair, nails, skin and make-up studio.
 * A home page with the way to book on top, the categories, the favourite
 * services, the team, how booking works, the lookbook, what clients say and
 * the newest posts; a services page with prices and durations and the
 * packages, the team with their days, a lookbook, the about page, a booking
 * form whose bookings the client follows from their account (the booking's
 * own page with its messages, and My Appointments), a contact page whose
 * messages become conversations (the inbox and the conversation page), a
 * blog with its post page, the sign-in and account pages, a staff area
 * (the coming bookings, the blog's form, the staff directory), a help
 * center that searches the whole site and a newsletter that collects
 * e-mail addresses. Built on Bootstrap 5.
 * Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-salon/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * salon's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, booking.php, standard.php and account.php
 * the pages and widgets. Each is included here in this one scope, in that
 * order, and uses the variables the files before it set.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()). There is no catalog: the salon sells nothing
 * online.
 *
 * A booking is a record of the Appointments form. A visitor without an
 * account gets one (auto-registration), so the booking's page and My
 * Appointments show it at once. Its Status is an office-use-only field the
 * staff change in the control panel (Requested, Confirmed, Completed,
 * Cancelled); the messages under it are the page's comments, mailed to the
 * client and to the salon.
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

$salon_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-salon';
include $salon_dir . '/parts.php';
include $salon_dir . '/mail.php';
$public   = include $salon_dir . '/pages_public.php';
$booking  = include $salon_dir . '/booking.php';
$standard = include $salon_dir . '/standard.php';
$account  = include $salon_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, the
// booking, the standard pages, the account and staff pages, the e-mails
// last of each part.
$pages   = array_merge($public, $booking['pages'], $standard['pages'], $account['pages']);
$widgets = array_merge($booking['widgets'], $standard['widgets'], $account['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The categories, the team, the looks, the packages, the story, the values,
// the questions and the privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, opening hours, location')),
        lang('Getting Here')    => array('getting-here', lang('parking, metro, bus, directions')),
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
// Two fonts: a light serif for the headings, a clean sans for the rest.
$salon_fonts = array(
    array('family' => 'Cormorant Garamond', 'weights' => array('500', '600'), 'enabled' => true),
    array('family' => 'Jost', 'weights' => array('400', '500', '600'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named psl- (Bootstrap owns ps-, its padding utilities); no block
// rule's class is named here. Selectors use descendants and pseudo-classes
// only, so they hold on the editor's canvas too. Colours are tokens only,
// so they follow any palette in both themes; a tint mixed with
// color-mix() comes after a plain fallback.
$salon_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Cormorant Garamond",Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Jost",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:500;--pg-heading-tracking:0;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // The small line over a title: spaced capitals.
    '.psl-eyebrow{letter-spacing:.14em;font-size:.75rem}',
    // The salon's name in the header, in the heading serif.
    '.psl-brand{font-family:var(--pg-font-heading);letter-spacing:.02em}',
    // The home page's first section: a soft wash of the palette's colour.
    '.psl-hero{background-color:var(--bs-tertiary-bg)}',
    '@supports (background-color:color-mix(in srgb,red 8%,blue)){.psl-hero{background-image:linear-gradient(160deg,color-mix(in srgb,var(--bs-primary) 10%,var(--bs-body-bg)) 0%,var(--bs-body-bg) 70%);background-color:var(--bs-body-bg)}}',
    // Cards that lead somewhere rise a little under the pointer.
    '.psl-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.psl-lift:hover,.psl-lift:focus-visible{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.psl-lift{transition:none}.psl-lift:hover,.psl-lift:focus-visible{transform:none}}',
));

return array(
    'name'        => lang('Beauty Salon'),
    'version'     => '1.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 55,
    'icon'        => 'bi-flower2',
    'description' => lang('A hair, nails, skin and make-up studio: services with prices and durations, the team with their days, a lookbook, packages, online booking the client follows from their account, a blog, an inbox, a staff area with the coming bookings and a help center that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The template's
    // own look travels in its User Styles ('assets'), so it holds under any
    // look and palette.
    'look'        => 'elegant',
    'palette'     => 'burgundy',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Online booking: the client picks the service, the stylist and a time of day, and follows the booking with its status and messages from their account.'),
        lang('Services with prices and durations, the team with their days, three packages and a lookbook.'),
        lang('A staff area with the coming bookings, the conversations with clients and the blog\'s form.'),
        lang('A help center that searches the whole site, and a beauty letter for e-mail campaigns.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_salon', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Salon Newsletter'),
            'description'  => lang('The addresses left on the beauty letter form of the salon.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'  => array('name' => lang('Salon Account Bar'), 'tree' => $account_bar_tree()),
        'notice'       => array('name' => lang('Salon Notice'), 'tree' => $notice()),
        'header'       => array('name' => lang('Salon Header'), 'tree' => $header()),
        'footer'       => array('name' => lang('Salon Footer'), 'tree' => $footer()),
        'staff_bar'    => array('name' => lang('Salon Staff Bar'), 'tree' => $staff_bar()),
        'booking_band' => array('name' => lang('Salon Booking Band'), 'tree' => $booking_band()),
        'email_header' => array('name' => lang('Salon E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer' => array('name' => lang('Salon E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: its psl- rules in one User Styles entry, and
    // its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array(), $salon_css),
        'fonts' => json_encode($salon_fonts),
    ),
);
