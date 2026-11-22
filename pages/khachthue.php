
<?php
session_start();

require_once "../config/database.php";

// Chỉ quản lý mới được thêm khách thuê
if (
    !isset($_SESSION['user_id']) ||
    $_SESSION['vaiTro'] != 'QuanLy'
) {
    header("Location: ../welcome.php");
    exit();
}

$thongBao = "";
$loi = "";

// Xử lý khi nhấn nút thêm
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Lấy dữ liệu từ form
    $hoTen = trim($_POST['HoTen'] ?? '');
    $ngaySinh = $_POST['NgaySinh'] ?: null;
    $gioiTinh = $_POST['GioiTinh'] ?: null;
    $soDienThoai = trim($_POST['SoDienThoai'] ?? '');
    $email = trim($_POST['Email'] ?? '') ?: null;
    $cccd = trim($_POST['CCCD'] ?? '') ?: null;
    $diaChi = trim($_POST['DiaChi'] ?? '') ?: null;

    $tenDangNhap = trim($_POST['TenDangNhap'] ?? '');
    $matKhau = $_POST['MatKhau'] ?? '';

    // Kiểm tra dữ liệu bắt buộc
    if (
        $hoTen == '' ||
        $soDienThoai == '' ||
        $tenDangNhap == '' ||
        $matKhau == ''
    ) {
        $loi = "Vui lòng nhập đầy đủ các thông tin bắt buộc!";
    }
    elseif (strlen($matKhau) < 6) {
        $loi = "Mật khẩu phải có ít nhất 6 ký tự!";
    }
    else {
        try {

            // Kiểm tra tên đăng nhập đã tồn tại chưa
            $sqlCheck = "SELECT MaTaiKhoan
                         FROM TaiKhoan
                         WHERE TenDangNhap = ?";

            $stmtCheck = $conn->prepare($sqlCheck);
            $stmtCheck->bind_param("s", $tenDangNhap);
            $stmtCheck->execute();

            $resultCheck = $stmtCheck->get_result();

            if ($resultCheck->num_rows > 0) {
                throw new Exception("Tên đăng nhập đã tồn tại!");
            }

            $stmtCheck->close();

            // Bắt đầu transaction
            $conn->begin_transaction();

            // Bước 1: Thêm thông tin khách thuê
            $sqlKhachThue = "INSERT INTO KhachThue
                (HoTen, NgaySinh, GioiTinh, SoDienThoai,
                 Email, CCCD, DiaChi)
                VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmtKhachThue = $conn->prepare($sqlKhachThue);

            $stmtKhachThue->bind_param(
                "sssssss",
                $hoTen,
                $ngaySinh,
                $gioiTinh,
                $soDienThoai,
                $email,
                $cccd,
                $diaChi
            );

            $stmtKhachThue->execute();

            // Lấy mã khách thuê vừa tạo
            $maKhachThue = $conn->insert_id;

            $stmtKhachThue->close();


            // Bước 2: Tạo tài khoản đăng nhập
            $matKhauHash = password_hash(
                $matKhau,
                PASSWORD_DEFAULT
            );

            $vaiTro = "KhachThue";
            $trangThai = "HoatDong";

            $sqlTaiKhoan = "INSERT INTO TaiKhoan
                (TenDangNhap, MatKhau, VaiTro,
                 MaKhachThue, TrangThai)
                VALUES (?, ?, ?, ?, ?)";

            $stmtTaiKhoan = $conn->prepare($sqlTaiKhoan);

            $stmtTaiKhoan->bind_param(
                "sssis",
                $tenDangNhap,
                $matKhauHash,
                $vaiTro,
                $maKhachThue,
                $trangThai
            );

            $stmtTaiKhoan->execute();

            $stmtTaiKhoan->close();

            // Hoàn tất cả hai thao tác
            $conn->commit();

            $thongBao = "Thêm khách thuê và tài khoản thành công!";

        } catch (Exception $e) {

            // Có lỗi thì hoàn tác dữ liệu
            $conn->rollback();

            $loi = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Thêm khách thuê - HappyHome</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            margin: 0;
            padding: 30px;
            color: #333;
        }

        .container {
            max-width: 850px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        h2 {
            margin-top: 0;
            color: #2563eb;
        }

        .section-title {
            margin-top: 25px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            font-size: 17px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            font-size: 14px;
            font-weight: bold;
        }

        input, select {
            padding: 11px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            width: 100%;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #2563eb;
        }

        .required {
            color: red;
        }

        .buttons {
            margin-top: 25px;
            display: flex;
            gap: 10px;
        }

        button, .back {
            padding: 11px 20px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        button {
            background: #2563eb;
            color: white;
        }

        .back {
            background: #e5e7eb;
            color: #333;
        }

        .success, .error {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        @media (max-width: 600px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h2>Thêm khách thuê</h2>

    <p>Nhập thông tin khách thuê và tạo tài khoản đăng nhập.</p>

    <?php if ($thongBao != ''): ?>
        <div class="success">
            <?php echo htmlspecialchars($thongBao); ?>
        </div>
    <?php endif; ?>

    <?php if ($loi != ''): ?>
        <div class="error">
            <?php echo htmlspecialchars($loi); ?>
        </div>
    <?php endif; ?>


    <form method="POST">

        <h3 class="section-title">Thông tin cá nhân</h3>

        <div class="form-grid">

            <div class="form-group">
                <label>Họ tên <span class="required">*</span></label>
                <input type="text" name="HoTen" required
                       value="<?php echo htmlspecialchars($_POST['HoTen'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Số điện thoại <span class="required">*</span></label>
                <input type="text" name="SoDienThoai" required
                       value="<?php echo htmlspecialchars($_POST['SoDienThoai'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Ngày sinh</label>
                <input type="date" name="NgaySinh"
                       value="<?php echo htmlspecialchars($_POST['NgaySinh'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Giới tính</label>
                <select name="GioiTinh">
                    <option value="">-- Chọn giới tính --</option>
                    <option value="Nam">Nam</option>
                    <option value="Nu">Nữ</option>
                    <option value="Khac">Khác</option>
                </select>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="Email"
                       value="<?php echo htmlspecialchars($_POST['Email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>CCCD</label>
                <input type="text" name="CCCD"
                       value="<?php echo htmlspecialchars($_POST['CCCD'] ?? ''); ?>">
            </div>

            <div class="form-group full">
                <label>Địa chỉ</label>
                <input type="text" name="DiaChi"
                       value="<?php echo htmlspecialchars($_POST['DiaChi'] ?? ''); ?>">
            </div>

        </div>


        <h3 class="section-title">Thông tin tài khoản</h3>

        <div class="form-grid">

            <div class="form-group">
                <label>Tên đăng nhập <span class="required">*</span></label>
                <input type="text" name="TenDangNhap" required
                       value="<?php echo htmlspecialchars($_POST['TenDangNhap'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Mật khẩu <span class="required">*</span></label>
                <input type="password" name="MatKhau"
                       minlength="6" required>
            </div>

        </div>

        <div class="buttons">

            <button type="submit">
                Thêm khách thuê
            </button>

            <a href="khachthue.php" class="back">
                Quay lại
            </a>

        </div>

    </form>

</div>

</body>
</html>
