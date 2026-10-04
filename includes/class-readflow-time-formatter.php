<?php
/**
 * Optional, translatable duration labels; independent of reading calculations.
 *
 * @package ReadFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ReadFlow_Time_Formatter {

	/**
	 * Formats seconds for a badge without altering the source duration.
	 *
	 * Uses ceiling seconds below a minute, then ceiling minutes. Zero is
	 * "0 sec". Invalid, negative, or non-finite input is treated as zero.
	 * Returned strings are plain text; consumers must escape for their context.
	 *
	 * @param mixed $seconds Duration in seconds.
	 * @return array{formatted_time: string, formatted_short_time: string}
	 */
	public static function format( $seconds ) {
		$seconds = is_numeric( $seconds ) ? (float) $seconds : 0.0;
		if ( ! is_finite( $seconds ) || $seconds < 0 || $seconds > PHP_INT_MAX ) {
			$seconds = 0.0;
		}

		if ( ceil( $seconds ) < 60 ) {
			/* translators: %s: Number of seconds. */
			$short = sprintf( __( '%s sec', 'readflow' ), number_format_i18n( ceil( $seconds ) ) );
		} else {
			$minutes = (int) ceil( $seconds / 60 );
			if ( $minutes < 60 ) {
				/* translators: %s: Number of minutes. */
				$short = sprintf( __( '%s min', 'readflow' ), number_format_i18n( $minutes ) );
			} else {
				$hours     = intdiv( $minutes, 60 );
				$remaining = $minutes % 60;
				if ( $remaining ) {
					/* translators: 1: Number of hours, 2: Remaining minutes. */
					$short = sprintf( __( '%1$s hr %2$s min', 'readflow' ), number_format_i18n( $hours ), number_format_i18n( $remaining ) );
				} else {
					/* translators: %s: Number of hours. */
					$short = sprintf( __( '%s hr', 'readflow' ), number_format_i18n( $hours ) );
				}
			}
		}

		return array(
			/* translators: %s: Formatted duration, such as "5 min". */
			'formatted_time'       => sprintf( __( '%s read', 'readflow' ), $short ),
			'formatted_short_time' => $short,
		);
	}
}
