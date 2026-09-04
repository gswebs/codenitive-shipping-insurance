<?php
/**
 * Plugin Name: Codenitive Shipping Insurance
 * Description: Adds an optional shipping insurance toggle on WooCommerce cart and checkout pages.
 * Plugin URI:  https://github.com/gswebs/codenitive-shipping-insurance
 * Version: 1.9.0
 * Author: Codenitive
 * Text Domain: codenitive-shipping-insurance
 * Requires Plugins: woocommerce
 * Author URI:  https://codenitive.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

final class Codenitive_Shipping_Insurance {
	const VERSION = '1.9.0';
	const OPTION  = 'codenitive_shipping_insurance';
	const SESSION = 'codenitive_shipping_insurance_enabled_v2';
	const VERSION_OPTION = 'codenitive_shipping_insurance_version';

	public function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function declare_compatibility() {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_notice' ) );
			return;
		}

		$this->maybe_upgrade();

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		$this->register_display_locations();
		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'capture_checkout_state' ) );
		add_action( 'cfw_update_checkout_after_customer_save', array( $this, 'capture_checkout_state' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_fee' ) );
		add_action( 'wc_ajax_codenitive_toggle_insurance', array( $this, 'toggle_from_cart' ) );
		add_action( 'wc_ajax_nopriv_codenitive_toggle_insurance', array( $this, 'toggle_from_cart' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_order_meta' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'show_order_meta' ) );
	}

	private function defaults() {
		return array(
			'enabled'       => 'yes',
			'heading'       => 'Shipping Insurance',
			'label'         => 'Package protection',
			'description'   => 'Against loss, theft, or damage in transit and quick resolution.',
			'fee'           => '3.00',
			'default_state' => 'no',
			'taxable'       => 'no',
			'cart_location' => 'woocommerce_before_cart_totals',
			'checkout_location' => 'woocommerce_after_checkout_billing_form',
		);
	}

	private function cart_locations() {
		return array(
			'none'                           => __( 'Do not display on cart page', 'codenitive-shipping-insurance' ),
			'woocommerce_before_cart'        => __( 'Before cart', 'codenitive-shipping-insurance' ),
			'woocommerce_before_cart_table'  => __( 'Before cart table', 'codenitive-shipping-insurance' ),
			'woocommerce_after_cart_table'   => __( 'After cart table', 'codenitive-shipping-insurance' ),
			'woocommerce_before_cart_totals' => __( 'Before cart totals', 'codenitive-shipping-insurance' ),
			'woocommerce_after_cart_totals'  => __( 'After cart totals', 'codenitive-shipping-insurance' ),
			'woocommerce_after_cart'         => __( 'After cart', 'codenitive-shipping-insurance' ),
		);
	}

	private function checkout_locations() {
		return array(
			'none'                                             => __( 'Do not display on checkout page', 'codenitive-shipping-insurance' ),
			'woocommerce_checkout_before_customer_details'     => __( 'Before customer details', 'codenitive-shipping-insurance' ),
			'woocommerce_after_checkout_billing_form'          => __( 'After billing form', 'codenitive-shipping-insurance' ),
			'woocommerce_checkout_after_customer_details'      => __( 'After customer details', 'codenitive-shipping-insurance' ),
			'woocommerce_checkout_before_order_review_heading' => __( 'Before order review heading', 'codenitive-shipping-insurance' ),
			'woocommerce_checkout_before_order_review'         => __( 'Before order review', 'codenitive-shipping-insurance' ),
			'woocommerce_checkout_after_order_review'          => __( 'After order review', 'codenitive-shipping-insurance' ),
		);
	}

	private function register_display_locations() {
		$s = $this->settings();

		if ( isset( $this->cart_locations()[ $s['cart_location'] ] ) && 'none' !== $s['cart_location'] ) {
			add_action( $s['cart_location'], array( $this, 'render_cart' ) );
		}

		if ( isset( $this->checkout_locations()[ $s['checkout_location'] ] ) && 'none' !== $s['checkout_location'] ) {
			add_action( $s['checkout_location'], array( $this, 'render_checkout' ) );
		}
	}

	private function settings() {
		return wp_parse_args( get_option( self::OPTION, array() ), $this->defaults() );
	}

	private function maybe_upgrade() {
		$installed_version = get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $installed_version, '1.7.0', '<' ) ) {
			$settings                  = get_option( self::OPTION, array() );
			$settings['default_state'] = 'no';
			update_option( self::OPTION, $settings );
			update_option( self::VERSION_OPTION, self::VERSION );
		}
	}

	public function woocommerce_notice() {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Codenitive Shipping Insurance requires WooCommerce.', 'codenitive-shipping-insurance' ) . '</p></div>';
	}

	public function admin_menu() {
		add_submenu_page( 'woocommerce', __( 'Shipping Insurance', 'codenitive-shipping-insurance' ), __( 'Shipping Insurance', 'codenitive-shipping-insurance' ), 'manage_woocommerce', 'codenitive-shipping-insurance', array( $this, 'settings_page' ) );
	}

	public function register_settings() {
		register_setting( 'codenitive_shipping_insurance_group', self::OPTION, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		$defaults = $this->defaults();
		$cart_location = sanitize_key( $input['cart_location'] ?? $defaults['cart_location'] );
		$checkout_location = sanitize_key( $input['checkout_location'] ?? $defaults['checkout_location'] );

		if ( ! isset( $this->cart_locations()[ $cart_location ] ) ) {
			$cart_location = $defaults['cart_location'];
		}

		if ( ! isset( $this->checkout_locations()[ $checkout_location ] ) ) {
			$checkout_location = $defaults['checkout_location'];
		}

		return array(
			'enabled'       => ! empty( $input['enabled'] ) ? 'yes' : 'no',
			'heading'       => sanitize_text_field( $input['heading'] ?? $defaults['heading'] ),
			'label'         => sanitize_text_field( $input['label'] ?? $defaults['label'] ),
			'description'   => sanitize_textarea_field( $input['description'] ?? $defaults['description'] ),
			'fee'           => wc_format_decimal( $input['fee'] ?? $defaults['fee'] ),
			'default_state' => ! empty( $input['default_state'] ) ? 'yes' : 'no',
			'taxable'       => ! empty( $input['taxable'] ) ? 'yes' : 'no',
			'cart_location' => $cart_location,
			'checkout_location' => $checkout_location,
		);
	}

	public function settings_page() {
		$s = $this->settings();
		?>
		<div class="wrap"><h1><?php esc_html_e( 'Shipping Insurance', 'codenitive-shipping-insurance' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'codenitive_shipping_insurance_group' ); ?>
			<table class="form-table" role="presentation">
			<tr><th><?php esc_html_e( 'Enable', 'codenitive-shipping-insurance' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 'yes' ); ?>> <?php esc_html_e( 'Show shipping insurance on cart and checkout pages', 'codenitive-shipping-insurance' ); ?></label></td></tr>
			<tr><th><label for="csi-cart-location"><?php esc_html_e( 'Cart location', 'codenitive-shipping-insurance' ); ?></label></th><td><select id="csi-cart-location" name="<?php echo esc_attr( self::OPTION ); ?>[cart_location]">
				<?php foreach ( $this->cart_locations() as $hook => $label ) : ?>
					<option value="<?php echo esc_attr( $hook ); ?>" <?php selected( $s['cart_location'], $hook ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select></td></tr>
			<tr><th><label for="csi-checkout-location"><?php esc_html_e( 'Checkout location', 'codenitive-shipping-insurance' ); ?></label></th><td><select id="csi-checkout-location" name="<?php echo esc_attr( self::OPTION ); ?>[checkout_location]">
				<?php foreach ( $this->checkout_locations() as $hook => $label ) : ?>
					<option value="<?php echo esc_attr( $hook ); ?>" <?php selected( $s['checkout_location'], $hook ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select><p class="description"><?php esc_html_e( 'CheckoutWC compatibility depends on the template. After billing form is the recommended position.', 'codenitive-shipping-insurance' ); ?></p></td></tr>
			<tr><th><label for="csi-heading"><?php esc_html_e( 'Heading', 'codenitive-shipping-insurance' ); ?></label></th><td><input class="regular-text" id="csi-heading" name="<?php echo esc_attr( self::OPTION ); ?>[heading]" value="<?php echo esc_attr( $s['heading'] ); ?>"></td></tr>
			<tr><th><label for="csi-label"><?php esc_html_e( 'Option label', 'codenitive-shipping-insurance' ); ?></label></th><td><input class="regular-text" id="csi-label" name="<?php echo esc_attr( self::OPTION ); ?>[label]" value="<?php echo esc_attr( $s['label'] ); ?>"></td></tr>
			<tr><th><label for="csi-description"><?php esc_html_e( 'Description', 'codenitive-shipping-insurance' ); ?></label></th><td><textarea class="large-text" rows="3" id="csi-description" name="<?php echo esc_attr( self::OPTION ); ?>[description]"><?php echo esc_textarea( $s['description'] ); ?></textarea></td></tr>
			<tr><th><label for="csi-fee"><?php esc_html_e( 'Fee', 'codenitive-shipping-insurance' ); ?></label></th><td><input type="number" min="0" step="0.01" id="csi-fee" name="<?php echo esc_attr( self::OPTION ); ?>[fee]" value="<?php echo esc_attr( $s['fee'] ); ?>"></td></tr>
			<tr><th><?php esc_html_e( 'Default state', 'codenitive-shipping-insurance' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[default_state]" value="1" <?php checked( $s['default_state'], 'yes' ); ?>> <?php esc_html_e( 'Enabled by default', 'codenitive-shipping-insurance' ); ?></label></td></tr>
			<tr><th><?php esc_html_e( 'Tax', 'codenitive-shipping-insurance' ); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[taxable]" value="1" <?php checked( $s['taxable'], 'yes' ); ?>> <?php esc_html_e( 'The insurance fee is taxable', 'codenitive-shipping-insurance' ); ?></label></td></tr>
			</table><?php submit_button(); ?>
		</form></div>
		<?php
	}

	public function assets() {
		if ( ( ! is_cart() && ! is_checkout() ) || is_order_received_page() ) return;
		wp_enqueue_style( 'codenitive-shipping-insurance', plugin_dir_url( __FILE__ ) . 'assets/css/checkout.css', array(), self::VERSION );
		$dependencies = is_checkout() ? array( 'jquery', 'wc-checkout' ) : array( 'jquery' );
		wp_enqueue_script( 'codenitive-shipping-insurance', plugin_dir_url( __FILE__ ) . 'assets/js/checkout.js', $dependencies, self::VERSION, true );
		wp_localize_script(
			'codenitive-shipping-insurance',
			'codenitiveInsurance',
			array(
				'isCart' => is_cart(),
				'isCheckoutWC' => function_exists( 'cfw_is_checkout' ) && cfw_is_checkout(),
				'ajaxUrl' => WC_AJAX::get_endpoint( 'codenitive_toggle_insurance' ),
				'nonce'   => wp_create_nonce( 'codenitive_toggle_insurance' ),
			)
		);
	}

	private function is_selected() {
        $s = $this->settings();
    
        if ( WC()->session ) {
            $saved_state = WC()->session->get( self::SESSION, null );
    
            if ( null !== $saved_state ) {
                return 'yes' === $saved_state;
            }
    
			// Initialize the session with the configured default state.
            $default = $s['default_state'];
            WC()->session->set( self::SESSION, $default );
            return 'yes' === $default;
        }
    
        return 'yes' === $s['default_state'];
    }

	private function render( $context ) {
		$s = $this->settings();
		if ( 'yes' !== $s['enabled'] ) return;
		$checked = $this->is_selected();
		?>
		<section class="codenitive-insurance codenitive-insurance--<?php echo esc_attr( $context ); ?>">
			<h3><span class="codenitive-insurance__shield" aria-hidden="true">✣</span><?php echo esc_html( $s['heading'] ); ?></h3>
			<label class="codenitive-insurance__card">
				<span class="codenitive-insurance__copy"><strong><?php echo esc_html( $s['label'] ); ?></strong><span><?php echo esc_html( $s['description'] ); ?></span></span>
				<span class="codenitive-insurance__action">
					<span class="codenitive-insurance__price"><?php echo wp_kses_post( wc_price( (float) $s['fee'] ) ); ?></span>
					<input type="hidden" name="codenitive_shipping_insurance" value="no">
					<input type="checkbox" name="codenitive_shipping_insurance" value="yes" <?php checked( $checked ); ?>>
					<span class="codenitive-insurance__switch" aria-hidden="true"></span>
				</span>
			</label>
		</section>
		<?php
	}

	public function render_cart() {
		$this->render( 'cart' );
	}

	public function render_checkout() {
		if ( is_checkout() && ! is_order_received_page() ) {
			$this->render( 'checkout' );
		}
	}

	public function toggle_from_cart() {
		check_ajax_referer( 'codenitive_toggle_insurance', 'nonce' );

		if ( ! WC()->session ) {
			wp_send_json_error();
		}

		$enabled = isset( $_POST['enabled'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );
		WC()->session->set( self::SESSION, $enabled ? 'yes' : 'no' );
		WC()->cart->calculate_totals();
		wp_send_json_success();
	}

	public function capture_checkout_state( $post_data ) {
        if ( is_array( $post_data ) ) {
            $data = $post_data;
        } else {
            parse_str( (string) $post_data, $data );
        }
    
        $is_enabled = isset( $data['codenitive_shipping_insurance'] ) && 'yes' === $data['codenitive_shipping_insurance'];
    
        WC()->session->set( self::SESSION, $is_enabled ? 'yes' : 'no' );
    }

	public function add_fee( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) return;
		$s = $this->settings();
		if ( 'yes' === $s['enabled'] && $this->is_selected() && (float) $s['fee'] > 0 ) {
			$cart->add_fee( $s['label'], (float) $s['fee'], 'yes' === $s['taxable'] );
		}
	}

	public function save_order_meta( $order, $data ) {
		$selected = $this->is_selected();
		$order->update_meta_data( '_codenitive_shipping_insurance', $selected ? 'yes' : 'no' );
		if ( $selected ) $order->update_meta_data( '_codenitive_shipping_insurance_fee', $this->settings()['fee'] );
	}

	public function show_order_meta( $order ) {
		if ( 'yes' === $order->get_meta( '_codenitive_shipping_insurance' ) ) {
			echo '<p><strong>' . esc_html__( 'Shipping insurance:', 'codenitive-shipping-insurance' ) . '</strong> ' . wp_kses_post( wc_price( (float) $order->get_meta( '_codenitive_shipping_insurance_fee' ), array( 'currency' => $order->get_currency() ) ) ) . '</p>';
		}
	}
}

new Codenitive_Shipping_Insurance();
