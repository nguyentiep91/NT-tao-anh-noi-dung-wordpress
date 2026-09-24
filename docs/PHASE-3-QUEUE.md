# Chạy hàng loạt và giới hạn ngày — phiên bản 0.11.0 (tự động chèn từ 0.13.0)

## Mục tiêu

Xử lý nhiều bài liên tiếp mà không đổi mô hình an toàn: mỗi bước đúng một yêu cầu API, mọi ảnh vẫn đi qua workflow duyệt, và toàn bộ hệ thống dừng đúng trần chi phí do quản trị viên đặt.

## Thành phần

| File | Vai trò |
| --- | --- |
| `includes/generation/class-nt-content-images-usage-tracker.php` | Bộ đếm ảnh AI/ngày theo provider (option, reset theo ngày UTC) |
| `includes/queue/class-nt-content-images-generation-queue.php` | Job store trong option; start/step/pause/resume/cancel |
| `includes/queue/class-nt-content-images-queue-rest-controller.php` | REST `/queue/*` |
| Panel "Chạy hàng loạt" trong trang **Ảnh trong bài** | Chọn phạm vi, tiến độ, tạm dừng/hủy |

## Cách hoạt động

```text
Start: chọn phạm vi (ảnh đại diện / ảnh trong bài) + số bài tối đa (1–50)
→ lấy ứng viên từ audit theo mức ưu tiên
→ UI lặp gọi POST /queue/step trong khi tab mở
→ mỗi step xử lý ĐÚNG MỘT ảnh (featured trước, rồi từng vị trí trong bài)
→ auto chèn chữ + WebP chạy như luồng đơn lẻ
→ lỗi từng bài được ghi lại và đi tiếp, không làm chết cả đợt
→ hết việc → completed
```

- Mỗi step giới hạn một yêu cầu provider nên luôn nằm gọn trong một request admin (không vượt `max_execution_time`).
- Transient lock chặn hai tab cùng bấm step.
- Bài đã có featured → đánh dấu `skipped`; kế hoạch không có ảnh nội dung → `skipped`.
- Đợt chạy **tự tạm dừng** khi chạm giới hạn ngày (`pause_reason = daily_limit`) và có thể tiếp tục hôm sau.

## Tự động duyệt & chèn (0.13.0)

Checkbox **"Tự động duyệt & chèn vào bài sau khi tạo xong mỗi bài"** (mặc định bật) thêm một bước cục bộ `insert` cho từng bài sau khi tạo đủ ảnh:

1. Đặt ảnh đại diện: duyệt bản `generated` mới nhất không phải ảnh nội dung (thường là bản đã chèn chữ); chỉ khi bài chưa có thumbnail.
2. Duyệt bản mới nhất của **từng vị trí** ảnh trong bài (bản chèn chữ thắng bản gốc).
3. Gọi Content Inserter chèn theo kế hoạch — có snapshot, hoàn tác riêng từng bài như luồng thủ công.

Bước insert không gọi API nên không tính vào giới hạn ngày. Bài không có ảnh nội dung mới → `skipped`; lỗi chèn (không tìm thấy anchor) được ghi vào bài đó và đợt chạy vẫn đi tiếp. Trạng thái từng bài hiển thị thêm cột "chèn vào bài" với số ảnh đã chèn.

Tắt checkbox để quay về hành vi cũ: ảnh nằm chờ duyệt thủ công.

## Giới hạn ảnh AI/ngày

- Cấu hình tại **Ảnh AI & Canva → Giới hạn tổng ảnh AI/ngày** (mặc định 100, tối đa 1000), áp dụng cho mọi provider và mọi đường tạo ảnh: nút đơn lẻ, bộ ảnh nội dung, chạy hàng loạt.
- Chỉ ảnh tạo **thành công** mới tính; overlay chữ render cục bộ không tính.
- Giới hạn Cloudflare/ngày riêng vẫn giữ nguyên như lớp chặn thứ hai.

## Nén WebP khi lưu

Từ 0.11.0, mọi ảnh PNG/JPEG từ provider được nén sang WebP (quality 82, filter `nt_content_images_webp_quality`) trước khi vào Media Library nếu bản WebP nhỏ hơn; đo thực tế ảnh FLUX giảm từ ~1.5 MB PNG còn ~0.7–0.8 MB WebP. Tắt bằng filter `nt_content_images_convert_webp`.

## REST API

```text
POST /queue/start   {include_featured, include_content, limit}
GET  /queue/status
POST /queue/step
POST /queue/pause | /queue/resume | /queue/cancel
```

Tất cả yêu cầu cookie + nonce + `manage_options`.

## Kết quả kiểm thử thật (LocalWP, 2026-07-27)

- Queue 1 bài thật qua OpenRouter: step 1 tạo ảnh trong 54 giây (bộ đếm ngày 0 → 1, auto chèn chữ, WebP 815 KB), step 2 kết thúc `completed`.
- Khi provider lỗi (OpenAI billing hard limit): bài bị đánh dấu lỗi kèm thông báo, đợt chạy vẫn kết thúc sạch, bộ đếm không tăng.
- 46/46 unit test pass (Usage Tracker, WebP converter, inserter, overlay, providers).

## Giới hạn hiện tại

- Cần giữ tab admin mở trong khi chạy (UI tự tiếp tục đợt dở dang khi mở lại trang). Chưa chạy nền bằng WP-Cron/Action Scheduler.
- Chưa có báo cáo chi phí quy đổi tiền tệ theo provider — mới dừng ở số lượng ảnh/ngày.
