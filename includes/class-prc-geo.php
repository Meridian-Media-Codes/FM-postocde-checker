<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class PRC_Geo {

	/**
	 * Geocode with a transient cache.
	 * @param string $query Address or postcode.
	 * @param bool   $is_base Whether this is the base address (longer cache).
	 * @return array|false [ 'lat' => float, 'lon' => float ] or false
	 */
	public function geocode_with_cache( $query, $is_base = false ) {
		$key = 'prc_geo_' . md5( strtolower( trim( (string) $query ) ) );
		$cached = get_transient( $key );

		if ( $cached && is_array( $cached ) && isset( $cached['lat'], $cached['lon'] ) ) {
			return $cached;
		}

		$data = $this->geocode( $query );

		if ( $data ) {
			set_transient( $key, $data, $is_base ? DAY_IN_SECONDS * 7 : DAY_IN_SECONDS );
		}

		return $data;
	}

		public function normalize_postcode( $postcode ) {
			$pc = strtoupper( trim( (string) $postcode ) );

			if ( $pc === '' ) {
				return '';
			}

			$pc = preg_replace( '/\s+/', '', $pc );
			return $pc;
		}


	public function extract_outward( $postcode ) {
		$pc = $this->normalize_postcode( $postcode );

		// Outward code is everything before the last 3 characters (inward part)
		// Example: FY11AA -> FY1, FY81AA -> FY8, PR42AB -> PR4
		if ( strlen( $pc ) < 5 ) {
			return $pc;
		}

		return substr( $pc, 0, -3 );
	}

	public function is_allowed_by_prefix( $postcode, $allowed_prefixes_csv ) {
		$outward = $this->extract_outward( $postcode );
		if ( $outward === '' ) {
			return false;
		}

		$allowed = strtoupper( (string) $allowed_prefixes_csv );
		$allowed = preg_replace( '/\s+/', '', $allowed );
		$list = array_filter( array_map( 'trim', explode( ',', $allowed ) ) );

		if ( empty( $list ) ) {
			return false;
		}

		foreach ( $list as $prefix ) {
			if ( $prefix === '' ) {
				continue;
			}
			if ( strpos( $outward, $prefix ) === 0 ) {
				return true;
			}
		}

		return false;
	}


	/**
	 * Geocode router.
	 * - UK postcodes: postcodes.io first (reliable)
	 * - Otherwise: Nominatim (addresses)
	 * @param string $query
	 * @return array|false
	 */
	private function geocode( $query ) {
		$q = trim( (string) $query );

		// UK postcode pattern
		if ( preg_match( '/^[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}$/i', $q ) ) {
			$pcio = $this->geocode_postcodes_io( $q );
			if ( $pcio ) {
				return $pcio;
			}

			// If postcodes.io fails for any reason, try Nominatim as fallback
			return $this->geocode_nominatim( $q . ', UK' );
		}

		// Non-postcode: try Nominatim (full addresses, towns, etc.)
		return $this->geocode_nominatim( $q );
	}

	/**
	 * Geocode UK postcodes using postcodes.io
	 * @param string $postcode
	 * @return array|false
	 */
	private function geocode_postcodes_io( $postcode ) {
		$pc = strtoupper( trim( (string) $postcode ) );
		$pc = preg_replace( '/\s+/', '', $pc );

		$url = 'https://api.postcodes.io/postcodes/' . rawurlencode( $pc );

		$res = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/json',
				'User-Agent' => 'PRC/' . PRC_VERSION . ' ' . home_url( '/' ),
				'Referer'    => home_url( '/' ),
			],
		] );

		if ( is_wp_error( $res ) ) {
			error_log( '[PRC] postcodes.io error for "' . $postcode . '": ' . $res->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $res );
		if ( $code !== 200 ) {
			error_log( '[PRC] postcodes.io error for "' . $postcode . '": HTTP ' . $code );
			return false;
		}

		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( empty( $json['result'] ) || ! is_array( $json['result'] ) ) {
			error_log( '[PRC] postcodes.io error for "' . $postcode . '": empty JSON result' );
			return false;
		}

		$lat = $json['result']['latitude'] ?? null;
		$lon = $json['result']['longitude'] ?? null;

		if ( ! $lat || ! $lon ) {
			error_log( '[PRC] postcodes.io error for "' . $postcode . '": missing lat/lon' );
			return false;
		}

		return [
			'lat' => (float) $lat,
			'lon' => (float) $lon,
		];
	}

	/**
	 * Call Nominatim API with error logging.
	 * @param string $query
	 * @return array|false
	 */
	private function geocode_nominatim( $query ) {
		$opts = PRC_Settings::get_options();
		$email = $opts['contact_email'] ?? '';

		$user_agent = 'PRC/' . PRC_VERSION . ' (WordPress plugin)';
		if ( ! empty( $email ) ) {
			$user_agent .= ' ' . $email;
		}

		$url = add_query_arg( [
			'format' => 'jsonv2',
			'q'      => trim( (string) $query ), // do NOT rawurlencode
			'limit'  => 1,
		], 'https://nominatim.openstreetmap.org/search' );

		$args = [
			'timeout' => 15,
			'headers' => [
				'User-Agent' => $user_agent,
				'Accept'     => 'application/json',
				'Referer'    => home_url( '/' ),
			],
		];

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			error_log( '[PRC] Geocoding error for "' . $query . '": ' . $response->get_error_message() );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 200 ) {
			error_log( '[PRC] Geocoding error for "' . $query . '": HTTP ' . $code );
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( ! is_array( $json ) || empty( $json ) ) {
			error_log( '[PRC] Geocoding error for "' . $query . '": empty JSON' );
			return false;
		}

		$item = $json[0];

		if ( isset( $item['lat'], $item['lon'] ) ) {
			return [
				'lat' => (float) $item['lat'],
				'lon' => (float) $item['lon'],
			];
		}

		error_log( '[PRC] Geocoding error for "' . $query . '": lat/lon missing in JSON' );
		return false;
	}

	/**
	 * Haversine distance in kilometers.
	 */
	public function haversine_km( $lat1, $lon1, $lat2, $lon2 ) {
		$earth_radius_km = 6371.0;

		$dLat = deg2rad( $lat2 - $lat1 );
		$dLon = deg2rad( $lon2 - $lon1 );

		$a = sin( $dLat / 2 ) * sin( $dLat / 2 ) +
			cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) *
			sin( $dLon / 2 ) * sin( $dLon / 2 );

		$c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );

		return $earth_radius_km * $c;
	}
}
