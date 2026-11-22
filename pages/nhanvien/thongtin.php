<?php
require_once __DIR__ . "/../../includes/nhanvien.php";

hienThiKhungNhanVien("Thông tin cá nhân", "profile");
?>
<div class="panel" style="max-width:760px">
    <div class="panel-header">
        <h3>Hồ sơ nhân viên</h3>
        <span class="status progress"><?php echo $nhanVien["LoaiNhanVien"] === "KyThuat" ? "Nhân viên kỹ thuật" : "Nhân viên vệ sinh"; ?></span>
    </div>
    <div class="detail-list">
        <div class="detail-item"><span>Mã nhân viên</span><strong>NV<?php echo (int) $nhanVien["MaNhanVien"]; ?></strong></div>
        <div class="detail-item"><span>Họ tên</span><strong><?php echo htmlspecialchars($nhanVien["HoTen"]); ?></strong></div>
        <div class="detail-item"><span>Số điện thoại</span><strong><?php echo htmlspecialchars($nhanVien["SoDienThoai"] ?: "Chưa cập nhật"); ?></strong></div>
        <div class="detail-item"><span>Email</span><strong><?php echo htmlspecialchars($nhanVien["Email"] ?: "Chưa cập nhật"); ?></strong></div>
        <div class="detail-item"><span>Bộ phận</span><strong><?php echo $nhanVien["LoaiNhanVien"] === "KyThuat" ? "Kỹ thuật" : "Vệ sinh"; ?></strong></div>
        <div class="detail-item"><span>Vai trò</span><strong>Nhân viên</strong></div>
        <div class="detail-item"><span>Ngày bắt đầu làm việc</span><strong><?php echo $nhanVien["NgayVaoLam"] ? date("d/m/Y", strtotime($nhanVien["NgayVaoLam"])) : "Chưa cập nhật"; ?></strong></div>
        <div class="detail-item"><span>Tên đăng nhập</span><strong><?php echo htmlspecialchars($nhanVien["TenDangNhap"]); ?></strong></div>
    </div>
</div>
<?php hienThiDongKhungNhanVien(); ?>