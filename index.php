<?php
require_once 'config.php';
require_once 'security.php';

$security = new AdvancedSecurity($pdo);

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin-login.php");
    exit;
}

if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > 1800) {
    session_unset();
    session_destroy();
    header("Location: admin-login.php");
    exit;
}

$_SESSION['last_activity'] = time();

if (isset($_GET['logout'])) {
    $security->logSecurityEvent($security->getClientIP(), "ADMIN_LOGOUT", "User: " . $_SESSION['admin_email']);
    session_unset();
    session_destroy();
    header("Location: admin-login.php");
    exit;
}

// FRUIT DATA LOGIC
$pageTitle = "All Fruits";
$searchQuery = "";
$fruits = [];
$error = null;
$selectedFruit = $_GET['fruit'] ?? '';
$showOnlySelected = isset($_GET['showOnly']);

function fetchAllFruits() {
    $url = "https://www.fruityvice.com/api/fruit/all";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'FruitInfo Website');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($response === false) {
        throw new Exception("Failed to connect to API: " . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("API returned HTTP $httpCode - Service may be unavailable");
    }
    
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON response from API");
    }
    
    if (empty($data)) {
        throw new Exception("API returned empty data");
    }
    
    return $data;
}

function getFruitColor($fruitName) {
    $fruitColors = [
        'apple' => ['primary' => '#dc2626', 'secondary' => '#fecaca', 'accent' => '#ef4444'],
        'strawberry' => ['primary' => '#dc2626', 'secondary' => '#fecaca', 'accent' => '#ef4444'],
        'cherry' => ['primary' => '#dc2626', 'secondary' => '#fecaca', 'accent' => '#ef4444'],
        'raspberry' => ['primary' => '#dc2626', 'secondary' => '#fecaca', 'accent' => '#ef4444'],
        'watermelon' => ['primary' => '#dc2626', 'secondary' => '#bbf7d0', 'accent' => '#22c55e'],
        'orange' => ['primary' => '#ea580c', 'secondary' => '#fed7aa', 'accent' => '#f97316'],
        'mandarin' => ['primary' => '#ea580c', 'secondary' => '#fed7aa', 'accent' => '#f97316'],
        'mango' => ['primary' => '#f59e0b', 'secondary' => '#fef3c7', 'accent' => '#d97706'],
        'banana' => ['primary' => '#eab308', 'secondary' => '#fef9c3', 'accent' => '#ca8a04'],
        'lemon' => ['primary' => '#eab308', 'secondary' => '#fef9c3', 'accent' => '#ca8a04'],
        'pineapple' => ['primary' => '#eab308', 'secondary' => '#fef9c3', 'accent' => '#ca8a04'],
        'kiwi' => ['primary' => '#16a34a', 'secondary' => '#bbf7d0', 'accent' => '#22c55e'],
        'lime' => ['primary' => '#84cc16', 'secondary' => '#d9f99d', 'accent' => '#65a30d'],
        'avocado' => ['primary' => '#15803d', 'secondary' => '#bbf7d0', 'accent' => '#16a34a'],
        'pear' => ['primary' => '#84cc16', 'secondary' => '#d9f99d', 'accent' => '#65a30d'],
        'blueberry' => ['primary' => '#7e22ce', 'secondary' => '#e9d5ff', 'accent' => '#a855f7'],
        'plum' => ['primary' => '#7e22ce', 'secondary' => '#e9d5ff', 'accent' => '#a855f7'],
        'grape' => ['primary' => '#7e22ce', 'secondary' => '#e9d5ff', 'accent' => '#a855f7'],
        'coconut' => ['primary' => '#a16207', 'secondary' => '#fef3c7', 'accent' => '#d97706'],
        'peach' => ['primary' => '#fdba74', 'secondary' => '#fed7aa', 'accent' => '#fb923c'],
    ];
    
    $name = strtolower($fruitName);
    
    if (isset($fruitColors[$name])) {
        return $fruitColors[$name];
    }
    
    foreach ($fruitColors as $key => $colors) {
        if (strpos($name, $key) !== false) {
            return $colors;
        }
    }
    
    return ['primary' => '#800020', 'secondary' => '#1a1f1c', 'accent' => '#e8e8e8'];
}

$category = $_GET['category'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

try {
    $allFruits = fetchAllFruits();
    
    if (!empty($searchQuery)) {
        $fruits = array_filter($allFruits, function($fruit) use ($searchQuery) {
            return stripos($fruit['name'], $searchQuery) !== false;
        });
        $fruits = array_values($fruits);
        $pageTitle = "Search Results for: " . htmlspecialchars($searchQuery);
    } else {
        switch($category) {
            case 'berries':
                $fruits = array_filter($allFruits, function($fruit) {
                    $berryNames = ['strawberry', 'blueberry', 'raspberry', 'blackberry', 'cranberry'];
                    $name = strtolower($fruit['name']);
                    return in_array($name, $berryNames) || strpos($name, 'berry') !== false;
                });
                $pageTitle = "Berries";
                break;
                
            case 'citrus':
                $fruits = array_filter($allFruits, function($fruit) {
                    $citrusNames = ['orange', 'lemon', 'lime', 'grapefruit', 'mandarin'];
                    $name = strtolower($fruit['name']);
                    return in_array($name, $citrusNames);
                });
                $pageTitle = "Citrus Fruits";
                break;
                
            case 'tropical':
                $fruits = array_filter($allFruits, function($fruit) {
                    $tropicalNames = ['banana', 'pineapple', 'mango', 'papaya', 'coconut'];
                    $name = strtolower($fruit['name']);
                    return in_array($name, $tropicalNames);
                });
                $pageTitle = "Tropical Fruits";
                break;
                
            case 'stone':
                $fruits = array_filter($allFruits, function($fruit) {
                    $stoneNames = ['peach', 'plum', 'cherry', 'apricot'];
                    $name = strtolower($fruit['name']);
                    return in_array($name, $stoneNames);
                });
                $pageTitle = "Stone Fruits";
                break;
                
            case 'melons':
                $fruits = array_filter($allFruits, function($fruit) {
                    $melonNames = ['watermelon', 'melon', 'cantaloupe'];
                    $name = strtolower($fruit['name']);
                    foreach ($melonNames as $melon) {
                        if (strpos($name, $melon) !== false) {
                            return true;
                        }
                    }
                    return false;
                });
                $pageTitle = "Melons";
                break;
                
            default:
                $fruits = $allFruits;
                $pageTitle = "All Fruits";
                break;
        }
        
        $fruits = array_values($fruits);
    }
    
    if (is_array($fruits)) {
        usort($fruits, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
    }
    
} catch (Exception $e) {
    $error = "Unable to load fruit data from API: " . $e->getMessage();
    $fruits = [];
}

if ($showOnlySelected && !empty($selectedFruit) && is_array($fruits)) {
    $filteredFruits = [];
    foreach($fruits as $fruit) {
        if ($fruit['name'] === $selectedFruit) {
            $filteredFruits[] = $fruit;
            break;
        }
    }
    $fruits = $filteredFruits;
    if (!empty($fruits)) {
        $pageTitle = htmlspecialchars($selectedFruit);
    }
}

if (empty($fruits) && !empty($searchQuery)) {
    $error = "No fruits found matching '" . htmlspecialchars($searchQuery) . "'";
} elseif (empty($fruits) && $category !== 'all') {
    $error = "No " . htmlspecialchars($category) . " fruits found in the database";
} elseif (empty($fruits)) {
    $error = "No fruits available in the database";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FruitInfo - <?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-dark text-light">
    <!-- Scroll Progress Bar -->
    <div class="scroll-progress"></div>

    <!-- Professional Navigation - UPDATED -->
    <nav class="navbar navbar-expand-lg navbar-dark professional-nav fixed-top">
        <div class="container">
            <!-- Brand -->
            <a class="navbar-brand" href="?category=all">
                <div class="brand-container">
                    <i class="fas fa-apple-alt brand-icon"></i>
                    <span class="brand-text">Fruit<span class="brand-highlight">Info</span></span>
                </div>
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navbar Content -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <!-- Navigation Buttons - UPDATED -->
                <div class="navbar-nav me-auto">
                    <a href="?category=all" class="nav-btn <?php echo $category == 'all' && empty($searchQuery) && empty($selectedFruit) ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>Home
                    </a>
                    <a href="?category=berries" class="nav-btn <?php echo $category == 'berries' ? 'active' : ''; ?>">
                        <i class="fas fa-seedling"></i>Berries
                    </a>
                    <a href="?category=citrus" class="nav-btn <?php echo $category == 'citrus' ? 'active' : ''; ?>">
                        <i class="fas fa-lemon"></i>Citrus
                    </a>
                    <a href="?category=tropical" class="nav-btn <?php echo $category == 'tropical' ? 'active' : ''; ?>">
                        <i class="fas fa-umbrella-beach"></i>Tropical
                    </a>
                    <a href="?category=stone" class="nav-btn <?php echo $category == 'stone' ? 'active' : ''; ?>">
                        <i class="fas fa-gem"></i>Stone Fruits
                    </a>
                    <a href="?category=melons" class="nav-btn <?php echo $category == 'melons' ? 'active' : ''; ?>">
                        <i class="fas fa-water"></i>Melons
                    </a>
                </div>

                <!-- Search Bar - UPDATED -->
                <div class="search-container">
                    <form id="searchForm" class="d-flex">
                        <div class="input-group search-group">
                            <input type="text" id="searchInput" name="search" 
                                   class="form-control search-input" 
                                   placeholder="Search fruits..." 
                                   value="<?php echo htmlspecialchars($searchQuery); ?>">
                            <button type="submit" class="btn search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Dropdown with Logout - UPDATED -->
                <div class="user-menu">
                    <div class="dropdown">
                        <button class="btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-chevron-down dropdown-icon"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item logout-btn" href="?logout=true">
                                    <i class="fas fa-sign-out-alt"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-container">
        <div class="container-fluid">
            <div class="row">
                <!-- Main Content Area -->
                <div class="col-12">
                    <div class="content-wrapper">
                        <!-- Page Header -->
                        <div class="page-header">
                            <div class="header-content">
                                <div class="header-text">
                                    <h1 class="page-title"><?php echo $pageTitle; ?></h1>
                                    <p class="page-subtitle">
                                        <?php if ($showOnlySelected && !empty($selectedFruit)): ?>
                                            <i class="fas fa-star me-2"></i>Detailed view of <?php echo htmlspecialchars($selectedFruit); ?>
                                        <?php else: ?>
                                            <i class="fas fa-info-circle me-2"></i>Click any fruit card to view detailed information
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="header-actions">
                                    <?php if ($showOnlySelected && !empty($selectedFruit)): ?>
                                        <button onclick="showAllFruits()" class="btn btn-primary action-btn">
                                            <i class="fas fa-grid me-2"></i>View All Fruits
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($fruits) && is_array($fruits) && !empty($fruits) && !$error && !$showOnlySelected): ?>
                                        <div class="fruits-count-badge">
                                            <span class="count"><?php echo count($fruits); ?></span>
                                            <span class="label">Fruits</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Error Message -->
                        <?php if (isset($error)): ?>
                            <div class="error-alert">
                                <div class="alert-content">
                                    <i class="fas fa-exclamation-triangle alert-icon"></i>
                                    <div class="alert-text">
                                        <strong>Notice:</strong> <?php echo htmlspecialchars($error); ?>
                                    </div>
                                </div>
                                <a href="?category=all" class="btn btn-outline">View All Fruits</a>
                            </div>
                        <?php endif; ?>

                        <!-- Loading State -->
                        <div id="loading" class="loading-state">
                            <div class="loading-spinner">
                                <div class="spinner"></div>
                            </div>
                            <p class="loading-text">Loading fresh fruit data from API...</p>
                        </div>

                        <!-- Fruit Grid -->
                        <div id="fruitGrid" class="<?php echo $showOnlySelected && !empty($selectedFruit) ? 'single-fruit-view' : 'fruits-grid'; ?>">
                            <?php if (isset($fruits) && is_array($fruits) && !empty($fruits)): ?>
                                <?php foreach($fruits as $index => $fruit): ?>
                                    <?php
                                    $isSelected = $selectedFruit === $fruit['name'];
                                    $fruitColors = getFruitColor($fruit['name']);
                                    $displayClass = $showOnlySelected && !$isSelected ? 'd-none' : '';
                                    $cardClass = $showOnlySelected && $isSelected ? 'fruit-card single-card' : 'fruit-card';
                                    ?>
                                    
                                    <div class="<?php echo $displayClass; ?>">
                                        <div class="<?php echo $cardClass; ?> fruit-grid-item"
                                             data-fruit-name="<?php echo htmlspecialchars($fruit['name']); ?>"
                                             onclick="selectFruit('<?php echo htmlspecialchars($fruit['name']); ?>')"
                                             style="--fruit-primary: <?php echo $fruitColors['primary']; ?>;
                                                    --fruit-secondary: <?php echo $fruitColors['secondary']; ?>;
                                                    --fruit-accent: <?php echo $fruitColors['accent']; ?>;">
                                            <div class="card-header">
                                                <div class="fruit-title">
                                                    <h3 class="fruit-name"><?php echo htmlspecialchars($fruit['name']); ?></h3>
                                                    <span class="fruit-family" style="background: <?php echo $fruitColors['primary']; ?>">
                                                        <?php echo isset($fruit['family']) ? htmlspecialchars($fruit['family']) : 'Fruit'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="card-body">
                                                <div class="nutrition-section">
                                                    <h4 class="section-title">
                                                        <i class="fas fa-apple-alt me-2"></i>Nutrition (per 100g)
                                                    </h4>
                                                    <div class="nutrition-grid">
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-label">Calories</span>
                                                            <span class="nutrition-value"><?php echo isset($fruit['nutritions']['calories']) ? htmlspecialchars($fruit['nutritions']['calories']) : 'N/A'; ?></span>
                                                        </div>
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-label">Sugar</span>
                                                            <span class="nutrition-value"><?php echo isset($fruit['nutritions']['sugar']) ? htmlspecialchars($fruit['nutritions']['sugar']) . 'g' : 'N/A'; ?></span>
                                                        </div>
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-label">Carbs</span>
                                                            <span class="nutrition-value"><?php echo isset($fruit['nutritions']['carbohydrates']) ? htmlspecialchars($fruit['nutritions']['carbohydrates']) . 'g' : 'N/A'; ?></span>
                                                        </div>
                                                        <div class="nutrition-item">
                                                            <span class="nutrition-label">Protein</span>
                                                            <span class="nutrition-value"><?php echo isset($fruit['nutritions']['protein']) ? htmlspecialchars($fruit['nutritions']['protein']) . 'g' : 'N/A'; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <div class="fruit-details">
                                                    <div class="detail-item">
                                                        <span class="detail-label">Order</span>
                                                        <span class="detail-value"><?php echo isset($fruit['order']) ? htmlspecialchars($fruit['order']) : 'N/A'; ?></span>
                                                    </div>
                                                    <div class="detail-item">
                                                        <span class="detail-label">Genus</span>
                                                        <span class="detail-value"><?php echo isset($fruit['genus']) ? htmlspecialchars($fruit['genus']) : 'N/A'; ?></span>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!$showOnlySelected || !$isSelected): ?>
                                                    <div class="card-footer">
                                                        <div class="click-hint">
                                                            <i class="fas fa-mouse-pointer me-2"></i>
                                                            Click for details
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php elseif (!isset($error)): ?>
                                <!-- Empty State -->
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-fruit-watermelon"></i>
                                    </div>
                                    <h3 class="empty-title">No fruits found</h3>
                                    <p class="empty-text">Try a different search term or browse by category</p>
                                    <a href="?category=all" class="btn btn-primary">
                                        <i class="fas fa-th-large me-2"></i>Browse All Fruits
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="professional-footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <i class="fas fa-apple-alt footer-icon"></i>
                    <span class="footer-text">Fruit<span>Info</span></span>
                </div>
                <div class="footer-info">
                    <p class="footer-desc">Your comprehensive source for fruit information</p>
                    <p class="footer-source">
                        <i class="fas fa-database me-1"></i>
                        Live data from <a href="https://fruityvice.com" target="_blank">FruityVice API</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <div class="back-to-top">
        <i class="fas fa-chevron-up"></i>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script.js"></script>
</body>
</html>