<?php
require_once __DIR__ . '/_common.php';

// ---------- Xử lý POST: ngừng cho thuê / mở lại cho thuê ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $postString('action');
    $id = filter_var($postString('id'), FILTER_VALIDATE_INT);
    $id = ($id === false || $id < 1) ? 0 : (int) $id;

    if (!$checkCsrf()) {
        $setMessage('error', 'Phiên làm việc không hợp lệ. Vui lòng thử lại.');
    } elseif ($id === 0 || !in_array($action, array('stop', 'reopen'), true)) {
        $setMessage('error', 'Yêu cầu không hợp lệ.');
    } else {
        try {
            $stmt = $conn->prepare('SELECT TenPhong, TrangThai FROM phong WHERE MaPhong = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $room = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$room) {
                $setMessage('error', 'Không tìm thấy phòng.');
            } elseif ($action === 'stop') {
                if ($room['TrangThai'] === 'NgungChoThue') {
                    $setMessage('warning', 'Phòng ' . $room['TenPhong'] . ' đã ngừng cho thuê từ trước.');
                } elseif ($room['TrangThai'] === 'DaCoNguoiO' || $countActiveContracts($id) > 0) {
                    $setMessage('error', 'Không thể ngừng cho thuê phòng ' . $room['TenPhong'] . ' vì đang có người ở hoặc còn hợp đồng chưa kết thúc.');
                } else {
                    $stmt = $conn->prepare("UPDATE phong SET TrangThai = 'NgungChoThue' WHERE MaPhong = ?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                    $setMessage('success', 'Đã ngừng cho thuê phòng ' . $room['TenPhong'] . '. Lịch sử hợp đồng, hóa đơn vẫn được giữ nguyên.');
                }
            } else { // reopen
                if ($room['TrangThai'] !== 'NgungChoThue') {
                    $setMessage('warning', 'Phòng ' . $room['TenPhong'] . ' đang không ở trạng thái ngừng cho thuê.');
                } else {
                    $stmt = $conn->prepare("UPDATE phong SET TrangThai = 'Trong' WHERE MaPhong = ?");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $stmt->close();
                    $setMessage('success', 'Đã mở lại cho thuê phòng ' . $room['TenPhong'] . '.');
                }
            }
        } catch (mysqli_sql_exception $e) {
            error_log('Lỗi cập nhật trạng thái phòng: ' . $e->getMessage());
            $setMessage('error', 'Có lỗi xảy ra, vui lòng thử lại sau.');
        }
    }

    $back = '/HappyHome/pages/admin/quanlyphong/index.php';
    $qs = isset($_POST['return_qs']) && is_string($_POST['return_qs']) ? $_POST['return_qs'] : '';
    if ($qs !== '' && preg_match('/^[A-Za-z0-9_%=&+.\-]*$/', $qs)) {
        $back .= '?' . $qs;
    }
    header('Location: ' . $back);
    exit();
}

// ---------- Danh sách ----------
$search = trim(isset($_GET['q']) && is_string($_GET['q']) ? $_GET['q'] : '');
$typeFilter = isset($_GET['type']) && is_string($_GET['type']) ? $_GET['type'] : 'all';
if ($typeFilter !== 'all' && !isset($loaiPhongLabels[$typeFilter])) {
    $typeFilter = 'all';
}
$statusFilter = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : 'all';
if ($statusFilter !== 'all' && !isset($trangThaiLabels[$statusFilter])) {
    $statusFilter = 'all';
}

$pageSize = 10;
$currentPage = isset($_GET['page']) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
$currentPage = ($currentPage === false || $currentPage < 1) ? 1 : $currentPage;
$totalPages = 1;
$rooms = array();
$stats = array('Tong' => 0, 'Trong' => 0, 'DaCoNguoiO' => 0, 'Bao' => 0, 'NgungChoThue' => 0);
$loadError = false;

try {
    $res = $conn->query(
        "SELECT
            COUNT(*) AS Tong,
            SUM(TrangThai = 'Trong') AS Trong,
            SUM(TrangThai = 'DaCoNguoiO') AS DaCoNguoiO,
            SUM(TrangThai IN ('DangDonDep', 'DangSuaChua')) AS Bao,
            SUM(TrangThai = 'NgungChoThue') AS NgungChoThue
         FROM phong"
    );
    $row = $res->fetch_assoc();
    foreach ($stats as $k => $_) {
        $stats[$k] = (int) $row[$k];
    }

    $like = '%' . $search . '%';
    $where = "WHERE (? = '' OR TenPhong LIKE ? OR COALESCE(MoTa, '') LIKE ?)
              AND (? = 'all' OR LoaiPhong = ?)
              AND (? = 'all' OR TrangThai = ?)";

    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM phong $where");
    $stmt->bind_param('sssssss', $search, $like, $like, $typeFilter, $typeFilter, $statusFilter, $statusFilter);
    $stmt->execute();
    $totalFiltered = (int) $stmt->get_result()->fetch_assoc()['n'];
    $stmt->close();

    $totalPages = max(1, (int) ceil($totalFiltered / $pageSize));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $pageSize;

    $stmt = $conn->prepare(
        "SELECT MaPhong, TenPhong, LoaiPhong, DienTich, GiaThue, MoTa, TrangThai
         FROM phong $where
         ORDER BY (TrangThai = 'NgungChoThue'), TenPhong
         LIMIT ? OFFSET ?"
    );
    $stmt->bind_param('sssssssii', $search, $like, $like, $typeFilter, $typeFilter, $statusFilter, $statusFilter, $pageSize, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($r = $result->fetch_assoc()) {
        $rooms[] = $r;
    }
    $stmt->close();
} catch (mysqli_sql_exception $e) {
    error_log('Không thể tải danh sách phòng: ' . $e->getMessage());
    $loadError = true;
}

$conn->close();

$query = array('q' => $search, 'type' => $typeFilter, 'status' => $statusFilter);
$returnQs = http_build_query($query + array('page' => $currentPage));
$hasFilter = $search !== '' || $typeFilter !== 'all' || $statusFilter !== 'all';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý phòng - HappyHome</title>
    <link rel="stylesheet" href="/HappyHome/assets/css/dashboard.css">
    <style><?php echo $roomCss; ?></style>
</head>
<body>
<div class="container">
    <aside class="sidebar">
        <div class="logo">Happy<span>Home.</span></div>
        <p class="menu-title">MENU CHÍNH</p>
        <nav>
            <?php foreach ($menu as $item): ?>
                <a href="<?php echo $escape($item['url']); ?>"
                   class="<?php echo $item['name'] === 'Quản lý phòng' ? 'active' : ''; ?>">
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
                <h2>Quản lý phòng</h2>
                <p>Quản lý phòng trọ, sleep box và tình trạng phòng</p>
            </div>
            <div class="user-info">
                <div class="avatar">👤</div>
                <div>
                    <strong><?php echo $escape($tenDangNhap); ?></strong>
                    <p><?php echo $escape($vaiTro === 'Admin' ? 'Admin' : 'Quản lý'); ?></p>
                </div>
            </div>
        </header>

        <section class="room-page">
            <div class="room-heading">
                <div>
                    <h1>Danh sách phòng</h1>
                    <p>Thêm mới, chỉnh sửa thông tin, cập nhật tình trạng hoặc ngừng cho thuê.</p>
                </div>
                <a class="room-button" href="thongtin.php?action=create">＋ Thêm phòng / sleep box</a>
            </div>

            <?php if (is_array($message) && isset($message['text'])): ?>
                <?php $alertType = in_array($message['type'], array('success', 'error', 'warning'), true) ? $message['type'] : 'warning'; ?>
                <div class="room-alert <?php echo $alertType; ?>"><?php echo $escape($message['text']); ?></div>
            <?php endif; ?>

            <div class="room-stats">
                <article class="room-stat"><div class="room-stat-label">Tổng số phòng</div><div class="room-stat-value"><?php echo $stats['Tong']; ?></div></article>
                <article class="room-stat"><div class="room-stat-label">Còn trống</div><div class="room-stat-value"><?php echo $stats['Trong']; ?></div></article>
                <article class="room-stat"><div class="room-stat-label">Đã có người ở</div><div class="room-stat-value"><?php echo $stats['DaCoNguoiO']; ?></div></article>
                <article class="room-stat"><div class="room-stat-label">Dọn dẹp / sửa chữa</div><div class="room-stat-value"><?php echo $stats['Bao']; ?></div></article>
                <article class="room-stat"><div class="room-stat-label">Ngừng cho thuê</div><div class="room-stat-value"><?php echo $stats['NgungChoThue']; ?></div></article>
            </div>

            <div class="room-toolbar">
                <h2>Tất cả phòng</h2>
                <form class="room-filters" method="get" action="index.php">
                    <input type="search" name="q" value="<?php echo $escape($search); ?>" placeholder="Tìm tên phòng, mô tả" aria-label="Tìm phòng">
                    <select name="type" aria-label="Lọc theo loại phòng">
                        <option value="all">Tất cả loại</option>
                        <?php foreach ($loaiPhongLabels as $value => $label): ?>
                            <option value="<?php echo $escape($value); ?>" <?php echo $typeFilter === $value ? 'selected' : ''; ?>><?php echo $escape($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="status" aria-label="Lọc theo trạng thái">
                        <option value="all">Tất cả trạng thái</option>
                        <?php foreach ($trangThaiLabels as $value => $label): ?>
                            <option value="<?php echo $escape($value); ?>" <?php echo $statusFilter === $value ? 'selected' : ''; ?>><?php echo $escape($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit">Tìm kiếm</button>
                    <?php if ($hasFilter): ?><a class="room-reset" href="index.php">Đặt lại</a><?php endif; ?>
                </form>
            </div>

            <div class="room-table-wrap">
                <?php if ($loadError): ?>
                    <div class="room-error">Không thể tải danh sách phòng. Vui lòng thử lại sau.</div>
                <?php elseif (count($rooms) === 0): ?>
                    <div class="room-empty"><?php echo $hasFilter ? 'Không tìm thấy phòng phù hợp.' : 'Chưa có phòng nào. Hãy bấm “Thêm phòng / sleep box” để bắt đầu.'; ?></div>
                <?php else: ?>
                    <table class="room-table">
                        <thead>
                            <tr>
                                <th>Phòng</th>
                                <th>Loại</th>
                                <th>Diện tích</th>
                                <th>Giá thuê / tháng</th>
                                <th>Mô tả</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rooms as $room): ?>
                                <?php
                                $roomId = (int) $room['MaPhong'];
                                $isStopped = $room['TrangThai'] === 'NgungChoThue';
                                ?>
                                <tr class="<?php echo $isStopped ? 'is-stopped' : ''; ?>">
                                    <td>
                                        <span class="room-name"><?php echo $escape($room['TenPhong']); ?></span>
                                        <span class="room-id">Mã phòng: <?php echo $roomId; ?></span>
                                    </td>
                                    <td><?php echo $escape(isset($loaiPhongLabels[$room['LoaiPhong']]) ? $loaiPhongLabels[$room['LoaiPhong']] : $room['LoaiPhong']); ?></td>
                                    <td><?php echo $room['DienTich'] !== null ? $escape(rtrim(rtrim($room['DienTich'], '0'), '.')) . ' m²' : '—'; ?></td>
                                    <td><?php echo number_format((float) $room['GiaThue'], 0, ',', '.'); ?> đ</td>
                                    <td><div class="room-desc" title="<?php echo $escape($room['MoTa']); ?>"><?php echo $room['MoTa'] !== null && $room['MoTa'] !== '' ? $escape($room['MoTa']) : '—'; ?></div></td>
                                    <td>
                                        <span class="room-status <?php echo $escape(isset($trangThaiClass[$room['TrangThai']]) ? $trangThaiClass[$room['TrangThai']] : ''); ?>">
                                            <?php echo $escape(isset($trangThaiLabels[$room['TrangThai']]) ? $trangThaiLabels[$room['TrangThai']] : $room['TrangThai']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="room-actions">
                                            <a href="thongtin.php?action=edit&amp;id=<?php echo $roomId; ?>">Sửa</a>
                                            <?php if ($isStopped): ?>
                                                <form method="post" action="index.php" onsubmit="return confirm('Mở lại cho thuê phòng này?');">
                                                    <input type="hidden" name="action" value="reopen">
                                                    <input type="hidden" name="id" value="<?php echo $roomId; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                                    <input type="hidden" name="return_qs" value="<?php echo $escape($returnQs); ?>">
                                                    <button type="submit">Mở lại</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="post" action="index.php" onsubmit="return confirm('Ngừng cho thuê phòng này? Lịch sử hợp đồng và hóa đơn vẫn được giữ lại.');">
                                                    <input type="hidden" name="action" value="stop">
                                                    <input type="hidden" name="id" value="<?php echo $roomId; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                                                    <input type="hidden" name="return_qs" value="<?php echo $escape($returnQs); ?>">
                                                    <button class="room-danger" type="submit">Ngừng cho thuê</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <?php if (!$loadError && $totalPages > 1): ?>
                <nav class="room-pagination" aria-label="Phân trang danh sách phòng">
                    <span>Trang <?php echo $currentPage; ?> / <?php echo $totalPages; ?></span>
                    <div class="room-pages">
                        <?php if ($currentPage > 1): ?>
                            <a href="index.php?<?php echo $escape(http_build_query($query + array('page' => $currentPage - 1))); ?>" aria-label="Trang trước">‹</a>
                        <?php endif; ?>
                        <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                            <?php if ($p === $currentPage): ?>
                                <span class="current" aria-current="page"><?php echo $p; ?></span>
                            <?php else: ?>
                                <a href="index.php?<?php echo $escape(http_build_query($query + array('page' => $p))); ?>"><?php echo $p; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($currentPage < $totalPages): ?>
                            <a href="index.php?<?php echo $escape(http_build_query($query + array('page' => $currentPage + 1))); ?>" aria-label="Trang tiếp">›</a>
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
