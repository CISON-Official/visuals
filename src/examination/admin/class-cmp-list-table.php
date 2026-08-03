<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * One configurable WP_List_Table used for every admin screen in this
 * plugin (courses, applications, examinations, exam orders, exemptions,
 * invoices) instead of writing six near-identical subclasses.
 *
 * Configuration is passed in as plain arrays/callables so each admin view
 * file just describes its columns/data/row-actions.
 */
class CMP_List_Table extends WP_List_Table {

	private $columns_config;
	private $data_callback;
	private $row_actions_callback;
	private $status_column;
	private $status_labels;
	private $items_per_page = 20;

	public function __construct( $args ) {
		parent::__construct(
			array(
				'singular' => $args['singular'],
				'plural'   => $args['plural'],
				'ajax'     => false,
			)
		);

		$this->columns_config       = $args['columns'];
		$this->data_callback        = $args['data_callback'];
		$this->row_actions_callback = isset( $args['row_actions_callback'] ) ? $args['row_actions_callback'] : null;
		$this->status_column        = isset( $args['status_column'] ) ? $args['status_column'] : null;
		$this->status_labels        = isset( $args['status_labels'] ) ? $args['status_labels'] : array();
	}

	public function get_columns() {
		return $this->columns_config;
	}

	/**
	 * Core's default primary-column detection relies on columns being
	 * registered through the manage_{screen}_columns filter, which we
	 * intentionally skip (this table's columns are configured per-view
	 * instead). Just use the first configured column.
	 */
	protected function get_primary_column_name() {
		$keys = array_keys( $this->columns_config );
		return $keys ? $keys[0] : '';
	}

	public function prepare_items() {
		$all_items = call_user_func( $this->data_callback );

		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $status_filter && $this->status_column ) {
			$all_items = array_values(
				array_filter(
					$all_items,
					function ( $item ) use ( $status_filter ) {
						return isset( $item->{$this->status_column} ) && $item->{$this->status_column} === $status_filter;
					}
				)
			);
		}

		$total_items = count( $all_items );
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $this->items_per_page;

		$this->items = array_slice( $all_items, $offset, $this->items_per_page );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $this->items_per_page,
				'total_pages' => ceil( $total_items / $this->items_per_page ),
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), array() );
	}

	public function column_default( $item, $column_name ) {
		if ( 'status' === $column_name && $this->status_column && isset( $item->{$this->status_column} ) ) {
			$status = $item->{$this->status_column};
			$label  = isset( $this->status_labels[ $status ] ) ? $this->status_labels[ $status ] : ucfirst( $status );
			return '<span class="cmp-admin-status cmp-admin-status-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
		}

		if ( isset( $item->$column_name ) ) {
			// Values are prepared by this plugin's own data_callback (not raw
			// user input at this point), and some columns intentionally embed
			// links (e.g. "View PDF"), so allow safe post-level HTML rather
			// than flattening everything to plain text.
			return wp_kses_post( $item->$column_name );
		}

		return '';
	}

	/**
	 * Renders the "first" column with row actions (approve/reject links, etc.)
	 * Views pass which column key is "primary" via column config; we just
	 * always attach actions under whatever the table's first data column is.
	 */
	protected function handle_row_actions( $item, $column_name, $primary ) {
		if ( $column_name !== $primary || ! $this->row_actions_callback ) {
			return '';
		}
		$actions = call_user_func( $this->row_actions_callback, $item );
		return $this->row_actions( $actions );
	}
}
