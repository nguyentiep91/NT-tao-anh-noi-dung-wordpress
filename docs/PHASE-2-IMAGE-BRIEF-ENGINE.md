# Phase 2 — Image Brief Engine

## Mục tiêu

Image Brief Engine chuyển kết quả audit thành kế hoạch hình ảnh có cấu trúc, có thể kiểm tra và duyệt trước khi plugin gọi bất kỳ nhà cung cấp AI nào.

Module này không:

- Gọi OpenAI, Canva hoặc API ngoài.
- Tạo file ảnh.
- Tạo attachment.
- Sửa `post_content`.
- Đặt featured image.
- Thực thi shortcode hoặc render block.

## Luồng xử lý

```text
Audit result
    ↓
Brief Source Builder
    ↓
Intent Classifier
    ↓
Visual Strategy Resolver
    ↓
Placement Planner
    ↓
Restriction Builder
    ↓
Brief Validator
    ↓
Draft brief → pending_review → approved/rejected
```

## Bảng dữ liệu

Bảng:

```text
{$wpdb->prefix}nt_content_image_briefs
```

Một bài có thể có nhiều phiên bản brief. Khi `source_content_hash` thay đổi, brief cũ được đánh dấu `outdated` và brief mới tăng `brief_version`.

## Phân loại nội dung

Các loại ban đầu:

- `legal_update`
- `legal_explainer`
- `how_to`
- `checklist`
- `comparison`
- `definition`
- `course`
- `service`
- `news`
- `event`
- `case_study`
- `general_education`

Search intent:

- `informational`
- `procedural`
- `comparative`
- `transactional`
- `navigational`
- `event_discovery`

Phân loại hiện là rule-based, giải thích được và không phát sinh chi phí API.

## Image Brief JSON schema 1.0

Ví dụ rút gọn:

```json
{
  "schema_version": "1.0",
  "post_id": 123,
  "source_content_hash": "sha256...",
  "topic": "Hướng dẫn lập E-HSMT",
  "content_type": "how_to",
  "search_intent": "procedural",
  "priority": {
    "score": 95,
    "label": "very_high"
  },
  "featured_image": {
    "required": true,
    "visual_type": "workflow_cover",
    "aspect_ratio": "16:9",
    "text_overlay": true,
    "overlay_source": "plugin_template"
  },
  "recommended_content_images": 2,
  "content_images": [
    {
      "index": 1,
      "purpose": "Tạo điểm ngắt thị giác sau phần mở đầu",
      "visual_type": "workflow",
      "placement": {
        "type": "after_intro",
        "paragraph_index": 3
      }
    }
  ],
  "restrictions": [
    "no_fake_government_interface",
    "no_national_emblem",
    "main_text_must_be_rendered_by_plugin_template"
  ]
}
```

## Placement Planner

Anchor được lưu theo cấu trúc ổn định:

- `after_intro`
- `before_heading`
- `manual_review_required`

Không lưu vị trí bằng offset ký tự. Điều này giảm rủi ro khi bài viết được chỉnh sửa.

Các heading liên quan CTA, FAQ, hotline, học phí và kết luận bị loại khỏi danh sách anchor tự động.

## Hạn chế an toàn

Hạn chế mặc định:

- Không tạo chữ ngẫu nhiên hoặc không đọc được.
- Không tạo logo, con dấu, chữ ký hoặc quốc huy giả.
- Không tạo tài liệu hành chính giả.
- Không giả giao diện website nhà nước.
- Không tự tạo nội dung pháp lý.
- Chữ chính thức phải do template của plugin render.

Hạn chế chuyên biệt được bổ sung cho:

- Đấu thầu/VNEPS.
- Chứng chỉ và chứng nhận.
- FDA, ISO, CE, UKCA, Halal, GMP.
- Bài pháp lý.
- Bài sự kiện.

## REST API nội bộ

Namespace:

```text
nt-content-images/v1
```

Routes:

```text
POST /briefs/generate
GET  /briefs
GET  /briefs/candidates
GET  /briefs/summary
GET  /briefs/{id}
POST /briefs/{id}/status
```

Tất cả route yêu cầu tài khoản có `manage_options` và REST nonce hợp lệ.

## Giới hạn batch

Mỗi request chỉ tạo tối đa 20 brief. Đây là giới hạn cứng để tránh request dài trên hosting có tài nguyên thấp.

## Giao diện quản trị

Menu:

```text
NT – Tạo ảnh nội dung
├── Kiểm tra bài viết
└── Kế hoạch hình ảnh
```

Chức năng:

- Xem 20 bài ưu tiên rất cao.
- Chọn bài và tạo brief hàng loạt.
- Lọc brief theo trạng thái và loại nội dung.
- Xem chi tiết chiến lược, placement, restrictions và validation.
- Gửi duyệt, duyệt hoặc từ chối.

## Pilot bắt buộc

Trước khi xây Prompt Builder, cần tạo brief cho 10 bài đại diện:

- 2 bài pháp lý.
- 2 bài hướng dẫn.
- 2 bài khóa học.
- 2 bài dịch vụ.
- 1 bài so sánh.
- 1 bài tin tức hoặc sự kiện.

Mỗi brief cần được đối chiếu thủ công về classification, số ảnh, placement và restrictions.
