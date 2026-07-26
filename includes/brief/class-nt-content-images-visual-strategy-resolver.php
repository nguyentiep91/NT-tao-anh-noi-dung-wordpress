<?php
/**
 * Resolves deterministic visual strategy presets.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Visual_Strategy_Resolver {
	/**
	 * Returns visual direction for a classified content type.
	 *
	 * @return array<string, mixed>
	 */
	public function resolve( string $content_type, array $source ): array {
		$presets = array(
			'legal_update' => array(
				'id'               => 'legal_editorial',
				'art_direction'    => 'Professional legal editorial, policy documents, architecture and institutional trust, navy blue, gold and white.',
				'featured_type'    => 'editorial_cover',
				'content_types'    => array( 'timeline', 'conceptual_document', 'policy_process' ),
				'template_family'  => 'legal',
			),
			'legal_explainer' => array(
				'id'               => 'legal_explainer',
				'art_direction'    => 'Clear legal explanation with document symbolism, professional office context and restrained institutional styling.',
				'featured_type'    => 'editorial_cover',
				'content_types'    => array( 'concept_map', 'requirement_checklist', 'process' ),
				'template_family'  => 'legal',
			),
			'how_to' => array(
				'id'               => 'professional_workflow',
				'art_direction'    => 'Step-by-step professional workflow, clean hierarchy, modern office and technology context.',
				'featured_type'    => 'workflow_cover',
				'content_types'    => array( 'workflow', 'step_sequence', 'checklist' ),
				'template_family'  => 'how_to',
			),
			'checklist' => array(
				'id'               => 'professional_checklist',
				'art_direction'    => 'Organized checklist and verification concept with clean business visuals and practical detail.',
				'featured_type'    => 'checklist_cover',
				'content_types'    => array( 'checklist', 'risk_warning', 'document_set' ),
				'template_family'  => 'how_to',
			),
			'comparison' => array(
				'id'               => 'balanced_comparison',
				'art_direction'    => 'Balanced split composition comparing two concepts with neutral visual hierarchy and no false official marks.',
				'featured_type'    => 'split_comparison',
				'content_types'    => array( 'comparison_matrix', 'before_after', 'two_column_concept' ),
				'template_family'  => 'comparison',
			),
			'course' => array(
				'id'               => 'professional_training',
				'art_direction'    => 'Modern professional training environment, instructor and adult learners, practical education and trust.',
				'featured_type'    => 'training_cover',
				'content_types'    => array( 'classroom', 'learning_process', 'skills_outcome' ),
				'template_family'  => 'education',
			),
			'service' => array(
				'id'               => 'professional_consulting',
				'art_direction'    => 'Expert consultation, client collaboration, credible business environment and service confidence.',
				'featured_type'    => 'service_cover',
				'content_types'    => array( 'consultation', 'service_process', 'client_outcome' ),
				'template_family'  => 'service',
			),
			'event' => array(
				'id'               => 'professional_event',
				'art_direction'    => 'Professional seminar or workshop atmosphere, audience engagement, modern venue and editorial realism.',
				'featured_type'    => 'event_cover',
				'content_types'    => array( 'event_scene', 'speaker_audience', 'agenda_concept' ),
				'template_family'  => 'event',
			),
			'news' => array(
				'id'               => 'editorial_news',
				'art_direction'    => 'Timely editorial visual with realistic professional context, clean focal point and restrained branding.',
				'featured_type'    => 'news_cover',
				'content_types'    => array( 'editorial_scene', 'key_development', 'contextual_visual' ),
				'template_family'  => 'news',
			),
			'definition' => array(
				'id'               => 'concept_explainer',
				'art_direction'    => 'Simple conceptual explanation with one clear central metaphor and uncluttered editorial composition.',
				'featured_type'    => 'concept_cover',
				'content_types'    => array( 'concept_diagram', 'definition_visual', 'key_elements' ),
				'template_family'  => 'education',
			),
			'case_study' => array(
				'id'               => 'case_study_editorial',
				'art_direction'    => 'Realistic professional case study scene with problem, action and result storytelling.',
				'featured_type'    => 'case_study_cover',
				'content_types'    => array( 'problem_solution', 'process', 'outcome' ),
				'template_family'  => 'case_study',
			),
			'general_education' => array(
				'id'               => 'professional_editorial',
				'art_direction'    => 'Professional educational editorial visual, clear subject focus, modern and credible presentation.',
				'featured_type'    => 'editorial_cover',
				'content_types'    => array( 'conceptual_visual', 'process', 'summary' ),
				'template_family'  => 'general',
			),
		);

		$strategy = $presets[ $content_type ] ?? $presets['general_education'];
		$strategy['aspect_ratio'] = '16:9';
		$strategy['text_overlay']  = true;
		$strategy['brand_mode']    = 'plugin_template_overlay';
		$strategy['locale']        = 'vi-VN';
		$strategy['topic_hint']    = sanitize_text_field( (string) ( $source['focus_keyphrase'] ?? $source['title'] ?? '' ) );

		return $strategy;
	}
}
