<?php
/**
 * NSA REGISTRATIONS TABLE SHORTCODE
 * [nsa_registrations per_page="20" filter="registering_for"]
 */
function nsa_registrations_table_shortcode($atts) {
    $atts = shortcode_atts(array(
        'per_page' => 20,
        'filter'   => 'registering_for',
        'columns'  => 'member_id,first_name,last_name,email,registering_for,payment_status,registration_date'
    ), $atts);

    global $wpdb;
    $table_name = $wpdb->prefix . 'nsa_registrations';
    

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return '<p style="color:red;">Error: Registration table not found.</p>';
    }

    $search       = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $filter_key   = sanitize_key($atts['filter']);
    $filter_value = isset($_GET['filter_' . $filter_key]) ? sanitize_text_field($_GET['filter_' . $filter_key]) : '';
    $paged        = isset($_GET['nsa_paged']) ? max(1, intval($_GET['nsa_paged'])) : 1;
    $per_page     = intval($atts['per_page']);
    $offset       = ($paged - 1) * $per_page;

    $where_clauses = array("1=1");
    $query_params  = array();

    if ($search) {
        $search_term = '%' . $wpdb->esc_like($search) . '%';
        $where_clauses[] = "($filter_key LIKE %s OR member_id LIKE %s OR first_name LIKE %s OR last_name LIKE %s OR email LIKE %s)";
        for($i=0; $i<5; $i++) { $query_params[] = $search_term; }
    }

    if ($filter_value) {
        $where_clauses[] = "$filter_key = %s";
        $query_params[] = $filter_value;
    }

    $where_sql = implode(' AND ', $where_clauses);

    $total_items = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE $where_sql", $query_params));
    $total_pages = ceil($total_items / $per_page);

    $data_query = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE $where_sql ORDER BY registration_date DESC LIMIT %d OFFSET %d",
        array_merge($query_params, array($per_page, $offset))
    );
    $results = $wpdb->get_results($data_query, ARRAY_A);

    $columns = array_map('trim', explode(',', $atts['columns']));

    ob_start();
    ?>
    <div class="u-nsa-container">
        <div class="u-nsa-controls">
            <div class="u-nsa-stats">
                Showing <strong><?php echo count($results); ?></strong> of <strong><?php echo $total_items; ?></strong> entries
            </div>
            
            <div class="u-nsa-actions">
                <form method="get" class="u-nsa-search-form">
                    <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search records..." class="u-nsa-input">
                    <button type="submit" class="u-nsa-btn-primary"><i class="fas fa-search"></i></button>
                    <?php if ($search): ?>
                        <a href="<?php echo remove_query_arg(array('s', 'nsa_paged')); ?>" class="u-nsa-clear">Clear</a>
                    <?php endif; ?>
                </form>

                <?php if ($filter_key): ?>
                <form method="get" class="u-nsa-filter-form">
                    <select name="filter_<?php echo esc_attr($filter_key); ?>" onchange="this.form.submit()" class="u-nsa-select">
                        <option value="">All <?php echo ucwords(str_replace('_', ' ', $filter_key)); ?></option>
                        <?php
                        $options = $wpdb->get_col("SELECT DISTINCT $filter_key FROM $table_name WHERE $filter_key != '' ORDER BY $filter_key ASC");
                        foreach ($options as $opt) {
                            printf('<option value="%s" %s>%s</option>', esc_attr($opt), selected($filter_value, $opt, false), esc_html($opt));
                        }
                        ?>
                    </select>
                    <input type="hidden" name="s" value="<?php echo esc_attr($search); ?>">
                </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="u-nsa-table-wrapper">
            <table class="u-nsa-table">
                <thead>
                    <tr>
                        <?php foreach ($columns as $col): ?>
                            <th><?php echo ucwords(str_replace('_', ' ', $col)); ?></th>
                        <?php endforeach; ?>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($results): foreach ($results as $row): ?>
                        <tr>
                            <?php foreach ($columns as $col): ?>
                                <td>
                                    <?php 
                                    if ($col === 'registration_date') {
                                        echo date('M j, Y', strtotime($row[$col]));
                                    } elseif ($col === 'payment_status') {
                                        $status = strtolower($row[$col]);
                                        echo "<span class='u-nsa-status status-$status'>" . esc_html(ucfirst($status)) . "</span>";
                                    } else {
                                        echo esc_html($row[$col] ?: '—');
                                    }
                                    ?>
                                </td>
                            <?php endforeach; ?>
                            <td>
                                <a href="<?php echo admin_url('post.php?post=' . intval($row['order_id'] ?? 0) . '&action=edit'); ?>" class="u-nsa-btn-view" target="_blank">
                                    <i class="fas fa-external-link-alt"></i> Order
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="<?php echo count($columns) + 1; ?>" class="u-nsa-no-data">No records found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="u-nsa-pagination">
                <?php echo paginate_links(array(
                    'base'      => add_query_arg('nsa_paged', '%#%'),
                    'format'    => '',
                    'total'     => $total_pages,
                    'current'   => $paged,
                    'prev_text' => '«',
                    'next_text' => '»',
                    'add_args'  => array('s' => $search, 'filter_' . $filter_key => $filter_value)
                )); ?>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .u-nsa-container { margin: 20px 0; font-family: system-ui, -apple-system, sans-serif; color: #2d3748; }
        .u-nsa-controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 15px; }
        .u-nsa-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .u-nsa-input, .u-nsa-select { padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 14px; }
        .u-nsa-btn-primary { background: #3182ce; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; }
        .u-nsa-btn-primary:hover { background: #2b6cb0; }
        .u-nsa-table-wrapper { background: white; border-radius: 12px; border: 1px solid #e2e8f0; overflow-x: auto; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .u-nsa-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        .u-nsa-table th { background: #f7fafc; padding: 16px; font-weight: 700; border-bottom: 2px solid #edf2f7; text-transform: uppercase; font-size: 12px; letter-spacing: 0.05em; }
        .u-nsa-table td { padding: 16px; border-bottom: 1px solid #edf2f7; }
        .u-nsa-table tr:hover { background: #fdfdfd; }
        .u-nsa-status { padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; }
        .status-paid { background: #c6f6d5; color: #22543d; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .u-nsa-btn-view { color: #3182ce; text-decoration: none; font-weight: 600; font-size: 13px; }
        .u-nsa-pagination { margin-top: 20px; display: flex; justify-content: center; gap: 5px; }
        .u-nsa-pagination .page-numbers { padding: 8px 14px; border: 1px solid #e2e8f0; border-radius: 6px; text-decoration: none; color: #4a5568; transition: 0.2s; }
        .u-nsa-pagination .current { background: #3182ce; color: white; border-color: #3182ce; }
        .u-nsa-pagination .page-numbers:hover:not(.current) { background: #edf2f7; }
        .u-nsa-no-data { padding: 50px; text-align: center; color: #a0aec0; }
        @media (max-width: 768px) { .u-nsa-controls { flex-direction: column; align-items: stretch; } }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('nsa_registrations', 'nsa_registrations_table_shortcode');