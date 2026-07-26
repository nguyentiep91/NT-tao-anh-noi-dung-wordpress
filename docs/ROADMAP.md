# Lộ trình phát triển

## Phase 0 — Khởi tạo dự án

Mục tiêu: repository có cấu trúc rõ ràng, plugin kích hoạt an toàn và chưa làm thay đổi dữ liệu nội dung.

- [x] Khởi tạo `main` và `develop`.
- [x] Tạo README và tài liệu kiến trúc.
- [x] Tạo bootstrap, activator, deactivator và uninstall an toàn.
- [x] Bổ sung coding standards và kiểm tra cú pháp tự động.
- [ ] Tạo bản ZIP thử nghiệm đầu tiên.
- [x] Kiểm tra kích hoạt và vận hành trên LocalWP.

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

- [x] Thống kê tổng số bài theo post type và trạng thái.
- [x] Tạo REST controller nội bộ có REST nonce và capability `manage_options`.
- [x] Quét theo batch 5–50 bài, hiển thị tiến độ, tạm dừng, tiếp tục và hủy.
- [x] Chỉ quét lại bài có `content_hash` thay đổi trong chế độ mặc định.
- [x] Lưu job state trong WordPress options để tải lại trang không mất tiến độ.
- [x] Khóa request ngắn hạn để ngăn hai batch chạy đồng thời.
- [x] Một bài lỗi không làm dừng toàn bộ tiến trình.
- [x] Tạo màn hình danh sách, bộ lọc và phân trang.
- [x] Tạo màn hình chi tiết một bài.
- [x] Export CSV UTF-8 theo bộ lọc và chống CSV injection.
- [x] Không gọi AI và không thay đổi bài viết hoặc Media Library.

### Sprint Audit 3 — QA thực tế

- [x] Cài đặt và chạy smoke test thành công trên LocalWP.
- [x] Audit hoàn tất 51/51 bài, không có lỗi runtime trong lần chạy được ghi nhận.
- [x] Cơ chế quét tăng dần đã ghi nhận cả bài quét mới và bài được bỏ qua.
- [ ] Tạo fixtures tự động cho Gutenberg, Classic Editor, gallery, cover và shortcode.
- [ ] Đối chiếu thủ công 20–30 bài thực tế.
- [ ] Kiểm tra đầy đủ deactivate/uninstall trên WordPress thử nghiệm.
- [ ] Kiểm tra pause/resume/reload và xử lý đồng thời theo ma trận QA.
- [ ] Hoàn thiện coding standards bắt buộc.
- [ ] Tạo bản ZIP thử nghiệm đầu tiên.

**Tiêu chí hoàn thành Phase 1:** báo cáo audit chính xác, không làm thay đổi database nội dung, Media Library, shortcode, schema hoặc ngày đăng.

## Phase 2 — Image Brief Engine

### Sprint Brief 1 — Nền tảng và tạo brief theo quy tắc

- [x] Tạo bảng `{$wpdb->prefix}nt_content_image_briefs` có versioning.
- [x] Tạo Brief Repository và workflow `draft/pending_review/approved/rejected/outdated`.
- [x] Tạo Brief Source Builder từ dữ liệu bài viết và kết quả audit.
- [x] Tạo Intent Classifier theo quy tắc giải thích được.
- [x] Tạo Visual Strategy Resolver theo loại nội dung.
- [x] Tạo Placement Planner theo anchor, không sửa `post_content`.
- [x] Tạo Restriction Builder cho pháp lý, đấu thầu, chứng chỉ, FDA/ISO/CE.
- [x] Tạo Image Brief JSON schema `1.0`.
- [x] Tạo Brief Validator.
- [x] Tạo REST API nội bộ và màn hình “Kế hoạch hình ảnh”.
- [x] Tạo brief hàng loạt tối đa 20 bài/lần.
- [x] Cho phép gửi duyệt, duyệt và từ chối brief.
- [ ] Kiểm thử pilot 10 bài đại diện trên LocalWP.
- [ ] Bổ sung giao diện chỉnh sửa chi tiết brief.

### Sprint Brief 2 — Prompt Builder

- [ ] Prompt builder theo template.
- [ ] Negative prompt/constraints từ brief.
- [ ] Phiên bản prompt và truy vết nguồn.
- [ ] Giao diện xem trước/chỉnh sửa prompt.
- [ ] Kiểm thử prompt trên 10 brief đã duyệt.

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
