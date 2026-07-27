# Dọn ảnh không dùng và chống trùng lặp — phiên bản 0.12.0

## Mục tiêu

Media Library chỉ giữ ảnh **thật sự được dùng**: ảnh đại diện đã duyệt và ảnh đã chèn vào bài. Mọi ảnh trung gian của quy trình tạo ảnh được tự động dọn, không tích rác.

Trước 0.12.0, mỗi ảnh dùng thật kéo theo trung bình ~10 file rác (bản gốc chưa chèn chữ, ứng viên bị loại, ảnh lỗi). Đo thực tế trên site: 55 attachment nhưng chỉ 4 ảnh được dùng — 46 ảnh rác chiếm 18 MB.

## Thành phần

| File | Vai trò |
| --- | --- |
| `includes/media/class-nt-content-images-media-cleanup.php` | Service dọn rác: 4 hook lifecycle + sweep toàn bộ |
| `includes/media/class-nt-content-images-media-manager.php` | Chống trùng lặp bằng hash SHA-256 (`_ntci_bytes_hash`) |
| REST `POST /content-images/cleanup` | Quét và xoá toàn bộ ảnh không dùng |
| Nút **Dọn ảnh không dùng** (trang Ảnh trong bài) | Gọi sweep, báo số ảnh/MB đã dọn |
| Checkbox **Tự động dọn ảnh trung gian** (Ảnh AI & Canva) | Bật/tắt auto cleanup (`auto_cleanup`, mặc định BẬT) |

## Tự dọn theo quy trình (auto cleanup)

| Sự kiện | Hành vi dọn |
| --- | --- |
| Chèn chữ xong (`after_overlay`) | Xoá ảnh nền gốc chưa chữ + record của nó (bản có chữ thay thế hoàn toàn) |
| Từ chối ảnh (`after_reject`) | Xoá luôn attachment + record vừa bị từ chối |
| Chèn ảnh vào bài (`after_insert`) | Xoá các ứng viên thừa của những slot vừa được chèn |
| Duyệt ảnh đại diện (`after_featured_approval`) | Xoá các ứng viên featured thua cuộc của bài đó |

## Quy tắc an toàn (mọi đường xoá đều qua `delete_record_if_unused`)

1. **Không bao giờ xoá ảnh đang dùng**: đang là thumbnail của bất kỳ bài nào, hoặc xuất hiện trong `post_content` bất kỳ bài nào (khớp `wp-image-{id}`, block JSON `"id":{id}`, tên file gốc và tên từng bản resize từ attachment metadata — khớp chính xác, không nhầm file anh em cùng tiền tố).
2. **Chỉ xoá ảnh plugin tạo** (có meta `_nt_content_images_source_post_id`); ảnh quản trị viên tự tải lên không bao giờ bị đụng tới.
3. **Attachment dùng chung** bởi record khác (do dedup) chỉ xoá record, giữ file.
4. Ảnh trong bài ở thùng rác vẫn được tính là đang dùng (khôi phục bài không vỡ ảnh).

## Sweep thủ công

Nút **Dọn ảnh không dùng** xoá *mọi* ảnh plugin chưa dùng bất kể trạng thái — gồm cả ảnh đang chờ duyệt (hộp thoại xác nhận nói rõ). Dùng khi muốn đưa Media Library về đúng trạng thái "chỉ ảnh sử dụng".

## Chống trùng lặp

Khi lưu ảnh, plugin hash `post_id + kích thước đích + byte ảnh`. Nếu Media Library đã có attachment y hệt (meta `_ntci_bytes_hash`) thì tái sử dụng, không tạo file mới — ví dụ bấm "Chèn chữ" hai lần cùng một mẫu.

## Kết quả kiểm thử thật (LocalWP, 2026-07-27)

- E2E 14/14: overlay tự xoá bản gốc; reject tự xoá; dedup tái sử dụng attachment; sweep dọn 43 file (21 MB) trong một lần chạy.
- Media Library: 55 → 11 attachment; 0 record rác còn lại; logo thương hiệu, ảnh tự upload, 3 ảnh đã chèn và 1 featured đã duyệt còn nguyên vẹn.
- Unit test 51/51 (needle matching, settings auto_cleanup).

## Giới hạn hiện tại

- Ảnh được nhúng thủ công vào widget/template PHP (ngoài `post_content`) không được nhận diện là "đang dùng" — trường hợp này không xảy ra với ảnh plugin tạo theo quy trình chuẩn.
- Sweep chạy trong một request; với site rất lớn (>1000 record) nên chạy nhiều lần.
