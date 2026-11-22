<?php
/**
 * Pinegrap - Enterprise Website Platform
 *
 * Carrier scorecard: per carrier, and under it per province, the parcels
 * shipped in a month, the share delivered on time, the median days, the
 * share with a delivery problem and the share returned
 * (includes/fn/delivery_scorecard.php). "Last 12 months" shows one of those
 * figures per carrier and month. Above the table, the shipping methods
 * whose transit days differ from what their carrier measured. Excel gives
 * the table as shown. Nothing is changed here.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

include('init.php');
$user = validate_user();

validate_ecommerce_report_access();

if (!ECOMMERCE || !ECOMMERCE_SHIPPING) {
    output_error(lang('Shipping is not enabled on this site.'));
}

$now = time();
$month = pg_delivery_scorecard_month($_GET['month'] ?? '', $now);
$year = (($_GET['view'] ?? '') === 'year');
$figure_keys = pg_delivery_scorecard_figure_keys();
$figure = is_string($_GET['figure'] ?? null) ? $_GET['figure'] : '';

if (!isset($figure_keys[$figure])) {
    $figure = 'on_time';
}

$months = pg_delivery_scorecard_months($month);

if ($year) {
    $bounds = array(pg_delivery_scorecard_bounds($months[0])[0], pg_delivery_scorecard_bounds($month)[1]);
    $view = pg_delivery_scorecard_year_view(pg_delivery_scorecard_read($bounds[0], $bounds[1], false), $months);
} else {
    $bounds = pg_delivery_scorecard_bounds($month);
    $view = pg_delivery_scorecard_month_view(pg_delivery_scorecard_read($bounds[0], $bounds[1], true), $month);
}

// The address of this screen with some of its choices changed.
$link = function ($changes) use ($month, $year, $figure) {
    $query = array_merge(array('month' => $month, 'view' => $year ? 'year' : '', 'figure' => $year ? $figure : ''), $changes);

    return 'view_carrier_scorecard.php?' . http_build_query(array_filter($query, 'strlen'));
};

$carrier_name = function ($carrier) {
    return ($carrier === '') ? lang('Carrier not known') : pg_delivery_scorecard_carrier_name($carrier);
};

// ------------------------------------------------------------------ export
if (($_GET['export'] ?? '') === 'xlsx') {
    $columns = array(lang('Carrier'));
    $rows = array();

    if ($year) {
        foreach ($months as $key) {
            $columns[] = pg_customer_value_month_name($key);
        }

        foreach ($view['carriers'] as $row) {
            $line = array($carrier_name($row['carrier']));

            foreach ($months as $key) {
                $line[] = $row['months'][$key]['enough'] ? $row['months'][$key][$figure] : null;
            }

            $rows[] = $line;
        }
    } else {
        $columns[] = lang('Province');

        foreach ($figure_keys as $label) {
            $columns[] = lang($label);
        }

        $line_of = function ($carrier, $province, $figures) use ($figure_keys) {
            $line = array($carrier, $province);

            foreach (array_keys($figure_keys) as $key) {
                $line[] = ($figures['enough'] || $key === 'parcels') ? $figures[$key] : null;
            }

            return $line;
        };

        foreach ($view['carriers'] as $row) {
            $rows[] = $line_of($carrier_name($row['carrier']), '', $row['figures']);

            foreach ($row['provinces'] as $province) {
                $rows[] = $line_of($carrier_name($row['carrier']), $province['name'], $province['figures']);
            }
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'pgd');

    if (($path === false) || !pg_xlsx_write_plain($columns, $rows, $path, lang('Carrier Scorecard'))) {
        if ($path !== false) {
            @unlink($path);
        }
        output_error(lang('The workbook could not be written.'));
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="carrier_scorecard_' . ($year ? $months[0] . '_' : '') . $month . '.xlsx"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    @unlink($path);
    exit();
}

// ------------------------------------------------------------------ display
$total = $view['total'];
$figures = array(
    pg_report_view_figure(lang('Parcels'), pg_delivery_scorecard_cell($total, 'parcels'), 'bi-box-seam', 'blue'),
    pg_report_view_figure(lang('On time'), (pg_delivery_scorecard_cell($total, 'on_time') !== '') ? pg_delivery_scorecard_cell($total, 'on_time') : '–', 'bi-clock', 'teal'),
    pg_report_view_figure(lang('Median days'), (pg_delivery_scorecard_cell($total, 'median') !== '') ? pg_delivery_scorecard_cell($total, 'median') : '–', 'bi-calendar3', 'violet'),
    pg_report_view_figure(lang('With a problem'), (pg_delivery_scorecard_cell($total, 'problem') !== '') ? pg_delivery_scorecard_cell($total, 'problem') : '–', 'bi-exclamation-triangle', 'amber'),
    pg_report_view_figure(lang('Returned'), (pg_delivery_scorecard_cell($total, 'returned') !== '') ? pg_delivery_scorecard_cell($total, 'returned') : '–', 'bi-arrow-return-left', 'rose'),
);

// A figure's cell; empty values read as a dash.
$cell = function ($figures, $key) {
    $text = pg_delivery_scorecard_cell($figures, $key);

    return '<td class="text-end">' . (($text !== '') ? h($text) : '<span class="fg-secondary">–</span>') . '</td>';
};

$few = '<td colspan="' . count($figure_keys) . '" class="fg-secondary">' . h(lang('Too few parcels')) . '</td>';

$output_rows = '';

if ($year) {
    foreach ($view['carriers'] as $row) {
        $output_rows .= '<tr><th scope="row">' . h($carrier_name($row['carrier'])) . '</th>';

        foreach ($months as $key) {
            $output_rows .= $row['months'][$key]['enough']
                ? $cell($row['months'][$key], $figure)
                : '<td class="text-end"><span class="fg-secondary" aria-hidden="true">–</span><span class="visually-hidden">' . h(lang('Too few parcels')) . '</span></td>';
        }

        $output_rows .= '</tr>';
    }

    $output_head = '<th>' . h(lang('Carrier')) . '</th>';

    foreach ($months as $key) {
        $output_head .= '<th class="text-end">' . h(pg_customer_value_month_name($key)) . '</th>';
    }

    $columns = 13;
} else {
    foreach ($view['carriers'] as $index => $row) {
        $rows_id = 'pg_scorecard_provinces_' . $index;
        $toggle = $row['provinces']
            ? ' <button type="button" class="pg-gather-toggle ms-2" data-pg-scorecard-toggle="' . $rows_id . '" aria-expanded="false">'
                . h(lang('Provinces')) . '<span class="bi bi-chevron-down" aria-hidden="true"></span></button>'
            : '';

        $output_rows .= '<tr class="pg-report-l1"><th scope="row" colspan="2">' . h($carrier_name($row['carrier'])) . $toggle . '</th>';

        if ($row['figures']['enough']) {
            foreach (array_keys($figure_keys) as $key) {
                $output_rows .= $cell($row['figures'], $key);
            }
        } else {
            $output_rows .= $few;
        }

        $output_rows .= '</tr>';

        foreach ($row['provinces'] as $province) {
            $output_rows .= '<tr class="pg-report-l2" data-pg-scorecard-of="' . $rows_id . '" hidden><td></td><th scope="row">' . h($province['name']) . '</th>';

            if ($province['figures']['enough']) {
                foreach (array_keys($figure_keys) as $key) {
                    $output_rows .= $cell($province['figures'], $key);
                }
            } else {
                $output_rows .= $few;
            }

            $output_rows .= '</tr>';
        }
    }

    $output_head = '<th colspan="2">' . h(lang('Carrier')) . '</th>';

    foreach ($figure_keys as $label) {
        $output_head .= '<th class="text-end">' . h(lang($label)) . '</th>';
    }

    $columns = 2 + count($figure_keys);
}

if ($output_rows === '') {
    $output_rows = '<tr><td colspan="' . $columns . '" class="fg-secondary">' . h(lang('No parcel was shipped in this period.')) . '</td></tr>';
}

$period = $year
    ? pg_customer_value_month_name($months[0]) . ' – ' . pg_customer_value_month_name($month)
    : pg_customer_value_month_name($month);

// Last 12 months: the figure the columns show.
$output_figure_choice = '';

if ($year) {
    $output_figure_choice = '<div class="mb-3"><span class="pg-seg" role="group" aria-label="' . h(lang('Figure')) . '">';

    foreach ($figure_keys as $key => $label) {
        $output_figure_choice .= '<a class="pg-seg-item' . (($key === $figure) ? ' is-active' : '') . '" href="' . h($link(array('figure' => $key))) . '"' . (($key === $figure) ? ' aria-current="page"' : '') . '>' . h(lang($label)) . '</a>';
    }

    $output_figure_choice .= '</span></div>';
}

// The methods whose typed transit days differ from what their carrier
// measured.
$output_arrival = '';
$arrival_checks = pg_delivery_tower_arrival_checks_read();

if ($arrival_checks) {
    $output_arrival_rows = '';

    foreach ($arrival_checks as $check) {
        $output_arrival_rows .= '<div class="pg-card-inner-row"><span class="flex-grow-1">' . h(pg_delivery_tower_arrival_sentence($check['name'], $check['check'])) . '</span>'
            . '<a class="pg-button pg-button-quiet pg-button-sm" href="' . h('edit_shipping_method.php?id=' . $check['id']) . '">' . h(lang('Open the method')) . '</a></div>';
    }

    $output_arrival = '
    <section class="pg-card pg-report-frame" aria-labelledby="pg_scorecard_arrival_title">
        <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_scorecard_arrival_title">' . h(lang('Arrival days to check')) . '</h3><span class="pg-report-frame-note">' . h(lang('Days measured over the last 90 days against the transit days typed on the method.')) . '</span></div>
        <div>' . $output_arrival_rows . '</div>
    </section>';
}

$output_stepper = '
    <div class="pg-seg" role="group" aria-label="' . h(lang('Month')) . '">
        <a class="pg-seg-item pg-report-step" href="' . h($link(array('month' => pg_delivery_scorecard_month_step($month, -1)))) . '" title="' . h(lang('Previous')) . '" aria-label="' . h(lang('Previous')) . '"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
        <a class="pg-seg-item' . (!$year ? ' is-active' : '') . '" href="' . h($link(array('view' => '', 'figure' => ''))) . '"' . (!$year ? ' aria-current="page"' : '') . '>' . h(pg_customer_value_month_name($month)) . '</a>
        <a class="pg-seg-item pg-report-step" href="' . h($link(array('month' => pg_delivery_scorecard_month_step($month, 1)))) . '" title="' . h(lang('Next')) . '" aria-label="' . h(lang('Next')) . '"><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
    </div>
    <div class="pg-seg" role="group" aria-label="' . h(lang('Period')) . '">
        <a class="pg-seg-item' . ($year ? ' is-active' : '') . '" href="' . h($link(array('view' => 'year', 'figure' => $figure))) . '"' . ($year ? ' aria-current="page"' : '') . '>' . h(lang('Last 12 months')) . '</a>
    </div>
    <span class="pg-report-range"><i class="bi bi-calendar3" aria-hidden="true"></i>' . h($period) . '</span>
    <a class="pg-button pg-button-quiet ms-auto" href="' . h($link(array('export' => 'xlsx'))) . '"><i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>' . h(lang('Excel')) . '</a>';

print
    pg_page_shell(
        array(
            'title' => lang('Carrier Scorecard'),
            'extra classes' => 'store pg-measure-report',
            'icon' => 'store',
            'heading' => lang('Carrier Scorecard'),
            'heading_description' => lang('See how each carrier delivered: on time, in how many days, and how many parcels had a problem.'),
            'cancel' => false
        )
    ) . '
<main id="content" class="container-fluid">
    ' . pg_delivery_tower_segments_html('scorecard', $user, true) . '
    <div class="pg-report-tools">' . $output_stepper . '
    </div>
    <div class="row">
        <div class="col-12">
            <div class="mb-3">' . pg_report_figures_html($figures) . '</div>
            ' . $output_arrival . '
            ' . $output_figure_choice . '
            <section class="pg-card pg-report-frame" aria-labelledby="pg_scorecard_title">
                <div class="pg-report-frame-head"><h3 class="pg-report-frame-title" id="pg_scorecard_title">' . h($year ? lang($figure_keys[$figure]) : lang('By carrier')) . '</h3><span class="pg-report-frame-note">' . h($period) . ' · ' . h(lang(array('string' => '{var:1} parcels', 'vars' => pg_format_number($total['parcels'], 0)))) . ($year ? ' · – ' . h(lang('Too few parcels')) : '') . '</span></div>
                <div class="pg-report-frame-body">
                    <table class="pg-report-table">
                        <thead><tr>' . $output_head . '</tr></thead>
                        <tbody>' . $output_rows . '</tbody>
                    </table>
                </div>
            </section>
            <p class="fg-secondary small">' . h(lang('Parcels are counted in the month they were shipped. On time compares the delivery with the date given at checkout.')) . '</p>
        </div>
    </div>
</main>
<script>
document.querySelectorAll("[data-pg-scorecard-toggle]").forEach(function (button) {
    button.addEventListener("click", function () {
        var open = button.getAttribute("aria-expanded") !== "true";
        button.setAttribute("aria-expanded", open ? "true" : "false");
        button.querySelector(".bi").className = "bi " + (open ? "bi-chevron-up" : "bi-chevron-down");
        document.querySelectorAll("[data-pg-scorecard-of=\"" + button.getAttribute("data-pg-scorecard-toggle") + "\"]").forEach(function (row) {
            row.hidden = !open;
        });
    });
});
</script>
' .
output_footer();
