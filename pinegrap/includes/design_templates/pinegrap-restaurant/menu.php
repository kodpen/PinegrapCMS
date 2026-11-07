<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Design template "Restaurant", its menu and its events: the menu with a
 * tab per course, the dish's own page and the staff page that adds a dish
 * (the Menu form, a collection, with the sample dishes written in when it
 * is made); the coming events, the event's own page and the staff page
 * that adds one (the Events form, with the sample events); and the lists
 * the home page places (the favourite dishes and the next events).
 * Included after parts.php in the same scope; returns
 * array('pages' => ..., 'widgets' => ...).
 *
 * A dish is a record of the Menu form. Its course is a pick list whose
 * answers are the words parts.php gives, so each tab is a list filtered by
 * one course. The price stays words written with the currency (no
 * shop product per dish); the diet tags are boxes, so a dish carries
 * several.
 *
 * An event is a record of the Events form: its date is a date field, so
 * the lists show the events from today on, the nearest first, and an
 * event leaves them once its day has passed. An event card opens the
 * reservation with the event chosen (?event=, the reservation form's
 * Event field reads it).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

if (!defined('PG_FUNCTIONS_DIR')) {
    exit;
}

$menu_pages = array();

// ── Pieces ──────────────────────────────────────────────────────────────

// A date field in the format given (date()'s letters), in place of the
// site's own date format.
$bind_date = function ($token, $format) {
    return array('_bindings' => array('text' => $token), '_bindFormats' => array('text' => $format));
};

// A link to a template page that sends values of the record or list row
// it is drawn in: $params maps the address name to the token it takes
// (the renderer's __page_link, filled per row in a list).
$page_link = function ($page_key, $params) {
    $list = array();
    foreach ($params as $name => $token) $list[] = array('name' => $name, 'token' => $token);
    return array(
        '_bindings'   => array('href' => '__page_link'),
        '_bindParams' => array('href' => array('page_id' => '{{tab:' . $page_key . '}}', 'params' => $list)),
    );
};

// A group of boxes that may all be ticked, under its legend: the legend
// names the field (the dish's diet tags).
$checkboxes = function ($id, $name, $legend, $options) use ($el, $attr) {
    $children = array($el('legend', 'form-label fs-6 mb-2', lang('Label'), array(), array('text' => $legend)));
    foreach (array_values($options) as $n => $o) {
        $rid = $id . '-' . ($n + 1);
        $children[] = $el('div', 'form-check form-check-inline', lang('Option'), array(
            $el('input', 'form-check-input', lang('Field'), array(), array('id' => $rid, '_attrs' => array(
                $attr('type', 'checkbox'), $attr('name', $name), $attr('value', $o),
            ))),
            $el('label', 'form-check-label', lang('Label'), array(), array('text' => $o, '_attrs' => array($attr('for', $rid)))),
        ));
    }
    return $el('fieldset', 'mb-3', $legend, $children);
};

// The key of the diet tags: each tag with its mark, in one line.
$diet_key = function () use ($el, $span, $icon, $diets) {
    $children = array($span(lang('Diet tags'), 'small fw-semibold text-body-secondary me-1', lang('Label')));
    foreach ($diets as $d) {
        $children[] = $el('span', 'badge rounded-pill bg-' . $d[2] . '-subtle text-' . $d[2] . '-emphasis d-inline-flex align-items-center gap-1', $d[0], array(
            $icon($d[1]),
            $span($d[0], '', lang('Text')),
        ));
    }
    return $el('div', 'd-flex flex-wrap align-items-center gap-2', lang('Diet Key'), $children);
};

// The kinds of event, the Events form's pick list, in the words parts.php
// gives the sample events.
$event_kinds = array();
foreach ($events as $e) $event_kinds[$e['kind']] = $e['kind'];
$event_kinds = array_values($event_kinds);

// The sample events as records of the Events form, each dated from the
// day the template opens.
$sample_events = array();
foreach ($events as $n => $e) {
    $sample_events[] = array(
        'title'      => $e['title'],
        'event_date' => $event_on($e['weekday'], $e['weeks']),
        'event_time' => $e['time'],
        'kind'       => $e['kind'],
        'price'      => $per_guest($e['price']),
        'summary'    => $e['text'],
        'image'      => $photo('event-' . ($n + 1), 1200, 675),
    );
}
$event_sample = $sample_events[0];

// An event of the lists: its picture with the date on a leaf over it, the
// kind, the title linking the event page, the time, the words, the price
// and the way to reserve a table for it.
$event_card = function () use ($el, $span, $para, $link, $icon, $col, $image, $bind, $bind_date, $attr, $page_link, $event_sample) {
    return $col(array(
        $el('article', 'card h-100 border-0 shadow-sm overflow-hidden prt-lift', lang('Event Card'), array(
            $el('div', 'position-relative', lang('Picture Area'), array(
                $el('div', 'ratio ratio-16x9 bg-body-tertiary', lang('Picture Frame'), array(
                    $image($event_sample['image'], $event_sample['title'], 'object-fit-cover',
                        array('_bindings' => array('src' => 'image', 'alt' => 'title'), '_attrs' => array($attr('loading', 'lazy')))),
                )),
                $el('div', 'position-absolute top-0 start-0 m-3 text-center rounded-3 bg-body text-body px-3 py-2 shadow-sm', lang('Date Leaf'), array(
                    $span(date('d', strtotime($event_sample['event_date'])), 'prt-display d-block fs-3 lh-1', lang('Day'), $bind_date('event_date', 'd')),
                    $span(date('m.Y', strtotime($event_sample['event_date'])), 'd-block small fw-semibold text-primary mt-1', lang('Month'), $bind_date('event_date', 'm.Y')),
                )),
            )),
            $el('div', 'card-body d-flex flex-column p-4', lang('Card Body'), array(
                $span($event_sample['kind'], 'badge rounded-pill bg-primary-subtle text-primary-emphasis align-self-start mb-2', lang('Kind'), $bind('kind')),
                $el('h3', 'prt-display h4 position-static mb-2', lang('Event Title'), array(
                    $link($event_sample['title'], '#', 'stretched-link link-body-emphasis text-decoration-none', array(),
                        array('_bindings' => array('text' => 'title', 'href' => 'form_item_view'))),
                )),
                $el('p', 'd-flex align-items-center gap-1 small text-body-secondary mb-2', lang('Time'), array(
                    $icon('bi-clock'),
                    $span($event_sample['event_time'], '', lang('Time'), $bind('event_time')),
                )),
                $para($event_sample['summary'], 'small mb-3', $bind('summary')),
                $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-auto', lang('Card Footer'), array(
                    $span($event_sample['price'], 'prt-price small fw-semibold', lang('Price'), $bind('price')),
                    // Above the card's stretched link, so it opens the
                    // reservation with the event chosen (?event=).
                    $link(lang('Reserve a place'), '{{page:reserve}}', 'btn btn-sm btn-outline-primary position-relative', array(),
                        $page_link('reserve', array('event' => 'title'))),
                )),
            )),
        )),
    ), '6', '4');
};

// A list of the Menu form's dishes: the dish page, in the order they were
// added (the sample's order, the menu's own).
$dish_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:dish_new}}',
        'detail_page_id'      => '{{tab:dish}}',
        'order_by_field'      => 'submitted_date_and_time',
        'order_by_direction'  => 'ASC',
    ), $more);
};
// A list of the Events form's events from today on, the nearest first.
$event_list_config = function ($more) {
    return array_merge(array(
        'regionType'          => 'form_list_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'detail_page_id'      => '{{tab:event}}',
        'order_by_field'      => 'event_date',
        'order_by_direction'  => 'ASC',
        'filters'             => array(
            array('field' => 'event_date', 'operator' => 'is greater than or equal to', 'value' => '', 'dynamic_value' => 'current date'),
        ),
        'empty_message'       => lang('No events planned right now. Subscribe to the newsletter to hear about the next ones.'),
    ), $more);
};

// A staff page with one form in a card.
$menu_staff_page = function ($key, $name, $title, $lead, $widget_key, $form) use ($staff_page, $page_header, $el, $container, $row, $col, $widget) {
    return array(
        'key'     => $key,
        'name'    => $name,
        'folder'  => 'private',
        'title'   => $title,
        'search'  => false,
        'sitemap' => false,
        'noindex' => true,
        'form'    => $form,
        'tree'    => $staff_page(array(
            $page_header($title, $lead),
            $el('section', 'py-5', lang('Content'), array(
                $container(array(
                    $row(array(
                        $col(array(
                            $el('div', 'p-4 rounded-3 border', lang('Form Card'), array($widget($widget_key))),
                        ), '', '8'),
                    ), '', 'center'),
                )),
            )),
        )),
    );
};

// A record page's not-found box: a record deleted or renamed.
$not_found = function ($icon_name, $title, $text, $back_text, $back_page) use ($el, $icon, $heading, $para, $link, $bind, $show_when) {
    return $el('div', 'text-center py-5', lang('Not Found'), array(
        $icon($icon_name, 'display-5 text-body-tertiary'),
        $heading('h2', $title, 'h3 fw-semibold mt-3'),
        $para($text, 'text-body-secondary mb-4', $bind('not_found')),
        $link($back_text, '{{page:' . $back_page . '}}', 'btn btn-primary px-4'),
    ), $show_when('record_not_found'));
};

// A record page's top line: the way back to the list and the Edit button.
$record_toolbar = function ($back_text, $back_page) use ($el, $icon, $span, $edit_button) {
    return $el('div', 'd-flex flex-wrap align-items-center justify-content-between gap-2 mb-4', lang('Toolbar'), array(
        $el('a', 'd-inline-flex align-items-center gap-1 small fw-semibold link-primary text-decoration-none', lang('Link'), array(
            $icon('bi-arrow-left'),
            $span($back_text, '', lang('Link Text')),
        ), array('href' => '{{page:' . $back_page . '}}')),
        $edit_button(),
    ));
};

// ── The menu ────────────────────────────────────────────────────────────
// The diet key, a tab for each course (each a list of the Menu form's
// dishes filtered by the course) and what to know before ordering.
$menu_tabs  = array();
$menu_panes = array();
$n = 0;
foreach ($courses as $key => $c) {
    $pane = 'rt-menu-' . $key;
    $menu_tabs[] = $el('li', 'nav-item', lang('Nav Item'), array(
        $el('button', 'nav-link d-inline-flex align-items-center gap-2 text-nowrap' . ($n === 0 ? ' active' : ''), $c['title'], array(
            $icon($c['icon']),
            $span($c['title'], '', lang('Text')),
        ), array('id' => $pane . '-tab', '_attrs' => array(
            $attr('type', 'button'),
            $attr('role', 'tab'),
            $attr('data-bs-toggle', 'pill'),
            $attr('data-bs-target', '#' . $pane),
            $attr('aria-controls', $pane),
            $attr('aria-selected', $n === 0 ? 'true' : 'false'),
        ))),
    ), array('_attrs' => array($attr('role', 'presentation'))));
    $menu_panes[] = $el('div', 'tab-pane fade' . ($n === 0 ? ' show active' : ''), $c['title'], array(
        $el('p', 'd-flex align-items-start gap-2 small text-body-secondary mb-2', lang('Course Note'), array(
            $icon('bi-info-circle', 'text-primary mt-1'),
            $span($c['note'], '', lang('Text')),
        )),
        $widget('menu_' . $key),
    ), array('id' => $pane, '_attrs' => array(
        $attr('role', 'tabpanel'),
        $attr('aria-labelledby', $pane . '-tab'),
        $attr('tabindex', '0'),
    )));
    $n++;
}

$menu_pages[] = array(
    'key'              => 'menu',
    'name'             => lang('menu'),
    'folder'           => 'public',
    'title'            => lang('The Menu'),
    'meta_description' => lang('The restaurant\'s menu: bread and small bites, starters, wood-fired main courses and desserts, with prices and diet tags.'),
    'tree'             => $page(array(
        $page_header(lang('The Menu'), lang('Written each week after the market. Every dish carries its diet tags; ask us about anything else.'), array(
            $el('div', 'd-flex flex-wrap gap-2 mt-3', lang('Buttons'), array(
                $link(lang('Tasting Menus'), '{{page:tasting}}', 'btn btn-outline-primary px-4'),
                $link(lang('Wine List'), '{{page:wine}}', 'btn btn-outline-primary px-4'),
            )),
        )),
        $el('section', 'py-5', lang('The Menu'), array(
            $container(array(
                $diet_key(),
                $el('ul', 'nav nav-pills gap-2 mt-4 mb-4', lang('Menu Tabs'), $menu_tabs, array('_attrs' => array(
                    $attr('role', 'tablist'),
                    $attr('aria-label', lang('Courses of the menu')),
                ))),
                $el('div', 'tab-content', lang('Menu Courses'), $menu_panes),
                $para(lang('Prices include VAT. The menu changes with the market; your waiter has tonight\'s.'), 'small text-body-secondary mt-4 mb-0'),
            )),
        ), $searchable('rt-menu', lang('menu, food, dishes, prices, vegetarian, vegan, gluten free'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Before You Order'), array(
            $container(array(
                $row(array(
                    $col(array(
                        $heading('h2', lang('Before you order'), 'prt-display h3 mb-3'),
                        $check_list(array(
                            lang('Tell us about an allergy when you book; the kitchen plans your plates'),
                            lang('Most dishes come as a small plate for children'),
                            lang('The bread is baked here every morning and comes back free'),
                            lang('Ask the sommelier for a glass to go with each course'),
                        )),
                    ), '', '7'),
                    $col(array(
                        $el('div', 'p-4 p-lg-5 rounded-3 bg-primary-subtle', lang('Text'), array(
                            $heading('h2', lang('Can\'t choose?'), 'prt-display h4 mb-2'),
                            $para(lang('Let the kitchen cook for you: the tasting menus bring the best of the week to the whole table.'), 'mb-3'),
                            $link(lang('See the tasting menus'), '{{page:tasting}}', 'btn btn-primary px-4'),
                        )),
                    ), '', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            )),
        ), $searchable('rt-menu-notes', lang('allergy, children, bread, wine'))),
    )),
);

// One dish: its photo, course, name, price, words, tags and the ways to
// book a table or go back to the menu.
$menu_pages[] = array(
    'key'              => 'dish',
    'name'             => lang('dish'),
    'folder'           => 'public',
    'title'            => lang('Menu Dish'),
    'meta_description' => lang('A dish of the restaurant\'s menu: what is in it, its price and its diet tags.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('dish_detail'),
    )),
);

$menu_pages[] = $menu_staff_page('dish_new', lang('new-dish'), lang('New Dish'),
    lang('A new dish appears in its course of the menu as soon as you send it. To change or remove one, open the Menu form in the control panel.'), 'dish_form',
    array(
        'form_name'            => lang('Menu'),
        'collection'           => 1,
        'confirmation_message' => lang('The dish is on the menu now.'),
        'confirmation_page_id' => '{{tab:menu}}',
        // The template's dishes are written in when the form is made, so
        // the menu and the home page do not open empty
        // (pg_cf_seed_sample_records()).
        'sample_records'       => $dish_records,
        // The photo is the dish's share image (pg_cf_share_image_field_id()).
        'share_image_field'    => 'photo',
    ));

// ── Events ──────────────────────────────────────────────────────────────

$menu_pages[] = array(
    'key'              => 'events',
    'name'             => lang('events'),
    'folder'           => 'public',
    'title'            => lang('Events'),
    'meta_description' => lang('Winemaker\'s dinners, cooking classes, supper clubs and music at lunch: the coming events at the restaurant and how to join.'),
    'tree'             => $page(array(
        $page_header(lang('Events'), lang('Dinners with the growers, mornings in the kitchen with the chef and long lunches with music. Reserve a place to join.')),
        $el('section', 'py-5', lang('Coming Events'), array(
            $container(array($widget('events_list'))),
        ), $searchable('rt-events', lang('events, wine dinner, cooking class, music, supper club'))),
        $el('section', 'py-5 bg-body-tertiary', lang('Private Dining'), array(
            $container(array(
                $el('div', 'd-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3', lang('Text'), array(
                    $el('div', '', lang('Text'), array(
                        $heading('h2', lang('An event of your own?'), 'prt-display h4 mb-1'),
                        $para(lang('Birthdays, weddings and company dinners in the wine cellar, the garden room or the whole restaurant.'), 'text-body-secondary mb-0'),
                    )),
                    $link(lang('Plan a private dinner'), '{{page:private_dining}}', 'btn btn-primary px-4 text-nowrap'),
                )),
            )),
        )),
    )),
);

// One event: its picture, kind, title, date and time, price, words and the
// way to reserve a place.
$menu_pages[] = array(
    'key'              => 'event',
    'name'             => lang('event'),
    'folder'           => 'public',
    'title'            => lang('Event'),
    'meta_description' => lang('An event at the restaurant: the date, the time, the price and how to reserve a place.'),
    'sitemap'          => false,
    'tree'             => $page(array(
        $widget('event_detail'),
    )),
);

$menu_pages[] = $menu_staff_page('event_new', lang('new-event'), lang('New Event'),
    lang('The event appears on the events page and the home page until its day has passed.'), 'event_form',
    array(
        'form_name'            => lang('Events'),
        'collection'           => 1,
        'confirmation_message' => lang('The event is published.'),
        'confirmation_page_id' => '{{tab:events}}',
        'sample_records'       => $sample_events,
        'share_image_field'    => 'image',
    ));

// ── Widgets ─────────────────────────────────────────────────────────────

$menu_widgets = array();

// A tab of the menu for each course: the dishes of that course.
foreach ($courses as $key => $c) {
    $menu_widgets['menu_' . $key] = array(
        'page'   => 'menu',
        'slug'   => lang('form-list'),
        'config' => $dish_list_config(array(
            'items_per_page' => 50,
            'filters'        => array(array('field' => 'course', 'operator' => 'is equal to', 'value' => $c['title'], 'dynamic_value' => '')),
            'empty_message'  => lang('Nothing in this course tonight; ask your waiter for the kitchen\'s specials.'),
        )),
        'tree'   => $widget_root(array($row(array($loop(array($dish_line()))), '4'))),
    );
}

// The home page's favourites: the dishes marked for it, four of them.
$menu_widgets['home_dishes'] = array(
    'page'   => 'home',
    'slug'   => lang('favourites'),
    'config' => $dish_list_config(array(
        'items_per_page' => 4,
        'max_results'    => 4,
        'filters'        => array(array('field' => 'featured', 'operator' => 'is equal to', 'value' => lang('Yes'), 'dynamic_value' => '')),
        'empty_message'  => lang('Mark a dish for the home page and it appears here.'),
    )),
    'tree'   => $widget_root(array($row(array($loop(array($dish_card()))), '4'))),
);

// The staff's Menu form: one dish. Its course puts it in its tab of the
// menu; "Show on the home page" among the favourites.
$menu_widgets['dish_form'] = array(
    'page'   => 'dish_new',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $form_step(1, lang('The dish')),
        $field('rt-dish-name', lang('Name'), pg_cf_with_role($input('rt-dish-name', 'text', 'name', 'off'), 'title')),
        $row(array(
            $col(array($field('rt-dish-course', lang('Menu Course'), pg_cf_with_role($select('rt-dish-course', 'course', lang('Choose a part of the menu…'), $course_titles), 'category'))), '6'),
            $col(array($field('rt-dish-price', lang('Price'), $input('rt-dish-price', 'text', 'price', 'off', true, array(), array($attr('aria-describedby', 'rt-dish-price-help'))),
                'mb-3', lang('Write it with the currency, as the menu shows it.'))), '6'),
        ), '3'),
        $field('rt-dish-description', lang('Description'), pg_cf_with_role($textarea('rt-dish-description', 'description', 2), 'description'), 'mb-3',
            lang('One or two lines: what is on the plate.')),
        $form_step(2, lang('Tags')),
        $checkboxes('rt-dish-diet', 'diet', lang('Diet tags'), $diet_titles),
        $row(array(
            $col(array($field('rt-dish-badge', lang('Badge'), $select('rt-dish-badge', 'badge', lang('No badge'), $badges, false))), '6'),
            $col(array($field('rt-dish-featured', lang('Show on the home page'), $select('rt-dish-featured', 'featured', '', array(lang('No'), lang('Yes')), false, lang('No')))), '6'),
        ), '3'),
        $form_step(3, lang('Photo')),
        $field('rt-dish-photo', lang('Photo'), pg_cf_with_role($file('rt-dish-photo', 'photo', 'public', 'image/*'), 'media'), 'mb-3',
            lang('A landscape photo works best; it is also the share image.')),
        $submit(lang('Add to the Menu')),
        $loop(array()),
    )),
);

// One dish on its own page.
$dish_sample = $dishes['lamb'];
$menu_widgets['dish_detail'] = array(
    'page'   => 'dish',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:dish_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This dish is no longer on the menu. The kitchen may have moved on to the next season.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $not_found('bi-egg-fried', lang('Dish not found'), lang('This dish is no longer on the menu. The kitchen may have moved on to the next season.'), lang('See the menu'), 'menu'),
            $el('article', '', lang('Menu Dish'), array(
                $record_toolbar(lang('The whole menu'), 'menu'),
                $row(array(
                    $col(array(
                        $optional_image('photo', 'name', 'rounded-3 object-fit-cover', 'ratio ratio-4x3',
                            $photo('dish-lamb', 1200, 900), lang('Photo')),
                    ), '6', '6'),
                    $col(array(
                        $span($courses[$dish_sample['course']]['title'], 'badge bg-primary-subtle text-primary-emphasis mb-2', lang('Menu Course'), $bind('course')),
                        $heading('h1', $dish_sample['name'], 'prt-display display-6 mb-2', $bind('name')),
                        $para($money($dish_sample['price']), 'prt-price h4 fw-semibold text-primary mb-3', $bind('price')),
                        $para($dish_sample['text'], 'lead text-body-secondary mb-3', $bind('description')),
                        $el('div', 'd-flex flex-wrap gap-2 mb-4', lang('Tags'), array(
                            $span($dish_sample['badge'], 'pg-hide-if-empty badge text-bg-warning', lang('Badge'), $bind('badge')),
                            $el('span', 'pg-hide-if-empty badge rounded-pill bg-success-subtle text-success-emphasis', lang('Diet'), array(
                                $icon('bi-leaf', 'me-1'),
                                $span(implode(', ', $dish_sample['diet']), 'pg-field-value', lang('Value'), $bind('diet')),
                            )),
                        )),
                        $para(lang('Allergies? Tell us when you book and the kitchen plans your plate.'), 'small text-body-secondary mb-4'),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Reserve a Table'), '{{page:reserve}}', 'btn btn-primary px-4'),
                            $link(lang('See the menu'), '{{page:menu}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '6', '6'),
                ), '4', '', 'g-lg-5 align-items-center'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

// The events page: every event from today on, the nearest first.
$menu_widgets['events_list'] = array(
    'page'   => 'events',
    'slug'   => lang('form-list'),
    'config' => $event_list_config(array('items_per_page' => 12)),
    'tree'   => $widget_root(array($row(array($loop(array($event_card()))), '4'))),
);

// The home page's next three events.
$menu_widgets['home_events'] = array(
    'page'   => 'home',
    'slug'   => lang('coming-events'),
    'config' => $event_list_config(array('items_per_page' => 3, 'max_results' => 3)),
    'tree'   => $widget_root(array($row(array($loop(array($event_card()))), '4'))),
);

// The staff's Events form: one event.
$menu_widgets['event_form'] = array(
    'page'   => 'event_new',
    'slug'   => 'form',
    'config' => array('regionType' => 'custom_form', 'form_source' => 'page'),
    'tree'   => $widget_root(array(
        $field('rt-event-title', lang('Title'), pg_cf_with_role($input('rt-event-title', 'text', 'title', 'off'), 'title')),
        $row(array(
            $col(array($field('rt-event-date', lang('Date'), $input('rt-event-date', 'date', 'event_date', 'off'))), '6'),
            $col(array($field('rt-event-time', lang('Starts at'), $input('rt-event-time', 'text', 'event_time', 'off', true, array(), array($attr('placeholder', '19:30'))))), '6'),
            $col(array($field('rt-event-kind', lang('Kind'), pg_cf_with_role($select('rt-event-kind', 'kind', lang('Choose…'), $event_kinds), 'category'))), '6'),
            $col(array($field('rt-event-price', lang('Price'), $input('rt-event-price', 'text', 'price', 'off', false))), '6'),
        ), '3'),
        $field('rt-event-summary', lang('Summary'), pg_cf_with_role($textarea('rt-event-summary', 'summary', 3), 'description')),
        $field('rt-event-image', lang('Image'), pg_cf_with_role($file('rt-event-image', 'image', 'public', 'image/*'), 'media')),
        $submit(lang('Publish')),
        $loop(array()),
    )),
);

// One event on its own page.
$menu_widgets['event_detail'] = array(
    'page'   => 'event',
    'slug'   => lang('form-detail'),
    'config' => array(
        'regionType'          => 'form_item_view',
        'custom_form_page_id' => '{{tab:event_new}}',
        'access_control'      => 'public',
        'not_found_message'   => lang('This event could not be found. It may have passed, or the page was renamed.'),
    ),
    'tree'   => $widget_root(array(
        $container(array(
            $not_found('bi-calendar-x', lang('Event not found'), lang('This event could not be found. It may have passed, or the page was renamed.'), lang('See the events'), 'events'),
            $el('article', '', lang('Event'), array(
                $record_toolbar(lang('All events'), 'events'),
                $row(array(
                    $col(array(
                        $optional_image('image', 'title', 'rounded-3 object-fit-cover', 'ratio ratio-16x9',
                            $event_sample['image'], lang('Image')),
                    ), '6', '7'),
                    $col(array(
                        $span($event_sample['kind'], 'badge bg-primary-subtle text-primary-emphasis mb-2', lang('Kind'), $bind('kind')),
                        $heading('h1', $event_sample['title'], 'prt-display display-6 mb-3', $bind('title')),
                        $el('ul', 'list-unstyled mb-3', lang('When'), array(
                            $el('li', 'd-flex align-items-center gap-2 mb-2', lang('Date'), array(
                                $icon('bi-calendar3', 'text-primary'),
                                $span($event_sample['event_date'], 'fw-semibold', lang('Date'), $bind('event_date')),
                            )),
                            $el('li', 'd-flex align-items-center gap-2 mb-2', lang('Time'), array(
                                $icon('bi-clock', 'text-primary'),
                                $span($event_sample['event_time'], '', lang('Time'), $bind('event_time')),
                            )),
                            $el('li', 'pg-hide-if-empty d-flex align-items-center gap-2 mb-0', lang('Price'), array(
                                $icon('bi-tag', 'text-primary'),
                                $span($event_sample['price'], 'pg-field-value prt-price', lang('Price'), $bind('price')),
                            )),
                        )),
                        $para($event_sample['summary'], 'lead text-body-secondary mb-4', $bind('summary')),
                        $el('div', 'd-flex flex-wrap gap-2', lang('Buttons'), array(
                            $link(lang('Reserve a place'), '{{page:reserve}}', 'btn btn-primary px-4', array(),
                                $page_link('reserve', array('event' => 'title'))),
                            $link(lang('All events'), '{{page:events}}', 'btn btn-outline-primary px-4'),
                        )),
                    ), '6', '5'),
                ), '4', '', 'g-lg-5 align-items-center'),
            ), $show_when('record_found')),
        ), 'py-5'),
    )),
);

return array('pages' => $menu_pages, 'widgets' => $menu_widgets);
