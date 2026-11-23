<?php
    session_start();
    require_once __DIR__."/../config/database.php";

    function escapeHTML($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }
    function formatSalary($min, $max) {
        if($min !== null && $max !== null) {
            return number_format((float)$min, 0, ",", ".") . "-" . number_format((float)$max, 0, ",", ".") . " ден";
        }
        if($min !== null) {
            return "Од " . number_format((float)$min, 0, ",", ".") . " ден";
        }
        if($max !== null) {
            return "До " . number_format((float)$max, 0, ",", ".") . " ден";
        }
        return "Плата по договор";
    }

    $profileId = isset($_GET["id"]) ? (int)$_GET["id"]:0;
    if($profileId <= 0) {
        header("Location:index.php");
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$profileId]);
    $profile = $stmt->fetch();

    if(!$profile) {
        header("Location:index.php");
        exit;
    }

    $isProfileCompany = ($profile["role"] === "company");
    $isOwnProfile = isset($_SESSION["user_id"]) && (int)$_SESSION["user_id"] === $profileId;
    $viewerIsCompany = isset($_SESSION["user_role"]) && $_SESSION["user_role"] === "company";

    $industries = [
        "it" => "ИТ и технологија",
        "marketing" => "Маркетинг и рекламирање",
        "design" => "Дизајн",
        "finance" => "Финансии и банкарство",
        "engineering" => "Инженерство и производство",
        "sales" => "Продажба и трговија",
        "other" => "Друго"
    ];
    $categories = [
        "it" => "ИТ",
        "marketing" => "Маркетинг",
        "design" => "Дизајн",
        "finance" => "Финансии",
        "engineering" => "Инженерство",
        "sales" => "Продажба"
    ];
    $companyJobs = [];
    if($isProfileCompany) {
        $stmtJobs = $pdo->prepare("
            SELECT id, title, location, category, employment_type, salary_min, salary_max, created_at
            FROM jobs
            WHERE company_id = ? AND status = 'active'
            ORDER BY created_at DESC 
        ");
        $stmtJobs->execute([$profileId]);
        $companyJobs = $stmtJobs->fetchAll();
    }
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escapeHTML($profile["name"]); ?> | JobConnect</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../style/pages.css">
    <link rel="stylesheet" href="../style/profile.css">
</head>
<body>
    <?php include "navbar.php"; ?>
    <main class="container page-main">
        <div class="public-profile-wrapper">
            <div class="public-profile-header">
                <?php if(!empty($profile["profile_pic"]) && file_exists($profile["profile_pic"])):?>
                    <img src="<?php echo escapeHTML($profile["profile_pic"]);?>" alt="<?php echo escapeHTML($profile["name"]);?>">
                <?php else: ?>
                    <div class="public-avatar-placeholder">
                        <?php echo escapeHTML(strtoupper(substr($profile["name"], 0, 2)));?>
                    </div>
                <?php endif; ?>

                <div class="public-profile-title-area">
                    <span class="public-role-badge">
                        <?php echo $isProfileCompany ? "🏢 Компанија" : "👤 Кандидат"; ?>
                    </span>
                    <h1><?php echo escapeHTML($profile["name"]);?></h1>
                    <div class="public-profile-subtitle">
                        <?php if($isProfileCompany):?>
                            <?php if(!empty($profile["industry"])):?>
                                <span>💼 <?php echo escapeHTML($industries[$profile["industry"]] ?? $profile["industry"]); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($profile["location"])):?>
                            <span>📍 <?php echo escapeHTML($profile["location"]); ?></span>
                        <?php endif; ?>
                        <?php else: ?>
                            <?php if(!empty($profile["university"])):?>
                                <span>🎓 <?php echo escapeHTML($profile["university"]); ?></span>
                            <?php elseif((!empty($profile["high_school"]))):?>
                                <span>🏫 <?php echo escapeHTML($profile["high_school"]); ?></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <span>Член од <?php echo date("d.m.Y", strtotime($profile["created_at"]));?></span>
                    </div>
                </div>
                <?php if($isOwnProfile):?>
                    <a href="<?php echo $isProfileCompany ? 'myCompany.php':'myProfile.php'; ?>" class="btn btn-outline">
                        Уреди профил
                    </a>
                <?php endif; ?>
            </div>

            <?php if($isProfileCompany):?>
                <div class="public-profile-grid">
                    <div>
                        <div class="public-card">
                            <h3>За компанијата</h3>
                            <?php if(!empty($profile["description"])):?>
                                <p class="public-description"><?php echo escapeHTML($profile["description"]);?></p>
                            <?php else: ?>
                                <p class="muted-text">Компанијата сè уште нема додадено опис.</p>
                            <?php endif; ?>
                        </div>

                        <div class="public-card">
                            <h3>Активни работни позиции(<?php echo count($companyJobs);?>)</h3>
                            <?php if(count($companyJobs)>0): ?>
                                <div class="public-jobs-list"></div>
                                    <?php foreach($companyJobs as $job):?>
                                        <div class="public-job-item">
                                            <div>
                                                <h4><?php echo escapeHTML($job["title"]); ?></h4>
                                                <div class="public-job-meta">
                                                    <span><?php echo escapeHTML($job["location"]);?></span>
                                                    <span><?php echo escapeHTML($categories[$job["category"]] ?? $job["category"]);?></span>
                                                    <span><?php echo escapeHTML($job["employment_type"]);?></span>
                                                    <span><?php echo escapeHTML(formatSalary($job["salary_min"], $job["salary_max"]));?></span>
                                                </div>
                                            </div>
                                            <a href="job-details.php?id=<?php echo (int)$job["id"]; ?>" class="btn btn-primary">Детали</a>
                                        </div>
                                    <?php endforeach;?>
                                </div>
                            <?php else:?>
                                <p class="muted-text">Компанијата моментално нема активни огласи.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div>
                        <div class="public-card">
                            <h3>Контакт и информации</h3>
                            <ul class="public-info-list">
                                <li>
                                    <span class="public-info-label">E-mail адреса</span>
                                    <span class="public-info-value">
                                        <a href="mailto:<?php echo escapeHTML($profile["email"]);?>">
                                            <?php echo escapeHTML($profile["email"]);?>
                                        </a>
                                    </span>
                                </li>
                                <?php if(!empty($profile["location"])):?>
                                    <li>
                                        <span class="public-info-label">Седиште/Локација</span>
                                        <span class="public-info-value"><?php echo escapeHTML($profile["location"]);?></span>
                                    </li>
                                <?php endif; ?>
                                <?php if(!empty($profile["website"])):?>
                                    <li>
                                        <span class="public-info-label">Веб-страница</span>
                                        <span class="public-info-value">
                                            <a href="<?php echo escapeHTML($profile["website"]);?>" target="_blank" rel="noopener noreferrer">
                                                <?php echo escapeHTML($profile["website"]);?>
                                            </a>
                                        </span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="public-profile-grid">
                    <div class="public-card">
                        <h3>Образование</h3>
                        <ul class="public-info-list">
                            <li>
                                <span class="public-info-label">Факултет/Високо образование</span>
                                <span class="public-info-value">
                                    <?php echo !empty($profile["university"]) ? escapeHTML($profile["university"]):"Нема внесено податок";?>
                                </span>
                            </li>
                            <li>
                                <span class="public-info-label">Средно училиште</span>
                                <span class="public-info-value">
                                    <?php echo !empty($profile["high_school"]) ? escapeHTML($profile["high_school"]):"Нема внесено податок";?>
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="public-card">
                        <h3>Контакт и документи</h3>
                        <ul class="public-info-list">
                            <li>
                                <span class="public-info-label">E-mail адреса</span>
                                <span class="public-info-value">
                                    <a href="mailto:<?php echo escapeHTML($profile["email"]);?>">
                                        <?php echo escapeHTML($profile["email"]);?>
                                    </a>
                                </span>
                            </li>
                            <?php if(($viewerIsCompany || $isOwnProfile) && !empty($profile["default_cv_path"]) && 
                            file_exists($profile["default_cv_path"])):?>
                                <li>
                                    <span class="public-info-label">Биографија(CV)</span>
                                    <span class="public-info-value">
                                        <a href="<?php echo escapeHTML($profile["default_cv_path"]);?>" download class="btn btn-outline">
                                            📄 Преземи CV
                                        </a>
                                    </span>
                                </li>
                            <?php endif;?>
                        </ul>
                    </div>
                </div>
            <?php endif;?>
        </div>
    </main>
</body>
</html>
