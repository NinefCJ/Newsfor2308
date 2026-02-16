<?php
/**
 * 增加文章浏览量
 * @param mysqli $conn 数据库连接
 * @param int $article_id 文章ID
 */
function increase_view_count($conn, $article_id) {
    $article_id = intval($article_id);
    // 使用预处理语句防止SQL注入，并提升性能
    $stmt = $conn->prepare("UPDATE articles SET view_count = view_count + 1 WHERE id = ?");
    $stmt->bind_param("i", $article_id);
    $stmt->execute();
    $stmt->close();
}

/**
 * 获取文章摘要（优化版本）
 * @param string $content 文章内容
 * @param int $length 摘要长度
 * @return string 清理后的摘要
 */
function get_excerpt($content, $length = 150) {
    // 去除HTML标签和换行，获取纯文本
    $text = strip_tags($content);
    $text = str_replace(["\r", "\n"], ' ', $text);
    $text = trim($text);

    // 使用mb_substr处理中文字符，避免乱码
    if (mb_strlen($text, 'UTF-8') > $length) {
        $text = mb_substr($text, 0, $length, 'UTF-8') . '...';
    }
    return $text;
}

/**
 * 安全地输出变量，防止XSS攻击
 * @param mixed $data 要输出的数据
 * @return string 安全的HTML字符串
 */
function safe_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}
?>
