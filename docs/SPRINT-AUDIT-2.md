# Sprint Audit 2 — Batch, REST API và giao diện quản trị

## Phạm vi

Sprint Audit 2 biến các dịch vụ backend của Sprint Audit 1 thành một quy trình audit có thể vận hành trong WordPress Admin. Plugin vẫn chỉ đọc nội dung và chỉ ghi dữ liệu dẫn xuất vào bảng audit riêng.

## Luồng xử lý

```text
Admin bắt đầu audit
    ↓
Audit Query đếm và lấy ID bài viết
    ↓
Job Store lưu trạng thái tiến trình
    ↓
Batch Runner xử lý 5–50 bài/request
    ↓
Content hash giống nhau → bỏ qua
Content hash thay đổi → quét và upsert
Một bài lỗi → ghi lỗi và tiếp tục
    ↓
REST API trả tiến độ
    ↓
Admin UI cập nhật summary, danh sách và chi tiết
```

## Dịch vụ mới

### Audit Query

- Chỉ lấy ID để giảm bộ nhớ.
- Hỗ trợ `post`, `page` và filter mở rộng qua hook.
- Trạng thái được cho phép: publish, draft, pending, private, future.
- Batch size bị giới hạn tối đa 50.

### Audit Job Store

- Lưu một job tại `nt_content_images_audit_job` với `autoload=no`.
- Trạng thái: running, paused, completed, cancelled, failed.
- Dữ liệu tiến độ tồn tại khi tải lại trang.
- Lock ngắn hạn ngăn hai request xử lý batch đồng thời.

### Batch Runner

Chế độ quét:

- `new`: chỉ bài chưa có bản ghi audit.
- `changed`: bài mới hoặc có content hash thay đổi.
- `all`: quét lại toàn bộ.

Một lỗi cục bộ không dừng job. Thông báo lỗi được làm sạch và không chứa nội dung bài, nonce hoặc secret.

### REST Controller

Namespace:

```text
nt-content-images/v1
```

Endpoint:

```text
POST /audit/start
POST /audit/process
POST /audit/pause
POST /audit/resume
POST /audit/cancel
GET  /audit/status
GET  /audit/summary
GET  /audit/posts
GET  /audit/posts/{id}
GET  /audit/config
```

Tất cả endpoint yêu cầu:

- Người dùng đã đăng nhập.
- Capability `manage_options`.
- REST nonce hợp lệ khi dùng cookie authentication.

### Admin UI

Menu:

```text
NT – Tạo ảnh nội dung
└── Kiểm tra bài viết
```

Giao diện hỗ trợ:

- Chọn post type, status, scan mode và batch size.
- Bắt đầu, tạm dừng, tiếp tục, hủy.
- Hiển thị tổng, đã xử lý, đã quét, bỏ qua, lỗi và phần trăm.
- Tổng quan tình trạng ảnh.
- Lọc, tìm kiếm, phân trang.
- Xem chi tiết cấu trúc và thành phần cần bảo vệ.
- Export CSV UTF-8.

### CSV Exporter

- Chỉ chạy qua authenticated `admin-post.php`.
- Kiểm tra capability và nonce.
- UTF-8 BOM để Excel đọc đúng tiếng Việt.
- Escape ô bắt đầu bằng `=`, `+`, `-`, `@`, tab hoặc carriage return để chống CSV injection.

## Nguyên tắc an toàn

Sprint này không:

- Sửa `post_content`, title, date hoặc taxonomy.
- Tạo, xóa hoặc thay attachment.
- Render Gutenberg block.
- Thực thi shortcode.
- Gọi OpenAI, Canva hoặc API ngoài.
- Cung cấp REST endpoint công khai.

## Giới hạn trước Sprint Audit 3

- Chưa có test tích hợp WordPress tự động.
- Chưa xác minh UI trên dữ liệu thật gần 500 bài.
- Chưa tạo ZIP cài đặt thử nghiệm.
- Chưa kiểm thử hosting có giới hạn thấp.

Các nội dung này được thực hiện trong Sprint Audit 3 trước khi chuyển sang Image Brief Engine.
