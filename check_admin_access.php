<?php
// check_admin_access.php - 管理员访问诊断脚本
require_once 'includes/config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>管理员登录问题诊断</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        .container { max-width: 800px; margin-top: 50px; }
        .card { margin-bottom: 20px; }
        .success { color: #198754; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
<div class='container'>
<h2 class='mb-4'>🔧 管理员登录问题诊断</h2>";

// 1. 检查数据库连接
echo "<div class='card'>
        <div class='card-header'>1. 数据库连接检查</div>
        <div class='card-body'>";

if (!$conn) {
    echo "<p class='error'>✗ 数据库连接对象为空</p>";
} elseif ($conn->connect_error) {
    echo "<p class='error'>✗ 数据库连接失败: " . $conn->connect_error . "</p>";
} else {
    echo "<p class='success'>✓ 数据库连接成功</p>";
    echo "<p>服务器: " . DB_SERVER . "<br>
             数据库: " . DB_NAME . "<br>
             字符集: " . $conn->character_set_name() . "</p>";
}

echo "</div></div>";

// 2. 检查admins表是否存在
echo "<div class='card'>
        <div class='card-header'>2. 管理员表检查</div>
        <div class='card-body'>";

$table_check = $conn->query("SHOW TABLES LIKE 'admins'");
if ($table_check && $table_check->num_rows > 0) {
    echo "<p class='success'>✓ admins 表存在</p>";
    
    // 3. 检查管理员记录
    $sql = "SELECT id, username, password_hash, created_at FROM admins";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo "<p class='success'>✓ 找到管理员记录: " . $result->num_rows . " 条</p>";
        
        echo "<table class='table table-sm'>
                <thead>
                    <tr><th>ID</th><th>用户名</th><th>密码哈希（前20位）</th><th>创建时间</th></tr>
                </thead>
                <tbody>";
        
        while($row = $result->fetch_assoc()) {
            $hash_preview = substr($row['password_hash'], 0, 20) . '...';
            echo "<tr>
                    <td>" . $row['id'] . "</td>
                    <td>" . htmlspecialchars($row['username']) . "</td>
                    <td><code>" . $hash_preview . "</code></td>
                    <td>" . $row['created_at'] . "</td>
                  </tr>";
        }
        
        echo "</tbody></table>";
        
        // 4. 验证密码哈希
        echo "<h5>密码验证测试</h5>";
        
        // 重置指针
        $result->data_seek(0);
        $test_passwords = ['ChangeMe123', 'changeMe123', 'ChangeMe123!', 'password', 'admin123'];
        
        while($row = $result->fetch_assoc()) {
            echo "<p>测试用户名: <strong>" . htmlspecialchars($row['username']) . "</strong></p>";
            echo "<ul>";
            
            foreach($test_passwords as $test_pwd) {
                $verified = password_verify($test_pwd, $row['password_hash']);
                $status = $verified ? "<span class='success'>✓ 匹配</span>" : "<span class='text-muted'>✗ 不匹配</span>";
                echo "<li>测试密码: <code>" . htmlspecialchars($test_pwd) . "</code> - " . $status . "</li>";
            }
            
            echo "</ul>";
        }
        
    } else {
        echo "<p class='error'>✗ admins 表中没有记录！</p>";
        echo "<p>需要创建管理员账户。</p>";
    }
} else {
    echo "<p class='error'>✗ admins 表不存在！</p>";
    echo "<p>可能需要运行数据库安装脚本。</p>";
}

echo "</div></div>";

// 5. 创建/重置管理员账户
echo "<div class='card'>
        <div class='card-header'>3. 管理员账户工具</div>
        <div class='card-body'>";

if (isset($_POST['create_admin']) && !empty($_POST['username']) && !empty($_POST['password'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    // 生成密码哈希
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // 检查用户是否已存在
    $check_sql = "SELECT id FROM admins WHERE username = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $username);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // 更新现有用户
        $update_sql = "UPDATE admins SET password_hash = ? WHERE username = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $password_hash, $username);
        
        if ($update_stmt->execute()) {
            echo "<div class='alert alert-success'>
                    <h5>✓ 密码更新成功！</h5>
                    <p>用户名: <strong>" . htmlspecialchars($username) . "</strong></p>
                    <p>新密码: <strong>" . htmlspecialchars($password) . "</strong></p>
                    <p>请使用新密码登录。</p>
                  </div>";
        } else {
            echo "<div class='alert alert-danger'>更新失败: " . $conn->error . "</div>";
        }
        $update_stmt->close();
    } else {
        // 插入新用户
        $insert_sql = "INSERT INTO admins (username, password_hash) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ss", $username, $password_hash);
        
        if ($insert_stmt->execute()) {
            echo "<div class='alert alert-success'>
                    <h5>✓ 管理员创建成功！</h5>
                    <p>用户名: <strong>" . htmlspecialchars($username) . "</strong></p>
                    <p>密码: <strong>" . htmlspecialchars($password) . "</strong></p>
                    <p>请使用以上凭据登录。</p>
                  </div>";
        } else {
            echo "<div class='alert alert-danger'>创建失败: " . $conn->error . "</div>";
        }
        $insert_stmt->close();
    }
    
    $check_stmt->close();
}

// 显示创建表单
echo "<form method='POST' class='mt-4'>
        <h5>创建/重置管理员账户</h5>
        <div class='mb-3'>
            <label class='form-label'>用户名</label>
            <input type='text' class='form-control' name='username' value='news2308' required>
        </div>
        <div class='mb-3'>
            <label class='form-label'>密码</label>
            <input type='password' class='form-control' name='password' value='ChangeMe123' required>
            <div class='form-text'>建议使用强密码，至少6个字符</div>
        </div>
        <div class='mb-3'>
            <label class='form-label'>确认密码</label>
            <input type='password' class='form-control' name='password_confirm' value='ChangeMe123' required>
        </div>
        <button type='submit' class='btn btn-primary' name='create_admin'>创建/重置管理员账户</button>
        <p class='form-text text-muted mt-2'>此操作将创建新的管理员账户或重置现有账户的密码。</p>
      </form>";

echo "</div></div>";

// 6. 检查会话设置
echo "<div class='card'>
        <div class='card-header'>4. 会话配置检查</div>
        <div class='card-body'>";

echo "<p>当前会话状态: " . session_status() . " (2 = PHP_SESSION_ACTIVE)</p>";
echo "<p>会话保存路径: " . session_save_path() . "</p>";

// 测试会话功能
session_start();
if (!isset($_SESSION['test_time'])) {
    $_SESSION['test_time'] = time();
    echo "<p class='success'>✓ 会话功能正常，已设置测试会话变量</p>";
} else {
    echo "<p class='success'>✓ 会话功能正常，测试变量已存在: " . $_SESSION['test_time'] . "</p>";
}

echo "</div></div>";

// 7. 提供登录链接
echo "<div class='card'>
        <div class='card-header'>5. 登录测试</div>
        <div class='card-body text-center'>
            <p>使用上面创建的管理员账户登录：</p>
            <a href='admin/login.php' class='btn btn-success btn-lg' target='_blank'>
                <i class='bi bi-box-arrow-in-right'></i> 前往登录页面
            </a>
            <p class='mt-3'>
                <small>如果仍然无法登录，请检查浏览器控制台（F12）和服务器错误日志。</small>
            </p>
        </div>
      </div>";

$conn->close();
echo "</div></body></html>";
