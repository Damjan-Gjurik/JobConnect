<?php
    session_start();
    require_once __DIR__ . "/config/database.php";

    $isLoggedIn = isset($_SESSION["user_id"]);
    $userRole = $_SESSION["user_role"] ?? null;
    $isCompany = $isLoggedIn && $userRole === "company";

    $jobs = [];
    $companyApplications = [];

    $stmtActiveCount = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE company_id = ? AND status = 'active'");
    $stmtActiveCount->execute([(int)$_SESSION["user_id"]]);
    $activeJobsCount = (int)$stmtActiveCount->fetchColumn();

    $totalAppsCount = count($companyApplications);
    $pendingAppsCount = 0;
    foreach($companyApplications as $app) {
        if(($app["application_status"] ?? "") === "pending") {
            $pendingAppsCount++;
        }
    }

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
        "it" => "IT",
        "marketing" => "Маркетинг",
        "design" => "Дизајн",
        "finance" => "Финансии",
        "engineering" => "Инженерство",
        "sales" => "Продажба"
    ];

    try {
        if ($isCompany) {
            // Компанијата ги гледа апликациите само за своите огласи
            $sql = "
                SELECT
                    application.id AS application_id,
                    application.status AS application_status,
                    application.created_at AS applied_at,
                    application.cv_path,
                    application.cover_letter_path,
                    application.cover_letter,
                    jobs.id AS job_id,
                    jobs.title AS job_title,
                    users.id AS candidate_id,
                    users.name AS candidate_name,
                    users.email AS candidate_email,
                    users.high_school,
                    users.university
                FROM application
                INNER JOIN jobs ON application.job_id = jobs.id
                INNER JOIN users ON application.user_id = users.id
                WHERE jobs.company_id = :company_id
                ORDER BY application.created_at DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                "company_id" => (int)$_SESSION["user_id"]
            ]);
            $companyApplications = $stmt->fetchAll();

        } else {
            // Кандидатите и посетителите ги гледаат активните огласи
            $userId = isset($_SESSION["user_id"]) && $_SESSION["user_role"] === "candidate" ? (int)$_SESSION["user_id"] : 0;

            $sql = "
                SELECT
                    jobs.id,
                    jobs.company_id,
                    jobs.title,
                    jobs.description,
                    jobs.location,
                    jobs.category,
                    jobs.employment_type,
                    jobs.salary_min,
                    jobs.salary_max,
                    jobs.created_at,
                    users.name AS company_name,
                    application.status AS application_status
                FROM jobs
                INNER JOIN users ON jobs.company_id = users.id
                LEFT JOIN application ON jobs.id = application.job_id AND application.user_id = :user_id
                WHERE jobs.status = 'active'
                ORDER BY jobs.created_at DESC
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(["user_id" => $userId]);
            $jobs = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $jobs = [];
        $companyApplications = [];
    }
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="jobConnect - пронајди работна позиција и поврзи се со компании.">
    <title>JobConnect | Пронајди ја својата кариера</title>
    <link rel="stylesheet" href="./style/style.css">
    <link rel="stylesheet" href="./style/profile.css">
</head>
<body>
    <?php include "./php/navbar.php"; ?>

    <section class="hero" id="home">
        <div class="container hero-content">
            <div class="hero-text">
                <?php if ($isCompany): ?>
                    <span class="hero-badge">Компаниски портал • Добре дојдовте, 
                        <?php echo escapeHTML($_SESSION["user_name"] ?? "Компанија");?></span>
                    <h1>Пронајди ги вистинските <span>таленти за твојот тим.</span></h1>
                    <p>Управувај со вашите активни огласи, разгледувај пристигнати биографии и поврзи се со
                        најдобрите кандидати на едно место.
                    </p>
                    <div class="company-hero-actions">
                        <a href="php/create-job.php" class="btn btn-primary">+ Објави нов оглас</a>
                        <a href="php/dashboard.php" class="btn btn-outline">Мои огласи</a>
                        <a href="php/myCompany.php" class="btn btn-outline">Уреди профил</a>
                    </div>
                    <div class="company-hero-stats">
                        <div class="hero-stat-item">
                            <strong><?php echo $activeJobsCount;?></strong>
                            <span>Активни огласи</span>
                        </div>
                        <div class="hero-stat-item">
                            <strong><?php echo $totalAppsCount; ?></strong>
                            <span>Вкупно апликации</span>
                        </div>
                        <div class="hero-stat-item">
                            <strong><?php echo $pendingAppsCount; ?></strong>
                            <span>Нови (на чекање)</span>
                        </div>
                    </div>
                <?php else:?>
                    <span class="hero-badge">Твојата кариера започнува тука</span>
                    <h1>Пронајди ја работата <span>што ја заслужуваш.</span></h1>
                    <p>Истражи работни позиции, поврзи се со компании и направи го следниот чекор во твојата кариера.</p>

                    <form id="searchForm" class="search-form">
                        <div class="search-field">
                            <label for="keyword">Работна позиција</label>
                            <input type="text" id="keyword" placeholder="Пр. Веб девелопер">
                        </div>
                        <div class="search-field">
                            <label for="location">Локација</label>
                            <input type="text" id="location" placeholder="Пр. Скопје">
                        </div>
                        <div class="search-field">
                            <label for="category">Категорија</label>
                            <select id="category">
                                <option value="">Сите категории</option>
                                <option value="it">ИТ</option>
                                <option value="marketing">Маркетинг</option>
                                <option value="design">Дизајн</option>
                                <option value="finance">Финансии</option>
                                <option value="engineering">Инженерство</option>
                                <option value="sales">Продажба</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary search-btn">Пребарај</button>
                    </form>

                    <div class="popular-searches">
                        <span>Популарно:</span>
                        <button class="search-tag" data-search="developer" type="button">Developer</button>
                        <button class="search-tag" data-search="designer" type="button">Designer</button>
                        <button class="search-tag" data-search="marketing" type="button">Marketing</button>
                    </div>
                <?php endif;?>
            </div>
        </div>
    </section>

    <?php if (!$isCompany): ?>
        <section class="categories-section" id="categories">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <span class="section-subtitle">Истражи можности</span>
                        <h2>Популарни категории</h2>
                    </div>
                </div>
                <div class="categories-grid">
                    <button class="category-card" data-category="it" type="button">
                        <span class="category-icon">💻</span>
                        <h3>ИТ и технологија</h3>
                        <p>Програмирање и технологии</p>
                    </button>
                    <button class="category-card" data-category="marketing" type="button">
                        <span class="category-icon">📈</span>
                        <h3>Маркетинг</h3>
                        <p>Дигитален маркетинг и рекламирање</p>
                    </button>
                    <button class="category-card" data-category="design" type="button">
                        <span class="category-icon">🎨</span>
                        <h3>Дизајн</h3>
                        <p>UI/UX и графички дизајн</p>
                    </button>
                    <button class="category-card" data-category="finance" type="button">
                        <span class="category-icon">💰</span>
                        <h3>Финансии</h3>
                        <p>Банкарство и сметководство</p>
                    </button>
                    <button class="category-card" data-category="engineering" type="button">
                        <span class="category-icon">⚙️</span>
                        <h3>Инженерство</h3>
                        <p>Технички и инженерски позиции</p>
                    </button>
                    <button class="category-card" data-category="sales" type="button">
                        <span class="category-icon">🤝</span>
                        <h3>Продажба</h3>
                        <p>Продажба и комуникација</p>
                    </button>
                </div>
            </div>
        </section>

        <section class="jobs-section" id="jobs">
            <div class="container">
                <div class="section-heading jobs-heading">
                    <div>
                        <span class="section-subtitle">Нови можности</span>
                        <h2>Најнови работни позиции</h2>
                        <p id="resultsCount">
                            <?php echo count($jobs); ?>
                            <?php echo count($jobs) === 1 ? "оглас" : "огласи"; ?>
                        </p>
                    </div>
                    <button id="resetSearch" class="btn btn-outline" type="button">Ресетирај филтри</button>
                </div>

                <div class="job-grid">
                    <?php foreach ($jobs as $job): ?>
                        <?php
                            $companyName = $job["company_name"];
                            $companyInitials = strtoupper(substr($companyName, 0, 2));
                            $category = $job["category"];
                            $categoryName = $categories[$category] ?? $category;
                        ?>
                        <article 
                            class="job-card"
                            data-title="<?php echo escapeHTML($job["title"]); ?>"
                            data-company="<?php echo escapeHTML($companyName); ?>"
                            data-location="<?php echo escapeHTML($job["location"]); ?>"
                            data-category="<?php echo escapeHTML($category); ?>"
                        >
                            <div class="job-card-top">
                                <div class="company-logo">
                                    <?php echo escapeHTML($companyInitials); ?>
                                </div>
                                <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                    <span class="job-type">
                                        <?php echo escapeHTML($job["employment_type"]); ?>
                                    </span>
                                    <?php if (!empty($job["application_status"])): ?>
                                        <?php 
                                            $statusLabels = [
                                                "pending" => "На чекање",
                                                "reviewing" => "Во разгледување",
                                                "accepted" => "Прифатена",
                                                "rejected" => "Одбиена"
                                            ];
                                            $appStatusText = $statusLabels[$job["application_status"]] ?? $job["application_status"];
                                        ?>
                                        <span class="job-type" style="background: #e0f2fe; color: #0369a1;">
                                            Аплицирано: <?php echo escapeHTML($appStatusText); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <h3><?php echo escapeHTML($job["title"]); ?></h3>
                            <p class="company-name"><a href="php/profile.php?id=<?php echo(int)$job["company_id"];?>"
                            class="profile-link-inline"><?php echo escapeHTML($companyName); ?></a></p>

                            <div class="job-info">
                                <span>📍 <?php echo escapeHTML($job["location"]); ?></span>
                                <span>💼 <?php echo escapeHTML($categoryName); ?></span>
                            </div>

                            <p class="job-description"><?php echo escapeHTML($job["description"]); ?></p>

                            <div class="job-card-bottom">
                                <span class="salary">
                                    <?php echo escapeHTML(formatSalary($job["salary_min"], $job["salary_max"])); ?>
                                </span>
                                <a href="php/job-details.php?id=<?php echo (int)$job['id']; ?>" class="btn btn-primary details-btn">Детали</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <div id="emptyState" <?php echo count($jobs) > 0 ? "hidden" : ""; ?>>
                    <div class="empty-icon">🔎</div>
                    <h3><?php echo count($jobs) > 0 ? "Не се пронајдени огласи." : "Моментално нема активни огласи."; ?></h3> 
                    <p><?php echo count($jobs) > 0 ? "Обиди се со други зборови или локација" : "Провери повторно подоцна за нови работни можности"; ?></p>                 
                </div>
            </div>
        </section>

    <?php else: ?>
        <?php
            $appStatusLabels = [
                "pending" => "На чекање",
                "reviewing" => "Во разгледување",
                "accepted" => "Прифатена",
                "rejected" => "Одбиена"
            ];
        ?>
        <section id="applications" class="jobs-section">
            <div class="container">
                <div class="section-heading jobs-heading">
                    <div>
                        <span class="section-subtitle">Компаниски преглед</span>
                        <h2>Пристигнати апликации</h2>
                        <p>Вкупно пристигнати апликации на вашите огласи: <?php echo count($companyApplications); ?></p>
                    </div>
                </div>

                <?php if (count($companyApplications) > 0): ?>
                    <div class="job-grid">
                        <?php foreach ($companyApplications as $app): ?>
                            <?php
                                $candidateInitials = strtoupper(substr($app["candidate_name"], 0, 2));
                                $statusKey = $app["application_status"] ?? "pending";
                                $statusText = $appStatusLabels[$statusKey] ?? $statusKey;
                            ?>
                            <article class="job-card">
                                <div class="job-card-top">
                                    <div class="company-logo"><?php echo escapeHTML($candidateInitials); ?></div>
                                    <span class="job-type status-badge status-<?php echo escapeHTML($statusKey); ?>">
                                        <?php echo escapeHTML($statusText); ?>
                                    </span>
                                </div>

                                <h3><a href="php/profile.php?id=<?php echo (int)$app["candidate_id"];?>" 
                                class="profile-link-inline"><?php echo escapeHTML($app["candidate_name"]); ?></a></h3>
                                <p class="company-name">Оглас: <strong><?php echo escapeHTML($app["job_title"]); ?></strong></p>

                                <div class="job-info">
                                    <span>✉️ <?php echo escapeHTML($app["candidate_email"]); ?></span>
                                    <span>📅 <?php echo date("d.m.Y H:i", strtotime($app["applied_at"])); ?></span>

                                    <?php if (!empty($app["university"])): ?>
                                        <span>🎓 <?php echo escapeHTML($app["university"]); ?></span>
                                    <?php elseif (!empty($app["high_school"])): ?>
                                        <span>🏫 <?php echo escapeHTML($app["high_school"]); ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($app["cover_letter"])): ?>
                                    <p class="job-description"><?php echo escapeHTML($app["cover_letter"]); ?></p>
                                <?php endif; ?>

                                <div class="job-card-bottom">
                                    <div class="doc-links">
                                        <?php if (!empty($app["cv_path"])): ?>
                                            <a href="<?php echo escapeHTML($app["cv_path"]); ?>" download class="doc-link">📄 CV</a>
                                        <?php endif; ?>
                                        <?php if (!empty($app["cover_letter_path"])): ?>
                                            <a href="<?php echo escapeHTML($app["cover_letter_path"]); ?>" download class="doc-link">📝 Мотивационо писмо</a>
                                        <?php endif; ?>
                                    </div>
                                    <a href="php/application-details.php?id=<?php echo (int)$app["application_id"]; ?>" class="btn btn-primary">Разгледај</a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div id="emptyState">
                        <div class="empty-icon">📭</div>
                        <h3>Сè уште нема пристигнато апликации.</h3>
                        <p>Кога кандидатите ќе аплицираат на вашите активни огласи, нивните апликации ќе се појават тука.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="cta-section">
            <div class="container cta-content">
                <div>
                    <h2>Бараш нови вработени?</h2>
                    <p>Објави нов работен оглас и пронајди соодветни кандидати.</p>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <footer class="footer">
        <div class="container footer-container">
            <a href="#home" class="logo">Job<span>Connect</span></a>
            <p>© 2026 JobConnect. Сите права се задржани.</p>
            <p>Поврзување на таленти со можности.</p>
        </div>
    </footer>
    <script src="script/script.js"></script>
</body>
</html>