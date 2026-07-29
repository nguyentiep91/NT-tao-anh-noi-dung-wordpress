# Ảnh minh hoạ trong bài viết — phiên bản 0.10.0

## Mục tiêu

Hoàn thành mắt xích cuối của luồng sản phẩm: từ kế hoạch hình ảnh (Phase 2) đến ảnh thật nằm đúng vị trí trong `post_content`, có duyệt và có hoàn tác.

```text
Bài viết đã audit
→ Image Brief: số ảnh theo độ dài (≥3000 từ: 3, 1500–3000: 2, còn lại: 1)
   + vị trí an toàn (sau mở đầu, trước các H2 chuyển phần)
→ Tạo ảnh cho từng vị trí (prompt theo ngữ cảnh section, góc máy thay đổi từng ảnh)
→ Duyệt từng ảnh
→ Chèn vào post_content (block Gutenberg hoặc figure Classic)
→ Hoàn tác bất kỳ lúc nào (snapshot trước khi chèn)
```

## Thành phần

| File | Vai trò |
| --- | --- |
| `includes/generation/class-nt-content-images-content-prompt-builder.php` | Prompt theo section: heading + trích 400 ký tự nội dung, 5 góc máy luân phiên, phong cách tư liệu tự nhiên, cấm chữ/logo |
| `includes/generation/class-nt-content-images-content-image-generator.php` | Đọc brief, tạo ảnh cho từng vị trí `safe_candidate`, bỏ qua vị trí đã có ảnh; record `settings.image_type = content` |
| `includes/insertion/class-nt-content-images-content-inserter.php` | Chèn block ảnh vào anchor, snapshot `_ntci_content_snapshot`, rollback byte-identical |
| `includes/insertion/class-nt-content-images-content-rest-controller.php` | REST: candidates, plan, generate, insert, rollback |
| `admin/class-nt-content-images-content-admin.php` + assets | Màn hình **Ảnh trong bài**: chọn bài → xem kế hoạch → tạo → duyệt → chèn → hoàn tác |

## Quy tắc chèn

- Chỉ chèn ảnh có record trạng thái `approved`; sau khi chèn chuyển `inserted`.
- `after_intro`: sau đoạn văn thứ 3 (block `<!-- /wp:paragraph -->` hoặc `</p>`; bài ngắn hơn thì sau đoạn cuối cùng).
- `before_heading`: trước H2 khớp văn bản heading (so sánh không phân biệt hoa thường, chuẩn hoá khoảng trắng, hỗ trợ tiếng Việt).
- Không tìm thấy anchor → bỏ qua vị trí đó và báo lại, không bao giờ chèn bừa.
- Vị trí `manual_review_required` trong kế hoạch (bài có shortcode bảo vệ, builder phức tạp) không bao giờ được tạo/chèn tự động.
- Mỗi slot chỉ chèn đúng một ảnh: bản ghi mới nhất được duyệt thắng (thường là bản đã chèn chữ); slot đã `inserted` bị chặn chèn lại để tránh trùng.
- Duyệt ảnh content **không** đụng tới featured image (guard riêng trong approve).

## Chữ trên ảnh và metadata SEO

- Khi bật **Tự động chèn chữ**, ảnh trong bài được overlay theo mẫu riêng (cấu hình "Mẫu cho ảnh trong bài"); **chữ trên ảnh là tên mục H2** tương ứng (ảnh sau mở đầu dùng tiêu đề bài), kèm badge chuyên mục và dòng thương hiệu/logo.
- Attachment đầy đủ metadata: **Title** = `{heading} — {chủ đề}`, **Alt** = như Title, **Caption** = `Minh họa cho phần "{heading}"` (hiện thành figcaption dưới ảnh).
- Thẻ `<img>` chèn vào bài có cả `alt` và `title` attribute.

## Snapshot & hoàn tác

- Trước lần chèn đầu tiên, toàn bộ `post_content` gốc được lưu vào postmeta `_ntci_content_snapshot` (JSON, đã `wp_slash` để không hỏng dữ liệu khi WordPress unslash).
- Hoàn tác khôi phục nội dung nguyên vẹn từng byte, đưa record về `approved` và xoá snapshot.
- WordPress revisions vẫn hoạt động song song như lớp bảo hiểm thứ hai.

## REST API

Namespace `nt-content-images/v1`, cookie + nonce + `manage_options`:

```text
GET  /content-images/candidates      → bài đã audit, ưu tiên bài dài
GET  /content-images/plan?post_id=   → kế hoạch + trạng thái từng vị trí + snapshot
POST /content-images/generate        → tạo cả bộ, hoặc {index} cho một vị trí
POST /content-images/insert          → chèn các ảnh đã duyệt
POST /content-images/rollback        → khôi phục nội dung gốc
```

## Kết quả kiểm thử thật (LocalWP, 2026-07-27)

Bài test tiếng Việt 2.500 từ, 7 mục H2, OpenRouter FLUX.2:

- Kế hoạch: 2 ảnh (sau mở đầu + trước "Hồ sơ và tài liệu cần chuẩn bị") — đúng ngưỡng 1500–3000 từ.
- Tạo 2 ảnh thật trong 65 giây, mỗi ảnh một góc máy khác nhau, đúng ngữ cảnh section.
- Duyệt 2 ảnh — featured image không bị ảnh hưởng.
- Chèn đúng 2 vị trí đã hoạch định (xác minh bằng vị trí chuỗi trong content).
- Hoàn tác khôi phục nội dung **byte-identical** với bản gốc.
- 41/41 unit test pass, gồm 6 test cho logic chèn (block + classic + anchor mất).

## Giới hạn hiện tại

- Tạo cả bộ ảnh chạy tuần tự trong một request (~30–90 giây/ảnh) — cần queue nền (Phase 3) trước khi chạy batch nhiều bài.
- Brief chỉ tự làm mới khi profile đổi hoặc chưa có; sửa nội dung bài lớn nên tạo brief mới thủ công ở màn Kế hoạch hình ảnh.
- Model FLUX đôi khi vẫn vẽ chữ nhỏ trong tài liệu/đạo cụ dù prompt cấm — hãy từ chối và tạo lại vị trí đó khi gặp.
- Chưa tự chèn attribution cho ảnh stock vào nội dung.
