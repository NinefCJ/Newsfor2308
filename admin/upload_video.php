<?php
// admin/upload_video.php - 视频上传处理
session_start();
require_once '../includes/config.php';

// 检查是否登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    die(json_encode(['error' => '未授权访问']));
}

// 检查是否有文件上传
if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['error' => '没有上传文件或上传出错']));
}

// 允许的视频类型
$allowed_types = [
    'video/mp4',
    'video/webm',
    'video/ogg',
    'video/quicktime',
    'video/x-msvideo',
    'video/x-flv',
    'video/3gpp',
    'video/x-matroska'
];

$file = $_FILES['video'];

// 检查文件类型
$file_type = mime_content_type($file['tmp_name']);
if (!in_array($file_type, $allowed_types)) {
    // 尝试通过文件扩展名检查
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['mp4', 'webm', 'ogg', 'mov', 'avi', 'flv', '3gp', 'mkv'];
    
    if (!in_array($ext, $allowed_ext)) {
        http_response_code(400);
        die(json_encode(['error' => '不支持的视频格式: ' . $ext]));
    }
}

// 检查文件大小（限制为50MB）
$max_size = 50 * 1024 * 1024; // 50MB
if ($file['size'] > $max_size) {
    http_response_code(400);
    die(json_encode(['error' => '视频文件太大，最大支持50MB']));
}

// 创建视频上传目录
$video_dir = '../assets/uploads/videos/';
$thumb_dir = '../assets/uploads/videos/thumbs/';

if (!is_dir($video_dir)) {
    mkdir($video_dir, 0755, true);
}
if (!is_dir($thumb_dir)) {
    mkdir($thumb_dir, 0755, true);
}

// 生成安全的文件名
$original_name = basename($file['name']);
$file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
$safe_name = 'video_' . date('Ymd_His') . '_' . uniqid() . '.' . $file_extension;
$target_path = $video_dir . $safe_name;

// 移动上传的文件
if (move_uploaded_file($file['tmp_name'], $target_path)) {
    // 生成视频缩略图
    $thumb_path = $thumb_dir . 'thumb_' . str_replace('.' . $file_extension, '.jpg', $safe_name);
    if (generate_video_thumbnail($target_path, $thumb_path)) {
        $has_thumb = 'assets/uploads/videos/thumbs/thumb_' . str_replace('.' . $file_extension, '.jpg', $safe_name);
    } else {
        $has_thumb = 'assets/img/video-placeholder.png';
    }
    
    // 获取视频信息
    $video_info = get_video_info($target_path);
    
    // 返回给前端
    $response = [
        'success' => true,
        'url' => 'assets/uploads/videos/' . $safe_name,
        'thumb' => $has_thumb,
        'name' => $original_name,
        'size' => format_bytes($file['size']),
        'duration' => $video_info['duration'] ?? 0,
        'dimensions' => $video_info['dimensions'] ?? '未知'
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
} else {
    http_response_code(500);
    die(json_encode(['error' => '视频保存失败']));
}

// 生成视频缩略图函数
function generate_video_thumbnail($video_path, $thumb_path, $time = 2) {
    if (!function_exists('shell_exec')) {
        return false;
    }
    
    $ffmpeg_path = 'ffmpeg'; // 假设ffmpeg在系统PATH中
    
    // 获取视频时长
    $duration_cmd = "$ffmpeg_path -i " . escapeshellarg($video_path) . " 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//";
    $duration = shell_exec($duration_cmd);
    
    if (!$duration) {
        return false;
    }
    
    // 在视频的第2秒截图
    $cmd = "$ffmpeg_path -ss $time -i " . escapeshellarg($video_path) . " -vframes 1 -vf 'scale=320:-1' -q:v 2 " . escapeshellarg($thumb_path) . " 2>&1";
    
    shell_exec($cmd);
    
    return file_exists($thumb_path);
}

// 获取视频信息函数
function get_video_info($video_path) {
    if (!function_exists('shell_exec')) {
        return ['duration' => 0, 'dimensions' => '未知'];
    }
    
    $ffmpeg_path = 'ffmpeg';
    $cmd = "$ffmpeg_path -i " . escapeshellarg($video_path) . " 2>&1";
    $output = shell_exec($cmd);
    
    $info = ['duration' => 0, 'dimensions' => '未知'];
    
    // 提取时长
    if (preg_match('/Duration: (\d{2}):(\d{2}):(\d{2})\.\d{2}/', $output, $matches)) {
        $hours = intval($matches[1]);
        $minutes = intval($matches[2]);
        $seconds = intval($matches[3]);
        $info['duration'] = $hours * 3600 + $minutes * 60 + $seconds;
    }
    
    // 提取分辨率
    if (preg_match('/(\d{3,4})x(\d{3,4})/', $output, $matches)) {
        $info['dimensions'] = $matches[1] . '×' . $matches[2];
    }
    
    return $info;
}

// 格式化字节大小
function format_bytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>
