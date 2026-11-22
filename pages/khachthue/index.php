
<?php
session_start();

// Kiểm tra đăng nhập
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['vaiTro']) ||
    $_SESSION['vaiTro'] != 'KhachThue'
) {
    header("Location: ../../welcome.php");
    exit();
}

$tenDangNhap = $_SESSION['ten_dang_nhap'] ?? 'Khách thuê';

// Danh sách chức năng
$menu = [
    [
        "ten" => "Trang chủ",
        "icon" => "🏠",
        "link" => "index.php"
    ],
    [
        "ten" => "Tìm kiếm phòng",
        "icon" => "🔍",
        "link" => "phong.php"
    ],
    [
        "ten" => "Hợp đồng của tôi",
        "icon" => "📄",
        "link" => "hopdong.php"
    ],
    [
        "ten" => "Hóa đơn và thanh toán",
        "icon" => "🧾",
        "link" => "hoadon.php"
    ],
    [
        "ten" => "Kiến nghị / Báo sự cố",
        "icon" => "📩",
        "link" => "kiennghi.php"
    ],
    [
        "ten" => "Thông tin cá nhân",
        "icon" => "👤",
        "link" => "thongtin.php"
    ]
];
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>HappyHome - Trang chủ khách thuê</title>

    <link rel="stylesheet" href="../../assets/css/khachthue.css">
</head>

<body>

<div class="container">

    <!-- SIDEBAR -->
    <aside class="sidebar">

        <div class="logo">
            Happy<span>Home</span>
        </div>

        <div class="menu-title">
            KHÁCH THUÊ
        </div>

        <nav class="menu">

            <?php foreach ($menu as $item): ?>

                <a href="<?php echo htmlspecialchars($item['link']); ?>"
                   class="<?php echo $item['link'] == 'index.php' ? 'active' : ''; ?>">

                    <span class="menu-icon">
                        <?php echo $item['icon']; ?>
                    </span>

                    <?php echo htmlspecialchars($item['ten']); ?>

                </a>

            <?php endforeach; ?>

        </nav>

        <div class="sidebar-bottom">
            <a href="../../auth/logout.php">
                <span>🚪</span>
                Đăng xuất
            </a>
        </div>

    </aside>


    <!-- MAIN -->
    <main class="main">

        <!-- TOPBAR -->
        <header class="topbar">

            <div>
                <h2>Trang chủ</h2>
                <p>Chào mừng bạn đến với HappyHome</p>
            </div>

            <div class="user-info">
                <div class="avatar">
                    <?php echo strtoupper(substr($tenDangNhap, 0, 1)); ?>
                </div>

                <div>
                    <strong>
                        <?php echo htmlspecialchars($tenDangNhap); ?>
                    </strong>
                    <small>Khách thuê</small>
                </div>
            </div>

        </header>


        <!-- CONTENT -->
        <section class="content">

            <!-- WELCOME -->
            <div class="welcome-box">

                <div>
                    <h1>Xin chào, <?php echo htmlspecialchars($tenDangNhap); ?>!</h1>

                    <p>
                        Chào mừng bạn đến với hệ thống quản lý nhà trọ HappyHome.
                        Tại đây, bạn có thể theo dõi thông tin thuê phòng,
                        hợp đồng và các khoản thanh toán.
                    </p>
                </div>

                <div class="welcome-icon">
                    🏡
                </div>

            </div>


            <!-- QUICK FUNCTIONS -->
            <h3 class="section-title">Các chức năng của bạn</h3>

            <div class="function-grid">

                <a href="phong.php" class="function-card">

                    <div class="card-icon blue">
                        🔍
                    </div>

                    <h3>Tìm kiếm phòng</h3>

                    <p>
                        Xem thông tin và tìm kiếm phòng trọ phù hợp.
                    </p>

                    <span class="card-link">Xem chi tiết →</span>

                </a>


                <a href="hopdong.php" class="function-card">

                    <div class="card-icon green">
                        📄
                    </div>

                    <h3>Hợp đồng của tôi</h3>

                    <p>
                        Theo dõi thông tin và thời hạn hợp đồng thuê.
                    </p>

                    <span class="card-link">Xem chi tiết →</span>

                </a>


                <a href="hoadon.php" class="function-card">

                    <div class="card-icon orange">
                        🧾
                    </div>

                    <h3>Hóa đơn và thanh toán</h3>

                    <p>
                        Kiểm tra hóa đơn và lịch sử thanh toán.
                    </p>

                    <span class="card-link">Xem chi tiết →</span>

                </a>


                <a href="kiennghi.php" class="function-card">

                    <div class="card-icon purple">
                        📩
                    </div>

                    <h3>Kiến nghị / Báo sự cố</h3>

                    <p>
                        Gửi phản hồi hoặc báo cáo vấn đề trong quá trình thuê.
                    </p>

                    <span class="card-link">Gửi phản hồi →</span>

                </a>


                <a href="thongtin.php" class="function-card">

                    <div class="card-icon gray">
                        👤
                    </div>

                    <h3>Thông tin cá nhân</h3>

                    <p>
                        Xem và quản lý thông tin tài khoản của bạn.
                    </p>

                    <span class="card-link">Xem chi tiết →</span>

                </a>

            </div>


            <!-- NOTICE -->
            <div class="notice-box">

                <div class="notice-icon">ℹ️</div>

                <div>
                    <h3>Lưu ý dành cho khách thuê</h3>

                    <p>
                        Vui lòng kiểm tra hóa đơn và thanh toán đúng thời hạn.
                        Nếu gặp vấn đề về phòng hoặc dịch vụ, bạn có thể gửi
                        kiến nghị để nhân viên HappyHome hỗ trợ.
                    </p>
                </div>

            </div>

        </section>


        <!-- FOOTER -->
        <footer class="footer">
            © 2026 HappyHome - Hệ thống quản lý nhà trọ
        </footer>

    </main>

</div>

</body>
</html>
