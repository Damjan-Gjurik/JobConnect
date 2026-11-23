<?php
    session_start();
    require_once __DIR__."/../config/database.php";

    if(!isset($_SESSION["user_id"])||$_SESSION["user_role"]!=="company") {
        header("Location:login.php");
        exit;
    }
    function escapeHTML($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
    }

    $errors = [];
    $title = "";
    $description = "";
    $location = "";
    $category = "it";
    $employment_type = "Full-time";
    $salary_min = "";
    $salary_max = "";

    if($_SERVER["REQUEST_METHOD"]==="POST") {
        $title = trim($_POST["title"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $location = trim($_POST["location"] ?? "");
        $category = trim($_POST["category"] ?? "it");
        $employment_type = trim($_POST["employment_type"] ?? "Full-time");
        $salary_min = $_POST["salary_min"] !== "" ? (float)$_POST["salary_min"]:null;
        $salary_max = $_POST["salary_max"] !== "" ? (float)$_POST["salary_max"]:null;

        if($title === ""||mb_strlen($title) < 3) {
            $errors[] = "Внесете наслов со најмалку 3 карактери.";
        }
        if ($description === "") {
            $errors[] = "Внесете опис на работна позиција.";
        }
        if ($location === "") {
            $errors[] = "Внесете локација.";
        }
        if ($salary_min !== null && $salary_max !== null && $salary_min > $salary_max) {
            $errors[] = "Минималната плата не може да биде поголема од максималната.";
        }
        if(empty($errors)) {
            try {
            $stmt = $pdo->prepare("
                INSERT INTO jobs (company_id, title, description, location, category, employment_type, salary_min, salary_max, status) 
                VALUES (:company_id, :title, :description, :location, :category, :employment_type, :salary_min, :salary_max, 'active')
            ");
            
            $stmt->execute([
                "company_id" => $_SESSION["user_id"],
                "title" => $title,
                "description" => $description,
                "location" => $location,
                "category" => $category,
                "employment_type" => $employment_type,
                "salary_min" => $salary_min,
                "salary_max" => $salary_max
            ]);

            header("Location: index.php");
            exit;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            $errors[] = "Настана грешка при објавувањето на огласот.";
        }
        }
    }
?>
<!DOCTYPE html>
<html lang="mk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Објави нов оглас | JobConnect</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../style/pages.css">
</head>
<body>
    <?php include "navbar.php"; ?>
    <main class="form-wrapper">
        <div class="form-card">
            <h1>Објави нов оглас</h1>
            <p class="form-subtitle">Пополнете ги информациите за работната позиција.</p>
            <?php if(!empty($errors)):?>
                <div class="alert alert-error">
                    <?php foreach($errors as $error):?>
                        <p><?php echo escapeHTML($error);?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="title">Наслов на позицијата.</label>
                    <input type="text" id="title" name="title" class="form-control" 
                    placeholder="Пр. Веб девелопер" value="<?php echo escapeHTML($title);?>" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="category">Категорија</label>
                        <select id="category" name="category" class="form-control" required>
                            <option value="it" <?php echo $category === "it" ? "selected" : ""; ?>>ИТ</option>
                            <option value="marketing" <?php echo $category === "marketing" ? "selected" : ""; ?>>Маркетинг</option>
                            <option value="design" <?php echo $category === "design" ? "selected" : ""; ?>>Дизајн</option>
                            <option value="finance" <?php echo $category === "finance" ? "selected" : ""; ?>>Финансии</option>
                            <option value="engineering" <?php echo $category === "engineering" ? "selected" : ""; ?>>Инженерство</option>
                            <option value="sales" <?php echo $category === "sales" ? "selected" : ""; ?>>Продажба</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="employment_type">Тип на вработување</label>
                        <select id="employment_type" name="employment_type" class="form-control" required>
                            <option value="Full-time" <?php echo $employment_type === "Full-time" ? "selected" : ""; ?>>Full-time</option>
                            <option value="Part-time" <?php echo $employment_type === "Part-time" ? "selected" : ""; ?>>Part-time</option>
                            <option value="Contract" <?php echo $employment_type === "Contract" ? "selected" : ""; ?>>Contract</option>
                            <option value="Internship" <?php echo $employment_type === "Internship" ? "selected" : ""; ?>>Пракса (Internship)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="salary_min">Минимална плата(ден)</label>
                    <input type="number" id="salary_min" name="salary_min" class="form-control"
                    placeholder="Пр. 35000" value="<?php echo escapeHTML($salary_min);?>">
                </div>
                <div class="form-group">
                    <label for="salary_max">Максимална плата(ден)</label>
                    <input type="number" id="salary_max" name="salary_max" class="form-control"
                    placeholder="Пр. 60000" value="<?php echo escapeHTML($salary_min);?>">
                </div>
                <div class="form-group">
                    <label for="description">Опис на огласот</label>
                    <textarea id="description" name="description" class="form-control" rows="6" placeholder="Опишете ги одговорностите, потребните вештини и бенефитите..." required><?php echo escapeHTML($description); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="location">Локација</label>
                    <input type="text" id="location" name="location" class="form-control" placeholder="Пр. Скопје" value="<?php echo escapeHTML($location); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Објави оглас</button>
            </form>
            <p class="back-link"><a href="../index.php">← Назад кон почетна</p>
        </div>
    </main>
</body>
</html>