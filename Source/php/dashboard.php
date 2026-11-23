<?php
    session_start();
    require_once __DIR__."/../config/database.php";

    if(!isset($_SESSION["user_id"])) {
        header("Location:index.php");
        exit;
    }

    $userId = (int)$_SESSION["user_id"];
    $userRole = $_SESSION["user_role"] ?? "";
    $isCompany = $userRole === "company";

    $items = [];
    $success = "";
    $error = "";

    function escapeHTML($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }

    function formatSalary($min, $max) {
        if($min !== null && $max !== null) {
            return number_format((float)$min, 0, ",", ".")."-".number_format((float)$max, 0, ",", ".")." ден.";
        }
        if($min!==null) {
            return "Од ".number_format((float)$min, 0, ",", ".")." ден";
        }
        if($max!==null) {
            return "До ".number_format((float)$max, 0, ",", ".")." ден";
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

    $statusLabels = [
        "pending" => "На чекање",
        "reviewing" => "Во разгледување",
        "accepted" => "Прифатена",
        "rejected" => "Одбиена"
    ];

    if($isCompany && $_SERVER["REQUEST_METHOD"] === "POST" && 
    isset($_POST["toggle_job_id"], $_POST["new_status"])) {
        $targetJobId = (int)$_POST["toggle_job_id"];
        $newStatus = $_POST["new_status"] === "active" ? "active" : "closed";

        try {
            $stmt = $pdo->prepare("UPDATE jobs SET status = :status WHERE id = :id AND company_id = :company_id");
            $stmt->execute([
                "status" => $newStatus,
                "id" => $targetJobId,
                "company_id" => $userId
            ]);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = "Настана грешка при промена на статусот на огласот.";
        }
    }

    try {
        if($isCompany) {
            $stmt = $pdo->prepare("
                SELECT jobs.*, COUNT(application.id) AS total_applications
                FROM jobs LEFT JOIN application ON jobs.id = application.job_id
                WHERE jobs.company_id = :company_id
                GROUP BY jobs.id
                ORDER BY jobs.created_at DESC
            ");
            $stmt->execute(["company_id" => $userId]);
            $items = $stmt->fetchAll();
        } else {
            $stmt = $pdo->prepare("
            SELECT 
                application.id AS application_id,
                application.status AS application_status,
                application.created_at AS applied_at,
                application.cv_path,
                application.cover_letter_path,
                jobs.id AS job_id,
                jobs.title,
                jobs.location,
                jobs.category,
                jobs.employment_type,
                jobs.salary_min,
                jobs.salary_max,
                jobs.status AS job_status,
                users.name AS company_name
            FROM application
            INNER JOIN jobs ON application.job_id = jobs.id
            INNER JOIN users ON jobs.company_id = users.id
            WHERE application.user_id = :user_id
            ORDER BY application.created_at DESC
            ");
            $stmt->execute(["user_id" => $userId]);
            $items = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $error = "Настана грешка при вчитување на податоците";
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isCompany ? "Мои објавени огласи" : "Мои апликации"; ?>|JobConnect</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../style/pages.css">
</head>
<body>
    <?php include "navbar.php"; ?>
    <main class="container page-main">
        <div class="section-heading">
            <div>
                <span class="section-subtitle"><?php echo $isCompany ? "Компаниски панел":"Кандидатски панел";?></span>
                <h2><?php echo $isCompany ? "Мои објавени огласи":"Мои испратени апликации";?></h2>
                <p>Вкупно:<?php echo count($items);?></p>
            </div>
        </div>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo escapeHTML($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo escapeHTML($error); ?></div>
        <?php endif; ?>
        
        <?php if(count($items) > 0):?>
            <div class="job-grid">
                <?php if($isCompany):?>
                    <?php foreach($items as $job):?>
                        <?php
                            $categoryName = $categories[$job["category"]] ?? $job["category"];
                            $isActive = $job["status"] === "active";
                        ?>
                        <article class="job-card">
                            <div class="job-card-top">
                                <span class="job-type"><?php echo escapeHTML($job["employment_type"]);?></span>
                                <span class="job-type status-badge <?php echo $isActive ? 'status-active':'status-closed';?>">
                                    <?php echo $isActive ? "Активен":"Затворен";?>
                                </span>
                            </div>

                            <h3><?php echo escapeHTML($job["title"]);?></h3>
                            <p class="company-name">Објавено на: <?php echo date("d.m.Y", strtotime($job["created_at"]));?></p>

                            <div class="job-info">
                                <span>📍 <?php echo escapeHTML($job["location"]);?></span>
                                <span>💼 <?php echo escapeHTML($categoryName);?></span>
                                <span>👥 Апликации:<strong><?php echo (int)$job["total_applications"];?></strong></span>
                            </div>
                            <p class="job-description"><?php echo escapeHTML($job["description"]);?></p>

                            <div class="job-card-bottom">
                                <span class="salary"><?php echo escapeHTML(formatSalary($job["salary_min"], $job["salary_max"]));?></span>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="toggle_job_id" value="<?php echo (int)$job["id"]; ?>">
                                    <input type="hidden" name="new_status" value="<?php echo $isActive ? 'closed':'active';?>">
                                    <button type="submit" class="btn btn-outline btn-sm">
                                        <?php echo $isActive ? "Затвори оглас":"Активирај";?>
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach;?>
                <?php else:?>
                    <?php foreach($items as $app):?>
                        <?php
                            $companyInitials = strtoupper(substr($app["company_name"], 0, 2));
                            $appStatusText = $statusLabels[$app["application_status"]] ?? $app["application_status"];
                            $statusClass = "status- ".$app["application_status"];
                        ?>
                        <article class="job-card">
                            <div class="job-card-top">
                                <div class="company-logo"><?php echo escapeHTML($companyInitials);?></div>
                                <span class="job-type status-badge <?php echo escapeHTML($statusClass);?>">
                                    <?php echo escapeHTML($appStatusText);?>
                                </span>
                            </div>
                            <h3><?php echo escapeHTML($app["title"]);?></h3>
                            <p class="company_name"><?php echo escapeHTML($app["company_name"]);?></p>

                            <div class="job-info">
                                <span>📍 <?php echo escapeHTML($app["location"]);?></span>
                                <span>🕒 <?php echo escapeHTML($app["employment_type"]);?></span>
                            </div>
                            <p class="job-description">
                                Аплицирано на: <?php echo date("d.m.Y H:i", strtotime($app["applied_at"]));?>
                            </p>
                            <div class="job-card-bottom">
                                <div class="doc-links">
                                    <?php if(!empty($app["cv_path"])):?>
                                        <a href="<?php echo escapeHTML($app["cv_path"]);?>" download class="doc-link">📄 CV</a>
                                    <?php endif;?>
                                    <?php if(!empty($app["cover_letter_path"])):?>
                                        <a href="<?php echo escapeHTML($app["cover_letter_path"]);?>" download class="doc-link">📝 Писмо</a>
                                    <?php endif;?>
                                </div>
                                <?php if($app["job_status"] === "active"):?>
                                    <a href="job-details.php?id=<?php echo (int)$app["job_id"]; ?>" class="btn btn-primary">Оглас</a>
                                <?php else:?>
                                    <span class="closed-note">Огласот е затворен</span>
                                <?php endif;?>
                            </div>
                        </article>
                    <?php endforeach;?>
                <?php endif;?>  
            </div>
        <?php else:?>
            <div id="emptyState">
                <div class="empty-icon"><?php echo $isCompany ? "📋":"📭";?></div>
                <h3><?php echo $isCompany ? "Сè уште немате објавено огласи.":"Сè уште немате испратено апликации."?></h3>
                <p><?php echo $isCompany ? "Кликнете на копчето погоре за да го објавите вашиот прв оглас." : 
                "Прелистајте ги активните работни позиции на почетната страна и аплицирајте."; ?></p>
            </div>
        <?php endif;?>
    </main>
</body>
</html>