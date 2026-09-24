# AI Image Generation MVP — phiên bản 0.6.0

## Mục tiêu

Chuyển plugin từ giai đoạn phân tích sang khả năng tạo ảnh thật:

1. Chọn một nội dung đã audit và đang thiếu ảnh đại diện.
2. Tận dụng Image Brief hiện hành hoặc tự tạo brief mới từ nội dung.
3. Tạo prompt nền ảnh từ chủ đề, loại nội dung, visual strategy, restrictions và Brand Profile.
4. Gọi OpenAI Images API ở backend WordPress.
5. Lưu ảnh vào Media Library và crop về 1280 × 720 khi server hỗ trợ.
6. Hiển thị ảnh ở trạng thái chờ duyệt.
7. Chỉ đặt làm ảnh đại diện sau khi quản trị viên bấm duyệt.

## Phạm vi MVP

- Chỉ tạo featured image.
- Mỗi thao tác chỉ tạo một ảnh.
- Không chạy hàng loạt.
- Không ghi đè bài đã có featured image.
- Không tự chèn ảnh vào `post_content`.
- Không tạo chữ, logo hoặc watermark bằng AI.
- Không tự publish ảnh khi chưa duyệt.

## Nhà cung cấp

MVP dùng endpoint OpenAI Images với các model cấu hình được:

- `gpt-image-1-mini` — mặc định cho pilot.
- `gpt-image-1` — lựa chọn chất lượng cao hơn.

Cấu hình mặc định:

- Input size: `1536x1024`.
- Output format: `webp`.
- Background: `opaque`.
- Target Media Library crop: `1280x720`.
- Quality: `medium`.

## API key

Ưu tiên khai báo trong `wp-config.php`:

```php
define( 'NT_CONTENT_IMAGES_OPENAI_API_KEY', 'sk-...' );
```

Có thể nhập trong màn hình **NT – Tạo ảnh nội dung → Tạo ảnh AI** để thử nghiệm. Key lưu ở option không autoload và không bao giờ được gửi xuống JavaScript hoặc đưa vào profile export.

## Workflow

```text
Audited post thiếu featured image
  → Image Brief
  → Featured Prompt Builder
  → OpenAI Images API
  → Media Library candidate
  → generated
  → approve hoặc reject
```

Trạng thái generation:

- `generated`: ảnh đã tạo và đang chờ duyệt.
- `approved`: ảnh đã được đặt làm featured image.
- `rejected`: ảnh bị từ chối nhưng vẫn còn trong Media Library để truy vết.
- `failed`: provider hoặc lưu trữ gặp lỗi.

## REST API

Namespace: `nt-content-images/v1`

- `POST /generations/featured`
- `GET /generations`
- `GET /generations/candidates`
- `GET /generations/config`
- `GET /generations/{id}`
- `POST /generations/{id}/approve`
- `POST /generations/{id}/reject`

Mọi endpoint yêu cầu cookie authentication, REST nonce và capability `manage_options`.

## An toàn dữ liệu

- Không thay đổi title, content, taxonomy, schema hoặc ngày đăng khi tạo ảnh.
- Chỉ `set_post_thumbnail()` sau thao tác duyệt rõ ràng.
- Không ghi đè featured image khác đã được gắn trong thời gian chờ duyệt.
- API key không ghi vào log, generation record hoặc REST response.
- Provider response chỉ lưu usage và revised prompt; không lưu base64 ảnh.

## Pilot LocalWP

1. Chuyển sang nhánh `feature/ai-image-generation-mvp`.
2. Tải lại WP Admin để migration tạo bảng `wp_nt_content_image_generations`.
3. Cấu hình API key.
4. Chọn một bài thử không có featured image.
5. Bấm **Tạo ảnh** đúng một lần.
6. Kiểm tra Media Library, kích thước, alt text và prompt.
7. Bấm **Duyệt làm ảnh đại diện**.
8. Kiểm tra bài viết và debug log.
9. Thử **Từ chối** trên ảnh thứ hai.
10. Chỉ mở rộng lên 5 bài sau khi lần đầu đạt.

## Chưa bao gồm

- Ảnh trong nội dung.
- Text/logo overlay.
- Tạo nhiều phương án trong một request.
- Queue, cron và batch generation.
- Cost estimator.
- Provider khác ngoài OpenAI.
- Rollback featured image đã duyệt.
