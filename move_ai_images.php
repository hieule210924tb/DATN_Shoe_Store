<?php
/**
 * Script copy hình ảnh AI vào thư mục uploads/products
 */
require_once __DIR__ . '/config/config.php';

$aiImagesDir = 'C:\Users\admin\.gemini\antigravity-ide\brain\0356ddb7-4d45-4750-acf5-c41d0ba6c673';
$uploadDir = UPLOAD_PATH . '/products';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Map the generated file names (with timestamp) to our standard names
$filesToCopy = [
    'shoe_nike_1790152341904.png' => 'shoe_nike.png',
    'shoe_adidas_1790152353709.png' => 'shoe_adidas.png',
    'shoe_classic_1790152372935.png' => 'shoe_classic.png',
    'shoe_basketball_1790152391288.png' => 'shoe_basketball.png'
];

$successCount = 0;
echo "<h2>Đang copy hình ảnh AI...</h2>";
echo "<ul>";

foreach ($filesToCopy as $sourceName => $destName) {
    $sourcePath = $aiImagesDir . '\\' . $sourceName;
    $destPath = $uploadDir . '/' . $destName;
    
    if (file_exists($sourcePath)) {
        if (copy($sourcePath, $destPath)) {
            echo "<li style='color:green;'>Đã copy thành công: <b>$destName</b></li>";
            $successCount++;
        } else {
            echo "<li style='color:red;'>Lỗi khi copy: $destName</li>";
        }
    } else {
        echo "<li style='color:orange;'>Không tìm thấy file nguồn: $sourcePath</li>";
    }
}

echo "</ul>";
echo "<h3>Hoàn tất! Đã copy $successCount/4 hình ảnh.</h3>";
echo "<a href='index.php'>Quay lại trang chủ</a>";
