# Kiến trúc plugin

## 1. Phạm vi

**NT – Tạo ảnh cho nội dung WordPress** là plugin độc lập với theme, dùng để:

1. Audit bài viết ở chế độ chỉ đọc.
2. Tạo `image brief` từ dữ liệu bài viết.
3. Gọi API nhà cung cấp AI tạo ảnh.
4. Hậu xử lý ảnh và chèn nhận diện thương hiệu.
5. Lưu ảnh vào Media Library.
6. Quản lý workflow nháp, chờ duyệt, đã duyệt và đã chèn.
7. Ghi nhật ký, lỗi và chi phí.

## 2. Luồng xử lý chính

```text
WordPress Post
    ↓
Content Scanner
    ↓
Content Analyzer
    ↓
Image Brief Builder
    ↓
Prompt Builder
    ↓
AI Provider Adapter
    ↓
Generated Background Image
    ↓
Image Processor
    ↓
Template Renderer hoặc Canva Adapter
    ↓
Media Library
    ↓
Approval Workflow
    ↓
Featured Image hoặc In-content Image
```

## 3. Các module dự kiến

### A. Content Audit

- Quét post type được cho phép.
- Xác định ảnh đại diện và ảnh trong nội dung.
- Đọc title, excerpt, categories, tags, H2/H3, focus keyphrase.
- Không sửa dữ liệu bài viết.

### B. Content Analysis

- Phân loại chủ đề và search intent.
- Xác định loại ảnh phù hợp.
- Tạo `image brief` JSON có cấu trúc.
- Thiết lập vùng cấm: logo giả, quốc huy giả, chữ pháp lý giả, giao diện thao tác giả.

### C. Prompt Builder

- Kết hợp brief, template và quy tắc thương hiệu.
- Tạo prompt ảnh nền không chữ.
- Lưu phiên bản prompt để truy vết.

### D. AI Provider Layer

Áp dụng adapter interface để không khóa plugin vào một nhà cung cấp:

```php
interface Image_Provider_Interface {
    public function generate( array $request ): array;
    public function validate_credentials(): bool;
    public function estimate_cost( array $request ): float;
}
```

Nhà cung cấp đầu tiên dự kiến là OpenAI Image API. Gemini, Adobe Firefly và Stability AI chỉ được thêm ở giai đoạn sau.

### E. Image Processing

- Kiểm tra MIME type và kích thước.
- Resize/crop theo preset.
- Chuyển WebP nếu máy chủ hỗ trợ.
- Nén theo mức chất lượng cấu hình.
- Tạo tên tệp, alt text, caption và metadata.
- Kiểm tra ảnh trùng ở giai đoạn nâng cao.

### F. Template Renderer

- Chèn tiêu đề, nhãn chuyên mục, logo thật và website.
- Ưu tiên SVG/Imagick hoặc giải pháp render đáng tin cậy.
- Không để AI tự viết phần chữ chính thức.
- Hỗ trợ nhiều template theo nhóm nội dung.

### G. Canva Adapter — tùy chọn

- OAuth 2.0.
- Upload ảnh nền.
- Tạo thiết kế từ Brand Template nếu tài khoản hỗ trợ.
- Export thiết kế.
- Không phải dependency bắt buộc của plugin.

### H. Media & Post Integration

- Lưu attachment bằng WordPress Media API.
- Đặt featured image khi được duyệt.
- Chèn block ảnh vào vị trí an toàn trong `post_content`.
- Lưu snapshot nội dung cũ trước khi chèn.
- Có hoàn tác từng bài và từng batch.

### I. Workflow & Queue

Trạng thái cốt lõi:

```text
draft → pending_review → approved → inserted
                 ↘ rejected
```

Hàng đợi phải chạy theo batch nhỏ. Không thực hiện hàng trăm yêu cầu API trong một request quản trị.

### J. Audit Log & Cost Tracking

- Provider/model.
- Request ID.
- Người thao tác.
- Thời gian.
- Hành động.
- Trạng thái.
- Chi phí ước tính và thực tế khi provider trả về.
- Thông tin lỗi đã được làm sạch, không lưu secret.

## 4. Lớp dữ liệu dự kiến

### Bảng `{$wpdb->prefix}nt_content_images`

Lưu bản ghi ảnh và workflow:

- `id`
- `post_id`
- `attachment_id`
- `image_type`
- `status`
- `provider`
- `model`
- `prompt_hash`
- `template_id`
- `insert_position`
- `created_by`
- `created_at`
- `updated_at`

### Bảng `{$wpdb->prefix}nt_content_image_logs`

Lưu nhật ký hoạt động và lỗi.

### Bảng `{$wpdb->prefix}nt_content_image_costs`

Lưu số liệu chi phí theo provider/model/request.

Các bảng chỉ được tạo khi module tương ứng được triển khai và migration đã có phiên bản.

## 5. Bảo mật

- Capability riêng cho từng nhóm thao tác.
- Nonce cho mọi hành động quản trị.
- Sanitize đầu vào, escape đầu ra.
- API key ưu tiên đọc từ constant hoặc environment; nếu lưu DB phải mã hóa/che dữ liệu khi hiển thị.
- Không ghi secret vào log.
- Không cho frontend gọi trực tiếp provider API.
- Giới hạn tốc độ và số lượng batch.
- Kiểm tra MIME, kích thước và nguồn ảnh trả về.

## 6. Nguyên tắc an toàn nội dung

- Không sửa tiêu đề, nội dung pháp lý, shortcode, internal link, schema hoặc ngày đăng.
- Không ghi đè ảnh đã có nếu chưa được phép.
- Không tự publish lại bài.
- Không tự tạo logo, quốc huy, con dấu hoặc tài liệu pháp lý giả.
- Không chèn ảnh vào trong bảng, shortcode, list đang mở hoặc block không an toàn.
- Giai đoạn đầu luôn yêu cầu duyệt thủ công.

## 7. Quy ước thư mục mục tiêu

```text
nt-tao-anh-noi-dung-wordpress/
├── nt-tao-anh-noi-dung-wordpress.php
├── uninstall.php
├── includes/
├── admin/
├── public/
├── assets/
├── templates/
├── languages/
├── docs/
├── tests/
└── vendor/
```

## 8. Chiến lược phát triển

- `main`: mốc ổn định.
- `develop`: tích hợp các feature đã kiểm tra.
- `feature/audit`, `feature/queue`, `feature/openai-provider`, `feature/template-renderer`.
- Mỗi pull request chỉ nên tập trung vào một module.
- Chỉ merge khi kích hoạt/deactivate plugin không phát sinh fatal error.
