# Lộ trình phát triển

## Phase 0 — Khởi tạo dự án

Mục tiêu: repository có cấu trúc rõ ràng, plugin kích hoạt an toàn và chưa làm thay đổi dữ liệu nội dung.

- [x] Khởi tạo `main` và `develop`.
- [x] Tạo README và tài liệu kiến trúc.
- [x] Tạo bootstrap, activator, deactivator và uninstall an toàn.
- [x] Bổ sung coding standards và kiểm tra cú pháp tự động.
- [ ] Tạo bản ZIP thử nghiệm đầu tiên.
- [ ] Kiểm tra kích hoạt trên WordPress 6.4+ / PHP 8.1+.

**Tiêu chí hoàn thành:** plugin kích hoạt, hiển thị menu quản trị và không tạo/sửa ảnh hoặc nội dung.

## Phase 1 — Audit chỉ đọc

### Sprint Audit 1 — Nền tảng dữ liệu

- [x] Tạo migration bảng `{$wpdb->prefix}nt_content_images_audit`.
- [x] Tạo Audit Repository hỗ trợ đọc, upsert và xóa dữ liệu dẫn xuất.
- [x] Tạo Content Scanner cho từng bài viết.
- [x] Tạo Image Detector cho Gutenberg và Classic HTML.
- [x] Tạo Content Metrics Analyzer.
- [x] Tạo Priority Calculator và số ảnh nội dung khuyến nghị.
- [x] Đọc Yoast focus keyphrase nếu có.
- [x] Lưu `content_hash` để chuẩn bị quét tăng dần.
- [x] Không render block, không chạy shortcode và không sửa bài viết.

### Sprint Audit 2 — Batch và giao diện

- [ ] Thống kê tổng số bài theo post type và trạng thái.
- [ ] Tạo REST/AJAX controller có nonce và capability.
- [ ] Quét theo batch nhỏ, hiển thị tiến độ, tạm dừng và tiếp tục.
- [ ] Chỉ quét lại bài có `content_hash` thay đổi.
- [ ] Tạo màn hình danh sách, bộ lọc và phân trang.
- [ ] Tạo màn hình chi tiết một bài.
- [ ] Export CSV theo bộ lọc.

### Sprint Audit 3 — QA thực tế

- [ ] Tạo fixtures cho Gutenberg, Classic Editor, gallery, cover và shortcode.
- [ ] Đối chiếu thủ công 20–30 bài thực tế.
- [ ] Kiểm tra kích hoạt/deactivate/uninstall trên WordPress thử nghiệm.
- [ ] Hoàn thiện coding standards bắt buộc.

**Tiêu chí hoàn thành Phase 1:** báo cáo audit gần 500 bài chính xác, không làm thay đổi database nội dung, Media Library, shortcode, schema hoặc ngày đăng.

## Phase 2 — Image Brief và Prompt

- [ ] Quy tắc phân loại chủ đề.
- [ ] Image brief JSON có schema.
- [ ] Prompt builder theo template.
- [ ] Negative constraints cho pháp lý, logo, chữ và giao diện giả.
- [ ] Giao diện xem trước/chỉnh sửa prompt.

## Phase 3 — Queue và Workflow

- [ ] Hàng đợi batch.
- [ ] Trạng thái draft/pending/approved/rejected/inserted.
- [ ] Retry có giới hạn.
- [ ] Rate limit.
- [ ] Nhật ký thao tác.

## Phase 4 — Nhà cung cấp AI đầu tiên

- [ ] Provider interface.
- [ ] OpenAI Image adapter.
- [ ] Kiểm tra API key.
- [ ] Ước tính chi phí trước khi tạo.
- [ ] Tạo 1–3 phương án ảnh nền không chữ.
- [ ] Xử lý timeout và lỗi provider.

## Phase 5 — Xử lý ảnh và Media Library

- [ ] Resize/crop preset.
- [ ] WebP và nén ảnh.
- [ ] SEO filename, alt text và caption.
- [ ] Upload attachment.
- [ ] Kiểm tra ảnh trùng cơ bản.

## Phase 6 — Template chữ và thương hiệu

- [ ] SVG/Imagick renderer.
- [ ] Logo thật, website và nhãn chuyên mục.
- [ ] Font tiếng Việt được cấp phép sử dụng.
- [ ] 3–5 template prototype.
- [ ] Mở rộng 12–15 template sau thử nghiệm.

## Phase 7 — Chèn ảnh và hoàn tác

- [ ] Đặt featured image.
- [ ] Xác định vị trí chèn an toàn.
- [ ] Snapshot nội dung cũ.
- [ ] Hoàn tác từng bài.
- [ ] Hoàn tác từng batch.

## Phase 8 — Canva tùy chọn

- [ ] OAuth 2.0.
- [ ] Upload asset.
- [ ] Create design.
- [ ] Autofill khi tài khoản hỗ trợ.
- [ ] Export thiết kế.
- [ ] Nút “Chỉnh sửa bằng Canva”.

## Phase 9 — Kiểm thử thực tế

- [ ] 20 bài thuộc nhiều chuyên mục.
- [ ] QA desktop/mobile.
- [ ] Kiểm tra Core Web Vitals.
- [ ] Kiểm tra shortcode, Gutenberg, internal link và schema.
- [ ] Chạy batch 20–30 bài sau khi được duyệt.

## Ngoài phạm vi phiên bản đầu

- Tự động publish ảnh cho toàn bộ website mà không duyệt.
- Tạo video.
- Tạo hoặc giả lập giao diện hệ thống nhà nước.
- Tự viết nội dung pháp lý vào ảnh.
- Kết nối trực tiếp với tài khoản Codex/Claude thay cho API provider.
