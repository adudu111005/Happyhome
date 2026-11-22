<?php
require_once __DIR__ . "/../../includes/nhanvien.php";

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $current = $_POST["mat_khau_hien_tai"] ?? "";
    $new = $_POST["mat_khau_moi"] ?? "";
    $confirm = $_POST["xac_nhan_mat_khau"] ?? "";

    if ($current === "" || $new === "" || $confirm === "") {
        $error = "Vui lòng nhập đầy đủ các trường mật khẩu.";
    } elseif (strlen($new) < 6) {
        $error = "Mật khẩu mới phải có ít nhất 6 ký tự.";
    } elseif ($new !== $confirm) {
        $error = "Mật khẩu xác nhận không khớp.";
    } else {
        $maTaiKhoan = (int) $_SESSION["user_id"];
        $stmt = $conn->prepare("SELECT MatKhau FROM taikhoan WHERE MaTaiKhoan = ? LIMIT 1");
        $stmt->bind_param("i", $maTaiKhoan);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$account || !password_verify($current, $account["MatKhau"])) {
            $error = "Mật khẩu hiện tại không chính xác.";
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE taikhoan SET MatKhau = ? WHERE MaTaiKhoan = ?");
            $stmt->bind_param("si", $hash, $maTaiKhoan);
            if ($stmt->execute()) {
                $message = "Đổi mật khẩu thành công.";
            } else {
                $error = "Không thể đổi mật khẩu.";
            }
            $stmt->close();
        }
    }
}

hienThiKhungNhanVien("Đổi mật khẩu", "password");
?>
<div class="panel" style="max-width:620px">
    <?php if ($message): ?><div class="notice success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="notice error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label for="mat_khau_hien_tai">Mật khẩu hiện tại</label>
            <input class="form-control" type="password" id="mat_khau_hien_tai" name="mat_khau_hien_tai" autocomplete="current-password" required>
        </div>
        <div class="form-group">
            <label for="mat_khau_moi">Mật khẩu mới</label>
            <input class="form-control" type="password" id="mat_khau_moi" name="mat_khau_moi" autocomplete="new-password" minlength="6" required>
        </div>
        <div class="form-group">
            <label for="xac_nhan_mat_khau">Xác nhận mật khẩu mới</label>
            <input class="form-control" type="password" id="xac_nhan_mat_khau" name="xac_nhan_mat_khau" autocomplete="new-password" minlength="6" required>
        </div>
        <button class="btn" type="submit">Cập nhật mật khẩu</button>
    </form>
</div>
<?php hienThiDongKhungNhanVien(); ?>
