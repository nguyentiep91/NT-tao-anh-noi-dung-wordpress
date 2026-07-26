# Sprint Audit 1 — Nền tảng dữ liệu và phân tích chỉ đọc

## Mục tiêu

Sprint này tạo nền móng backend cho việc kiểm kê hình ảnh và cấu trúc bài viết. Chưa có giao diện quét hàng loạt, chưa gọi AI, chưa tải ảnh và chưa sửa `post_content`.

## Thành phần

### `NT_Content_Images_Audit_Migrator`

- Tạo bảng `{$wpdb->prefix}nt_content_images_audit` bằng `dbDelta()`.
- Theo dõi schema bằng option `nt_content_images_db_version`.
- Chỉ chứa dữ liệu phân tích dẫn xuất của plugin.

### `NT_Content_Images_Audit_Repository`

- Đọc kết quả theo `post_id`.
- Upsert idempotent theo unique key `post_id`.
- Xóa một bản ghi audit khi module sau yêu cầu.
- Không ghi vào `wp_posts`, `wp_postmeta` hoặc Media Library.

### `NT_Content_Images_Image_Detector`

Nhận diện ảnh từ:

- `core/image`.
- `core/cover`.
- `core/media-text`.
- Block lồng nhau như gallery.
- Thẻ `<img>` trong Classic Editor hoặc HTML của Gutenberg.

Bộ nhận diện:

- Không render block.
- Không chạy shortcode.
- Gộp ảnh trùng theo attachment ID hoặc URL.
- Phân biệt ảnh nội bộ và ảnh ngoài website.
- Loại trừ data URI và ảnh 1 × 1 pixel.
- Đọc alt text, kích thước và attachment metadata khi có.

### `NT_Content_Images_Content_Metrics_Analyzer`

Thống kê:

- Số từ Unicode, hỗ trợ tiếng Việt.
- Số đoạn, H2, H3, bảng và danh sách.
- Block name đang sử dụng.
- Shortcode dưới dạng văn bản.
- Cờ `has_complex_blocks` cho cấu trúc cần bảo vệ ở bước chèn ảnh sau này.

### `NT_Content_Images_Priority_Calculator`

Tính điểm 0–100 dựa trên:

- Trạng thái xuất bản.
- Thiếu featured image.
- Thiếu ảnh trong nội dung.
- Độ dài bài.
- Yoast focus keyphrase.

Nhãn kết quả:

- `very_high`: 80–100.
- `high`: 60–79.
- `medium`: 40–59.
- `low`: dưới 40.

Điểm có thể được điều chỉnh bởi filter:

```php
nt_content_images_priority_score
```

### `NT_Content_Images_Content_Scanner`

Kết hợp toàn bộ dịch vụ để tạo một kết quả audit chuẩn hóa cho một `post_id`.

- `scan( $post_id )`: chỉ đọc và trả kết quả, không lưu.
- `scan_and_store( $post_id )`: chỉ lưu kết quả dẫn xuất vào bảng audit của plugin.
- Bỏ qua revision và attachment.
- Đọc Yoast focus keyphrase nếu plugin/site có dữ liệu này.
- Tạo `content_hash` SHA-256 để chuẩn bị cho quét tăng dần.

## Dữ liệu không bị thay đổi

Sprint Audit 1 không:

- Sửa tiêu đề hoặc nội dung bài.
- Thực thi shortcode.
- Thay đổi internal link hoặc schema.
- Thay đổi ngày đăng.
- Tạo attachment.
- Đặt featured image.
- Gọi OpenAI, Canva hoặc bất kỳ API AI nào.

## Cách dùng nội bộ cho module tiếp theo

Sau khi plugin đã được khởi tạo, controller hoặc test có thể lấy scanner từ core coordinator:

```php
$scanner = $plugin->get_audit_scanner();
$result  = $scanner->scan( $post_id );
```

Sprint Audit 2 sẽ bổ sung controller, batch runner, progress UI, bộ lọc và export CSV. Không mở public endpoint trực tiếp cho scanner ở Sprint này.
