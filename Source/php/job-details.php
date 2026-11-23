<?php
session_start();
require_once __DIR__ . "/../config/database.php";

$jobId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$job = null;
$error = "";
$success = "";

function escapeHTML($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function formatSalary($min, $max) {
    if ($min !== null && $max !== null) {
        return number_format((float)$min, 0, ",", ".") . "-" . number_format((float)$max, 0, ",", ".") . " ден.";
    }
    if ($min !== null) {
        return "Од " . number_format((float)$min, 0, ",", ".") . " ден.";
    }
    if ($max !== null) {
        return "До " . number_format((float)$max, 0, ",", ".") . " ден.";
    }
    return "Плата по договор.";
}

$categories = [
    "it" => "ИТ",
    "marketing" => "Маркетинг",
    "design" => "Дизајн",
    "finance" => "Финансии",
    "engineering" => "Инженерство",
    "sales" => "Продажба"
];

try {
    $userId = isset($_SESSION["user_id"]) && $_SESSION["user_role"] === "candidate" ? $_SESSION["user_id"] : 0;
    $stmt = $pdo->prepare("
        SELECT jobs.*, users.name AS company_name, users.email AS company_email, application.status AS application_status 
        FROM jobs 
        INNER JOIN users ON jobs.company_id = users.id 
        LEFT JOIN application ON jobs.id = application.job_id AND application.user_id = :user_id
        WHERE jobs.id = :job_id AND jobs.status = 'active' 
        LIMIT 1
    ");
    $stmt->execute([
        "user_id" => $userId,
        "job_id" => $jobId
    ]);
    $job = $stmt->fetch();
} catch (PDOException $e) {
    error_log($e->getMessage());
}

if (!$job) {
    header("Location: index.php");
    exit;
}

// Обработка на аплицирање
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_SESSION["user_id"]) && $_SESSION["user_role"] === "candidate") {
    $name = trim($_POST["name"] ?? "");
    $surname = trim($_POST["surname"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    // Валидација
    if ($name === "" || $surname === "" || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === "") {
        $error = "Ве молиме пополнете ги сите задолжителни лични податоци со валиден емаил.";
    } else {
        try {
            // Провери дали веќе аплицирал
            $check = $pdo->prepare("SELECT id FROM application WHERE job_id = ? AND user_id = ?");
            $check->execute([$jobId, $_SESSION["user_id"]]);

            if ($check->fetch()) {
                $error = "Вие веќе имате аплицирано на овој оглас.";
            } else {
                // Креирај папка uploads ако не постои
                $uploadDir = "../uploads/";
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $cvPath = null;
                $coverLetterPath = null;

                // Качување на CV фајл
                if (isset($_FILES["cv"]) && $_FILES["cv"]["error"] === UPLOAD_ERR_OK) {
                    $fileTmp = $_FILES["cv"]["tmp_name"];
                    $fileName = time() . "_cv_" . basename($_FILES["cv"]["name"]);
                    $targetPath = $uploadDir . $fileName;
                    
                    if (move_uploaded_file($fileTmp, $targetPath)) {
                        $cvPath = $targetPath;
                    }
                }

                if (!$cvPath) {
                    $error = "Прикачувањето на CV е задолжително.";
                } else {
                    // Качување на мотивационо писмо (оционално)
                    if (isset($_FILES["cover_letter_file"]) && $_FILES["cover_letter_file"]["error"] === UPLOAD_ERR_OK) {
                        $clTmp = $_FILES["cover_letter_file"]["tmp_name"];
                        $clName = time() . "_cl_" . basename($_FILES["cover_letter_file"]["name"]);
                        $clTargetPath = $uploadDir . $clName;
                        
                        if (move_uploaded_file($clTmp, $clTargetPath)) {
                            $coverLetterPath = $clTargetPath;
                        }
                    }

                    // Зачувување во база
                    $insert = $pdo->prepare("
                        INSERT INTO application (job_id, user_id, surname, phone, cv_path, cover_letter_path, status) 
                        VALUES (?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $insert->execute([
                        $jobId,
                        $_SESSION["user_id"],
                        $surname,
                        $phone,
                        $cvPath,
                        $coverLetterPath
                    ]);

                    $success = "Успешно аплициравте на оваа работна позиција!";
                }
            }
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Настана грешка при аплицирањето. Обидете се повторно.";
        }
    }
}

$categoryLabel = $categories[$job["category"]] ?? $job["category"];
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeHTML($job["title"]); ?> | JobConnect</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../style/pages.css">
    <link rel="stylesheet" href="../style/profile.css">
</head>
<body>
    <?php include "navbar.php"; ?>
    <main class="container page-main">
        <div class="details-wrapper">
            <article class="details-card">
                <div class="job-card-top">
                    <div class="company-logo">
                        <?php echo escapeHTML(strtoupper(substr($job["company_name"], 0, 2))); ?>
                    </div>
                    <div>
                        <span class="job-type"><?php echo escapeHTML($job["employment_type"]); ?></span>
                        <?php if(!empty($job["application_status"])):?>
                            <?php
                                $statusLabel = [
                                    "pending" => "На чекање",
                                    "reviewing" => "Во разгледување",
                                    "accepted" => "Прифатена",
                                    "rejected" => "Одбиена"
                                ];
                                $appStatusText = $statusLabels[$job["application_status"]] ?? $job["application_status"];
                            ?>
                            <span class="job-type" style="background: #e0f2fe; color: #0369a1; margin-left: 5px;">
                                Аплицирано: <?php echo escapeHTML($appStatusText); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <h1 class="details-title"><?php echo escapeHTML($job["title"]); ?></h1>
                <p class="company-name details-company"><a href="profile.php?id=<?php echo (int)$job["company_id"];?>" 
                class="profile-link-inline">
                    <?php echo escapeHTML($job["company_name"]); ?>
                </a></p>

                <div class="details-meta">
                    <span>📍 <?php echo escapeHTML($job["location"]); ?></span>
                    <span>💼 Категорија: <?php echo escapeHTML($categoryLabel); ?></span>
                    <span>📅 Објавено: <?php echo date("d.m.Y", strtotime($job["created_at"])); ?></span>
                </div>

                <hr class="divider">

                <h3 class="details-section-title">Опис на работната позиција</h3>
                <p class="details-description"><?php echo escapeHTML($job["description"]); ?></p>

                <div class="details-salary">
                    Плата: <?php echo escapeHTML(formatSalary($job["salary_min"], $job["salary_max"])); ?>
                </div>

                <?php if ($error !== ""): ?>
                    <div class="alert alert-error"><?php echo escapeHTML($error); ?></div>
                <?php endif; ?>

                <?php if ($success !== ""): ?>
                    <div class="alert alert-success"><?php echo escapeHTML($success); ?></div>
                <?php endif; ?>

                <?php if (isset($_SESSION["user_id"]) && $_SESSION["user_role"] === "candidate"): ?>
                    <div class="apply-box">
                        <h3>Аплицирај на овој оглас</h3>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Име</label>
                                    <input type="text" id="name" name="name" class="form-control" value="<?php echo escapeHTML($_SESSION["user_name"] ?? ""); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="surname">Презиме</label>
                                    <input type="text" id="surname" name="surname" class="form-control" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="email">E-mail адреса</label>
                                    <input type="email" id="email" name="email" class="form-control" value="<?php echo escapeHTML($_SESSION["user_email"] ?? ""); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Телефонски број</label>
                                    <input type="text" id="phone" name="phone" class="form-control" placeholder="Пр. 070123456" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="cv">Прикачи CV (PDF, DOCX)</label>
                                <input type="file" id="cv" name="cv" class="form-control" accept=".pdf,.doc,.docx" required>
                            </div>

                            <div class="form-group">
                                <label for="cover_letter_file">Прикачи мотивационо писмо (опционално)</label>
                                <input type="file" id="cover_letter_file" name="cover_letter_file" class="form-control" accept=".pdf,.doc,.docx">
                            </div>

                            <button type="submit" class="btn btn-primary btn-block">Испрати апликација</button>
                        </form>
                    </div>
                <?php elseif (!isset($_SESSION["user_id"])): ?>
                    <div class="login-prompt">
                        <a href="login.php">Најавете се како кандидат</a> за да можете да аплицирате на оваа позиција.
                    </div>
                <?php endif; ?>
            </article>
        </div>
    </main>
</body>
</html>