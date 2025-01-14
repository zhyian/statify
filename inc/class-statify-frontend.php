<?php
/**
 * Statify: Statify_Frontend class
 *
 * This file contains the derived class for the plugin's frontend features.
 *
 * @package   Statify
 * @since     1.4.0
 */

// Quit if accessed outside WP context.
defined( 'ABSPATH' ) || exit;

/**
 * Statify_Frontend
 *
 * @since 1.4.0
 */
class Statify_Frontend extends Statify {

	/**
	 * Track the page view
	 *
	 * @since 0.1.0
	 * @since 1.7.0 $is_snippet parameter added.
	 * @since 2.0.0 Removed $is_snippet parameter.
	 *
	 * @return void
	 */
	public static function track_visit(): void {
		if ( self::is_javascript_tracking_enabled() ) {
			return;
		}

		// Set target & referrer.
		$target   = null;
		$referrer = null;
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$target = filter_var( wp_unslash( $_SERVER['REQUEST_URI'] ), FILTER_SANITIZE_URL );
		}
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referrer = filter_var( wp_unslash( $_SERVER['HTTP_REFERER'] ), FILTER_SANITIZE_URL );
		}

		Statify::track( $referrer, $target );
	}

	/**
	 * Declare GET variables for further use
	 *
	 * @since    1.1.0
	 * @version  1.3.1
	 *
	 * @param   array $vars Input with existing variables.
	 *
	 * @return  array  $vars  Output with plugin variables
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'statify_referrer';
		$vars[] = 'statify_target';

		return $vars;
	}


	/**
	 * Print JavaScript snippet
	 *
	 * @since    1.1.0
	 * @version  1.4.1
	 */
	public static function wp_footer(): void {
		// JS tracking disabled or AMP is used for the current request.
		if (
			! self::is_javascript_tracking_enabled() ||
			( function_exists( 'amp_is_request' ) && amp_is_request() ) ||
			( function_exists( 'is_amp_endpoint' ) && is_amp_endpoint() )
		) {
			return;
		}

		// Skip by internal rules (#84).
		if ( self::is_internal() ) {
			return;
		}

		wp_enqueue_script(
			'statify-js',
			plugins_url( 'js/snippet.min.js', STATIFY_FILE ),
			array(),
			STATIFY_VERSION,
			true
		);

		// Add endpoint to script.
		$script_data = array(
			'url' => esc_url_raw( rest_url( Statify_Api::REST_NAMESPACE . '/' . Statify_Api::REST_ROUTE_TRACK ) ),
		);
		if ( Statify::TRACKING_METHOD_JAVASCRIPT_WITH_NONCE_CHECK === self::$_options['snippet'] ) {
			$script_data['nonce'] = wp_create_nonce( 'statify_track' );
		}
		wp_localize_script( 'statify-js', 'statifyAjax', $script_data );
	}

	/**
	 * Add amp-analytics for Standard and Transitional mode.
	 *
	 * @see https://amp-wp.org/documentation/playbooks/analytics/
	 *
	 * @param array $analytics_entries Analytics entries.
	 */
	public static function amp_analytics_entries( array $analytics_entries ): array {
		// Analytics script is only relevant, if "JS" tracking is enabled, to prevent double tracking.
		if ( self::is_javascript_tracking_enabled() ) {
			$analytics_entries['statify'] = array(
				'type'   => '',
				'config' => wp_json_encode( self::make_amp_config() ),
			);
		}

		return $analytics_entries;
	}

	/**
	 * Add AMP-analytics for Reader mode.
	 *
	 * @see https://amp-wp.org/documentation/playbooks/analytics/
	 *
	 * @param array $analytics Analytics.
	 */
	public static function amp_post_template_analytics( array $analytics ): array {
		// Analytics script is only relevant, if "JS" tracking is enabled, to prevent double tracking.
		if ( self::is_javascript_tracking_enabled() ) {
			$analytics['statify'] = array(
				'type'        => '',
				'attributes'  => array(),
				'config_data' => self::make_amp_config(),
			);
		}

		return $analytics;
	}

	/**
	 * Generate AMP-analytics configuration.
	 *
	 * @return array Configuration array.
	 */
	private static function make_amp_config(): array {
		$cfg = array(
			'requests'       => array(
				'pageview' => rest_url( Statify_Api::REST_NAMESPACE . '/' . Statify_Api::REST_ROUTE_TRACK ),
			),
			'extraUrlParams' => array(
				'referrer' => '${documentReferrer}',
				'target'   => '${canonicalPath}amp/',
			),
			'triggers'       => array(
				'trackPageview' => array(
					'on'      => 'visible',
					'request' => 'pageview',
				),
			),
			'transport'      => array(
				'beacon'  => true,
				'xhrpost' => true,
				'image'   => false,
			),
		);

		if ( Statify::TRACKING_METHOD_JAVASCRIPT_WITH_NONCE_CHECK === self::$_options['snippet'] ) {
			$cfg['extraUrlParams']['nonce'] = wp_create_nonce( 'statify_track' );
		}

		return $cfg;
	}
}
