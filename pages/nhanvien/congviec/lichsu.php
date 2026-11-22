<?php
require_once __DIR__ . "/../../../includes/nhanvien.php";

$maNhanVien = (int) $nhanVien["MaNhanVien"];
$search = trim($_GET["q"] ?? "");
$from = $_GET["tu_ngay"] ?? "";
$to = $_GET["den_ngay"] ?? "";

$sql = "SELECT cv.*, p.TenPhong
        FROM congviec cv
        LEFT JOIN phong p ON p.MaPhong = cv.MaPhong
        WHERE cv.MaNhanVien = ? AND cv.TrangThai = 'HoanThanh'";
$params = [$maNhanVien];
$types = "i";
if ($search !== "") {
    $sql .= " AND (cv.TenCongViec LIKE ? OR cv.KetQua LIKE ? OR p.TenPhong LIKE ?)";
    $types .= "sss";
    $needle = "%" . $search . "%";
    $params[] = $needle; $params[] = $needle; $params[] = $needle;
}
if ($from !== "") {
    $sql .= " AND DATE(cv.NgayHoanThanh) >= ?";
    $types .= "s"; $params[] = $from;
}
if ($to !== "") {
    $sql .= " AND DATE(cv.NgayHoanThanh) <= ?";
    $types .= "s"; $params[] = $to;
}
$sql .= " ORDER BY cv.NgayHoanThanh DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$history = $stmt->get_result();

hienThiKhungNhanVien("Lịch sử công việc", "history");
?>
<div class="panel">
    <form class="toolbar" method="get">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm công việc, phòng hoặc kết quả">
        <input type="date" name="tu_ngay" value="<?php echo htmlspecialchars($from); ?>" aria-label="Từ ngày">
        <input type="date" name="den_ngay" value="<?php echo htmlspecialchars($to); ?>" aria-label="Đến ngày">
        <button class="btn" type="submit">Tìm kiếm</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Tên công việc</th><th>Phòng / khu vực</th><th>Ngày hoàn thành</th><th>Kết quả thực hiện</th><th>Ghi chú</th></tr></thead>
            <tbody>
            <?php if ($history->num_rows === 0): ?>
                <tr><td colspan="5">Không có công việc đã hoàn thành.</td></tr>
            <?php else:
                while ($task = $history->fetch_assoc()):
            ?>
                <tr>
                    <td><?php echo htmlspecialchars($task["TenCongViec"]); ?></td>
                    <td><?php echo htmlspecialchars($task["TenPhong"] ?: "Khu vực chung"); ?></td>
                    <td><?php echo $task["NgayHoanThanh"] ? date("d/m/Y H:i", strtotime($task["NgayHoanThanh"])) : "-"; ?></td>
                    <td><?php echo nl2br(htmlspecialchars($task["KetQua"] ?: "-")); ?></td>
                    <td><?php echo nl2br(htmlspecialchars($task["GhiChu"] ?: "-")); ?></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$stmt->close();
hienThiDongKhungNhanVien();