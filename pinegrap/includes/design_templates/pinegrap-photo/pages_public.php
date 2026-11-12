<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Photography Studio", its public pages: home, the
 * packages, how a shoot works, about and the questions page. Included
 * after parts.php in the same scope (its builders and the studio's data);
 * returns array('pages' => ..., 'widgets' => ...).
 *
 * The home and how it works stand on the Photo palette blocks (filmstrip
 * hero, proof contact sheet, behind the shot): the trees draw the blocks'
 * structure and classes, and their pg-photo- rules ship with the template's
 * User Styles (pinegrap-photo.php).
 *
 * Every figure comes from parts.php ($studio, $categories, $packages,
 * $package_prices, $package_deposits, $addons, $shoot_steps, $why_us,
 * $figures, $testimonials), so a change made there reaches every page here.
 * No page here links a shop page: the template stands on a site without
 * the shop.
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
        $para($eyebrow, 'pph-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pph-display h2 mb-3'),
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

// A tile with an icon, a title and a line: why clients choose the studio,
// its values and how to prepare for a shoot.
$feature_tile = function ($icon_name, $title, $text, $lg = '3', $more = array()) use ($el, $heading, $para, $col, $icon_tile) {
    return $col(array(
        $el('div', 'h-100 p-4 rounded-4 bg-body border shadow-sm', $title, array_merge(array(
            $icon_tile($icon_name, 'mb-3'),
            $heading('h3', $title, 'pph-display h5 mb-2'),
            $para($text, 'small text-body-secondary mb-0'),
        ), $more)),
    ), '6', $lg);
};

// The studio in four figures, in one row.
$figure_row = function () use ($el, $para, $row, $col, $figures) {
    $cols = array();
    foreach ($figures as $f) {
        $cols[] = $col(array(
            $el('div', 'text-center', $f['label'], array(
                $para($f['value'], 'pph-display display-5 text-primary mb-1'),
                $para($f['label'], 'small text-uppercase fw-semibold text-body-secondary mb-0'),
            )),
        ), '3', '', '', '6');
    }
    return $row($cols, '4');
};

// A node with an interaction (props._fx, played by pg_fx; reduced motion
// is honoured there).
$fx = function ($node, $effect) {
    $node['props']['_fx'] = $effect;
    return $node;
};
// A row or a list whose items rise in one after another on view.
$rise = function ($node, $stagger = 90) {
    $node['props']['_fx'] = array('effect' => 'slide-up', 'trigger' => 'view', 'stagger' => $stagger);
    return $node;
};

// Photo: Filmstrip Hero. The studio in a sentence over a strip of film
// (pg-photo-strip), one numbered frame per category; each frame's caption
// opens the gallery list filtered on it in the words the Category list
// uses. The strip scrolls sideways on a narrow screen, so it is a named
// region a keyboard reaches.
$filmstrip_hero = function () use ($el, $heading, $para, $link, $image, $span, $container, $attr, $photo, $studio, $categories, $galleries, $query, $fx, $searchable) {
    $frames = array();
    $n = 0;
    foreach ($categories as $key => $c) {
        $n++;
        $alt = $c['title'];
        foreach ($galleries as $g) {
            if ($g['category'] === $key) {
                $alt = $g['title'];
                break;
            }
        }
        $extra = $n > 3 ? array('_attrs' => array($attr('loading', 'lazy'))) : array();
        $frames[] = $el('li', '', lang('Frame'), array(
            $el('figure', 'mb-0', lang('Frame'), array(
                $image($photo('strip-' . $key, 600, 400), $alt, 'rounded-1', $extra),
                $el('figcaption', 'd-flex justify-content-between gap-2 small mt-2', lang('Caption'), array(
                    $span(sprintf('%02d', $n), 'font-monospace', lang('Frame Number')),
                    $link($c['title'], '{{page:galleries}}?' . $query(array('category' => $c['title'])), 'fw-semibold text-uppercase text-reset text-decoration-none'),
                )),
            )),
        ));
    }
    $text = $el('div', 'text-center col-lg-8 mx-auto mb-5', lang('Hero Text'), array(
        $para(lang('Photography studio'), 'pph-eyebrow text-uppercase fw-semibold text-primary mb-3'),
        $heading('h1', lang('Photos that feel like you'), 'pph-display display-3 mb-3'),
        $para($studio['tagline'] . '.', 'lead text-body-secondary mb-4'),
        $el('div', 'd-flex flex-wrap justify-content-center gap-2', lang('Buttons'), array(
            $link(lang('See the Galleries'), '{{page:galleries}}', 'btn btn-primary btn-lg rounded-pill px-4'),
            $link(lang('Book a Shoot'), '{{page:book}}', 'btn btn-outline-primary btn-lg rounded-pill px-4'),
        )),
        $para($studio['promise'] . '.', 'small text-body-secondary mt-4 mb-0'),
    ));
    $strip = $el('div', 'pg-photo-strip rounded-3', lang('Filmstrip'), array(
        $el('ol', 'list-unstyled d-flex gap-3 overflow-x-auto mb-0', lang('Frames'), $frames, array('_attrs' => array(
            $attr('role', 'region'), $attr('aria-label', lang('Our work in six frames')), $attr('tabindex', '0'),
        ))),
    ));
    return $el('section', 'py-5 overflow-hidden', lang('Hero'), array(
        $container(array(
            $fx($text, array('effect' => 'fade', 'trigger' => 'load', 'duration' => 800)),
            $fx($strip, array('effect' => 'slide-left', 'trigger' => 'load', 'duration' => 900, 'delay' => 200)),
        )),
    ), $searchable('ph-categories', lang('wedding photographer, portrait, family photos, newborn, event, product photography')));
};

// Photo: Proof Contact Sheet. How a client chooses their favourites beside
// a sheet of twelve numbered proofs (pg-photo-sheet), the picks ringed
// (pg-photo-picked) and marked for a screen reader. The section is a search
// section under $id; $more are links after the client area's.
$contact_sheet = function ($id, $eyebrow, $title, $intro, $topics, $more = array()) use ($el, $heading, $para, $link, $image, $icon, $span, $container, $row, $col, $attr, $photo, $fx, $searchable) {
    $picks = array(4, 7, 11);
    $proofs = array();
    for ($n = 1; $n <= 12; $n++) {
        $number = sprintf('%02d', $n);
        $picked = in_array($n, $picks, true);
        $kids = array(
            $image($photo('proof-' . $n, 360, 240), str_replace('{var}', $number, lang('Proof frame {var}')), $picked ? 'pg-photo-picked rounded-1' : 'rounded-1',
                array('_attrs' => array($attr('loading', 'lazy')))),
            $span($number, 'position-absolute top-0 start-0 m-1 badge bg-body text-body font-monospace', lang('Frame Number')),
        );
        if ($picked) {
            $kids[] = $el('div', 'position-absolute top-0 end-0 m-1 badge text-bg-primary', lang('Picked'), array(
                $icon('bi-heart-fill'),
                $span(lang('Selected'), 'visually-hidden', lang('Text')),
            ));
        }
        $proofs[] = $el('li', 'position-relative', str_replace('{var}', $number, lang('Frame {var}')), $kids);
    }
    $how = array(
        array(lang('Look through your proofs'), lang('Take your time; the gallery stays open until you send your selection.')),
        array(lang('Note the frame numbers'), lang('Write down the numbers of the photos you love and anything to retouch.')),
        array(lang('Send your selection'), lang('We edit your favourites by hand and tell you when they are ready.')),
    );
    $steps = array();
    foreach ($how as $n => $h) {
        $steps[] = $el('li', 'd-flex gap-3 mb-3', lang('Step'), array(
            $span((string)($n + 1), 'd-inline-flex align-items-center justify-content-center flex-shrink-0 rounded-circle bg-primary-subtle text-primary-emphasis fw-semibold p-2 lh-1', lang('Step Number')),
            $el('div', '', lang('Text'), array(
                $heading('h3', $h[0], 'h6 mb-1'),
                $para($h[1], 'small text-body-secondary mb-0'),
            )),
        ));
    }
    $body = $el('div', '', lang('Text'), array(
        $para($eyebrow, 'pph-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', $title, 'pph-display h2 mb-3'),
        $para($intro, 'text-body-secondary mb-4'),
        $el('ol', 'list-unstyled mb-4', lang('Steps'), $steps),
        $para(str_replace('{var}', '04, 07, 11', lang('Your selection: {var}')), 'small fw-semibold font-monospace mb-3'),
        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array_merge(array(
            $link(lang('Open the Client Area'), '{{page:client_area}}', 'btn btn-outline-primary rounded-pill px-4'),
        ), $more)),
    ));
    return $el('section', 'py-5 bg-body-tertiary', lang('Proof Contact Sheet'), array(
        $container(array(
            $row(array(
                $col(array($fx($body, array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700))), '', '5'),
                $col(array($fx($el('ol', 'pg-photo-sheet list-unstyled rounded-3 mb-0', lang('Proofs'), $proofs),
                    array('effect' => 'fade', 'trigger' => 'view', 'duration' => 600, 'stagger' => 60))), '', '7'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// Photo: Behind the Shot. One photograph and the story of how it was made,
// its camera settings as a description list (pg-photo-exif) with figures
// in even columns. The figures are plain text, not words to translate.
$behind_shot = function ($id, $topics) use ($el, $heading, $para, $link, $image, $span, $container, $row, $col, $attr, $photo, $query, $fx, $searchable) {
    $settings = array(
        array(lang('Lens'), '85 mm'),
        array(lang('Aperture'), 'f/1.8'),
        array(lang('Shutter'), '1/250 s'),
        array(lang('ISO'), '400'),
        array(lang('Light'), lang('Window light')),
        array(lang('Edit'), lang('By hand')),
    );
    $pairs = array();
    foreach ($settings as $s) {
        $pairs[] = $el('div', 'col', $s[0], array(
            $el('dt', 'small fw-normal text-body-secondary', lang('Term'), array(), array('text' => $s[0])),
            $el('dd', 'fw-semibold mb-0', lang('Value'), array(), array('text' => $s[1])),
        ));
    }
    $picture = $el('figure', 'mb-0', lang('Photograph'), array(
        $image($photo('shot', 1200, 1500), lang('A bride turning towards the window, lit from the side'), 'img-fluid rounded-4 w-100',
            array('_attrs' => array($attr('loading', 'lazy')))),
        $el('figcaption', 'small text-body-secondary mt-2', lang('Caption'), array(), array('text' => lang('Photographed by Deniz, in the studio'))),
    ));
    $body = $el('div', '', lang('Text'), array(
        $para(lang('Behind the shot'), 'pph-eyebrow text-uppercase fw-semibold text-primary mb-2'),
        $heading('h2', lang('One window, one minute of light'), 'pph-display h2 mb-3'),
        $para(lang('Late in the afternoon the sun reaches the north window at an angle for a few minutes. We waited for it, asked her to turn slowly, and took this on the third turn.'), 'text-body-secondary mb-4'),
        $heading('h3', lang('Camera settings'), 'h6 text-uppercase fw-semibold mb-2'),
        $el('dl', 'pg-photo-exif row row-cols-2 row-cols-sm-3 g-3 pt-3 mb-4', lang('Settings'), $pairs),
        $link(lang('See the gallery'), '{{page:galleries}}?' . $query(array('category' => lang('Portraits'))), 'btn btn-outline-primary rounded-pill px-4'),
    ));
    return $el('section', 'py-5', lang('Behind the Shot'), array(
        $container(array(
            $row(array(
                $col(array($fx($picture, array('effect' => 'fade', 'trigger' => 'view', 'duration' => 900))), '', '7'),
                $col(array($fx($body, array('effect' => 'slide-up', 'trigger' => 'view', 'duration' => 700, 'delay' => 150))), '', '5'),
            ), '4', '', 'g-lg-5 align-items-center'),
        )),
    ), $searchable($id, $topics));
};

// The steps of a shoot as a numbered list.
$shoot_step_items = array();
foreach ($shoot_steps as $n => $s) $shoot_step_items[] = $step($n + 1, $s['title'], $s['text']);

// The cards of the shared data, in their order.
$why_tiles = array();
foreach ($why_us as $w) $why_tiles[] = $feature_tile($w['icon'], $w['title'], $w['text']);
$package_cols = array();
foreach (array_keys($packages) as $key) $package_cols[] = $package_card($key);
// The home shows the two popular packages and the mini session.
$home_package_cols = array();
foreach ($packages as $key => $p) {
    if ($p['popular'] || $key === 'mini') $home_package_cols[] = $package_card($key);
}
$testimonial_cols = array();
foreach (array_keys($testimonials) as $n) $testimonial_cols[] = $testimonial_card($n);

// ── Home ────────────────────────────────────────────────────────────────

$public_pages[] = array(
    'key'              => 'home',
    'name'             => lang('home'),
    'folder'           => 'public',
    'title'            => lang('Home Page'),
    'meta_description' => lang('A photography studio for weddings, portraits, families, newborns, events and brands, with private online galleries.'),
    'tree'             => $page(array(
        // The way in: the studio in a sentence over a strip of its work, a
        // frame per category.
        $filmstrip_hero(),

        // The featured galleries, read from the Galleries collection.
        $el('section', 'py-5', lang('Featured Galleries'), array(
            $container(array(
                $section_header(lang('Recent work'), lang('Stories we have photographed'), lang('All Galleries'), '{{page:galleries}}'),
                $widget('home_galleries'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('Packages'), array(
            $container(array(
                $section_header(lang('Sessions and prices'), lang('Choose your session'), lang('All Packages'), '{{page:packages}}'),
                $rise($row($home_package_cols, '4')),
            )),
        )),

        $behind_shot('ph-home-shot', lang('natural light, lens, camera settings, how we photograph')),

        $contact_sheet('ph-home-clients', lang('Client Area'), lang('Your photos, in a gallery only you can open'),
            lang('After your shoot the proofs wait in a private gallery: sign in, choose your favourites and tell us what to retouch.'),
            lang('proofing, client gallery, download photos, password, private'),
            array($link(lang('How It Works'), '{{page:experience}}', 'btn btn-link link-body-emphasis px-2'))),

        // What clients say: sample words for the studio to replace, and
        // beside them its own Google reviews, which show once Google Reviews
        // is set up under Site Settings.
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our clients'), lang('What clients say'), lang('About Us'), '{{page:about}}'),
                $rise($row($testimonial_cols, '4'), 120),
                $widget('google_reviews'),
            )),
        )),

        $el('section', 'py-5 bg-body-tertiary', lang('From the Journal'), array(
            $container(array(
                $section_header(lang('Photo Journal'), lang('Stories and tips'), lang('All stories'), '{{page:blog}}'),
                $widget('home_posts'),
            )),
        )),
    )),
);

// ── Packages ────────────────────────────────────────────────────────────

// A row of the price table for each package: its price and the deposit
// that holds its date, the button opening the booking request with it.
$package_rows = array();
foreach ($packages as $key => $p) {
    $package_rows[] = $el('tr', '', $p['title'], array(
        $el('th', 'fw-semibold', lang('Package'), array(), array('text' => $p['title'], '_attrs' => array($attr('scope', 'row')))),
        $el('td', '', lang('Length'), array(), array('text' => $p['length'])),
        $el('td', 'text-nowrap', lang('Photos'), array(), array('text' => $images_text($key))),
        $el('td', 'pph-price fw-semibold text-nowrap', lang('Price'), array(), array('text' => $money($package_prices[$key]))),
        $el('td', 'pph-price text-nowrap', lang('Shoot Deposit'), array(), array('text' => $money($package_deposits[$key]))),
        $el('td', 'text-end', lang('Action'), array(
            $link(lang('Book this package'), '{{page:book}}?' . $query(array('package' => $p['title'])), 'btn btn-sm btn-outline-primary rounded-pill px-3 text-nowrap'),
        )),
    ));
}
$addon_items = array();
foreach ($addons as $a) {
    $addon_items[] = $el('li', 'list-group-item d-flex align-items-center gap-3 py-3', $a['title'], array(
        $icon($a['icon'], 'fs-5 text-primary'),
        $span($a['title'], 'flex-grow-1', lang('Text')),
        $span($money($a['price']), 'pph-price fw-semibold text-nowrap', lang('Price')),
    ));
}

$public_pages[] = array(
    'key'              => 'packages',
    'name'             => lang('packages'),
    'folder'           => 'public',
    'title'            => lang('Packages'),
    'meta_description' => lang('Session packages with prices: studio mini sessions, portraits, families, newborns, weddings and brand photography.'),
    'tree'             => $page(array(
        $page_header(lang('Packages'), lang('Every package includes a private online gallery and the download of every edited photo. Choose one and ask for a date.')),
        $el('section', 'py-5', lang('Packages'), array(
            $container(array(
                $rise($row($package_cols, '4')),
            )),
        ), $searchable('ph-packages', lang('price, prices, cost, how much, session fee, wedding package'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Prices and Deposits'), array(
            $container(array(
                $section_header(lang('At a glance'), lang('Prices and deposits')),
                $el('div', 'table-responsive position-relative', lang('Table'), array(
                    $el('table', 'table align-middle mb-0', lang('Price Table'), array(
                        $el('thead', '', lang('Table Head'), array(
                            $el('tr', '', lang('Row'), array(
                                $el('th', '', lang('Package'), array(), array('text' => lang('Package'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Length'), array(), array('text' => lang('How long'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Photos'), array(), array('text' => lang('Photos'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Price'), array(), array('text' => lang('Price'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Shoot Deposit'), array(), array('text' => lang('Shoot Deposit'), '_attrs' => array($attr('scope', 'col')))),
                                $el('th', '', lang('Action'), array($span(lang('Book this package'), 'visually-hidden', lang('Text'))), array('_attrs' => array($attr('scope', 'col')))),
                            )),
                        )),
                        $el('tbody', '', lang('Table Body'), $package_rows),
                    )),
                )),
                $para(lang('The deposit holds your date once we confirm it and is taken off the price; the rest is paid on the day of the shoot.'), 'small text-body-secondary mt-3 mb-0'),
            )),
        ), $searchable('ph-prices', lang('deposit, down payment, balance, price list'))),
        $el('section', 'py-5', lang('Add-ons'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Make it yours'), 'pph-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('Add to any package'), 'pph-display h2 mb-3'),
                        $para(lang('Name what you would like to add in your booking request; we add it to the price when we confirm.'), 'text-body-secondary mb-3'),
                        $link(lang('Frequently Asked Questions'), '{{page:faq}}', 'link-primary fw-semibold'),
                    ), '', '5'),
                    $col(array(
                        $rise($el('ul', 'list-group list-group-flush border rounded-4 overflow-hidden', lang('Add-ons'), $addon_items), 60),
                    ), '', '7'),
                ), '4', '', 'g-lg-5'),
            )),
        ), $searchable('ph-addons', lang('album, prints, extra photos, express, extra hour'))),
    )),
);

// ── How it works ────────────────────────────────────────────────────────

$prepare_tips = array(
    array('icon' => 'bi-palette', 'title' => lang('What to wear'), 'text' => lang('Plain colours that go together, no large logos, and something you feel good in. Bring a second outfit if your package allows it.')),
    array('icon' => 'bi-geo', 'title' => lang('Choosing a place'), 'text' => lang('Somewhere that means something to you, or our studio; we suggest places with good light near you.')),
    array('icon' => 'bi-balloon', 'title' => lang('With children'), 'text' => lang('Bring snacks and a favourite toy; we plan breaks and work around naps and moods.')),
    array('icon' => 'bi-list-check', 'title' => lang('Photos that matter'), 'text' => lang('Tell us the people, groups and moments you must have; we keep the list with us on the day.')),
);
$prepare_tiles = array();
foreach ($prepare_tips as $t) $prepare_tiles[] = $feature_tile($t['icon'], $t['title'], $t['text']);

$public_pages[] = array(
    'key'              => 'experience',
    'name'             => lang('how-it-works'),
    'folder'           => 'public',
    'title'            => lang('How It Works'),
    'meta_description' => lang('How a shoot works: asking for a date, planning, the session, choosing your favourites and downloading your photos.'),
    'tree'             => $page(array(
        $page_header(lang('How It Works'), lang('From the first message to the last download: what happens before, during and after your shoot.')),
        $el('section', 'py-5', lang('Steps'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $para(lang('Step by step'), 'pph-eyebrow text-uppercase fw-semibold text-primary mb-2'),
                        $heading('h2', lang('How a shoot works'), 'pph-display h2 mb-4'),
                        $rise($el('ol', 'list-unstyled mb-4', lang('Steps'), $shoot_step_items), 100),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Book a Shoot'), '{{page:book}}', 'btn btn-primary rounded-pill px-4'),
                            $link(lang('Book a Studio Mini Session'), '{{page:book_online}}', 'btn btn-outline-primary rounded-pill px-4'),
                        )),
                    ), '', '6'),
                    $col(array($framed($photo('steps', 1200, 900), lang('A photographer adjusting a light in the studio'))), '', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('ph-steps', lang('process, booking, how to book, what happens'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Preparing for Your Shoot'), array(
            $container(array(
                $section_header(lang('Before the day'), lang('Preparing for your shoot')),
                $rise($row($prepare_tiles, '4')),
            )),
        ), $searchable('ph-prepare', lang('what to wear, outfit, location, kids, shot list'))),
        $contact_sheet('ph-proofing', lang('After the shoot'), lang('Choosing your favourites'),
            lang('Within two weeks your proofs wait in a private gallery that only you can open after signing in. Each photo carries a frame number.'),
            lang('proofs, selection, favourites, retouch, private gallery')),
        $story_band('ph-delivery', lang('Your photos'), lang('Downloads, prints and albums'),
            array(lang('Your edited photos arrive in the same private gallery in full size, ready to download, share and print.'), lang('Prints and a printed album can be added to any package; we keep a backup of your gallery until you have downloaded it.')),
            $photo('delivery', 1200, 900), lang('A photo album open on a wooden table'), false, array(),
            lang('download, delivery, full resolution, prints, album, backup')),
    )),
);

// ── About ───────────────────────────────────────────────────────────────

$about_values = array(
    array('icon' => 'bi-eye', 'title' => lang('Real moments'), 'text' => lang('We guide gently and then wait: the photos you keep are rarely the posed ones.')),
    array('icon' => 'bi-hand-thumbs-up', 'title' => lang('Honest prices'), 'text' => lang('Every package says what it includes; no extra charges for the photos we took for you.')),
    array('icon' => 'bi-lock', 'title' => lang('Your photos stay yours'), 'text' => lang('We share a photo publicly only with your yes, and your gallery opens only to you.')),
);
$about_value_tiles = array();
foreach ($about_values as $v) $about_value_tiles[] = $feature_tile($v['icon'], $v['title'], $v['text'], '4');

$public_pages[] = array(
    'key'              => 'about',
    'name'             => lang('about'),
    'folder'           => 'public',
    'title'            => lang('About Us'),
    'meta_description' => lang('A small photography studio working in natural light: unhurried sessions, photos edited by hand and private galleries.'),
    'tree'             => $page(array(
        $page_header(lang('About Us'), $studio['tagline'] . '.'),
        $story_band('ph-our-story', lang('Our story'), lang('One camera and a north window'),
            array($studio['story'], lang('We photograph a few sessions a week, so every client has our full attention from the first message to the last download.')),
            $photo('about-studio', 1200, 900), lang('The studio with its large north window and a plain backdrop'), false,
            array($link(lang('Would you like to meet first?'), '{{page:contact}}', 'btn btn-outline-primary rounded-pill px-4')),
            lang('history, founder, team, who we are, studio')),
        $el('section', 'py-4 border-top border-bottom bg-body-tertiary', lang('Figures'), array(
            $container(array($figure_row())),
        )),
        $el('section', 'py-5', lang('Our Values'), array(
            $container(array(
                $section_header(lang('What we care about'), lang('How we work')),
                $rise($row($about_value_tiles, '4')),
            )),
        ), $searchable('ph-values', lang('values, consent, privacy, pricing'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Why Choose Us'), array(
            $container(array(
                $section_header(lang('The way we work'), lang('Why clients choose us')),
                $rise($row($why_tiles, '4')),
            )),
        ), $searchable('ph-about-why', lang('natural light, retouching, private gallery, relaxed session'))),
        $el('section', 'py-5', lang('Testimonials'), array(
            $container(array(
                $section_header(lang('Our clients'), lang('What clients say')),
                $rise($row($testimonial_cols, '4'), 120),
            )),
        )),
    )),
);

// ── Questions ───────────────────────────────────────────────────────────
// Three accordions: booking, the session, photos and payments. Each
// question is a search section of its own ($faq).
$faq_group = function ($id, $title, $items) use ($el, $heading, $faq) {
    $children = array();
    foreach ($items as $n => $q) $children[] = $faq($n + 1, $q[0], $q[1], $q[2], $id);
    return $el('div', 'mb-5', $title, array(
        $heading('h2', $title, 'pph-display h4 mb-3'),
        $el('div', 'accordion', lang('Questions'), $children, array('id' => $id)),
    ));
};

$public_pages[] = array(
    'key'              => 'faq',
    'name'             => lang('faq'),
    'folder'           => 'public',
    'title'            => lang('Frequently Asked Questions'),
    'meta_description' => lang('Answers about booking a shoot, the session, proofing, downloading your photos, prints and the deposit.'),
    'tree'             => $page(array(
        $page_header(lang('Frequently Asked Questions'), lang('Booking, the session, your photos and payments. Not answered here? Write to us.')),
        $el('section', 'py-5', lang('Questions'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $faq_group('ph-faq-booking', lang('Booking a shoot'), array(
                            array(lang('Is my request a confirmed date?'), lang('Not yet: we check the date and answer within a day. Your date is held when your request reads "Confirmed".'), lang('confirmation, availability, request, date')),
                            array(lang('How do I follow my request?'), lang('Sending the form opens an account for you. Under My Account you see the state of each request and can write to us there.'), lang('my account, status, follow, message')),
                            array(lang('Can I book a mini session online?'), lang('Yes: studio mini sessions can be booked straight away at a free time; other sessions start with a request so we can plan them with you.'), lang('online booking, free time, appointment, mini session')),
                            array(lang('Do you travel for a shoot?'), lang('Yes: family, wedding and brand sessions happen where you choose. Travel within the city is included; further away we agree it with you.'), lang('travel, location, outside, venue')),
                        )),
                        $faq_group('ph-faq-session', lang('The session'), array(
                            array(lang('We are not used to being photographed. Is that a problem?'), lang('Not at all: most clients say so. We guide you through every pose and leave time for you to relax.'), lang('nervous, camera shy, posing')),
                            array(lang('What if it rains on the day?'), lang('Outdoor sessions move to another day at no cost, or into the studio if you prefer.'), lang('weather, rain, reschedule')),
                            array(lang('Can we bring our dog?'), lang('Of course; tell us in your request so we plan a place where dogs are welcome.'), lang('pets, dog, animals')),
                        )),
                        $faq_group('ph-faq-photos', lang('Your photos'), array(
                            array(lang('When do I receive my photos?'), lang('Proofs within two weeks, the edited photos within two weeks of your selection; weddings take up to six weeks. An add-on brings your gallery within three days.'), lang('delivery time, how long, turnaround')),
                            array(lang('How do I choose my favourites?'), lang('Open your private gallery in the Client Area, note the frame numbers you love and send them with the selection form.'), lang('proofing, selection, favourites, frame number')),
                            array(lang('Can I download and print my photos?'), lang('Yes: every edited photo downloads in full size, and you may print them for yourself as often as you like.'), lang('download, print, full resolution, usage rights')),
                            array(lang('Who can see my gallery?'), lang('Only you, after signing in. If you want to share it with family or guests, ask us and we open it to them too.'), lang('privacy, private gallery, share, password')),
                        )),
                        $faq_group('ph-faq-payments', lang('Payments'), array(
                            array(lang('Is there a deposit?'), sprintf(lang('Yes: from %1$s for a mini session to %2$s for a wedding, paid once we confirm your date and taken off the price.'), $money($package_deposits['mini']), $money($package_deposits['wedding'])), lang('deposit, down payment, balance')),
                            array(lang('What if I need to cancel?'), lang('Move your date once at no cost. If you cancel more than four weeks before, the deposit is returned; closer to the date it is kept.'), lang('cancel, cancellation, refund, change date')),
                        )),
                    ), '', '8'),
                ), '4', 'center'),
            )),
        )),
    )),
);

// The home page's lists: its featured galleries (home_galleries) come with
// the galleries, its journal (home_posts) and the Google reviews
// (google_reviews) with the standard pages; this part places no widget of
// its own.
$public_widgets = array();

return array('pages' => $public_pages, 'widgets' => $public_widgets);
