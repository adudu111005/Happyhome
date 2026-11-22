<?php
session_start();

require_once dirname(__DIR__) . "/config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["vaiTro"] ?? "") !== "NhanVien"
) {
    header("Location: /HappyHome/welcome.php");
    exit();
}

$maTaiKhoan = (int) $_SESSION["user_id"];
$stmt = $conn->prepare(
    "SELECT nv.*, tk.Email, tk.SoDienThoai, tk.TenDangNhap
     FROM nhanvien nv
     JOIN taikhoan tk ON tk.MaTaiKhoan = nv.MaTaiKhoan
     WHERE nv.MaTaiKhoan = ? LIMIT 1"
);
$stmt->bind_param("i", $maTaiKhoan);
$stmt->execute();
$nhanVien = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$nhanVien) {
    session_destroy();
    header("Location: /HappyHome/welcome.php");
    exit();
}

foreach ([
    "GhiChu" => "TEXT NULL",
    "KetQua" => "TEXT NULL",
    "MucDoUuTien" => "VARCHAR(20) NULL",
] as $column => $definition) {
    $safeColumn = $conn->real_escape_string($column);
    $exists = $conn->query("SHOW COLUMNS FROM congviec LIKE '$safeColumn'");
    if ($exists && $exists->num_rows === 0) {
        $conn->query("ALTER TABLE congviec ADD COLUMN `$column` $definition");
    }
}

$baseUrl = "/HappyHome";
$currentPage = basename($_SERVER["PHP_SELF"]);

function tenTrangThai($trangThai)
{
    return [
        "ChoXuLy" => "Chờ xử lý",
        "DangXuLy" => "Đang thực hiện",
        "HoanThanh" => "Đã hoàn thành",
        "DaHuy" => "Đã hủy",
    ][$trangThai] ?? $trangThai;
}

function lopTrangThai($trangThai)
{
    return [
        "ChoXuLy" => "pending",
        "DangXuLy" => "progress",
        "HoanThanh" => "completed",
        "DaHuy" => "cancelled",
    ][$trangThai] ?? "pending";
}

function hienThiKhungNhanVien($title, $activePage)
{
    global $baseUrl, $nhanVien;

    $roleName = $nhanVien["LoaiNhanVien"] === "KyThuat"
        ? "Nhân viên kỹ thuật"
        : "Nhân viên vệ sinh";
    $name = $nhanVien["HoTen"] ?? "Nhân viên";
    $initial = mb_strtoupper(mb_substr($name, 0, 1, "UTF-8"), "UTF-8");

    echo '<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . ' - HappyHome</title>
    <link rel="stylesheet" href="' . $baseUrl . '/assets/css/nhanvien.css">
</head>
<body>
<div class="employee-layout">
    <aside class="employee-sidebar" id="employeeSidebar">
        <div class="employee-logo">Happy<span>Home.</span></div>
        <nav class="employee-menu">
            <a href="' . $baseUrl . '/pages/quanlynhanvien/dashboard.php" class="' . ($activePage === "dashboard" ? "active" : "") . '">🏠 <span>Tổng quan</span></a>
            <a href="' . $baseUrl . '/pages/quanlynhanvien/congviec/index.php" class="' . ($activePage === "tasks" ? "active" : "") . '">🛠️ <span>Công việc</span></a>
            <a href="' . $baseUrl . '/pages/quanlynhanvien/congviec/lichsu.php" class="' . ($activePage === "history" ? "active" : "") . '">🕘 <span>Lịch sử công việc</span></a>
            <a href="' . $baseUrl . '/pages/quanlynhanvien/thongtin.php" class="' . ($activePage === "profile" ? "active" : "") . '">👤 <span>Thông tin cá nhân</span></a>
            <a href="' . $baseUrl . '/pages/quanlynhanvien/doimatkhau.php" class="' . ($activePage === "password" ? "active" : "") . '">🔒 <span>Đổi mật khẩu</span></a>
        </nav>
        <div class="sidebar-user">
            <strong>' . htmlspecialchars($name) . '</strong>
            <small>' . htmlspecialchars($roleName) . '</small>
        </div>
        <a class="sidebar-logout" href="' . $baseUrl . '/auth/logout.php">🚪 Đăng xuất</a>
    </aside>
    <main class="employee-main">
        <header class="employee-topbar">
            <div style="display:flex;align-items:center;gap:12px">
                <button class="mobile-menu" type="button" id="mobileMenu" aria-label="Mở menu">☰</button>
                <h1>' . htmlspecialchars($title) . '</h1>
            </div>
            <div class="topbar-role">' . htmlspecialchars($roleName) . '</div>
        </header>
        <section class="employee-content">';
}

function hienThiDongKhungNhanVien()
{
    echo '</section></main></div>
<script>
const menuButton = document.getElementById("mobileMenu");
const sidebar = document.getElementById("employeeSidebar");
if (menuButton) {
    menuButton.addEventListener("click", () => sidebar.classList.toggle("open"));
}
</script>
</body>
</html>';
}
