# Template Pack theo website/lĩnh vực — 0.17.0

Template Pack là lớp chiến lược nằm trên 9 bố cục renderer hiện có. Pack không tạo renderer mới; mỗi pack chọn pool bố cục riêng cho ảnh đại diện và ảnh minh hoạ trong bài.

## 9 pack tích hợp

- corporate — doanh nghiệp, B2B, tư vấn, đấu thầu.
- education — giáo dục, đào tạo, khóa học.
- real_estate — bất động sản, property, proptech.
- certification — ISO, chứng nhận, tiêu chuẩn, compliance.
- legal — pháp lý, luật, quy định.
- news — tin tức, media, editorial.
- technology — công nghệ, software, AI, SaaS.
- minimal — website tối giản, ưu tiên ảnh sạch.
- luxury — premium, nội thất, hospitality, high-end.

## Chế độ Smart

Nếu chọn Tự động theo hồ sơ website, resolver chấm điểm theo Site Profile industries (+6/match), active_rule_packs (+8/match) và Brand Profile template_family (+2/match). Site/Rule Pack vì vậy được ưu tiên hơn template_family=corporate mặc định.

Nếu quản trị viên chọn một pack cụ thể, pack đó luôn ghi đè tự động.

Smart mode dùng deterministic rotation: (post_id + slot) mod pool_size. Tạo lại cùng ảnh vẫn ra đúng bố cục cũ.

## Tương thích

Fixed và Mix giữ nguyên hành vi cũ. Smart chỉ hoạt động khi quản trị viên chủ động chọn. Không migration database và không thay đổi renderer, Media Library, approval hay rollback.

## Mở rộng

- nt_content_images_template_packs: thêm/sửa pack.
- nt_content_images_resolved_template_pack: thay đổi pack sau bước chấm điểm.
