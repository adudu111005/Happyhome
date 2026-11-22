<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['vaiTro'])) {
    header("Location: /HappyHome/welcome.php");
    exit();
}

$vaiTro = $_SESSION['vaiTro'];

switch ($vaiTro) {
    case 'QuanLy':
    case 'Admin':
        header("Location: /HappyHome/pages/admin/index.php");
        exit();

    case 'NhanVien':
        header("Location: /HappyHome/pages/quanlynhanvien/dashboard.php");
        exit();

    case 'KhachThue':
        header("Location: /HappyHome/pages/quanlykhachthue/index.php");
        exit();

    default:
        session_unset();
        session_destroy();
        header("Location: /HappyHome/welcome.php");
        exit();
}