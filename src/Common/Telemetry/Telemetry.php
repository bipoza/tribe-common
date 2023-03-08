<?php
/**
 * Handles Telemetry setup and actions.
 *
 * @since   TBD
 *
 * @package TEC\Common\Telemetry
 */
namespace TEC\Common\Telemetry;

use TEC\Common\StellarWP\Telemetry\Core;
use TEC\Common\StellarWP\Telemetry\Config;
use TEC\Common\StellarWP\Telemetry\Opt_In\Opt_In_Subscriber;
use TEC\Common\StellarWP\Telemetry\Opt_In\Status;
use TEC\Common\Container;

/**
 * Class Telemetry
 *
 * @since   TBD

 * @package TEC\Common\Telemetry
 */
final class Telemetry {
	/**
	 * The plugin slug used for identification
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	protected static $plugin_slug  = 'tec-common';

	/**
	 * The custom hook prefix.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	protected static $hook_prefix = 'tec';

	/**
	 * Array to hold the optin args.
	 *
	 * @since TBD
	 *
	 * @var array
	 */
	private $optin_args = [];

	/**
	 * The slug for the parent plugin.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	private static $parent_plugin = '';

	function init() {
		/**
		 * Configure the container.
		 *
		 * The container must be compatible with stellarwp/container-contract.
		 * See here: https://github.com/stellarwp/container-contract#usage.
		 *
		 * If you do not have a container, we recommend https://github.com/lucatume/di52
		 * and the corresponding wrapper:
		 * https://github.com/stellarwp/container-contract/blob/main/examples/di52/Container.php
		 */
		$container = new Container();
		Config::set_container( $container );

		// Set the full URL for the Telemetry Server API.
		Config::set_server_url( 'https://telemetry-api.moderntribe.qa/api/v1' );

		// Set a unique prefix for actions & filters.
		Config::set_hook_prefix( self::$hook_prefix );

		// Set a unique plugin slug.
		Config::set_stellar_slug( self::$plugin_slug );

		// Initialize the library.

		Core::instance()->init( \Tribe__Main::instance()->get_parent_plugin_file() );
	}

	/**
	 * Get the slug of the parent plugin.
	 * Hydrated lazily.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public static function get_parent_plugin_slug(): string {
		if ( empty( self::$parent_plugin ) ) {
			$file = \Tribe__Main::instance()->get_parent_plugin_file();
			self::$parent_plugin = substr( $file, ( strrpos( $file, '/' ) + 1 ), ( strlen( $file ) - ( strrpos( $file, '/' ) + 5 ) ),  );
		}
		//"/Users/stephenpage/Sites/tec/dev/plugins/the-events-calendar/the-events-calendar.php"
		return self::$parent_plugin;
	}

	public static function get_slug(): string {
		return self::$plugin_slug;
	}

	public static function get_optin_arg_hook(): string {
		$slug = self::get_slug();
		return "stellarwp/telemetry/{$slug}/optin_args";
	}

	public static function get_permissions_url(): string {
		return apply_filters( 'tec_common_telemetry_permissions_url', '#' );
	}

	public static function get_terms_url(): string {
		return apply_filters( 'tec_common_telemetry_terms_url', '#' );
	}

	public static function get_privacy_url(): string {
		return apply_filters( 'tec_common_telemetry_privacy_url', '#' );
	}

	/**
	 * Filters the default optin modal args.
	 *
	 * @since TBD
	 *
	 * @param array<string|mixed> $args The current optin modal args.
	 *
	 * @return array<string|mixed>
	 */
	public function filter_optin_args( $args ): array {
		$user_name   = esc_html( wp_get_current_user()->display_name );

		/*
		if ET only change logo, name to Event Tickets
		if TEC only change logo
		If both, use The Events Calendar
		*/

		$optin_args = [
			'plugin_logo'           => tribe_resource_url( 'images/logo/tec-brand.svg', false, null, \Tribe__Main::instance() ),
			'plugin_logo_width'     => 'auto',
			'plugin_logo_height'    => 42,
			'plugin_logo_alt'       => 'TEC Common Logo',
			'plugin_name'           => 'TEC Common',
			'plugin_slug'           => self::$plugin_slug,
			'user_name'             => $user_name,
			'permissions_url'       => self::get_permissions_url(),
			'tos_url'               => self::get_terms_url(),
			'privacy_url'           => self::get_privacy_url(),
			'opted_in_plugins_text' => __( 'See which plugins you have opted in to tracking for', 'tribe-common' ),
			'heading'               => __( 'We hope you love TEC Common!', 'tribe-common' ),
			'intro'                 => __( "Hi, {$user_name}! This is an invitation to help our StellarWP community. If you opt-in, some data about your usage of TEC Common and future StellarWP Products will be shared with our teams (so they can work their butts off to improve). We will also share some helpful info on WordPress, and our products from time to time. And if you skip this, that’s okay! Our products still work just fine.", 'tribe-common' ),
		];

		$this->optin_args = apply_filters( 'tec_common_telemetry_optin_args', $optin_args );

		return array_merge( $args, $this->optin_args );
	}

	/**
	 * Triggers Telemetry's opt-in modal with our parameters.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function do_optin_modal(): void {
		$plugin_slug = self::$plugin_slug;

		$go = apply_filters( 'tec_common_telemetry_do_optin_modal', true, $plugin_slug );

		if ( ! $go ) {
			return;
		}

		do_action( "stellarwp/telemetry/{$plugin_slug}/optin" );
	}

	/**
	 * Saves the "Opt In Status" setting.
	 *
	 * @return void
	 */
	public function save_opt_in_setting_field(): void {
		// Return early if not saving the Opt In Status field.
		if ( ! isset( $_POST[ 'current-settings-tab' ] ) ) {
			return;
		}

		/**
		 * Filter for the the settings page/tab that the optin control goes on.
		 *
		 * @since TBD
		 *
		 * @param string $tab    The tab slug where the optin control is found.
		 * @param string $parent The prefix fot the setting
		 */
		$optin_tab = apply_filters( 'tec_common_telemetry_optin_tab', 'general' );

		$parent = self::get_parent_plugin_slug();
		$optin_tab = apply_filters( "tec_common_telemetry_{$parent}_optin_tab", $optin_tab );

		if ( $_POST[ 'current-settings-tab' ] !== $optin_tab ) {
			return;
		}

		// Get an instance of the Status class.
		$status = $this->get_status_object();

		// Get the value submitted on the settings page as a boolean.
		$value = ! empty( filter_input( INPUT_POST, 'opt-in-status', FILTER_VALIDATE_BOOLEAN ) );

		$status->set_status( $value );
	}

	public function get_status_object(): Status {
		return Config::get_container()->get( Status::class );
	}

	/**
	 * The library attempts to set the opt-in status for a site during 'admin_init'. Use the hook with a priority higher
	 * than 10 to make sure you're setting the status after it initializes the option in the options table.
	 */
	function migrate_existing_opt_in(): void {
		$user_has_opted_in_already = get_option( 'fs_accounts' ); // For now.

		if ( $user_has_opted_in_already ) {
			// Get the Opt_In_Subscriber object.
			$Opt_In_Subscriber = Config::get_container()->get( Opt_In_Subscriber::class );
			$Opt_In_Subscriber->opt_in();
		}
	}

	/**
	 * Get the status for this plugin, we don't care about the rest.
	 *
	 * @since TBD
	 *
	 * @return bool
	 */
	public static function get_plugin_status(): bool {
		$hook_prefix = self::$hook_prefix;
		$status_obj  = self::get_status_object();
		$option      = $status_obj->get_option();

		// Avoid checking for missing items
		if ( ! isset( $option['plugins'][self::$plugin_slug]['optin'] ) ) {
			return Status::STATUS_INACTIVE;
		}

		$status = $option['plugins'][self::$plugin_slug]['optin'];

		/**
		 * Filters the opt-in status value.
		 *
		 * @since TBD
		 *
		 * @param boolean $status The opt-in status value.
		 */
		return (bool) apply_filters( "tec_common_telemetry_{$hook_prefix}_optin_status", $status );
	}
}
