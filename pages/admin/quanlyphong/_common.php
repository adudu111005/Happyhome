<?php
// Dùng chung cho các trang Quản lý phòng (index.php, thongtin.php).
// Chỉ Quản lý / Admin được truy cập.

session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['vaiTro']) || !is_string($_SESSION['vaiTro'])) {
    header('Location: /HappyHome/welcome.php');
    exit();
}

$vaiTro = (string) $_SESSION['vaiTro'];
if (!in_array($vaiTro, array('QuanLy', 'Admin'), true)) {
    header('Location: /HappyHome/welcome.php');
    exit();
}

ob_start();
require_once __DIR__ . '/../../../config/database.php';
ob_end_clean();
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$tenDangNhap = isset($_SESSION['ten_dang_nhap']) ? (string) $_SESSION['ten_dang_nhap'] : '';

$escape = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$postString = function ($key) {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : '';
};

// CSRF
if (!isset($_SESSION['csrf_quanlyphong'])) {
    $_SESSION['csrf_quanlyphong'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_quanlyphong'];
$checkCsrf = function () use ($csrfToken) {
    $submitted = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    return hash_equals($csrfToken, $submitted);
};

// Thông báo flash
$message = isset($_SESSION['phong_message']) ? $_SESSION['phong_message'] : null;
unset($_SESSION['phong_message']);
$setMessage = function ($type, $text) {
    $_SESSION['phong_message'] = array('type' => $type, 'text' => $text);
};

// Bảng `phong` ban đầu chưa có trạng thái "Ngừng cho thuê": bổ sung nếu thiếu.
try {
    $col = $conn->query("SHOW COLUMNS FROM phong LIKE 'TrangThai'");
    $colInfo = $col ? $col->fetch_assoc() : null;
    if ($colInfo && strpos((string) $colInfo['Type'], 'NgungChoThue') === false) {
        $conn->query(
            "ALTER TABLE phong MODIFY TrangThai
             ENUM('Trong','DaCoNguoiO','DangDonDep','DangSuaChua','NgungChoThue')
             COLLATE utf8mb4_unicode_ci DEFAULT 'Trong'"
        );
    }
} catch (mysqli_sql_exception $e) {
    error_log('Không thể cập nhật cột TrangThai của bảng phong: ' . $e->getMessage());
}

$loaiPhongLabels = array('PhongTro' => 'Phòng trọ', 'SleepBox' => 'Sleep box');
$trangThaiLabels = array(
    'Trong' => 'Trống',
    'DaCoNguoiO' => 'Đã có người ở',
    'DangDonDep' => 'Đang dọn dẹp',
    'DangSuaChua' => 'Đang sửa chữa',
    'NgungChoThue' => 'Ngừng cho thuê'
);
$trangThaiClass = array(
    'Trong' => 'st-empty',
    'DaCoNguoiO' => 'st-occupied',
    'DangDonDep' => 'st-cleaning',
    'DangSuaChua' => 'st-repair',
    'NgungChoThue' => 'st-stopped'
);

// Số hợp đồng còn hiệu lực / đang chờ duyệt của một phòng
$countActiveContracts = function ($roomId) use ($conn) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS n FROM hopdong
         WHERE MaPhong = ? AND TrangThai IN ('HieuLuc', 'ChoDuyet')"
    );
    $stmt->bind_param('i', $roomId);
    $stmt->execute();
    $n = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();
    return $n;
};

$menu = array(
    array('icon' => '🏠', 'name' => 'Tổng quan', 'url' => '/HappyHome/pages/admin/index.php'),
    array('icon' => '🚪', 'name' => 'Quản lý phòng', 'url' => '/HappyHome/pages/admin/quanlyphong/index.php'),
    array('icon' => '👥', 'name' => 'Khách thuê', 'url' => '/HappyHome/pages/admin/quanlykhachthue/index.php'),
    array('icon' => '👨‍💼', 'name' => 'Nhân viên', 'url' => '/HappyHome/pages/admin/quanlynhanvien/index.php'),
    array('icon' => '📄', 'name' => 'Hợp đồng', 'url' => '/HappyHome/pages/admin/quanlykhachthue/hopdong.php'),
    array('icon' => '🧾', 'name' => 'Hóa đơn', 'url' => '/HappyHome/pages/admin/quanlytaichinh/hoadon.php'),
    array('icon' => '💰', 'name' => 'Thu chi', 'url' => '/HappyHome/pages/admin/quanlytaichinh/thuchi.php'),
    array('icon' => '🛠️', 'name' => 'Công việc', 'url' => '/HappyHome/pages/admin/quanlycongviec/index.php'),
    array('icon' => '📩', 'name' => 'Kiến nghị', 'url' => '/HappyHome/pages/admin/quanlykhachthue/kiennghi.php')
);

// CSS dùng chung cho các trang phòng
$roomCss = <<<'CSS'
.room-page, .room-page *, .room-content, .room-content * { box-sizing: border-box; }
.room-page { max-width: 1440px; margin: 0 auto; }
.room-heading { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin: 6px 0 22px; }
.room-heading h1 { color: #1e4e79; font-size: 25px; margin-bottom: 6px; }
.room-heading p { color: #777; font-size: 14px; }
.room-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 40px; padding: 9px 16px; border: 1px solid transparent; border-radius: 6px; background: #1e4e79; color: #fff; text-decoration: none; font-size: 14px; font-weight: 700; white-space: nowrap; cursor: pointer; }
.room-button:hover { background: #286090; }
.room-button.secondary { background: #fff; border-color: #d5dce5; color: #344054; }
.room-button.secondary:hover { background: #f7f9fb; }
.room-stats { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
.room-stat { background: #fff; border: 1px solid #e3e8ee; border-radius: 8px; padding: 16px 18px; box-shadow: 0 2px 8px rgba(27, 55, 80, .04); }
.room-stat-label { color: #667085; font-size: 13px; }
.room-stat-value { color: #1e4e79; font-size: 26px; font-weight: 700; margin-top: 6px; }
.room-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 14px; background: #fff; border: 1px solid #e3e8ee; border-radius: 8px 8px 0 0; padding: 16px; }
.room-toolbar h2 { color: #27364b; font-size: 17px; }
.room-filters { display: flex; flex-wrap: wrap; gap: 9px; }
.room-filters input, .room-filters select { min-height: 40px; padding: 8px 11px; border: 1px solid #d5dce5; border-radius: 6px; background: #fff; color: #344054; font-size: 14px; }
.room-filters input { width: min(260px, 44vw); }
.room-filters button, .room-reset { min-height: 40px; padding: 8px 13px; border: 1px solid #d5dce5; border-radius: 6px; background: #f8fafc; color: #344054; text-decoration: none; font-size: 14px; cursor: pointer; display: inline-flex; align-items: center; }
.room-filters button:hover, .room-reset:hover { background: #eef4f8; }
.room-table-wrap { overflow-x: auto; background: #fff; border: 1px solid #e3e8ee; border-top: 0; border-radius: 0 0 8px 8px; }
.room-table { width: 100%; border-collapse: collapse; min-width: 900px; }
.room-table th, .room-table td { padding: 13px 15px; border-bottom: 1px solid #edf0f3; text-align: left; vertical-align: middle; font-size: 13px; }
.room-table th { background: #f8fafc; color: #667085; font-size: 12px; font-weight: 700; white-space: nowrap; }
.room-table tbody tr:hover { background: #fbfdff; }
.room-table tbody tr:last-child td { border-bottom: 0; }
.room-table tr.is-stopped td { color: #98a2b3; }
.room-name { color: #26384b; font-size: 14px; font-weight: 700; }
.room-id { display: block; color: #98a2b3; font-size: 11px; margin-top: 4px; }
.room-desc { max-width: 260px; color: #667085; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.room-status { display: inline-block; padding: 5px 9px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
.st-empty { background: #e8f7ee; color: #21834a; }
.st-occupied { background: #e8f1fb; color: #1e5fa8; }
.st-cleaning { background: #fff4e0; color: #a35f00; }
.st-repair { background: #fdeceb; color: #b42318; }
.st-stopped { background: #f1f3f5; color: #667085; }
.room-actions { display: flex; gap: 12px; align-items: center; white-space: nowrap; }
.room-actions form { display: inline; }
.room-actions a, .room-actions button { padding: 0; border: 0; background: none; color: #1e4e79; font: inherit; font-size: 12px; text-decoration: none; cursor: pointer; }
.room-actions a:hover, .room-actions button:hover { text-decoration: underline; }
.room-actions .room-danger { color: #c0392b; }
.room-empty, .room-error { padding: 30px 16px; color: #667085; text-align: center; }
.room-error { color: #b42318; background: #fff6f5; }
.room-pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 15px 16px; background: #fff; border: 1px solid #e3e8ee; border-top: 0; border-radius: 0 0 8px 8px; color: #667085; font-size: 13px; }
.room-pages { display: flex; gap: 6px; }
.room-pages a, .room-pages span { min-width: 34px; padding: 8px 10px; border: 1px solid #e0e5eb; border-radius: 5px; color: #344054; text-align: center; text-decoration: none; }
.room-pages .current { background: #1e4e79; border-color: #1e4e79; color: #fff; }
.room-alert { padding: 12px 15px; margin-bottom: 15px; border: 1px solid; border-radius: 6px; font-size: 14px; line-height: 1.55; }
.room-alert.error { background: #fff5f4; border-color: #f3c6c2; color: #a1261c; }
.room-alert.success { background: #effaf3; border-color: #b6e2c4; color: #18713c; }
.room-alert.warning { background: #fff9e9; border-color: #f0dda6; color: #775b0b; }
.room-alert ul { margin: 0; padding-left: 20px; }
.room-card { background: #fff; border: 1px solid #e2e8ef; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(27, 55, 80, .04); }
.room-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px 20px; }
.room-field { display: flex; flex-direction: column; gap: 7px; min-width: 0; }
.room-field.full { grid-column: 1 / -1; }
.room-field label { color: #344054; font-size: 13px; font-weight: 700; }
.room-field input, .room-field select, .room-field textarea { width: 100%; min-height: 42px; padding: 10px 12px; border: 1px solid #d5dce5; border-radius: 6px; background: #fff; color: #27364b; font-size: 14px; }
.room-field textarea { min-height: 96px; resize: vertical; }
.room-field input:focus, .room-field select:focus, .room-field textarea:focus { outline: 0; border-color: #1e4e79; box-shadow: 0 0 0 3px rgba(30, 78, 121, .12); }
.room-field input:disabled, .room-field select:disabled { background: #f3f5f7; color: #7a8492; }
.room-help { color: #7a8492; font-size: 12px; }
.room-form-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 8px; }
.room-note { margin-top: 18px; padding: 13px 15px; border-radius: 6px; background: #f5f8fb; color: #586779; font-size: 13px; line-height: 1.6; }
@media (max-width: 900px) { .room-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 760px) {
    .room-heading, .room-toolbar { align-items: stretch; flex-direction: column; }
    .room-heading .room-button { align-self: flex-start; }
    .room-filters { display: grid; grid-template-columns: 1fr 1fr; }
    .room-filters input { width: 100%; grid-column: 1 / -1; }
    .room-pagination { align-items: flex-start; flex-direction: column; }
    .room-card { padding: 17px; }
    .room-form { grid-template-columns: 1fr; }
    .room-field.full { grid-column: auto; }
}
CSS;
