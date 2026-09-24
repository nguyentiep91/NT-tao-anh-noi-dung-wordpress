# Test matrix — Sprint Audit 1

Sprint Audit 1 currently relies on syntax/coding-standard CI plus manual WordPress integration testing in Sprint Audit 3.

Required fixtures for the next test implementation:

1. Post without images.
2. Post with featured image only.
3. Gutenberg `core/image`.
4. Nested gallery images.
5. Cover background image.
6. Media & Text image.
7. Classic Editor `<img>`.
8. External image URL.
9. Duplicate attachment rendered more than once.
10. Data URI and 1 × 1 tracking pixel exclusion.
11. Registered and unregistered shortcode text.
12. Table, list, H2 and H3 metrics.
13. Custom/non-core block complexity flag.
14. Yoast focus keyphrase.
15. Vietnamese content longer than 3,000 words.

No fixture may execute shortcodes, render blocks, mutate post content or create attachments during a read-only scan.
