<?php
/**
 * 后台访问权限验证模块
 * 将此文件包含在需要登录才能访问的后台页面最开头
 */
session_start();

// 检查会话中是否存在已登录的标志
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // 用户未登录，重定向到登录页面
    header('Location: login.php');
    exit(); // 确保脚本停止执行
}

// （可选）可以在这里添加更多的权限检查，例如检查用户角色等
?>
