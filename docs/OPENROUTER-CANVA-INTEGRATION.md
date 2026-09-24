# OpenRouter Images và Canva Connect

Phiên bản `0.7.0` mở rộng luồng tạo ảnh thực tế của plugin:

```text
Nội dung WordPress
→ Audit và Image Brief
→ OpenAI hoặc OpenRouter tạo ảnh nền
→ Media Library
→ Duyệt trực tiếp hoặc gửi sang Canva
→ Chỉnh sửa trong Canva
→ Xuất PNG về WordPress
→ Duyệt làm ảnh đại diện
```

## OpenRouter Images

Plugin dùng dedicated Image API:

- `GET https://openrouter.ai/api/v1/images/models`
- `POST https://openrouter.ai/api/v1/images`

Model được lấy động từ OpenRouter và lưu transient 15 phút. Generation chỉ gửi một ảnh mỗi thao tác. Plugin chỉ chấp nhận ảnh raster PNG, JPEG hoặc WebP và từ chối SVG trong MVP.

### Cấu hình production

```php
define( 'NT_CONTENT_IMAGES_OPENROUTER_API_KEY', 'sk-or-v1-...' );
```

Hoặc nhập API key tại **NT – Tạo ảnh nội dung → Tạo ảnh AI**. Key không được đưa xuống JavaScript hoặc lưu trong generation record.

## Canva Connect

Canva được dùng để chỉnh sửa ảnh đã tạo, không thay thế AI provider.

Workflow:

1. Upload WordPress attachment sang Canva bằng binary asset upload.
2. Chờ upload job hoàn tất.
3. Tạo custom design 1280 × 720 có asset vừa upload.
4. Mở temporary edit URL trong Canva.
5. Khi chỉnh xong, tạo PNG export job.
6. Tải PNG về WordPress.
7. Tạo generation record mới ở trạng thái `generated` để duyệt.

Bản Canva nhập về không ghi đè attachment gốc và không tự động đổi ảnh đại diện.

### Scopes tối thiểu

```text
asset:read
asset:write
design:content:read
design:content:write
```

### OAuth

Plugin dùng OAuth 2.0 Authorization Code + PKCE SHA-256:

- Authorization: `https://www.canva.com/api/oauth/authorize`
- Token: `https://api.canva.com/rest/v1/oauth/token`
- State và code verifier được giữ trong transient 15 phút.
- Access token và refresh token chỉ được lưu ở backend WordPress, option không autoload.
- Refresh token được thay mới khi Canva cấp token mới.

### Cấu hình production

```php
define( 'NT_CONTENT_IMAGES_CANVA_CLIENT_ID', '...' );
define( 'NT_CONTENT_IMAGES_CANVA_CLIENT_SECRET', '...' );
define(
    'NT_CONTENT_IMAGES_CANVA_REDIRECT_URI',
    'https://example.com/wp-admin/admin-post.php?action=nt_content_images_canva_callback'
);
```

Redirect URI phải khớp chính xác với URI đã khai báo trong Canva Developer Portal.

### LocalWP

Canva không chấp nhận `localhost` làm redirect URL. Có thể dùng:

- `http://127.0.0.1:<port>` khi cấu trúc LocalWP cho phép callback ổn định; hoặc
- LocalWP Live Link/tunnel HTTPS; hoặc
- staging domain kiểm thử.

Khai báo URI công khai bằng `NT_CONTENT_IMAGES_CANVA_REDIRECT_URI` khi WordPress Admin đang chạy trên URL local khác URI callback.

## An toàn

- Không gửi API key hoặc Canva token xuống JavaScript.
- Không log credential.
- Không lưu base64 ảnh trong database.
- Không tự động tạo ảnh hàng loạt.
- Không ghi đè featured image hiện có.
- Không sửa `post_content`.
- Canva export được nhập thành attachment mới và tiếp tục qua bước duyệt.
- Uninstall chỉ xóa credential/token khi quản trị viên đã bật chế độ xóa dữ liệu plugin.

## QA bắt buộc

### OpenRouter

1. Chọn OpenRouter.
2. Lưu API key.
3. Tải danh sách model ảnh.
4. Chọn một model raster.
5. Tạo đúng một ảnh thử.
6. Kiểm tra Media Library, provider/model, prompt và trạng thái `generated`.
7. Duyệt ảnh và kiểm tra featured image.

### Canva

1. Tạo integration trong Canva Developer Portal.
2. Bật đúng bốn scope tối thiểu.
3. Khai báo redirect URI chính xác.
4. Kết nối tài khoản Canva.
5. Gửi một ảnh đã tạo sang Canva.
6. Mở thiết kế và chỉnh sửa.
7. Nhập bản Canva về WordPress.
8. Xác nhận attachment mới và generation mới đang chờ duyệt.
9. Duyệt bản Canva và kiểm tra featured image.

Không merge vào `develop` trước khi cả OpenRouter generation và Canva round-trip đã được thử thật với credential hợp lệ.
