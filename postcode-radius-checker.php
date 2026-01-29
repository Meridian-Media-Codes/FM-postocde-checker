<?php
/**
 * Plugin Name: Postcode Radius Checker
 * Description: Simple postcode radius checker with admin-set base address and radius. Shortcode: [postcode_checker]
 * Version: 1.0.0
 * Author: Meridian Media
 * License: GPLv2 or later
 * Text Domain: prc
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'PRC_VERSION', '1.0.0' );
define( 'PRC_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRC_URL', plugin_dir_url( __FILE__ ) );

require_once PRC_PATH . 'includes/class-prc-settings.php';
require_once PRC_PATH . 'includes/class-prc-geo.php';

class PRC_Plugin {
	public static function init() {
		// Shortcode
		add_shortcode( 'postcode_checker', [ __CLASS__, 'render_shortcode' ] );

		// Assets
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );

		// AJAX
		add_action( 'wp_ajax_prc_check_postcode', [ __CLASS__, 'ajax_check_postcode' ] );
		add_action( 'wp_ajax_nopriv_prc_check_postcode', [ __CLASS__, 'ajax_check_postcode' ] );
	}
	
	public static function enqueue_assets() {
		wp_register_style( 'prc-frontend', PRC_URL . 'assets/css/frontend.css', [], PRC_VERSION );
		wp_enqueue_style( 'prc-frontend' );

		wp_register_script( 'prc-frontend', PRC_URL . 'assets/js/frontend.js', [ 'jquery' ], PRC_VERSION, true );

		$opts = PRC_Settings::get_options();
		$nonce = wp_create_nonce( 'prc_check_nonce' );

		wp_localize_script( 'prc-frontend', 'PRC', [
			'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
			'nonce'            => $nonce,
			'showDistance'     => false,
			'unit'             => 'miles',
			'allowedPrefixes'  => $opts['allowed_prefixes'] ?? 'FY',

			'colors' => [
				'successBg' => $opts['success_bg'] ?? '#e6ffed',
				'successTx' => $opts['success_tx'] ?? '#0a5d2a',
				'failBg'    => $opts['fail_bg'] ?? '#ffefef',
				'failTx'    => $opts['fail_tx'] ?? '#7a0a0a',
				'btnBg'     => $opts['btn_bg'] ?? '#111111',
				'btnTx'     => $opts['btn_tx'] ?? '#ffffff',
			],
			'labels' => [
				'placeholder' => $opts['field_placeholder'] ?? 'Enter postcode',
				'button'      => $opts['button_label'] ?? 'Check',
			],
		] );


		wp_enqueue_script( 'prc-frontend' );
	}

	public static function render_shortcode( $atts ) {
		$opts = PRC_Settings::get_options();

		ob_start();
		?>
		<div class="prc-widget" data-unit="<?php echo esc_attr( $opts['unit'] ?? 'miles' ); ?>">
			<div class="prc-form">
				<label for="prc-postcode" class="screen-reader-text"><?php echo esc_html( $opts['field_label'] ?? 'Postcode' ); ?></label>
				<input type="text" id="prc-postcode" class="prc-input" placeholder="<?php echo esc_attr( $opts['field_placeholder'] ?? 'Enter postcode' ); ?>" inputmode="text" autocomplete="postal-code" />
				<button type="button" id="prc-check" class="prc-btn"><?php echo esc_html( $opts['button_label'] ?? 'Check' ); ?></button>
			</div>
			<div id="prc-result" class="prc-result" aria-live="polite"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function ajax_check_postcode() {
		check_ajax_referer( 'prc_check_nonce', 'nonce' );

		$pc    = isset($_POST['postcode']) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';
		if ( empty( $pc ) ) {
			wp_send_json_error( [ 'message' => 'Postcode missing' ], 400 );
		}

				$opts = PRC_Settings::get_options();

		$allowed_prefixes = trim( (string) ( $opts['allowed_prefixes'] ?? 'FY' ) );
		if ( $allowed_prefixes === '' ) {
			wp_send_json_error( [ 'message' => 'Configuration incomplete' ], 400 );
		}

		$geo = new PRC_Geo();
		$inside = $geo->is_allowed_by_prefix( $pc, $allowed_prefixes );

		$response = [
			'inside' => $inside,
			'settings' => [
				'success_msg' => $opts['success_message'] ?? 'You are covered!',
				'fail_msg'    => $opts['fail_message'] ?? "Sorry, we don't regularly cover this area.",
				'success_cta_text' => $opts['success_cta_text'] ?? 'Book now',
				'success_cta_url'  => $opts['success_cta_url'] ?? '#',
				'fail_cta_text'    => $opts['fail_cta_text'] ?? 'But we can still come',
				'fail_cta_url'     => $opts['fail_cta_url'] ?? '#',
				'show_distance'    => false,
			],
		];

		wp_send_json_success( $response );

	}
}

PRC_Settings::init();
PRC_Plugin::init();
