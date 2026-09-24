# Sprint Audit 3 — Báo cáo kiểm thử runtime ban đầu

## Phạm vi bằng chứng

Báo cáo này ghi nhận lần chạy thực tế do chủ dự án thực hiện trên LocalWP sau khi cài mã nguồn Sprint Audit 2.

Đây là **runtime smoke test**, chưa thay thế toàn bộ ma trận QA tích hợp.

## Môi trường

- Nền tảng: LocalWP trên Windows.
- Site thử nghiệm: `test`.
- Đường dẫn WordPress: `C:\Users\ADMIN\Local Sites\test\app\public`.
- Plugin được cài trong `wp-content/plugins/nt-tao-anh-noi-dung-wordpress`.
- Khu vực kiểm thử: WordPress Admin → NT – Tạo ảnh nội dung → Kiểm tra bài viết.

Phiên bản WordPress và PHP cụ thể cần được bổ sung từ LocalWP Site Info trong lần QA tiếp theo.

## Kết quả lần chạy được ghi nhận

```text
Trạng thái: completed
Đã xử lý: 51 / 51
Đã quét: 25
Bỏ qua: 26
Lỗi: 0
Tiến độ: 100%
```

Tổng quan dữ liệu:

```text
Tổng bài đã audit: 51
Thiếu ảnh đại diện: 27
Không có ảnh nội dung: 51
Có ảnh ngoài website: 0
Có ảnh thiếu alt: 0
Ưu tiên rất cao: 25
Có shortcode: 24
Có block phức tạp: 0
Bài lỗi: 0
```

## Kết luận hiện tại

Đã xác nhận:

- Plugin cài đặt và kích hoạt được trong LocalWP.
- Menu quản trị và màn hình audit hiển thị.
- REST/batch workflow hoàn thành job 51 bài.
- Cơ chế `changed` đã phân biệt bài cần quét và bài được bỏ qua.
- Không có lỗi bài viết được giao diện ghi nhận trong lần chạy.
- Summary và progress UI cập nhật đến 100%.

Chưa được xác nhận đầy đủ:

- Độ chính xác thủ công của 20–30 bài.
- Pause/resume/reload/cancel theo ma trận QA.
- Chạy đồng thời ở hai tab.
- Deactivate/uninstall theo hai chế độ giữ và xóa dữ liệu.
- CSV injection và tương thích Excel.
- PHP warning/notice trong `debug.log` và LocalWP PHP log.
- Hiệu suất trên hosting tài nguyên thấp.
- Fixtures tự động cho Gutenberg, Classic Editor, gallery và cover.

## Quyết định kỹ thuật

Kết quả smoke test đủ để bắt đầu **Image Brief Engine** trên nhánh riêng, nhưng Phase Audit chỉ được khóa hoàn toàn sau khi các mục QA còn lại được hoàn tất.

Image Brief Engine tiếp tục tuân thủ nguyên tắc chỉ đọc đối với nội dung WordPress và không gọi AI trong phiên bản `0.4.0`.
