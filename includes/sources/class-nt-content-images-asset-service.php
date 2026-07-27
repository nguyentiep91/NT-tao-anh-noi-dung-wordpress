<?php
/**
 * Coordinates stock search, safe import and the existing approval workflow.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Asset_Service {
	private NT_Content_Images_Stock_Provider_Manager $providers;
	private NT_Content_Images_Remote_Image_Downloader $downloader;
	private NT_Content_Images_Media_Manager $media;
	private NT_Content_Images_Generation_Repository $generations;
	private NT_Content_Images_Asset_Repository $assets;
	private NT_Content_Images_Generation_Settings $generation_settings;
	private NT_Content_Images_Source_Settings $source_settings;
	private NT_Content_Images_Brief_Repository $briefs;
	private NT_Content_Images_Brief_Generator $brief_generator;
	private NT_Content_Images_Stock_Query_Builder $queries;
	private NT_Content_Images_Generation_Lock $lock;

	public function __construct(
		NT_Content_Images_Stock_Provider_Manager $providers,
		NT_Content_Images_Remote_Image_Downloader $downloader,
		NT_Content_Images_Media_Manager $media,
		NT_Content_Images_Generation_Repository $generations,
		NT_Content_Images_Asset_Repository $assets,
		NT_Content_Images_Generation_Settings $generation_settings,
		NT_Content_Images_Source_Settings $source_settings,
		NT_Content_Images_Brief_Repository $briefs,
		NT_Content_Images_Brief_Generator $brief_generator,
		NT_Content_Images_Stock_Query_Builder $queries,
		?NT_Content_Images_Generation_Lock $lock = null
	) {
		$this->providers = $providers;
		$this->downloader = $downloader;
		$this->media = $media;
		$this->generations = $generations;
		$this->assets = $assets;
		$this->generation_settings = $generation_settings;
		$this->source_settings = $source_settings;
		$this->briefs = $briefs;
		$this->brief_generator = $brief_generator;
		$this->queries = $queries;
		$this->lock = $lock ?? new NT_Content_Images_Generation_Lock();
	}

	public function register(): void {
		add_action( 'nt_content_images_after_featured_approval', array( $this, 'mark_asset_approved' ), 10, 1 );
	}

	public function mark_asset_approved( int $generation_id ): void {
		$this->assets->mark_approved_by_generation( $generation_id );
	}

	/** @return array<string, mixed>|WP_Error */
	public function search( int $post_id, string $provider_id, string $query = '', int $page = 1 ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'attachment' === $post->post_type || wp_is_post_revision( $post_id ) ) {
			return new WP_Error( 'ntci_stock_post_invalid', __( 'Nội dung không hợp lệ để tìm ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$provider = $this->providers->get( $provider_id );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}
		if ( ! $provider->is_configured() ) {
			return new WP_Error( 'ntci_stock_provider_not_configured', __( 'Kho ảnh chưa được cấu hình.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$brief_data = $this->resolve_brief( $post_id );
		if ( is_wp_error( $brief_data ) ) {
			return $brief_data;
		}
		$built = $this->queries->build( $brief_data['brief'] );
		$query = '' !== trim( $query ) ? sanitize_text_field( $query ) : (string) $built['primary_query'];
		$settings = $this->source_settings->get();
		$cache_key = 'ntci_stock_' . substr( hash( 'sha256', $provider_id . '|' . $query . '|' . $page . '|' . $settings['per_page'] ), 0, 32 );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			$cached['cached'] = true;
			return $cached;
		}
		$result = $provider->search(
			array(
				'query'       => $query,
				'page'        => max( 1, $page ),
				'per_page'    => $settings['per_page'],
				'orientation' => 'landscape',
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$result['post_id'] = $post_id;
		$result['brief_id'] = absint( $brief_data['brief_id'] );
		$result['suggested_query'] = (string) $built['primary_query'];
		$result['fallback_queries'] = $built['fallback_queries'];
		$result['cached'] = false;
		set_transient( $cache_key, $result, absint( $settings['search_cache_ttl'] ) );
		return $result;
	}

	/** @return array<string, mixed>|WP_Error */
	public function import( int $post_id, string $provider_id, string $asset_id, string $search_query, bool $license_confirmed ) {
		$post_id = absint( $post_id );
		if ( ! $this->lock->acquire( $post_id ) ) {
			return new WP_Error( 'ntci_stock_import_locked', __( 'Bài viết này đang được xử lý ảnh. Hãy chờ yêu cầu hiện tại hoàn tất.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 409 ) );
		}
		try {
			return $this->import_locked( $post_id, $provider_id, $asset_id, $search_query, $license_confirmed );
		} finally {
			$this->lock->release( $post_id );
		}
	}

	/** @return array<string, mixed>|WP_Error */
	private function import_locked( int $post_id, string $provider_id, string $asset_id, string $search_query, bool $license_confirmed ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post || 'attachment' === $post->post_type || wp_is_post_revision( $post_id ) ) {
			return new WP_Error( 'ntci_stock_post_invalid', __( 'Nội dung không hợp lệ để nhập ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		if ( get_post_thumbnail_id( $post_id ) ) {
			return new WP_Error( 'ntci_stock_featured_exists', __( 'Bài viết đã có ảnh đại diện. Plugin không tự ghi đè.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$provider_id = sanitize_key( $provider_id );
		$asset_id = sanitize_text_field( $asset_id );
		if ( null !== $this->assets->get_by_provider_asset_for_post( $post_id, $provider_id, $asset_id ) ) {
			return new WP_Error( 'ntci_stock_asset_duplicate', __( 'Ảnh kho này đã được nhập cho bài viết.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 409 ) );
		}
		$provider = $this->providers->get( $provider_id );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}
		$brief_data = $this->resolve_brief( $post_id );
		if ( is_wp_error( $brief_data ) ) {
			return $brief_data;
		}
		$brief_id = absint( $brief_data['brief_id'] );
		$asset = $provider->get_asset( $asset_id );
		if ( is_wp_error( $asset ) ) {
			return $asset;
		}
		if ( ! empty( $asset['requires_license_confirmation'] ) && ! $license_confirmed ) {
			return new WP_Error( 'ntci_stock_license_confirmation_required', __( 'Hãy xác nhận đã kiểm tra nguồn và giấy phép của ảnh Openverse.', 'nt-tao-anh-noi-dung-wordpress' ), array( 'status' => 409 ) );
		}
		$download = $this->downloader->download( (string) $asset['download_url'], $provider_id );
		if ( is_wp_error( $download ) ) {
			return $download;
		}
		$stored = $this->media->store_stock_candidate( $post_id, $download, $asset, $this->generation_settings->get() );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		$asset_row_id = $this->assets->insert(
			array_merge(
				$asset,
				$stored,
				array(
					'post_id'           => $post_id,
					'brief_id'          => $brief_id,
					'attachment_id'     => $stored['attachment_id'],
					'source_kind'       => 'stock',
					'provider_asset_id' => $asset['asset_id'],
					'attribution_text'  => $asset['attribution'],
					'search_query'      => sanitize_text_field( $search_query ),
					'original_url'      => $download['original_url'],
					'status'            => 'imported',
				)
			)
		);
		if ( false === $asset_row_id ) {
			wp_delete_attachment( absint( $stored['attachment_id'] ), true );
			return new WP_Error( 'ntci_stock_asset_store_failed', __( 'Không thể lưu thông tin nguồn ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$generation_id = $this->generations->insert(
			array(
				'post_id'       => $post_id,
				'brief_id'      => $brief_id,
				'attachment_id' => $stored['attachment_id'],
				'provider'      => $provider_id,
				'model'         => 'stock-library',
				'status'        => 'generated',
				'prompt'        => sanitize_text_field( $search_query ),
				'settings'      => array( 'source_kind' => 'stock' ),
				'response'      => array(
					'asset_id'        => $asset['asset_id'],
					'source_page_url' => $asset['source_page_url'],
					'creator_name'    => $asset['creator_name'],
					'license_code'    => $asset['license_code'],
					'license_url'     => $asset['license_url'],
					'attribution'     => $asset['attribution'],
					'asset_record_id' => $asset_row_id,
				),
			)
		);
		if ( false === $generation_id ) {
			$this->assets->delete( $asset_row_id );
			wp_delete_attachment( absint( $stored['attachment_id'] ), true );
			return new WP_Error( 'ntci_stock_generation_store_failed', __( 'Không thể tạo phiên duyệt ảnh kho.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$this->assets->link_generation( $asset_row_id, $generation_id );
		update_post_meta( absint( $stored['attachment_id'] ), '_nt_content_images_generation_id', $generation_id );
		update_post_meta( absint( $stored['attachment_id'] ), '_ntci_asset_record_id', $asset_row_id );
		return array(
			'generation' => $this->generations->get( $generation_id ),
			'asset'      => $this->assets->get( $asset_row_id ),
		);
	}

	/** @return array{brief_id:int,brief:array<string,mixed>}|WP_Error */
	private function resolve_brief( int $post_id ) {
		$latest = $this->briefs->get_latest_by_post_id( $post_id );
		if ( null === $latest || 'outdated' === (string) ( $latest['status'] ?? '' ) ) {
			$generated = $this->brief_generator->generate( $post_id );
			if ( is_wp_error( $generated ) ) {
				return $generated;
			}
			return array( 'brief_id' => absint( $generated['id'] ?? 0 ), 'brief' => $generated );
		}
		return array( 'brief_id' => absint( $latest['id'] ?? 0 ), 'brief' => is_array( $latest['brief'] ?? null ) ? $latest['brief'] : array() );
	}
}
