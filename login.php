<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $user = \Models\User::where('username', $username)->first();

    if ($user && password_verify($password, $user->password)) {
        $_SESSION['is_admin'] = ($user->role === 'admin');
        $_SESSION['username'] = $user->username;
        header('Location: index.php');
        exit;
    } else {
        $error = "Неверный логин или пароль";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Администртор</title>
    <link rel="icon" href="./ico/palm.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="./styles/sign.css">
    <style>
        .answ {
            margin-top: 15px;
            font-size: 1rem;
            text-align: center;
            color: red;
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2><i class="fas fa-user-shield"></i> Администртор</h2>
        </div>
        <div class="login-body">
            <form method="POST">
                <div class="form-group">
                    <label for="username">Логин</label>
                    <input type="text" name="username" class="form-control" placeholder="Введите логин" required>
                </div>

                <div class="form-group">
                    <label for="password">Пароль</label>
                    <input type="password" name="password" class="form-control" placeholder="Введите пароль" required>
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-sign-in-alt"></i> Войти
                </button>
            </form>

            <?php if ($error): ?>
                <div class="answ"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>