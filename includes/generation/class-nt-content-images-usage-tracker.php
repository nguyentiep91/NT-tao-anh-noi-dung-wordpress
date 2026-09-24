<?php
/**
 * Counts AI images generated per day across all providers.
 *
 * Backs the shared daily cap: batch jobs and single clicks both stop when the
 * administrator's limit is reached. Template overlays render locally and are
 * never counted.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Usage_Tracker {
	private const OPTION = 'nt_content_images_daily_usage';

	/** @return array{date: string, total: int, providers: array<string, int>} */
	public static function get_today(): array {
		$today = gmdate( 'Y-m-d' );
		$raw   = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) || $today !== (string) ( $raw['date'] ?? '' ) ) {
			return array( 'date' => $today, 'total' => 0, 'providers' => array() );
		}
		$providers = is_array( $raw['providers'] ?? null ) ? array_map( 'absint', $raw['providers'] ) : array();
		return array(
			'date'      => $today,
			'total'     => absint( $raw['total'] ?? 0 ),
			'providers' => $providers,
		);
	}

	public static function increment( string $provider ): void {
		$provider = sanitize_key( $provider );
		$usage    = self::get_today();
		$usage['total']++;
		$usage['providers'][ $provider ] = absint( $usage['providers'][ $provider ] ?? 0 ) + 1;
		update_option( self::OPTION, $usage, false );
	}

	public static function remaining( int $limit ): int {
		return max( 0, absint( $limit ) - self::get_today()['total'] );
	}

	public static function is_exhausted( int $limit ): bool {
		return $limit > 0 && self::get_today()['total'] >= $limit;
	}

	/** @return WP_Error Consistent error used by every generation path. */
	public static function limit_error( int $limit ): WP_Error {
		return new WP_Error(
			'ntci_daily_limit_reached',
			sprintf(
				/* translators: %d: daily image limit. */
				__( 'Đã đạt giới hạn %d ảnh AI/ngày. Tăng giới hạn trong cấu hình hoặc chờ sang ngày mới.', 'nt-tao-anh-noi-dung-wordpress' ),
				absint( $limit )
			),
			array( 'status' => 429 )
		);
	}

	public static function delete_options(): void {
		delete_option( self::OPTION );
	}
}
