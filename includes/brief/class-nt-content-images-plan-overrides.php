<?php
/**
 * Per-post user overrides layered on top of the automatic image plan.
 *
 * Overrides sống trong postmeta, tách hẳn khỏi brief: tạo lại kế hoạch hay
 * đổi quy tắc chung không ghi đè những gì quản trị viên đã sửa tay. Slot do
 * người dùng thêm mới dùng index từ 101 trở lên để không va chạm với slot
 * tự động (1..N) kể cả khi brief thay đổi số lượng.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Plan_Overrides {
	public const META        = '_ntci_plan_overrides';
	public const EXTRA_BASE  = 100;
	public const SCENE_MAX   = 1000;
	public const HARD_CAP    = 5;

	/** @return array{slots: array<int, array<string, mixed>>, extra: array<int, array<string, mixed>>} */
	public static function get( int $post_id ): array {
		$raw  = json_decode( (string) get_post_meta( absint( $post_id ), self::META, true ), true );
		$raw  = is_array( $raw ) ? $raw : array();
		$data = array(
			'slots' => array(),
			'extra' => array(),
		);
		foreach ( is_array( $raw['slots'] ?? null ) ? $raw['slots'] : array() as $index => $patch ) {
			$index = absint( $index );
			if ( $index > 0 && is_array( $patch ) ) {
				$data['slots'][ $index ] = self::sanitize_patch( $patch );
			}
		}
		foreach ( is_array( $raw['extra'] ?? null ) ? $raw['extra'] : array() as $extra ) {
			if ( ! is_array( $extra ) ) {
				continue;
			}
			$index   = absint( $extra['index'] ?? 0 );
			$heading = sanitize_text_field( (string) ( $extra['heading_text'] ?? '' ) );
			if ( $index > self::EXTRA_BASE && '' !== $heading ) {
				$data['extra'][ $index ] = array_merge( self::sanitize_patch( $extra ), array( 'index' => $index, 'heading_text' => $heading ) );
			}
		}
		return $data;
	}

	public static function has_overrides( int $post_id ): bool {
		$data = self::get( $post_id );
		return array() !== $data['slots'] || array() !== $data['extra'];
	}

	/**
	 * Slots the administrator switched off — the insert step must skip these
	 * even when an approved image already exists for them.
	 *
	 * @return array<int, true> Map of disabled slot indexes.
	 */
	public static function disabled_indexes( int $post_id ): array {
		$data     = self::get( $post_id );
		$disabled = array();
		foreach ( array( 'slots', 'extra' ) as $group ) {
			foreach ( $data[ $group ] as $index => $patch ) {
				if ( isset( $patch['enabled'] ) && false === $patch['enabled'] ) {
					$disabled[ absint( $index ) ] = true;
				}
			}
		}
		return $disabled;
	}

	/** Merges one slot patch (enabled / heading_text / custom_scene). */
	public static function update_slot( int $post_id, int $index, array $patch ): void {
		$data  = self::get( $post_id );
		$index = absint( $index );
		if ( $index > self::EXTRA_BASE && isset( $data['extra'][ $index ] ) ) {
			$data['extra'][ $index ] = array_merge( $data['extra'][ $index ], self::sanitize_patch( $patch, true ) );
		} else {
			$current                 = $data['slots'][ $index ] ?? array();
			$data['slots'][ $index ] = array_merge( $current, self::sanitize_patch( $patch, true ) );
			// Entry về đúng trạng thái mặc định (bật, không đổi heading/scene) thì xoá cho gọn.
			$entry = $data['slots'][ $index ];
			$noop  = ( ! isset( $entry['enabled'] ) || true === $entry['enabled'] )
				&& '' === (string) ( $entry['heading_text'] ?? '' )
				&& '' === (string) ( $entry['custom_scene'] ?? '' );
			if ( $noop ) {
				unset( $data['slots'][ $index ] );
			}
		}
		self::store( $post_id, $data );
	}

	/** @return int|WP_Error New slot index. */
	public static function add_extra( int $post_id, string $heading, int $current_total ) {
		$heading = sanitize_text_field( $heading );
		if ( '' === $heading ) {
			return new WP_Error( 'ntci_plan_heading_empty', __( 'Hãy chọn một mục (H2/H3) để thêm vị trí ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( $current_total >= self::HARD_CAP ) {
			return new WP_Error( 'ntci_plan_cap_reached', __( 'Đã chạm trần 5 ảnh trong bài. Hãy tắt bớt một vị trí trước khi thêm.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$data  = self::get( $post_id );
		$index = self::EXTRA_BASE + 1;
		while ( isset( $data['extra'][ $index ] ) ) {
			$index++;
		}
		$data['extra'][ $index ] = array(
			'index'        => $index,
			'heading_text' => $heading,
			'enabled'      => true,
			'custom_scene' => '',
		);
		self::store( $post_id, $data );
		return $index;
	}

	public static function remove_extra( int $post_id, int $index ): void {
		$data = self::get( $post_id );
		unset( $data['extra'][ absint( $index ) ] );
		self::store( $post_id, $data );
	}

	public static function reset( int $post_id ): void {
		delete_post_meta( absint( $post_id ), self::META );
	}

	/**
	 * Applies overrides onto the automatic plan. Pure — unit tested.
	 *
	 * @param array<int, array<string, mixed>> $brief_images Slots from the brief (index 1..N).
	 * @param array{slots: array<int, array<string, mixed>>, extra: array<int, array<string, mixed>>} $overrides Overrides payload.
	 * @return array<int, array<string, mixed>> Merged slot list, sorted by index.
	 */
	public static function apply( array $brief_images, array $overrides ): array {
		$images = array();
		foreach ( $brief_images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}
			$index = absint( $image['index'] ?? 0 );
			if ( $index < 1 ) {
				continue;
			}
			$patch = $overrides['slots'][ $index ] ?? array();
			if ( '' !== (string) ( $patch['heading_text'] ?? '' ) ) {
				$image['placement'] = array(
					'type'         => 'before_heading',
					'heading_text' => (string) $patch['heading_text'],
					'safety'       => 'safe_candidate',
				);
			}
			$image['enabled']       = ! isset( $patch['enabled'] ) || false !== $patch['enabled'];
			$image['custom_scene']  = (string) ( $patch['custom_scene'] ?? '' );
			$image['user_modified'] = array() !== $patch;
			$image['is_extra']      = false;
			$images[ $index ]       = $image;
		}
		foreach ( $overrides['extra'] as $index => $extra ) {
			$index            = absint( $index );
			$images[ $index ] = array(
				'index'         => $index,
				'purpose'       => sprintf( __( 'Vị trí do quản trị viên thêm: %s', 'nt-tao-anh-noi-dung-wordpress' ), (string) $extra['heading_text'] ),
				'visual_type'   => 'conceptual_visual',
				'aspect_ratio'  => '16:9',
				'text_overlay'  => false,
				'placement'     => array(
					'type'         => 'before_heading',
					'heading_text' => (string) $extra['heading_text'],
					'safety'       => 'safe_candidate',
				),
				'enabled'       => ! isset( $extra['enabled'] ) || false !== $extra['enabled'],
				'custom_scene'  => (string) ( $extra['custom_scene'] ?? '' ),
				'user_modified' => true,
				'is_extra'      => true,
			);
		}
		ksort( $images );
		return array_values( $images );
	}

	/**
	 * Lists candidate headings (H2/H3) found in raw post content. Pure — unit tested.
	 *
	 * @return array<int, array{text: string, level: int}>
	 */
	public static function list_headings( string $content ): array {
		$headings = array();
		if ( preg_match_all( '/<h([23])[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$text = sanitize_text_field( wp_strip_all_tags( (string) $match[2] ) );
				$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
				if ( '' === $text ) {
					continue;
				}
				$exists = false;
				foreach ( $headings as $heading ) {
					if ( $heading['text'] === $text ) {
						$exists = true;
						break;
					}
				}
				if ( ! $exists ) {
					$headings[] = array(
						'text'  => $text,
						'level' => (int) $match[1],
					);
				}
			}
		}
		return $headings;
	}

	/**
	 * @param array<string, mixed> $patch Raw patch.
	 * @param bool                 $keep_missing True: chỉ giữ khóa có mặt trong patch (dùng khi merge).
	 * @return array<string, mixed>
	 */
	private static function sanitize_patch( array $patch, bool $keep_missing = false ): array {
		$clean = array();
		if ( ! $keep_missing || array_key_exists( 'enabled', $patch ) ) {
			$clean['enabled'] = ! isset( $patch['enabled'] ) || false !== $patch['enabled'] && '0' !== (string) $patch['enabled'] && 'false' !== (string) $patch['enabled'];
		}
		if ( ! $keep_missing || array_key_exists( 'heading_text', $patch ) ) {
			$clean['heading_text'] = sanitize_text_field( (string) ( $patch['heading_text'] ?? '' ) );
		}
		if ( ! $keep_missing || array_key_exists( 'custom_scene', $patch ) ) {
			$scene                 = sanitize_textarea_field( (string) ( $patch['custom_scene'] ?? '' ) );
			$clean['custom_scene'] = function_exists( 'mb_substr' ) ? mb_substr( $scene, 0, self::SCENE_MAX, 'UTF-8' ) : substr( $scene, 0, self::SCENE_MAX );
		}
		return $clean;
	}

	/** @param array{slots: array<int, mixed>, extra: array<int, mixed>} $data Overrides payload. */
	private static function store( int $post_id, array $data ): void {
		if ( array() === $data['slots'] && array() === $data['extra'] ) {
			delete_post_meta( absint( $post_id ), self::META );
			return;
		}
		// wp_slash vì update_post_meta unslash dữ liệu — thiếu nó JSON tiếng Việt sẽ hỏng.
		update_post_meta( absint( $post_id ), self::META, wp_slash( (string) wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	}
}
