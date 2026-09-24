# Affordable Image Sources — phiên bản 0.8.0

## Mục tiêu

Phiên bản 0.8.0 mở rộng plugin theo chiến lược tiết kiệm chi phí:

```text
Image Brief
├── Kho ảnh miễn phí: Pexels, Openverse
└── AI: Cloudflare Workers AI, fal.ai, OpenRouter, OpenAI
```

Mặc định là `stock_first`: quản trị viên tìm ảnh miễn phí trước, sau đó chủ động chuyển sang AI khi không có kết quả phù hợp. Plugin không tự chọn ảnh đầu tiên và không tự chuyển sang provider trả phí.

## Provider

### Pexels

- Yêu cầu API key.
- Chỉ tìm ảnh landscape.
- Lưu photographer, trang nguồn, Pexels License và attribution.
- Ảnh chỉ được nhập khi quản trị viên bấm chọn.

```php
define( 'NT_CONTENT_IMAGES_PEXELS_API_KEY', '...' );
```

### Openverse

- Có thể tìm kiếm không cần token; token tùy chọn hỗ trợ hạn mức cao hơn khi Openverse cấp.
- Chỉ chấp nhận mặc định: Public Domain Mark, CC0, CC BY, CC BY-SA.
- Chặn NC, ND, mature content và SVG.
- CC BY/CC BY-SA yêu cầu quản trị viên xác nhận đã kiểm tra giấy phép trước khi nhập.
- Luôn lưu liên kết nguồn, creator, license URL và attribution.

```php
define( 'NT_CONTENT_IMAGES_OPENVERSE_API_TOKEN', '...' ); // Không bắt buộc.
```

Openverse là công cụ tổng hợp metadata; quản trị viên vẫn phải kiểm tra trang nguồn và giấy phép trước khi dùng ảnh.

### Cloudflare Workers AI

Model MVP:

```text
@cf/black-forest-labs/flux-1-schnell
```

Credential:

```php
define( 'NT_CONTENT_IMAGES_CLOUDFLARE_ACCOUNT_ID', '...' );
define( 'NT_CONTENT_IMAGES_CLOUDFLARE_API_TOKEN', '...' );
```

Plugin:

- Cắt prompt còn tối đa 2.048 ký tự.
- Dùng 4 inference steps và đúng một ảnh.
- Giới hạn số ảnh/ngày do quản trị viên cấu hình, mặc định 100.
- Không tự fallback sang fal.ai/OpenAI/OpenRouter khi quota hoặc request lỗi.
- Chỉ nhận PNG, JPEG hoặc WebP qua kiểm tra bytes thật.

### fal.ai

Model MVP:

```text
fal-ai/flux/schnell
```

Credential:

```php
define( 'NT_CONTENT_IMAGES_FAL_API_KEY', '...' );
```

Plugin submit request qua queue, kiểm tra trạng thái, lấy kết quả và tải ảnh từ miền fal được phép. Request hiện được polling trong cùng request quản trị tối đa khoảng 72 giây. Đây là giới hạn MVP; queue bền vững chạy nền sẽ được triển khai trước khi bật batch lớn.

## Cấu trúc dữ liệu

Bảng mới:

```text
{$wpdb->prefix}nt_content_image_assets
```

Lưu:

- Post, brief, generation và attachment IDs.
- Loại nguồn: stock/ai/canva/manual.
- Provider và asset ID phía nhà cung cấp.
- URL nguồn và URL ảnh gốc.
- Creator và attribution.
- License code/version/URL.
- Kích thước, MIME, dung lượng và checksum.
- Trạng thái imported/approved/rejected.

Kết quả tìm kiếm chưa chọn chỉ được cache tạm, không ghi từng ảnh vào database.

## Luồng stock image

```text
Chọn bài thiếu featured image
→ plugin lấy hoặc tạo Image Brief
→ sinh từ khóa stock ngắn gọn
→ tìm Pexels/Openverse
→ quản trị viên xem nguồn và giấy phép
→ chọn ảnh
→ plugin lấy metadata mới nhất
→ tải ảnh an toàn
→ lưu Media Library
→ tạo asset + generation record
→ chờ duyệt
→ duyệt làm featured image hoặc gửi Canva
```

## Bảo mật tải ảnh

`NT_Content_Images_Remote_Image_Downloader` áp dụng:

- HTTPS bắt buộc.
- `wp_http_validate_url()` và `wp_safe_remote_get()`.
- Tối đa 2 redirect.
- Giới hạn 25 MB.
- Kiểm tra MIME từ bytes bằng `getimagesizefromstring()`.
- Chỉ PNG/JPEG/WebP; không SVG.
- Pexels chỉ chấp nhận `images.pexels.com`.
- fal.ai chỉ chấp nhận `fal.media` và subdomain.
- Openverse vẫn đi qua WordPress safe HTTP validation vì nguồn ảnh phân tán.

## REST API

```text
GET  /nt-content-images/v1/sources/config
GET  /nt-content-images/v1/sources/search
POST /nt-content-images/v1/sources/import
GET  /nt-content-images/v1/sources/assets
```

Tất cả endpoint yêu cầu người dùng đã xác thực và capability `manage_options`.

## Không bao gồm trong MVP

- Không tự tải kết quả stock đầu tiên.
- Không tự bật fallback trả phí.
- Không chạy batch hàng trăm bài.
- Không tự thêm attribution vào frontend post content.
- Chưa có persisted background queue cho fal.ai.
- Chưa tự động chèn ảnh vào nội dung bài viết.

## QA bắt buộc trước khi merge

1. Kích hoạt/nâng cấp plugin trên LocalWP và kiểm tra bảng assets.
2. Tìm Pexels, nhập một ảnh và duyệt làm featured image.
3. Tìm Openverse CC0 và CC BY; xác nhận policy giấy phép.
4. Tạo một ảnh Cloudflare thật.
5. Tạo một ảnh fal.ai thật.
6. Gửi một ảnh stock và một ảnh AI sang Canva.
7. Kiểm tra Media Library metadata, crop 1280×720, log và REST nonce.
8. Không merge stacked PR trước khi runtime QA hoàn tất.
