<?php
    if(session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $inPhpFolder = (basename(dirname($_SERVER["SCRIPT_FILENAME"])) === "php");

    $rootPath = $inPhpFolder ? "../" : "";
    $phpPath  = $inPhpFolder ? "" : "PHP/";

    $navLoggedIn = isset($_SESSION["user_id"]);
    $navUserRole = $_SESSION["user_role"] ?? null;
    $navIsCompany = $navLoggedIn && $navUserRole === "company";
    $currentPage = basename($_SERVER["PHP_SELF"]);

    if(!function_exists("escapeHTML")) {
        function escapeHTML($value) {
            return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
        }
    }
?>
<header class="navbar">
    <div class="container nav-container">
        <a href="<?php echo $rootPath; ?>index.php" class="logo">Job<span>Connect</span></a>

        <?php if($currentPage === "index.php" && !$navIsCompany):?>
            <nav class="nav-links">
                <a href="<?php echo $rootPath; ?>index.php#home">Почетна</a>
                <a href="<?php echo $rootPath; ?>index.php#jobs">Работни позиции</a>
                <a href="<?php echo $rootPath; ?>index.php#categories">Категории</a>
            </nav>
        <?php endif; ?>

        <div class="nav-buttons">
            <?php if ($navLoggedIn): ?>
                <?php if ($navIsCompany): ?>
                    <a href="<?php echo $phpPath; ?>myCompany.php" class="btn btn-outline" title="Уреди профил на компанија">
                        <?php echo escapeHTML($_SESSION["user_name"]); ?>
                    </a>
                    <a href="<?php echo $phpPath; ?>dashboard.php" class="btn btn-outline">Мои огласи</a>
                <?php else: ?>
                    <span class="nav-user-greeting">
                        <?php echo escapeHTML($_SESSION["user_name"]); ?>
                    </span>
                    <a href="<?php echo $phpPath; ?>myProfile.php" class="btn btn-outline">Мој профил</a>
                <?php endif; ?>

                <a href="<?php echo $phpPath; ?>logout.php" class="btn btn-outline">Одјави се</a>
            <?php else: ?>
                <a href="<?php echo $phpPath; ?>login.php" class="btn btn-outline">Најави се</a>
                <a href="<?php echo $phpPath; ?>register.php" class="btn btn-primary">Регистрирај се</a>
            <?php endif; ?>
        </div>
    </div>
</header>