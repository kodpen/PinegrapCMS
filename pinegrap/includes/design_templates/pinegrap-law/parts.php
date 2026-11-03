<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Law Firm", part 1 of its files: the node builders, the
 * firm itself (its practice areas, lawyers, figures, case results, fee
 * models and the answers of the consultation form's pick lists) and the
 * parts every page repeats (account bar, header, footer, consultation
 * band, page shells, section headers, form fields, the comment thread).
 * The page files of the same folder are included after it in the same
 * scope and build their trees with these variables;
 * includes/design_templates/pinegrap-law.php puts them together.
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
// opening until the firm puts its own in its place.
$photo = function ($seed, $width, $height) {
    return 'https://picsum.photos/seed/pinegrap-law-' . $seed . '/' . (int)$width . '/' . (int)$height;
};
// A sample portrait: the person's initials on the palette's colour. The
// initials follow the name in the site's language.
$portrait = function ($name, $size = 480) {
    $initials = '';
    foreach (array_slice(preg_split('/\s+/u', trim($name)), 0, 2) as $w) $initials .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    return 'https://placehold.co/' . (int)$size . 'x' . (int)$size . '/e0e7ff/1e3a8a?text=' . rawurlencode($initials !== '' ? $initials : 'L');
};

// ── The firm: its practice areas, lawyers, results and fees ─────────────

// The practice areas, in the order the pages list them. Each has its own
// page (area_<key>), the matters it covers, the lawyer who leads it and
// the words a visitor may search for.
$practice_areas = array(
    'corporate'  => array('icon' => 'bi-building', 'title' => lang('Corporate & Commercial'), 'lead' => 'elif',
        'text'    => lang('Company formation, shareholder agreements, contracts and the deals that grow a business.'),
        'matters' => array(lang('Company formation and restructuring'), lang('Shareholder and partnership agreements'),
                           lang('Commercial contracts and terms of trade'), lang('Mergers, acquisitions and due diligence')),
        'topics'  => lang('company, business, contract, shareholder, merger, acquisition')),
    'employment' => array('icon' => 'bi-people', 'title' => lang('Employment'), 'lead' => 'murat',
        'text'    => lang('Advice for employers and employees, from the first contract to a dispute at the labour court.'),
        'matters' => array(lang('Employment contracts and workplace policies'), lang('Dismissal and severance claims'),
                           lang('Mediation before the labour court'), lang('Work permits for foreign staff')),
        'topics'  => lang('employment, labour, dismissal, severance, work permit')),
    'realestate' => array('icon' => 'bi-house-door', 'title' => lang('Real Estate'), 'lead' => 'zeynep',
        'text'    => lang('Buying, selling, leasing and building, with the title deed checked before anything is signed.'),
        'matters' => array(lang('Purchase and sale of property'), lang('Title deed and zoning checks'),
                           lang('Commercial and residential leases'), lang('Construction and urban renewal contracts')),
        'topics'  => lang('property, real estate, title deed, lease, rent, construction')),
    'family'     => array('icon' => 'bi-heart', 'title' => lang('Family & Inheritance'), 'lead' => 'ayse',
        'text'    => lang('Divorce, custody, wills and estates, handled with discretion and with the family in mind.'),
        'matters' => array(lang('Divorce by agreement and contested divorce'), lang('Custody, visitation and maintenance'),
                           lang('Wills and estate planning'), lang('Inheritance shares and probate')),
        'topics'  => lang('divorce, custody, alimony, will, inheritance, estate')),
    'disputes'   => array('icon' => 'bi-shield-check', 'title' => lang('Litigation & Disputes'), 'lead' => 'kerem',
        'text'    => lang('Courts, arbitration and mediation: we settle where we can and argue the case where we must.'),
        'matters' => array(lang('Commercial litigation'), lang('Domestic and international arbitration'),
                           lang('Debt recovery and enforcement'), lang('Mediation and settlement talks')),
        'topics'  => lang('lawsuit, court, arbitration, mediation, debt collection, enforcement')),
    'ip'         => array('icon' => 'bi-lightbulb', 'title' => lang('Intellectual Property & Technology'), 'lead' => 'elif',
        'text'    => lang('Trademarks, software, data protection and the contracts that keep what you build your own.'),
        'matters' => array(lang('Trademark and design registration'), lang('Software and licence agreements'),
                           lang('Personal data protection (KVKK and GDPR)'), lang('E-commerce terms and online disputes')),
        'topics'  => lang('trademark, patent, copyright, software, data protection, KVKK, GDPR')),
);

// The lawyers, in the order the pages list them, with the areas they work
// in, where they are admitted and the languages they work in.
$lawyers = array(
    'ayse'   => array('name' => lang('Ayşe Yılmaz'), 'role' => lang('Founding Partner'), 'areas' => array('family', 'disputes'),
        'bio'       => lang('Twenty years in family and estate matters; she founded the firm on plain words and fixed fees.'),
        'education' => lang('Istanbul University, Faculty of Law'),
        'bar'       => lang('Istanbul Bar Association, 2006'),
        'languages' => lang('Turkish, English')),
    'kerem'  => array('name' => lang('Kerem Arslan'), 'role' => lang('Partner'), 'areas' => array('disputes', 'corporate'),
        'bio'       => lang('A litigator and arbitration counsel who has argued commercial cases before courts and tribunals.'),
        'education' => lang('Ankara University, Faculty of Law; LL.M., King\'s College London'),
        'bar'       => lang('Istanbul Bar Association, 2009'),
        'languages' => lang('Turkish, English, German')),
    'elif'   => array('name' => lang('Elif Koç'), 'role' => lang('Partner'), 'areas' => array('corporate', 'ip'),
        'bio'       => lang('Advises start-ups and family companies on their contracts, their brands and their software.'),
        'education' => lang('Galatasaray University, Faculty of Law'),
        'bar'       => lang('Istanbul Bar Association, 2012'),
        'languages' => lang('Turkish, English, French')),
    'murat'  => array('name' => lang('Murat Öztürk'), 'role' => lang('Senior Associate'), 'areas' => array('employment'),
        'bio'       => lang('Employment law for both sides of the table, and a trained mediator for workplace disputes.'),
        'education' => lang('Marmara University, Faculty of Law'),
        'bar'       => lang('Istanbul Bar Association, 2015'),
        'languages' => lang('Turkish, English')),
    'zeynep' => array('name' => lang('Zeynep Çelik'), 'role' => lang('Associate'), 'areas' => array('realestate'),
        'bio'       => lang('Property purchases, leases and urban renewal projects, from the first check to the title deed.'),
        'education' => lang('Bilgi University, Faculty of Law'),
        'bar'       => lang('Istanbul Bar Association, 2018'),
        'languages' => lang('Turkish, English, Arabic')),
);

// The firm in four figures (the home and about pages).
$figures = array(
    array('value' => '20+', 'label' => lang('Years of practice')),
    array('value' => '1,200+', 'label' => lang('Matters closed')),
    array('value' => '5', 'label' => lang('Lawyers')),
    array('value' => '5', 'label' => lang('Working languages')),
);

// Case results: what the firm did, never a promise. Each names its area.
$results = array(
    array('area' => 'corporate', 'title' => lang('Sale of a family manufacturer'),
          'text'    => lang('Due diligence, negotiation and the share purchase agreement for the sale of a third-generation company.'),
          'outcome' => lang('Closed in four months, with the founders keeping a seat on the board.')),
    array('area' => 'employment', 'title' => lang('Group dismissal at a logistics firm'),
          'text'    => lang('Advice to an employer on the notice, the consultation and the severance of forty staff.'),
          'outcome' => lang('No claim reached the court; every settlement was signed at mediation.')),
    array('area' => 'disputes', 'title' => lang('Construction arbitration'),
          'text'    => lang('Counsel to a contractor in an arbitration over delay and unpaid progress payments.'),
          'outcome' => lang('The tribunal awarded the full amount claimed, with interest and costs.')),
    array('area' => 'realestate', 'title' => lang('Urban renewal of an apartment block'),
          'text'    => lang('Representation of thirty owners in choosing a developer and agreeing the share of the new flats.'),
          'outcome' => lang('A contract signed by every owner, with a completion guarantee from the developer.')),
);

// How the firm charges, in the order the fees page lists them.
$fee_models = array(
    array('icon' => 'bi-chat-square-text', 'title' => lang('First consultation'), 'price' => lang('30 minutes, free'),
          'text' => lang('We hear the matter, say whether we can help and what it would take. No obligation.')),
    array('icon' => 'bi-tag', 'title' => lang('Fixed fee'), 'price' => lang('Agreed in advance'),
          'text' => lang('For defined work (a company formation, a contract, a divorce by agreement): one price, in writing.')),
    array('icon' => 'bi-clock-history', 'title' => lang('Hourly rate'), 'price' => lang('From $150 an hour'),
          'text' => lang('For open-ended work, billed monthly with the hours itemised. Never below the bar\'s minimum tariff.')),
    array('icon' => 'bi-briefcase', 'title' => lang('Monthly retainer'), 'price' => lang('From $600 a month'),
          'text' => lang('A lawyer for your business: contracts reviewed and questions answered within a working day.')),
);

// The answers of the consultation form's pick lists. Each answer's value is
// its own words, so the e-mails and the lists read as the client chose.
$area_titles = array();
foreach ($practice_areas as $a) $area_titles[] = $a['title'];
$area_titles[] = lang('Something else');
$lawyer_choices = array(lang('No preference'));
foreach ($lawyers as $l) $lawyer_choices[] = $l['name'] . ' · ' . $l['role'];
$contact_choices = array(
    array(lang('Video call'), lang('Video call')),
    array(lang('Phone'), lang('Phone')),
    array(lang('At the office'), lang('At the office')),
);
// The times of day, inside the office hours ($hours).
$time_windows = array(
    lang('Morning (09:00–12:00)'),
    lang('Early afternoon (13:00–15:00)'),
    lang('Late afternoon (15:00–18:00)'),
);
// The query string of a link that opens the consultation form with a
// choice made: the form's Practice Area (?area=), Lawyer (?lawyer=) and
// summary (?summary=) fields start with these values (_cf.url_param). A
// lawyer is named as the Lawyer list names them.
$consultation_query = function ($params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
};

// ── The parts every page repeats ────────────────────────────────────────

// The line in the account bar. A shared component.
$notice = function () use ($el, $icon, $span) {
    return $el('p', 'd-flex align-items-center gap-2 mb-0', lang('Notice'), array(
        $icon('bi-calendar-check', 'text-primary'),
        $span(lang('First consultation free for 30 minutes, by video or at our office, within two working days.'), '', lang('Text')),
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

// The header: the firm's name, the menu and the way to ask for a
// consultation. The links to the current page are marked active on the
// server (smart active state), which is what lets one header serve every
// page.
$header = function () use ($el, $link, $icon, $span, $attr) {
    $items = array();
    foreach (array(
        array('practice', lang('Practice Areas')),
        array('lawyers', lang('Our Lawyers')),
        array('results', lang('Case Results')),
        array('fees', lang('Fees')),
        array('about', lang('About')),
        array('blog', lang('Insights')),
        array('contact', lang('Contact us')),
    ) as $n) {
        $items[] = $el('li', 'nav-item', lang('Nav Item'), array($link($n[1], '{{page:' . $n[0] . '}}', 'nav-link text-nowrap')));
    }
    return $el('header', 'bg-body border-bottom', lang('Site Header'), array(
        $el('nav', 'navbar navbar-expand-xl py-3', lang('Navbar'), array(
            $el('div', 'container', lang('Navbar Container'), array(
                $el('a', 'navbar-brand d-flex align-items-center gap-2 fw-semibold', lang('Brand'), array(
                    $el('span', 'd-inline-flex p-2 rounded-2 bg-primary text-white lh-1', lang('Icon Box'), array($icon('bi-bank', 'fs-5'))),
                    $span('{{site_name}}', 'plw-brand fs-4', lang('Name')),
                ), array('href' => '{{page:home}}')),
                $el('button', 'navbar-toggler', lang('Toggler'), array(
                    $el('span', 'navbar-toggler-icon', lang('Toggler Icon')),
                ), array('_attrs' => array(
                    $attr('type', 'button'),
                    $attr('data-bs-toggle', 'collapse'),
                    $attr('data-bs-target', '#lw-nav'),
                    $attr('aria-controls', 'lw-nav'),
                    $attr('aria-expanded', 'false'),
                    $attr('aria-label', lang('Toggle navigation')),
                ))),
                $el('div', 'collapse navbar-collapse', lang('Nav Links'), array(
                    $el('ul', 'navbar-nav mx-xl-auto column-gap-xl-1 py-3 py-xl-0', lang('Nav Menu'), $items, array('smartActive' => true)),
                    $el('div', 'd-flex flex-column flex-xl-row align-items-xl-center gap-2 gap-xl-3 pb-2 pb-xl-0', lang('Header Actions'), array(
                        $el('a', 'btn btn-primary d-inline-flex align-items-center justify-content-center gap-2 px-4 text-nowrap', lang('Call to Action'), array(
                            $icon('bi-calendar-check'),
                            $span(lang('Book a Consultation'), '', lang('Link Text')),
                        ), array('href' => '{{page:consultation}}')),
                    )),
                ), array('id' => 'lw-nav')),
            )),
        ), array('_attrs' => array($attr('aria-label', lang('Main navigation'))))),
    ));
};

// The office hours, as a definition list: the day, then the hours.
$hours = function ($class = 'row small mb-0') use ($el) {
    $children = array();
    foreach (array(
        array(lang('Monday–Friday'), '09:00–18:00'),
        array(lang('Saturday'), lang('By appointment')),
        array(lang('Sunday'), lang('Closed')),
    ) as $d) {
        $children[] = $el('dt', 'col-6 fw-normal text-body-secondary', lang('Day'), array(), array('text' => $d[0]));
        $children[] = $el('dd', 'col-6 mb-2', lang('Time'), array(), array('text' => $d[1]));
    }
    return $el('dl', $class, lang('List'), $children);
};

// The footer, on a dark band: the firm in a sentence, the links for
// clients, the practice areas, how to reach the office and the hours; at
// the bottom the line that the site's words are not legal advice.
$footer = function () use ($el, $para, $link, $icon, $span, $heading, $hours, $practice_areas, $attr) {
    $plain = 'link-body-emphasis text-decoration-none';
    $list = function ($links) use ($el) {
        $items = array();
        foreach ($links as $l) $items[] = $el('li', 'mb-2', lang('List Item'), array($l));
        return $el('ul', 'list-unstyled small mb-0', lang('Links'), $items);
    };
    $area_links = array();
    foreach ($practice_areas as $key => $a) $area_links[] = $link($a['title'], '{{page:area_' . $key . '}}', $plain);
    $social = function ($name, $label) use ($el, $icon, $attr) {
        return $el('a', 'link-body-emphasis fs-5', $label, array($icon($name)), array('href' => '#', '_attrs' => array($attr('aria-label', $label))));
    };
    $contact_line = function ($icon_name, $child, $name) use ($el, $icon) {
        return $el('p', 'd-flex align-items-start gap-2 small mb-2', $name, array($icon($icon_name, 'text-primary'), $child));
    };

    return $el('footer', 'mt-auto pt-5 pb-4 bg-body text-body', lang('Site Footer'), array(
        $el('div', 'container', lang('Footer Container'), array(
            $el('div', 'row g-4', lang('Footer Columns'), array(
                $el('div', 'col-12 col-lg-4', lang('About the Firm'), array(
                    $el('a', 'd-inline-flex align-items-center gap-2 fw-semibold fs-4 mb-3 ' . $plain, lang('Brand'), array(
                        $icon('bi-bank', 'text-primary'),
                        $span('{{site_name}}', '', lang('Name')),
                    ), array('href' => '{{page:home}}')),
                    $para(lang('A law firm for businesses and families: clear advice, agreed fees and a lawyer who answers.'), 'small text-body-secondary mb-3'),
                    $el('div', 'd-flex gap-3', lang('Social Links'), array(
                        $social('bi-linkedin', 'LinkedIn'),
                        $social('bi-twitter-x', 'X'),
                        $social('bi-youtube', 'YouTube'),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Clients'), array(
                    $heading('h2', lang('Clients'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list(array(
                        $link(lang('Book a Consultation'), '{{page:consultation}}', $plain),
                        $link(lang('My Consultations'), '{{page:my_consultations}}', $plain),
                        $link(lang('Inbox'), '{{page:inbox}}', $plain),
                        $link(lang('My Account'), '{{page:my_account}}', $plain),
                        $link(lang('Careers'), '{{page:careers}}', $plain),
                        $link(lang('Help Center'), '{{page:search}}', $plain),
                        $link(lang('Privacy Policy'), '{{page:privacy}}', $plain),
                    )),
                )),
                $el('div', 'col-6 col-md-4 col-lg-2', lang('Footer Links'), array(
                    $heading('h2', lang('Practice Areas'), 'h6 text-uppercase small fw-bold mb-3'),
                    $list($area_links),
                )),
                $el('div', 'col-12 col-md-4 col-lg-4', lang('Contact Details'), array(
                    $heading('h2', lang('Our Office'), 'h6 text-uppercase small fw-bold mb-3'),
                    $contact_line('bi-geo-alt', $span(lang('Sample Street 1, 34000 Istanbul, Türkiye'), '', lang('Text')), lang('Address')),
                    $contact_line('bi-telephone', $link('+90 555 000 00 00', 'tel:+905550000000', $plain), lang('Phone')),
                    $contact_line('bi-envelope', $link('office@example.com', 'mailto:office@example.com', $plain . ' text-break'), lang('Email')),
                    $hours('row small mt-3 mb-0'),
                )),
            )),
            $el('div', 'd-flex flex-column flex-md-row justify-content-between align-items-center gap-3 border-top mt-4 pt-4', lang('Footer Bottom'), array(
                $para('© {{year}} {{site_name}}', 'small text-body-secondary mb-0'),
                $para(lang('The information on this site is general and is not legal advice; ask us about your own matter.'), 'small text-body-secondary text-md-end mb-0'),
            )),
        )),
    ), array('_attrs' => array($attr('data-bs-theme', 'dark'))));
};

// The consultation band: a full-width band in the palette's soft colour
// with the way to ask for a consultation. Shared, so every page that ends
// with it carries the same offer.
$consult_band = function () use ($el, $heading, $para, $link, $container, $row, $col) {
    return $el('section', 'py-5 bg-primary-subtle', lang('Call to Action'), array(
        $container(array(
            $row(array(
                $col(array(
                    $heading('h2', lang('Tell us about your matter'), 'h3 fw-semibold mb-2'),
                    $para(lang('Describe it in a few lines; a lawyer replies within one working day with a time for the first consultation.'), 'text-body-secondary mb-0'),
                ), '', '8'),
                $col(array(
                    $el('div', 'd-flex flex-wrap gap-2 justify-content-lg-end', lang('Buttons'), array(
                        $link(lang('Book a Consultation'), '{{page:consultation}}', 'btn btn-primary btn-lg px-4'),
                        $link(lang('Call us'), 'tel:+905550000000', 'btn btn-outline-primary btn-lg px-4'),
                    )),
                ), '', '4'),
            ), '4', '', 'align-items-center'),
        )),
    ));
};

// A lawyer's card (the home, lawyers and area pages): portrait, name, role,
// areas, a line about them, and a link that opens the consultation form
// with them chosen.
$lawyer_card = function ($key) use ($el, $heading, $para, $link, $image, $span, $col, $attr, $portrait, $lawyers, $practice_areas, $consultation_query) {
    $l = $lawyers[$key];
    $area_names = array();
    foreach ($l['areas'] as $a) $area_names[] = $practice_areas[$a]['title'];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm plw-lift', lang('Lawyer Card'), array(
            $image($portrait($l['name']), $l['name'], 'card-img-top object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $heading('h3', $l['name'], 'h5 fw-semibold mb-1'),
                $para($l['role'], 'small text-primary fw-semibold mb-2'),
                $span(implode(' · ', $area_names), 'd-block small text-body-secondary mb-3', lang('Practice Areas')),
                $para($l['bio'], 'small mb-3'),
                $link(lang('Ask for a consultation'), '{{page:consultation}}?' . $consultation_query(array('lawyer' => $l['name'] . ' · ' . $l['role'])),
                    'mt-auto small fw-semibold link-primary text-decoration-none'),
            )),
        )),
    ), '6', '4');
};

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
        $para($eyebrow, 'plw-eyebrow text-uppercase fw-semibold text-primary mb-1'),
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
$faq = function ($n, $question, $answer, $topics = '', $parent = 'lw-faq') use ($el, $para, $attr, $searchable) {
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
$textarea = function ($id, $name, $rows, $required = true, $cf = array()) use ($el, $attr) {
    $attrs = array($attr('name', $name), $attr('rows', (string)$rows));
    if ($required) $attrs[] = $attr('required', '');
    $props = array('id' => $id, '_attrs' => $attrs);
    if ($cf) $props['_cf'] = $cf;
    return $el('textarea', 'form-control', lang('Field'), array(), $props);
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
// replies, a post's comments), in the reading column: the page's Comments
// widget, which reads the record the page's view above it shows.
$comments_section = function ($name, $widget_key) use ($el, $container, $row, $col, $widget) {
    return $el('section', 'pb-5', $name, array(
        $container(array(
            $row(array(
                $col(array($widget($widget_key)), '', '8'),
            ), '', 'center'),
        )),
    ));
};

// A Messages node: the notices and errors of the form it sits in.
$messages = function ($class = '') {
    return array('type' => 'content', 'props' => array('contentType' => 'messages', 'cssClass' => $class), 'children' => array());
};

// A thread of the Comments widget: the comments in the widget's order, each
// a card with its writer, badges, date, the comment it answers where the
// thread stops going deeper, its words, its file and its links; the page
// links; the form (its notices first, the comment being answered, the
// stars while the page asks for them, the words, the name for whoever types
// one, the file, the staff's publish options, the box to follow the thread,
// the CAPTCHA); the box to follow or stop following; the staff's Stop and
// Allow buttons. A part under a flag shows only while it holds for the
// visitor. $w: the words (title, empty, heading, label, sign_in, submit,
// watch_box, follow, unfollow, close, reopen), reply (each comment can be
// answered and shared), watch (the boxes to follow the thread).
$comment_thread = function ($w) use ($el, $heading, $para, $link, $span, $attr, $bind, $show_when, $loop, $widget_root, $messages) {
    $actions = array();
    if ($w['reply']) {
        $actions[] = $link(lang('Reply'), '#', 'link-primary', array(), array('_bindings' => array('action' => 'comment_reply')));
        $actions[] = $link(lang('Share'), '#', 'link-secondary', array(), array('_bindings' => array('action' => 'comment_share')));
    }
    // Bound to the comment's edit screen, so the list adds no pencil of its own.
    $actions[] = $link(lang('Edit'), '#', 'link-secondary', array(), array('_bindings' => array('href' => '__comment_edit_url', 'eo_visible_if' => 'comment_can_edit')));

    $badges = array(
        $span(lang('Jane Cooper'), 'fw-semibold', lang('Name'), $bind('__comment_name')),
        $span('jane', 'small text-body-secondary', lang('User Name'), array('_bindings' => array('text' => '__comment_staff_name', 'eo_visible_if' => 'is_staff'))),
        $span(lang('Badge'), 'badge rounded-pill text-bg-secondary', lang('Badge'), array('_bindings' => array('text' => '__comment_badge', 'eo_visible_if' => 'comment_has_badge'))),
    );
    if ($w['reply']) {
        $badges[] = $span(lang('Featured'), 'badge rounded-pill text-bg-primary', lang('Featured'), $show_when('comment_is_featured'));
    }
    $card = $el('div', 'card border-0 bg-body-tertiary rounded-4 flex-grow-1', lang('Comment Card'), array(
        $el('div', 'card-body p-4', lang('Card Body'), array(
            $el('p', 'small fw-semibold text-danger-emphasis mb-2 pg-hide-if-empty', lang('Comment Status'), array(),
                array('text' => lang('Awaiting approval'), '_bindings' => array('text' => '__comment_status', 'eo_visible_if' => 'comment_is_pending'))),
            $el('div', 'text-warning mb-1', lang('Stars'), pg_sw_part_comment_stars(), $show_when('comment_has_rating')),
            $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('Name Line'), $badges),
            $span(lang('2 days ago'), 'd-block small text-body-secondary pg-hide-if-empty', lang('Time'), $bind('__comment_date')),
            $el('blockquote', 'border-start border-3 ps-3 my-2 small text-body-secondary', lang('Quote'), array(
                $el('p', 'mb-1', lang('In reply to'), array(
                    $span(lang('In reply to'), 'me-1', lang('Label')),
                    $link(lang('Jane Cooper'), '#', 'fw-semibold link-body-emphasis', array(), array('_bindings' => array('href' => '__comment_parent_url', 'text' => '__comment_parent_name'))),
                )),
                $el('p', 'mb-0 pg-hide-if-empty', lang('Quoted text'), array(), array('text' => lang('Quoted text'), '_bindings' => array('text' => '__comment_quote'))),
            ), $show_when('comment_has_quote')),
            $el('div', 'mt-2', lang('Message'), array(), array('text' => lang('Comment text'), '_bindings' => array('text' => '__comment_message_html'))),
            $el('p', 'small mt-3 mb-0', lang('Attachment'), array(
                $link('file.pdf', '#', 'me-2', array(), array('target' => '_blank', 'rel' => 'noopener', '_bindings' => array('href' => '__comment_file_url', 'text' => '__comment_file_name'))),
                $span('12 KB', 'text-body-secondary', lang('Value'), $bind('__comment_file_size')),
            ), $show_when('comment_has_file')),
            $el('div', 'd-flex flex-wrap gap-3 mt-3 small', lang('Comment Actions'), $actions),
        )),
    ));

    $label = function ($for, $text, $class = 'form-label') use ($el, $attr) {
        return $el('label', $class, lang('Label'), array(), array('text' => $text, '_attrs' => array($attr('for', $for))));
    };
    $control = function ($tag, $class, $id, $value, $attrs = array()) use ($el) {
        return $el($tag, $class, lang('Field'), array(), array('id' => $id, '_attrs' => $attrs, '_bindings' => array('value' => $value)));
    };
    $fields = array($messages());
    if ($w['reply']) {
        // The comment being answered. Reply shows the box by its hidden
        // attribute, so it takes no display utility (d-flex would keep it
        // on screen, empty).
        $fields[] = $el('div', 'alert alert-secondary py-2 small', lang('Replying To'), array(
            $span(lang('Replying to'), 'me-1', lang('Text')),
            $el('strong', '', lang('Name'), array(), array('text' => lang('Jane Cooper'), '_bindings' => array('text' => '__reply_to_name'))),
            $link(lang('Cancel'), '#', 'ms-3 alert-link', array(), array('_bindings' => array('action' => 'comment_reply_cancel'))),
        ), $show_when('is_replying'));
    }
    $fields[] = $el('div', 'mb-3', lang('Rating'), array(
        $el('div', 'form-label', lang('Label'), array(), array('text' => lang('Rating'))),
        $el('div', '', lang('Stars'), array(), array('_bindings' => array('section' => 'comment_rating_input'))),
    ), $show_when('rating_on'));
    $fields[] = $el('div', 'mb-3', lang('Message'), array(
        $label('comment_message', $w['label']),
        $control('textarea', 'form-control', 'comment_message', 'comment_message', array($attr('rows', '5'))),
    ));
    $fields[] = $el('div', 'mb-3', lang('Your Name'), array(
        $label('comment_name', lang('Your Name')),
        $control('input', 'form-control', 'comment_name', 'comment_name', array($attr('type', 'text'))),
    ), $show_when('name_is_typed'));
    $fields[] = $el('div', 'mb-3', lang('Your Name'), array(
        $label('comment_name_type', lang('Your Name')),
        $control('select', 'form-select', 'comment_name_type', 'comment_name_type'),
    ), $show_when('name_is_chosen'));
    $fields[] = $el('p', 'mb-3 small', lang('Posting as'), array(
        $span(lang('Posting as'), 'me-1', lang('Label')),
        $el('strong', '', lang('Name'), array(), array('text' => lang('Jane Cooper'), '_bindings' => array('text' => '__comment_author_name'))),
    ), $show_when('name_is_fixed'));
    $fields[] = $el('div', 'mb-3', lang('Attach a File'), array(
        $label('comment_file', lang('Attach a File')),
        $control('input', 'form-control', 'comment_file', 'comment_file', array($attr('type', 'file'))),
    ), $show_when('files_on'));
    $fields[] = $el('div', 'mb-3 small', lang('Publish'), array(), array('_bindings' => array('section' => 'comment_publish')));
    if ($w['watch']) {
        $fields[] = $el('div', 'form-check mb-3', lang('Watch'), array(
            $control('input', 'form-check-input', 'comment_watcher', 'comment_watcher', array($attr('type', 'checkbox'))),
            $label('comment_watcher', $w['watch_box'], 'form-check-label'),
        ), $show_when('watching_on'));
    }
    $fields[] = $el('div', 'mb-3', lang('CAPTCHA'), array(), array('_bindings' => array('section' => 'comment_captcha')));
    $fields[] = $el('div', '', lang('Send Button'), array(
        $el('button', 'btn btn-primary rounded-pill px-4', lang('Send Button'), array(), array('text' => $w['submit'], '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => 'comment_submit'))),
    ));

    $small_button = function ($text, $action) use ($el, $attr) {
        return $el('button', 'btn btn-sm btn-outline-secondary rounded-pill', lang('Send Button'), array(), array('text' => $text, '_attrs' => array($attr('type', 'submit')), '_bindings' => array('action' => $action)));
    };

    $children = array(
        $heading('h2', $w['title'], 'h4 fw-bold mb-3', array('_bindings' => array('text' => '__comments_title', 'eo_visible_if' => 'has_comments'))),
        $el('div', 'pg-hide-if-empty', lang('Comment List'), array(
            $loop(array($el('div', 'd-flex mb-3', lang('Comment'), array(
                $el('div', 'flex-shrink-0 border-start border-2 ms-2 me-3', lang('Reply Indent'), array(), $show_when('comment_is_reply')),
                $card,
            )))),
            $el('div', 'mt-3', lang('Pagination'), array(), array('_bindings' => array('section' => 'comment_pagination'))),
        )),
        $para($w['empty'], 'text-body-secondary', $show_when('no_comments')),
        $el('div', 'mt-4', lang('Add a comment'), array(
            $heading('h3', $w['heading'], 'h5 fw-bold mb-2', $show_when('comments_open')),
            $el('p', 'small text-body-secondary mb-3 pg-hide-if-empty', lang('Text'), array(), array('_bindings' => array('text' => '__comments_intro', 'eo_visible_if' => 'comments_open'))),
            $el('div', 'alert alert-secondary', lang('Closed'), array(), array('_bindings' => array('text' => '__comments_closed_message', 'eo_visible_if' => 'comments_closed'))),
            $el('div', 'alert alert-secondary d-flex flex-wrap align-items-center gap-2', lang('Sign in'), array(
                $span($w['sign_in'], '', lang('Text')),
                $link(lang('Sign in'), '#', 'alert-link', array(), array('rel' => 'nofollow', '_bindings' => array('href' => '__login_url'))),
            ), $show_when('must_sign_in')),
            $el('form', 'card card-body border-0 bg-body-tertiary rounded-4 p-4', lang('Comment Form'), $fields, array('_bindings' => array('action' => 'comment_form', 'eo_visible_if' => 'can_comment'))),
        )),
    );
    if ($w['watch']) {
        // Following the thread: how many follow it, the visitor's own
        // button, and the staff's list of who follows.
        $children[] = $el('div', 'small border-top pt-3 mt-4 d-flex flex-column gap-2 pg-hide-if-empty', lang('Watch'), array(
            $el('p', 'mb-0 pg-hide-if-empty', lang('Text'), array(), $bind('__watcher_count_text')),
            $el('form', 'd-flex flex-wrap align-items-center gap-2', lang('Watch Form'), array(
                $messages('w-100'),
                $small_button($w['follow'], 'comment_watch'),
                $small_button($w['unfollow'], 'comment_unwatch'),
            ), array('_bindings' => array('action' => 'comment_watch_form'))),
            $el('div', '', lang('Watchers'), array(), array('_bindings' => array('section' => 'comment_watchers'))),
        ), $show_when('watching_on'));
    }
    $children[] = $el('div', 'd-flex justify-content-end mt-3', lang('Staff Controls'), array(
        $el('form', 'd-flex gap-2', lang('Open or Close'), array(
            $small_button($w['close'], 'comment_close'),
            $small_button($w['reopen'], 'comment_reopen'),
        ), array('_bindings' => array('action' => 'comment_allow_new_form'))),
    ), $show_when('is_staff'));

    return $widget_root($children);
};

// ── Staff, newsletter and blog parts ────────────────────────────────────

// The staff pages' own menu, under the header: one shared component.
$staff_bar = function () use ($el, $link, $span) {
    $item = function ($key, $text) use ($link) {
        return $link($text, '{{page:' . $key . '}}', 'nav-link px-0');
    };
    return $el('div', 'border-bottom small', lang('Staff Bar'), array(
        $el('div', 'container d-flex flex-wrap align-items-center gap-4 py-2', lang('Staff Bar Container'), array(
            $span(lang('Staff'), 'badge rounded-pill text-bg-primary', lang('Staff Badge')),
            $el('nav', 'nav nav-underline gap-4', lang('Staff Links'), array(
                $item('staff', lang('Staff Area')),
                $item('staff_consultations', lang('All Consultations')),
                $item('staff_applications', lang('Applications')),
                $item('blog_new', lang('New Insight')),
                $item('staff_entry', lang('Add to the Staff Directory')),
                $item('inbox', lang('Inbox')),
            ), array('smartActive' => true)),
        )),
    ));
};

// A page of the staff area: the staff bar under the header, no newsletter.
$staff_page = function ($main) use ($root, $shared, $el) {
    return $root(array(
        $shared('account_bar'),
        $shared('header'),
        $shared('staff_bar'),
        $el('main', '', lang('Main Content'), $main),
        $shared('footer'),
    ));
};

// The newsletter's e-mail field and button, in one line. The address is the
// contact's, so the sign-up becomes a contact of the newsletter's group.
$newsletter_line = function ($id) use ($el, $attr, $submit) {
    return $el('div', 'input-group mb-3', lang('E-mail and Button'), array(
        $el('label', 'visually-hidden', lang('Label'), array(), array('text' => lang('Email'), '_attrs' => array($attr('for', $id)))),
        $el('input', 'form-control', lang('Field'), array(), array('id' => $id, '_cf' => array('contact_field' => 'email_address'), '_attrs' => array(
            $attr('type', 'email'), $attr('name', 'email'), $attr('required', ''), $attr('autocomplete', 'email'),
            $attr('placeholder', lang('Your e-mail address')),
        ))),
        $submit(lang('Subscribe')),
    ));
};
$newsletter_consent = function ($id) use ($agree) {
    return $agree($id, 'consent', lang('I would like to receive the firm\'s legal updates by e-mail. I can unsubscribe at any time.'));
};

// A file field; what is sent is kept in the template folder named. The
// firm's forms take documents (a contract, a court letter, a CV), so the
// default accepts PDFs, office files and pictures; one that takes only
// pictures is a collection's image kind.
$file = function ($id, $name, $folder, $accept = '.pdf,.doc,.docx,.odt,.jpg,.jpeg,.png') use ($el, $attr) {
    return $el('input', 'form-control', '', array(), array('id' => $id, '_attrs' => array(
        $attr('type', 'file'), $attr('name', $name), $attr('accept', $accept),
    ), '_cf' => array('upload_folder_id' => '{{folder:' . $folder . '}}')
            + ($accept === 'image/*' ? array('kind' => 'image') : array())));
};


// A picture that leaves with its row when the record has none (the
// pg-hide-if-empty row of the form widgets).
$optional_image = function ($token, $alt_token, $class, $frame, $sample, $alt) use ($el, $image) {
    return $el('div', 'pg-hide-if-empty', lang('Picture'), array(
        $el('span', 'pg-field-value d-block ' . $frame, lang('Picture Frame'), array(
            $image($sample, $alt, $class, array('_bindings' => array('src' => $token, 'alt' => $alt_token))),
        )),
    ));
};

// A blog post card of the lists (the blog page and the home page).
$post_card = function () use ($el, $para, $span, $link, $col, $optional_image, $bind) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden', lang('Post Card'), array(
            $optional_image('cover_image', 'title', 'card-img-top object-fit-cover', 'ratio ratio-16x9 bg-body-tertiary',
                'https://picsum.photos/seed/pinegrap-law-post/800/450', lang('Cover Image')),
            $el('div', 'card-body d-flex flex-column', lang('Card Body'), array(
                $para(lang('Date'), 'small text-uppercase fw-semibold text-primary mb-2', $bind('submitted_date_and_time')),
                $el('h3', 'h5 card-title fw-bold position-static', lang('Post Title'), array(
                    $link(lang('What a shareholders agreement should say'), '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $para(lang('A short summary of the post. It is what makes a visitor open it, so two sentences are enough.'),
                    'card-text small text-body-secondary', $bind('summary')),
                $span(lang('Read More'), 'mt-auto small fw-semibold text-primary', lang('Read More')),
            )),
        )),
    ), '6', '4');
};


// Summarize the post with AI: with the visitor's own assistant (each link
// opens it with a question about the post's address) or with the browser's
// built-in model, whose button the browser shows only where it can
// summarize and whose points come into the box below. Shown for a post
// anybody may open.
$ai_summary = function () use ($el, $link, $icon, $span, $attr, $bind, $show_when) {
    $assistant = function ($text, $token) use ($link, $attr) {
        return $link($text, '#', 'btn btn-sm btn-outline-primary rounded-pill', array($attr('rel', 'noopener nofollow')),
            array('target' => '_blank', '_bindings' => array('href' => $token)));
    };
    return $el('aside', 'p-3 rounded-4 bg-body-tertiary mb-4', lang('AI Summary'), array(
        $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('AI Buttons'), array(
            $icon('bi-stars', 'text-primary'),
            $span(lang('Summarize this post with AI'), 'small fw-semibold me-1', lang('Label')),
            $assistant('ChatGPT', '__ai_chatgpt_url'),
            $assistant('Claude', '__ai_claude_url'),
            $assistant('Perplexity', '__ai_perplexity_url'),
            $assistant('Google AI', '__ai_google_url'),
            $el('button', 'btn btn-sm btn-primary rounded-pill', lang('Summarize Here Button'), array(), array(
                'text' => lang('Summarize here'), '_bindings' => array('action' => 'ai_summarize'))),
        )),
        $el('div', 'small mt-3', lang('Summary Box'), array(), array_merge(
            array('text' => lang('The main points of the post, written by the visitor\'s browser.')), $bind('__ai_summary'))),
    ), $show_when('has_ai_links'));
};

