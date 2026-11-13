<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Car Dealership", its public pages: home, the service
 * centre, finance, about, visit us and the questions buyers ask. Included
 * after parts.php in the same scope (its builders and the dealer's data);
 * returns the list of pages. The widgets the home places (home_featured,
 * home_used, home_posts, google_reviews) are built by vehicles.php and
 * standard.php; test_drive_band is the shared component of
 * $test_drive_band.
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

// ── Pieces the pages share ──────────────────────────────────────────────

// The dealer's figures in a row.
$figure_row = function ($class = '') use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pdl-display display-6 fw-bold text-primary mb-1'),
                $para($f['label'], 'small text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $el('div', $class, lang('Figures'), array($row($cols, '4')));
};

// A service card of the service centre: the icon, the work and a line
// about it. A search section of its own, so "tyres" opens the page there.
$service_card = function ($key, $md = '6', $lg = '3') use ($el, $heading, $para, $icon_tile, $col, $searchable, $services) {
    $s = $services[$key];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm', $s['title'], array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $icon_tile($s['icon'], 'mb-3'),
                $heading('h3', $s['title'], 'h5 fw-semibold mb-2'),
                $para($s['text'], 'text-body-secondary mb-0'),
            )),
        ), $searchable('service-' . $key)),
    ), $md, $lg);
};

// A person of the team: the portrait, the name, the role and a line.
$team_card = function ($n) use ($el, $heading, $para, $image, $col, $attr, $portrait, $team) {
    $t = $team[$n];
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm text-center', lang('Team Member'), array(
            $el('div', 'card-body p-4', lang('Card Body'), array(
                $image($portrait($t['name'], 192), $t['name'], 'rounded-circle mb-3',
                    array('width' => '96', 'height' => '96', '_attrs' => array($attr('loading', 'lazy')))),
                $heading('h3', $t['name'], 'h5 fw-semibold mb-1'),
                $para($t['role'], 'small fw-semibold text-primary mb-2'),
                $para($t['bio'], 'small text-body-secondary mb-0'),
            )),
        )),
    ), '6', '4');
};

// One of the dealer's principles: the icon, the title and a line.
$value = function ($icon_name, $title, $text) use ($el, $heading, $para, $icon_tile, $col) {
    return $col(array(
        $el('div', 'h-100', $title, array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'h5 fw-semibold mb-2'),
            $para($text, 'text-body-secondary mb-0'),
        )),
    ), '6', '3');
};

// A way to reach a department on the visit page: the icon, its name, the
// hours and the phone.
$department = function ($icon_name, $title, $text, $when) use ($el, $heading, $para, $icon_tile, $col) {
    return $col(array(
        $el('div', 'd-flex gap-3', $title, array(
            $icon_tile($icon_name, 'flex-shrink-0 align-self-start'),
            $el('div', '', lang('Text'), array(
                $heading('h3', $title, 'h6 fw-bold mb-1'),
                $para($text, 'small text-body-secondary mb-1'),
                $para($when, 'small fw-semibold mb-0'),
            )),
        )),
    ), '', '4');
};

$phone_href = 'tel:' . preg_replace('/[^0-9+]/', '', $dealer['phone']);

$finance_cards = array();
foreach (array_keys($finance_plans) as $key) $finance_cards[] = $finance_card($key);
$testimonial_cards = array();
foreach (array_keys($testimonials) as $n) $testimonial_cards[] = $testimonial_card($n);
$team_cards = array();
foreach (array_keys($team) as $n) $team_cards[] = $team_card($n);

// The dealer's figures as Stats: Counter draws them, in the showroom's
// thin type; they rise one after another on view.
$figure_band = function () use ($el, $heading, $para, $container, $row, $col, $fx, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $heading('div', $f['value'], 'pg-dl-type display-4 text-primary mb-2'),
            $para($f['label'], 'text-body-secondary fw-medium mb-0'),
        ), '3', '', '', '6');
    }
    return $el('section', 'py-5 border-bottom', lang('Figures'), array(
        $container(array(
            $fx($row($cols, '4', '', 'text-center'), array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => 100)),
        )),
    ));
};

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('New cars from four makes, checked used cars, test drives booked online, a fair trade-in offer and finance explained before you sign.'),
    'tree'             => $page(array(
        // A showroom: the range in panels a window tall, the first one the
        // page's title; the sticky bar keeps the two requests in reach
        // while the visitor scrolls the rest of the page.
        $showroom_panel('velo-volt', 'h1'),
        $el('div', '', lang('Showroom'), array(
            $sticky_bar(lang('Drive it before you decide'), lang('Choose a car, a day and a time; we confirm within one working day.'),
                array(lang('Book a Test Drive'), '{{page:test_drive}}'), array(lang('Value My Car'), '{{page:tradein}}')),
            $showroom_panel('kestrel-gt'),
            $showroom_panel('norda-tour'),
            $figure_band(),

            // The stock: the ways in, then the featured and the latest used
            // cars.
            $stock_finder(),
            $el('section', 'pb-5', lang('Featured Cars'), array(
                $container(array(
                    $section_header(lang('In the showroom'), lang('Featured cars'), lang('All cars'), '{{page:vehicles}}'),
                    $widget('home_featured'),
                )),
            )),
            $el('section', 'py-5', lang('Used Cars'), array(
                $container(array(
                    $section_header(lang('Checked and ready'), lang('Latest used cars'), lang('All used cars'), '{{page:used_cars}}'),
                    $widget('home_used'),
                )),
            )),

            // Test drive, trade-in and finance, then the ways to pay.
            $three_ways(),
            $el('section', 'py-5', lang('Finance'), array(
                $container(array(
                    $section_header(lang('Ways to pay'), lang('Finance that fits your month'), lang('How finance works'), '{{page:finance}}'),
                    $fx($row($finance_cards, '4'), array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => 120)),
                )),
            )),

            // What customers say: sample words for the dealer to replace,
            // and beside them its own Google reviews, which show once Google
            // Reviews is set up under Site Settings.
            $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
                $container(array(
                    $section_header(lang('Our customers'), lang('What customers say')),
                    $fx($row($testimonial_cards, '4'), array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => 120)),
                    $widget('google_reviews'),
                )),
            )),

            $el('section', 'py-5', lang('Showroom News'), array(
                $container(array(
                    $section_header(lang('From the showroom'), lang('Showroom News'), lang('All articles'), '{{page:blog}}'),
                    $widget('home_posts'),
                )),
            )),
        )),

        $shared('test_drive_band'),
    )),
);

// ── Service centre ──────────────────────────────────────────────────────

$service_cards = array();
foreach (array_keys($services) as $key) $service_cards[] = $service_card($key, '6', '6');

$public_pages[] = array(
    'key'              => 'service',
    'name'             => lang('service'),
    'folder'           => 'public',
    'title'            => lang('Service Centre'),
    'meta_description' => lang('Servicing, tyres, brakes, bodywork and electric car care for every make, with genuine parts and a courtesy car for longer jobs.'),
    'tree'             => $page(array(
        $page_header(lang('Service Centre'), lang('Servicing and repairs for every make, by the people who sold you the car.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Book a Service'), '{{page:contact}}', 'btn btn-primary px-4'),
                $link($dealer['phone'], $phone_href, 'btn btn-outline-secondary px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Services'), array(
            $container(array(
                $row(array(
                    $col(array($row($service_cards, '4')), '', '8'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary', lang('Workshop Hours'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Workshop hours'), 'h5 fw-semibold mb-3'),
                                $hours('row small mb-3'),
                                $para(lang('The workshop is closed on Sunday; the showroom opens for viewings only.'), 'small text-body-secondary mb-3'),
                                $link(lang('Visit Us'), '{{page:visit}}', 'btn btn-sm btn-outline-primary'),
                            )),
                        )),
                    ), '', '4'),
                ), '4', '', 'g-lg-5'),
            )),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Every Service'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('workshop', 1200, 900), lang('A technician checking a car on the lift'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '5'),
                    $col(array(
                        $para(lang('With every service'), 'pdl-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('What every visit includes'), 'h3 fw-semibold mb-3'),
                        $check_list(array(
                            lang('A price agreed before we start, and a call before any extra work'),
                            lang('Genuine or equivalent parts, with the old ones shown on request'),
                            lang('The service stamp in your book and the record in our system'),
                            lang('A free wash and a check of the tyres, lights and fluids'),
                            lang('A courtesy car for work that takes longer than a day'),
                        )),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('every-service', lang('warranty, parts, courtesy car, price'))),
        $shared('test_drive_band'),
    )),
);

// ── Finance ─────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'finance',
    'name'             => lang('finance'),
    'folder'           => 'public',
    'title'            => lang('Finance'),
    'meta_description' => lang('Hire purchase, personal contract purchase or cash: how each way to pay works, what we ask for and how to apply.'),
    'tree'             => $page(array(
        $page_header(lang('Finance'), lang('Three ways to pay, every monthly figure explained before you sign.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-4', lang('Buttons'), array(
                $link(lang('Apply for Finance'), '{{page:finance_apply}}', 'btn btn-primary px-4'),
                $link(lang('Value My Car'), '{{page:tradein}}', 'btn btn-outline-secondary px-4'),
            )),
        )),
        $el('section', 'py-5', lang('Ways to Pay'), array(
            $container(array(
                $section_header(lang('Ways to pay'), lang('Choose the plan that fits')),
                $row($finance_cards, '4'),
            )),
        ), $searchable('ways-to-pay', lang('hire purchase, PCP, loan, monthly payment, cash'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Apply'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('How to apply'), 'pdl-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('An answer within two working days'), 'h3 fw-semibold mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('List'), array(
                            $step(1, lang('Send the request'), lang('Choose the car and the plan, and tell us the deposit, term and income band. No ID or bank number online.')),
                            $step(2, lang('Talk to the adviser'), lang('Our finance adviser calls you, explains the figures and tells you which documents to bring.')),
                            $step(3, lang('Sign in the showroom'), lang('Bring the documents, read the agreement with us and sign; then the car is yours to drive.')),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 shadow-sm', lang('Good to Know'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Good to know'), 'h5 fw-semibold mb-3'),
                                $check_list(array(
                                    lang('A request is not a credit check and costs nothing'),
                                    lang('Your trade-in can be the deposit'),
                                    lang('You can pay off early at any time'),
                                    lang('Follow your request and write to the adviser from your account'),
                                ), 'list-unstyled small mb-4'),
                                $link(lang('Apply for Finance'), '{{page:finance_apply}}', 'btn btn-primary w-100'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
                $para(lang('Finance is subject to status and given to residents aged 18 and over. Figures on this site are examples; the agreement you sign states the rate and the total you pay.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('how-to-apply', lang('apply, documents, credit, deposit, term'))),
        $shared('test_drive_band'),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About'),
    'meta_description' => lang('A family dealership since 1998: how we choose our cars, the people you meet and what every buyer can expect from us.'),
    'tree'             => $page(array(
        $page_header(lang('About'), $dealer['tagline']),
        $el('section', 'py-5', lang('Our Story'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-4x3 rounded-4 overflow-hidden', lang('Picture Frame'), array(
                            $image($photo('about', 1200, 900), lang('The dealership\'s showroom by the coast road'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                    ), '', '6'),
                    $col(array(
                        $para(lang('Our story'), 'pdl-eyebrow text-uppercase fw-semibold text-primary mb-1'),
                        $heading('h2', lang('On the coast road since 1998'), 'h3 fw-semibold mb-3'),
                        $para($dealer['bio'], 'text-body-secondary'),
                        $para(lang('We sell fewer used cars than the big lots, and we know each one: we have driven it, checked it on 120 points and read its service history.'), 'text-body-secondary mb-0'),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('our-story', lang('story, history, family, since 1998'))),
        $el('section', 'py-4 border-top border-bottom', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Values'), array(
            $container(array(
                $section_header(lang('How we work'), lang('Our principles')),
                $row(array(
                    $value('bi-tag', lang('Clear prices'), lang('The price on the card is the price you pay, with nothing added at the desk.')),
                    $value('bi-clipboard-check', lang('Checked cars'), lang('Every used car checked on 120 points, with the report given to you.')),
                    $value('bi-shield-check', lang('A real warranty'), lang('At least six months on every used car, honoured in our own workshop.')),
                    $value('bi-chat-dots', lang('No pressure'), lang('Take the time you need; a test drive never comes with a sales pitch.')),
                ), '4'),
            )),
        ), $searchable('values', lang('trust, warranty, inspection, price'))),
        $el('section', 'py-5', lang('Our Team'), array(
            $container(array(
                $section_header(lang('The team'), lang('The people you meet')),
                $row($team_cards, '4'),
            )),
        ), $searchable('team', lang('sales, workshop, finance adviser, staff'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our customers'), lang('What customers say')),
                $row($testimonial_cards, '4'),
            )),
        )),
        $shared('test_drive_band'),
    )),
);

// ── Visit us ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'visit',
    'name'             => lang('visit-us'),
    'folder'           => 'public',
    'title'            => lang('Visit Us'),
    'meta_description' => lang('The showroom\'s address, opening hours, parking and how to reach sales, the workshop and the finance adviser.'),
    'tree'             => $page(array(
        $page_header(lang('Visit Us'), lang('Come and see the cars, have a coffee and take one for a drive.')),
        $el('section', 'py-5', lang('Showroom'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'ratio ratio-16x9 rounded-4 overflow-hidden mb-4', lang('Picture Frame'), array(
                            $image($photo('showroom', 1200, 675), lang('The front of the showroom with cars parked outside'), 'object-fit-cover', array('_attrs' => array($attr('loading', 'lazy')))),
                        )),
                        $heading('h2', lang('Finding us'), 'h4 fw-semibold mb-3'),
                        $check_list(array(
                            lang('On the coast road, ten minutes from the city centre'),
                            lang('Free parking for customers in front of the showroom'),
                            lang('A bus stop at the door, and a lift back home while your car is serviced'),
                            lang('A lounge with coffee and Wi-Fi while you wait'),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('aside', 'card border-0 bg-body-tertiary', lang('Contact Details'), array(
                            $el('div', 'card-body p-4', lang('Card Body'), array(
                                $heading('h2', lang('Address'), 'h6 text-uppercase small fw-bold mb-2'),
                                $para($dealer['address'], 'mb-2'),
                                $link(lang('Get directions'), 'https://www.google.com/maps/search/?' . $query(array('api' => '1', 'query' => $dealer['address'])),
                                    'd-inline-block small fw-semibold link-primary mb-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                                $heading('h2', lang('Phone and e-mail'), 'h6 text-uppercase small fw-bold mb-2'),
                                $link($dealer['phone'], $phone_href, 'd-block link-body-emphasis text-decoration-none mb-1'),
                                $link($dealer['email'], 'mailto:' . $dealer['email'], 'd-block link-body-emphasis text-decoration-none text-break mb-4'),
                                $heading('h2', lang('Opening hours'), 'h6 text-uppercase small fw-bold mb-2'),
                                $hours('row small mb-3'),
                                $link(lang('Write to Us'), '{{page:contact}}', 'btn btn-primary w-100'),
                            )),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('finding-us', lang('address, directions, parking, map, location'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Departments'), array(
            $container(array(
                $section_header(lang('Who to ask'), lang('Sales, workshop and finance')),
                $row(array(
                    $department('bi-car-front', lang('Sales'), lang('Test drives, new and used cars and trade-ins.'), lang('Every day the showroom is open')),
                    $department('bi-wrench-adjustable', lang('Workshop'), lang('Servicing, repairs, tyres and bodywork.'), lang('Monday to Saturday')),
                    $department('bi-cash-coin', lang('Finance'), lang('Ways to pay and finance requests.'), lang('Monday to Friday, by appointment')),
                ), '4'),
            )),
        ), $searchable('departments', lang('sales, workshop, finance, contact'))),
        $shared('test_drive_band'),
    )),
);

// ── FAQ ─────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Questions and Answers'),
    'meta_description' => lang('Answers to what buyers ask: test drives, trade-ins, finance, warranties, used car checks and servicing.'),
    'tree'             => $page(array(
        $page_header(lang('Questions and Answers'), lang('What buyers ask before they come to us. Anything else? Write to us and the answer comes to your inbox.')),
        $el('section', 'py-5', lang('FAQ'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $el('div', 'accordion', lang('Questions'), array(
                            $faq(1, lang('How do I book a test drive?'), lang('Open the car\'s page and press "Book a Test Drive", or use the button at the top of every page. Choose a day and a time of day; we confirm within one working day.'), lang('test drive, appointment, try a car')),
                            $faq(2, lang('What do I bring to a test drive?'), lang('Your driving licence. If you want an offer for your car, bring it with its papers and service book.'), lang('licence, documents, test drive')),
                            $faq(3, lang('How is my trade-in valued?'), lang('Send the car\'s details and photos with "Value My Car". We inspect it in person and give the offer in writing; it stays valid for seven days.'), lang('trade-in, part exchange, valuation, sell my car')),
                            $faq(4, lang('What warranty comes with a used car?'), lang('At least six months, twelve on most cars, honoured in our own workshop. The car\'s page states it.'), lang('warranty, guarantee, used car')),
                            $faq(5, lang('What is checked on a used car?'), lang('120 points from the brakes to the electrics, a road test and the service history. You get the report with the car.'), lang('inspection, check, history, report')),
                            $faq(6, lang('Can I buy with finance?'), lang('Yes: hire purchase or personal contract purchase. Send a finance request; the adviser calls you and explains every figure before you sign.'), lang('finance, loan, monthly, credit')),
                            $faq(7, lang('Can I reserve a car?'), lang('Call or write to us. We hold a car for a day while you decide, and a reserved car shows "Reserved" on its card.'), lang('reserve, hold, deposit')),
                            $faq(8, lang('How do I follow my requests?'), lang('The test drive, trade-in and finance forms open an account for you. Your requests\' pages show their status, and you can write to us there.'), lang('status, follow, account, message')),
                        ), array('id' => 'dl-faq')),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
        $shared('test_drive_band'),
    )),
);

return $public_pages;
