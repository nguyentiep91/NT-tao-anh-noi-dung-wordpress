<?php
/**
 * Resolves provider-neutral visual strategy presets.
 *
 * @package NT_Content_Images
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class NT_Content_Images_Visual_Strategy_Resolver {
	/** @return array<string, mixed> */
	public function resolve( string $content_type, array $source ): array {
		$presets = array(
			'legal_update' => array( 'id' => 'legal_editorial', 'art_direction' => 'Professional legal editorial, abstract policy documents, architecture and institutional trust, no authentic official document replica.', 'featured_type' => 'editorial_cover', 'content_types' => array( 'timeline', 'conceptual_document', 'policy_process' ), 'template_family' => 'legal' ),
			'legal_explainer' => array( 'id' => 'legal_explainer', 'art_direction' => 'Clear legal explanation with document symbolism, professional context and restrained institutional styling.', 'featured_type' => 'editorial_cover', 'content_types' => array( 'concept_map', 'requirement_checklist', 'process' ), 'template_family' => 'legal' ),
			'legal_procedure' => array( 'id' => 'legal_process', 'art_direction' => 'Structured legal procedure represented as a professional workflow with abstract forms and safe non-official document elements.', 'featured_type' => 'workflow_cover', 'content_types' => array( 'process', 'document_set', 'requirement_checklist' ), 'template_family' => 'legal' ),
			'how_to' => array( 'id' => 'professional_workflow', 'art_direction' => 'Step-by-step professional workflow, clean hierarchy, modern workplace and technology context.', 'featured_type' => 'workflow_cover', 'content_types' => array( 'workflow', 'step_sequence', 'checklist' ), 'template_family' => 'how_to' ),
			'checklist' => array( 'id' => 'professional_checklist', 'art_direction' => 'Organized checklist and verification concept with clean business visuals and practical detail.', 'featured_type' => 'checklist_cover', 'content_types' => array( 'checklist', 'risk_warning', 'document_set' ), 'template_family' => 'how_to' ),
			'comparison' => array( 'id' => 'balanced_comparison', 'art_direction' => 'Balanced split composition comparing two concepts with neutral hierarchy and no false official marks.', 'featured_type' => 'split_comparison', 'content_types' => array( 'comparison_matrix', 'before_after', 'two_column_concept' ), 'template_family' => 'comparison' ),
			'course' => array( 'id' => 'professional_training', 'art_direction' => 'Modern professional training environment, instructor and adult learners, practical education and trust.', 'featured_type' => 'training_cover', 'content_types' => array( 'classroom', 'learning_process', 'skills_outcome' ), 'template_family' => 'education' ),
			'education_guide' => array( 'id' => 'education_explainer', 'art_direction' => 'Clear learning process, study materials and practical skill development in a credible educational environment.', 'featured_type' => 'training_cover', 'content_types' => array( 'learning_process', 'study_materials', 'skills_outcome' ), 'template_family' => 'education' ),
			'education_event' => array( 'id' => 'education_event', 'art_direction' => 'Professional class or seminar atmosphere with adult learners and a clean editorial event composition.', 'featured_type' => 'event_cover', 'content_types' => array( 'classroom', 'speaker_audience', 'agenda_concept' ), 'template_family' => 'education' ),
			'service' => array( 'id' => 'professional_consulting', 'art_direction' => 'Expert consultation, client collaboration, credible business environment and service confidence.', 'featured_type' => 'service_cover', 'content_types' => array( 'consultation', 'service_process', 'client_outcome' ), 'template_family' => 'service' ),
			'procurement_guide' => array( 'id' => 'procurement_workflow', 'art_direction' => 'Professional procurement workflow with teams reviewing neutral digital documents, no readable portal interface.', 'featured_type' => 'workflow_cover', 'content_types' => array( 'workflow', 'document_review', 'checklist' ), 'template_family' => 'procurement' ),
			'procurement_checklist' => array( 'id' => 'procurement_checklist', 'art_direction' => 'Procurement document verification and risk-control checklist in a modern office, no tender codes or portal replica.', 'featured_type' => 'checklist_cover', 'content_types' => array( 'checklist', 'risk_warning', 'document_set' ), 'template_family' => 'procurement' ),
			'procurement_service' => array( 'id' => 'procurement_consulting', 'art_direction' => 'Expert procurement consultation and client collaboration with abstract bidding documents and secure digital workflow.', 'featured_type' => 'service_cover', 'content_types' => array( 'consultation', 'service_process', 'document_review' ), 'template_family' => 'procurement' ),
			'event' => array( 'id' => 'professional_event', 'art_direction' => 'Professional seminar or workshop atmosphere, audience engagement and editorial realism.', 'featured_type' => 'event_cover', 'content_types' => array( 'event_scene', 'speaker_audience', 'agenda_concept' ), 'template_family' => 'event' ),
			'news' => array( 'id' => 'editorial_news', 'art_direction' => 'Timely editorial visual with realistic professional context, clean focal point and restrained branding.', 'featured_type' => 'news_cover', 'content_types' => array( 'editorial_scene', 'key_development', 'contextual_visual' ), 'template_family' => 'news' ),
			'definition' => array( 'id' => 'concept_explainer', 'art_direction' => 'Simple conceptual explanation with one clear central metaphor and uncluttered editorial composition.', 'featured_type' => 'concept_cover', 'content_types' => array( 'concept_diagram', 'definition_visual', 'key_elements' ), 'template_family' => 'education' ),
			'case_study' => array( 'id' => 'case_study_editorial', 'art_direction' => 'Realistic professional case study scene with problem, action and result storytelling.', 'featured_type' => 'case_study_cover', 'content_types' => array( 'problem_solution', 'process', 'outcome' ), 'template_family' => 'case_study' ),
			'general_content' => array( 'id' => 'professional_editorial', 'art_direction' => 'Professional editorial visual with clear subject focus, modern credible presentation and adaptable composition.', 'featured_type' => 'editorial_cover', 'content_types' => array( 'conceptual_visual', 'process', 'summary' ), 'template_family' => 'general' ),
		);

		$strategy = $presets[ sanitize_key( $content_type ) ] ?? $presets['general_content'];
		$strategy['aspect_ratio'] = '16:9';
		$strategy['text_overlay']  = true;
		$strategy['brand_mode']    = 'plugin_template_overlay';
		$strategy['locale']        = sanitize_text_field( (string) ( $source['language'] ?? get_locale() ) );
		$strategy['topic_hint']    = sanitize_text_field( (string) ( $source['focus_keyphrase'] ?? $source['title'] ?? '' ) );

		return apply_filters( 'nt_content_images_visual_strategy', $strategy, $content_type, $source );
	}
}
