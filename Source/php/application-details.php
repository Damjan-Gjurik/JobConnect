<?php
    session_start();
    require_once __DIR__."/../config/database.php";

    if(!isset($_SESSION["user_id"])||$_SESSION["user_role"]!=="company") {
        header("Location:index.php");
        exit;
    }

    $appId = isset($_GET["id"]) ? (int)$_GET["id"]:0;
    $application = null;
    $success = "";
    $error = "";

    function escapeHTML($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }

    $statusLabels = [
        'pending' => "На чекање",
        "reviewing" => "Во разлгедување",
        "accepted" => "Прифатена",
        "reject" => "Одбиена"
    ];

    // Ажурирање на статус на апликацијата
    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["status"])) {
        $newStatus = $_POST["status"];
        if (array_key_exists($newStatus, $statusLabels)) {
            try {
                $update = $pdo->prepare("
                    UPDATE application 
                    INNER JOIN jobs ON application.job_id = jobs.id 
                    SET application.status = :status 
                    WHERE application.id = :app_id AND jobs.company_id = :company_id
                ");
                $update->execute([
                    "status" => $newStatus,
                    "app_id" => $appId,
                    "company_id" => $_SESSION["user_id"]
                ]);
                $success = "Статусот на апликацијата е успешно ажуриран!";
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = "Настана грешка при ажурирање на статусот.";
            }
        }
    }
    try {
        $stmt=$pdo->prepare("
            SELECT
                application.*,
                jobs.title AS job_title,
                jobs.location AS job_location,
                jobs.employment_type,
                application.user_id AS candidate_id,
                users.name AS candidate_name,
                users.email AS candidate_email
            FROM application
            INNER JOIN jobs ON application.job_id = jobs.id
            INNER JOIN users ON application.user_id = users.id
            WHERE application.id = :app_id AND jobs.company_id = :company_id
            LIMIT 1;
        ");
        $stmt->execute([
            "app_id" => $appId,
            "company_id" => $_SESSION["user_id"]
        ]);
        $application = $stmt->fetch();
    } catch(PDOEsception $e) {
        error_log($e->getMessage());
    }
    if (!$application) {
        header("Location:index.php");
        exit;
    }
    $fullName = trim($application["candidate_name"]." ".($application["surname"] ?? ""));
    $currentStatusLabel = $statusLabels[$application["status"]] ?? $application["status"];
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Детали за апликацијата | JobConnect</title>
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
                        <?php echo escapeHTML(strtoupper(substr($application["candidate_name"], 0, 2))); ?>
                    </div>
                    <span class="job-type"><?php echo escapeHTML($currentStatusLabel);?></span>
                </div>
                <h1 class="details-title"><a href="profile.php?id=<?php echo (int)$application["candidate_id"];?>" 
                class="profile-link-inline">
                    <?php echo escapeHTML($fullName);?></a></h1>
                <p class="company-name details-company">Апликација за:<?php echo escapeHTML($application["job_title"]);?></p>

                <div class="details-meta">
                    <span>✉️ <?php echo escapeHTML($application["candidate_email"]);?></span>
                    <?php if(!empty($application["phone"])):?>
                        <span>📞 <?php echo escapeHTML($application["phone"]); ?></span>
                    <?php endif; ?>
                    <span>📅 Аплицирано: <?php echo date("d.m.Y H:i", strtotime($application["created_at"])); ?></span>
                </div>
                <hr class="divider">

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo escapeHTML($success); ?></div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error"><?php echo escapeHTML($error); ?></div>
                <?php endif; ?>

                <h3 class="details-section-title">Прикачени документи</h3>
                <div class="files-grid">
                    <?php if(!empty($application["cv_path"]) && file_exists($application["cv_path"])):?>
                        <a href="<?php echo escapeHTML($application["cv_path"]);?>" download-class="file-download-card">
                            <span class="file-icon">📄</span>
                            <div>
                                <strong>Биографија(CV)</strong>
                                <span>Кликни за преземање</span>
                            </div>
                        </a>
                    <?php else: ?>
                        <div class="file-download-card disabled">
                            <span class="file-icon">❌</span>
                            <div>
                                <strong>Биографија(CV)</strong>
                                <span>Нема прикачен фајл</span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if(!empty($application["cover_letter_path"]) && file_exists($application["cover_letter_path"])):?>
                        <a href="<?php echo escapeHTML($application["cover_letter_path"]); ?>" download-class="file-download-card">
                            <span class="file-icon">📝</span>
                            <div>
                                <strong>Мотивационо писмо</strong>
                                <span>Кликни за преземање</span>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if(!empty($application["cover_letter"])):?>
                    <hr class="divider">
                    <h3 class="details-section-title">Дополнителна порака од кандидатот</h3>
                    <p class="details-description"><?php echo escapeHTML($application["cover_letter"]);?></p>
                <?php endif; ?>
                <hr class="divider">

                <div class="apply-box">
                    <h3>Промени статус на апликацијата</h3>
                    <form method="POST" class="status-form">
                        <div class="form-group">
                            <select name="status" class="form-control">
                                <?php foreach($statusLabels as $key => $label):?>
                                    <option value="<?php echo escapeHTML($key);?>" 
                                    <?php echo $application["status"] === $key ? "selected":"";?>>
                                        <?php echo escapeHTML($label);?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Зачувај статус</button>
                    </form>
                </div>
            </article>
        </div>
    </main>
</body>
</html>