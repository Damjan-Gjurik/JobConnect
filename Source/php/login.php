<?php
    session_start();
    require_once __DIR__."/../config/database.php";

    $error = "";
    $email = "";

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)|| $password === "") {
            $error = "Внеси валидна e-mail адреса и лозинка.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user=$stmt->fetch();

                if ($user && password_verify($password, $user["password"])) {
                    session_regenerate_id(true);
                    $_SESSION["user_id"] = (int)$user["id"];
                    $_SESSION["user_name"] = $user["name"];
                    $_SESSION["user_email"] = $user["email"];
                    $_SESSION["user_role"] = $user["role"];

                    header("Location:../index.php");
                    exit;
                } else {
                    $error = "Погрешна e-mail адреса или лозинка!";
                }
            } catch(PDOException $e) {
                error_log($e->getMessage());
                $error = "Настана грешка при најавата. Обиди се повторно.";
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Најава | JobConnect</title>
    <style>
        * {
            box-sizing:border-box;
        }
        body {
            margin:0;
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            padding:30px 15px;
            background:#f4f6fb;
            font-family:Arial, sans-serif;
        }
        .login-container {
            width:100%;
            max-width:460px;
            padding:35px;
            background:white;
            border-radius:14px;
            box-shadow:0 8px 30px rgba(0, 0, 0, 0.08);
        }
        h1 {
            margin-top:0;
            color:#172554;
            text-align:center;
        }
        .subtitle {
            margin-bottom:25px;
            color:#64748b;
            text-align:center;
        }
        label {
            display:block;
            margin:15px 0 7px;
            font-weight:bold;
            color:#334155;
        }
        input {
            width:100%;
            padding:12px;
            border:1px solid #cbd5e1;
            border-radius:7px;
            font-size:15px;
        }
        input:focus {
            outline:2px solid #93c5fd;
            border-color:#2563eb;
        }
        button {
            width:100%;
            margin-top:24px;
            padding:13px;
            border:none;
            border-radius:7px;
            background:#2563eb;
            color:white;
            font-size:16px;
            font-weight:bold;
            cursor:pointer;
        }
        button:hover {
            background:#1d4ed8;
        }
        .error {
            padding:12px 15px;
            border-radius:7px;
            background:#fee2e2;
            color:#991b1b;
        }
        .register-link {
            margin-top:22px;
            text-align:center;
            color:#64748b;
        }
        a {
            color:#2563eb;
            text-decoration:none;
            font-weight:bold;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h1>Добре дојде назад!</h1>
        <p class="subtitle">Најави се на твојот JobConnect профил</p>
        <?php if ($error !== ""):?>
            <div class="error">
                <?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8");?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <label for="email">E-mail адреса</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8");?>" required>

            <label for="password">Лозинка</label>
            <input type="password" id="password" name="password" required>

            <button type="submit">Најави се</button>
        </form>
        <p class="register-link">Сè уште немаш профил? <a href="register.php">Регистрирај се!</a></p>
    </div>
</body>
</html>