# AI soạn alt text & caption — phiên bản 0.14.0

## Mục tiêu

Alt text và caption do AI viết bằng tiếng Việt tự nhiên, chuẩn SEO, thay cho
chuỗi mẫu cứng ("Minh họa cho phần X", "Ảnh được tạo tự động…").

## Cách hoạt động

- Ngay khi tạo mỗi ảnh (đại diện và trong bài), plugin gọi **một model văn bản
  giá rẻ qua OpenRouter** (`caption_model`, mặc định `google/gemini-2.5-flash-lite`,
  dùng chung API key OpenRouter) với ngữ cảnh: tiêu đề bài, tiêu đề mục và
  mô tả cảnh trong ảnh (prompt tạo ảnh).
- AI trả về JSON `{alt, caption}`: alt ≤ 125 ký tự mô tả đúng ảnh có từ khóa;
  caption 1 câu thu hút, ≤ 180 ký tự, không markdown/emoji, không bịa số liệu.
- Bản chèn chữ (overlay) **kế thừa nguyên văn** alt/caption từ ảnh gốc —
  không gọi AI lần hai.
- Chi phí ~0.0001–0.001 USD/ảnh; **không tính** vào giới hạn ảnh AI/ngày.

## An toàn và fallback

- Lỗi tạm thời (429/5xx/mạng) tự thử lại một lần sau 2 giây.
- Mọi thất bại (kể cả JSON hỏng) → dùng lại alt/caption theo mẫu cũ; quy trình
  tạo ảnh và chạy hàng loạt không bao giờ bị chặn.
- Kết quả đi qua sanitize + giới hạn độ dài trước khi lưu.

## Cấu hình (Ảnh AI & Canva)

- Checkbox **"Dùng AI soạn alt text & caption"** (mặc định bật; tự bỏ qua khi
  chưa có key OpenRouter).
- Dropdown **"Model soạn alt/caption"**: tải toàn bộ model văn bản từ OpenRouter
  (~357 model, kèm giá input mỗi 1 triệu token, cache 15 phút). Chưa có key thì
  hiện ô nhập tay `provider/model`.

## Kiểm thử thật (LocalWP, 2026-07-28)

- 58/58 unit test (parse JSON/code-fence, clamp, HTTP stub, retry 429, settings).
- E2E: 2 ảnh nội dung + 1 featured nhận alt/caption AI tiếng Việt tự nhiên;
  overlay copy đúng nguyên văn; figcaption trong bài là caption AI.
