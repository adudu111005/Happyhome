<?php
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
$databaseBootstrapOutput = ob_get_clean();
if ($databaseBootstrapOutput !== '') {
    error_log('config/database.php emitted unexpected output while loading the tenant page.');
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$escape = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$redirect = function ($url) {
    header('Location: ' . $url);
    exit();
};
$postString = function ($key) {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : '';
};

if (!isset($_SESSION['csrf_quanlykhachthue'])) {
    $_SESSION['csrf_quanlykhachthue'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_quanlykhachthue'];

$message = isset($_SESSION['khachthue_message']) ? $_SESSION['khachthue_message'] : null;
unset($_SESSION['khachthue_message']);

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$action = $isPost
    ? $postString('action')
    : (isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : 'create');
$allowedActions = array('create', 'view', 'edit', 'delete', 'deleted');
if (!in_array($action, $allowedActions, true)) {
    $action = 'create';
}

$rawId = $isPost
    ? $postString('id')
    : (isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : '');
$id = filter_var($rawId, FILTER_VALIDATE_INT);
$id = ($id === false || $id < 1) ? 0 : (int) $id;

$errors = array();
$customer = null;
$confirmDelete = false;
$formValues = array(
    'HoTen' => '',
    'CCCD' => '',
    'SoDienThoai' => '',
    'TenDangNhap' => ''
);
$tenDangNhap = isset($_SESSION['ten_dang_nhap']) ? (string) $_SESSION['ten_dang_nhap'] : '';

$loadCustomer = function ($customerId) use ($conn) {
    $stmt = $conn->prepare(
        "SELECT
            kt.MaKhachThue,
            kt.MaTaiKhoan,
            kt.HoTen,
            kt.CCCD,
            kt.SoDienThoai,
            tk.TenDangNhap,
            tk.TrangThai AS TrangThaiTaiKhoan,
            tk.MaKhachThue AS MaKhachThueTaiKhoan
         FROM khachthue kt
         INNER JOIN taikhoan tk ON tk.MaTaiKhoan = kt.MaTaiKhoan
         WHERE kt.MaKhachThue = ? AND tk.VaiTro = 'KhachThue'
         LIMIT 1"
    );
    $stmt->bind_param('i', $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row ? $row : null;
};

if (in_array($action, array('view', 'edit', 'delete'), true)) {
    if ($id < 1) {
        $errors[] = 'Mã khách thuê không hợp lệ.';
    } else {
        try {
            $customer = $loadCustomer($id);
            if (!$customer) {
                $errors[] = 'Không tìm thấy khách thuê hoặc tài khoản không hợp lệ.';
            } else {
                $formValues = array(
                    'HoTen' => $customer['HoTen'],
                    'CCCD' => $customer['CCCD'],
                    'SoDienThoai' => $customer['SoDienThoai'],
                    'TenDangNhap' => $customer['TenDangNhap']
                );
            }
        } catch (mysqli_sql_exception $exception) {
            error_log('Không thể tải hồ sơ khách thuê: ' . $exception->getMessage());
            $errors[] = 'Không thể tải thông tin khách thuê. Vui lòng thử lại sau.';
        }
    }
}

if ($isPost && in_array($action, array('create', 'edit'), true)) {
    $formValues = array(
        'HoTen' => trim($postString('HoTen')),
        'CCCD' => trim($postString('CCCD')),
        'SoDienThoai' => trim($postString('SoDienThoai')),
        'TenDangNhap' => trim($postString('TenDangNhap'))
    );
    $password = $postString('MatKhau');
    $confirmPassword = $postString('XacNhanMatKhau');
    $submittedToken = $postString('csrf_token');

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $errors[] = 'Phiên biểu mẫu không hợp lệ hoặc đã hết hạn. Vui lòng tải lại trang.';
    }
    if ($action === 'edit' && (!$customer || $id < 1)) {
        $errors[] = 'Không thể xác định khách thuê cần cập nhật.';
    }
    if ($formValues['HoTen'] === '' || $formValues['CCCD'] === '' ||
        $formValues['SoDienThoai'] === '' || $formValues['TenDangNhap'] === '') {
        $errors[] = 'Vui lòng nhập đầy đủ họ tên, CCCD, số điện thoại và tên đăng nhập.';
    }
    if ($formValues['CCCD'] !== '' && !preg_match('/^[0-9]{9,20}$/', $formValues['CCCD'])) {
        $errors[] = 'CCCD chỉ được gồm 9 đến 20 chữ số.';
    }
    if ($formValues['SoDienThoai'] !== '' && !preg_match('/^[0-9]{10,15}$/', $formValues['SoDienThoai'])) {
        $errors[] = 'Số điện thoại phải gồm 10 đến 15 chữ số.';
    }
    if ($formValues['TenDangNhap'] !== '' &&
        !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $formValues['TenDangNhap'])) {
        $errors[] = 'Tên đăng nhập phải dài 3 đến 50 ký tự, chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.';
    }
    if ($action === 'create') {
        if (strlen($password) < 8) {
            $errors[] = 'Mật khẩu phải có ít nhất 8 ký tự.';
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Mật khẩu xác nhận không khớp.';
        }
    }
    if ($action === 'edit' && $customer &&
        $customer['MaKhachThueTaiKhoan'] !== null &&
        (int) $customer['MaKhachThueTaiKhoan'] !== $id) {
        $errors[] = 'Liên kết tài khoản và hồ sơ khách thuê không nhất quán; không thể cập nhật.';
    }

    if (count($errors) === 0) {
        try {
            $engineStmt = $conn->prepare(
                "SELECT COUNT(*) AS SoBangInnoDB
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN (?, ?)
                   AND ENGINE = 'InnoDB'"
            );
            $tenantTable = 'khachthue';
            $accountTable = 'taikhoan';
            $engineStmt->bind_param('ss', $tenantTable, $accountTable);
            $engineStmt->execute();
            $engineCount = (int) $engineStmt->get_result()->fetch_assoc()['SoBangInnoDB'];
            $engineStmt->close();

            if ($engineCount !== 2) {
                $errors[] = 'Không thể đảm bảo transaction: bảng khachthue và taikhoan cần dùng InnoDB.';
            } else {
                $usernameStmt = $conn->prepare(
                    $action === 'create'
                        ? 'SELECT MaTaiKhoan FROM taikhoan WHERE TenDangNhap = ? LIMIT 1'
                        : 'SELECT MaTaiKhoan FROM taikhoan WHERE TenDangNhap = ? AND MaTaiKhoan <> ? LIMIT 1'
                );
                if ($action === 'create') {
                    $usernameStmt->bind_param('s', $formValues['TenDangNhap']);
                } else {
                    $accountIdForCheck = (int) $customer['MaTaiKhoan'];
                    $usernameStmt->bind_param('si', $formValues['TenDangNhap'], $accountIdForCheck);
                }
                $usernameStmt->execute();
                $usernameExists = $usernameStmt->get_result()->num_rows > 0;
                $usernameStmt->close();

                $cccdStmt = $conn->prepare(
                    $action === 'create'
                        ? 'SELECT MaKhachThue FROM khachthue WHERE CCCD = ? LIMIT 1'
                        : 'SELECT MaKhachThue FROM khachthue WHERE CCCD = ? AND MaKhachThue <> ? LIMIT 1'
                );
                if ($action === 'create') {
                    $cccdStmt->bind_param('s', $formValues['CCCD']);
                } else {
                    $cccdStmt->bind_param('si', $formValues['CCCD'], $id);
                }
                $cccdStmt->execute();
                $cccdExists = $cccdStmt->get_result()->num_rows > 0;
                $cccdStmt->close();

                $phoneStmt = $conn->prepare(
                    $action === 'create'
                        ? 'SELECT MaKhachThue FROM khachthue WHERE SoDienThoai = ? LIMIT 1'
                        : 'SELECT MaKhachThue FROM khachthue WHERE SoDienThoai = ? AND MaKhachThue <> ? LIMIT 1'
                );
                if ($action === 'create') {
                    $phoneStmt->bind_param('s', $formValues['SoDienThoai']);
                } else {
                    $phoneStmt->bind_param('si', $formValues['SoDienThoai'], $id);
                }
                $phoneStmt->execute();
                $phoneExists = $phoneStmt->get_result()->num_rows > 0;
                $phoneStmt->close();

                if ($usernameExists) {
                    $errors[] = 'Tên đăng nhập đã được sử dụng.';
                }
                if ($cccdExists) {
                    $errors[] = 'CCCD đã tồn tại trong hồ sơ khách thuê khác.';
                }
                if ($phoneExists) {
                    $errors[] = 'Số điện thoại đã được sử dụng cho khách thuê khác.';
                }

                if (count($errors) === 0) {
                    $transactionStarted = false;
                    try {
                        $conn->begin_transaction();
                        $transactionStarted = true;

                        if ($action === 'create') {
                            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                            $accountStmt = $conn->prepare(
                                "INSERT INTO taikhoan
                                    (TenDangNhap, MatKhau, VaiTro, MaKhachThue, TrangThai, SoDienThoai)
                                 VALUES (?, ?, 'KhachThue', NULL, 'HoatDong', ?)"
                            );
                            $accountStmt->bind_param(
                                'sss',
                                $formValues['TenDangNhap'],
                                $passwordHash,
                                $formValues['SoDienThoai']
                            );
                            $accountStmt->execute();
                            $accountId = (int) $conn->insert_id;
                            $accountStmt->close();

                            $tenantStmt = $conn->prepare(
                                'INSERT INTO khachthue (HoTen, SoDienThoai, CCCD, MaTaiKhoan) VALUES (?, ?, ?, ?)'
                            );
                            $tenantStmt->bind_param(
                                'sssi',
                                $formValues['HoTen'],
                                $formValues['SoDienThoai'],
                                $formValues['CCCD'],
                                $accountId
                            );
                            $tenantStmt->execute();
                            $customerId = (int) $conn->insert_id;
                            $tenantStmt->close();

                            $linkStmt = $conn->prepare(
                                "UPDATE taikhoan
                                 SET MaKhachThue = ?
                                 WHERE MaTaiKhoan = ? AND VaiTro = 'KhachThue' AND MaKhachThue IS NULL"
                            );
                            $linkStmt->bind_param('ii', $customerId, $accountId);
                            $linkStmt->execute();
                            if ($linkStmt->affected_rows !== 1) {
                                throw new RuntimeException('Không thể liên kết tài khoản với hồ sơ khách thuê.');
                            }
                            $linkStmt->close();

                            $conn->commit();
                            $transactionStarted = false;
                            $_SESSION['csrf_quanlykhachthue'] = bin2hex(random_bytes(32));
                            $_SESSION['khachthue_message'] = array(
                                'type' => 'success',
                                'text' => 'Thêm khách thuê thành công.'
                            );
                            $redirect('thongtin.php?action=view&id=' . $customerId);
                        } else {
                            $accountId = (int) $customer['MaTaiKhoan'];
                            $linkAccountStmt = $conn->prepare(
                                "UPDATE taikhoan
                                 SET TenDangNhap = ?, SoDienThoai = ?, MaKhachThue = ?
                                 WHERE MaTaiKhoan = ? AND VaiTro = 'KhachThue'
                                   AND (MaKhachThue IS NULL OR MaKhachThue = ?)"
                            );
                            $linkAccountStmt->bind_param(
                                'ssiii',
                                $formValues['TenDangNhap'],
                                $formValues['SoDienThoai'],
                                $id,
                                $accountId,
                                $id
                            );
                            $linkAccountStmt->execute();
                            if ($linkAccountStmt->affected_rows === 0 &&
                                ((int) $customer['MaKhachThueTaiKhoan'] !== $id ||
                                 $customer['TenDangNhap'] !== $formValues['TenDangNhap'])) {
                                throw new RuntimeException('Không thể cập nhật tài khoản khách thuê.');
                            }
                            $linkAccountStmt->close();

                            $tenantStmt = $conn->prepare(
                                'UPDATE khachthue
                                 SET HoTen = ?, SoDienThoai = ?, CCCD = ?
                                 WHERE MaKhachThue = ? AND MaTaiKhoan = ?'
                            );
                            $tenantStmt->bind_param(
                                'sssii',
                                $formValues['HoTen'],
                                $formValues['SoDienThoai'],
                                $formValues['CCCD'],
                                $id,
                                $accountId
                            );
                            $tenantStmt->execute();
                            if ($tenantStmt->affected_rows === 0 &&
                                ($customer['HoTen'] !== $formValues['HoTen'] ||
                                 $customer['CCCD'] !== $formValues['CCCD'] ||
                                 $customer['SoDienThoai'] !== $formValues['SoDienThoai'])) {
                                throw new RuntimeException('Không thể cập nhật hồ sơ khách thuê.');
                            }
                            $tenantStmt->close();

                            $conn->commit();
                            $transactionStarted = false;
                            $_SESSION['csrf_quanlykhachthue'] = bin2hex(random_bytes(32));
                            $_SESSION['khachthue_message'] = array(
                                'type' => 'success',
                                'text' => 'Cập nhật thông tin khách thuê thành công.'
                            );
                            $redirect('thongtin.php?action=view&id=' . $id);
                        }
                    } catch (mysqli_sql_exception $exception) {
                        if ($transactionStarted) {
                            try {
                                $conn->rollback();
                            } catch (mysqli_sql_exception $rollbackException) {
                                error_log('Không thể rollback cập nhật khách thuê: ' . $rollbackException->getMessage());
                            }
                        }
                        error_log('Lỗi ghi dữ liệu khách thuê: ' . $exception->getMessage());
                        $errors[] = $exception->getCode() === 1062
                            ? 'Tên đăng nhập hoặc CCCD đã tồn tại.'
                            : 'Không thể lưu thông tin khách thuê. Vui lòng thử lại sau.';
                    } catch (RuntimeException $exception) {
                        if ($transactionStarted) {
                            try {
                                $conn->rollback();
                            } catch (mysqli_sql_exception $rollbackException) {
                                error_log('Không thể rollback cập nhật khách thuê: ' . $rollbackException->getMessage());
                            }
                        }
                        error_log('Lỗi liên kết khách thuê: ' . $exception->getMessage());
                        $errors[] = 'Không thể lưu đầy đủ liên kết khách thuê. Dữ liệu đã được hoàn tác.';
                    }
                }
            }
        } catch (mysqli_sql_exception $exception) {
            error_log('Lỗi kiểm tra dữ liệu khách thuê: ' . $exception->getMessage());
            $errors[] = 'Không thể kiểm tra dữ liệu khách thuê. Vui lòng thử lại sau.';
        }
    }
}

if ($isPost && $action === 'delete') {
    if (!$customer || $id < 1) {
        if (count($errors) === 0) {
            $errors[] = 'Không tìm thấy khách thuê cần xóa.';
        }
    } else {
        $submittedToken = $postString('csrf_token');
        $isConfirmed = $postString('confirm_delete') === '1';
        if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken) || !$isConfirmed) {
            $confirmDelete = true;
        } else {
            if ($customer['MaKhachThueTaiKhoan'] !== null &&
                (int) $customer['MaKhachThueTaiKhoan'] !== $id) {
                $errors[] = 'Liên kết tài khoản và hồ sơ không nhất quán; không thể xóa.';
            } else {
                $transactionStarted = false;
                try {
                    $engineStmt = $conn->prepare(
                        "SELECT COUNT(*) AS SoBangInnoDB
                         FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA = DATABASE()
                           AND TABLE_NAME IN (?, ?)
                           AND ENGINE = 'InnoDB'"
                    );
                    $tenantTable = 'khachthue';
                    $accountTable = 'taikhoan';
                    $engineStmt->bind_param('ss', $tenantTable, $accountTable);
                    $engineStmt->execute();
                    $engineCount = (int) $engineStmt->get_result()->fetch_assoc()['SoBangInnoDB'];
                    $engineStmt->close();

                    if ($engineCount !== 2) {
                        $errors[] = 'Không thể đảm bảo transaction: bảng khachthue và taikhoan cần dùng InnoDB.';
                    } else {
                        $conn->begin_transaction();
                        $transactionStarted = true;

                        $lockStmt = $conn->prepare(
                            'SELECT MaKhachThue FROM khachthue WHERE MaKhachThue = ? FOR UPDATE'
                        );
                        $lockStmt->bind_param('i', $id);
                        $lockStmt->execute();
                        $lockedCustomer = $lockStmt->get_result()->fetch_assoc();
                        $lockStmt->close();

                        if (!$lockedCustomer) {
                            throw new RuntimeException('Không tìm thấy hồ sơ khách thuê.');
                        }

                        $historyStmt = $conn->prepare(
                            "SELECT
                                EXISTS(SELECT 1 FROM hopdong WHERE MaKhachThue = ?) AS CoHopDong,
                                EXISTS(SELECT 1 FROM kiennghi WHERE MaKhachThue = ?) AS CoKienNghi,
                                EXISTS(
                                    SELECT 1 FROM hoadon hd
                                    INNER JOIN hopdong h ON h.MaHopDong = hd.MaHopDong
                                    WHERE h.MaKhachThue = ?
                                ) AS CoHoaDon,
                                EXISTS(
                                    SELECT 1 FROM thanhtoan tt
                                    INNER JOIN hoadon hd ON hd.MaHoaDon = tt.MaHoaDon
                                    INNER JOIN hopdong h ON h.MaHopDong = hd.MaHopDong
                                    WHERE h.MaKhachThue = ?
                                ) AS CoThanhToan,
                                EXISTS(
                                    SELECT 1 FROM thuchi tc
                                    INNER JOIN thanhtoan tt ON tt.MaThanhToan = tc.MaThanhToan
                                    INNER JOIN hoadon hd ON hd.MaHoaDon = tt.MaHoaDon
                                    INNER JOIN hopdong h ON h.MaHopDong = hd.MaHopDong
                                    WHERE h.MaKhachThue = ?
                                ) AS CoThuChi"
                        );
                        $historyStmt->bind_param('iiiii', $id, $id, $id, $id, $id);
                        $historyStmt->execute();
                        $history = $historyStmt->get_result()->fetch_assoc();
                        $historyStmt->close();

                        $hasHistory = (int) $history['CoHopDong'] === 1 ||
                            (int) $history['CoKienNghi'] === 1 ||
                            (int) $history['CoHoaDon'] === 1 ||
                            (int) $history['CoThanhToan'] === 1 ||
                            (int) $history['CoThuChi'] === 1;
                        $accountId = (int) $customer['MaTaiKhoan'];

                        if ($hasHistory) {
                            $lockAccountStmt = $conn->prepare(
                                "UPDATE taikhoan
                                 SET TrangThai = 'Khoa'
                                 WHERE MaTaiKhoan = ? AND VaiTro = 'KhachThue'"
                            );
                            $lockAccountStmt->bind_param('i', $accountId);
                            $lockAccountStmt->execute();
                            if ($lockAccountStmt->affected_rows === 0 &&
                                $customer['TrangThaiTaiKhoan'] !== 'Khoa') {
                                throw new RuntimeException('Không thể khóa tài khoản khách thuê.');
                            }
                            $lockAccountStmt->close();
                            $conn->commit();
                            $transactionStarted = false;
                            $_SESSION['csrf_quanlykhachthue'] = bin2hex(random_bytes(32));
                            $_SESSION['khachthue_message'] = array(
                                'type' => 'success',
                                'text' => 'Khách thuê có dữ liệu lịch sử. Hồ sơ được giữ lại và tài khoản đã bị khóa.'
                            );
                            $redirect('thongtin.php?action=view&id=' . $id);
                        }

                        $deleteAccountStmt = $conn->prepare(
                            "DELETE FROM taikhoan
                             WHERE MaTaiKhoan = ? AND VaiTro = 'KhachThue'
                               AND (MaKhachThue IS NULL OR MaKhachThue = ?)"
                        );
                        $deleteAccountStmt->bind_param('ii', $accountId, $id);
                        $deleteAccountStmt->execute();
                        if ($deleteAccountStmt->affected_rows !== 1) {
                            throw new RuntimeException('Không thể xóa tài khoản khách thuê đã liên kết.');
                        }
                        $deleteAccountStmt->close();

                        $deleteCustomerStmt = $conn->prepare(
                            'DELETE FROM khachthue WHERE MaKhachThue = ? AND MaTaiKhoan = ?'
                        );
                        $deleteCustomerStmt->bind_param('ii', $id, $accountId);
                        $deleteCustomerStmt->execute();
                        if ($deleteCustomerStmt->affected_rows !== 1) {
                            throw new RuntimeException('Không thể xóa hồ sơ khách thuê.');
                        }
                        $deleteCustomerStmt->close();

                        $conn->commit();
                        $transactionStarted = false;
                        $_SESSION['csrf_quanlykhachthue'] = bin2hex(random_bytes(32));
                        $_SESSION['khachthue_message'] = array(
                            'type' => 'success',
                            'text' => 'Đã xóa khách thuê và tài khoản liên kết.'
                        );
                        $redirect('thongtin.php?action=deleted');
                    }
                } catch (mysqli_sql_exception $exception) {
                    if ($transactionStarted) {
                        try {
                            $conn->rollback();
                        } catch (mysqli_sql_exception $rollbackException) {
                            error_log('Không thể rollback xóa khách thuê: ' . $rollbackException->getMessage());
                        }
                    }
                    error_log('Lỗi xóa khách thuê: ' . $exception->getMessage());
                    $errors[] = $exception->getCode() === 1451
                        ? 'Không thể xóa vì khách thuê đang được dữ liệu khác tham chiếu.'
                        : 'Không thể hoàn tất thao tác xóa. Vui lòng thử lại sau.';
                } catch (RuntimeException $exception) {
                    if ($transactionStarted) {
                        try {
                            $conn->rollback();
                        } catch (mysqli_sql_exception $rollbackException) {
                            error_log('Không thể rollback xóa khách thuê: ' . $rollbackException->getMessage());
                        }
                    }
                    error_log('Lỗi liên kết khi xóa khách thuê: ' . $exception->getMessage());
                    $errors[] = 'Không thể xóa đầy đủ dữ liệu khách thuê. Các thay đổi đã được hoàn tác.';
                }
            }
        }
    }
}

if (!$isPost && $action === 'delete' && $customer && count($errors) === 0) {
    $confirmDelete = true;
}

if ($isPost && !in_array($action, array('create', 'edit', 'delete'), true)) {
    $errors[] = 'Yêu cầu không hợp lệ.';
}

if ($action === 'edit' && !$isPost && $customer) {
    $formValues = array(
        'HoTen' => $customer['HoTen'],
        'CCCD' => $customer['CCCD'],
        'SoDienThoai' => $customer['SoDienThoai'],
        'TenDangNhap' => $customer['TenDangNhap']
    );
}

$conn->close();

$pageTitle = $action === 'create' ? 'Thêm khách thuê' :
    ($action === 'edit' ? 'Sửa thông tin khách thuê' :
    ($action === 'view' ? 'Chi tiết khách thuê' : 'Quản lý khách thuê'));
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
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $escape($pageTitle); ?> - HappyHome</title>
    <link rel="stylesheet" href="/HappyHome/assets/css/dashboard.css">
    <style>
        .tenant-content { max-width: 1000px; margin: 0 auto; }
        .tenant-title { display: flex; justify-content: space-between; align-items: center; gap: 14px; margin: 8px 0 20px; }
        .tenant-title h1 { color: #1e4e79; font-size: 24px; margin-bottom: 5px; }
        .tenant-title p { color: #777; font-size: 14px; }
        .tenant-card { background: #fff; border: 1px solid #e2e8ef; border-radius: 8px; padding: 24px; box-shadow: 0 2px 8px rgba(27, 55, 80, .04); }
        .tenant-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 17px 20px; }
        .tenant-field { display: flex; flex-direction: column; gap: 7px; }
        .tenant-field.full { grid-column: 1 / -1; }
        .tenant-field label { color: #344054; font-size: 13px; font-weight: 700; }
        .tenant-field input { width: 100%; min-height: 42px; padding: 10px 12px; border: 1px solid #d5dce5; border-radius: 6px; color: #27364b; font-size: 14px; }
        .tenant-field input:focus { outline: 0; border-color: #1e4e79; box-shadow: 0 0 0 3px rgba(30, 78, 121, .12); }
        .tenant-help { color: #7a8492; font-size: 12px; }
        .tenant-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-top: 8px; }
        .tenant-button { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 9px 15px; border: 1px solid transparent; border-radius: 6px; background: #1e4e79; color: #fff; font-size: 14px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .tenant-button:hover { background: #286090; }
        .tenant-button.secondary { background: #fff; border-color: #d5dce5; color: #344054; }
        .tenant-button.secondary:hover { background: #f7f9fb; }
        .tenant-button.danger { background: #b42318; }
        .tenant-button.danger:hover { background: #912018; }
        .tenant-alert { padding: 12px 15px; margin-bottom: 15px; border: 1px solid; border-radius: 6px; font-size: 14px; line-height: 1.55; }
        .tenant-alert.error { background: #fff5f4; border-color: #f3c6c2; color: #a1261c; }
        .tenant-alert.success { background: #effaf3; border-color: #b6e2c4; color: #18713c; }
        .tenant-alert.warning { background: #fff9e9; border-color: #f0dda6; color: #775b0b; }
        .tenant-alert ul { margin: 0; padding-left: 20px; }
        .tenant-details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 25px; }
        .tenant-detail { padding: 14px 0; border-bottom: 1px solid #edf0f3; }
        .tenant-detail-label { color: #7a8492; font-size: 12px; margin-bottom: 6px; }
        .tenant-detail-value { color: #26384b; font-size: 15px; font-weight: 600; overflow-wrap: anywhere; }
        .tenant-note { margin-top: 18px; padding: 13px 15px; border-radius: 6px; background: #f5f8fb; color: #586779; font-size: 13px; line-height: 1.6; }
        @media (max-width: 700px) {
            .tenant-card { padding: 17px; }
            .tenant-title { align-items: flex-start; flex-direction: column; }
            .tenant-form, .tenant-details { grid-template-columns: 1fr; }
            .tenant-field.full { grid-column: auto; }
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
                <h2><?php echo $escape($pageTitle); ?></h2>
                <p>Quản lý hồ sơ khách thuê HappyHome</p>
            </div>
            <div class="user-info">
                <div class="avatar">👤</div>
                <div>
                    <strong><?php echo $escape($tenDangNhap); ?></strong>
                    <p><?php echo $escape($vaiTro === 'Admin' ? 'Admin' : 'Quản lý'); ?></p>
                </div>
            </div>
        </header>

        <section class="tenant-content">
            <div class="tenant-title">
                <div>
                    <h1><?php echo $escape($pageTitle); ?></h1>
                    <p>Thông tin được lưu theo hồ sơ khách thuê và tài khoản đăng nhập.</p>
                </div>
                <a class="tenant-button secondary" href="index.php">← Danh sách khách thuê</a>
            </div>

            <?php if ($action !== 'deleted' && is_array($message) && isset($message['text'])): ?>
                <div class="tenant-alert <?php echo isset($message['type']) && $message['type'] === 'success' ? 'success' : 'warning'; ?>">
                    <?php echo $escape($message['text']); ?>
                </div>
            <?php endif; ?>

            <?php if (count($errors) > 0): ?>
                <div class="tenant-alert error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $escape($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($action === 'create' || $action === 'edit'): ?>
                <div class="tenant-card">
                    <form method="post" action="thongtin.php">
                        <input type="hidden" name="action" value="<?php echo $escape($action); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <?php endif; ?>
                        <div class="tenant-form">
                            <div class="tenant-field full">
                                <label for="HoTen">Họ và tên *</label>
                                <input id="HoTen" name="HoTen" type="text" maxlength="100" required
                                       value="<?php echo $escape($formValues['HoTen']); ?>" autocomplete="name">
                            </div>
                            <div class="tenant-field">
                                <label for="CCCD">CCCD *</label>
                                <input id="CCCD" name="CCCD" type="text" maxlength="20" inputmode="numeric" required
                                       value="<?php echo $escape($formValues['CCCD']); ?>">
                            </div>
                            <div class="tenant-field">
                                <label for="SoDienThoai">Số điện thoại *</label>
                                <input id="SoDienThoai" name="SoDienThoai" type="tel" maxlength="15" inputmode="numeric" required
                                       value="<?php echo $escape($formValues['SoDienThoai']); ?>" autocomplete="tel">
                            </div>
                            <div class="tenant-field full">
                                <label for="TenDangNhap">Tên đăng nhập *</label>
                                <input id="TenDangNhap" name="TenDangNhap" type="text" maxlength="50" required
                                       value="<?php echo $escape($formValues['TenDangNhap']); ?>" autocomplete="username">
                            </div>
                            <?php if ($action === 'create'): ?>
                                <div class="tenant-field">
                                    <label for="MatKhau">Mật khẩu *</label>
                                    <input id="MatKhau" name="MatKhau" type="password" minlength="8" required autocomplete="new-password">
                                    <span class="tenant-help">Tối thiểu 8 ký tự.</span>
                                </div>
                                <div class="tenant-field">
                                    <label for="XacNhanMatKhau">Xác nhận mật khẩu *</label>
                                    <input id="XacNhanMatKhau" name="XacNhanMatKhau" type="password" minlength="8" required autocomplete="new-password">
                                </div>
                            <?php else: ?>
                                <div class="tenant-field full">
                                    <span class="tenant-help">Để trống mật khẩu: chỉnh sửa hồ sơ không thay đổi mật khẩu hiện tại.</span>
                                </div>
                            <?php endif; ?>
                            <div class="tenant-field full">
                                <div class="tenant-actions">
                                    <button class="tenant-button" type="submit">
                                        <?php echo $action === 'create' ? 'Thêm khách thuê' : 'Lưu thay đổi'; ?>
                                    </button>
                                    <a class="tenant-button secondary" href="index.php">Hủy</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            <?php elseif ($action === 'view' && $customer): ?>
                <div class="tenant-card">
                    <div class="tenant-details">
                        <div class="tenant-detail">
                            <div class="tenant-detail-label">Mã khách thuê</div>
                            <div class="tenant-detail-value"><?php echo (int) $customer['MaKhachThue']; ?></div>
                        </div>
                        <div class="tenant-detail">
                            <div class="tenant-detail-label">Họ và tên</div>
                            <div class="tenant-detail-value"><?php echo $escape($customer['HoTen']); ?></div>
                        </div>
                        <div class="tenant-detail">
                            <div class="tenant-detail-label">CCCD</div>
                            <div class="tenant-detail-value"><?php echo $escape($customer['CCCD'] !== null && $customer['CCCD'] !== '' ? $customer['CCCD'] : '—'); ?></div>
                        </div>
                        <div class="tenant-detail">
                            <div class="tenant-detail-label">Số điện thoại</div>
                            <div class="tenant-detail-value"><?php echo $escape($customer['SoDienThoai']); ?></div>
                        </div>
                        <div class="tenant-detail">
                            <div class="tenant-detail-label">Tên đăng nhập</div>
                            <div class="tenant-detail-value"><?php echo $escape($customer['TenDangNhap']); ?></div>
                        </div>
                        <div class="tenant-detail">
                            <div class="tenant-detail-label">Trạng thái tài khoản</div>
                            <div class="tenant-detail-value">
                                <?php echo $customer['TrangThaiTaiKhoan'] === 'HoatDong' ? 'Hoạt động' : 'Đã khóa'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="tenant-actions">
                        <a class="tenant-button" href="thongtin.php?action=edit&amp;id=<?php echo (int) $customer['MaKhachThue']; ?>">Sửa thông tin</a>
                        <a class="tenant-button secondary" href="index.php">Quay lại danh sách</a>
                    </div>
                </div>
            <?php elseif ($action === 'delete' && $customer && $confirmDelete): ?>
                <div class="tenant-card">
                    <div class="tenant-alert warning">
                        Xác nhận xóa hồ sơ <strong><?php echo $escape($customer['HoTen']); ?></strong>?
                        Nếu khách thuê có hợp đồng, kiến nghị, hóa đơn hoặc giao dịch, hồ sơ sẽ được giữ lại và tài khoản bị khóa.
                    </div>
                    <form method="post" action="thongtin.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int) $customer['MaKhachThue']; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $escape($csrfToken); ?>">
                        <input type="hidden" name="confirm_delete" value="1">
                        <div class="tenant-actions">
                            <button class="tenant-button danger" type="submit">Xác nhận xóa</button>
                            <a class="tenant-button secondary" href="index.php">Hủy</a>
                        </div>
                    </form>
                </div>
            <?php elseif ($action === 'deleted'): ?>
                <div class="tenant-card">
                    <div class="tenant-alert success">
                        <?php echo is_array($message) && isset($message['text']) ? $escape($message['text']) : 'Thao tác đã hoàn tất.'; ?>
                    </div>
                    <a class="tenant-button" href="index.php">Về danh sách khách thuê</a>
                </div>
            <?php elseif (count($errors) > 0): ?>
                <div class="tenant-card">
                    <a class="tenant-button secondary" href="index.php">Quay lại danh sách</a>
                </div>
            <?php endif; ?>
        </section>
        <footer>© 2026 HappyHome - Hệ thống quản lý nhà trọ</footer>
    </main>
</div>
</body>
</html>