<?php
    session_start();

    require_once __DIR__ . "/../config/database.php";

    $errors = [];
    $name = "";
    $email = "";
    $role = "candidate";

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $confirmPassword = $_POST["confirm_password"] ?? "";
        $role = $_POST["role"] ?? "candidate";

        // Проверка на име
        if ($name === "" || mb_strlen($name) < 2) {
            $errors[] = "Внеси име со најмалку 2 карактери.";
        }

        // Проверка на e-mail
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Внеси валидна e-mail адреса.";
        }

        // Проверка на лозинка
        if (strlen($password) < 8) {
            $errors[] = "Лозинката мора да има најмалку 8 карактери.";
        }

        if ($password !== $confirmPassword) {
            $errors[] = "Лозинките не се совпаѓаат.";
        }

        // Дозволени кориснички улоги
        if (!in_array($role, ["candidate", "company"], true)) {
            $errors[] = "Избрана е невалидна корисничка улога.";
        }

        if (empty($errors)) {
            try {
                // Проверка дали e-mail адресата веќе постои
                $checkEmail = $pdo->prepare(
                    "SELECT id FROM users WHERE email = ?"
                );
                $checkEmail->execute([$email]);

                if ($checkEmail->fetch()) {
                    $errors[] = "Оваа e-mail адреса веќе е регистрирана.";
                } else {
                    // Хеширање на лозинката
                    $hashedPassword = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    // Внесување на корисникот во базата
                    $insertUser = $pdo->prepare(
                        "INSERT INTO users (name, email, password, role)
                        VALUES (?, ?, ?, ?)"
                    );

                    $insertUser->execute([
                        $name,
                        $email,
                        $hashedPassword,
                        $role
                    ]);

                    // Автоматска најава по успешна регистрација
                    $userId = (int) $pdo->lastInsertId();

                    session_regenerate_id(true);

                    $_SESSION["user_id"] = $userId;
                    $_SESSION["user_name"] = $name;
                    $_SESSION["user_email"] = $email;
                    $_SESSION["user_role"] = $role;

                    header("Location:../index.php");
                    exit;
                }

            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errors[] = "Настана грешка при регистрацијата. Обиди се повторно.";
            }
        }
    }

    function escapeOutput($value) {
        return htmlspecialchars($value, ENT_QUOTES, "UTF-8");
    }
?>

<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрација | JobConnect</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 15px;
            background: #f4f6fb;
            font-family: Arial, sans-serif;
        }

        .register-container {
            width: 100%;
            max-width: 460px;
            padding: 35px;
            background: white;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            color: #172554;
            text-align: center;
        }

        .subtitle {
            margin-bottom: 25px;
            color: #64748b;
            text-align: center;
        }

        label {
            display: block;
            margin: 15px 0 7px;
            font-weight: bold;
            color: #334155;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font-size: 15px;
        }

        input:focus, select:focus {
            outline: 2px solid #93c5fd;
            border-color: #2563eb;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            border: none;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1d4ed8;
        }

        .errors {
            padding: 12px 15px;
            border-radius: 7px;
            background: #fee2e2;
            color: #991b1b;
        }

        .errors p {
            margin: 5px 0;
        }

        .login-link {
            margin-top: 22px;
            text-align: center;
            color: #64748b;
        }

        a {
            color: #2563eb;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="register-container">

    <h1>Креирај профил</h1>

    <p class="subtitle">
        Придружи се на JobConnect
    </p>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p><?= escapeOutput($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="register.php">

        <label for="name">Име / Име на компанија</label>
        <input
            type="text"
            id="name"
            name="name"
            value="<?= escapeOutput($name) ?>"
            required
        >

        <label for="email">E-mail адреса</label>
        <input
            type="email"
            id="email"
            name="email"
            value="<?= escapeOutput($email) ?>"
            required
        >

        <label for="role">Тип на профил</label>
        <select id="role" name="role" required>
            <option value="candidate"
                <?= $role === "candidate" ? "selected" : "" ?>>
                Кандидат
            </option>

            <option value="company"
                <?= $role === "company" ? "selected" : "" ?>>
                Компанија
            </option>
        </select>

        <label for="password">Лозинка</label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            required
        >

        <label for="confirm_password">Потврди лозинка</label>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            minlength="8"
            required
        >

        <button type="submit">Регистрирај се</button>

    </form>

    <p class="login-link">
        Веќе имаш профил?
        <a href="login.php">Најави се</a>
    </p>

</div>

</body>
</html>