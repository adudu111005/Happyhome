<?php
require_once __DIR__ . '/_common.php';

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$action = $isPost
    ? $postString('action')
    : (isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : 'create');
if (!in_array($action, array('create', 'edit'), true)) {
    $action = 'create';
}

$rawId = $isPost ? $postString('id') : (isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '');
$id = filter_var($rawId, FILTER_VALIDATE_INT);
$id = ($id === false || $id < 1) ? 0 : (int) $id;

$goList = function () {
    header('Location: /HappyHome/pages/admin/quanlyphong/index.php');
    exit();
};

$errors = array();
$room = null;              // bản ghi hiện tại khi sửa
$hasActiveContract = false;
$form = array(
    'TenPhong' => '',
    'LoaiPhong' => 'PhongTro',
    'DienTich' => '',
    'GiaThue' => '',
    'MoTa' => '',
    'TrangThai' => 'Trong'
);

try {
    if ($action === 'edit') {
        if ($id === 0) {
            $setMessage('error', 'Không tìm thấy phòng cần sửa.');
            $goList();
        }
        $stmt = $conn->prepare('SELECT MaPhong, TenPhong, LoaiPhong, DienTich, GiaThue, MoTa, TrangThai FROM phong WHERE MaPhong = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $room = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$room) {
            $setMessage('error', 'Không tìm thấy phòng cần sửa.');
            $goList();
        }
        $hasActiveContract = $countActiveContracts($id) > 0;

        if (!$isPost) {
            $form = array(
                'TenPhong' => (string) $room['TenPhong'],
                'LoaiPhong' => (string) $room['LoaiPhong'],
                'DienTich' => $room['DienTich'] !== null ? rtrim(rtrim((string) $room['DienTich'], '0'), '.') : '',
                'GiaThue' => rtrim(rtrim((string) $room['GiaThue'], '0'), '.'),
                'MoTa' => (string) $room['MoTa'],
                'TrangThai' => (string) $room['TrangThai']
            );
        }
    }

    // Các trạng thái được phép chọn
    if ($action === 'create') {
        $allowedStatus = array('Trong', 'DangDonDep', 'DangSuaChua');
    } else {
        $allowedStatus = array('Trong', 'DaCoNguoiO', 'DangDonDep', 'DangSuaChua');
        if ($room['TrangThai'] === 'NgungChoThue') {
            $allowedStatus[] = 'NgungChoThue'; // giữ nguyên nếu không đổi
        }
    }

    // ---------- Xử lý lưu ----------
    if ($isPost) {
        $form['TenPhong'] = preg_replace('/\s+/u', ' ', $postString('TenPhong'));
        $form['LoaiPhong'] = $postString('LoaiPhong');
        $form['DienTich'] = $postString('DienTich');
        $form['GiaThue'] = $postString('GiaThue');
        $form['MoTa'] = $postString('MoTa');
        $form['TrangThai'] = $postString('TrangThai');

        if (!$checkCsrf()) {
            $errors[] = 'Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.';
        }

        // Tên phòng
        $len = mb_strlen($form['TenPhong'], 'UTF-8');
        if ($len === 0) {
            $errors[] = 'Vui lòng nhập tên phòng.';
        } elseif ($len > 50) {
            $errors[] = 'Tên phòng tối đa 50 ký tự.';
        }

        // Loại phòng
        if (!isset($loaiPhongLabels[$form['LoaiPhong']])) {
            $errors[] = 'Loại phòng không hợp lệ.';
        }

        // Diện tích (không bắt buộc)
        $dienTich = null;
        if ($form['DienTich'] !== '') {
            $dt = str_replace(',', '.', str_replace(' ', '', $form['DienTich']));
            if (!preg_match('/^\d{1,4}(\.\d{1,2})?$/', $dt) || (float) $dt <= 0) {
                $errors[] = 'Diện tích phải là số lớn hơn 0 (tối đa 2 chữ số thập phân).';
            } else {
                $dienTich = $dt;
            }
        }

        // Giá thuê
        $giaThue = null;
        $gt = str_replace(' ', '', $form['GiaThue']);
        if ($gt === '') {
            $errors[] = 'Vui lòng nhập giá thuê.';
        } elseif (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $gt) || (float) $gt <= 0) {
            $errors[] = 'Giá thuê phải là số lớn hơn 0.';
        } else {
            $giaThue = $gt;
        }

        // Mô tả
        if (mb_strlen($form['MoTa'], 'UTF-8') > 2000) {
            $errors[] = 'Mô tả tối đa 2000 ký tự.';
        }

        // Trạng thái
        if (!in_array($form['TrangThai'], $allowedStatus, true)) {
            $errors[] = 'Trạng thái không hợp lệ.';
        } elseif ($action === 'edit' && $hasActiveContract && $form['TrangThai'] !== 'DaCoNguoiO') {
            $errors[] = 'Phòng đang có hợp đồng chưa kết thúc nên trạng thái phải là “Đã có người ở”. Hãy kết thúc hợp đồng trước khi đổi trạng thái.';
        }

        // Trùng tên phòng
        if (count($errors) === 0) {
            $excludeId = $action === 'edit' ? $id : 0;
            $stmt = $conn->prepare('SELECT MaPhong FROM phong WHERE TenPhong = ? AND MaPhong <> ? LIMIT 1');
            $stmt->bind_param('si', $form['TenPhong'], $excludeId);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'Tên phòng “' . $form['TenPhong'] . '” đã tồn tại.';
            }
            $stmt->close();
        }

        if (count($errors) === 0) {
            $moTa = $form['MoTa'] === '' ? null : $form['MoTa'];
            try {
                if ($action === 'create') {
                    $stmt = $conn->prepare(
                        'INSERT INTO phong (TenPhong, LoaiPhong, DienTich, GiaThue, MoTa, TrangThai) VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->bind_param('ssssss', $form['TenPhong'], $form['LoaiPhong'], $dienTich, $giaThue, $moTa, $form['TrangThai']);
                    $stmt->execute();
                    $stmt->close();
                    $_SESSION['csrf_quanlyphong'] = bin2hex(random_bytes(32));
                    $setMessage('success', 'Đã thêm ' . mb_strtolower($loaiPhongLabels[$form['LoaiPhong']], 'UTF-8') . ' “' . $form['TenPhong'] . '”.');
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE phong SET TenPhong = ?, LoaiPhong = ?, DienTich = ?, GiaThue = ?, MoTa = ?, TrangThai = ? WHERE MaPhong = ?'
                    );
                    $stmt->bind_param('ssssssi', $form['TenPhong'], $form['LoaiPhong'], $dienTich, $giaThue, $moTa, $form['TrangThai'], $id);
                    $stmt->execute();
                    $stmt->close();
                    $_SESSION['csrf_quanlyphong'] = bin2hex(random_bytes(32));
                    $setMessage('success', 'Đã cập nhật phòng “' . $form['TenPhong'] . '”.');
                }
                $goList();
            } catch (mysqli_sql_exception $e) {
                if ((int) $e->getCode() === 1062) {
                    $errors[] = 'Tên phòng “' . $form['TenPhong'] . '” đã tồn tại.';
                } else {
                    error_log('Lỗi lưu phòng: ' . $e->getMessage());
                    $errors[] = 'Không thể lưu phòng, vui lòng thử lại sau.';
                }
            }
        }
    }
} catch (mysqli_sql_exception $e) {
    error_log('Lỗi trang thông tin phòng: ' . $e->getMessage());
    $errors[] = 'Có lỗi xảy ra, vui lòng thử lại sau.';
    if (!isset($allowedStatus)) {
        $allowedStatus = array('Trong');
    }
}

$conn->close();

$pageTitle = $action === 'edit' ? 'Sửa thông tin phòng' : 'Thêm phòng / sleep box';
$statusLocked = $action === 'edit' && $hasActiveContract;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $escape($pageTitle); ?> - HappyHome</title>
    <link rel="stylesheet" href="/HappyHome/assets/css/dashboard.css">
    <style><?php echo $roomCss; ?> .room-content { max-width: 900px; margin: 0 auto; }</style>
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
                <h2><?php echo $escape($pageTitle); ?></h2>
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

        <section class="room-content">
            <div class="room-heading">
                <div>
                    <h1><?php echo $escape($pageTitle); ?></h1>
                    <p><?php echo $action === 'edit' ? 'Chỉnh sửa thông tin và cập nhật tình trạng phòng.' : 'Nhập thông tin phòng trọ hoặc sleep box mới.'; ?></p>
                </div>
                <a class="room-button secondary" href="index.php">← Danh sách phòng</a>
            </div>

            <?php if (count($errors) > 0): ?>
                <div class="room-alert error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $escape($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="room-card">
                <form method="post" action="thongtin.php" novalidate>
                    <input type="hidden" name="action" value="<?php echo $escape($action); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                    <?php if ($action === 'edit'): ?>
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                    <?php endif; ?>

                    <div class="room-form">
                        <div class="room-field">
                            <label for="TenPhong">Tên phòng *</label>
                            <input id="TenPhong" name="TenPhong" type="text" maxlength="50" required
                                   placeholder="VD: P101, SB-01" value="<?php echo $escape($form['TenPhong']); ?>">
                            <span class="room-help">Tên phải là duy nhất, tối đa 50 ký tự.</span>
                        </div>

                        <div class="room-field">
                            <label for="LoaiPhong">Loại *</label>
                            <select id="LoaiPhong" name="LoaiPhong" required>
                                <?php foreach ($loaiPhongLabels as $value => $label): ?>
                                    <option value="<?php echo $escape($value); ?>" <?php echo $form['LoaiPhong'] === $value ? 'selected' : ''; ?>><?php echo $escape($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="room-field">
                            <label for="DienTich">Diện tích (m²)</label>
                            <input id="DienTich" name="DienTich" type="text" inputmode="decimal" maxlength="8"
                                   placeholder="VD: 18.5" value="<?php echo $escape($form['DienTich']); ?>">
                        </div>

                        <div class="room-field">
                            <label for="GiaThue">Giá thuê / tháng (VNĐ) *</label>
                            <input id="GiaThue" name="GiaThue" type="text" inputmode="numeric" maxlength="16" required
                                   placeholder="VD: 2500000" value="<?php echo $escape($form['GiaThue']); ?>">
                        </div>

                        <div class="room-field full">
                            <label for="TrangThai">Tình trạng phòng *</label>
                            <select id="TrangThai" name="TrangThai" <?php echo $statusLocked ? 'disabled' : ''; ?>>
                                <?php foreach ($allowedStatus as $value): ?>
                                    <option value="<?php echo $escape($value); ?>" <?php echo $form['TrangThai'] === $value ? 'selected' : ''; ?>><?php echo $escape($trangThaiLabels[$value]); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($statusLocked): ?>
                                <input type="hidden" name="TrangThai" value="DaCoNguoiO">
                                <span class="room-help">Phòng đang có hợp đồng chưa kết thúc nên không thể đổi tình trạng.</span>
                            <?php elseif ($action === 'create'): ?>
                                <span class="room-help">Phòng mới chưa có hợp đồng nên chỉ chọn: Trống, Đang dọn dẹp hoặc Đang sửa chữa.</span>
                            <?php endif; ?>
                        </div>

                        <div class="room-field full">
                            <label for="MoTa">Mô tả</label>
                            <textarea id="MoTa" name="MoTa" maxlength="2000" placeholder="Tiện nghi, vị trí, lưu ý..."><?php echo $escape($form['MoTa']); ?></textarea>
                        </div>
                    </div>

                    <div class="room-form-actions">
                        <button class="room-button" type="submit"><?php echo $action === 'edit' ? 'Lưu thay đổi' : 'Thêm phòng'; ?></button>
                        <a class="room-button secondary" href="index.php">Hủy</a>
                    </div>
                </form>
            </div>

            <?php if ($action === 'edit'): ?>
                <p class="room-note">Muốn ngừng cho thuê phòng này, hãy dùng nút “Ngừng cho thuê” ở danh sách phòng. Dữ liệu hợp đồng, hóa đơn cũ vẫn được giữ lại.</p>
            <?php endif; ?>
        </section>

        <footer>© 2026 HappyHome - Hệ thống quản lý nhà trọ</footer>
    </main>
</div>
</body>
</html>
