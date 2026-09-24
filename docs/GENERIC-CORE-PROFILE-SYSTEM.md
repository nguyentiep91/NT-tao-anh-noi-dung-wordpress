# Generic Core & Profile System

## Mục tiêu

Sprint này tách toàn bộ cấu hình phụ thuộc website khỏi lõi audit và Image Brief Engine. Plugin có thể được cài trên nhiều website WordPress mà không phải sửa mã nguồn theo tên miền, thương hiệu, shortcode, post type hoặc plugin SEO cụ thể.

Phiên bản triển khai: `0.5.0`  
Database schema: `1.2.0`  
Image Brief schema: `1.1`

## Kiến trúc

```text
Generic Core
├── Post Type Registry
├── Content Type Mapper
├── Site Profile
├── Brand Profile
├── Rule Pack Registry
├── SEO Adapter Manager
├── Audit Engine
└── Image Brief Engine
```

### Site Profile

Site Profile lưu:

- Tên website và tên miền.
- Locale/ngôn ngữ.
- Lĩnh vực hoạt động.
- Rule pack đang bật.
- Post type được phép audit/tạo brief.
- Ánh xạ post type sang nhóm nội dung dùng chung.
- Shortcode cần bảo vệ.
- Heading không được chọn làm điểm chèn ảnh.
- Chính sách nội dung dùng page builder.

Option:

```text
nt_content_images_site_profile
```

### Brand Profile

Brand Profile lưu:

- Tên thương hiệu và website.
- Attachment ID của logo thật.
- Màu chính, màu phụ, màu nhấn.
- Font family.
- Template family.
- Quy tắc render logo, website và lớp chữ.

Option:

```text
nt_content_images_brand_profile
```

API key không thuộc Site/Brand Profile và không được xuất trong JSON cấu hình.

### Profile version và hash

Mỗi thay đổi cấu hình làm tăng:

```text
nt_content_images_profile_version
```

Brief mới ghi:

- `profile_version`
- `profile_hash`
- `brand_profile_hash`
- `active_rule_packs`
- `post_type_mapping`

Khi profile thay đổi, brief hiện hành được đánh dấu `outdated`. Brief được tạo bằng profile cũ không thể chuyển sang `approved`.

## Post Type Registry

Registry đọc các post type có giao diện quản trị và public/publicly queryable, bỏ qua `attachment`.

Hook mở rộng:

```php
nt_content_images_supported_post_types
nt_content_images_post_type_mapping
```

Ví dụ ánh xạ:

```json
{
  "post": "article",
  "page": "page",
  "product": "commerce_product",
  "lp_course": "education_course"
}
```

Audit Query và Brief Source Builder chỉ xử lý post type được bật trong Site Profile.

## Rule Pack

Rule pack triển khai interface:

```php
NT_Content_Images_Rule_Pack_Interface
```

Các pack tích hợp đầu tiên:

- `generic`: quy tắc mặc định cho mọi website.
- `education`: khóa học, đào tạo, tài liệu học và sự kiện giáo dục.
- `legal`: nội dung pháp lý, cập nhật quy định và thủ tục.
- `procurement`: đấu thầu, hồ sơ mời thầu/dự thầu và quy trình mua sắm.

Generic pack luôn được bật. Các pack khác là tùy chọn theo website.

Hook mở rộng:

```php
nt_content_images_register_rule_packs
nt_content_images_content_classification
nt_content_images_restrictions
nt_content_images_visual_strategy
nt_content_images_target_content_image_count
nt_content_images_image_brief
```

## SEO Adapter

SEO Adapter Manager đọc dữ liệu theo thứ tự ưu tiên:

1. Yoast SEO.
2. Rank Math.
3. WordPress fallback.

Dữ liệu gồm:

- Focus keyphrase.
- SEO title.
- Meta description.
- Adapter đã sử dụng.

Hook:

```php
nt_content_images_seo_adapters
nt_content_images_seo_metadata
```

Plugin vẫn hoạt động khi không cài plugin SEO.

## Shortcode được bảo vệ

Quản trị viên nhập shortcode tag, không nhập toàn bộ shortcode. Ví dụ:

```text
vdt_course_cta
contact-form-7
products
```

Khi một shortcode được bảo vệ xuất hiện trong nội dung:

- Brief vẫn được tạo.
- Placement tự động chuyển thành `manual_review_required`.
- Validator tạo cảnh báo.
- Plugin không thực thi shortcode.

## Page builder policy

Các chế độ:

- `safe_only`: chỉ dùng anchor tự động khi nội dung không có block phức tạp.
- `manual_for_builders`: nội dung builder/block phức tạp yêu cầu kiểm tra thủ công.
- `media_only`: về sau chỉ tạo/lưu ảnh, không chèn tự động.

Sprint này chưa tạo adapter riêng cho Elementor, Divi, WPBakery, Bricks hoặc ACF Flexible Content.

## Import và Export

Trang **Cấu hình website** cho phép:

- Export profile JSON.
- Import profile JSON.
- Giới hạn payload 200 KB.
- Sanitize toàn bộ dữ liệu.
- Không chứa API key hoặc bí mật.

Cài sang website mới:

```text
Cài plugin
→ mở Cấu hình website
→ nhập hoặc tạo Site Profile
→ chọn Brand Profile
→ bật post type/rule pack
→ audit thử
→ tạo brief thử
```

## Migration

Bảng brief được mở rộng với:

```text
profile_version
profile_hash
brand_profile_hash
rule_packs_json
post_type_mapping
```

Dữ liệu audit và brief cũ không bị xóa. Brief schema cũ được giữ để truy vết nhưng có thể được validator cảnh báo `legacy_brief_schema`.

## An toàn và phạm vi

Sprint này không:

- Gọi OpenAI, Canva hoặc API tạo ảnh.
- Tạo file ảnh hoặc Media attachment.
- Sửa bài viết, taxonomy, schema hoặc ngày đăng.
- Thực thi shortcode hoặc render block.
- Lưu API key trong profile/export.

## QA bắt buộc trên LocalWP

1. Nâng cấp từ `0.4.0` lên `0.5.0` không mất audit/brief.
2. Mở và lưu Site/Brand Profile.
3. Bật/tắt post type và xác nhận màn hình audit cập nhật.
4. Bật từng Rule Pack và tạo brief mẫu.
5. Kiểm tra Yoast, Rank Math và WordPress fallback.
6. Cấu hình shortcode bảo vệ và xác nhận placement thủ công.
7. Export/import JSON trên một website LocalWP khác.
8. Đổi profile và xác nhận brief cũ thành `outdated`.
9. Xác nhận không thay đổi `wp_posts` hoặc Media Library.
