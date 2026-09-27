<?php
/**
 * API địa chỉ Việt Nam - WinK Shoe Store
 * Serve dữ liệu 34 tỉnh/thành + xã/phường từ file JSON local
 * Nguồn: github.com/vietmap-company/vietnam_administrative_address
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: public, max-age=86400'); // Cache 1 ngày

$action = $_GET['action'] ?? 'provinces';
$code   = $_GET['code'] ?? '';

$provinceFile = dirname(__DIR__) . '/assets/js/province.json';
$wardFile     = dirname(__DIR__) . '/assets/js/ward.json';

if ($action === 'provinces') {
    if (!file_exists($provinceFile)) {
        echo json_encode(['error' => 'province.json not found']);
        exit;
    }
    $data = json_decode(file_get_contents($provinceFile), true);
    // Chuyển object thành array, sắp xếp theo tên
    $list = array_values($data);
    usort($list, fn($a, $b) => strcmp($a['name'], $b['name']));
    echo json_encode($list, JSON_UNESCAPED_UNICODE);

} elseif ($action === 'wards' && $code !== '') {
    if (!file_exists($wardFile)) {
        echo json_encode(['error' => 'ward.json not found']);
        exit;
    }
    $data = json_decode(file_get_contents($wardFile), true);
    // Lọc xã theo parent_code
    $wards = array_values(array_filter($data, fn($w) => $w['parent_code'] === $code));
    usort($wards, fn($a, $b) => strcmp($a['name'], $b['name']));
    echo json_encode($wards, JSON_UNESCAPED_UNICODE);

} else {
    echo json_encode(['error' => 'Invalid action']);
}
