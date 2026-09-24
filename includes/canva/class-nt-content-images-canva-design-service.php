<?php
/**
 * Connects generated WordPress images with editable Canva designs.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Canva_Design_Service {
	private NT_Content_Images_Canva_Client $client;
	private NT_Content_Images_Generation_Repository $repository;
	private NT_Content_Images_Media_Manager $media;
	private NT_Content_Images_Generation_Settings $generation_settings;

	public function __construct(
		NT_Content_Images_Canva_Client $client,
		NT_Content_Images_Generation_Repository $repository,
		NT_Content_Images_Media_Manager $media,
		NT_Content_Images_Generation_Settings $generation_settings
	) {
		$this->client              = $client;
		$this->repository          = $repository;
		$this->media               = $media;
		$this->generation_settings = $generation_settings;
	}

	/** @return array<string, mixed>|WP_Error */
	public function create_design( int $generation_id ) {
		$item = $this->repository->get( $generation_id );
		if ( null === $item || ! in_array( (string) $item['status'], array( 'generated', 'approved', 'rejected' ), true ) ) {
			return new WP_Error( 'ntci_canva_generation_invalid', __( 'Phiên tạo ảnh không hợp lệ để gửi sang Canva.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$existing = is_array( $item['response']['canva'] ?? null ) ? $item['response']['canva'] : array();
		if ( ! empty( $existing['design_id'] ) && ! empty( $existing['edit_url'] ) ) {
			return $item;
		}
		$attachment_id = absint( $item['attachment_id'] );
		if ( 0 === $attachment_id || ! get_post( $attachment_id ) ) {
			return new WP_Error( 'ntci_canva_attachment_missing', __( 'Không tìm thấy ảnh WordPress để gửi sang Canva.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$upload = $this->client->upload_attachment( $attachment_id );
		if ( is_wp_error( $upload ) ) {
			return $upload;
		}
		$asset = is_array( $upload['asset'] ?? null ) ? $upload['asset'] : array();
		$asset_id = sanitize_text_field( (string) ( $asset['id'] ?? '' ) );
		if ( '' === $asset_id ) {
			return new WP_Error( 'ntci_canva_asset_missing', __( 'Canva không trả về asset ID.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$design = $this->client->create_design( $asset_id, (string) $item['title'], 1280, 720 );
		if ( is_wp_error( $design ) ) {
			return $design;
		}
		$canva = array(
			'asset_id'  => $asset_id,
			'design_id' => sanitize_text_field( (string) ( $design['id'] ?? '' ) ),
			'edit_url'  => esc_url_raw( (string) ( $design['urls']['edit_url'] ?? '' ) ),
			'view_url'  => esc_url_raw( (string) ( $design['urls']['view_url'] ?? '' ) ),
			'created_at'=> time(),
		);
		$this->repository->merge_response( $generation_id, array( 'canva' => $canva ) );
		update_post_meta( $attachment_id, '_nt_content_images_canva_design_id', $canva['design_id'] );
		return $this->repository->get( $generation_id );
	}

	/**
	 * Imports an edited Canva design as a new reviewable generation record.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function import_design( int $generation_id ) {
		$item = $this->repository->get( $generation_id );
		if ( null === $item ) {
			return new WP_Error( 'ntci_canva_generation_missing', __( 'Không tìm thấy phiên tạo ảnh.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$canva = is_array( $item['response']['canva'] ?? null ) ? $item['response']['canva'] : array();
		$design_id = sanitize_text_field( (string) ( $canva['design_id'] ?? '' ) );
		if ( '' === $design_id ) {
			return new WP_Error( 'ntci_canva_design_missing', __( 'Phiên tạo ảnh chưa có thiết kế Canva.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		$export = $this->client->export_design_png( $design_id, 1280, 720 );
		if ( is_wp_error( $export ) ) {
			return $export;
		}
		$config = $this->generation_settings->get();
		$stored = $this->media->store_featured_candidate( absint( $item['post_id'] ), $export, (string) $item['prompt'], $config );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		$new_id = $this->repository->insert(
			array(
				'post_id'       => absint( $item['post_id'] ),
				'brief_id'      => absint( $item['brief_id'] ),
				'attachment_id' => absint( $stored['attachment_id'] ),
				'provider'      => 'canva',
				'model'         => 'canva-design-export',
				'status'        => 'generated',
				'prompt'        => (string) $item['prompt'],
				'settings'      => array(
					'target_width'  => 1280,
					'target_height' => 720,
					'output_format' => 'png',
				),
				'response'      => array(
					'canva' => array(
						'source_generation_id' => $generation_id,
						'design_id'             => $design_id,
						'edit_url'              => esc_url_raw( (string) ( $canva['edit_url'] ?? '' ) ),
						'exported_at'           => time(),
						'export_job'            => sanitize_text_field( (string) ( $export['export_job'] ?? '' ) ),
					),
				),
			)
		);
		if ( false === $new_id ) {
			wp_delete_attachment( absint( $stored['attachment_id'] ), true );
			return new WP_Error( 'ntci_canva_import_store_failed', __( 'Không thể lưu ảnh nhập từ Canva.', 'nt-tao-anh-noi-dung-wordpress' ) );
		}
		update_post_meta( absint( $stored['attachment_id'] ), '_nt_content_images_generation_id', $new_id );
		update_post_meta( absint( $stored['attachment_id'] ), '_nt_content_images_canva_design_id', $design_id );
		$this->repository->merge_response( $generation_id, array( 'canva' => array( 'latest_import_generation_id' => $new_id ) ) );
		return $this->repository->get( $new_id );
	}
}
