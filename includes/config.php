<?php
// includes/config.php - 修复数据库连接问题

// 开发阶段开启错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

// InfinityFree 主机数据库配置 - 请根据您的实际信息修改
// 常见配置示例：
// define('DB_SERVER', 'sqlXXX.epizy.com');  // InfinityFree 通常使用这种格式
// define('DB_SERVER', 'localhost');         // 也可能是 localhost

// 重要：在 InfinityFree 控制面板中查找正确的数据库信息
define('DB_SERVER', 'sql102.infinityfree.com'); // 尝试这个，如果不行再试 sqlXXX.epizy.com
define('DB_USERNAME', 'if0_41141237');    // 您的数据库用户名
define('DB_PASSWORD', '219012cao');    // 您的数据库密码
define('DB_NAME', 'if0_41141237_news2308'); // 完整的数据库名

// 创建数据库连接
try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    
    // 检查连接
    if ($conn->connect_error) {
        // 抛出异常，而不是默默失败
        throw new Exception("数据库连接失败: " . $conn->connect_error . 
                          "<br>请检查 config.php 中的配置是否正确。<br>" .
                          "服务器: " . DB_SERVER . "<br>" .
                          "用户名: " . DB_USERNAME . "<br>" .
                          "数据库: " . DB_NAME);
    }
    
    // 设置字符集
    if (!$conn->set_charset("utf8mb4")) {
        throw new Exception("字符集设置失败: " . $conn->error);
    }
    
    // 设置时区
    date_default_timezone_set('Asia/Shanghai');
    
    // 连接成功标记
    define('DB_CONNECTED', true);
    
} catch (Exception $e) {
    // 显示详细的错误信息
    die("<div style='padding:20px; border:2px solid red; margin:20px;'>
            <h2>数据库连接错误</h2>
            <p>" . $e->getMessage() . "</p>
            <h3>如何解决：</h3>
            <ol>
                <li>登录 InfinityFree 控制面板</li>
                <li>找到 MySQL 数据库部分</li>
                <li>查看正确的服务器地址、用户名、密码和数据库名</li>
                <li>更新 config.php 中的配置</li>
            </ol>
            <p><a href='javascript:location.reload()'>刷新页面</a> | 
               <a href='index.php'>返回首页</a></p>
         </div>");
}
?>
