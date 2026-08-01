<?php

if (!defined('ABSPATH')) {
    exit; // No direct access.
}
add_action('wp_enqueue_scripts', 'evp_enqueue_result_charts_js');
function evp_enqueue_result_charts_js()
{
    global $post;
    if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'election_results')) {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], '4.4.0', true);
    }
}


/**
 * Validate a shortcode-supplied year, falling back to the current year
 * (in the site's configured timezone) for anything malformed.
 */
function evp_sanitize_ballot_year($raw_year)
{
    $year = sanitize_text_field($raw_year);
    if (!preg_match('/^\d{4}$/', $year)) {
        $year = current_time('Y');
    }
    return $year;
}

add_shortcode('election_results', 'evp_render_year_results');
function evp_render_year_results($atts)
{
    global $wpdb;
    $table_elections = $wpdb->prefix . 'election_entries';
    $table_candidates = $wpdb->prefix . 'election_candidates';
    $table_voters = $wpdb->prefix . 'election_voters';

    $args = shortcode_atts([
        'year' => current_time('Y'),
    ], $atts);

    $target_year = evp_sanitize_ballot_year($args['year']);

    $elections = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_elections WHERE YEAR(created_at) = %s ORDER BY id ASC",
        $target_year
    ));

    if (empty($elections)) {
        return '<p class="evp-error">No election results found for the year ' . esc_html($target_year) . '.</p>';
    }

    $color_palette = ['#ff6384', '#36a2eb', '#cc65fe', '#ffce56', '#4bc0c0', '#9966ff', '#ff9f40', '#c9cbcf'];

    ob_start();
    ?>
    <style>
        .evp-results-wrapper {
            max-width: 700px;
            margin: 30px auto;
            font-family: system-ui, sans-serif;
        }

        .evp-results-title {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            color: #222;
        }

        .evp-position-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .evp-position-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .evp-position-meta h3 {
            margin: 0;
            font-size: 1.3rem;
            color: #1a202c;
        }

        .evp-toggle-btn {
            background: #edf2f7;
            border: 1px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .evp-toggle-btn:hover {
            background: #e2e8f0;
        }

        .evp-chart-container {
            position: relative;
            height: 320px;
            width: 100%;
        }
    </style>

    <div class="evp-results-wrapper">
        <h2 class="evp-results-title">Election Standings — <?php echo esc_html($target_year); ?></h2>

        <?php foreach ($elections as $index => $election):
            $candidates = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_candidates WHERE election_id = %d ORDER BY id ASC",
                $election->id
            ));

            if (empty($candidates))
                continue;

            $labels = [];
            $vote_counts = [];
            $background_colors = [];

            foreach ($candidates as $c_idx => $candidate) {
                $labels[] = $candidate->name;

                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_voters WHERE election_id = %d AND candidate_id = %d",
                    $election->id,
                    $candidate->id
                ));
                $vote_counts[] = intval($count);

                $background_colors[] = $color_palette[$c_idx % count($color_palette)];
            }

            $chart_id = "evpChart_" . $election->id;

            // Belt-and-suspenders JSON encoding: candidate/election names
            // are already sanitized (tags stripped) when stored, but
            // these hex flags additionally neutralize </script>, quotes,
            // ampersands, and apostrophes so this can never break out of
            // the inline <script> context even if that assumption ever
            // stops holding (e.g. a future import path that skips
            // sanitize_text_field).
            $json_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP;
            ?>
            <div class="evp-position-card">
                <div class="evp-position-meta">
                    <h3><?php echo esc_html($election->position); ?> <small
                            style="font-weight:normal; font-size:0.85rem; color:#718096;">(<?php echo esc_html($election->name); ?>)</small>
                    </h3>
                    <button type="button" class="evp-toggle-btn" onclick="evpToggleChart('<?php echo esc_js($chart_id); ?>')">📊
                        Switch View (Bar/Line)</button>
                </div>

                <div class="evp-chart-container">
                    <canvas id="<?php echo esc_attr($chart_id); ?>"></canvas>
                </div>
            </div>

            <script>
                document.addEventListener("DOMContentLoaded", function () {
                    console.log("I am running for my life");
                    console.log(<?php echo wp_json_encode($vote_counts, $json_flags); ?>);
                    if (typeof Chart === "undefined") return;

                    const ctx = document.getElementById('<?php echo esc_js($chart_id); ?>').getContext('2d');

                    window.<?php echo esc_js($chart_id); ?>_data = {
                        labels: <?php echo wp_json_encode($labels, $json_flags); ?>,
                        datasets: [{
                            label: 'Votes Collected',
                            data: <?php echo wp_json_encode($vote_counts, $json_flags); ?>,
                            backgroundColor: <?php echo wp_json_encode($background_colors, $json_flags); ?>,
                            borderColor: <?php echo wp_json_encode($background_colors, $json_flags); ?>,
                            borderWidth: 2,
                            tension: 0.3,
                            fill: false
                        }]
                    };

                    window.<?php echo esc_js($chart_id); ?>_obj = new Chart(ctx, {
                        type: 'bar',
                        data: window.<?php echo esc_js($chart_id); ?>_data,
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: {
                                        generateLabels: function (chart) {
                                            const data = chart.data;
                                            if (data.labels.length && data.datasets.length) {
                                                return data.labels.map(function (label, i) {
                                                    return {
                                                        text: label,
                                                        fillStyle: data.datasets[0].backgroundColor[i],
                                                        strokeStyle: data.datasets[0].borderColor[i],
                                                        lineWidth: data.datasets[0].borderWidth,
                                                        hidden: false,
                                                        index: i
                                                    };
                                                });
                                            }
                                            return [];
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: { stepSize: 1 }
                                }
                            }
                        }
                    });
                });
            </script>
        <?php endforeach; ?>
    </div>

    <script>
        function evpToggleChart(chartId) {
            const chartInstance = window[chartId + '_obj'];
            if (!chartInstance) return;

            chartInstance.config.type = chartInstance.config.type === 'bar' ? 'line' : 'bar';

            const dataset = chartInstance.config.data.datasets[0];
            if (chartInstance.config.type === 'line') {
                dataset.borderColor = '#4a5568';
                dataset.pointBackgroundColor = window[chartId + '_data'].datasets[0].backgroundColor;
                dataset.pointRadius = 6;
            } else {
                dataset.borderColor = window[chartId + '_data'].datasets[0].backgroundColor;
                dataset.pointRadius = 0;
            }

            chartInstance.update();
        }
    </script>
    <?php
    return ob_get_clean();
}