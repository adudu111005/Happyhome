<?php
require_once __DIR__ . "/../../../includes/nhanvien.php";

$maNhanVien = (int) $nhanVien["MaNhanVien"];
$filter = $_GET["trang_thai"] ?? "TatCa";
$search = trim($_GET["q"] ?? "");
$allowed = ["TatCa", "ChoXuLy", "DangXuLy", "HoanThanh"];
if (!in_array($filter, $allowed, true)) $filter = "TatCa";

$sql = "SELECT cv.*, p.TenPhong
        FROM congviec cv
        LEFT JOIN phong p ON p.MaPhong = cv.MaPhong
        WHERE cv.MaNhanVien = ?";
$params = [$maNhanVien];
$types = "i";
if ($filter !== "TatCa") {
    $sql .= " AND cv.TrangThai = ?";
    $types .= "s";
    $params[] = $filter;
}
if ($search !== "") {
    $sql .= " AND (cv.TenCongViec LIKE ? OR p.TenPhong LIKE ?)";
    $types .= "ss";
    $needle = "%" . $search . "%";
    $params[] = $needle;
    $params[] = $needle;
}
$sql .= " ORDER BY cv.NgayGiao DESC, cv.MaCongViec DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result();

hienThiKhungNhanVien("Danh sách công việc", "tasks");
?>
<div class="panel">
    <form class="toolbar" method="get">
        <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="Tìm tên công việc hoặc phòng">
        <?php foreach (["TatCa" => "Tất cả", "ChoXuLy" => "Chờ xử lý", "DangXuLy" => "Đang thực hiện", "HoanThanh" => "Đã hoàn thành"] as $value => $label): ?>
            <button type="submit" name="trang_thai" value="<?php echo $value; ?>" class="filter-btn <?php echo $filter === $value ? "active" : ""; ?>"><?php echo $label; ?></button>
        <?php endforeach; ?>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Mã công việc</th><th>Tên công việc</th><th>Phòng / khu vực</th><th>Ngày giao</th><th>Hạn hoàn thành</th><th>Ưu tiên</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
            <tbody>
            <?php if ($tasks->num_rows === 0): ?>
                <tr><td colspan="8">Không có công việc phù hợp.</td></tr>
            <?php else:
                while ($task = $tasks->fetch_assoc()):
            ?>
                <tr>
                    <td>#CV<?php echo (int) $task["MaCongViec"]; ?></td>
                    <td><?php echo htmlspecialchars($task["TenCongViec"]); ?></td>
                    <td><?php echo htmlspecialchars($task["TenPhong"] ?: "Khu vực chung"); ?></td>
                    <td><?php echo $task["NgayGiao"] ? date("d/m/Y", strtotime($task["NgayGiao"])) : "-"; ?></td>
                    <td><?php echo $task["HanHoanThanh"] ? date("d/m/Y", strtotime($task["HanHoanThanh"])) : "-"; ?></td>
                    <td><?php echo htmlspecialchars($task["MucDoUuTien"] ?? "Bình thường"); ?></td>
                    <td><span class="status <?php echo lopTrangThai($task["TrangThai"]); ?>"><?php echo tenTrangThai($task["TrangThai"]); ?></span></td>
                    <td><a class="btn btn-secondary" href="chitiet.php?id=<?php echo (int) $task["MaCongViec"]; ?>">Xem</a></td>
                </tr>
            <?php endwhile; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$stmt->close();
hienThiDongKhungNhanVien();