<?php
/**
 * EDD Utilities Bootstrap
 *
 * @package     EDD
 * @subpackage  Utilities
 * @copyright   Copyright (c) 2018, Pippin Williamson
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       3.0
 */

/**
 * Class that bootstraps various utilities leveraged in EDD core.
 *
 * @since 3.0
 */
class EDD_Utilities {

	/**
	 * Represents the WordPress gmt offset in seconds.
	 *
	 * @since 3.0
	 * @var   int
	 */
	private $gmt_offset = null;

	/**
	 * Represents the value of the WordPress 'date_format' option at run-time.
	 *
	 * @since 3.0
	 * @var   string
	 */
	private $date_format = null;

	/**
	 * Represents the value of the WordPress 'time_format' option at run-time.
	 *
	 * @since 3.0
	 * @var   string
	 */
	private $time_format = null;

	/**
	 * Represents the value of the WordPress time zone at run-time.
	 *
	 * @since 3.0
	 * @var   string
	 */
	private $time_zone = null;

	/**
	 * Sets up instantiating core utilities.
	 *
	 * @since 3.0
	 */
	public function __construct() {
		$this->set_gmt_offset();
		$this->set_date_format();
		$this->set_time_format();
		$this->set_time_zone();
		$this->includes();
	}

	/**
	 * Loads needed files for core utilities.
	 *
	 * @since 3.0
	 */
	private function includes() {
		$utils_dir = EDD_PLUGIN_DIR . 'includes/utils/';

		// Interfaces.
		require_once $utils_dir . 'interface-static-registry.php';
		require_once $utils_dir . 'interface-error-logger.php';

		// Exceptions.
		require_once $utils_dir . 'class-edd-exception.php';
		require_once $utils_dir . 'exceptions/class-attribute-not-found.php';
		require_once $utils_dir . 'exceptions/class-invalid-argument.php';
		require_once $utils_dir . 'exceptions/class-invalid-parameter.php';

		// Date management.
		require_once $utils_dir . 'class-date.php';

		// Registry.
		require_once $utils_dir . 'class-registry.php';
	}

	/**
	 * Retrieves a given registry instance by name.
	 *
	 * @since 3.0
	 *
	 * @param string $name Registry name.
	 * @return \EDD\Utils\Registry|\WP_Error The registry instance if it exists, otherwise a WP_Error..
	 */
	public function get_registry( $name ) {

		switch( $name ) {
			case 'reports':

				if ( ! did_action( 'edd_reports_init' ) ) {

					_doing_it_wrong( __FUNCTION__, 'The Report registry cannot be retrieved prior to the edd_reports_init hook.', 'EDD 3.0' );

				} elseif ( class_exists( '\EDD\Reports\Data\Report_Registry' ) ) {

					$registry = \EDD\Reports\Data\Report_Registry::instance();

				}
				break;

			case 'reports:endpoints':

				if ( ! did_action( 'edd_reports_init' ) ) {

					_doing_it_wrong( __FUNCTION__, 'The Endpoints registry cannot be retrieved prior to the edd_reports_init hook.', 'EDD 3.0' );

				} elseif ( class_exists( '\EDD\Reports\Data\Endpoint_Registry' ) ) {

					$registry = \EDD\Reports\Data\Endpoint_Registry::instance();

				}
				break;

			default:
				$registry = new \WP_Error( 'invalid_registry', "The '{$name}' registry does not exist." );
				break;
		}

		return $registry;
	}

	/**
	 * Retrieves a date format string based on a given short-hand format.
	 *
	 * @since 3.0
	 *
	 * @param string $format Shorthand date format string. Accepts 'date', 'time', 'mysql', 'datetime',
	 *                       'picker-field' or 'picker-js'. If none of the accepted values, the
	 *                       original value will simply be returned. Default is the value of the
	 *                       `$date_format` property, derived from the core 'date_format' option.
	 * @return string date_format()-compatible date format string.
	 */
	public function get_date_format_string( $format = 'date' ) {

		if ( empty( $format ) ) {
			$format = 'date';
		}

		if ( ! in_array( $format, array( 'date', 'time', 'datetime', 'mysql', 'picker-field', 'picker-js' ) ) ) {
			return $format;
		}

		switch( $format ) {
			case 'picker-field' :
				$format = 'yyyy-mm-dd';
				break;

			case 'picker-js' :
				$format = 'yy-mm-dd';
				break;

			case 'mysql':
				$format = 'Y-m-d H:i:s';
				break;

			case 'datetime':
				$format = $this->get_date_format() . ' ' . $this->get_time_format();
				break;

			case 'time':
				$format = $this->get_time_format();
				break;

			case 'date':
			default:
				$format = $this->get_date_format();
				break;
		}

		return $format;
	}

	/**
	 * Retrieves a date instance for the WP timezone (and offset) based on the given date string.
	 *
	 * Incoming time is expected to be UTC.
	 *
	 * @since 3.0
	 *
	 * @param string $date_string  Optional. Date string. Default 'now'.
	 * @param string $timezone     Optional. Timezone to generate the Carbon instance for.
	 *                             Default is the timezone set in WordPress settings.
	 * @param bool   $apply_offset Optional. Whether to apply the offset in seconds to the generated
	 *                             date. Default true.
	 * @return \EDD\Utils\Date Date instance.
	 */
	public function date( $date_string = 'now', $timezone = null, $apply_offset = true ) {

		// Fallback to this time zone
		if ( null === $timezone ) {
			$timezone = $this->get_time_zone();
		}

		/*
		 * Create the DateTime object with the "local" WordPress timezone.
		 *
		 * Note that supplying the timezone during DateTime instantiation doesn't actually
		 * convert the UNIX timestamp, it just lays the groundwork for deriving the offset.
		 */
		$date = new EDD\Utils\Date( $date_string, new DateTimezone( $timezone ) );

		if ( false === $apply_offset ) {
			/*
			 * The offset is automatically applied when the Date object is instantiated.
			 *
			 * If $apply_offset is false, the interval needs to be removed again after the fact.
			 */
			$offset   = $date->getOffset();
			$interval = \DateInterval::createFromDateString( "-{$offset} seconds" );
			$date->add( $interval );
		}

		return $date;
	}

	/**
	 * Retrieves the WordPress GMT offset property, as cached at run-time.
	 *
	 * @since 3.0
	 *
	 * @param bool $refresh Optional. Whether to refresh the `$gmt_offset` value before retrieval.
	 *                      Default false.
	 * @return int Value of the gmt_offset property.
	 */
	public function get_gmt_offset( $refresh = false ) {
		if ( is_null( $this->gmt_offset ) || ( true === $refresh ) ) {
			$this->set_gmt_offset();
		}

		return $this->gmt_offset;
	}

	/**
	 * Retrieves the WordPress date format, as cached at run-time.
	 *
	 * @since 3.0
	 *
	 * @param bool $refresh Optional. Whether to refresh the `$gmt_offset` value before retrieval.
	 *                      Default false.
	 * @return string Value of the `$date_format` property.
	 */
	public function get_date_format( $refresh = false ) {
		if ( is_null( $this->date_format ) || ( true === $refresh ) ) {
			$this->set_date_format();
		}

		return $this->date_format;
	}

	/**
	 * Retrieves the WordPress time format, as cached at run-time.
	 *
	 * @since 3.0
	 *
	 * @param bool $refresh Optional. Whether to refresh the `$gmt_offset` value before retrieval.
	 *                      Default false.
	 * @return string Value of the `$time_format` property.
	 */
	public function get_time_format( $refresh = false ) {
		if ( is_null( $this->time_format ) || ( true === $refresh ) ) {
			$this->set_time_format();
		}

		return $this->time_format;
	}

	/**
	 * Retrieves the WordPress time zone, as cached at run-time.
	 *
	 * @since 3.0
	 *
	 * @param bool $refresh Optional. Whether to refresh the `$time_zone` value before retrieval.
	 *                      Default false.
	 * @return string Value of the `$time_zone` property.
	 */
	public function get_time_zone( $refresh = false ) {
		if ( is_null( $this->time_zone ) || ( true === $refresh ) ) {
			$this->set_time_zone();
		}

		return $this->time_zone;
	}

	/** Private Setters *******************************************************/

	/**
	 * Private setter for GMT offset
	 *
	 * @since 3.0
	 */
	private function set_gmt_offset() {
		$this->gmt_offset = get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS;
	}

	/**
	 * Private setter for date format
	 *
	 * @since 3.0
	 */
	private function set_date_format() {
		$this->date_format = get_option( 'date_format', 'M j, Y' );
	}

	/**
	 * Private setter for time format
	 *
	 * @since 3.0
	 */
	private function set_time_format() {
		$this->time_format = get_option( 'time_format', 'g:i a'  );
	}

	/**
	 * Private setter for time zone
	 *
	 * @since 3.0
	 */
	private function set_time_zone() {

		// Default return value
		$retval = 'UTC';

		// Get some useful values
		$timezone   = get_option( 'timezone_string' );
		$gmt_offset = $this->get_gmt_offset();

		// Use timezone string if it's available
		if ( ! empty( $timezone ) ) {
			$retval = $timezone;

		// Use GMT offset to calculate from list
		} elseif ( ! empty( $gmt_offset ) ) {

			// Attempt to guess the timezone string from the GMT offset & DST
			$is_dst   = date( 'I' );
			$timezone = timezone_name_from_abbr( '', $gmt_offset, $is_dst );

			// Return the timezone
			if ( false !== $timezone ) {
				$retval = $timezone;

			// Last try, guess timezone string manually
			} else {
				$list = timezone_abbreviations_list();

				foreach ( $list as $abbr ) {
					foreach ( $abbr as $city ) {
						if ( ( $city['dst'] == $is_dst ) && ( $city['offset'] == $gmt_offset ) ) {
							$retval = $city['timezone_id'];
							break 2;
						}
					}
				}
			}
		}

		// Set
		$this->time_zone = $retval;
	}
}
