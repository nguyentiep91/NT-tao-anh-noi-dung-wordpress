# Profile System Test Matrix

## Môi trường

- WordPress 6.4+.
- PHP 8.1, 8.2 và 8.3.
- MySQL/MariaDB.
- Prefix mặc định và prefix tùy chỉnh.
- Website có/không có Yoast hoặc Rank Math.

## Migration và tương thích ngược

| ID | Tình huống | Kết quả mong đợi |
|---|---|---|
| P-01 | Nâng cấp từ 0.4.0 lên 0.5.0 | Không mất bảng audit/brief hoặc dữ liệu cũ |
| P-02 | Chạy `dbDelta()` nhiều lần | Không tạo bảng/cột trùng |
| P-03 | Brief cũ schema 1.0 | Vẫn đọc được; validator có thể cảnh báo legacy |
| P-04 | Prefix database tùy chỉnh | Bảng sử dụng `$wpdb->prefix` |

## Site Profile

| ID | Tình huống | Kết quả mong đợi |
|---|---|---|
| P-10 | Lưu tên website/domain/locale | Dữ liệu được sanitize và hiển thị lại đúng |
| P-11 | Bỏ chọn toàn bộ post type | Fallback về post type mặc định hợp lệ |
| P-12 | Bật custom post type public | Audit UI và query nhận post type đó |
| P-13 | Gửi post type không tồn tại | Bị loại khỏi profile |
| P-14 | Generic Rule Pack bị bỏ chọn | Hệ thống tự thêm lại `generic` |
| P-15 | Shortcode nhập dạng `[tag attr]` | Lưu thành tag sạch `tag` |
| P-16 | Heading blocklist nhiều dòng | Lưu đúng từng cụm từ |
| P-17 | Thay đổi profile | Tăng profile version và brief hiện hành thành outdated |

## Brand Profile

| ID | Tình huống | Kết quả mong đợi |
|---|---|---|
| P-20 | Lưu màu hợp lệ | Lưu màu hex đúng |
| P-21 | Màu không hợp lệ | Dùng fallback an toàn |
| P-22 | Bỏ chọn logo/website/overlay | Các cờ được lưu `false` |
| P-23 | Yêu cầu logo nhưng ID bằng 0 | Validator trả warning |
| P-24 | Đổi Brand Profile | Brief cũ thành outdated |

## Rule Pack

| ID | Tình huống | Kết quả mong đợi |
|---|---|---|
| P-30 | Chỉ bật Generic | Không sinh restriction đặc thù lĩnh vực |
| P-31 | Bật Education | Nhận diện course/education event |
| P-32 | Bật Legal | Nhận diện legal update/procedure và thêm legal restrictions |
| P-33 | Bật Procurement | Nhận diện procurement content và cấm giao diện VNEPS giả |
| P-34 | Hai pack cùng khớp | Pack có score/priority cao hơn thắng, signals giải thích được |
| P-35 | Rule pack bên thứ ba qua hook | Registry nhận pack hợp lệ |

## SEO Adapter

| ID | Tình huống | Kết quả mong đợi |
|---|---|---|
| P-40 | Không cài plugin SEO | WordPress fallback hoạt động |
| P-41 | Cài Yoast | Đọc focus keyword/title/description của Yoast |
| P-42 | Cài Rank Math | Đọc focus keyword/title/description của Rank Math |
| P-43 | Yoast metadata rỗng | Fallback bổ sung từ WordPress |
| P-44 | Không ghi SEO metadata | Audit không sửa postmeta |

## Audit và brief

| ID | Tình huống | Kết quả mong đợi |
|---|---|---|
| P-50 | Audit post type bị tắt | Không được query/xử lý |
| P-51 | Tạo brief post type bị tắt | Trả WP_Error |
| P-52 | Nội dung có protected shortcode | Placement chuyển manual review |
| P-53 | Nội dung có block phức tạp | Placement theo builder policy |
| P-54 | Brief mới | Lưu profile hash, brand hash, rule packs và mapping |
| P-55 | Profile không đổi | Generate lại cập nhật version hiện tại |
| P-56 | Profile/content đổi | Tạo brief version mới, brief cũ outdated |
| P-57 | Duyệt brief dùng profile cũ | REST trả 409 |

## Import/Export

| ID | Tình huống | Kết quả mong đợi |
|---|---|---|
| P-60 | Export JSON | Có Site/Brand Profile, không có secret |
| P-61 | Import JSON hợp lệ | Normalize theo post type/rule pack ở website đích |
| P-62 | JSON sai cấu trúc | Trả WP_Error, không ghi options |
| P-63 | JSON trên 200 KB | Bị từ chối |
| P-64 | Website đích thiếu post type | Post type không tồn tại bị loại |

## Bảo mật

| ID | Tình huống | Kết quả mong đợi |
|---|---|---|
| P-70 | Người dùng không có `manage_options` | Không lưu/import/export được |
| P-71 | Nonce sai | Request bị từ chối |
| P-72 | XSS trong tên/profile | Dữ liệu được sanitize/escape |
| P-73 | URL chứa credentials/path bất thường | Domain chỉ lưu hostname đã làm sạch |
| P-74 | Export profile | Không chứa API key, cookie, nonce hoặc token |

## Toàn vẹn dữ liệu

Sau toàn bộ test phải xác nhận:

- Không thay đổi `post_content`, `post_title`, `post_date` hoặc taxonomy.
- Không tạo/xóa Media attachment.
- Không thực thi shortcode.
- Không gọi API ngoài.
- Chỉ thay đổi bảng/options thuộc plugin.
