<?php 
    session_start();
    require_once __DIR__."/../config/database.php";

    if(!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "candidate") {
        header("Location:index.php");
        exit;
    }

    $userId = (int)$_SESSION["user_id"];
    $success = $_SESSION["flash_success"] ?? "";
    unset($_SESSION["flash_success"]);
    $error = "";

    function escapeHTML($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }

    function fetchUser($pdo, $id) {
        $stmt=$pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    $user = fetchUser($pdo, $userId);
    if(!$user) {
        header("Location:logout.php");
        exit;
    }

    if($_SERVER["REQUEST_METHOD"] === "POST") {
        $action = $_POST["action"] ?? "";

        if($action === "update_profile") {
            $name = trim($_POST["name"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $highSchool = trim($_POST["high_school"] ?? "");
            $university = trim($_POST["university"] ?? "");

            if($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Ве молиме внесете валидно корисничко име и емаил адреса.";
            } else {
                try {
                    $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $checkEmail->execute([$email, $userId]);

                    if($checkEmail->fetch()) {
                        $error = "Оваа емаил адреса веќе е регистрирана од друг корисник.";
                    } else {
                        $profilePicPath = $user["profile_pic"];
                        $cvPath = $user["cv_path"];
                        $uploadDir = "../uploads/";
                        if(!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        if(isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] === UPLOAD_ERR_OK) {
                            $allowedImg = ["image/jpeg", "image/png", "image/webp"];
                            $fileType = mime_content_type($_FILES["profile_pic"]["tmp_name"]);
                            
                            if(in_array($fileType, $allowedImg, true)) {
                                $ext = pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
                                $picName = "avatar_".$userId."_".time().".".$ext;
                                $targetPic = $uploadDir.$picName;

                                if(move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $targetPic)) {
                                    if(!empty($profilePicPath) && file_exists($profilePicPath)) {
                                        unlink($profilePicPath);
                                    }
                                    $profilePicPath = $targetPic;
                                }
                            } else {
                                $error = "Профилната слика мора да биде JPG, PNG или WEBP.";
                            }
                        }
                        if($error === "" && isset($_FILES["default_cv"]) && $_FILES["default_cv"]["error"] === UPLOAD_ERR_OK) {
                            $cvExt = strtolower(pathinfo($_FILES["default_cv"]["name"], PATHINFO_EXTENSION));
                            if(in_array($cvExt, ["pdf", "doc", "docx"], true)) {
                                $cvName = "profile_cv" . $userId . "_" . time() . "." . $cvExt;
                                $targetCv = $uploadDir . $cvName;

                                if(move_uploaded_file($_FILES["default_cv"]["tmp_name"], $targetCv)) {
                                    if(!empty($cvPath) && file_exists($cvPath)) {
                                        unlink($cvPath);
                                    }
                                    $cvPath = $targetCv;
                                }
                            }
                            else {
                                $error = "CV фајлот мора да биде PDF, DOC или DOCX";
                            }
                        }
                        if ($error === "") {
                            $update = $pdo->prepare("
                                UPDATE users
                                SET name = :name,
                                    email = :email,
                                    profile_pic = :profile_pic,
                                    high_school = :high_school,
                                    university = :university,
                                    cv_path = :cv_path
                                WHERE id = :id
                            ");
                            $update->execute([
                                "name" => $name,
                                "email" => $email,
                                "profile_pic" => $profilePicPath,
                                "high_school" => $highSchool !== "" ? $highSchool:null,
                                "university" => $university !== "" ? $university:null,
                                "cv_path" => $cvPath,
                                "id" => $userId
                            ]);

                            $_SESSION["user_name"] = $name;
                            $_SESSION["user_email"] = $email;
                            $_SESSION["flash_success"] = "Податоците на профилот се успешно зачувани!";

                            header("Location: myProfile.php");
                            exit;
                        }
                    }
                } catch(PDOException $e) {
                    error_log($e->getMessage());
                    $error = "Настана грешка при зачувување на податоците на профилот.";
                }
            }
        }
        if($action === "remove_avatar") {
            try {
                if(!empty($user["profile_pic"]) && file_exists($user["profile_pic"])) {
                    unlink($user["profile_pic"]);
                }
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?");
                $stmt->execute([$userId]);

                $_SESSION["flash_success"] = "Профилната слика е успешно отстранета.";
                header("Location:myProfile.php");
                exit;
            } catch (PDOException $e){
                error_log($e->getMessage());
                $error = "Настана грешка при отстранување на сликата.";
            }
        }

        if($action === "change_password") {
            $currentPassword = $_POST["current_password"] ?? "";
            $newPassword = $_POST["new_password"] ?? "";
            $confirmPassword = $_POST["confirm_password"] ?? "";

            if(!password_verify($currentPassword, $user["password"])) {
                $error = "Моменталната лозинка е неточна.";
            } elseif(strlen($newPassword) < 6) {
                $error = "Новата лозинка мора да има најмалку 6 карактери.";
            } elseif($newPassword !== $confirmPassword) {
                $error = "Новата лозинка и потврдата не се совпаѓаат.";
            } else {
                try {
                    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed, $userId]);
                    $_SESSION["flash_success"] = "Лозинката е успешно променета!";
                    header("Location: myProfile.php");
                    exit;
                } catch(PDOException $e) {
                    error_log($e->getMessage());
                    $error = "Настана грешка при промена на лозинката.";
                }
            }
        }

        if($action === "delete_account") {
            $deletePassword = $_POST["delete_password"] ?? "";
            if(!password_verify($deletePassword, $user["password"])) {
                $error = "Внесовте погрешна лозинка. Профилот не е избришан.";
            } else {
                try {
                    if(!empty($user["profile_pic"]) && file_exists($user["profile_pic"])) {
                        unlink($user["profile_pic"]);
                    }
                    if(!empty($user["cv_path"]) && file_exists($user["cv_path"])) {
                        unlink($user["cv_path"]);
                    }
                    $pdo->prepare("DELETE FROM application WHERE user_id = ?")->execute([$userId]);
                    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);

                    session_destroy();
                    header("Location:index.php");
                    exit;
                } catch(PDOException $e) {
                    error_log($e->getMessage());
                    $error = "Настана грешка при бришење на профилот.";
                }
            }
        }
    }
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мој профил | JobConnect</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../style/pages.css">
</head>
<body>
    <?php include "navbar.php"; ?>
    <main class="container page-main">
        <div class="details-wrapper">
            <div class="section-heading">
                <div>
                    <span class="section-subtitle">Кандидат</span>
                    <h2>Уредување на профил</h2>
                </div>
                <a href="dashboard.php" class="btn btn-primary">Мои апликации</a>
            </div>

            <?php if(!empty($success)): ?>
                <div class="alert alert-success"><?php echo escapeHTML($success);?></div>
            <?php endif; ?>
            <?php if(!empty($error)): ?>
                <div class="alert alert-error"><?php echo escapeHTML($error);?></div>
            <?php endif; ?>

            <div class="form-card profile-section">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="profile_avatar-row">
                        <?php if(!empty($user["profile_pic"]) && file_exists($user["profile_pic"])):?>
                            <img src="<?php echo escapeHTML($user["profile_pic"]);?>" alt="Профилна слика"
                            class="profile-avatar-img">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder">
                                <?php echo escapeHTML(strtoupper(substr($user["name"], 0, 2))); ?>
                            </div>
                        <?php endif; ?>

                        <div class="form-group" style="margin-bottom:0; flex:1;">
                            <label for="profile_pic">Профилна слика(JPG, PNG, WEBP)</label>
                            <input type="file" id="profile_pic" name="profile_pic" 
                            class="form-control" accept="image/jpeg, image/png, image/webp">

                            <?php if(!empty($user["profile_pic"]) && file_exists($user["profile_pic"])):?>
                                <button type="submit" form="removeAvatarForm" class="btn-remove-avatar"
                                onclick="return confirm('Дали сте сигурни дека сакате да ја отстраните профилната слика?');">
                                    Отстрани моментална слика
                                </button>
                            <?php endif;;?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Корисничко име/Име и презиме</label>
                            <input type="text" id="name" name="name" class="form-control" 
                            value="<?php echo escapeHTML($user["name"]);?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">E-mail адреса</label>
                            <input type="email" id="email" name="email" class="form-control"
                            value="<?php echo escapeHTML($user["email"]);?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="highschool">Завршено средно образование</label>
                            <input type="text" id="high_school" name="high_school" class="form-control"
                            placeholder='Пр. СУГС "Раде Јовчевски Корчагин"'value="<?php echo escapeHTML($user["high_school"]);?>">
                        </div>
                        <div class="form-group">
                            <label for="university">Факултет(опционално)</label>
                            <input type="text" id="university" name="university" class="form-control"
                            placeholder='Пр. ФИНКИ - УКИМ"'value="<?php echo escapeHTML($user["university"]);?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="default_cv">Прикачи CV(PDF, DOC, DOCX)</label>
                        <input type="file" id="default_cv" name="default_cv" class="form-control" accept=".pdf, .doc, .docx">
                        <?php if(!empty($user["cv_path"]) && file_exists($user["cv_path"])):?>
                            <p class="current-file-note">
                                Моментално прикачено CV:
                                <a href="<?php echo escapeHTML($user["cv_path"]);?>" download>Преземи CV</a>
                            </p>
                        <?php endif; ?>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-primary">Зачувај промени</button>
                </form>
                <form id="removeAvatarForm" method="POST" style="display:none;">
                    <input type="hidden" name="action" value="remove_avatar">
                </form>
            </div>
            <div class="form-card profile-section">
                <h3 class="details-section-title">Промена на лозинка</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">Моментална лозинка</label>
                        <input type="password" id="current_password" name="current_password"
                        class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">Нова лозинка(минимум 6 карактери).</label>
                            <input type="password" id="new_password" name="new_password"
                            class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Потврди нова лозинка.</label>
                            <input type="password" id="confirm_password" name="confirm_password"
                            class="form-control" required>
                        </div>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-outline">Ажурирај лозинка</button>
                </form>
            </div>
            <div class="form-card danger-zone">
                <h3 class="danger-title">Бришење на профил</h3>
                <p class="danger-text">Оваа акција е трајна. Сите ваши лични податоци, 
                    прикачено CV и испратени апликации ќе бидат избришани</p>
                <form method="POST" onsubmit="return confirm('Дали сте сигурни дека сакате трајно да го избришете вашиот профил?');">
                    <input type="hidden" name="action" value="delete_account">
                    <div class="status-form">
                        <div class="form-group">
                            <input type="password" name="delete_password" class="form-control"
                            placeholder="Внесете ја вашата лозинка за потврда" required>
                        </div>
                        <button type="submit" class="btn btn-danger">Избриши профил</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
