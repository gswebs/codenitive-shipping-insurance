<?php
/**
 * Plugin Name: Codenitive Shipping Insurance
 * Description: Adds optional single-fee or tiered shipping insurance to WooCommerce cart and checkout pages.
 * Plugin URI:  https://github.com/gswebs/codenitive-shipping-insurance
 * Version: 1.0.2
 * Author: Codenitive
 * Text Domain: codenitive-shipping-insurance
 * Requires Plugins: woocommerce
 * Author URI:  https://codenitive.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined( 'ABSPATH' ) || exit;

final class Codenitive_Shipping_Insurance {
	const VERSION = '1.0.2';
	const OPTION  = 'codenitive_shipping_insurance';
	const SESSION = 'codenitive_shipping_insurance_enabled_v2';
	const SESSION_DEFAULT = 'codenitive_shipping_insurance_default_v1';
	const CHOICE_SESSION = 'codenitive_shipping_insurance_choice_v1';
	const INSTRUCTIONS_SESSION = 'codenitive_shipping_instructions_v1';
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
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
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
			'display_type'      => 'toggle',
			'tiered_heading'    => 'Shipping / Lost Packages Insurance',
			'decline_label'     => 'I Decline Lost Packages Insurance',
			'coverage_options'  => "300|15.00\n500|30.00\n1000|60.00\n2000|150.00",
			'tiered_description' => 'We cover lost packages by the shipping carrier up to the selected insurance amount. We do not replace orders that the shipping company has marked as delivered.',
			'instructions_enabled' => 'yes',
			'instructions_label' => 'Shipping Instructions (optional)',
			'instructions_description' => 'Please provide any special instructions you have.',
		);
	}

	private function coverage_options( $raw = null ) {
		if ( null === $raw ) {
			$s   = $this->settings();
			$raw = $s['coverage_options'];
		}

		$options = array();

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$parts    = array_map( 'trim', explode( '|', $line, 2 ) );
			$coverage = isset( $parts[0] ) ? wc_format_decimal( $parts[0] ) : '';
			$fee      = isset( $parts[1] ) ? wc_format_decimal( $parts[1] ) : '';

			if ( '' === $coverage || '' === $fee || (float) $coverage <= 0 || (float) $fee < 0 ) {
				continue;
			}

			$options[ $coverage ] = array(
				'coverage' => $coverage,
				'fee'      => $fee,
			);
		}

		return $options;
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

		if ( version_compare( $installed_version, '1.0.2', '<' ) ) {
			$settings                  = get_option( self::OPTION, array() );
			$settings['default_state'] = 'no';
			update_option( self::OPTION, $settings );
		}

		if ( self::VERSION !== $installed_version ) {
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

	public function admin_assets( $hook_suffix ) {
		if ( 'woocommerce_page_codenitive-shipping-insurance' !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'codenitive-shipping-insurance-admin', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', array(), self::VERSION );
		wp_enqueue_script( 'codenitive-shipping-insurance-admin', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), self::VERSION, true );
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

		$display_type = isset( $input['display_type'] ) && 'tiered' === $input['display_type'] ? 'tiered' : 'toggle';
		$raw_coverage = '';

		if ( isset( $input['coverage_amount'], $input['coverage_fee'] ) && is_array( $input['coverage_amount'] ) && is_array( $input['coverage_fee'] ) ) {
			foreach ( $input['coverage_amount'] as $index => $coverage ) {
				$fee = $input['coverage_fee'][ $index ] ?? '';
				$raw_coverage .= wc_format_decimal( wp_unslash( $coverage ) ) . '|' . wc_format_decimal( wp_unslash( $fee ) ) . "\n";
			}
		} else {
			$raw_coverage = sanitize_textarea_field( $input['coverage_options'] ?? $defaults['coverage_options'] );
		}

		$coverage_options = $this->coverage_options( $raw_coverage );
		$coverage_lines = array();

		foreach ( $coverage_options as $option ) {
			$coverage_lines[] = $option['coverage'] . '|' . $option['fee'];
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
			'display_type'      => $display_type,
			'tiered_heading'    => sanitize_text_field( $input['tiered_heading'] ?? $defaults['tiered_heading'] ),
			'decline_label'     => sanitize_text_field( $input['decline_label'] ?? $defaults['decline_label'] ),
			'coverage_options'  => ! empty( $coverage_lines ) ? implode( "\n", $coverage_lines ) : $defaults['coverage_options'],
			'tiered_description' => sanitize_textarea_field( $input['tiered_description'] ?? $defaults['tiered_description'] ),
			'instructions_enabled' => ! empty( $input['instructions_enabled'] ) ? 'yes' : 'no',
			'instructions_label' => sanitize_text_field( $input['instructions_label'] ?? $defaults['instructions_label'] ),
			'instructions_description' => sanitize_text_field( $input['instructions_description'] ?? $defaults['instructions_description'] ),
		);
	}

	public function settings_page() {
		$s                = $this->settings();
		$coverage_options = $this->coverage_options();
		$option_name      = self::OPTION;
		?>
		<div class="wrap csi-admin">
			<div class="csi-admin__header">
				<div><h1><?php esc_html_e( 'Shipping Insurance', 'codenitive-shipping-insurance' ); ?></h1><p><?php esc_html_e( 'Configure package protection for your WooCommerce cart and checkout.', 'codenitive-shipping-insurance' ); ?></p></div>
				<span class="csi-admin__version">v<?php echo esc_html( self::VERSION ); ?></span>
			</div>
			<?php settings_errors(); ?>
			<form method="post" action="options.php" id="csi-settings-form">
				<?php settings_fields( 'codenitive_shipping_insurance_group' ); ?>
				<nav class="csi-tabs" aria-label="<?php esc_attr_e( 'Shipping Insurance settings', 'codenitive-shipping-insurance' ); ?>">
					<button type="button" class="csi-tab is-active" data-tab="general"><?php esc_html_e( 'General', 'codenitive-shipping-insurance' ); ?></button>
					<button type="button" class="csi-tab" data-tab="display"><?php esc_html_e( 'Display', 'codenitive-shipping-insurance' ); ?></button>
					<button type="button" class="csi-tab" data-tab="coverage"><?php esc_html_e( 'Coverage Choices', 'codenitive-shipping-insurance' ); ?></button>
					<button type="button" class="csi-tab" data-tab="advanced"><?php esc_html_e( 'Advanced', 'codenitive-shipping-insurance' ); ?></button>
				</nav>

				<div class="csi-admin__layout">
					<div class="csi-admin__main">
						<section class="csi-panel is-active" data-panel="general">
							<div class="csi-card"><div class="csi-card__heading"><h2><?php esc_html_e( 'General settings', 'codenitive-shipping-insurance' ); ?></h2><p><?php esc_html_e( 'Enable insurance and choose how customers select it.', 'codenitive-shipping-insurance' ); ?></p></div>
								<div class="csi-field csi-field--inline"><div><label><?php esc_html_e( 'Enable shipping insurance', 'codenitive-shipping-insurance' ); ?></label><p><?php esc_html_e( 'Display the insurance option on the selected store pages.', 'codenitive-shipping-insurance' ); ?></p></div><label class="csi-switch"><input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[enabled]" value="1" <?php checked( $s['enabled'], 'yes' ); ?>><span></span></label></div>
								<div class="csi-field"><label><?php esc_html_e( 'Insurance type', 'codenitive-shipping-insurance' ); ?></label><div class="csi-choice-cards">
									<label class="csi-choice-card"><input type="radio" name="<?php echo esc_attr( $option_name ); ?>[display_type]" value="toggle" <?php checked( $s['display_type'], 'toggle' ); ?>><span><strong><?php esc_html_e( 'Single fee toggle', 'codenitive-shipping-insurance' ); ?></strong><small><?php esc_html_e( 'One simple on/off package-protection option.', 'codenitive-shipping-insurance' ); ?></small></span></label>
									<label class="csi-choice-card"><input type="radio" name="<?php echo esc_attr( $option_name ); ?>[display_type]" value="tiered" <?php checked( $s['display_type'], 'tiered' ); ?>><span><strong><?php esc_html_e( 'Coverage choices', 'codenitive-shipping-insurance' ); ?></strong><small><?php esc_html_e( 'Multiple coverage levels with different fees.', 'codenitive-shipping-insurance' ); ?></small></span></label>
								</div></div>
								<div class="csi-mode csi-mode--toggle">
									<div class="csi-field"><label for="csi-heading"><?php esc_html_e( 'Heading', 'codenitive-shipping-insurance' ); ?></label><input type="text" id="csi-heading" name="<?php echo esc_attr( $option_name ); ?>[heading]" value="<?php echo esc_attr( $s['heading'] ); ?>"></div>
									<div class="csi-field"><label for="csi-label"><?php esc_html_e( 'Option label', 'codenitive-shipping-insurance' ); ?></label><input type="text" id="csi-label" name="<?php echo esc_attr( $option_name ); ?>[label]" value="<?php echo esc_attr( $s['label'] ); ?>"></div>
									<div class="csi-field"><label for="csi-description"><?php esc_html_e( 'Description', 'codenitive-shipping-insurance' ); ?></label><textarea id="csi-description" rows="3" name="<?php echo esc_attr( $option_name ); ?>[description]"><?php echo esc_textarea( $s['description'] ); ?></textarea></div>
									<div class="csi-field"><label for="csi-fee"><?php esc_html_e( 'Insurance fee', 'codenitive-shipping-insurance' ); ?></label><div class="csi-money"><span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span><input type="number" min="0" step="0.01" id="csi-fee" name="<?php echo esc_attr( $option_name ); ?>[fee]" value="<?php echo esc_attr( $s['fee'] ); ?>"></div></div>
								</div>
							</div>
						</section>

						<section class="csi-panel" data-panel="display"><div class="csi-card"><div class="csi-card__heading"><h2><?php esc_html_e( 'Display locations', 'codenitive-shipping-insurance' ); ?></h2><p><?php esc_html_e( 'Choose where the insurance selector appears.', 'codenitive-shipping-insurance' ); ?></p></div>
							<div class="csi-field"><label for="csi-cart-location"><?php esc_html_e( 'Cart page location', 'codenitive-shipping-insurance' ); ?></label><select id="csi-cart-location" name="<?php echo esc_attr( $option_name ); ?>[cart_location]"><?php foreach ( $this->cart_locations() as $hook => $label ) : ?><option value="<?php echo esc_attr( $hook ); ?>" <?php selected( $s['cart_location'], $hook ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
							<div class="csi-field"><label for="csi-checkout-location"><?php esc_html_e( 'Checkout page location', 'codenitive-shipping-insurance' ); ?></label><select id="csi-checkout-location" name="<?php echo esc_attr( $option_name ); ?>[checkout_location]"><?php foreach ( $this->checkout_locations() as $hook => $label ) : ?><option value="<?php echo esc_attr( $hook ); ?>" <?php selected( $s['checkout_location'], $hook ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><p><?php esc_html_e( 'For CheckoutWC, After billing form is recommended.', 'codenitive-shipping-insurance' ); ?></p></div>
						</div></section>

						<section class="csi-panel" data-panel="coverage"><div class="csi-card"><div class="csi-card__heading"><h2><?php esc_html_e( 'Coverage choices', 'codenitive-shipping-insurance' ); ?></h2><p><?php esc_html_e( 'Add, remove, and drag rows to arrange the choices shown to customers.', 'codenitive-shipping-insurance' ); ?></p></div>
							<div class="csi-field"><label for="csi-tiered-heading"><?php esc_html_e( 'Section heading', 'codenitive-shipping-insurance' ); ?></label><input type="text" id="csi-tiered-heading" name="<?php echo esc_attr( $option_name ); ?>[tiered_heading]" value="<?php echo esc_attr( $s['tiered_heading'] ); ?>"></div>
							<div class="csi-field"><label for="csi-decline-label"><?php esc_html_e( 'Decline option label', 'codenitive-shipping-insurance' ); ?></label><input type="text" id="csi-decline-label" name="<?php echo esc_attr( $option_name ); ?>[decline_label]" value="<?php echo esc_attr( $s['decline_label'] ); ?>"></div>
							<div class="csi-field"><label><?php esc_html_e( 'Insurance levels', 'codenitive-shipping-insurance' ); ?></label><div class="csi-coverage-table"><div class="csi-coverage-table__head"><span></span><span><?php esc_html_e( 'Coverage amount', 'codenitive-shipping-insurance' ); ?></span><span><?php esc_html_e( 'Customer fee', 'codenitive-shipping-insurance' ); ?></span><span></span></div><div id="csi-coverage-rows">
								<?php foreach ( $coverage_options as $option ) : ?><div class="csi-coverage-row"><button type="button" class="csi-drag" aria-label="<?php esc_attr_e( 'Drag to reorder', 'codenitive-shipping-insurance' ); ?>">⋮⋮</button><div class="csi-money"><span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span><input type="number" min="0.01" step="0.01" name="<?php echo esc_attr( $option_name ); ?>[coverage_amount][]" value="<?php echo esc_attr( $option['coverage'] ); ?>" required></div><div class="csi-money"><span><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span><input type="number" min="0" step="0.01" name="<?php echo esc_attr( $option_name ); ?>[coverage_fee][]" value="<?php echo esc_attr( $option['fee'] ); ?>" required></div><button type="button" class="button-link-delete csi-remove-row"><?php esc_html_e( 'Remove', 'codenitive-shipping-insurance' ); ?></button></div><?php endforeach; ?>
							</div><button type="button" class="button button-secondary" id="csi-add-coverage"><span aria-hidden="true">＋</span> <?php esc_html_e( 'Add coverage choice', 'codenitive-shipping-insurance' ); ?></button></div></div>
							<div class="csi-field"><label for="csi-tiered-description"><?php esc_html_e( 'Coverage explanation', 'codenitive-shipping-insurance' ); ?></label><textarea id="csi-tiered-description" rows="4" name="<?php echo esc_attr( $option_name ); ?>[tiered_description]"><?php echo esc_textarea( $s['tiered_description'] ); ?></textarea></div>
						</div></section>

						<section class="csi-panel" data-panel="advanced"><div class="csi-card"><div class="csi-card__heading"><h2><?php esc_html_e( 'Advanced settings', 'codenitive-shipping-insurance' ); ?></h2><p><?php esc_html_e( 'Control tax, defaults, and checkout instructions.', 'codenitive-shipping-insurance' ); ?></p></div>
							<div class="csi-field csi-field--inline csi-mode csi-mode--toggle"><div><label><?php esc_html_e( 'Enabled by default', 'codenitive-shipping-insurance' ); ?></label><p><?php esc_html_e( 'Applies only to the single fee toggle.', 'codenitive-shipping-insurance' ); ?></p></div><label class="csi-switch"><input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[default_state]" value="1" <?php checked( $s['default_state'], 'yes' ); ?>><span></span></label></div>
							<div class="csi-field csi-field--inline"><div><label><?php esc_html_e( 'Taxable fee', 'codenitive-shipping-insurance' ); ?></label><p><?php esc_html_e( 'Allow WooCommerce to calculate tax on insurance fees.', 'codenitive-shipping-insurance' ); ?></p></div><label class="csi-switch"><input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[taxable]" value="1" <?php checked( $s['taxable'], 'yes' ); ?>><span></span></label></div>
							<div class="csi-mode csi-mode--tiered"><div class="csi-field csi-field--inline"><div><label><?php esc_html_e( 'Shipping instructions', 'codenitive-shipping-insurance' ); ?></label><p><?php esc_html_e( 'Show an optional instructions field on checkout.', 'codenitive-shipping-insurance' ); ?></p></div><label class="csi-switch"><input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[instructions_enabled]" value="1" <?php checked( $s['instructions_enabled'], 'yes' ); ?>><span></span></label></div>
								<div class="csi-field"><label for="csi-instructions-label"><?php esc_html_e( 'Instructions label', 'codenitive-shipping-insurance' ); ?></label><input type="text" id="csi-instructions-label" name="<?php echo esc_attr( $option_name ); ?>[instructions_label]" value="<?php echo esc_attr( $s['instructions_label'] ); ?>"></div>
								<div class="csi-field"><label for="csi-instructions-description"><?php esc_html_e( 'Help text', 'codenitive-shipping-insurance' ); ?></label><input type="text" id="csi-instructions-description" name="<?php echo esc_attr( $option_name ); ?>[instructions_description]" value="<?php echo esc_attr( $s['instructions_description'] ); ?>"></div></div>
						</div></section>
					</div>
					<aside class="csi-admin__sidebar"><div class="csi-card csi-save-card"><h3><?php esc_html_e( 'Save your changes', 'codenitive-shipping-insurance' ); ?></h3><p><?php esc_html_e( 'Review your settings, then save and test the cart and checkout.', 'codenitive-shipping-insurance' ); ?></p><?php submit_button( __( 'Save Settings', 'codenitive-shipping-insurance' ), 'primary', 'submit', false ); ?></div><div class="csi-card csi-help-card"><h3><?php esc_html_e( 'Quick tip', 'codenitive-shipping-insurance' ); ?></h3><p><?php esc_html_e( 'Coverage choices always start with a decline option so customers can clearly opt out.', 'codenitive-shipping-insurance' ); ?></p></div></aside>
				</div>
			</form>
		</div>
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
    	$settings = $this->settings();
    	$default  = $settings['default_state'];
    
    	if ( ! WC()->session ) {
    		return 'yes' === $default;
    	}
    
    	$saved_state = WC()->session->get(
    		self::SESSION,
    		null
    	);
    
    	$session_default = WC()->session->get(
    		self::SESSION_DEFAULT,
    		null
    	);
    
    	/*
    	 * Initialize the selection when:
    	 * 1. No selection exists in the session.
    	 * 2. The administrator changed the default setting.
    	 */
    	if (
    		null === $saved_state ||
    		$default !== $session_default
    	) {
    		WC()->session->set(
    			self::SESSION,
    			$default
    		);
    
    		WC()->session->set(
    			self::SESSION_DEFAULT,
    			$default
    		);
    
    		return 'yes' === $default;
    	}
    
    	return 'yes' === $saved_state;
    }

	private function selected_choice() {
		$options = $this->coverage_options();
		$choice  = WC()->session ? (string) WC()->session->get( self::CHOICE_SESSION, 'decline' ) : 'decline';

		return isset( $options[ $choice ] ) ? $choice : 'decline';
	}

	private function selected_fee_data() {
		$s = $this->settings();

		if ( 'tiered' === $s['display_type'] ) {
			$choice  = $this->selected_choice();
			$options = $this->coverage_options();

			if ( 'decline' === $choice || ! isset( $options[ $choice ] ) ) {
				return null;
			}

			return array(
				'fee'      => (float) $options[ $choice ]['fee'],
				'coverage' => $options[ $choice ]['coverage'],
				'label'    => sprintf(
					/* translators: %s: insurance coverage amount. */
					__( 'Shipping Insurance - Coverage up to %s', 'codenitive-shipping-insurance' ),
					html_entity_decode( wp_strip_all_tags( wc_price( (float) $options[ $choice ]['coverage'] ) ), ENT_QUOTES, get_bloginfo( 'charset' ) )
				),
			);
		}

		if ( ! $this->is_selected() ) {
			return null;
		}

		return array(
			'fee'      => (float) $s['fee'],
			'coverage' => '',
			'label'    => $s['label'],
		);
	}

	private function render( $context ) {
		$s = $this->settings();
		if ( 'yes' !== $s['enabled'] ) return;

		if ( 'tiered' === $s['display_type'] ) {
			$this->render_tiered( $context, $s );
			return;
		}

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

	private function render_tiered( $context, $s ) {
		$selected = $this->selected_choice();
		$options  = $this->coverage_options();
		?>
		<section class="codenitive-insurance codenitive-insurance--tiered codenitive-insurance--<?php echo esc_attr( $context ); ?>">
			<h3><?php echo esc_html( $s['tiered_heading'] ); ?></h3>
			<div class="codenitive-insurance__choices">
				<label class="codenitive-insurance__choice">
					<input type="radio" name="codenitive_shipping_insurance_choice" value="decline" <?php checked( $selected, 'decline' ); ?>>
					<span><?php echo esc_html( $s['decline_label'] ); ?></span>
				</label>
				<?php foreach ( $options as $coverage => $option ) : ?>
					<label class="codenitive-insurance__choice">
						<input type="radio" name="codenitive_shipping_insurance_choice" value="<?php echo esc_attr( $coverage ); ?>" <?php checked( $selected, $coverage ); ?>>
						<span><?php
							echo wp_kses_post(
								sprintf(
									/* translators: 1: coverage amount, 2: insurance fee. */
									__( 'We Cover Up To %1$s (%2$s)', 'codenitive-shipping-insurance' ),
									wc_price( (float) $option['coverage'] ),
									'<strong>' . wc_price( (float) $option['fee'] ) . '</strong>'
								)
							);
						?></span>
					</label>
				<?php endforeach; ?>
			</div>
			<?php if ( ! empty( $s['tiered_description'] ) ) : ?>
				<p class="codenitive-insurance__tiered-description"><?php echo esc_html( $s['tiered_description'] ); ?></p>
			<?php endif; ?>
			<?php if ( 'checkout' === $context && 'yes' === $s['instructions_enabled'] ) : ?>
				<div class="codenitive-insurance__instructions">
					<label for="codenitive_shipping_instructions"><strong><?php echo esc_html( $s['instructions_label'] ); ?></strong></label>
					<textarea id="codenitive_shipping_instructions" name="codenitive_shipping_instructions" rows="5"><?php echo esc_textarea( WC()->session ? WC()->session->get( self::INSTRUCTIONS_SESSION, '' ) : '' ); ?></textarea>
					<?php if ( ! empty( $s['instructions_description'] ) ) : ?>
						<p><?php echo esc_html( $s['instructions_description'] ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>
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

		$s = $this->settings();

		if ( 'tiered' === $s['display_type'] ) {
			$choice = isset( $_POST['choice'] )
				? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['choice'] ) ) )
				: 'decline';
			$options = $this->coverage_options();
			$choice  = isset( $options[ $choice ] ) ? $choice : 'decline';
			WC()->session->set( self::CHOICE_SESSION, $choice );
			WC()->session->set( self::SESSION, 'decline' === $choice ? 'no' : 'yes' );
		} else {
			$enabled = isset( $_POST['enabled'] ) && 'yes' === sanitize_text_field( wp_unslash( $_POST['enabled'] ) );
			WC()->session->set( self::SESSION, $enabled ? 'yes' : 'no' );
		}

		WC()->cart->calculate_totals();
		wp_send_json_success();
	}

	public function capture_checkout_state( $post_data ) {
        if ( is_array( $post_data ) ) {
            $data = $post_data;
        } else {
            parse_str( (string) $post_data, $data );
        }
    
		$s = $this->settings();

		if ( 'tiered' === $s['display_type'] ) {
			$choice  = isset( $data['codenitive_shipping_insurance_choice'] ) ? wc_format_decimal( wp_unslash( $data['codenitive_shipping_insurance_choice'] ) ) : 'decline';
			$options = $this->coverage_options();
			$choice  = isset( $options[ $choice ] ) ? $choice : 'decline';
			WC()->session->set( self::CHOICE_SESSION, $choice );
			WC()->session->set( self::SESSION, 'decline' === $choice ? 'no' : 'yes' );
		} else {
			$is_enabled = isset( $data['codenitive_shipping_insurance'] ) && 'yes' === $data['codenitive_shipping_insurance'];
			WC()->session->set( self::SESSION, $is_enabled ? 'yes' : 'no' );
		}

		if ( isset( $data['codenitive_shipping_instructions'] ) ) {
			WC()->session->set( self::INSTRUCTIONS_SESSION, sanitize_textarea_field( wp_unslash( $data['codenitive_shipping_instructions'] ) ) );
		}
    }

	public function add_fee( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) return;
		$s        = $this->settings();
		$fee_data = $this->selected_fee_data();
		if ( 'yes' === $s['enabled'] && $fee_data && $fee_data['fee'] > 0 ) {
			$cart->add_fee( $fee_data['label'], $fee_data['fee'], 'yes' === $s['taxable'] );
		}
	}

	public function save_order_meta( $order, $data ) {
		$fee_data = $this->selected_fee_data();
		$order->update_meta_data( '_codenitive_shipping_insurance', $fee_data ? 'yes' : 'no' );

		if ( $fee_data ) {
			$order->update_meta_data( '_codenitive_shipping_insurance_fee', $fee_data['fee'] );
			if ( '' !== $fee_data['coverage'] ) {
				$order->update_meta_data( '_codenitive_shipping_insurance_coverage', $fee_data['coverage'] );
			}
		}

		$s = $this->settings();
		$instructions = 'tiered' === $s['display_type'] && 'yes' === $s['instructions_enabled']
			? ( isset( $data['codenitive_shipping_instructions'] )
				? sanitize_textarea_field( wp_unslash( $data['codenitive_shipping_instructions'] ) )
				: ( WC()->session ? sanitize_textarea_field( WC()->session->get( self::INSTRUCTIONS_SESSION, '' ) ) : '' ) )
			: '';

		if ( '' !== $instructions ) {
			$order->update_meta_data( '_codenitive_shipping_instructions', $instructions );
		}
	}

	public function show_order_meta( $order ) {
		if ( 'yes' === $order->get_meta( '_codenitive_shipping_insurance' ) ) {
			echo '<p><strong>' . esc_html__( 'Shipping insurance:', 'codenitive-shipping-insurance' ) . '</strong> ' . wp_kses_post( wc_price( (float) $order->get_meta( '_codenitive_shipping_insurance_fee' ), array( 'currency' => $order->get_currency() ) ) ) . '</p>';
			if ( $order->get_meta( '_codenitive_shipping_insurance_coverage' ) ) {
				echo '<p><strong>' . esc_html__( 'Insurance coverage:', 'codenitive-shipping-insurance' ) . '</strong> ' . wp_kses_post( wc_price( (float) $order->get_meta( '_codenitive_shipping_insurance_coverage' ), array( 'currency' => $order->get_currency() ) ) ) . '</p>';
			}
		}

		if ( $order->get_meta( '_codenitive_shipping_instructions' ) ) {
			echo '<p><strong>' . esc_html__( 'Shipping instructions:', 'codenitive-shipping-insurance' ) . '</strong><br>' . nl2br( esc_html( $order->get_meta( '_codenitive_shipping_instructions' ) ) ) . '</p>';
		}
	}
}

new Codenitive_Shipping_Insurance();
