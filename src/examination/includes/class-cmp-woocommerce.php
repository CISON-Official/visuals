<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces accounts/paystack.py + ProcessPaymentView + PaystackCallbackView.
 *
 * Instead of talking to Paystack directly, this class creates a WooCommerce
 * order carrying a single virtual "fee" line item for the invoice amount,
 * and sends the member to WooCommerce's own pay-for-order checkout screen.
 * WordPress/WooCommerce (and whichever gateway the site owner has enabled
 * inside WooCommerce — Paystack's WooCommerce gateway, Flutterwave, cards,
 * bank transfer, etc.) own everything after that: card capture, receipts,
 * refunds, gateway webhooks.
 *
 * This class only listens for "the WooCommerce order is now paid" and
 * reflects that back onto our own invoice ledger + exam order.
 */
class CMP_WooCommerce {

	const FEE_PRODUCT_META_KEY = '_cmp_fee_product';

	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'on_order_paid' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'on_order_paid' ) );
	}

	/**
	 * Creates (or reuses) a single hidden WooCommerce "Fee" product used as
	 * the line item for every invoice. Kept out of the shop catalog.
	 */
	private function get_or_create_fee_product() {
		$product_id = get_option( 'cmp_fee_product_id' );

		if ( $product_id && 'product' === get_post_type( $product_id ) ) {
			return wc_get_product( $product_id );
		}

		$product = new WC_Product_Simple();
		$product->set_name( __( 'Member Portal Fee', 'cison-member-portal' ) );
		$product->set_status( 'private' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$product->save();

		update_option( 'cmp_fee_product_id', $product->get_id() );

		return $product;
	}

	/**
	 * Creates a WooCommerce order for the given invoice and returns the URL
	 * the member should be sent to in order to pay it (WooCommerce's native
	 * "pay for order" checkout page).
	 *
	 * @return string|WP_Error
	 */
	public function create_order_for_invoice( $invoice ) {
		if ( ! class_exists( 'WC_Order' ) ) {
			return new WP_Error( 'cmp_wc_missing', __( 'WooCommerce is not active.', 'cison-member-portal' ) );
		}

		$user = get_userdata( $invoice->user_id );
		if ( ! $user ) {
			return new WP_Error( 'cmp_user_missing', __( 'Member account not found.', 'cison-member-portal' ) );
		}

		$fee_product = $this->get_or_create_fee_product();

		$order = wc_create_order( array( 'customer_id' => $invoice->user_id ) );

		$item = new WC_Order_Item_Product();
		$item->set_product( $fee_product );
		$item->set_name( $invoice->description ? $invoice->description : $invoice->invoice_number );
		$item->set_quantity( 1 );
		$item->set_subtotal( $invoice->amount );
		$item->set_total( $invoice->amount );
		$order->add_item( $item );

		$order->set_address(
			array(
				'email'      => $user->user_email,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
			),
			'billing'
		);

		$order->update_meta_data( '_cmp_invoice_id', $invoice->id );
		$order->update_meta_data( '_cmp_invoice_number', $invoice->invoice_number );
		$order->calculate_totals();
		$order->set_status( 'pending' );
		$order->save();

		CMP_Invoices::attach_wc_order( $invoice->id, $order->get_id() );

		return $order->get_checkout_payment_url();
	}

	/**
	 * Fires when a WooCommerce order tied to one of our invoices is marked
	 * paid (processing or completed). Reflects the payment back onto our
	 * ledger, and — for exam fees — marks the linked exam order as paid,
	 * same effect as ExamRegistrationOrderAdmin's "Mark as Paid" action.
	 */
	public function on_order_paid( $order_id ) {
		$invoice = CMP_Invoices::get_invoice_by_wc_order( $order_id );
		if ( ! $invoice || 'paid' === $invoice->status ) {
			return;
		}

		CMP_Invoices::mark_paid( $invoice->id );

		if ( 'exam_fee' === $invoice->fee_type ) {
			$exam_order = CMP_Examinations::get_order_by_invoice( $invoice->id );
			if ( $exam_order ) {
				CMP_Examinations::mark_order_paid( $exam_order->id );
			}
		}

		/**
		 * Fires after a CISON Member Portal invoice has been settled via
		 * WooCommerce. Other plugins/themes (e.g. BuddyBoss notifications)
		 * can hook in here.
		 */
		do_action( 'cmp_invoice_paid', $invoice );
	}
}
