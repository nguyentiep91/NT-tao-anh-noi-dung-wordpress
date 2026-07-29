<?php
/**
 * Administrator-configurable rules deciding how many in-article images a post gets.
 *
 * Trước 0.16.0 các ngưỡng này nằm cứng trong code (<1500 từ → 1 ảnh,
 * 1500–3000 → 2, >3000 → 3, bài khóa học/dịch vụ tối đa 2).
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Plan_Settings {
	private const OPTION = 'nt_content_images_plan_settings';

	/** Content types luôn bị giới hạn riêng (trang bán khóa học/dịch vụ không cần nhiều ảnh minh hoạ). */
	public const SPECIAL_TYPES = array( 'course', 'service', 'event', 'education_event', 'procurement_service' );

	/** @return array<string, int> */
	public static function defaults(): array {
		return array(
			'threshold_small' => 1500,
			'threshold_large' => 3000,
			'count_small'     => 1,
			'count_medium'    => 2,
			'count_large'     => 3,
			'max_images'      => 3,
			'special_max'     => 2,
		);
	}

	/** @return array<string, int> */
	public function get(): array {
		$raw = get_option( self::OPTION, array() );
		return self::sanitize( is_array( $raw ) ? $raw : array() );
	}

	/** @return array<string, int>|WP_Error */
	public function save( array $raw ) {
		$clean = self::sanitize( $raw );
		if ( $clean['threshold_large'] <= $clean['threshold_small'] ) {
			return new WP_Error( 'ntci_plan_thresholds_invalid', __( 'Ngưỡng từ phía trên phải lớn hơn ngưỡng phía dưới.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		update_option( self::OPTION, $clean, false );
		return $clean;
	}

	/** Number of in-article images planned for one post. */
	public function image_count( int $word_count, string $content_type ): int {
		$rules = $this->get();
		$count = $word_count < $rules['threshold_small']
			? $rules['count_small']
			: ( $word_count <= $rules['threshold_large'] ? $rules['count_medium'] : $rules['count_large'] );
		$count = min( $count, $rules['max_images'] );
		if ( in_array( sanitize_key( $content_type ), self::SPECIAL_TYPES, true ) ) {
			$count = min( $count, $rules['special_max'] );
		}
		return max( 0, min( 5, $count ) );
	}

	public static function delete_options(): void {
		delete_option( self::OPTION );
	}

	/**
	 * @param array<string, mixed> $raw Raw values.
	 * @return array<string, int>
	 */
	private static function sanitize( array $raw ): array {
		$defaults = self::defaults();
		return array(
			'threshold_small' => min( 20000, max( 100, absint( $raw['threshold_small'] ?? $defaults['threshold_small'] ) ) ),
			'threshold_large' => min( 50000, max( 200, absint( $raw['threshold_large'] ?? $defaults['threshold_large'] ) ) ),
			'count_small'     => min( 5, absint( $raw['count_small'] ?? $defaults['count_small'] ) ),
			'count_medium'    => min( 5, absint( $raw['count_medium'] ?? $defaults['count_medium'] ) ),
			'count_large'     => min( 5, absint( $raw['count_large'] ?? $defaults['count_large'] ) ),
			'max_images'      => min( 5, absint( $raw['max_images'] ?? $defaults['max_images'] ) ),
			'special_max'     => min( 5, absint( $raw['special_max'] ?? $defaults['special_max'] ) ),
		);
	}
}
