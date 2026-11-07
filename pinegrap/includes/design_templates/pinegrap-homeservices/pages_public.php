<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Home Services", its public pages: home, about, the
 * service areas, the price guide, the questions page and the customer
 * reviews. Included after parts.php in the same scope (its builders and
 * the company's data); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($company, $categories, $services,
 * $service_prices, $call_out, $areas, $how_it_works, $why_us, $figures,
 * $testimonials), so a change made there reaches every page here. No page
 * here links a shop page: the template stands on a site without the shop.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$public_pages = array();

// A picture in a rounded frame of the given ratio, loaded lazily unless it
// is the first thing a page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-3 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'phs-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'phs-display h2 mb-3'),
    );
    $last = count($texts) - 1;
    foreach (array_values($texts) as $n => $t) {
        $words[] = $para($t, 'text-body-secondary ' . ($n === $last && empty($more) ? 'mb-0' : 'mb-3'));
    }
    return $el('section', $class, $title, array(
        $container(array(
            $row(array(
                $col(array_merge($words, $more), '', '6', '', '12', $flip ? 'order-lg-last' : ''),
                $col(array($framed($picture, $alt)), '', '6'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// A tile with an icon, a title and a line: why customers choose the
// company and what it promises.
$feature_tile = function ($icon_name, $title, $text, $lg = '3') use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-3 bg-body border shadow-sm', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'phs-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        )),
    ), '6', $lg);
};

// The company in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'phs-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// A band that sends a question to the contact page.
$ask_band = function ($title, $text) use ($el, $heading, $para, $link, $container) {
    return $el('section', 'py-5', lang('Write to Us'), array(
        $container(array(
            $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                $el('div', '', lang('Text'), array(
                    $heading('h2', $title, 'phs-display h4 mb-1'),
                    $para($text, 'text-body-secondary mb-0'),
                )),
                $link(lang('Contact Us'), '{{page:contact}}', 'btn btn-primary px-4 text-nowrap'),
            )),
        )),
    ));
};

// How a job goes, as numbered steps in a list.
$how_steps = function () use ($el, $step, $how_it_works) {
    $items = array();
    foreach ($how_it_works as $n => $s) $items[] = $step($n + 1, $s['title'], $s['text']);
    return $el('ol', 'list-unstyled mb-4', lang('Steps'), $items);
};

// The cards of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$category_cols = array();
foreach (array_keys($categories) as $key) $category_cols[] = $category_card($key);
$area_cols = array();
foreach (array_keys($areas) as $key) $area_cols[] = $area_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);
$call_out_text = $money($call_out);
$core_names = array();
$outer_names = array();
foreach ($areas as $a) {
    if ($a['core']) $core_names[] = $a['name'];
    else $outer_names[] = $a['name'];
}
$emergency_link = 'tel:' . preg_replace('/[^0-9+]/', '', $company['emergency']);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('Cleaning, plumbing, electrical work and repairs at home, with the price agreed first and a twelve-month guarantee.'),
    'tree'             => $page(array(
        // The way in: the company in a sentence, the two ways on, the
        // emergency line and a photo of a technician at work.
        $el('section', 'py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('One call for every job at home'), 'phs-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Your home, looked after'), 'phs-display display-4 mb-3'),
                        $para($company['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Book a Visit'), '{{page:book}}', 'btn btn-primary btn-lg px-4'),
                            $link(lang('Get a Free Estimate'), '{{page:estimate}}', 'btn btn-outline-primary btn-lg px-4'),
                        )),
                        $el('p', 'd-flex flex-wrap align-items-center gap-2 small mt-4 mb-0', lang('Emergency Line'), array(
                            $icon('bi-telephone-forward', 'text-primary'),
                            $span(lang('Burst pipe or no power? Our emergency line:'), 'text-body-secondary', lang('Text')),
                            $link($company['emergency'], $emergency_link, 'fw-semibold link-primary text-nowrap'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('A technician fixing the pipes under a kitchen sink'), '4x3', false),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Service Categories'), array(
            $container(array(
                $section_header(lang('What we do'), lang('Every job at home, one team'), lang('All Services'), '{{page:services}}'),
                $row($category_cols, '4'),
            )),
        ), $searchable('hs-home-categories', lang('cleaner, plumber, electrician, handyman, repairs'))),

        // The featured services, read from the Services collection.
        $el('section', 'py-5 bg-body-tertiary', lang('Featured Services'), array(
            $container(array(
                $section_header(lang('Booked most often'), lang('Popular services'), lang('See the prices'), '{{page:prices}}'),
                $widget('home_services'),
            )),
        )),

        $el('section', 'py-5', lang('How It Works'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Simple from start to finish'), 'phs-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('How it works'), 'phs-display h2 mb-4'),
                        $how_steps(),
                        $link(lang('Book a Visit'), '{{page:book}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                    $col(array($framed($photo('home-how', 1200, 900), lang('A technician showing a customer the work on a tablet'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('hs-how-it-works', lang('how to book, steps, payment, arrival window'))),

        $el('section', 'py-5 bg-body-tertiary', lang('Why Choose Us'), array(
            $container(array(
                $section_header(lang('Our promise'), lang('Why customers choose us'), lang('About Us'), '{{page:about}}'),
                $row($why_tiles, '4'),
            )),
        ), $searchable('hs-why-us', lang('insured, vetted, guarantee, fixed price, punctual'))),

        // The team, read from the Team collection.
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $section_header(lang('The people who come'), lang('Meet the team'), lang('Our Team'), '{{page:team}}'),
                $widget('home_team'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Service Areas'), array(
            $container(array(
                $section_header(lang('Where we work'), lang('Across the city and around it'), lang('Service Areas'), '{{page:areas}}'),
                $para(sprintf(lang('No call-out charge in %s.'), implode(', ', $core_names)), 'text-body-secondary mb-0'),
            )),
        )),

        // What customers say: sample words for the company to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings. Each service page carries its own
        // reviews.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our customers'), lang('What customers say'), lang('Customer Reviews'), '{{page:reviews}}'),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('From the Blog'), array(
            $container(array(
                $section_header(lang('Home Care Tips'), lang('Keep your home in good shape'), lang('All tips'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-hand-thumbs-up', 'title' => lang('Done right the first time'), 'text' => lang('We bring the parts a job is likely to need, so most jobs end in one visit.')),
    array('icon' => 'bi-house-heart', 'title' => lang('Treat every home as ours'), 'text' => lang('Shoe covers on, dust sheets down and the room left cleaner than we found it.')),
    array('icon' => 'bi-mortarboard', 'title' => lang('Always learning'), 'text' => lang('Every technician trains each year on new materials, appliances and safety rules.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text'], '4');

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A home services company started by a plumber and a cleaner: one team for every job, prices agreed first.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $company['tagline'] . '.'),
        $story_band('hs-our-story', lang('Our story'), lang('A plumber, a cleaner and one phone number'),
            array($company['story'], $company['licence'] . '.'),
            $photo('about-team', 1200, 900), lang('The team in front of the company vans'), false, array(),
            lang('history, founders, company, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('How we work'), lang('What we care about'), lang('Our Team'), '{{page:team}}'),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('hs-values', lang('values, quality, tidy, training, respect'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Why Choose Us'), array(
            $container(array(
                $section_header(lang('Our promise'), lang('Why customers choose us')),
                $row($why_tiles, '4'),
            )),
        ), $searchable('hs-about-why', lang('insured, vetted, guarantee, fixed price, punctual'))),
        $ask_band(lang('A question about a job?'), lang('Write to us with a few words and a photo; the right technician answers, usually the same day.')),
    )),
);

// ── Service areas ───────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'areas',
    'name'             => lang('service-areas'),
    'folder'           => 'public',
    'title'            => lang('Service Areas'),
    'meta_description' => lang('The parts of the city we visit, on which days, and where a call-out charge applies.'),
    'tree'             => $page(array(
        $page_header(lang('Service Areas'), lang('Where our technicians go, on which days, and what a visit costs to get there.')),
        $el('section', 'py-5', lang('Areas'), array(
            $container(array(
                $row($area_cols, '4'),
            )),
        ), $searchable('hs-areas', lang('district, neighbourhood, location, coverage, do you come to'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Call-out Charge'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('When a call-out charge applies'), 'phs-display h3 mb-3'),
                        $check_list(array(
                            sprintf(lang('No call-out charge in %s.'), implode(', ', $core_names)),
                            sprintf(lang('A call-out charge of %1$s per visit in %2$s.'), $call_out_text, implode(', ', $outer_names)),
                            lang('The charge is added once per visit, however many jobs we do while we are there.'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('Outside these areas?'), 'phs-display h3 mb-3'),
                        $para(lang('We sometimes travel further for a larger job. Ask for an estimate and tell us where you are; we answer within a working day.'), 'text-body-secondary mb-3'),
                        $link(lang('Get an Estimate'), '{{page:estimate}}', 'btn btn-outline-primary px-4'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('hs-call-out', lang('call-out fee, travel charge, distance, outside the city'))),
    )),
);

// ── Price guide ─────────────────────────────────────────────────────────

// A table per category: a row for each sample service, its starting price
// and a button opening the visit booking with the service chosen.
$price_tables = array();
foreach ($categories as $ckey => $c) {
    $rows = array();
    foreach ($services as $key => $s) {
        if ($s['category'] !== $ckey) continue;
        $rows[] = $el('tr', '', $s['name'], array(
            $el('th', 'fw-semibold', lang('Service Name'), array(), array('text' => $s['name'], '_attrs' => array($attr('scope', 'row')))),
            $el('td', 'text-nowrap', lang('Duration'), array(), array('text' => $s['duration'])),
            $el('td', 'phs-price fw-semibold text-nowrap', lang('Price'), array(), array('text' => $price_text($key))),
            $el('td', 'text-end', lang('Action'), array(
                $link(lang('Book a visit for this service'), '{{page:book}}?' . $query(array('service' => $s['name'])), 'btn btn-sm btn-outline-primary px-3 text-nowrap'),
            )),
        ));
    }
    $price_tables[] = $el('div', 'mb-5', $c['title'], array(
        $el('div', 'd-flex align-items-center gap-2 mb-3', lang('Category Heading'), array(
            $icon($c['icon'], 'fs-4 text-primary'),
            $heading('h2', $c['title'], 'phs-display h4 mb-0'),
        )),
        $el('div', 'table-responsive', lang('Table'), array(
            $el('table', 'table align-middle mb-0', lang('Price Table'), array(
                $el('thead', '', lang('Table Head'), array(
                    $el('tr', '', lang('Row'), array(
                        $el('th', '', lang('Service Name'), array(), array('text' => lang('Service Name'), '_attrs' => array($attr('scope', 'col')))),
                        $el('th', '', lang('Duration'), array(), array('text' => lang('How long'), '_attrs' => array($attr('scope', 'col')))),
                        $el('th', '', lang('Price'), array(), array('text' => lang('Starting price'), '_attrs' => array($attr('scope', 'col')))),
                        $el('th', '', lang('Action'), array($span(lang('Book a visit for this service'), 'visually-hidden', lang('Text'))), array('_attrs' => array($attr('scope', 'col')))),
                    )),
                )),
                $el('tbody', '', lang('Table Body'), $rows),
            )),
        )),
    ), array('id' => 'hs-prices-' . $ckey));
}

$public_pages[] = array(
    'key'              => 'prices',
    'name'             => lang('prices'),
    'folder'           => 'public',
    'title'            => lang('Prices'),
    'meta_description' => lang('Starting prices for cleaning, plumbing, electrical work and repairs, and how the final price is agreed.'),
    'tree'             => $page(array(
        $page_header(lang('Prices'), lang('What our services start from. We confirm the exact price before any work begins.')),
        $el('section', 'py-5', lang('Price Guide'), array(
            $container(array(
                $row(array(
                    $col($price_tables, '', '10'),
                ), '', 'center'),
            )),
        ), $searchable('hs-price-guide', lang('price, cost, how much, rate, hourly, fee'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How Prices Work'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('How the price is set'), 'phs-display h3 mb-3'),
                        $check_list(array(
                            lang('A price per visit covers the work the service describes; we confirm it when we confirm your visit.'),
                            lang('A price per hour is charged for the time on site, one hour at least, then by the half hour.'),
                            lang('Parts beyond small fixings are priced separately and agreed with you before they are fitted.'),
                            sprintf(lang('A call-out charge of %1$s per visit in %2$s.'), $call_out_text, implode(', ', $outer_names)),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('A bigger or unusual job?'), 'phs-display h3 mb-3'),
                        $para(lang('Send us a few photos and a line about the job: we reply with a written estimate, free and with no obligation.'), 'text-body-secondary mb-3'),
                        $link(lang('Get a Free Estimate'), '{{page:estimate}}', 'btn btn-primary px-4'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('hs-how-prices-work', lang('parts, materials, hourly rate, minimum charge, quote'))),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: booking, on the day, prices and payment. Each question
// is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'phs-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about booking a visit, arrival times, emergencies, prices, parts, payment and our guarantee.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Booking a visit, the day itself, prices and payment. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('hs-faq-booking', lang('Booking a visit'), array(
                            array(lang('Is my visit booked as soon as I send the form?'), lang('Not yet: we check the day and the time window and confirm within a working day. Your visit is booked when it reads "Confirmed" under My Visits.'), lang('confirmation, request, availability, appointment')),
                            array(lang('How do I follow my visit or my estimate?'), lang('Sending a form opens an account for you. Under My Account you see the state of each visit and estimate and can write to us there.'), lang('my account, status, follow, message')),
                            array(lang('Should I book a visit or ask for an estimate?'), lang('Book a visit when the job is one of our listed services. Ask for an estimate for a larger or unusual job, or when you want a price in writing first.'), lang('quote, estimate, price first, large job')),
                            array(lang('What about emergencies?'), sprintf(lang('For a burst pipe, a flood or a home without power, call our emergency line on %s; do not use the forms.'), $company['emergency']), lang('emergency, urgent, flood, leak, power cut, out of hours')),
                        )),
                        $faq_group('hs-faq-visit', lang('On the day'), array(
                            array(lang('When will the technician arrive?'), lang('Within the time window you chose. If we are running late, the technician calls you before the window ends.'), lang('arrival time, late, window, punctual')),
                            array(lang('Do I need to be at home?'), lang('Not always: you can leave a key for us, and say so in the booking. We send you a message when the work is done.'), lang('key, access, not at home, absent')),
                            array(lang('Do you bring your own products and tools?'), lang('Yes: our cleaners bring their products and equipment, and our technicians their tools and the common parts.'), lang('equipment, products, tools, supplies')),
                            array(lang('Who comes to my home?'), lang('A trained and insured member of our own team; each one is on our team page.'), lang('technician, cleaner, staff, vetted, insured')),
                        )),
                        $faq_group('hs-faq-payment', lang('Prices and payment'), array(
                            array(lang('How much will it cost?'), lang('Our price guide shows what each service starts from; we confirm the price before any work begins, and it does not change halfway.'), lang('price, cost, how much, fixed price')),
                            array(lang('Is there a call-out charge?'), sprintf(lang('Only outside the centre: %1$s per visit in %2$s.'), $call_out_text, implode(', ', $outer_names)), lang('call-out fee, travel charge')),
                            array(lang('When and how do I pay?'), lang('Once the work is done and you are happy with it, by card or bank transfer. We send the invoice by e-mail.'), lang('payment, card, bank transfer, invoice')),
                            array(lang('What if something goes wrong later?'), $company['licence'] . '.', lang('guarantee, warranty, insurance, come back')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// ── Customer reviews ────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'reviews',
    'name'             => lang('reviews'),
    'folder'           => 'public',
    'title'            => lang('Customer Reviews'),
    'meta_description' => lang('What our customers say about our cleaning, plumbing, electrical and repair work.'),
    'tree'             => $page(array(
        $page_header(lang('Customer Reviews'), lang('What our customers say. Every service page carries the reviews of that service, with their stars.')),
        // Sample words for the company to replace, then its own Google
        // reviews, which show once Google Reviews is set up under Site
        // Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $row($testimonial_cols, '4'),
            )),
        ), $searchable('hs-reviews', lang('reviews, ratings, testimonials, feedback, stars'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Google Reviews'), array(
            $container(array(
                $widget('google_reviews'),
            )),
        )),
        $el('section', 'py-5', lang('Leave a Review'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Had a job done by us?'), 'phs-display h4 mb-1'),
                        $para(lang('Open the service you had on our service list and leave your review and stars at the foot of its page.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Our Services'), '{{page:services}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        ), $searchable('hs-leave-review', lang('write a review, rate, feedback'))),
    )),
);

// The home page's lists: its featured services (home_services) and team
// (home_team) come with the services, its tips (home_posts) and the Google
// reviews (google_reviews) with the standard pages; this part places no
// widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
