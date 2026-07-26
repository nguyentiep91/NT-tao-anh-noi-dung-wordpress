# NT – Tạo ảnh cho nội dung WordPress

Plugin WordPress hỗ trợ phân tích nội dung bài viết, tạo hình ảnh bằng AI, chèn chữ và nhận diện thương hiệu, tối ưu ảnh, lưu vào Media Library và quản lý quy trình duyệt trước khi chèn vào bài.

## Thông tin dự án

- **Tên plugin:** NT – Tạo ảnh cho nội dung WordPress
- **Tác giả:** Nguyễn Tiệp
- **Website:** https://nguyentiep.vn
- **Repository:** https://github.com/nguyentiep91/NT-tao-anh-noi-dung-wordpress
- **Trạng thái:** Đã khởi tạo kiến trúc và bộ khung phát triển trên nhánh `develop`

## Mục tiêu chính

1. Quét bài viết WordPress để xác định bài thiếu ảnh đại diện hoặc ảnh trong nội dung.
2. Phân tích tiêu đề, chuyên mục, từ khóa, đoạn mở đầu và heading để tạo `image brief`.
3. Kết nối nhà cung cấp AI tạo ảnh thông qua API.
4. Tối ưu kích thước, định dạng WebP, tên tệp, alt text và metadata.
5. Chèn chữ, logo và nhận diện thương hiệu bằng template nội bộ; Canva là tích hợp tùy chọn.
6. Cho phép tạo nháp, duyệt, tạo lại, chèn ảnh và hoàn tác.
7. Theo dõi nhật ký, lỗi và chi phí API.

## Nguyên tắc kiến trúc

- Plugin độc lập với theme và không gắn cứng với một website cụ thể.
- Không kết nối trực tiếp với tài khoản Codex hoặc Claude Code khi vận hành.
- Codex/Claude Code chỉ được dùng để phát triển và kiểm thử mã nguồn.
- API key được lưu phía máy chủ, không hiển thị ở frontend.
- Không tự sửa nội dung pháp lý, shortcode, internal link, schema hoặc ngày xuất bản.
- Giai đoạn đầu vận hành theo chế độ: **tạo ảnh → chờ duyệt → mới chèn**.

## Yêu cầu dự kiến

- WordPress 6.4+
- PHP 8.1+
- PHP extension: GD hoặc Imagick
- HTTPS
- WP-Cron hoặc Action Scheduler cho xử lý hàng đợi

## Quy ước kỹ thuật

```text
Plugin slug: nt-tao-anh-noi-dung-wordpress
Text domain: nt-tao-anh-noi-dung-wordpress
PHP namespace mục tiêu: NT\ContentImages
REST namespace: nt-content-images/v1
Option prefix: nt_content_images_
Database prefix: nt_content_images_
```

## Nhánh phát triển

- `main`: phiên bản ổn định hoặc mốc đã được duyệt.
- `develop`: nhánh tích hợp phát triển.
- `feature/*`: từng module hoặc tính năng riêng.
- `fix/*`: sửa lỗi.

## Bộ khung hiện tại

- Bootstrap plugin và metadata chính thức.
- Kiểm tra PHP tối thiểu khi kích hoạt.
- Activator, deactivator và uninstall an toàn.
- Menu quản trị placeholder, chưa tạo hoặc chèn ảnh.
- Tài liệu kiến trúc và roadmap theo giai đoạn.
- Composer và PHP_CodeSniffer cho coding standards.

## Lộ trình gần nhất

- [x] Khởi tạo repository.
- [x] Tạo bộ khung plugin có thể kích hoạt an toàn.
- [x] Hoàn thiện tài liệu kiến trúc ban đầu.
- [ ] Kiểm tra kích hoạt trên môi trường WordPress thử nghiệm.
- [ ] Xây module audit bài viết ở chế độ chỉ đọc.
- [ ] Xây hàng đợi xử lý và workflow duyệt.
- [ ] Tích hợp nhà cung cấp AI đầu tiên.
- [ ] Xây template chèn chữ/logo.
- [ ] Bổ sung Canva tùy chọn.

## Tài liệu

- [Kiến trúc tổng thể](docs/ARCHITECTURE.md)
- [Lộ trình phát triển](docs/ROADMAP.md)

## Bản quyền

Copyright © Nguyễn Tiệp. Thông tin giấy phép sử dụng sẽ được xác định trước khi phát hành công khai.
