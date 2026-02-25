<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

// Get enrollment ID from URL
$enrollment_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Fetch enrollment details with all related information
$enrollment = $conn->query("
    SELECT e.*, 
           u.fullname, u.email, u.id as user_id,
           g.grade_name,
           s.section_name,
           sub.subject_name
    FROM enrollments e 
    LEFT JOIN users u ON e.student_id = u.id 
    LEFT JOIN grade_levels g ON e.grade_id = g.id 
    LEFT JOIN sections s ON s.grade_id = g.id
    LEFT JOIN subjects sub ON sub.grade_id = g.id
    WHERE e.id = '$enrollment_id'
    GROUP BY e.id
")->fetch_assoc();

// If enrollment not found, redirect back
if(!$enrollment) {
    header("Location: enrollments.php?error=not_found");
    exit();
}

// Get student's enrollment history
$history = $conn->query("
    SELECT * FROM enrollments 
    WHERE student_id = '{$enrollment['student_id']}' 
    ORDER BY id DESC
");

// Get student's attendance record
$attendance = $conn->query("
    SELECT a.*, sub.subject_name 
    FROM attendance a
    LEFT JOIN subjects sub ON a.subject_id = sub.id
    WHERE a.student_id = '{$enrollment['student_id']}'
    ORDER BY a.date DESC
    LIMIT 10
");

// Get available sections for this grade
$sections = $conn->query("
    SELECT * FROM sections 
    WHERE grade_id = '{$enrollment['grade_id']}'
");

// Handle section assignment
if(isset($_POST['assign_section'])) {
    $section_id = $_POST['section_id'];
    $conn->query("UPDATE enrollments SET section_id = '$section_id' WHERE id = '$enrollment_id'");
    $success = "Section assigned successfully!";
    // Refresh enrollment data
    $enrollment = $conn->query("SELECT e.*, u.fullname, u.email, g.grade_name FROM enrollments e LEFT JOIN users u ON e.student_id = u.id LEFT JOIN grade_levels g ON e.grade_id = g.id WHERE e.id = '$enrollment_id'")->fetch_assoc();
}

// Handle status update
if(isset($_POST['update_status'])) {
    $new_status = $_POST['new_status'];
    $remarks = $_POST['remarks'];
    $conn->query("UPDATE enrollments SET status = '$new_status', remarks = '$remarks' WHERE id = '$enrollment_id'");
    $success = "Status updated successfully!";
    // Refresh enrollment data
    $enrollment = $conn->query("SELECT e.*, u.fullname, u.email, g.grade_name FROM enrollments e LEFT JOIN users u ON e.student_id = u.id LEFT JOIN grade_levels g ON e.grade_id = g.id WHERE e.id = '$enrollment_id'")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Enrollment - Placido L. Señor Senior High School</title>
    <link rel="stylesheet" href="../assets/css/enrollment.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8eb 100%);
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .school-logo {
            width: 50px;
            height: 50px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0B4F2E;
            font-weight: bold;
            font-size: 20px;
        }

        .header h1 {
            font-size: 20px;
            font-weight: 500;
        }

        .header h1 span {
            display: block;
            font-size: 14px;
            opacity: 0.8;
            margin-top: 5px;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .enrollment-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 24px;
            font-weight: 500;
        }

        .status-badge {
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending { background: #f59e0b; color: white; }
        .status-enrolled { background: #10b981; color: white; }
        .status-rejected { background: #ef4444; color: white; }

        .card-body {
            padding: 30px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }

        .info-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
        }

        .info-section h3 {
            color: #0B4F2E;
            font-size: 16px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-row {
            display: flex;
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px dashed #e0e0e0;
        }

        .info-label {
            width: 120px;
            color: #666;
            font-size: 14px;
        }

        .info-value {
            flex: 1;
            color: #333;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary { background: #0B4F2E; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #6c757d; color: white; }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .history-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 13px;
            color: #555;
        }

        .history-table td {
            padding: 12px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 500px;
            width: 90%;
        }

        .modal-content h3 {
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="school-logo">PLS</div>
            <div>
                <h1>Enrollment Details<br><span>View and manage enrollment information</span></h1>
            </div>
        </div>
        <a href="enrollments.php" class="back-btn">← Back to Enrollments</a>
    </div>

    <div class="container">
        <?php if(isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Main Enrollment Card -->
        <div class="enrollment-card">
            <div class="card-header">
                <h2>Enrollment #<?php echo $enrollment['id']; ?></h2>
                <span class="status-badge status-<?php echo strtolower($enrollment['status']); ?>">
                    <?php echo $enrollment['status']; ?>
                </span>
            </div>
            
            <div class="card-body">
                <div class="info-grid">
                    <!-- Student Information -->
                    <div class="info-section">
                        <h3>👤 Student Information</h3>
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><?php echo $enrollment['fullname']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo $enrollment['email']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Student ID:</span>
                            <span class="info-value">STU-<?php echo str_pad($enrollment['user_id'], 5, '0', STR_PAD_LEFT); ?></span>
                        </div>
                    </div>

                    <!-- Enrollment Information -->
                    <div class="info-section">
                        <h3>📋 Enrollment Information</h3>
                        <div class="info-row">
                            <span class="info-label">Grade Level:</span>
                            <span class="info-value"><?php echo $enrollment['grade_name']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Strand:</span>
                            <span class="info-value"><?php echo $enrollment['strand'] ?: 'Not Applicable'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">School Year:</span>
                            <span class="info-value"><?php echo $enrollment['school_year']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Enrollment Date:</span>
                            <span class="info-value"><?php echo date('F d, Y', strtotime($enrollment['created_at'] ?? 'now')); ?></span>
                        </div>
                    </div>

                    <!-- Section Assignment -->
                    <div class="info-section">
                        <h3>📌 Section Assignment</h3>
                        <div class="info-row">
                            <span class="info-label">Current Section:</span>
                            <span class="info-value"><?php echo $enrollment['section_name'] ?: 'Not Assigned'; ?></span>
                        </div>
                        
                        <form method="POST" style="margin-top: 15px;">
                            <select name="section_id" style="width: 100%; padding: 10px; margin-bottom: 10px; border: 2px solid #e0e0e0; border-radius: 6px;">
                                <option value="">-- Assign to Section --</option>
                                <?php while($sec = $sections->fetch_assoc()): ?>
                                    <option value="<?php echo $sec['id']; ?>"><?php echo $sec['section_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                            <button type="submit" name="assign_section" class="btn btn-primary" style="width: 100%;">Assign Section</button>
                        </form>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button onclick="showStatusModal()" class="btn btn-warning">
                        🔄 Update Status
                    </button>
                    <a href="?approve=<?php echo $enrollment['id']; ?>" class="btn btn-success" onclick="return confirm('Approve this enrollment?')">
                        ✅ Approve Enrollment
                    </a>
                    <a href="?reject=<?php echo $enrollment['id']; ?>" class="btn btn-danger" onclick="return confirm('Reject this enrollment?')">
                        ❌ Reject Enrollment
                    </a>
                    <a href="print_enrollment.php?id=<?php echo $enrollment['id']; ?>" class="btn btn-secondary" target="_blank">
                        🖨️ Print Details
                    </a>
                </div>
            </div>
        </div>

        <!-- Enrollment History -->
        <div class="enrollment-card">
            <div class="card-header" style="background: #6c757d;">
                <h3>📜 Enrollment History</h3>
            </div>
            <div class="card-body">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Grade Level</th>
                            <th>Strand</th>
                            <th>School Year</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($hist = $history->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $hist['id']; ?></td>
                                <td><?php echo $enrollment['grade_name']; ?></td>
                                <td><?php echo $hist['strand'] ?: '—'; ?></td>
                                <td><?php echo $hist['school_year']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($hist['status']); ?>" style="padding: 3px 10px; font-size: 11px;">
                                        <?php echo $hist['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($hist['created_at'] ?? 'now')); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Attendance -->
        <div class="enrollment-card">
            <div class="card-header" style="background: #0B4F2E;">
                <h3>📊 Recent Attendance</h3>
            </div>
            <div class="card-body">
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Subject</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($attendance && $attendance->num_rows > 0): ?>
                            <?php while($att = $attendance->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($att['date'])); ?></td>
                                    <td><?php echo $att['subject_name']; ?></td>
                                    <td>
                                        <span style="color: <?php 
                                            echo $att['status'] == 'Present' ? '#10b981' : 
                                                ($att['status'] == 'Late' ? '#f59e0b' : '#ef4444'); 
                                        ?>">
                                            ● <?php echo $att['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #999;">No attendance records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <h3>Update Enrollment Status</h3>
            <form method="POST">
                <div class="form-group">
                    <label>New Status</label>
                    <select name="new_status" required>
                        <option value="Pending" <?php echo $enrollment['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Enrolled" <?php echo $enrollment['status'] == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                        <option value="Rejected" <?php echo $enrollment['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Remarks (Optional)</label>
                    <textarea name="remarks" placeholder="Add any remarks or notes..."><?php echo $enrollment['remarks'] ?? ''; ?></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="update_status" class="btn btn-primary" style="flex: 1;">Update Status</button>
                    <button type="button" class="btn btn-secondary" onclick="hideStatusModal()" style="flex: 1;">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showStatusModal() {
            document.getElementById('statusModal').style.display = 'flex';
        }
        
        function hideStatusModal() {
            document.getElementById('statusModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>