<?php
session_start();
// 如果已经登录，直接跳转到后台主页
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit();
}

require_once '../includes/config.php'; // 包含数据库配置

$error = ''; // 用于存储错误信息

// 处理登录表单提交
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // 1. 查询数据库，验证用户名
    $sql = "SELECT id, username, password_hash FROM admins WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        // 2. 验证密码 (使用 password_verify 对比加密后的哈希值)
        if (password_verify($password, $admin['password_hash'])) {
            // 3. 登录成功，设置会话变量
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            
            // 4. 重定向到后台管理主页
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "用户名或密码错误。";
        }
    } else {
        $error = "用户名或密码错误。";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>2308新闻部 - 管理员登录</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f5f5; }
        .login-container { max-width: 400px; margin-top: 100px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card login-container shadow">
                    <div class="card-header text-center bg-primary text-white">
                        <h4><i class="bi bi-lock"></i> 2308新闻部后台登录</h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">用户名</label>
                                <input type="text" class="form-control" id="username" name="username" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">密码</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">登录</button>
                            </div>
                        </form>
                        <hr class="my-4">
                        <p class="text-muted text-center small">
                            <i class="bi bi-info-circle"></i> 首次使用？请使用预设管理员账号登录。
                        </p>
                        <p class="text-center mb-0">
                            <a href="../index.php" class="text-decoration-none">← 返回网站首页</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
