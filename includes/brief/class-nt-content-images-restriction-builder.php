<?php
/**
 * Builds safety restrictions for image briefs.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Restriction_Builder {
	/**
	 * Returns machine-readable restrictions based on source content.
	 *
	 * @param array<string, mixed> $source Source package.
	 * @return string[]
	 */
	public function build( array $source, string $content_type ): array {
		$text = $this->normalize(
			implode(
				' ',
				array(
					(string) ( $source['title'] ?? '' ),
					(string) ( $source['focus_keyphrase'] ?? '' ),
					(string) ( $source['excerpt'] ?? '' ),
				)
			)
		);
		$restrictions = array(
			'no_random_or_unreadable_text',
			'no_fake_logos',
			'no_fake_seals_or_signatures',
			'no_national_emblem',
			'no_fake_official_documents',
			'no_fake_government_interface',
			'no_ai_generated_legal_claims',
			'no_invented_phone_numbers_or_addresses',
			'no_specific_real_person_likeness_without_source',
			'main_text_must_be_rendered_by_plugin_template',
		);

		if ( $this->contains_any( $text, array( 'đấu thầu', 'vneps', 'muasamcong', 'e-hsmt', 'e-hsdt' ) ) ) {
			$restrictions[] = 'no_fake_vneps_or_procurement_portal_ui';
			$restrictions[] = 'no_invented_tender_codes_or_notices';
		}

		if ( $this->contains_any( $text, array( 'chứng chỉ', 'chứng nhận', 'văn bằng', 'certificate' ) ) ) {
			$restrictions[] = 'no_realistic_fake_certificate_blank';
			$restrictions[] = 'no_fake_approval_stamp';
		}

		if ( $this->contains_any( $text, array( 'fda', 'iso', 'ce marking', 'ukca', 'halal', 'gmp' ) ) ) {
			$restrictions[] = 'no_fake_certification_or_regulator_logo';
			$restrictions[] = 'no_false_approval_badge';
		}

		if ( in_array( $content_type, array( 'legal_update', 'legal_explainer' ), true ) ) {
			$restrictions[] = 'no_invented_article_numbers_or_legal_quotes';
			$restrictions[] = 'no_document_facsimile_presented_as_authentic';
		}

		if ( 'event' === $content_type ) {
			$restrictions[] = 'no_invented_event_date_or_venue_in_generated_background';
		}

		return array_values( array_unique( $restrictions ) );
	}

	/**
	 * Normalizes UTF-8 text for keyword checks.
	 */
	private function normalize( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Checks whether a string contains any needle.
	 *
	 * @param string[] $needles Keywords.
	 */
	private function contains_any( string $text, array $needles ): bool {
		foreach ( $needles as $needle ) {
			if ( false !== strpos( $text, $needle ) ) {
				return true;
			}
		}

		return false;
	}
}
