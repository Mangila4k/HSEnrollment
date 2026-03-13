<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$admin_id = $_SESSION['user']['id'];
$admin_name = $_SESSION['user']['fullname'];
$success_message = '';
$error_message = '';

// Check for session messages
if(isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Toggle 2FA for a user
if(isset($_GET['toggle']) && isset($_GET['user_id'])) {
    $user_id = $_GET['user_id'];
    $action = $_GET['toggle'];
    
    if($action == 'enable') {
        $conn->query("UPDATE users SET two_factor_enabled = 1 WHERE id = $user_id");
        $success_message = "2FA enabled successfully for the user.";
    } elseif($action == 'disable') {
        $conn->query("UPDATE users SET two_factor_enabled = 0 WHERE id = $user_id");
        $success_message = "2FA disabled successfully for the user.";
    }
}

// Get all admin and registrar users
$users = $conn->query("
    SELECT id, fullname, email, role, two_factor_enabled, two_factor_last_used 
    FROM users 
    WHERE role IN ('Admin', 'Registrar') 
    ORDER BY role, fullname
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Settings - Admin Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #0B4F2E;
            --primary-dark: #1a7a42;
            --primary-light: rgba(11, 79, 46, 0.1);
            --accent: #FFD700;
            --text-primary: #2b2d42;
            --text-secondary: #8d99ae;
            --border-color: #e9ecef;
            --hover-color: #f8f9fa;
            --success: #28a745;
            --danger: #dc3545;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f4f7fd;
            min-height: 100vh;
        }

        .app-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 30px 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar h2 {
            font-size: 24px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
            font-weight: 700;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar h2 i {
            color: var(--accent);
        }

        .admin-info {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 20px;
        }

        .admin-avatar {
            width: 80px;
            height: 80px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 32px;
            font-weight: bold;
            color: var(--primary);
            border: 3px solid white;
        }

        .menu-items {
            list-style: none;
        }

        .menu-items li {
            margin-bottom: 5px;
        }

        .menu-items a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
        }

        .menu-items a:hover,
        .menu-items a.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .menu-items a i {
            color: var(--accent);
        }

        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-size: 28px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .dashboard-header p {
            color: var(--text-secondary);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid var(--success);
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid var(--danger);
        }

        .info-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 15px;
            padding: 25px;
            color: white;
            margin-bottom: 30px;
        }

        .info-card h3 {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card p {
            opacity: 0.9;
            line-height: 1.6;
        }

        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .table-header h3 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
        }

        .table-header h3 i {
            color: var(--primary);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 15px;
            background: #f8f9fa;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table tr:hover {
            background: var(--hover-color);
        }

        .role-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .role-badge.admin {
            background: linear-gradient(135deg, #f093fb, #f5576c);
            color: white;
        }

        .role-badge.registrar {
            background: linear-gradient(135deg, #4facfe, #00f2fe);
            color: white;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .status-enabled {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid #28a745;
        }

        .status-disabled {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
            border: 1px solid #6c757d;
        }

        .btn-toggle {
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .btn-enable {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .btn-enable:hover {
            background: #28a745;
            color: white;
        }

        .btn-disable {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .btn-disable:hover {
            background: #dc3545;
            color: white;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }
            
            .sidebar h2 span,
            .admin-info h3,
            .admin-info p,
            .menu-items a span {
                display: none;
            }
            
            .main-content {
                margin-left: 80px;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="sidebar">
            <h2><i class="fas fa-check-circle"></i><span>PNHS</span></h2>
            <div class="admin-info">
                <div class="admin-avatar"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></div>
                <h3><?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?></h3>
                <p>Administrator</p>
            </div>
            <ul class="menu-items">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                <li><a href="manage_accounts.php"><i class="fas fa-users-cog"></i><span>Accounts</span></a></li>
                <li><a href="2fa_settings.php" class="active"><i class="fas fa-shield-alt"></i><span>2FA Settings</span></a></li>
                <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a></li>
            </ul>
        </div>

        <div class="main-content">
            <div class="dashboard-header">
                <h1>Two-Factor Authentication Settings</h1>
                <p>Manage 2FA for admin and registrar accounts</p>
            </div>

            <?php if($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="info-card">
                <h3><i class="fas fa-shield-alt"></i> About Two-Factor Authentication</h3>
                <p>Two-Factor Authentication (2FA) adds an extra layer of security to user accounts. When enabled, users will be required to enter a verification code sent to their email after entering their password. This helps protect against unauthorized access even if passwords are compromised.</p>
                <p style="margin-top: 10px;"><i class="fas fa-envelope"></i> <strong>Note:</strong> Verification codes are sent via email and expire after 5 minutes.</p>
            </div>

            <div class="table-card">
                <div class="table-header">
                    <h3><i class="fas fa-users-cog"></i> Admin & Registrar Accounts</h3>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>2FA Status</th>
                            <th>Last Used</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['fullname']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="role-badge <?php echo strtolower($user['role']); ?>">
                                        <?php echo $user['role']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $user['two_factor_enabled'] ? 'status-enabled' : 'status-disabled'; ?>">
                                        <i class="fas fa-<?php echo $user['two_factor_enabled'] ? 'check-circle' : 'times-circle'; ?>"></i>
                                        <?php echo $user['two_factor_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if($user['two_factor_last_used']) {
                                        echo date('M d, Y h:i A', strtotime($user['two_factor_last_used']));
                                    } else {
                                        echo 'Never';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if($user['two_factor_enabled']): ?>
                                        <a href="?toggle=disable&user_id=<?php echo $user['id']; ?>" 
                                           class="btn-toggle btn-disable"
                                           onclick="return confirm('Disable 2FA for this user?')">
                                            <i class="fas fa-times"></i> Disable
                                        </a>
                                    <?php else: ?>
                                        <a href="?toggle=enable&user_id=<?php echo $user['id']; ?>" 
                                           class="btn-toggle btn-enable"
                                           onclick="return confirm('Enable 2FA for this user?')">
                                            <i class="fas fa-check"></i> Enable
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>