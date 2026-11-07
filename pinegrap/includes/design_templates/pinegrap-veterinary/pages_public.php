<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Veterinary", its public pages: home, the services and
 * one page per service, the pets the practice treats, emergencies, the new
 * clients' first visit, the pet health plans, about, the questions and
 * getting here. Included after parts.php in the same scope (its builders
 * and the practice's data); returns array('pages' => ..., 'widgets' => ...).
 *
 * Every figure comes from parts.php ($practice, $opening_hours, $species,
 * $services, $vets, $plans, $emergency_signs, $first_visit, $figures,
 * $testimonials), so a change made there reaches every page here. No page
 * here links a shop page: the template stands on a site without the shop,
 * and a health plan is joined through the sign-up request.
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

// A picture in a frame of the given ratio, loaded lazily unless it is the
// first thing a page shows.
$framed = function ($src, $alt, $ratio = '4x3', $lazy = true) use ($el, $image, $attr) {
    $extra = $lazy ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
    return $el('div', 'ratio ratio-' . $ratio . ' rounded-4 overflow-hidden bg-body-tertiary shadow-sm', lang('Picture Frame'), array(
        $image($src, $alt, 'object-fit-cover', $extra),
    ));
};

// A band of text beside a picture; $flip puts the picture first from lg up.
// The band is a search section under its anchor $id, with the topics a
// visitor may search for that it does not say itself.
$story_band = function ($id, $eyebrow, $title, $texts, $picture, $alt, $flip = false, $more = array(), $topics = '', $class = 'py-5')
    use ($el, $heading, $para, $container, $row, $col, $framed, $searchable) {
    $words = array(
        $para($eyebrow, 'pvt-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pvt-display h2 mb-3'),
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

// A tile with an icon, a title and a line: the values, the ways to the
// practice and what a first visit holds.
$feature_tile = function ($icon_name, $title, $text, $lg = '4', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pvt-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// The practice in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pvt-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// The opening hours as a short list, for the location and emergency pages.
$hours_list = function ($class = 'list-unstyled mb-0') use ($el, $span, $opening_hours) {
    $items = array();
    foreach ($opening_hours as $h) {
        $items[] = $el('li', 'd-flex justify-content-between gap-3 py-2 border-bottom', $h['days'], array(
            $span($h['days'], '', lang('Days')),
            $span($h['hours'], 'pvt-price fw-semibold', lang('Hours')),
        ));
    }
    return $el('ul', $class, lang('Opening Hours'), $items);
};

// The emergency line as a large button that calls it.
$tel = function ($number) {
    return 'tel:' . preg_replace('/[^0-9+]/', '', $number);
};
$emergency_button = function ($class = 'btn btn-danger btn-lg rounded-pill px-4') use ($el, $icon, $span, $practice, $tel) {
    return $el('a', $class . ' d-inline-flex align-items-center gap-2', lang('Emergency Line'), array(
        $icon('bi-telephone-plus'),
        $span($practice['emergency'], 'text-nowrap', lang('Link Text')),
    ), array('href' => $tel($practice['emergency'])));
};

// The first visit's steps, numbered.
$first_visit_steps = function () use ($el, $step, $first_visit) {
    $items = array();
    foreach ($first_visit as $n => $s) $items[] = $step($n + 1, $s['title'], $s['text']);
    return $el('ol', 'list-unstyled mb-0', lang('Steps'), $items);
};

// The cards of the shared data, in their order.
$service_cols = array();
foreach (array_keys($services) as $key) $service_cols[] = $service_card($key);
$species_cols = array();
foreach (array_keys($species) as $key) $species_cols[] = $species_card($key);
$plan_cols = array();
foreach (array_keys($plans) as $key) $plan_cols[] = $plan_card($key);
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A friendly veterinary practice for dogs, cats, rabbits and birds: check-ups, vaccinations, dentistry, surgery, health plans and a 24-hour emergency line.'),
    'tree'             => $page(array(
        // The way in: the practice in a sentence, the two ways to see a vet
        // and a photo of a consultation.
        $el('section', 'pvt-hero py-5 overflow-hidden', lang('Hero'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para($practice['region'], 'pvt-eyebrow text-uppercase fw-semibold text-primary mb-3'),
                        $heading('h1', lang('Healthy pets, happy homes'), 'pvt-display display-4 mb-3'),
                        $para($practice['tagline'] . '.', 'lead text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Book an Appointment'), '{{page:appointment}}', 'btn btn-primary btn-lg rounded-pill px-4'),
                            $link(lang('Pick a Free Time'), '{{page:book_online}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
                        )),
                    ), '', '5'),
                    $col(array(
                        $framed($photo('hero', 1400, 1050), lang('A vet listening to a calm dog\'s heart on the consulting table'), '4x3', false),
                    ), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        )),

        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),

        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $section_header(lang('What we do'), lang('Care for every stage of life'), lang('All services'), '{{page:services}}'),
                $row($service_cols, '4'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Pets We Treat'), array(
            $container(array(
                $section_header(lang('Our patients'), lang('Dogs, cats and smaller friends'), lang('Pets We Treat'), '{{page:pets}}'),
                $row($species_cols, '4'),
            )),
        )),

        // The vets, read from the Vets collection.
        $el('section', 'py-5', lang('Our Vets'), array(
            $container(array(
                $section_header(lang('The team'), lang('Vets who know your pet by name'), lang('All vets'), '{{page:vets}}'),
                $widget('home_vets'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Health Plans'), array(
            $container(array(
                $section_header(lang('Health plans'), lang('Preventive care, spread over the year'), lang('Compare the plans'), '{{page:health_plans}}'),
                $row($plan_cols, '4'),
            )),
        )),

        // The emergency line in a band of its own.
        $el('section', 'py-5', lang('Emergencies'), array(
            $container(array(
                $el('div', 'p-4 p-lg-5 rounded-4 bg-danger-subtle text-danger-emphasis d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Emergency Box'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('An emergency, day or night?'), 'pvt-display h3 mb-1'),
                        $para(lang('Call before you set off, so the vet on duty is ready when you arrive.'), 'mb-0'),
                    )),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                        $emergency_button(),
                        $link(lang('What to do'), '{{page:emergency}}', 'btn btn-outline-danger btn-lg rounded-pill px-4'),
                    )),
                )),
            )),
        ), $searchable('vt-home-emergency', lang('emergency, urgent, night, weekend, out of hours'))),

        // What owners say: sample words for the practice to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5 bg-body-tertiary', lang('Owner Stories'), array(
            $container(array(
                $section_header(lang('Our clients'), lang('What pet owners say')),
                $row($testimonial_cols, '4'),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5', lang('From Pet Care Articles'), array(
            $container(array(
                $section_header(lang('Pet Care Articles'), lang('Advice from our vets'), lang('All articles'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Services ────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'services',
    'name'             => lang('services'),
    'folder'           => 'public',
    'title'            => lang('Our Services'),
    'meta_description' => lang('Wellness exams, vaccinations, dental care, surgery, diagnostics and nutrition for dogs, cats, rabbits and birds, with prices.'),
    'tree'             => $page(array(
        $page_header(lang('Our Services'), lang('Everything a pet needs, from the first vaccination to surgery, in one practice with its own theatre and laboratory.')),
        $el('section', 'py-5', lang('Our Services'), array(
            $container(array(
                $row($service_cols, '4'),
            )),
        ), $searchable('vt-services', lang('treatment, price, cost, check-up, vaccine, operation, dentist'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('About our prices'), 'pvt-display h3 mb-3'),
                        $check_list(array(
                            lang('Every price includes the examination that comes with it'),
                            lang('Before a treatment we give you a written estimate'),
                            lang('Health plan members pay ten per cent less on other treatments'),
                            lang('We take cards, cash and bank transfer'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-4 bg-primary-subtle', lang('Text'), array(
                            $heading('h2', lang('Not sure what your pet needs?'), 'pvt-display h4 mb-2'),
                            $para(lang('Ask for an appointment and tell us what you have noticed; the vet decides what the visit needs.'), 'mb-3'),
                            $link(lang('Book an Appointment'), '{{page:appointment}}?' . $query(array('service' => lang('My pet is unwell'))), 'btn btn-primary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('vt-service-prices', lang('estimate, payment, cards, cost'))),
    )),
);

// One page per service: what it is, what it covers, the pets it is for,
// the vets who do it and the appointment request with the service chosen.
// Its address is a translated slug, so a Turkish site reads Turkish
// addresses.
$service_slugs = array(
    'wellness'    => lang('wellness-exams'),
    'vaccination' => lang('vaccinations'),
    'dental'      => lang('pet-dental-care'),
    'surgery'     => lang('pet-surgery'),
    'diagnostics' => lang('diagnostics-and-imaging'),
    'nutrition'   => lang('nutrition-and-weight'),
);
foreach ($services as $key => $s) {
    $vet_lines = array();
    foreach ($vets as $v) {
        if (in_array($key, $v['services'], true)) {
            $vet_lines[] = $v['name'] . ' · ' . $v['role'];
        }
    }
    $public_pages[] = array(
        'key'              => 'service_' . $key,
        'name'             => $service_slugs[$key],
        'folder'           => 'public',
        'title'            => $s['title'],
        'meta_description' => $s['summary'],
        'tree'             => $page(array(
            $page_header($s['title'], $s['summary']),
            $el('section', 'py-5', $s['title'], array(
                $container(array(
                    $row(array(
                        $col(array(
                            $icon_tile($s['icon'], 'mb-3'),
                            $para($s['text'], 'lead mb-4'),
                            $species_badges($s['species']),
                            $heading('h2', lang('What it covers'), 'pvt-display h4 mb-3'),
                            $check_list($s['points'], 'list-unstyled mb-4'),
                            $para($service_price($key), 'pvt-price h5 fw-bold mb-4'),
                            $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                                $link(lang('Book an Appointment'), '{{page:appointment}}?' . $query(array('service' => $s['title'])), 'btn btn-primary rounded-pill px-4'),
                                $link(lang('Pick a Free Time'), '{{page:book_online}}', 'btn btn-outline-primary rounded-pill px-4'),
                            )),
                        ), '', '6'),
                        $col(array($framed($photo('service-' . $key, 1200, 900), $s['title'])), '', '6'),
                    ), '4', '', 'g-lg-5 align-items-center'),
                )),
            ), $searchable('vt-service-' . $key)),
            $el('section', 'py-5 bg-body-tertiary', lang('Our Vets'), array(
                $container(array(
                    $section_header(lang('Who does it'), lang('The vets for this service'), lang('All vets'), '{{page:vets}}'),
                    $check_list($vet_lines),
                )),
            )),
        )),
    );
}

// ── Pets we treat ───────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'pets',
    'name'             => lang('pets-we-treat'),
    'folder'           => 'public',
    'title'            => lang('Pets We Treat'),
    'meta_description' => lang('Dogs, cats, rabbits, guinea pigs, hamsters, ferrets and birds: what we do for each and the vets who see them most.'),
    'tree'             => $page(array(
        $page_header(lang('Pets We Treat'), lang('Each kind of pet has its own needs. Find the vets who see yours most and the articles written for it.')),
        $el('section', 'py-5', lang('Pets'), array(
            $container(array(
                $row($species_cols, '4'),
            )),
        ), $searchable('vt-pets', lang('dog, cat, rabbit, guinea pig, hamster, ferret, bird, parrot, exotic'))),
        $story_band('vt-cat-friendly', lang('Cats'), lang('A practice that cats do not mind'),
            array(lang('Cats wait in their own quiet room, away from barking. We handle them gently, use towels and calming scents and give them time to come out of the carrier on their own.')),
            $photo('cat-room', 1200, 900), lang('A cat resting in a soft bed in the quiet waiting room'), false, array(),
            lang('cat friendly, stress, nervous cat, carrier'), 'py-5 bg-body-tertiary'),
        $story_band('vt-small-pets', lang('Rabbits and Small Pets'), lang('Small patients, specialist care'),
            array(lang('Rabbits and small pets hide pain well, and their teeth and diet need a vet who knows them. Bring them in a carrier with some of their own hay or bedding.')),
            $photo('rabbit', 1200, 900), lang('A vet checking the teeth of a calm rabbit'), true,
            array($link(lang('Find a vet'), '{{page:vets}}?' . $query(array('species' => $species['small']['title'])), 'btn btn-outline-primary rounded-pill px-4')),
            lang('rabbit teeth, guinea pig, hay, small animal')),
    )),
);

// ── Emergencies ─────────────────────────────────────────────────────────

$sign_items = array();
foreach ($emergency_signs as $s) $sign_items[] = $s;

$public_pages[] = array(
    'key'              => 'emergency',
    'name'             => lang('emergencies'),
    'folder'           => 'public',
    'title'            => lang('Emergencies'),
    'meta_description' => lang('What to do in a pet emergency, the signs that cannot wait and our emergency line, which answers day and night.'),
    'tree'             => $page(array(
        $page_header(lang('Emergencies'), lang('If your pet is in danger, call us at once, day or night, before you set off.'), array(
            $el('div', 'mt-3', lang('Buttons'), array($emergency_button())),
        )),
        $el('section', 'py-5', lang('When to Call'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Call at once if your pet has'), 'pvt-display h3 mb-3'),
                        $check_list($sign_items),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('On the way to us'), 'pvt-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Call first'), lang('The vet on duty tells you what to do now and gets ready for your arrival.')),
                            $step(2, lang('Keep your pet warm and still'), lang('Wrap it in a towel or blanket; carry a small pet in its carrier.')),
                            $step(3, lang('Bring what it swallowed'), lang('The packet, the plant or a photo of it helps the vet choose the treatment.')),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('vt-emergency-signs', lang('poison, bleeding, breathing, collapse, vomiting, urgent'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Out of Hours'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Out of hours'), 'pvt-display h3 mb-3'),
                        $para(lang('Outside the opening hours one of our vets answers the emergency line and meets you at the practice. An out-of-hours visit costs more than a daytime one; the vet tells you the fee on the phone.'), 'text-body-secondary mb-0'),
                    ), '', '7'),
                    $col(array(
                        $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                        $hours_list(),
                    ), '', '5'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('vt-out-of-hours', lang('night, weekend, Sunday, holiday, fee'))),
    ), false),
);

// ── New clients ─────────────────────────────────────────────────────────

$first_visit_tiles = array(
    $feature_tile('bi-clock', lang('Thirty minutes'), lang('A first visit takes half an hour, so there is time for the examination and your questions.')),
    $feature_tile('bi-cash-coin', $service_price('wellness'), lang('A first visit costs the same as a wellness exam; vaccinations are added if your pet needs them.')),
    $feature_tile('bi-heart', lang('No rush'), lang('Nervous pets get time to settle; we can also start with a short, friendly visit and no treatment at all.')),
);

$public_pages[] = array(
    'key'              => 'new_clients',
    'name'             => lang('new-clients'),
    'folder'           => 'public',
    'title'            => lang('New Clients'),
    'meta_description' => lang('Registering with the practice: how a first visit works, what to bring, what it costs and how health plans spread the cost of care.'),
    'tree'             => $page(array(
        $page_header(lang('New Clients'), lang('Welcome! Registering costs nothing: your pet is registered at its first visit.')),
        $el('section', 'py-5', lang('First Visit'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Your first visit, step by step'), 'pvt-display h3 mb-4'),
                        $first_visit_steps(),
                    ), '', '6'),
                    $col(array($framed($photo('first-visit', 1200, 900), lang('A puppy meeting the vet for the first time'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('vt-first-visit', lang('register, registration, first appointment, new patient, what to bring'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $row($first_visit_tiles, '4'),
            )),
        ), $searchable('vt-first-visit-cost', lang('cost, price, length, nervous pet'))),
        $el('section', 'py-5', lang('Ready'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Ready to register?'), 'pvt-display h4 mb-1'),
                        $para(lang('Ask for a first appointment, or pick a free time with a vet now.'), 'text-body-secondary mb-0'),
                    )),
                    $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                        $link(lang('Book an Appointment'), '{{page:appointment}}?' . $query(array('service' => $services['wellness']['title'])), 'btn btn-primary rounded-pill px-4 text-nowrap'),
                        $link(lang('Health Plans'), '{{page:health_plans}}', 'btn btn-outline-primary rounded-pill px-4 text-nowrap'),
                    )),
                )),
            )),
        )),
    )),
);

// ── Health plans ────────────────────────────────────────────────────────
// The plans, what every plan includes and how joining works. Each plan
// opens the sign-up request with the plan chosen; the shop's plan products
// are linked from the shop pages only.
$public_pages[] = array(
    'key'              => 'health_plans',
    'name'             => lang('pet-health-plans'),
    'folder'           => 'public',
    'title'            => lang('Health Plans'),
    'meta_description' => lang('Pet health plans for puppies and kittens, adult and senior pets, rabbits and small pets: preventive care paid monthly.'),
    'tree'             => $page(array(
        $page_header(lang('Health Plans'), lang('The preventive care your pet needs each year, for a small monthly payment and with ten per cent off other treatments.')),
        $el('section', 'py-5', lang('Plans'), array(
            $container(array(
                $row($plan_cols, '4'),
            )),
        ), $searchable('vt-plans', lang('price, monthly, insurance, preventive care, flea, worm, vaccination'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Good to Know'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Every plan includes'), 'pvt-display h3 mb-3'),
                        $check_list(array(
                            lang('A plan runs for twelve months from the first payment'),
                            lang('Reminders when a check or a vaccination is due'),
                            lang('Ten per cent off treatments the plan does not cover'),
                            lang('A health plan is not insurance: it covers planned care, not accidents'),
                        )),
                    ), '', '6'),
                    $col(array(
                        $heading('h2', lang('How a plan starts'), 'pvt-display h3 mb-4'),
                        $el('ol', 'list-unstyled mb-0', lang('Steps'), array(
                            $step(1, lang('Choose a plan'), lang('Open the request from a plan or choose it in the form, with your pet\'s name and age.')),
                            $step(2, lang('We check the plan fits'), lang('The reception answers within a working day and books the first check.')),
                            $step(3, lang('Care starts'), lang('The plan starts at the first check; you pay each month or the whole year at once.')),
                        )),
                    ), '', '6'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('vt-plan-terms', lang('terms, cancel, insurance, payment, renew'))),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-hand-thumbs-up', 'title' => lang('Calm and gentle'), 'text' => lang('A pet that is not afraid heals better, so we take our time and handle every patient gently.')),
    array('icon' => 'bi-chat-heart', 'title' => lang('Clear about costs'), 'text' => lang('You get a written estimate before any treatment, and we call you before we do anything more.')),
    array('icon' => 'bi-mortarboard', 'title' => lang('Always learning'), 'text' => lang('Our vets and nurses train every year, and we share what we learn in our Pet Care Articles.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text']);

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('Two vets, a corner shop and a lot of patience: the story of the practice, its team and how we care for pets.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $practice['tagline'] . '.'),
        $story_band('vt-our-story', lang('Our story'), lang('From one consulting room to a full practice'),
            array($practice['story']),
            $photo('about', 1200, 900), lang('The bright reception of the practice with a dog waiting'), false, array(),
            lang('history, founders, owners, who we are')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('How we look after pets')),
                $row($about_value_tiles, '4'),
            )),
        ), $searchable('vt-values', lang('values, approach, fear free, estimate'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Our Vets'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('Meet the team'), 'pvt-display h4 mb-1'),
                        $para(lang('Four vets, five nurses and a reception team who love animals as much as you do.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Our Vets'), '{{page:vets}}', 'btn btn-primary rounded-pill px-4 text-nowrap'),
                )),
            )),
        )),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: appointments, care and costs, emergencies. Each
// question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pvt-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about appointments, registering a new pet, prices, health plans, vaccinations and what to do in an emergency.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Appointments, care and emergencies. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('vt-faq-appointments', lang('Appointments'), array(
                            array(lang('How do I book an appointment?'), lang('Send an appointment request and the reception confirms a time, or pick a free time with a vet online and it is booked at once.'), lang('book, booking, appointment, online')),
                            array(lang('How do I follow my request?'), lang('Sending a form opens an account for you. Under My Account you see the state of each request and can write to the reception there.'), lang('my account, status, track')),
                            array(lang('Can I choose my vet?'), lang('Yes: choose the vet in the request or open it from the vet\'s own page. Choose Any vet for the first free time.'), lang('vet, doctor, choose, same vet')),
                        )),
                        $faq_group('vt-faq-care', lang('Care and costs'), array(
                            array(lang('How often does my pet need a check-up?'), lang('Once a year for most adult pets, twice a year for pets over seven and for rabbits.'), lang('check-up, yearly, senior, how often')),
                            array(lang('Is a health plan the same as insurance?'), lang('No. A plan spreads the cost of planned care such as checks and vaccinations; insurance pays for accidents and illness. Many owners have both.'), lang('insurance, plan, cover, accident')),
                            array(lang('Do you treat rabbits and birds?'), lang('Yes, every week. Dr. Collins sees most of our rabbits, small pets and birds.'), lang('rabbit, bird, exotic, guinea pig')),
                        )),
                        $faq_group('vt-faq-emergency', lang('Emergencies'), array(
                            array(lang('What if my pet is ill at night?'), lang('Call the emergency line: a vet answers day and night and meets you at the practice.'), lang('night, weekend, out of hours, urgent')),
                            array(lang('My dog ate chocolate. What do I do?'), lang('Call us at once and tell us what it ate, how much and when. Do not make your dog sick unless the vet tells you to.'), lang('chocolate, poison, toxic, ate')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// ── Getting here ────────────────────────────────────────────────────────

$ways_here = array(
    array('icon' => 'bi-car-front', 'title' => lang('By car'), 'text' => lang('Park in front of the door; a sick or heavy pet can be carried straight in.')),
    array('icon' => 'bi-bus-front', 'title' => lang('By bus'), 'text' => lang('The Old Town stop is two minutes away; pets travel free in a carrier.')),
    array('icon' => 'bi-person-walking', 'title' => lang('On foot'), 'text' => lang('Chestnut Lane runs off the market square; dogs can wait at the water bowl by the door.')),
);
$way_tiles = array();
foreach ($ways_here as $w) $way_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);

$public_pages[] = array(
    'key'              => 'location',
    'name'             => lang('getting-here'),
    'folder'           => 'public',
    'title'            => lang('Getting Here'),
    'meta_description' => lang('Where the practice is, its opening hours and emergency line, and how to reach it by car, bus or on foot.'),
    'tree'             => $page(array(
        $page_header(lang('Getting Here'), $practice['region'] . '.'),
        $el('section', 'py-5', lang('Address'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Find us'), 'pvt-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', $practice['address'], 'pvt-display h3 mb-3'),
                        $el('ul', 'list-unstyled mb-4', lang('Contact Details'), array(
                            $el('li', 'd-flex gap-2 mb-2', lang('Phone'), array(
                                $icon('bi-telephone', 'text-primary'),
                                $link($practice['phone'], $tel($practice['phone']), 'link-body-emphasis text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-2', lang('Emergency Line'), array(
                                $icon('bi-telephone-plus', 'text-danger'),
                                $link($practice['emergency'], $tel($practice['emergency']), 'link-body-emphasis fw-semibold text-decoration-none'),
                            )),
                            $el('li', 'd-flex gap-2 mb-0', lang('Email'), array(
                                $icon('bi-envelope', 'text-primary'),
                                $link($practice['email'], 'mailto:' . $practice['email'], 'link-body-emphasis text-decoration-none text-break'),
                            )),
                        )),
                        $heading('h3', lang('Opening hours'), 'h6 fw-bold mb-2'),
                        $hours_list('list-unstyled mb-4'),
                        $link(lang('Open in maps'), 'https://www.openstreetmap.org/search?query=' . rawurlencode($practice['address']), 'btn btn-outline-primary rounded-pill px-4', array($attr('rel', 'noopener')), array('target' => '_blank')),
                    ), '', '5'),
                    $col(array($framed($photo('location', 1400, 1050), lang('The practice\'s green front door on Chestnut Lane'))), '', '7'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('vt-address', lang('address, map, directions, opening hours, where'))),
        $el('section', 'py-5 bg-body-tertiary', lang('How to Get Here'), array(
            $container(array(
                $section_header(lang('On the way'), lang('How to get here')),
                $row($way_tiles, '4'),
            )),
        ), $searchable('vt-travel', lang('parking, bus, car, transport'))),
    )),
);

// The home page's lists: its vets (home_vets) come with the vets, its
// articles (home_posts) and the Google reviews (google_reviews) with the
// standard pages; this part places no widget of its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
