<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['vaiTro'])) {
    header('Location: /HappyHome/welcome.php');
    exit();
}

$vaiTro = (string) $_SESSION['vaiTro'];
if (!in_array($vaiTro, array('QuanLy', 'Admin'), true)) {
    header('Location: /HappyHome/welcome.php');
    exit();
}

require_once __DIR__ . '/../../../config/database.php';

$tenDangNhap = isset($_SESSION['ten_dang_nhap']) ? (string) $_SESSION['ten_dang_nhap'] : '';
$escape = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$search = trim(isset($_GET['q']) ? (string) $_GET['q'] : '');
$statusFilter = isset($_GET['status']) ? (string) $_GET['status'] : 'all';
if (!in_array($statusFilter, array('all', 'renting', 'returned'), true)) {
    $statusFilter = 'all';
}

$pageSize = 10;
$currentPage = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
$currentPage = ($currentPage === false || $currentPage < 1) ? 1 : $currentPage;
$offset = 0;
$totalCustomers = 0;
$rentingCustomers = 0;
$returnedCustomers = 0;
$totalPages = 1;
$customers = array();
$loadError = false;

try {
    $statsStmt = $conn->prepare(
        "SELECT
            COUNT(DISTINCT kt.MaKhachThue) AS TongKhach,
            COUNT(DISTINCT CASE
                WHEN hd.MaKhachThue IS NOT NULL THEN kt.MaKhachThue
            END) AS DangThue
         FROM khachthue kt
         LEFT JOIN (
            SELECT DISTINCT MaKhachThue
            FROM hopdong
            WHERE TrangThai = 'HieuLuc'
         ) hd ON hd.MaKhachThue = kt.MaKhachThue"
    );
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();
    $statsStmt->close();

    $totalCustomers = (int) $stats['TongKhach'];
    $rentingCustomers = (int) $stats['DangThue'];
    $returnedCustomers = max(0, $totalCustomers - $rentingCustomers);

    $searchPattern = '%' . $search . '%';
    $activeFilter = $statusFilter === 'renting' ? 1 : 0;
    $filterSql = "
        WHERE (? = '' OR kt.HoTen LIKE ? OR
            COALESCE(tk.SoDienThoai, '') LIKE ? OR
            COALESCE(kt.CCCD, '') LIKE ?)
          AND (? = 'all' OR EXISTS (
              SELECT 1
              FROM hopdong hd_filter
              WHERE hd_filter.MaKhachThue = kt.MaKhachThue
                AND hd_filter.TrangThai = 'HieuLuc'
          ) = ?)
    ";

    $countStmt = $conn->prepare(
        "SELECT COUNT(*) AS TongSo
         FROM khachthue kt
         LEFT JOIN taikhoan tk ON tk.MaTaiKhoan = kt.MaTaiKhoan
         $filterSql"
    );
    $countStmt->bind_param(
        'sssssi',
        $search,
        $searchPattern,
        $searchPattern,
        $searchPattern,
        $statusFilter,
        $activeFilter
    );
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalFiltered = (int) $countResult->fetch_assoc()['TongSo'];
    $countStmt->close();

    $totalPages = max(1, (int) ceil($totalFiltered / $pageSize));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $pageSize;

    $listStmt = $conn->prepare(
        "SELECT
            kt.MaKhachThue,
            kt.HoTen,
            kt.CCCD,
            tk.SoDienThoai,
            hopdong_dangthue.DanhSachPhong,
            CASE
                WHEN hopdong_dangthue.MaKhachThue IS NULL THEN 'returned'
                ELSE 'renting'
            END AS TrangThaiThue
         FROM khachthue kt
         LEFT JOIN taikhoan tk ON tk.MaTaiKhoan = kt.MaTaiKhoan
         LEFT JOIN (
            SELECT
                hd.MaKhachThue,
                GROUP_CONCAT(DISTINCT p.TenPhong ORDER BY p.TenPhong SEPARATOR ', ') AS DanhSachPhong
            FROM hopdong hd
            LEFT JOIN phong p ON p.MaPhong = hd.MaPhong
            WHERE hd.TrangThai = 'HieuLuc'
            GROUP BY hd.MaKhachThue
         ) hopdong_dangthue ON hopdong_dangthue.MaKhachThue = kt.MaKhachThue
         $filterSql
         ORDER BY kt.MaKhachThue DESC
         LIMIT ? OFFSET ?"
    );
$listStmt->bind_param(
    'sssssiii',
    $search,
    $searchPattern,
    $searchPattern,
    $searchPattern,
    $statusFilter,
    $activeFilter,
    $pageSize,
    $offset
);
    $listStmt->execute();
    $listResult = $listStmt->get_result();
    while ($customer = $listResult->fetch_assoc()) {
        $customers[] = $customer;
    }
    $listStmt->close();
} catch (mysqli_sql_exception $exception) {
    error_log('Không thể tải danh sách khách thuê: ' . $exception->getMessage());
    $loadError = true;
}

$conn->close();

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

$paginationQuery = array('q' => $search, 'status' => $statusFilter);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý khách thuê - HappyHome</title>
    <link rel="stylesheet" href="/HappyHome/assets/css/dashboard.css">
    <style>
        .tenant-page { max-width: 1440px; margin: 0 auto; }
        .tenant-heading { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin: 6px 0 22px; }
        .tenant-heading h1 { color: #1e4e79; font-size: 25px; margin-bottom: 6px; }
        .tenant-heading p { color: #777; font-size: 14px; }
        .tenant-button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; padding: 11px 16px; border: 0; border-radius: 6px; background: #1e4e79; color: #fff; text-decoration: none; font-size: 14px; font-weight: 700; white-space: nowrap; cursor: pointer; }
        .tenant-button:hover { background: #286090; }
        .tenant-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-bottom: 22px; }
        .tenant-stat { background: #fff; border: 1px solid #e3e8ee; border-radius: 8px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(27, 55, 80, .04); }
        .tenant-stat-label { color: #667085; font-size: 13px; }
        .tenant-stat-value { color: #1e4e79; font-size: 28px; font-weight: 700; margin-top: 8px; }
        .tenant-stat-hint { color: #8a94a3; font-size: 12px; margin-top: 4px; }
        .tenant-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 14px; background: #fff; border: 1px solid #e3e8ee; border-radius: 8px 8px 0 0; padding: 16px; }
        .tenant-toolbar h2 { color: #27364b; font-size: 17px; }
        .tenant-filters { display: flex; flex-wrap: wrap; gap: 9px; }
        .tenant-filters input, .tenant-filters select { min-height: 40px; padding: 8px 11px; border: 1px solid #d5dce5; border-radius: 6px; background: #fff; color: #344054; font-size: 14px; }
        .tenant-filters input { width: min(320px, 44vw); }
        .tenant-filters button, .tenant-reset { min-height: 40px; padding: 8px 13px; border: 1px solid #d5dce5; border-radius: 6px; background: #f8fafc; color: #344054; text-decoration: none; font-size: 14px; cursor: pointer; }
        .tenant-filters button:hover, .tenant-reset:hover { background: #eef4f8; }
        .tenant-table-wrap { overflow-x: auto; background: #fff; border: 1px solid #e3e8ee; border-top: 0; border-radius: 0 0 8px 8px; }
        .tenant-table { width: 100%; border-collapse: collapse; min-width: 860px; }
        .tenant-table th, .tenant-table td { padding: 13px 15px; border-bottom: 1px solid #edf0f3; text-align: left; vertical-align: middle; font-size: 13px; }
        .tenant-table th { background: #f8fafc; color: #667085; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .tenant-table tbody tr:hover { background: #fbfdff; }
        .tenant-table tbody tr:last-child td { border-bottom: 0; }
        .tenant-name { color: #26384b; font-size: 14px; font-weight: 700; }
        .tenant-id { display: block; color: #98a2b3; font-size: 11px; margin-top: 4px; }
        .tenant-status { display: inline-block; padding: 5px 9px; border-radius: 20px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .tenant-status.is-renting { background: #e8f7ee; color: #21834a; }
        .tenant-status.is-returned { background: #f1f3f5; color: #667085; }
        .tenant-actions { display: flex; gap: 10px; white-space: nowrap; }
        .tenant-actions a, .tenant-actions button { padding: 0; border: 0; background: none; color: #1e4e79; font: inherit; font-size: 12px; text-decoration: none; cursor: pointer; }
        .tenant-actions a:hover, .tenant-actions button:hover { text-decoration: underline; }
        .tenant-actions .tenant-delete { color: #c0392b; }
        .tenant-empty, .tenant-error { padding: 30px 16px; color: #667085; text-align: center; }
        .tenant-error { color: #b42318; background: #fff6f5; }
        .tenant-pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 15px 16px; background: #fff; border: 1px solid #e3e8ee; border-top: 0; border-radius: 0 0 8px 8px; color: #667085; font-size: 13px; }
        .tenant-pages { display: flex; gap: 6px; }
        .tenant-pages a, .tenant-pages span { min-width: 34px; padding: 8px 10px; border: 1px solid #e0e5eb; border-radius: 5px; color: #344054; text-align: center; text-decoration: none; }
        .tenant-pages .current { background: #1e4e79; border-color: #1e4e79; color: #fff; }
        @media (max-width: 760px) {
            .tenant-stats { grid-template-columns: 1fr; gap: 10px; }
            .tenant-heading, .tenant-toolbar { align-items: stretch; flex-direction: column; }
            .tenant-heading .tenant-button { align-self: flex-start; }
            .tenant-filters { display: grid; grid-template-columns: 1fr 1fr; }
            .tenant-filters input { width: 100%; grid-column: 1 / -1; }
            .tenant-pagination { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <aside class="sidebar">
        <div class="logo">Happy<span>Home.</span></div>
        <p class="menu-title">MENU CHÍNH</p>
        <nav>
            <?php foreach ($menu as $item): ?>
                <a href="<?php echo $escape($item['url']); ?>"
                   class="<?php echo $item['name'] === 'Khách thuê' ? 'active' : ''; ?>">
                    <span><?php echo $escape($item['icon']); ?></span>
                    <?php echo $escape($item['name']); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-bottom">
            <a href="/HappyHome/auth/logout.php" class="logout">🚪 Đăng xuất</a>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div>
                <h2>Khách thuê</h2>
                <p>Quản lý hồ sơ và tình trạng thuê phòng</p>
            </div>
            <div class="user-info">
                <div class="avatar">👤</div>
                <div>
                    <strong><?php echo $escape($tenDangNhap); ?></strong>
                    <p><?php echo $escape($vaiTro === 'Admin' ? 'Admin' : 'Quản lý'); ?></p>
                </div>
            </div>
        </header>

        <section class="tenant-page">
            <div class="tenant-heading">
                <div>
                    <h1>Danh sách khách thuê</h1>
                    <p>Theo dõi thông tin và tình trạng thuê của khách hàng.</p>
                </div>
                <a class="tenant-button" href="thongtin.php?action=create">＋ Thêm khách thuê</a>
            </div>

            <div class="tenant-stats">
                <article class="tenant-stat">
                    <div class="tenant-stat-label">Tổng số khách thuê</div>
                    <div class="tenant-stat-value"><?php echo number_format($totalCustomers); ?></div>
                    <div class="tenant-stat-hint">Tất cả hồ sơ khách thuê</div>
                </article>
                <article class="tenant-stat">
                    <div class="tenant-stat-label">Đang thuê</div>
                    <div class="tenant-stat-value"><?php echo number_format($rentingCustomers); ?></div>
                    <div class="tenant-stat-hint">Có hợp đồng hiệu lực</div>
                </article>
                <article class="tenant-stat">
                    <div class="tenant-stat-label">Đã trả phòng</div>
                    <div class="tenant-stat-value"><?php echo number_format($returnedCustomers); ?></div>
                    <div class="tenant-stat-hint">Hiện không có hợp đồng hiệu lực</div>
                </article>
            </div>

            <div class="tenant-toolbar">
                <h2>Danh sách khách hàng</h2>
                <form class="tenant-filters" method="get" action="">
                    <input type="search" name="q" value="<?php echo $escape($search); ?>"
                           placeholder="Tìm họ tên, số điện thoại, CCCD" aria-label="Tìm khách thuê">
                    <select name="status" aria-label="Lọc theo trạng thái thuê">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Tất cả trạng thái</option>
                        <option value="renting" <?php echo $statusFilter === 'renting' ? 'selected' : ''; ?>>Đang thuê</option>
                        <option value="returned" <?php echo $statusFilter === 'returned' ? 'selected' : ''; ?>>Đã trả phòng</option>
                    </select>
                    <button type="submit">Tìm kiếm</button>
                    <?php if ($search !== '' || $statusFilter !== 'all'): ?>
                        <a class="tenant-reset" href="index.php">Đặt lại</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="tenant-table-wrap">
                <?php if ($loadError): ?>
                    <div class="tenant-error">Không thể tải danh sách khách thuê. Vui lòng thử lại sau.</div>
                <?php elseif (count($customers) === 0): ?>
                    <div class="tenant-empty">Không tìm thấy khách thuê phù hợp.</div>
                <?php else: ?>
                    <table class="tenant-table">
                        <thead>
                            <tr>
                                <th>Khách thuê</th>
                                <th>Số điện thoại</th>
                                <th>CCCD</th>
                                <th>Phòng đang thuê</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $customer): ?>
                                <?php
                                $customerId = (int) $customer['MaKhachThue'];
                                $isRenting = $customer['TrangThaiThue'] === 'renting';
                                ?>
                                <tr>
                                    <td>
                                        <span class="tenant-name"><?php echo $escape($customer['HoTen']); ?></span>
                                        <span class="tenant-id">Mã khách: <?php echo $customerId; ?></span>
                                    </td>
                                    <td><?php echo $escape($customer['SoDienThoai'] !== null && $customer['SoDienThoai'] !== '' ? $customer['SoDienThoai'] : '—'); ?></td>
                                    <td><?php echo $escape($customer['CCCD'] !== null && $customer['CCCD'] !== '' ? $customer['CCCD'] : '—'); ?></td>
                                    <td><?php echo $escape($customer['DanhSachPhong'] !== null && $customer['DanhSachPhong'] !== '' ? $customer['DanhSachPhong'] : '—'); ?></td>
                                    <td>
                                        <span class="tenant-status <?php echo $isRenting ? 'is-renting' : 'is-returned'; ?>">
                                            <?php echo $isRenting ? 'Đang thuê' : 'Đã trả phòng'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="tenant-actions">
                                            <a href="thongtin.php?action=view&amp;id=<?php echo $customerId; ?>">Chi tiết</a>
                                            <a href="thongtin.php?action=edit&amp;id=<?php echo $customerId; ?>">Sửa</a>
                                            <form method="post" action="thongtin.php" onsubmit="return confirm('Bạn có chắc muốn xóa khách thuê này?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $customerId; ?>">
                                                <button class="tenant-delete" type="submit">Xóa</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if (!$loadError && $totalPages > 1): ?>
                <nav class="tenant-pagination" aria-label="Phân trang danh sách khách thuê">
                    <span>Trang <?php echo $currentPage; ?> / <?php echo $totalPages; ?></span>
                    <div class="tenant-pages">
                        <?php if ($currentPage > 1): ?>
                            <?php $paginationQuery['page'] = $currentPage - 1; ?>
                            <a href="index.php?<?php echo $escape(http_build_query($paginationQuery)); ?>" aria-label="Trang trước">‹</a>
                        <?php endif; ?>
                        <?php
                        $firstPage = max(1, $currentPage - 2);
                        $lastPage = min($totalPages, $currentPage + 2);
                        for ($pageNumber = $firstPage; $pageNumber <= $lastPage; $pageNumber++):
                            $paginationQuery['page'] = $pageNumber;
                        ?>
                            <?php if ($pageNumber === $currentPage): ?>
                                <span class="current" aria-current="page"><?php echo $pageNumber; ?></span>
                            <?php else: ?>
                                <a href="index.php?<?php echo $escape(http_build_query($paginationQuery)); ?>"><?php echo $pageNumber; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <?php $paginationQuery['page'] = $currentPage + 1; ?>
                            <a href="index.php?<?php echo $escape(http_build_query($paginationQuery)); ?>" aria-label="Trang tiếp">›</a>
                        <?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </section>

        <footer>© 2026 HappyHome - Hệ thống quản lý nhà trọ</footer>
    </main>
</div>
</body>
</html>