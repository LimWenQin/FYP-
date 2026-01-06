<?php
// admin_reset_password.php
session_start();
include 'dataconnection.php';

$msg = "";
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// 验证 Token 是否有效且未过期
if (empty($token) || empty($email)) {<?php
// admin_reset_password.php
session_start();
include 'dataconnection.php';

$msg = "";
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

// 验证 Token 是否有效且未过期
if (empty($token) || empty($email)) {
    die("Invalid request.");
}

// 检查 Token
$sql = "SELECT * FROM password_resets WHERE token='$token' AND email='$email' AND expires_at > NOW()";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 0) {
    die("Invalid or expired token. <a href='admin_forgot_password.php'>Try again</a>");
}

// 处理密码更新
if (isset($_POST['update_password'])) {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    if ($pass1 === $pass2) {
        // 这里直接更新为明文密码，为了匹配你现有的数据库 admin123 风格
        // 如果想加密，请使用: $new_pass = password_hash($pass1, PASSWORD_DEFAULT);
        $new_pass = $pass1;

        // 更新 admin 表
        $update_sql = "UPDATE admin SET Admin_Password='$new_pass' WHERE Admin_Email='$email'";
        mysqli_query($conn, $update_sql);

        // 删除 Token (一次性使用)
        mysqli_query($conn, "DELETE FROM password_resets WHERE email='$email'");

        $msg = "<div class='success'>Password updated successfully! <a href='admin_login.php'>Login Now</a></div>";
    } else {
        $msg = "<div class='error'>Passwords do not match.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #EFF6FF; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 400px; text-align: center; }
        h2 { color: #2563EB; margin-bottom: 20px; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; outline: none; }
        button { width: 100%; padding: 12px; background: #2563EB; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .success { color: green; margin-bottom: 15px; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <?php echo $msg; ?>
        <?php if(strpos($msg, 'successfully') === false): ?>
        <form method="POST">
            <input type="password" name="pass1" placeholder="New Password" required>
            <input type="password" name="pass2" placeholder="Confirm Password" required>
            <button type="submit" name="update_password">Update Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
    die("Invalid request.");
}

// 检查 Token
$sql = "SELECT * FROM password_resets WHERE token='$token' AND email='$email' AND expires_at > NOW()";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 0) {
    die("Invalid or expired token. <a href='admin_forgot_password.php'>Try again</a>");
}

// 处理密码更新
if (isset($_POST['update_password'])) {
    $pass1 = $_POST['pass1'];
    $pass2 = $_POST['pass2'];

    if ($pass1 === $pass2) {
        // 这里直接更新为明文密码，为了匹配你现有的数据库 admin123 风格
        // 如果想加密，请使用: $new_pass = password_hash($pass1, PASSWORD_DEFAULT);
        $new_pass = $pass1;

        // 更新 admin 表
        $update_sql = "UPDATE admin SET Admin_Password='$new_pass' WHERE Admin_Email='$email'";
        mysqli_query($conn, $update_sql);

        // 删除 Token (一次性使用)
        mysqli_query($conn, "DELETE FROM password_resets WHERE email='$email'");

        $msg = "<div class='success'>Password updated successfully! <a href='admin_login.php'>Login Now</a></div>";
    } else {
        $msg = "<div class='error'>Passwords do not match.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #EFF6FF; display: flex; justify-content: center; align-items: center; height: 100vh; font-family: 'Segoe UI', sans-serif; }
        .container { background: white; padding: 40px; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 400px; text-align: center; }
        h2 { color: #2563EB; margin-bottom: 20px; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 15px; outline: none; }
        button { width: 100%; padding: 12px; background: #2563EB; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; }
        .success { color: green; margin-bottom: 15px; }
        .error { color: red; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Reset Password</h2>
        <?php echo $msg; ?>
        <?php if(strpos($msg, 'successfully') === false): ?>
        <form method="POST">
            <input type="password" name="pass1" placeholder="New Password" required>
            <input type="password" name="pass2" placeholder="Confirm Password" required>
            <button type="submit" name="update_password">Update Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>

</html>
