# Image Brief Engine — Test matrix

## Functional fixtures

1. Legal update containing a decree number.
2. Legal explainer without a new regulation signal.
3. Procedural how-to article with at least four H2 headings.
4. Checklist article.
5. Comparison article.
6. Course landing page.
7. Service landing page.
8. Event or seminar post.
9. General education article.
10. Post with shortcode and custom blocks.

## Assertions per brief

- `post_id` exists and matches the audited post.
- `source_content_hash` matches the audit record.
- `content_type` and `search_intent` are plausible.
- `featured_image.required` matches featured image status.
- `recommended_content_images` is between 0 and 5.
- `content_images` count matches the recommendation.
- Every placement is `after_intro`, `before_heading`, or `manual_review_required`.
- Legal/procurement/certification restrictions appear when relevant.
- Shortcode and complex block warnings appear when relevant.
- No post content, attachment, taxonomy or publish date is modified.

## Workflow assertions

- Generating the same content hash updates the current brief version without duplicate rows.
- Changing source content marks prior brief versions `outdated`.
- Invalid briefs cannot be approved.
- Draft brief can move to `pending_review`, `approved`, or `rejected`.
- Batch generation refuses more than 20 post IDs.
- REST routes reject users without `manage_options`.

## Pilot acceptance

The pilot passes when 10 representative briefs are manually reviewed and at least 9/10 have correct classification, image count, placement strategy and safety restrictions without post mutation.
