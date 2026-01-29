<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PRC_Settings {
	private static $option_key = 'prc_options';

	public static function init() {
		add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_settings' ] );
	}

	public static function get_options() {
		$defaults = [
			'base_address'      => '',
			'radius'            => 15,
			'unit'              => 'miles', // or 'km'
			'allowed_prefixes'  => 'FY',

			'field_label'       => 'Postcode',
			'field_placeholder' => 'Enter postcode',
			'button_label'      => 'Check',

			'success_message'   => 'You are covered!',
			'fail_message'      => "Sorry, we don't regularly cover this area",

			'success_cta_text'  => 'Book now',
			'success_cta_url'   => '/book',
			'fail_cta_text'     => 'But we can still come',
			'fail_cta_url'      => '/contact',

			'success_bg'        => '#e6ffed',
			'success_tx'        => '#0a5d2a',
			'fail_bg'           => '#ffefef',
			'fail_tx'           => '#7a0a0a',
			'btn_bg'            => '#111111',
			'btn_tx'            => '#ffffff',

			'show_distance'     => false,
			'contact_email'     => '',
		];

		$opts = get_option( self::$option_key, [] );
		return wp_parse_args( $opts, $defaults );
	}

	public static function add_menu() {
		add_options_page(
			'Postcode Radius Checker',
			'Postcode Radius',
			'manage_options',
			'prc-settings',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register_settings() {
		register_setting( 'prc_settings_group', self::$option_key, [ __CLASS__, 'sanitize' ] );
	}

	public static function sanitize( $input ) {
		$out = [];

		$out['allowed_prefixes']  = isset( $input['allowed_prefixes'] )
			? sanitize_text_field( $input['allowed_prefixes'] )
			: 'FY';

		$out['base_address']      = isset($input['base_address']) ? sanitize_text_field($input['base_address']) : '';
		$out['radius']            = isset($input['radius']) ? floatval($input['radius']) : 0;
		$out['unit']              = in_array(($input['unit'] ?? 'miles'), ['miles','km'], true) ? $input['unit'] : 'miles';

		$out['field_label']       = sanitize_text_field($input['field_label'] ?? 'Postcode');
		$out['field_placeholder'] = sanitize_text_field($input['field_placeholder'] ?? 'Enter postcode');
		$out['button_label']      = sanitize_text_field($input['button_label'] ?? 'Check');

		$out['success_message']   = sanitize_text_field($input['success_message'] ?? 'You are covered!');
		$out['fail_message']      = sanitize_text_field($input['fail_message'] ?? "Sorry, we don't regularly cover this area");

		$out['success_cta_text']  = sanitize_text_field($input['success_cta_text'] ?? 'Book now');
		$out['success_cta_url']   = esc_url_raw($input['success_cta_url'] ?? '#');
		$out['fail_cta_text']     = sanitize_text_field($input['fail_cta_text'] ?? 'But we can still come');
		$out['fail_cta_url']      = esc_url_raw($input['fail_cta_url'] ?? '#');

		$out['success_bg']        = sanitize_hex_color($input['success_bg'] ?? '#e6ffed');
		$out['success_tx']        = sanitize_hex_color($input['success_tx'] ?? '#0a5d2a');
		$out['fail_bg']           = sanitize_hex_color($input['fail_bg'] ?? '#ffefef');
		$out['fail_tx']           = sanitize_hex_color($input['fail_tx'] ?? '#7a0a0a');
		$out['btn_bg']            = sanitize_hex_color($input['btn_bg'] ?? '#111111');
		$out['btn_tx']            = sanitize_hex_color($input['btn_tx'] ?? '#ffffff');

		$out['show_distance']     = !empty($input['show_distance']) ? true : false;
		$out['contact_email']     = sanitize_email($input['contact_email'] ?? '');

		return $out;
	}

	public static function render_page() {
		if ( ! current_user_can('manage_options') ) { return; }
		$opts = self::get_options();
		?>
		<div class="wrap">
			<h1>Postcode Radius Checker</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'prc_settings_group' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row"><label for="allowed_prefixes">Allowed postcode prefixes</label></th>
						<td>
							<input
								name="<?php echo esc_attr(self::$option_key); ?>[allowed_prefixes]"
								type="text"
								id="allowed_prefixes"
								value="<?php echo esc_attr($opts['allowed_prefixes'] ?? 'FY'); ?>"
								class="regular-text"
								placeholder="FY, PR, LA"
							/>
							<p class="description">Comma separated. Example: FY, PR, LA. Matches the start of the outward code.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="base_address">Base address or postcode</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[base_address]" type="text" id="base_address" value="<?php echo esc_attr($opts['base_address']); ?>" class="regular-text" placeholder="Not used in prefix mode" /></td>
					</tr>

					<tr>
						<th scope="row"><label for="radius">Radius</label></th>
						<td>
							<input name="<?php echo esc_attr(self::$option_key); ?>[radius]" type="number" step="0.1" id="radius" value="<?php echo esc_attr($opts['radius']); ?>" class="small-text" />
							<select name="<?php echo esc_attr(self::$option_key); ?>[unit]" id="unit">
								<option value="miles" <?php selected($opts['unit'],'miles'); ?>>miles</option>
								<option value="km" <?php selected($opts['unit'],'km'); ?>>km</option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="field_label">Field label</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[field_label]" type="text" id="field_label" value="<?php echo esc_attr($opts['field_label']); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="field_placeholder">Field placeholder</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[field_placeholder]" type="text" id="field_placeholder" value="<?php echo esc_attr($opts['field_placeholder']); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="button_label">Button label</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[button_label]" type="text" id="button_label" value="<?php echo esc_attr($opts['button_label']); ?>" class="regular-text" /></td>
					</tr>

					<tr>
						<th scope="row"><label for="success_message">Success message</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[success_message]" type="text" id="success_message" value="<?php echo esc_attr($opts['success_message']); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="success_cta_text">Success CTA text</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[success_cta_text]" type="text" id="success_cta_text" value="<?php echo esc_attr($opts['success_cta_text']); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="success_cta_url">Success CTA URL</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[success_cta_url]" type="url" id="success_cta_url" value="<?php echo esc_attr($opts['success_cta_url']); ?>" class="regular-text code" /></td>
					</tr>

					<tr>
						<th scope="row"><label for="fail_message">Fail message</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[fail_message]" type="text" id="fail_message" value="<?php echo esc_attr($opts['fail_message']); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="fail_cta_text">Fail CTA text</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[fail_cta_text]" type="text" id="fail_cta_text" value="<?php echo esc_attr($opts['fail_cta_text']); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="fail_cta_url">Fail CTA URL</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[fail_cta_url]" type="url" id="fail_cta_url" value="<?php echo esc_attr($opts['fail_cta_url']); ?>" class="regular-text code" /></td>
					</tr>

					<tr>
						<th scope="row">Colours</th>
						<td>
							<label>Success bg <input type="text" class="prc-color" name="<?php echo esc_attr(self::$option_key); ?>[success_bg]" value="<?php echo esc_attr($opts['success_bg']); ?>" /></label>
							<label>Success text <input type="text" class="prc-color" name="<?php echo esc_attr(self::$option_key); ?>[success_tx]" value="<?php echo esc_attr($opts['success_tx']); ?>" /></label>
							<br />
							<label>Fail bg <input type="text" class="prc-color" name="<?php echo esc_attr(self::$option_key); ?>[fail_bg]" value="<?php echo esc_attr($opts['fail_bg']); ?>" /></label>
							<label>Fail text <input type="text" class="prc-color" name="<?php echo esc_attr(self::$option_key); ?>[fail_tx]" value="<?php echo esc_attr($opts['fail_tx']); ?>" /></label>
							<br />
							<label>Button bg <input type="text" class="prc-color" name="<?php echo esc_attr(self::$option_key); ?>[btn_bg]" value="<?php echo esc_attr($opts['btn_bg']); ?>" /></label>
							<label>Button text <input type="text" class="prc-color" name="<?php echo esc_attr(self::$option_key); ?>[btn_tx]" value="<?php echo esc_attr($opts['btn_tx']); ?>" /></label>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="show_distance">Show distance</label></th>
						<td><label><input name="<?php echo esc_attr(self::$option_key); ?>[show_distance]" type="checkbox" id="show_distance" value="1" <?php checked( $opts['show_distance'], true ); ?> /> Display calculated distance in the result box</label></td>
					</tr>

					<tr>
						<th scope="row"><label for="contact_email">Contact email for User-Agent</label></th>
						<td><input name="<?php echo esc_attr(self::$option_key); ?>[contact_email]" type="email" id="contact_email" value="<?php echo esc_attr($opts['contact_email']); ?>" class="regular-text" /><p class="description">Used in requests to the OSM Nominatim API to comply with usage policy.</p></td>
					</tr>

				</table>
				<?php submit_button(); ?>
			</form>
			<p><strong>Shortcode:</strong> <code>[postcode_checker]</code></p>
		</div>
		<?php
	}
}
