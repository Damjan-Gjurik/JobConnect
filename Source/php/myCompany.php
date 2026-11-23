<?php
    session_start();
    require_once __DIR__."/../config/database.php";

    if(!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "company") {
        header("Location:index.php");
        exit;
    }
    $companyId = (int)$_SESSION["user_id"];
    $success = $_SESSION["flash_success"] ?? "";
    unset($_SESSION["flash_success"]);
    $error = "";

    function escapeHTML($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }
    function fetchCompany($pdo, $id) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    $company = fetchCompany($pdo, $companyId);
    if (!$company) {
        header("Location:logout.php");
        exit;
    }
    $industries = [
        "it" => "ИТ и технологија",
        "marketing" => "Маркетинг и рекламирање",
        "design" => "Дизајн",
        "finance" => "Финансии и банкарство",
        "engineering" => "Инженерство",
        "sales" => "Продажба и трговија",
        "other" => "Друго"
    ];

    $activeJobsCount = 0;
    $totalApplicationsCount = 0;

    try {
        $stmtJobs = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ? AND status = 'active");
        $stmtJobs->execute([$companyId]);
        $activeJobsCount = (int)$stmtJobs->fetchColumn();

        $stmtApps = $pdo->prepare("
            SELECT COUNT(application.id)
            FROM application INNER JOIN jobs ON application.job_id = jobs.id
            WHERE jobs.company_id = ?
        ");
        $stmtApps->execute([$companyId]);
        $totalApplicationsCount = (int)$stmtApps->fetchColumn();
    } catch(PDOException $e) {
        error_log($e->getMessage());
    }

    if($_SERVER["REQUEST_METHOD"] === "POST") {
        $action = $_POST["action"] ?? "";

        if($action === "update_company") {
            $name = trim($_POST["name"] ?? "");
            $email = trim($_POST["email"] ?? "");
            $industry = trim($_POST["industry"] ?? "");
            $location = trim($_POST["location"] ?? "");
            $phone = trim($_POST["phone"] ?? "");
            $website = trim($_POST["website"] ?? "");
            $description = trim($_POST["description"] ?? "");

            if($name === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Ве молиме внесете валидно име на компанијата и емаил адреса.";
            } elseif($website !== "" && !filter_var($website, FILTER_VALIDATE_URL)) {
                $error = "Веб-страницата мора да биде валиден лини(пр. https://primer.com).";
            } else {
                try {
                    $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $checkEmail->execute([$email, $companyId]);

                    if($checkEmail->fetch()) {
                        $error = "Оваа емаил адреса веќе се користи од друг профил.";
                    } else {
                        $logoPath = $company["profile_pic"] ?? null;
                        $uploadDir = "../uploads/";
                        if(!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        if(isset($_FILES["profile_pic"]) && $_FILES["profile_pic"]["error"] === UPLOAD_ERR_OK) {
                            $allowedImg = ["image/jpeg", "image/png", "image/webp"];
                            $fileType = mime_content_type($_FILES["profile_pic"]["tmp_name"]);

                            if(in_array($fileType, $allowedImg, true)) {
                                $ext = pathinfo($_FILES["profile_pic"]["tmp_name"], PATHINFO_EXTENSION);
                                $picName = "company_" . $companyId . "_" . time() . "." . $ext;
                                $targetPic = $uploadDir . $picName;

                                if(move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $targetPic)) {
                                    if(!empty($logoPath) && file_exists($logoPath)) {
                                        unlink($logoPath);
                                    }
                                    $logoPath = $targetPic;
                                }
                            } else {
                                $error = "Логото мора да биде во JPG, PNG или WEBP формат.";
                            }
                        }
                        if($error === "") {
                            $update = $pdo->prepare("
                                UPDATE users
                                SET name = :name,
                                    email = :email,
                                    profile_pic = :profile_pic,
                                    industry = :industry,
                                    location = :location,
                                    phone = :phone,
                                    website = :website,
                                    description = :description
                                WHERE id = :id
                            ");
                            $update->execute([
                                "name" => $name,
                                "email" => $email,
                                "profile_pic" => $logoPath,
                                "industry" => $industry !== "" ? $industry:null,
                                "location" => $location !== "" ? $location:null,
                                "phone" => $phone !== "" ? $phone:null,
                                "website" => $website !== "" ? $website:null,
                                "description" => $description !== "" ? $description:null,
                                "id" => $companyId
                            ]);

                            $_SESSION["user_name"] = $name;
                            $_SESSION["user_email"] = $email;
                            $_SESSION["flash_success"] = "Податоците се успешно ажурирани!";

                            header("Location: myCompany.php");
                            exit;
                        }
                    }
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $error = "Настана грешка при зачувување на податоците";
                }
            }
        }
        if($action === "remove_avatar") {
            try {
                if(!empty($company["profile_pic"]) && file_exists($company["profile_pic"])) {
                    unlink($company["profile_pic"]);
                }
                $stmt = $pdo->prepare("UPDATE users SET profile_pic = NULL WHERE id = ?");
                $stmt->execute([$companyId]);

                $_SESSION["flash_success"] = "Логото на компанијата е успешно отстрането.";
                header("Location:myCompany.php");
                exit;
            } catch(PDOException $e) {
                error_log($e->getMessage());
                $error = "Настана грешка при отстранување на логото.";
            }
        }
        if($action === "change_password") {
            $currentPassword = $_POST["current_password"] ?? "";
            $newPassword = $_POST["new_password"] ?? "";
            $confirmPassword = $_POST["confirm_password"] ?? "";

            if (!password_verify($currentPassword, $company["password"])) {
                $error = "Моменталната лозинка е неточна.";
            } elseif (strlen($newPassword) < 6) {
                $error = "Новата лозинка и потврда не се совпаѓаат.";
            } else {
                try {
                    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed, $companyId]);

                    $_SESSION["flash_success"] = "Лозинката е успешно променета!";
                    header("Location: myCompany.php");
                    exit;
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    $error = "Настана грешка при промена на лозинката.";
                }
            }
        }
        if($action === "delete_account") {
            $deletePassword = $_POST["delete_password"] ?? "";

            if(!password_verify($deletePassword, $company["password"])) {
                $error = "Внесовте погрешна лозинка. Профилот не е избришан.";  
            } else {
                try {
                    if (!empty($company["profile_pic"]) && file_exists($company["profile_pic"])) {
                        unlink($company["profile_pic"]);
                    }
                    $pdo->prepare("
                        DELETE application FROM application
                        INNER JOIN jobs ON application.job_id = jobs.id
                        WHERE jobs.company_id = ?
                    ")->execute([$companyId]);
                    $pdo->prepare("DELETE FROM jobs WHERE company_id = ?")->execute([$companyId]);
                    $pdo->prepare("DELETE FROM users WHERE company_id = ?")->execute([$companyId]);

                    session_destroy();
                    header("Location:index.php");
                    exit;
                } catch (PDOException $e) {
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
    <title>Профил на компанија | JobConnect</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../style/pages.css">
</head>
<body>
    <?php include "navbar.php"; ?>
    <main class="container page-main">
        <div class="details-wrapper">
            <div class="section-heading">
                <div>
                    <span class="section-subtitle">Компаниски профил</span>
                    <h2><?php echo escapeHTML($company["name"]);?></h2>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <a href="dashboard.php" class="btn btn-outline">Мои огласи</a>
                    <a href="create-job.php" class="btn btn-primary">+ Објави нов оглас</a>
                </div>
            </div>
            <div class="company-stats-grid">
                <div class="stat-box">
                    <span class="stat-number"><?php echo $activeJobsCount;?></span>
                    <span class="stat-label">Активни огласи</span>
                </div>
                <div class="stat-box">
                    <span class="stat-number"><?php echo $totalApplicationsCount;?></span>
                    <span class="stat-label">Вкупно апликации</span>
                </div>
            </div>
            <?php if(!empty($success)):?>
                <div class="alert alert-success"><?php echo escapeHTML($success);?></div>
            <?php endif;?>
            <?php if(!empty($error)):?>
                <div class="alert alert-error"><?php echo escapeHTML($error);?></div>
            <?php endif; ?>
            <div class="form-card profile-section">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update_company">

                    <div class="profile-avatar-row">
                        <?php if(!empty($company["profile_pic"]) && file_exists($company["profile_pic"])):?>
                            <img src="<?php echo escapeHTML($company["profile_pic"]);?>" alt="Лого на компанија"
                            class="profile-avatar-img">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder">
                                <?php echo escapeHTML(strtoupper(substr($company["name"], 0, 2)));?>
                            </div>
                        <?php endif; ?>
                        <div class="form-group" style="margin-bottom:0; flex:1;">
                            <label for="profile_pic">Лого на компанијата(JPG, PNG, WEBP)</label>
                            <input type="file" id="profile_pic" name="profile_pic" class="form-control"
                            accept="image/jpeg/, image/png, image/webp">

                            <?php if(!empty($company["profile_pic"]) && file_exists($company["profile_pic"])):?>
                                <button type="submit" form="removeAvatarForm" class="btn-remove-avatar"
                                onclick="return confirm('Дали сте сигурни дека сакате да го отстраните логото?');">
                                    Отстрани моментално лого
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Име на компанијата</label>
                            <input type="text" id="name" name="name" class="form-control" 
                            value="<?php echo escapeHTML($company["name"]); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Официјален E-mail</label>
                            <input type="email" id="email" name="email" class="form-control"
                            value="<?php echo escapeHTML($company["email"]);?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="industry">Дејност/Индустрија</label>
                            <select id="industry" name="industry" class="form-control">
                                <option value="">Изберете дејност</option>
                                <?php foreach($industries as $key => $label):?>
                                    <option value="<?php echo escapeHTML($key);?>"
                                    <?php echo ($company["industry"] ?? "") === $key ? "selected":"";?>>
                                        <?php echo escapeHTML($label);?>
                                    </option>
                                <?php endforeach;?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="location">Локација/Седиште</label>
                            <input type="text" id="location" name="location" class="form-control" 
                            placeholder="Пр. Скопје, ул. Македонија бр. 10" 
                            value="<?php echo escapeHTML($company["location"] ?? "");?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Контакт телефон</label>
                            <input type="text" id="phone" name="phone" class="form-control"
                            placeholder="Пр. +389 70 123 456" value="<?php echo escapeHTML($company["phone"] ?? ""); ?>">
                        </div>
                        <div class="form-group">
                            <label for="website">Веб-страница (опционално)</label>
                            <input type="url" id="website" name="website" class="form-control" 
                            placeholder="https://vashatakompanija.mk" value="<?php echo escapeHTML($company["website"] ?? ""); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">За компанијата (опис)</label>
                        <textarea id="description" name="description" class="form-control" rows="4" placeholder="Краток опис за вашата компанија, работната култура и мисијата..."><?php echo escapeHTML($company["description"] ?? ""); ?></textarea>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-primary">Зачувај промени</button>
                </form>
                <form id="removeAvatarForm" method="POST" style="display: none;">
                    <input type="hidden" name="action" value="remove_avatar">
                </form>
            </div>
            <br>
            <div class="form-card profile-section">
                <h3 class="details-section-title">Промена на лозинка</h3>
                <form method="POST">
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label for="current_password">Моментална лозинка</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">Нова лозинка(мин. 6 карактери)</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Потврди нова лозинка</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <br>
                    <button type="submit" class="btn btn-outline">Ажурирај лозинка</button>
                </form>
            </div>
            <div class="form-card danger-zone">
                <h3 class="danger-title">Бришење на компаниски профил</h3>
                <p class="danger-text">Оваа акција е трајна. Сите објавени огласи на вашата компанија и пристигнатите
                    апликации за нив ќе бидат трајно избришани.
                </p>
                <form method="POST" onsubmit="return confirm('Дали сте сигурни дека сакате трајно да го избришете профилот накомпанијата и сите нејзини огласи?');">
                    <input type="hidden" name="action" value="delete_account">
                    <div class="status-form">
                        <div class="form-group">
                            <input type="password" name="delete_password" class="form-control"
                            placeholder="Внесете ја лозинката за потврда" required>
                        </div>
                        <button type="submit" class="btn btn-danger">Избриши компанија</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>