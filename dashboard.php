<?php
// public_html/dashboard.php
// The definitive, feature-complete, and self-contained user dashboard.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Security Check & Redirection
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 2. Load All Core Files
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/src/Core/functions.php';

// 3. Import All Necessary Models
use App\Models\User;
use App\Models\Transaction;
use App\Models\OtpMessage;

// 4. Fetch All Data for the Dashboard
$user = null;
$recentTransactions = [];
$pendingSellerOtpCount = 0;
$deliveredBuyerOtpCount = 0;

try {
    $pdo = db_connect();
    $userModel = new User($pdo);
    $user = $userModel->findById($_SESSION['user_id']);
    
    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
    
    $transactionModel = new Transaction($pdo);
    $recentTransactions = $transactionModel->getRecentTransactionsByUserId($user['id'], 3);

    // Notification Logic
    $otpMessageModel = new OtpMessage($pdo);
    $deliveredBuyerOtpCount = $otpMessageModel->countDeliveredRequestsForBuyer($user['id']);
    if ($user['role'] === 'seller' || $user['is_admin']) {
        $pendingSellerOtpCount = $otpMessageModel->countPendingRequestsForSeller($user['id']);
    }

} catch (\Exception $e) {
    error_log("Dashboard Page Error: " . $e->getMessage());
    die("A temporary error occurred. Please try refreshing the page.");
}

// 5. Set Page Title
$pageTitle = "My Dashboard";

// Theme toggle initialization
$theme = $_SESSION['theme'] ?? 'light';
if (isset($_GET['theme']) && in_array($_GET['theme'], ['light', 'dark'])) {
    $theme = $_SESSION['theme'] = $_GET['theme'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo e($pageTitle); ?> | Netboost</title>
    
    <!-- External Libraries -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- All CSS is now embedded directly in this file -->
    <style>
        /* --- 1. Root Variables & Global Resets --- */
        :root {
            --primary: #6B46C1; /* Purple */
            --primary-dark: #553C9A;
            --accent: #F97316; /* Orange */
            --secondary: #10B981; /* Green */
            --tertiary: #1E40AF; /* Deep Blue */
            --quaternary: #EF4444; /* Light Red */
            --dark: #000000; /* Black */
            --light: #FFFFFF;
            --glass: rgba(255, 255, 255, 0.1);
            --glass-border: rgba(0, 0, 0, 0.15);
            --error: #DC2626;
            --success: #059669;
            --text-main: #000000;
            --text-muted: rgba(0, 0, 0, 0.6);
            --shadow-md: 0 4px 12px rgba(0, 0, 0, 0.2);
            --transition: all 0.3s ease;
        }
        [data-theme="dark"] {
            --light: #1A1A1A;
            --glass: rgba(26, 26, 26, 0.8);
            --text-main: #FFFFFF;
            --text-muted: rgba(255, 255, 255, 0.6);
            --glass-border: rgba(255, 255, 255, 0.15);
            --background: linear-gradient(135deg, #0F0F0F 0%, #1A1A1A 100%);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background, linear-gradient(135deg, #FFFFFF 0%, #F5F5F5 100%));
            color: var(--text-main); min-height: 100vh; padding: 20px 15px;
            -webkit-font-smoothing: antialiased;
            transition: background var(--transition);
        }

        /* --- 2. Main Layout & Reusable Components --- */
        .container { max-width: 600px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
        .logo { font-size: 20px; font-weight: 700; color: var(--primary); display: flex; align-items: center; }
        .logo i { color: var(--accent); margin-right: 8px; }
        .profile { width: 40px; height: 40px; border-radius: 50%; overflow: hidden; border: 2px solid var(--primary); }
        .profile img { width: 100%; height: 100%; object-fit: cover; }
        .wallet-card { background: var(--glass); backdrop-filter: blur(5px); border: 1px solid var(--glass-border); border-radius: 15px; padding: 20px; margin-bottom: 25px; box-shadow: var(--shadow-md); }
        .wallet-title { font-size: 14px; color: var(--text-muted); }
        .wallet-amount { font-size: 28px; font-weight: 700; margin: 5px 0; color: var(--secondary); }
        .wallet-actions { display: flex; gap: 10px; margin-top: 15px; }
        .btn { padding: 10px 15px; border-radius: 10px; font-weight: 600; font-size: 14px; cursor: pointer; transition: var(--transition); border: none; display: flex; align-items: center; gap: 8px; text-decoration: none; }
        .btn-primary { background: var(--primary); color: var(--light); flex: 1; justify-content: center; }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-outline { background: transparent; color: var(--primary); border: 1px solid var(--glass-border); flex: 1; justify-content: center; }
        .btn-outline:hover { background-color: var(--glass); color: var(--light); }
        .services-section { margin-top: 25px; }
        .section-title { font-size: 16px; font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; justify-content: space-between; color: var(--dark); }
        .section-title a { font-size: 12px; color: var(--tertiary); text-decoration: none; font-weight: 500;}
        
        /* Main Services Grid (3 columns) */
        .main-services-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 25px; }
        .main-services-grid .service-card { min-width: 0; flex-shrink: 1; padding: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .main-services-grid .service-card .icon { font-size: 2rem; margin-bottom: 10px; line-height: 1; }
        .main-services-grid .service-card .title { font-weight: 600; font-size: 1rem; }
        .service-card { background: var(--glass); border: 1px solid var(--glass-border); border-radius: 12px; padding: 15px; text-align: center; transition: all 0.3s ease; text-decoration: none; color: var(--text-main); }
        .service-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-md); }

        /* Features Grid (4 columns) */
        .feature-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
        .feature-button { position: relative; background: var(--glass); padding: 15px 5px; border-radius: 12px; text-align: center; text-decoration: none; color: var(--text-muted); font-size: 12px; font-weight: 500; transition: var(--transition); border: 1px solid transparent; }
        .feature-button:hover { background: rgba(255, 255, 255, 0.2); color: var(--light); transform: translateY(-3px); border-color: var(--glass-border); }
        .feature-button i { display: block; font-size: 1.4rem; margin-bottom: 8px; }
        .feature-button .notification-badge { position: absolute; top: 5px; right: 5px; background-color: var(--quaternary); color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; font-weight: 700; display: flex; align-items: center; justify-content: center; border: 2px solid var(--light); }

        /* Recent Activity List */
        .transactions-section { margin-top: 25px; }
        .transaction-item { display: flex; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--glass-border); }
        .transaction-item:last-child { border-bottom: none; }
        .transaction-icon { width: 40px; height: 40px; border-radius: 50%; background: rgba(255, 255, 255, 0.1); display: flex; align-items: center; justify-content: center; margin-right: 12px; flex-shrink: 0; }
        .transaction-icon.positive { color: var(--success); }
        .transaction-icon.negative { color: var(--error); }
        .transaction-details { flex-grow: 1; }
        .transaction-title { font-size: 14px; font-weight: 500; margin-bottom: 3px; }
        .transaction-date { font-size: 12px; color: var(--text-muted); }
        .transaction-amount { font-size: 14px; font-weight: 600; }
        .transaction-amount.positive { color: var(--success); }
        .transaction-amount.negative { color: var(--error); }
        .no-activity { text-align: center; padding: 20px; color: var(--text-muted); background: var(--glass); border-radius: 12px; }
        .admin-link { display: block; background-color: var(--accent); color: var(--dark); padding: 10px; border-radius: 8px; text-align: center; margin-top: 20px; text-decoration: none; font-weight: 600; }

        /* Theme Toggle Button */
        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            transition: var(--transition);
        }
        .theme-toggle:hover {
            color: var(--primary);
        }
    </style>
</head>
<body data-theme="<?php echo $theme; ?>">
    <div class="container">
        <header class="header">
            <div class="logo"><i class="fas fa-fighter-jet"></i><span>Netboost</span></div>
            <div class="header-right" style="display: flex; align-items: center; gap: 20px;">
                <!-- Seller OTP Notification -->
                <?php if ($user['role'] === 'seller' || $user['is_admin']): ?>
                <a href="/user/my_sales.php" class="feature-button" style="position: relative; padding: 0; background: none; border: none;" title="Pending OTP Requests">
                    <i class="fas fa-bell" style="font-size: 1.5rem; color: var(--text-muted);"></i>
                    <?php if ($pendingSellerOtpCount > 0): ?>
                        <span class="notification-badge" style="top: -5px; right: -8px;"><?php echo $pendingSellerOtpCount; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <!-- Theme Toggle -->
                <button class="theme-toggle" onclick="window.location.href='?theme=<?php echo $theme === 'light' ? 'dark' : 'light'; ?>'" title="Toggle Theme">
                    <i class="fas fa-<?php echo $theme === 'light' ? 'moon' : 'sun'; ?>"></i>
                </button>
                <!-- Profile Link -->
                <a href="profile.php" class="profile" title="My Profile">
                    <img src="https://i.pravatar.cc/150?u=<?php echo e($user['id']); ?>" alt="Profile">
                </a>
            </div>
        </header>

        <div class="wallet-card">
            <div class="wallet-title">Wallet Balance</div>
            <div class="wallet-amount">₦<?php echo number_format($user['wallet_balance'], 2); ?></div>
            <div class="wallet-actions">
                <a href="fund_wallet.php" class="btn btn-primary"><i class="fas fa-plus"></i> Add Funds</a>
                <a href="transaction_history.php" class="btn btn-outline"><i class="fas fa-history"></i> Full History</a>
            </div>
        </div>

        <!-- Main Services Grid -->
        <div class="services-section">
            <div class="section-title"><span>Main Services</span></div>
            <div class="main-services-grid">
                <a href="buy_number.php" class="service-card">
                    <div class="icon" style="color: #6B46C1;"><i class="fas fa-sim-card fa-2x"></i></div>
                    <div class="title">Phone Numbers</div>
                </a>
                <a href="smm_services.php" class="service-card">
                    <div class="icon" style="color: #F97316;"><i class="fas fa-fighter-jet"></i></div>
                    <div class="title">Boost social</div>
                </a>
                <a href="marketplace.php" class="service-card">
                    <div class="icon" style="color: #10B981;"><i class="fas fa-store fa-2x"></i></div>
                    <div class="title">Log Market</div>
                </a>
            </div>
        </div>

        <!-- Features & Account Grid -->
        <div class="services-section">
            <div class="section-title"><span>More Features & History</span></div>
            <div class="feature-grid">
                <!-- Row 1 -->
                <a href="marketplace_order_history.php" class="feature-button" title="View your Log Market purchases and get OTPs">
                    <i class="fas fa-shopping-bag" style="color: #1E40AF;"></i>
                    <span>Log Purchases</span>
                    <!-- Buyer OTP Notification -->
                    <?php if ($deliveredBuyerOtpCount > 0): ?>
                        <span class="notification-badge"><?php echo $deliveredBuyerOtpCount; ?></span>
                    <?php endif; ?>
                </a>
                <a href="smm_batch_order.php" class="feature-button" title="Place multiple SMM orders at once">
                    <i class="fas fa-layer-group" style="color: #EF4444;"></i>
                    <span>Batch Order</span>
                </a>
                <a href="user/my_listings.php" class="feature-button" title="Manage the logs you are selling">
                    <i class="fas fa-upload" style="color: #6B46C1;"></i>
                    <span>My Listings</span>
                </a>
                <a href="user/my_sales.php" class="feature-button" title="View your sales and respond to OTPs">
                    <i class="fas fa-tags" style="color: #F97316;"></i>
                    <span>My Sales</span>
                </a>

                <!-- Row 2 -->
                <a href="order_history.php" class="feature-button" title="View your Phone Number orders">
                    <i class="fas fa-list-ul" style="color: #10B981;"></i>
                    <span>Number Orders</span>
                </a>
                <a href="smm_order_history.php" class="feature-button" title="View your SMM orders">
                    <i class="fas fa-hashtag" style="color: #1E40AF;"></i>
                    <span>SMM Orders</span>
                </a>
                <a href="support.php" class="feature-button" title="Contact Support">
                    <i class="fas fa-headset" style="color: #EF4444;"></i>
                    <span>Support</span>
                </a>
                <a href="refer_earn.php" class="feature-button" title="Refer friends and earn rewards">
                    <i class="fas fa-share-alt" style="color: #6B46C1;"></i>
                    <span>Refer & Earn</span>
                </a>
            </div>
        </div>

        <!-- Admin Panel Link -->
        <?php if ($user['is_admin']): ?>
            <a href="admin/index.php" class="admin-link"><i class="fas fa-user-shield"></i> Go to Admin Panel</a>
        <?php endif; ?>
    </div>
</body>
</html>