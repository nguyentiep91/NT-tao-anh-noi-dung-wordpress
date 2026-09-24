# Template Renderer — chèn chữ và thương hiệu, phiên bản 0.9.0

## Mục tiêu

Biến ảnh nền AI/stock thành ảnh minh hoạ hoàn chỉnh có tiêu đề, nhãn chuyên mục, logo và tên website — mà **không nhờ AI vẽ chữ**. Toàn bộ typography được render trong WordPress bằng GD/FreeType với font tiếng Việt được cấp phép, nên dấu tiếng Việt luôn chính xác và sắc nét.

```text
Ảnh nền đã tạo/nhập (generation record)
→ Chọn mẫu chữ
→ Xem thử (không lưu)
→ Chèn chữ
→ Attachment MỚI + generation record MỚI trạng thái generated
→ Duyệt làm ảnh đại diện như luồng bình thường
```

Ảnh gốc không bao giờ bị ghi đè. Bản chèn chữ luôn đi qua bước duyệt.

Khi bật **Tự động chèn chữ** (mặc định bật), plugin áp mẫu mặc định ngay sau mỗi lần tạo ảnh AI qua hook `nt_content_images_after_generate`: danh sách chờ duyệt có cả bản gốc lẫn bản có chữ. Lỗi chèn tự động không chặn luồng tạo ảnh — bản gốc vẫn còn để chèn tay.

## Thành phần

| File | Vai trò |
| --- | --- |
| `includes/templates/class-nt-content-images-template-registry.php` | Định nghĩa 5 mẫu overlay, filter `nt_content_images_overlay_templates` |
| `includes/templates/class-nt-content-images-template-settings.php` | Option `nt_content_images_template_settings`: mẫu mặc định + cờ hiển thị |
| `includes/templates/class-nt-content-images-overlay-renderer.php` | Vẽ bằng GD/FreeType: gradient, panel, badge, wrap chữ, logo |
| `includes/templates/class-nt-content-images-overlay-service.php` | Ghép dữ liệu bài viết + Brand Profile, preview/apply, tạo record mới |
| `includes/templates/class-nt-content-images-template-rest-controller.php` | REST endpoints |
| `assets/fonts/BeVietnamPro-*.ttf` | Font Be Vietnam Pro, giấy phép SIL OFL (kèm `OFL.txt`) |

## 5 mẫu có sẵn

1. **bottom_gradient — Dải tối phía dưới.** Gradient tối 1/3 dưới, tiêu đề trái, badge chuyên mục, dòng thương hiệu. Mặc định.
2. **left_panel — Khối màu bên trái.** Panel màu primary phủ 46% trái với viền accent, tiêu đề lớn tối đa 5 dòng.
3. **top_band — Dải thương hiệu phía trên.** Band màu primary chứa logo + tên thương hiệu và badge; tiêu đề trên gradient dưới.
4. **center_box — Khối trung tâm.** Hộp mờ bo góc giữa ảnh, chữ căn giữa.
5. **minimal_badge — Tối giản.** Không tiêu đề; chỉ badge góc trái và chip thương hiệu góc phải, giữ ảnh tự nhiên.

Kích thước chữ tự co giãn (44 → 26px) theo độ dài tiêu đề; quá dài sẽ cắt bằng dấu "…". Màu lấy từ Brand Profile (primary/secondary/accent); màu chữ trên badge tự đảo sáng/tối theo độ tương phản.

## Nguồn dữ liệu overlay

- **Tiêu đề**: `post_title` của bài viết gắn với generation record.
- **Nhãn chuyên mục**: term đầu tiên của taxonomy `category`, fallback sang taxonomy public đầu tiên của post type.
- **Logo**: `logo_attachment_id` trong Brand Profile (mặc định lấy custom logo của theme).
- **Thương hiệu/website**: `brand_name` và host của `website` trong Brand Profile.
- Cờ `overlay_enabled` trong Brand Profile phải bật; từng phần tử bật/tắt qua cấu hình mẫu chữ.
- Filter `nt_content_images_overlay_data` cho phép tùy biến dữ liệu trước khi render; `nt_content_images_overlay_fonts` cho phép thay font.

## REST API

Namespace `nt-content-images/v1`, yêu cầu cookie + nonce + `manage_options`:

```text
GET  /templates/config                      → templates, settings, trạng thái GD/font/logo
POST /generations/{id}/overlay/preview      → ảnh xem thử dạng data URI, không lưu
POST /generations/{id}/overlay              → render + lưu attachment mới + record mới
```

`template` là tham số tùy chọn; bỏ trống sẽ dùng mẫu mặc định.

## Quy tắc an toàn

- Chỉ áp dụng cho record có trạng thái `generated`, `approved` hoặc `rejected` và còn attachment.
- Record provider `template` không được chèn chữ lần nữa (tránh chữ chồng chữ); hãy áp mẫu khác từ ảnh nền gốc.
- Ảnh kết quả là attachment mới, record mới `generated`; ảnh đại diện chỉ đổi khi admin bấm duyệt.
- Không sửa `post_content`, không đụng tới bài viết.
- Hook `nt_content_images_after_overlay` bắn sau khi lưu thành công.

## Yêu cầu máy chủ

- PHP GD với FreeType (`imagettftext`). Có `imagewebp` sẽ xuất WebP, không thì PNG.
- Không cần Imagick. Không gọi API ngoài — render hoàn toàn cục bộ, không phát sinh chi phí.

## QA bắt buộc trước khi merge

1. Kích hoạt plugin 0.9.0 trên LocalWP, mở **Ảnh AI & Canva**, panel "Chèn chữ và thương hiệu theo mẫu" báo GD/font sẵn sàng.
2. Cấu hình Brand Profile: logo, màu, bật chèn chữ.
3. Với một ảnh đã tạo: Xem thử cả 5 mẫu, kiểm tra dấu tiếng Việt, xuống dòng và độ tương phản.
4. Chèn chữ một mẫu → xuất hiện record mới chờ duyệt, provider `template`, model là id mẫu.
5. Duyệt bản chèn chữ làm ảnh đại diện; xác nhận ảnh gốc còn nguyên.
6. Thử bài có tiêu đề rất dài (> 120 ký tự) và bài không có chuyên mục.
7. Tắt `overlay_enabled` trong Brand Profile → API trả lỗi rõ ràng.
8. Chạy `composer test` (có test render 5 mẫu bằng GD).

## Chưa bao gồm

- Chưa render SVG/Imagick (GD là đường chính; có thể thêm Imagick sau nếu cần chất lượng cao hơn).
- Chưa có editor kéo-thả vị trí chữ; muốn chỉnh tay dùng Canva.
- Chưa hỗ trợ template pack import/export theo lĩnh vực (đã có filter để đăng ký mẫu ngoài).
- Chưa tự chọn mẫu theo nhóm nội dung (dùng mẫu mặc định + chọn tay từng ảnh).
