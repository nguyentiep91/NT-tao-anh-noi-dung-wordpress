# Kế hoạch ảnh tuỳ chỉnh (0.16.0)

Hai lớp tuỳ chỉnh cho kế hoạch hình ảnh, tách bạch với brief tự động:

1. **Quy tắc chung** — số ảnh trong bài theo độ dài nội dung, cấu hình tại trang **Kế hoạch hình ảnh**.
2. **Tuỳ chỉnh từng bài** — chỉnh từng vị trí ảnh ngay trong chi tiết **Ảnh trong bài**.

## 1. Quy tắc số ảnh trong bài

- Panel "Quy tắc số ảnh trong bài" trên trang Kế hoạch hình ảnh, lưu qua `admin-post.php`
  (action `nt_content_images_save_plan_settings`, nonce + `manage_options`).
- Option `nt_content_images_plan_settings`, class `NT_Content_Images_Plan_Settings`:
  - `threshold_small` (mặc định 1500 từ) / `threshold_large` (3000 từ);
  - `count_small` / `count_medium` / `count_large` (1/2/3 ảnh) — cho phép 0;
  - `max_images` (trần mỗi bài, mặc định 3) và `special_max` (trần cho khóa học/dịch vụ/sự kiện, mặc định 2);
  - trần cứng 5 ảnh/bài; lưu thất bại với `ntci_plan_thresholds_invalid` nếu ngưỡng lớn ≤ ngưỡng nhỏ.
- `NT_Content_Images_Brief_Generator::target_content_image_count()` dùng service này; filter
  `nt_content_images_target_content_image_count` vẫn được áp cuối cùng.
- Quy tắc mới chỉ áp vào brief tạo/tạo lại sau đó — bài đã có brief giữ nguyên tới khi tạo lại.

## 2. Tuỳ chỉnh từng bài (per-post overrides)

- Postmeta `_ntci_plan_overrides` (JSON), class `NT_Content_Images_Plan_Overrides` — tách khỏi brief
  nên tạo lại brief hay đổi quy tắc chung **không** xoá tuỳ chỉnh tay.
- Mỗi slot tự động (index 1..N) nhận patch: `enabled` (bật/tắt), `heading_text` (đổi mục chèn,
  phải là H2/H3 đang có trong bài — sai trả `ntci_plan_heading_unknown`), `custom_scene`
  (mô tả cảnh gửi AI, tối đa 1000 ký tự; rỗng = quay về mô tả tự động).
- Slot thêm tay dùng index từ 101 để không va chạm khi brief đổi số lượng; trần cứng 5 ảnh
  (`ntci_plan_cap_reached`). Xoá bằng `remove_extra`, xoá toàn bộ tuỳ chỉnh bằng `reset`.
- `apply()` (pure static, có unit test) trộn overrides vào plan: heading override sinh placement
  `before_heading` + `safe_candidate`; slot có patch được gắn `user_modified`, slot thêm tay gắn `is_extra`.
- Vị trí bị tắt bị bỏ qua ở **cả ba** chỗ: tạo ảnh (`disabled_by_user` trong skipped), duyệt hàng loạt
  trong queue (`step_insert`), và chèn (`NT_Content_Images_Plan_Overrides::disabled_indexes()` trong
  `NT_Content_Images_Content_Inserter::insert()`) — kể cả khi ảnh đã được duyệt trước đó.
- `custom_scene` thay phần mô tả cảnh trong prompt ("Scene requested by the site editor…") nhưng giữ
  nguyên khung kỹ thuật (chống nét AI, 16:9, cấm chữ, restrictions) trong
  `NT_Content_Images_Content_Prompt_Builder`.

## REST

`POST /nt-content-images/v1/content-images/plan-override` (cookie + nonce + `manage_options`) —
một endpoint cho mọi thao tác, trả về plan mới sau khi áp:

| Tham số | Ý nghĩa |
| --- | --- |
| `post_id` | Bắt buộc. |
| `index` + `enabled`/`heading_text`/`custom_scene` | Patch một slot (patch rỗng → `ntci_plan_patch_empty`). |
| `add_heading` | Thêm vị trí ảnh tại một H2/H3 chưa dùng. |
| `remove_extra` | Xoá slot thêm tay theo index. |
| `reset` | Xoá mọi tuỳ chỉnh, về kế hoạch tự động. |

`GET /content-images/plan` trả thêm `headings` (H2/H3 của bài), `has_overrides`, và mỗi slot có
`enabled`, `custom_scene`, `user_modified`, `is_extra`.

## Giao diện

Chi tiết bài trong **Ảnh trong bài** (`content-admin.js`): mỗi slot có dropdown "Vị trí chèn"
(H2/H3 của bài), textarea "Mô tả cảnh cho AI" + nút Lưu mô tả, nút Tắt/Bật vị trí, nút Xoá vị trí
(slot thêm tay); cuối danh sách có "Thêm ảnh tại mục…" và "Xoá mọi tuỳ chỉnh, về kế hoạch tự động".
Slot tắt hiển thị mờ với ghi chú; slot đã sửa gắn nhãn "✎ đã tuỳ chỉnh".

## Kiểm thử

- Unit (PHPUnit, không cần WordPress): `PlanSettingsTest` (ma trận image_count, loại đặc biệt, số 0,
  trần, ngưỡng sai) và `PlanOverridesTest` (apply/merge, roundtrip tiếng Việt qua wp_slash,
  add/remove extra, disabled_indexes, list_headings).
- E2E trên LocalWP (0.16.0): đổi quy tắc → brief tạo lại đúng số slot; toàn bộ thao tác REST;
  tạo ảnh thật (FLUX) với custom scene — prompt chứa cảnh tuỳ chỉnh + khung kỹ thuật, record đặt
  placement theo heading đã đổi; slot tắt bị bỏ qua khi tạo lẫn khi chèn; bật lại chèn đúng ngay
  trước H2 đã override.
