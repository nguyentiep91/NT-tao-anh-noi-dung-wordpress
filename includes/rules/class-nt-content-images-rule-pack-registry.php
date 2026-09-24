<?php
/**
 * Registers, resolves and executes portable content rule packs.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Rule_Pack_Registry {
	/** @var array<string, NT_Content_Images_Rule_Pack_Interface> */
	private array $packs = array();

	/**
	 * Registers built-in packs and allows third-party extensions.
	 */
	public function __construct() {
		$this->register( new NT_Content_Images_Generic_Rule_Pack() );
		$this->register( new NT_Content_Images_Education_Rule_Pack() );
		$this->register( new NT_Content_Images_Legal_Rule_Pack() );
		$this->register( new NT_Content_Images_Procurement_Rule_Pack() );

		/**
		 * Fires after built-in rule packs have been registered.
		 *
		 * @param NT_Content_Images_Rule_Pack_Registry $registry Registry instance.
		 */
		do_action( 'nt_content_images_register_rule_packs', $this );
	}

	/** Registers one rule pack. */
	public function register( NT_Content_Images_Rule_Pack_Interface $pack ): void {
		$this->packs[ sanitize_key( $pack->get_id() ) ] = $pack;
	}

	/**
	 * Returns available rule pack IDs.
	 *
	 * @return string[]
	 */
	public function get_ids(): array {
		return array_keys( $this->packs );
	}

	/**
	 * Returns metadata for admin settings.
	 *
	 * @return array<int, array{id: string, label: string, priority: int}>
	 */
	public function get_metadata(): array {
		$items = array();
		foreach ( $this->packs as $pack ) {
			$items[] = array(
				'id'       => $pack->get_id(),
				'label'    => $pack->get_label(),
				'priority' => $pack->get_priority(),
			);
		}
		usort( $items, static fn( array $a, array $b ): int => $b['priority'] <=> $a['priority'] );
		return $items;
	}

	/**
	 * Classifies content using only active packs.
	 *
	 * @param array<string, mixed> $source     Source package.
	 * @param string[]             $active_ids Active pack IDs.
	 * @return array{content_type: string, search_intent: string, confidence: float, signals: string[], rule_packs: string[]}
	 */
	public function classify( array $source, array $active_ids ): array {
		$text       = $this->build_search_text( $source );
		$candidates = array();
		$used_packs = array();

		foreach ( $this->get_active_packs( $active_ids ) as $pack ) {
			foreach ( $pack->get_classification_rules() as $rule ) {
				$content_type = sanitize_key( (string) ( $rule['content_type'] ?? 'general_content' ) );
				$intent       = sanitize_key( (string) ( $rule['search_intent'] ?? 'informational' ) );
				$keywords     = is_array( $rule['keywords'] ?? null ) ? $rule['keywords'] : array();
				$matched      = $this->match_keywords( $text, $keywords );
				$weight       = max( 1, absint( $rule['weight'] ?? 1 ) );
				$base_score   = empty( $keywords ) ? 1 : count( $matched ) * $weight;

				if ( 0 === $base_score ) {
					continue;
				}

				$score = $base_score * 100 + $pack->get_priority();
				$candidates[] = array(
					'content_type' => $content_type,
					'search_intent'=> $intent,
					'score'        => $score,
					'matched'      => $matched,
					'pack'         => $pack->get_id(),
				);
			}
		}

		usort( $candidates, static fn( array $a, array $b ): int => $b['score'] <=> $a['score'] );
		$winner = $candidates[0] ?? array(
			'content_type' => 'general_content',
			'search_intent'=> 'informational',
			'score'        => 100,
			'matched'      => array(),
			'pack'         => 'generic',
		);

		$signals = array( 'rule_pack:' . $winner['pack'], 'rule:' . $winner['content_type'] );
		foreach ( (array) $winner['matched'] as $keyword ) {
			$signals[] = 'keyword:' . sanitize_title( (string) $keyword );
		}
		foreach ( $candidates as $candidate ) {
			if ( $candidate['content_type'] === $winner['content_type'] ) {
				$used_packs[] = $candidate['pack'];
			}
		}

		$result = array(
			'content_type' => (string) $winner['content_type'],
			'search_intent'=> (string) $winner['search_intent'],
			'confidence'   => round( min( 0.98, max( 0.35, 0.35 + ( count( (array) $winner['matched'] ) * 0.14 ) ) ), 2 ),
			'signals'      => array_values( array_unique( $signals ) ),
			'rule_packs'   => array_values( array_unique( array_merge( array( (string) $winner['pack'] ), $used_packs ) ) ),
		);

		/** Filters the final deterministic classification. */
		return apply_filters( 'nt_content_images_content_classification', $result, $source, $active_ids );
	}

	/**
	 * Aggregates restrictions from active packs.
	 *
	 * @param array<string, mixed> $source Source package.
	 * @param string[]             $active_ids Active pack IDs.
	 * @return string[]
	 */
	public function get_restrictions( array $source, string $content_type, array $active_ids ): array {
		$restrictions = array();
		foreach ( $this->get_active_packs( $active_ids ) as $pack ) {
			$restrictions = array_merge( $restrictions, $pack->get_restrictions( $source, $content_type ) );
		}
		$restrictions = array_values( array_unique( array_filter( array_map( 'sanitize_key', $restrictions ) ) ) );
		return apply_filters( 'nt_content_images_restrictions', $restrictions, $source, $content_type, $active_ids );
	}

	/**
	 * Aggregates blocked heading terms from active packs.
	 *
	 * @param string[] $active_ids Active pack IDs.
	 * @return string[]
	 */
	public function get_blocked_heading_terms( array $active_ids ): array {
		$terms = array();
		foreach ( $this->get_active_packs( $active_ids ) as $pack ) {
			$terms = array_merge( $terms, $pack->get_blocked_heading_terms() );
		}
		return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $terms ) ) ) );
	}

	/**
	 * @param string[] $active_ids Active IDs.
	 * @return NT_Content_Images_Rule_Pack_Interface[]
	 */
	private function get_active_packs( array $active_ids ): array {
		$ids = array_values( array_unique( array_map( 'sanitize_key', $active_ids ) ) );
		if ( ! in_array( 'generic', $ids, true ) ) {
			array_unshift( $ids, 'generic' );
		}
		$packs = array();
		foreach ( $ids as $id ) {
			if ( isset( $this->packs[ $id ] ) ) {
				$packs[] = $this->packs[ $id ];
			}
		}
		usort( $packs, static fn( NT_Content_Images_Rule_Pack_Interface $a, NT_Content_Images_Rule_Pack_Interface $b ): int => $b->get_priority() <=> $a->get_priority() );
		return $packs;
	}

	/** @param array<string, mixed> $source Source package. */
	private function build_search_text( array $source ): string {
		$parts = array(
			(string) ( $source['title'] ?? '' ),
			(string) ( $source['focus_keyphrase'] ?? '' ),
			(string) ( $source['seo_title'] ?? '' ),
			(string) ( $source['seo_description'] ?? '' ),
			(string) ( $source['excerpt'] ?? '' ),
			(string) ( $source['intro'] ?? '' ),
		);
		foreach ( (array) ( $source['taxonomies'] ?? array() ) as $terms ) {
			foreach ( (array) $terms as $term ) {
				$parts[] = is_array( $term ) ? (string) ( $term['name'] ?? '' ) : (string) $term;
			}
		}
		$text = implode( ' ', $parts );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * @param array<int, mixed> $keywords Keywords.
	 * @return string[]
	 */
	private function match_keywords( string $text, array $keywords ): array {
		$matched = array();
		foreach ( $keywords as $keyword ) {
			$keyword = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $keyword, 'UTF-8' ) : strtolower( (string) $keyword );
			if ( '' !== $keyword && false !== strpos( $text, $keyword ) ) {
				$matched[] = $keyword;
			}
		}
		return array_values( array_unique( $matched ) );
	}
}
