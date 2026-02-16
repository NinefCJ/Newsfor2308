<?php
// admin/upload_image.php - TinyMCE 图片上传处理
session_start();
require_once '../includes/config.php';

// 检查是否登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(['error' => '未授权访问']));
}

// 检查是否有文件上传
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['error' => '没有上传文件或上传出错']));
}

// 允许的文件类型
$allowed_types = [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/bmp',
    'image/svg+xml'
];

$file = $_FILES['file'];

// 检查文件类型
$file_type = mime_content_type($file['tmp_name']);
if (!in_array($file_type, $allowed_types)) {
    http_response_code(400);
    die(json_encode(['error' => '不支持的文件类型: ' . $file_type]));
}

// 检查文件大小（限制为5MB）
$max_size = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $max_size) {
    http_response_code(400);
    die(json_encode(['error' => '文件太大，最大支持5MB']));
}

// 创建上传目录
$upload_dir = '../assets/uploads/';
$thumb_dir = '../assets/uploads/thumbs/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}
if (!is_dir($thumb_dir)) {
    mkdir($thumb_dir, 0755, true);
}

// 生成安全的文件名
$original_name = basename($file['name']);
$file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$safe_name = 'img_' . date('Ymd_His') . '_' . uniqid() . '.' . $file_extension;
$target_path = $upload_dir . $safe_name;

// 移动上传的文件
if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // 生成缩略图
    $thumb_path = $thumb_dir . 'thumb_' . $safe_name;
    create_thumbnail($target_path, $thumb_path, 300, 300);
    
    // 返回给 TinyMCE
    $response = [
        'location' => 'assets/uploads/' . $safe_name,
        'thumb' => 'assets/uploads/thumbs/thumb_' . $safe_name,
        'name' => $original_name,
        'size' => $file['size']
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    http_response_code(500);
    die(json_encode(['error' => '文件保存失败']));
}

// 生成缩略图函数
function create_thumbnail($source, $destination, $width, $height) {
    $info = getimagesize($source);
    
    if (!$info) {
        return false;
    }
    
    $mime = $info['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        case 'image/bmp':
            $image = imagecreatefrombmp($source);
            break;
        default:
            return false;
    }
    
    if (!$image) {
        return false;
    }
    
    $src_width = imagesx($image);
    $src_height = imagesy($image);
    
    // 计算缩略图尺寸
    $ratio = min($width/$src_width, $height/$src_height);
    $new_width = floor($src_width * $ratio);
    $new_height = floor($src_height * $ratio);
    
    // 创建新图像
    $thumb = imagecreatetruecolor($new_width, $new_height);
    
    // 透明背景处理
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
        imagefilledrectangle($thumb, 0, 0, $new_width, $new_height, $transparent);
    }
    
    // 调整大小
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $new_width, $new_height, $src_width, $src_height);
    
    // 保存缩略图
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($thumb, $destination, 85);
            break;
        case 'image/png':
            imagepng($thumb, $destination, 9);
            break;
        case 'image/gif':
            imagegif($thumb, $destination);
            break;
        case 'image/webp':
            imagewebp($thumb, $destination, 85);
            break;
        case 'image/bmp':
            imagebmp($thumb, $destination);
            break;
    }
    
    imagedestroy($image);
    imagedestroy($thumb);
    
    return true;
}
?>
