# Lộ trình phát triển

## Phase 0 — Khởi tạo dự án

Mục tiêu: repository có cấu trúc rõ ràng, plugin kích hoạt an toàn và chưa làm thay đổi dữ liệu nội dung.

- [x] Khởi tạo `main` và `develop`.
- [x] Tạo README và tài liệu kiến trúc.
- [x] Tạo bootstrap, activator, deactivator và uninstall an toàn.
- [x] Bổ sung coding standards và kiểm tra cú pháp tự động.
- [ ] Tạo bản ZIP thử nghiệm đầu tiên.
- [x] Kiểm tra kích hoạt và vận hành trên LocalWP.

## Phase 1 — Audit chỉ đọc

### Sprint Audit 1 — Nền tảng dữ liệu

- [x] Migration bảng audit.
- [x] Audit Repository, Content Scanner, Image Detector và Content Metrics Analyzer.
- [x] Priority Calculator, SEO metadata ban đầu và `content_hash`.
- [x] Không render block, không chạy shortcode và không sửa bài viết.

### Sprint Audit 2 — Batch và giao diện

- [x] REST controller có capability và nonce.
- [x] Batch 5–50 bài, pause/resume/cancel và lưu trạng thái.
- [x] Quét tăng dần, xử lý lỗi từng bài.
- [x] Danh sách, bộ lọc, chi tiết và CSV an toàn.

### Sprint Audit 3 — QA thực tế

- [x] Smoke test LocalWP: 51/51 bài hoàn thành, không có lỗi runtime được ghi nhận.
- [ ] Fixtures tự động cho Gutenberg, Classic, gallery, cover và shortcode.
- [ ] Đối chiếu thủ công 20–30 bài.
- [ ] Kiểm tra deactivate/uninstall, pause/resume/reload và xử lý đồng thời.
- [ ] Coding standards bắt buộc và ZIP beta.

## Phase 2 — Image Brief Engine

### Sprint Brief 1 — Brief theo quy tắc

- [x] Bảng brief có versioning và workflow.
- [x] Brief Source Builder, Intent Classifier, Visual Strategy, Placement và Restriction Builder.
- [x] Image Brief JSON schema `1.0`, validator, REST API và giao diện quản trị.
- [x] Tạo tối đa 20 brief/lần; gửi duyệt, duyệt và từ chối.
- [ ] Pilot 10 bài đại diện trên LocalWP.
- [ ] Giao diện chỉnh sửa sâu từng trường brief.

### Sprint Generic Core & Profile System

- [x] Nâng plugin lên `0.5.0`, database schema `1.2.0` và brief schema `1.1`.
- [x] Tạo Site Profile và Brand Profile độc lập với website cụ thể.
- [x] Tạo màn hình **Cấu hình website**.
- [x] Phát hiện post type động và ánh xạ sang nhóm nội dung chung.
- [x] Cấu hình protected shortcode, blocked heading và page-builder policy.
- [x] Tạo Rule Pack interface/registry.
- [x] Tạo các pack `generic`, `education`, `legal`, `procurement`.
- [x] Refactor classifier, restrictions và placement sang rule pack.
- [x] Tạo SEO Adapter cho WordPress, Yoast và Rank Math.
- [x] Import/export profile JSON không chứa bí mật.
- [x] Lưu profile version/hash, brand hash, rule packs và mapping trong brief.
- [x] Đánh dấu brief cũ `outdated` khi profile thay đổi.
- [x] Chặn duyệt brief được tạo bằng profile cũ.
- [x] Hook mở rộng cho post type, rule pack, SEO metadata, classification, restrictions, strategy và brief.
- [ ] Runtime QA trên LocalWP với tối thiểu hai profile website khác nhau.
- [ ] Kiểm thử custom post type và import/export giữa hai website.

### Sprint Brief 2 — Prompt Builder

- [ ] Bảng prompt và versioning.
- [ ] Prompt Context Builder theo brief đã duyệt.
- [ ] Scene Composer và Composition Resolver.
- [ ] Negative prompt từ restrictions.
- [ ] Overlay JSON tách khỏi phần ảnh AI.
- [ ] Prompt package trung lập provider.
- [ ] Validator và giao diện xem/chỉnh sửa/duyệt prompt.
- [ ] Pilot trên 10 brief đã duyệt.

## Phase 3 — Queue và Workflow

- [x] Hàng đợi batch: mỗi bước một ảnh, pause/resume/cancel, lỗi từng bài không chặn cả đợt.
- [x] Giới hạn tổng ảnh AI/ngày cho mọi provider; đợt chạy tự tạm dừng khi chạm trần.
- [x] Nén WebP khi lưu ảnh provider vào Media Library.
- [ ] Chạy nền bằng WP-Cron/Action Scheduler (hiện cần giữ tab admin mở).
- [ ] Retry tự động có giới hạn cho lỗi tạm thời.

## Phase 4 — Nhà cung cấp AI đầu tiên

- [ ] Provider interface.
- [ ] OpenAI Image adapter.
- [ ] Kiểm tra API key và ước tính chi phí.
- [ ] Tạo 1–3 phương án ảnh nền không chữ.
- [ ] Xử lý timeout và lỗi provider.

## Phase 5 — Xử lý ảnh và Media Library

- [x] Resize/crop về kích thước đích khi lưu.
- [x] WebP và nén ảnh (0.11.0).
- [x] SEO filename, alt text và caption (0.10.0).
- [x] Upload attachment và kiểm tra ảnh trùng bằng hash (0.12.0).
- [x] Tự dọn ảnh trung gian không dùng: bản gốc sau chèn chữ, ảnh bị từ chối, ứng viên thừa sau chèn/duyệt (0.12.0).
- [x] Nút "Dọn ảnh không dùng" quét toàn bộ Media Library, chỉ giữ ảnh đang dùng (0.12.0).

## Phase 6 — Template chữ và thương hiệu

- [x] GD/FreeType renderer (thay cho phương án SVG/Imagick ban đầu).
- [x] Logo thật, website, nhãn chuyên mục và font được cấp phép (Be Vietnam Pro, SIL OFL).
- [x] 5 template đầu tiên: bottom_gradient, left_panel, top_band, center_box, minimal_badge.
- [x] Xem thử không lưu và chèn chữ thành ảnh mới chờ duyệt.
- [x] Filter mở rộng template, font và dữ liệu overlay.
- [ ] Runtime QA trên LocalWP theo checklist PHASE-6.
- [ ] Template pack theo lĩnh vực và tự chọn mẫu theo nhóm nội dung.

## Phase 7 — Chèn ảnh và hoàn tác

- [x] Đặt featured image (qua workflow duyệt từ 0.6.0).
- [x] Tạo ảnh minh hoạ cho từng vị trí trong kế hoạch (prompt theo section, góc máy luân phiên).
- [x] Chèn block ảnh an toàn theo anchor (Gutenberg + Classic), bỏ qua anchor không tìm thấy.
- [x] Snapshot trước khi chèn và rollback byte-identical từng bài.
- [x] Màn hình quản trị Ảnh trong bài (kế hoạch → tạo → duyệt → chèn → hoàn tác).
- [ ] Batch nhiều bài (chờ queue nền Phase 3).

## Phase 8 — Canva tùy chọn

- [ ] OAuth 2.0, upload asset, create design, autofill và export.
- [ ] Nút “Chỉnh sửa bằng Canva”.

## Phase 9 — Kiểm thử thực tế

- [ ] Pilot đa website và nhiều post type.
- [ ] QA desktop/mobile, Core Web Vitals và compatibility.
- [ ] Kiểm tra shortcode, builder, internal link và schema.

## Ngoài phạm vi phiên bản đầu

- Tự động publish ảnh cho toàn bộ website mà không duyệt.
- Tạo video.
- Tạo hoặc giả lập giao diện hệ thống nhà nước.
- Tự viết nội dung pháp lý vào ảnh.
- Kết nối trực tiếp tài khoản Codex/Claude thay cho API provider.
