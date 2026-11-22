<?php
require_once __DIR__ . "/../../../includes/nhanvien.php";

$maNhanVien = (int) $nhanVien["MaNhanVien"];
$maCongViec = (int) ($_GET["id"] ?? 0);
$message = "";
$error = "";

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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $trangThai = $_POST["trang_thai"] ?? "";
    $ghiChu = trim($_POST["ghi_chu"] ?? "");
    $ketQua = trim($_POST["ket_qua"] ?? "");
    $allowed = ["ChoXuLy", "DangXuLy", "HoanThanh"];

    if (!in_array($trangThai, $allowed, true)) {
        $error = "Trạng thái không hợp lệ.";
    } elseif ($trangThai === "HoanThanh" && $ketQua === "") {
        $error = "Vui lòng nhập kết quả thực hiện trước khi hoàn thành.";
    } else {
        $ngayHoanThanh = $trangThai === "HoanThanh" ? date("Y-m-d H:i:s") : null;
        $stmt = $conn->prepare("UPDATE congviec SET TrangThai = ?, GhiChu = ?, KetQua = ?, NgayHoanThanh = ? WHERE MaCongViec = ? AND MaNhanVien = ?");
        $stmt->bind_param("ssssii", $trangThai, $ghiChu, $ketQua, $ngayHoanThanh, $maCongViec, $maNhanVien);
        if ($stmt->execute()) {
            $message = "Cập nhật công việc thành công.";
        } else {
            $error = "Không thể cập nhật công việc.";
        }
        $stmt->close();
    }
}

$stmt = $conn->prepare("SELECT cv.*, p.TenPhong FROM congviec cv LEFT JOIN phong p ON p.MaPhong = cv.MaPhong WHERE cv.MaCongViec = ? AND cv.MaNhanVien = ? LIMIT 1");
$stmt->bind_param("ii", $maCongViec, $maNhanVien);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    hienThiKhungNhanVien("Chi tiết công việc", "tasks");
    echo '<div class="notice error">Không tìm thấy công việc hoặc công việc không thuộc tài khoản của bạn.</div>';
    hienThiDongKhungNhanVien();
    exit();
}

hienThiKhungNhanVien("Chi tiết công việc", "tasks");
?>
<?php if ($message): ?><div class="notice success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="detail-grid">
    <div class="panel">
        <div class="panel-header">
            <h3>#CV<?php echo (int) $task["MaCongViec"]; ?> - <?php echo htmlspecialchars($task["TenCongViec"]); ?></h3>
            <span class="status <?php echo lopTrangThai($task["TrangThai"]); ?>"><?php echo tenTrangThai($task["TrangThai"]); ?></span>
        </div>
        <div class="detail-list">
            <div class="detail-item"><span>Mô tả chi tiết</span><strong><?php echo nl2br(htmlspecialchars($task["MoTa"] ?: "Không có mô tả")); ?></strong></div>
            <div class="detail-item"><span>Phòng / khu vực</span><strong><?php echo htmlspecialchars($task["TenPhong"] ?: "Khu vực chung"); ?></strong></div>
            <div class="detail-item"><span>Ngày giao</span><strong><?php echo date("d/m/Y H:i", strtotime($task["NgayGiao"])); ?></strong></div>
            <div class="detail-item"><span>Hạn hoàn thành</span><strong><?php echo $task["HanHoanThanh"] ? date("d/m/Y H:i", strtotime($task["HanHoanThanh"])) : "Chưa đặt"; ?></strong></div>
            <div class="detail-item"><span>Mức độ ưu tiên</span><strong><?php echo htmlspecialchars($task["MucDoUuTien"] ?: "Bình thường"); ?></strong></div>
            <div class="detail-item"><span>Bộ phận</span><strong><?php echo $nhanVien["LoaiNhanVien"] === "KyThuat" ? "Nhân viên kỹ thuật" : "Nhân viên vệ sinh"; ?></strong></div>
        </div>

        <?php if ($nhanVien["LoaiNhanVien"] === "KyThuat"): ?>
            <hr style="border:0;border-top:1px solid #eef2f7;margin:20px 0">
            <h3>Thông tin kỹ thuật</h3>
            <div class="detail-list" style="margin-top:14px">
                <div class="detail-item"><span>Sự cố / thiết bị</span><strong><?php echo htmlspecialchars($task["MoTa"] ?: "Chưa có thông tin"); ?></strong></div>
                <div class="detail-item"><span>Phương án sửa chữa</span><strong>Kiểm tra thiết bị, ghi nhận nguyên nhân và xác nhận kết quả sau khi xử lý.</strong></div>
            </div>
        <?php else: ?>
            <hr style="border:0;border-top:1px solid #eef2f7;margin:20px 0">
            <h3>Checklist vệ sinh</h3>
            <div class="checklist" id="cleaningChecklist">
                <label><input type="checkbox"> Lau dọn sàn và các bề mặt</label>
                <label><input type="checkbox"> Vệ sinh nhà tắm và nhà vệ sinh</label>
                <label><input type="checkbox"> Kiểm tra và thu gom rác</label>
                <label><input type="checkbox"> Bố trí khu vực gọn gàng</label>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h3>Cập nhật công việc</h3>
        <form method="post" style="margin-top:16px">
            <div class="form-group">
                <label for="trang_thai">Trạng thái</label>
                <select class="form-control" id="trang_thai" name="trang_thai">
                    <option value="ChoXuLy" <?php echo $task["TrangThai"] === "ChoXuLy" ? "selected" : ""; ?>>Chờ xử lý</option>
                    <option value="DangXuLy" <?php echo $task["TrangThai"] === "DangXuLy" ? "selected" : ""; ?>>Đang thực hiện</option>
                    <option value="HoanThanh" <?php echo $task["TrangThai"] === "HoanThanh" ? "selected" : ""; ?>>Xác nhận hoàn thành</option>
                </select>
            </div>
            <div class="form-group">
                <label for="ghi_chu">Ghi chú quá trình</label>
                <textarea class="form-control" id="ghi_chu" name="ghi_chu" rows="4"><?php echo htmlspecialchars($task["GhiChu"] ?? ""); ?></textarea>
            </div>
            <div class="form-group">
                <label for="ket_qua">Kết quả thực hiện</label>
                <textarea class="form-control" id="ket_qua" name="ket_qua" rows="4"><?php echo htmlspecialchars($task["KetQua"] ?? ""); ?></textarea>
            </div>
            <button class="btn" type="submit">Lưu cập nhật</button>
        </form>
    </div>
</div>
<script>
const checklist = document.getElementById("cleaningChecklist");
if (checklist) {
    checklist.addEventListener("change", () => {
        const items = checklist.querySelectorAll('input[type="checkbox"]');
        const done = [...items].filter(item => item.checked).length;
        document.querySelector('[name="ket_qua"]').value = "Checklist vệ sinh: " + done + "/" + items.length + " hạng mục đã hoàn thành.";
    });
}
</script>
<?php hienThiDongKhungNhanVien(); ?>