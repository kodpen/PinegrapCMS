<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Beauty Salon", part 1 of its files: the node builders,
 * the salon itself (its services by category, the team, the packages, the
 * answers of the booking form's pick lists) and the parts every page
 * repeats (account bar, header, footer, booking band, page shells, section
 * headers, form fields). The page files of the same folder are included
 * after it in the same scope and build their trees with these variables;
 * includes/design_templates/pinegrap-salon.php puts them together.
 *
 * Every structural node carries its role in '_label' (the designer's own
 * label, shown beside the element's tag in the layer tree).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

// ── Node builders ───────────────────────────────────────────────────────
// Same shapes the editor's createNode() produces; the editor gives every
// node its id when the tabs open. $name is the node's label in the layer
// tree ('_label'): the tag stays visible beside it.
$el = function ($tag, $class, $name, $children = array(), $extra = array()) {
    $props = array('tag' => $tag, 'cssClass' => $class);
    if ($name !== '') $props['_label'] = $name;
    return array('type' => 'semantic', 'props' => array_merge($props, $extra), 'children' => $children);
};
$heading = function ($tag, $text, $class, $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'heading', 'tag' => $tag, 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
$para = function ($text, $class = '', $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'paragraph', 'text' => $text, 'cssClass' => $class), $extra), 'children' => array());
};
$link = function ($text, $href, $class, $attrs = array(), $extra = array()) {
    $props = array('contentType' => 'link', 'text' => $text, 'href' => $href, 'cssClass' => $class);
    if ($attrs) $props['_attrs'] = $attrs;
    return array('type' => 'content', 'props' => array_merge($props, $extra), 'children' => array());
};
$image = function ($src, $alt, $class, $extra = array()) {
    return array('type' => 'content', 'props' => array_merge(array('contentType' => 'image', 'src' => $src, 'alt' => $alt,
        'fluid' => false, 'rounded' => false, 'cssClass' => $class), $extra), 'children' => array());
};
$attr = function ($name, $value) {
    return array('name' => $name, 'value' => $value);
};
// A decorative icon: the words beside it say what it means.
$icon = function ($name, $class = '') use ($el, $attr) {
    return $el('i', trim('bi ' . $name . ' ' . $class), lang('Icon'), array(), array('_attrs' => array($attr('aria-hidden', 'true'))));
};
$span = function ($text, $class, $name, $extra = array()) use ($el) {
    return $el('span', $class, $name, array(), array_merge(array('text' => $text), $extra));
};
$container = function ($children, $class = '') {
    return array('type' => 'container', 'props' => array('fluid' => false, 'cssClass' => $class), 'children' => $children);
};
// A gutter above 4 is wider than the container's side padding and pushes a
// phone screen sideways; wider gaps are given from lg up (g-4 g-lg-5).
$row = function ($children, $gutter = '', $justify = '', $class = '') {
    return array('type' => 'row', 'props' => array('gutter' => $gutter, 'justify' => $justify, 'align' => '', 'cssClass' => $class), 'children' => $children);
};
$col = function ($children, $md = '', $lg = '', $sm = '', $xs = '12', $class = '') {
    return array('type' => 'col', 'props' => array('xs' => $xs, 'sm' => $sm, 'md' => $md, 'lg' => $lg, 'xl' => '', 'xxl' => '',
                                                  'offsetXs' => '', 'offsetMd' => '', 'order' => '', 'cssClass' => $class), 'children' => $children);
};
$root = function ($children) {
    return array('type' => 'root', 'props' => array('cssClass' => 'd-flex flex-column min-vh-100'), 'children' => $children);
};
$widget_root = function ($children) {
    return array('type' => 'root', 'props' => array(), 'children' => $children);
};
$loop = function ($children) {
    return array('type' => 'loop_area', 'props' => array(), 'children' => $children);
};
$widget = function ($key) {
    return array('type' => 'shared_ref', 'props' => array('templateWidget' => $key), 'children' => array());
};
$shared = function ($key) {
    return array('type' => 'shared_ref', 'props' => array('templateShared' => $key), 'children' => array());
};
$bind = function ($token) {
    return array('_bindings' => array('text' => $token));
};
$show_when = function ($flag) {
    return array('_bindings' => array('eo_visible_if' => $flag));
};
// A part of a page the site search can point a result at (Search and SERP in
// the editor's options): its anchor, the switch, and the topics a visitor
// may search for that the part does not say itself.
$searchable = function ($id, $topics = '') {
    $props = array('id' => $id, 'searchSection' => true);
    if ($topics !== '') $props['searchKeywords'] = $topics;
    return $props;
};
// The language switcher button: the server draws it for each request and
// leaves it out while the site has a single language.
$lang_switcher = function ($class = '', $variant = 'secondary') {
    return array('type' => 'component', 'props' => array(
        'componentType' => 'language_switcher',
        'variant' => $variant, 'outline' => true, 'size' => 'sm',
        'align' => 'lg-end', 'display' => 'name', 'icon' => 'translate',
        'cssClass' => $class,
    ), 'children' => array());
};

// A sample photo with a fixed seed, so the same picture comes back on every
// opening until the salon puts its own in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-salon-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: the person's initials on the palette's colour. The
// initials follow the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/fce7f3/9d174d?text=' . rawurlencode($initials !== '' ? $initials : 'S');
};

// ── The salon: its services, team and packages ──────────────────────────

// The categories, in the order the pages list them. Each has its section
// on the services page (#category-<key>) and the words a visitor may
// search for.
$categories = array(
    'hair'   => array('icon' => 'bi-scissors', 'title' => lang('Hair'),
        'text'   => lang('Cuts, colour and care for every length and texture, finished the way you wear it.'),
        'topics' => lang('hair, haircut, hairdresser, colour, dye, blow-dry')),
    'nails'  => array('icon' => 'bi-gem', 'title' => lang('Nails'),
        'text'   => lang('Manicures and pedicures with a long-lasting finish and a relaxing hand and foot care.'),
        'topics' => lang('nails, manicure, pedicure, gel, polish')),
    'skin'   => array('icon' => 'bi-droplet', 'title' => lang('Skin & Brows'),
        'text'   => lang('Facials for your skin type, brow shaping and lash lifts that last for weeks.'),
        'topics' => lang('facial, skin care, brows, lashes, waxing')),
    'makeup' => array('icon' => 'bi-brush', 'title' => lang('Make-up'),
        'text'   => lang('Make-up for an evening out, a photo shoot or the biggest day of your life.'),
        'topics' => lang('make-up, makeup, bridal, wedding, event')),
);

// The services, each in its category, with how long it takes and what it
// costs. The booking form lists their titles.
$services = array(
    'cut'      => array('category' => 'hair', 'title' => lang('Cut & Blow-dry'), 'duration' => lang('60 minutes'), 'price' => lang('From $45'),
        'text' => lang('A consultation, a wash, a cut that suits your face and a blow-dry to take home.')),
    'colour'   => array('category' => 'hair', 'title' => lang('Colour & Gloss'), 'duration' => lang('120 minutes'), 'price' => lang('From $90'),
        'text' => lang('All-over colour or roots, finished with a gloss for shine.')),
    'balayage' => array('category' => 'hair', 'title' => lang('Balayage & Highlights'), 'duration' => lang('180 minutes'), 'price' => lang('From $150'),
        'text' => lang('Hand-painted lightness that grows out softly, toned to your shade.')),
    'treat'    => array('category' => 'hair', 'title' => lang('Hair Treatment'), 'duration' => lang('45 minutes'), 'price' => lang('From $40'),
        'text' => lang('A deep treatment for dry, coloured or damaged hair.')),
    'mani'     => array('category' => 'nails', 'title' => lang('Gel Manicure'), 'duration' => lang('60 minutes'), 'price' => lang('$35'),
        'text' => lang('Shaping, cuticle care and a gel colour that lasts up to three weeks.')),
    'pedi'     => array('category' => 'nails', 'title' => lang('Spa Pedicure'), 'duration' => lang('75 minutes'), 'price' => lang('$45'),
        'text' => lang('A warm soak, a scrub, a massage and the polish of your choice.')),
    'facial'   => array('category' => 'skin', 'title' => lang('Signature Facial'), 'duration' => lang('60 minutes'), 'price' => lang('$70'),
        'text' => lang('Cleansing, exfoliation, a mask and a massage, chosen for your skin.')),
    'brows'    => array('category' => 'skin', 'title' => lang('Brow & Lash Lift'), 'duration' => lang('45 minutes'), 'price' => lang('$50'),
        'text' => lang('Shaped and tinted brows and lifted lashes that last six to eight weeks.')),
    'event'    => array('category' => 'makeup', 'title' => lang('Event Make-up'), 'duration' => lang('60 minutes'), 'price' => lang('$65'),
        'text' => lang('A look for an evening out or a photo shoot, made to last all night.')),
    'bridal'   => array('category' => 'makeup', 'title' => lang('Bridal Make-up'), 'duration' => lang('90 minutes'), 'price' => lang('From $180'),
        'text' => lang('A trial before the day and the finished look on the morning itself.')),
);

// The team, in the order the pages list them, with the categories they work
// in and the days they are in the salon.
$team = array(
    'selin' => array('name' => lang('Selin Aydın'), 'role' => lang('Founder & Colourist'), 'categories' => array('hair'),
        'bio'  => lang('Twelve years behind the chair; balayage and natural blondes are her signature.'),
        'days' => lang('Tuesday to Saturday')),
    'deniz' => array('name' => lang('Deniz Kaya'), 'role' => lang('Senior Stylist'), 'categories' => array('hair'),
        'bio'  => lang('Precision cuts, short styles and curly hair, cut dry when it suits the curl.'),
        'days' => lang('Monday to Friday')),
    'mira'  => array('name' => lang('Mira Demir'), 'role' => lang('Nail Artist'), 'categories' => array('nails'),
        'bio'  => lang('Clean gel work and fine nail art, from a nude finish to hand-painted details.'),
        'days' => lang('Wednesday to Sunday')),
    'leyla' => array('name' => lang('Leyla Şahin'), 'role' => lang('Skin Therapist & Make-up Artist'), 'categories' => array('skin', 'makeup'),
        'bio'  => lang('Facials, brows and lashes during the week; brides and events at weekends.'),
        'days' => lang('Thursday to Monday')),
);

// The packages: the middle one is the one most clients choose.
$packages = array(
    array('title' => lang('Express Refresh'), 'price' => lang('$75'), 'featured' => false,
          'text'  => lang('A quick lift between appointments, done in under two hours.'),
          'items' => array(lang('Cut & Blow-dry'), lang('Brow shaping'), lang('A glass of something cold'))),
    array('title' => lang('Pamper Day'), 'price' => lang('$160'), 'featured' => true,
          'text'  => lang('A half day for yourself: hair, hands and skin in one visit.'),
          'items' => array(lang('Signature Facial'), lang('Gel Manicure'), lang('Cut & Blow-dry'), lang('A light lunch'))),
    array('title' => lang('Bridal Package'), 'price' => lang('$320'), 'featured' => false,
          'text'  => lang('A trial weeks ahead and the whole look on the day.'),
          'items' => array(lang('Hair and make-up trial'), lang('Bridal hair and make-up on the day'), lang('Gel Manicure'), lang('A touch-up kit to take with you'))),
);

// The answers of the booking form's pick lists. Each answer's value is its
// own words, so the e-mails and the lists read as the client chose.
$service_titles = array();
foreach ($services as $s) $service_titles[] = $s['title'];
$team_choices = array(lang('No preference'));
foreach ($team as $t) $team_choices[] = $t['name'] . ' · ' . $t['role'];
$time_windows = array(
    lang('Morning (09:00–12:00)'),
    lang('Midday (12:00–15:00)'),
    lang('Afternoon (15:00–18:00)'),
    lang('Evening (18:00–20:00)'),
);

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar: the offer of the season. A shared component.
$notice = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-stars', 'text-primary'),
        $span(lang('New clients: 15% off your first visit when you book online.'), '', lang('Text')),
    ));
};

// The bar above the header: the notice on the left, the language switcher
// and the login region on the right. Every page places it as one shared
// component, so a change to it is made once for the whole site.
$account_bar_tree = function () use ($el, $widget, $shared, $lang_switcher) {
    return $el('div', 'border-bottom small bg-body-tertiary', lang('Account Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center justify-content-between gap-2 py-1', lang('Account Area'), array(
            $el('div', '', lang('Announcement Area'), array($shared('notice'))),
            $el('div', 'd-flex align-items-center gap-2 ms-auto', lang('Account Links'), array(
                $lang_switcher(), $widget('login_region'),
            )),
        )),
    ));
};

// The header: the salon's name, the menu and the way to book. The links to
// the current page are marked active on the server (smart active state),
// which is what lets one header serve every page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('services', lang('Services & Prices')),
        array('team', lang('Our Team')),
        array('lookbook', lang('Lookbook')),
        array('about', lang('About')),
        array('blog', lang('Blog')),
        array('contact', lang('Contact us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-semibold', lang('Brand'), array(
                    $el('span', 'd-inline-flex p-2 rounded-circle bg-primary-subtle text-primary-emphasis lh-1', lang('Icon Box'), array($icon('bi-flower2', 'fs-5'))),
                    $span('{{site_name}}', 'fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#sl-nav'),
                    $attr('aria-controls', 'sl-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav mx-xl-auto column-gap-xl-2 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 gap-xl-3 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary rounded-pill d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-heart'),
                            $span(lang('Book Now'), '', lang('Link Text')),
                        ), array('href' => '{{page:appointment}}')),
                    )),
                ), array('id' => 'sl-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The opening hours, as a definition list: the day, then the hours.
$hours = function ($class = 'row small mb-0') use ($el) {
    $children = array();
    foreach (array(
        array(lang('Monday'), lang('Closed')),
        array(lang('Tuesday–Friday'), '10:00–20:00'),
        array(lang('Saturday'), '09:00–19:00'),
        array(lang('Sunday'), '11:00–17:00'),
    ) as $d) {
        $children[] = $el('dt', 'col-6 fw-normal text-body-secondary', lang('Day'), array(), array('text' => $d[0]));
        $children[] = $el('dd', 'col-6 mb-2', lang('Time'), array(), array('text' => $d[1]));
    }
    return $el('dl', $class, lang('List'), $children);
};

// The footer, on a dark band: the salon in a sentence, the links for
// clients, the services, how to find the salon and the hours.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $hours, $categories, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $category_links = array();
    foreach ($categories as $key => $c) $category_links[] = $link($c['title'], '{{page:services}}#category-' . $key, $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Salon'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-semibold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-flower2', 'text-primary'),
                        $span('{{site_name}}', '', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para(lang('Hair, nails, skin and make-up in a calm studio, by a small team who remember how you like it.'), 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-instagram', 'Instagram'),
                        $social('bi-tiktok', 'TikTok'),
                        $social('bi-pinterest', 'Pinterest'),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Clients'), array(
                    $heading('h2', lang('Clients'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book Now'), '{{page:appointment}}', $plain),
                        $link(lang('My Appointments'), '{{page:my_appointments}}', $plain),
                        $link(lang('Inbox'), '{{page:inbox}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                        $link(lang('Help Center'), '{{page:search}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('Our Services'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($category_links),
                )),
                $el('div', 'col-12 col-md-4 col-lg-4', lang('Contact Details'), array(
                    $heading('h2', lang('Visit us'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')), lang('Address')),
                    $contact_line('bi-telephone', $link('+90 555 000 00 00', 'tel:+905550000000', $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link('hello@example.com', 'mailto:hello@example.com', $plain . ' text-break'), lang('Email')),
                    $hours('row small mt-3 mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('Prices are a guide; your stylist confirms the price before starting.'), 'small text-body-secondary text-md-end mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The booking band: a full-width band in the palette's soft colour with
// the way to book. Shared, so every page that ends with it carries the
// same offer.
$booking_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary-subtle', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Ready for a little time for yourself?'), 'h3 fw-semibold mb-2'),
                    $para(lang('Choose the service, a stylist and a time of day; we confirm by e-mail, usually within the hour.'), 'text-body-secondary mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book Now'), '{{page:appointment}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                        $link(lang('Call us'), 'tel:+905550000000', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A page of the salon: the account bar, the shared header, the page's own
// content, the newsletter band (left off where it would be in the way:
// the forms, the account pages) and the shared footer.
$page = function ($main, $newsletter = true) use ($root, $shared, $el, $widget) {
    $children = array(
        $shared('account_bar'),
        $shared('header'),
        $el('main', '', lang('Main Content'), $main),
    );
    if ($newsletter) $children[] = $widget('newsletter_band');
    $children[] = $shared('footer');
    return $root($children);
};

// The top of the inner pages: where the page sits, its title and one
// sentence, and whatever the page adds under them.
$page_header = function ($title, $lead, $more = array()) use ($el, $heading, $para, $link, $container, $attr) {
    return $el('header', 'py-4 py-lg-5 border-bottom bg-body-tertiary', lang('Page Header'), array(
        $container(array_merge(array(
            $el('nav', '', lang('Breadcrumb'), array(
                $el('ol', 'breadcrumb small mb-2', lang('Breadcrumb List'), array(
                    $el('li', 'breadcrumb-item', lang('Breadcrumb Item'), array($link(lang('Homepage'), '{{page:home}}', 'link-secondary text-decoration-none'))),
                    $el('li', 'breadcrumb-item active', lang('Breadcrumb Item'), array(), array('text' => $title, '_attrs' => array($attr('aria-current', 'page')))),
                )),
            ), array('_attrs' => array($attr('aria-label', lang('Breadcrumb'))))),
            $heading('h1', $title, 'display-6 fw-semibold mb-2'),
            $para($lead, 'lead text-body-secondary mb-0'),
        ), $more)),
    ));
};

// The heading over a section: a small line above, the title, a link to
// the rest, and a rule under them.
$section_header = function ($eyebrow, $title, $more_text = '', $more_href = '') use ($el, $heading, $para, $icon, $span) {
    $children = array($el('div', '', lang('Section Title'), array(
        $para($eyebrow, 'small text-uppercase fw-semibold text-primary mb-1'),
        $heading('h2', $title, 'h3 fw-semibold mb-0'),
    )));
    if ($more_text !== '') {
        $children[] = $el('a', 'd-inline-flex align-items-center gap-1 fw-semibold link-primary text-decoration-none text-nowrap', lang('Link'), array(
            $span($more_text, '', lang('Link Text')),
            $icon('bi-arrow-right'),
        ), array('href' => $more_href));
    }
    return $el('div', 'd-flex flex-wrap align-items-end justify-content-between gap-2 pb-3 mb-4 border-bottom', lang('Section Header'), $children);
};

// An icon in a soft round tile, the way the categories are marked.
$icon_tile = function ($name, $class = '') use ($el, $icon) {
    return $el('span', trim('d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary-emphasis p-3 lh-1 ' . $class), lang('Icon Box'), array(
        $icon($name, 'fs-4'),
    ));
};

// Five stars, for what clients say.
$stars = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex gap-1 text-warning mb-2', lang('Rating'), array(
        $span(lang('Five out of five stars'), 'visually-hidden', lang('Text')),
        $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'), $icon('bi-star-fill'),
    ));
};

// A numbered step of a short how-to.
$step = function ($n, $title, $text) use ($el, $heading, $para, $span) {
    return $el('li', 'd-flex gap-3 mb-4', lang('Step'), array(
        $span((string)$n, 'badge rounded-pill text-bg-primary fs-6 align-self-start', lang('Step Number')),
        $el('div', '', lang('Text'), array(
            $heading('h3', $title, 'h6 fw-bold mb-1'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ));
};

// A check list: a tick and the words on each line.
$check_list = function ($items, $class = 'list-unstyled mb-0') use ($el, $icon, $span) {
    $children = array();
    $last = count($items) - 1;
    foreach (array_values($items) as $n => $text) {
        $children[] = $el('li', 'd-flex gap-2 ' . ($n === $last ? 'mb-0' : 'mb-2'), lang('List Item'), array(
            $icon('bi-check2', 'text-primary'),
            $span($text, '', lang('Text')),
        ));
    }
    return $el('ul', $class, lang('Check List'), $children);
};

// A question: a search section of its own, so a search that matches it
// opens the page at the question (the marking opens the answer).
$faq = function ($n, $question, $answer, $topics = '', $parent = 'sl-faq') use ($el, $para, $attr, $searchable) {
    $id = $parent . '-' . $n;
    return $el('div', 'accordion-item', lang('Question'), array(
        $el('h3', 'accordion-header', lang('Question Header'), array(
            $el('button', 'accordion-button collapsed', lang('Question'), array(), array(
                'text'   => $question,
                '_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#' . $id),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-controls', $id),
                ),
            )),
        )),
        $el('div', 'accordion-collapse collapse', lang('Answer'), array(
            $el('div', 'accordion-body text-body-secondary', lang('Body'), array($para($answer, 'mb-0'))),
        ), array('id' => $id, '_attrs' => array($attr('data-bs-parent', '#' . $parent)))),
    ), $searchable($id . '-item', $topics));
};

// ── Form fields ─────────────────────────────────────────────────────────
$field = function ($id, $label, $control, $class = 'mb-3', $hint = '') use ($el, $attr, $para) {
    $children = array(
        $el('label', 'form-label', lang('Label'), array(), array('text' => $label, '_attrs' => array($attr('for', $id)))),
        $control,
    );
    if ($hint !== '') $children[] = $para($hint, 'form-text', array('id' => $id . '-help'));
    return $el('div', $class, $label, $children);
};
$input = function ($id, $type, $name, $autocomplete = '', $required = true, $cf = array(), $more = array()) use ($el, $attr) {
    $attrs = array($attr('type', $type), $attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    if ($autocomplete !== '') $attrs[] = $attr('autocomplete', $autocomplete);
    $props = array('id' => $id, '_attrs' => array_merge($attrs, $more));
    if ($cf) $props['_cf'] = $cf;
    return $el('input', 'form-control', lang('Field'), array(), $props);
};
$textarea = function ($id, $name, $rows, $required = true) use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    return $el('textarea', 'form-control', lang('Field'), array(), array('id' => $id, '_attrs' => $attrs));
};
// A pick list: the first, empty option is the prompt, not an answer.
// $selected is the answer chosen to begin with (no prompt then).
$select = function ($id, $name, $prompt, $options, $required = true, $selected = '', $cf = array()) use ($el, $attr) {
    $children = array();
    if ($selected === '') {
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $prompt, '_attrs' => array($attr('value', ''))));
    }
    foreach ($options as $text) {
        $oattrs = array($attr('value', $text));
        if ($text === $selected) $oattrs[] = $attr('selected', '');
        $children[] = $el('option', '', lang('Option'), array(), array('text' => $text, '_attrs' => $oattrs));
    }
    $attrs = array($attr('name', $name));
    if ($required) $attrs[] = $attr('required', '');
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('select', 'form-select', lang('Field'), $children, $props);
};
$submit = function ($text, $class = 'btn btn-primary rounded-pill px-4') use ($el, $attr) {
    return $el('button', $class, lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit'))));
};
$captcha = function () use ($el) {
    return $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'captcha')));
};
// A box the visitor must tick: what was agreed to is stored with the form.
// $privacy adds a link to the privacy page after the label.
$agree = function ($id, $name, $text, $privacy = false) use ($el, $attr, $link) {
    $children = array(
        $el('input', 'form-check-input', lang('Field'), array(), array('id' => $id, '_attrs' => array(
            $attr('type', 'checkbox'), $attr('name', $name), $attr('value', 'yes'), $attr('required', ''),
        ))),
        $el('label', 'form-check-label', lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $id)))),
    );
    if ($privacy) $children[] = $link(lang('Privacy Policy'), '{{page:privacy}}', 'ms-1');
    return $el('div', 'form-check small mb-3', lang('Consent'), $children);
};
// A group of radio buttons under its legend: the legend names the field.
// Each option is array(value, label).
$radios = function ($id, $name, $legend, $options, $checked) use ($el, $attr) {
    $children = array($el('legend', 'form-label fs-6 mb-2', lang('Label'), array(), array('text' => $legend)));
    foreach (array_values($options) as $n => $o) {
        $rid = $id . '-' . ($n + 1);
        $rattrs = array($attr('type', 'radio'), $attr('name', $name), $attr('value', $o[0]));
        if ($o[0] === $checked) $rattrs[] = $attr('checked', '');
        $children[] = $el('div', 'form-check form-check-inline', lang('Option'), array(
            $el('input', 'form-check-input', lang('Field'), array(), array('id' => $rid, '_attrs' => $rattrs)),
            $el('label', 'form-check-label', lang('Label'), array(), array('text' => $o[1], '_attrs' => array($attr('for', $rid)))),
        ));
    }
    return $el('fieldset', 'mb-3', $legend, $children);
};
// The numbered heading of a part of a longer form.
$form_step = function ($n, $title) use ($el, $span, $heading) {
    return $el('div', 'd-flex align-items-center gap-2 mt-2 mb-3', lang('Step'), array(
        $span((string)$n, 'badge rounded-circle text-bg-primary', lang('Step Number')),
        $heading('h2', $title, 'h6 fw-bold mb-0'),
    ));
};

// The record's Edit button, shown to whoever may edit the submissions of
// its form; it opens the record in the control panel and comes back.
$edit_button = function ($text = '') use ($link) {
    return $link($text !== '' ? $text : lang('Edit'), '#', 'btn btn-sm btn-outline-secondary rounded-pill', array(),
        array('_bindings' => array('href' => '__edit_url', 'eo_visible_if' => 'can_edit')));
};

// The comments of a record page (a booking's messages, a conversation's
// replies, a post's comments), in the reading column.
$comments_section = function ($name) use ($el, $container, $row, $col) {
    return $el('section', 'pb-5', $name, array(
        $container(array(
            $row(array(
                $col(array(
                    array('type' => 'region', 'props' => array('regionType' => 'comments_block', 'regionName' => ''), 'children' => array()),
                ), '', '8'),
            ), '', 'center'),
        )),
    ));
};
