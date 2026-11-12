<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Florist": a flower shop with its own studio. A home page
 * drawn on the Florist blocks (a hero with the bouquet in an arched frame,
 * the gift card and the occasions, the subscription plans on their twelve
 * weeks, the card message) with the bouquets, Our Work and what customers
 * say; the bouquets with their sizes and prices, flowers for each special
 * occasion, flower subscriptions, wedding and event flowers, the delivery
 * areas as rings with their fees, times and the day's cut-off, flower
 * care, the about page and the questions; Our Work with filters by event type and season and a page
 * each; a bouquet order request with its delivery date and card message,
 * event enquiries and subscription requests the customer follows from
 * their account, with the status and the florist's answers; a first
 * consultation booked at a free time; bouquets, subscription bundles,
 * vases, extras and gift cards in the shop when the shop is on; a contact
 * page whose messages become conversations, the Flower Journal (the blog)
 * with its post page, a newsletter, the sign-in and account pages, a staff
 * area (the Our Work form, the order and event lists, the blog and staff
 * directory forms), a help that searches the whole site and the e-mails.
 * Built on Bootstrap 5. Read by pg_design_templates() (includes/fn/designer.php).
 *
 * The pages are built in includes/design_templates/pinegrap-florist/ (not
 * read as templates of their own): parts.php holds the node builders, the
 * florist's data and the parts every page repeats; mail.php the e-mail
 * builders; pages_public.php, work.php, orders.php, enquiries.php,
 * shop.php, standard.php and account.php the pages and widgets. Each is
 * included here in this one scope, in that order, and uses the variables
 * the files before it set. parts.php keeps the sample work in $works and
 * shop.php its products in $catalog, so each part's return is kept under
 * a _part name.
 *
 * Opening the template gives the site the newsletter's contact group
 * ('contact_groups'), made once and found again by its name the next time
 * (_pg_tpl_contact_groups()); the florist sends the newsletter to it by
 * hand. With the shop on it also gets the bouquets by occasion, the
 * prepaid subscription bundles, the vases and extras and the gift cards
 * ('catalog', _pg_tpl_catalog()); only the shop pages need the shop, and
 * the shared parts never link them. A shop order's delivery date is the
 * checkout's requested arrival date, which the store sets up in its
 * shipping settings.
 *
 * A work is a record of the Our Work form, a collection: the staff add one
 * on its staff page and the lists show it at once, filtered by the address
 * (?type=, ?season=). An order request, an event enquiry and a
 * subscription request are records of their own forms, requests the
 * florist confirms and never sales: a sender without an account gets one
 * (auto-registration), so the record's page and My Requests show it at
 * once. Its Status is an office-use-only field the staff change in the
 * control panel; the messages under it are the page's comments, mailed to
 * the customer and to the florist.
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
// is kept under a _part name ($works is parts.php's sample work, $catalog
// shop.php's products).
$florist_dir = PG_FUNCTIONS_DIR . '/includes/design_templates/pinegrap-florist';
include $florist_dir . '/parts.php';
include $florist_dir . '/mail.php';
$public_part   = include $florist_dir . '/pages_public.php';
$work_part     = include $florist_dir . '/work.php';
$order_part    = include $florist_dir . '/orders.php';
$enquiry_part  = include $florist_dir . '/enquiries.php';
$shop_part     = include $florist_dir . '/shop.php';
$standard_part = include $florist_dir . '/standard.php';
$account_part  = include $florist_dir . '/account.php';

// The pages in the order the editor lists them: the visitor's pages, Our
// Work, the order requests, the event enquiries, subscriptions and the
// consultation, the shop, the standard pages, the account and staff pages,
// the e-mails last of each part.
$pages = array_merge($public_part['pages'], $work_part['pages'], $order_part['pages'], $enquiry_part['pages'],
    $shop_part['pages'], $standard_part['pages'], $account_part['pages']);
$widgets = array_merge($public_part['widgets'], $work_part['widgets'], $order_part['widgets'], $enquiry_part['widgets'],
    $shop_part['widgets'], $standard_part['widgets'], $account_part['widgets']);

// ── Search sections ─────────────────────────────────────────────────────
// The parts of the pages the site search points a result at (Search and
// SERP in the editor's options): a result that matches one links to it,
// the words are marked when the visitor arrives, and the page's structured
// data names it. A part is found by its id ('#…') or by the name it has in
// the layer tree, once per page; it keeps an id it already has. The topics
// are words a visitor may search for that the part does not say itself.
// The about page, the delivery areas, the care tips, the questions and the
// privacy notice are marked where they are built.
$search_sections = array(
    'contact' => array(
        lang('Contact Details') => array('contact-details', lang('address, phone, e-mail, opening hours, florist, flower shop')),
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
// Two fonts: a soft, high-contrast serif for the headings, the prices and
// the customers' words, a rounded sans for the rest.
$florist_fonts = array(
    array('family' => 'Cormorant Garamond', 'weights' => array('500', '600', '700'), 'enabled' => true),
    array('family' => 'Nunito Sans', 'weights' => array('400', '600', '700'), 'enabled' => true),
);

// The template's own CSS in User Styles. Every class is named pfl- (the
// field ids are fl-); no block rule's class is named here. Selectors use
// descendants and pseudo-classes only, so they hold on the editor's canvas
// too. Colours are tokens only, so they follow any palette in both themes.
$florist_css = implode("\n", array(
    ':root,[data-bs-theme]{--pg-font-heading:"Cormorant Garamond",Garamond,Georgia,"Times New Roman",serif;'
        . '--pg-font-body:"Nunito Sans",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;'
        . '--pg-heading-weight:600;--pg-display-weight:500;--pg-heading-tracking:0;--pg-heading-line-height:1.15}',
    'h1,h2,h3,h4,h5,h6,.h1,.h2,.h3,.h4,.h5,.h6,.display-1,.display-2,.display-3,.display-4,.display-5,.display-6{font-family:var(--pg-font-heading)}',
    'body{font-family:var(--pg-font-body)}',
    // Titles: the serif at its lighter weight.
    '.pfl-display{font-family:var(--pg-font-heading);font-weight:500}',
    // The small line over a title: spaced capitals.
    '.pfl-eyebrow{letter-spacing:.16em;font-size:.75rem}',
    // The shop's name in the header and the footer, in the serif.
    '.pfl-brand{font-family:var(--pg-font-heading);font-weight:700;letter-spacing:.02em}',
    // A price: figures of one width, kept on one line.
    '.pfl-price{font-variant-numeric:tabular-nums;white-space:nowrap}',
    // What a customer says: the serif in italics, a little larger.
    '.pfl-quote{font-family:var(--pg-font-heading);font-style:italic;font-size:1.25rem;line-height:1.55}',
    // Bouquet, occasion, plan, event and work cards rise a little under the
    // pointer.
    '.pfl-lift{transition:transform .2s ease,box-shadow .2s ease}',
    '.pfl-lift:hover,.pfl-lift:focus-visible,.pfl-lift:focus-within{transform:translateY(-3px);box-shadow:var(--bs-box-shadow)}',
    '@media (prefers-reduced-motion:reduce){.pfl-lift{transition:none}.pfl-lift:hover,.pfl-lift:focus-visible,.pfl-lift:focus-within{transform:none}}',
));

return array(
    'name'        => lang('Florist'),
    'version'     => '2.0.0',
    'framework'   => 'bootstrap5',
    'order'       => 121,
    'icon'        => 'bi-flower1',
    'description' => lang('A flower shop with its own studio: bouquets with their sizes and prices, flowers for every special occasion, flower subscriptions, wedding and event flowers, delivery areas, flower care, Our Work, order requests with a delivery date and card message the customer follows from their account, event enquiries, consultations booked at a free time, the shop with bouquets, subscription bundles and gift cards when it is on, the Flower Journal, a newsletter, an inbox, a staff area and a help that searches the whole site.'),

    // The theme the template is made for; the operator can pick another
    // before opening it, and change it later in the editor. The e-mails'
    // inline colours are this palette's rose. The template's own look
    // travels in its User Styles ('assets'), so it holds under any look
    // and palette.
    'look'        => 'organic',
    'palette'     => 'coral',

    // What the installer lists on the template's card, after the points
    // every template shares.
    'highlights'  => array(
        lang('Bouquets with their sizes and prices, flowers for every special occasion, flower subscriptions and the delivery areas.'),
        lang('Order requests with a delivery date and card message, their own page, status and the florist\'s answers.'),
        lang('Wedding and event flowers with enquiries, consultations booked at a free time, and Our Work with sample work the staff replace from a form.'),
        lang('Bouquets, subscription bundles, vases and gift cards with the shop on, the Flower Journal, a newsletter, an inbox, a staff area and a help.'),
    ),

    // Made (or found again) when the template is opened; see _pg_tpl_folders().
    'folders'     => array(
        'root'         => array('name' => 'pinegrap_florist', 'access' => 'public'),
        'public'       => array('name' => 'public', 'parent' => 'root', 'access' => 'public'),
        'registration' => array('name' => 'registration', 'parent' => 'root', 'access' => 'registration'),
        'private'      => array('name' => 'private', 'parent' => 'root', 'access' => 'private'),
    ),

    'catalog'        => $shop_part['catalog'],

    'contact_groups' => array(
        'newsletter' => array(
            'name'         => lang('Florist Newsletter'),
            'description'  => lang('The addresses left on the newsletter form of the florist.'),
            'subscription' => true,
        ),
    ),

    'pages'       => $pages,

    'widgets'     => $widgets,

    'shared'      => array(
        'account_bar'   => array('name' => lang('Florist Account Bar'), 'tree' => $account_bar_tree()),
        'notice'        => array('name' => lang('Florist Notice'), 'tree' => $notice()),
        'header'        => array('name' => lang('Florist Header'), 'tree' => $header()),
        'footer'        => array('name' => lang('Florist Footer'), 'tree' => $footer()),
        'staff_bar'     => array('name' => lang('Florist Staff Bar'), 'tree' => $staff_bar()),
        'delivery_band' => array('name' => lang('Florist Delivery Band'), 'tree' => $delivery_band()),
        'email_header'  => array('name' => lang('Florist E-mail Header'), 'tree' => $email_header_tree()),
        'email_footer'  => array('name' => lang('Florist E-mail Footer'), 'tree' => $email_footer_tree()),
    ),

    // The template's own look: the rules of the blocks its home, occasions,
    // subscriptions and delivery pages are drawn on (the Florist hero, card
    // message and subscription, Locations: Zones) and its pfl- rules in one
    // User Styles entry, and its two fonts.
    'assets'      => array(
        'css'   => pg_design_template_user_styles(array('florist-hero', 'florist-card', 'florist-subscription', 'locations-zones'), $florist_css),
        'fonts' => json_encode($florist_fonts),
    ),
);
