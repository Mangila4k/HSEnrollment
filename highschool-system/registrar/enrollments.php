<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

// Handle enrollment approval/rejection
if(isset($_GET['action']) && isset($_GET['id'])) {
    $enrollment_id = $_GET['id'];
    $action = $_GET['action'];
    
    if($action == 'approve') {
        $status = 'Enrolled';
        $message = "Enrollment approved successfully!";
    } elseif($action == 'reject') {
        $status = 'Rejected';
        $message = "Enrollment rejected.";
    }
    
    $conn->query("UPDATE enrollments SET status='$status' WHERE id='$enrollment_id'");
    $success = $message;
}

// Handle enrollment deletion
if(isset($_GET['delete'])) {
    $enrollment_id = $_GET['delete'];
    $conn->query("DELETE FROM enrollments WHERE id='$enrollment_id'");
    $success = "Enrollment record deleted successfully!";
}

// Handle new enrollment (manual entry by registrar)
if(isset($_POST['add_enrollment'])) {
    $student_id = $_POST['student_id'];
    $grade_id = $_POST['grade_id'];
    $strand = $_POST['strand'];
    $school_year = $_POST['school_year'];
    $status = $_POST['status'];
    
    $conn->query("INSERT INTO enrollments (student_id, grade_id, strand, school_year, status) 
                 VALUES ('$student_id', '$grade_id', '$strand', '$school_year', '$status')");
    $success = "New enrollment added successfully!";
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build query based on filters
$query = "SELECT e.*, u.fullname, u.email, g.grade_name 
          FROM enrollments e 
          LEFT JOIN users u ON e.student_id = u.id 
          LEFT JOIN grade_levels g ON e.grade_id = g.id 
          WHERE 1=1";

if($search) {
    $query .= " AND (u.fullname LIKE '%$search%' OR u.email LIKE '%$search%' OR e.school_year LIKE '%$search%')";
}

if($status_filter) {
    $query .= " AND e.status = '$status_filter'";
}

$query .= " ORDER BY e.id DESC";

$enrollments = $conn->query($query);

// Get counts for dashboard
$pending_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Pending'")->fetch_assoc()['count'];
$enrolled_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Enrolled'")->fetch_assoc()['count'];
$rejected_count = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status='Rejected'")->fetch_assoc()['count'];
$total_enrollments = $conn->query("SELECT COUNT(*) as count FROM enrollments")->fetch_assoc()['count'];

// Get students and grades for dropdown
$students = $conn->query("SELECT * FROM users WHERE role='Student' ORDER BY fullname");
$grades = $conn->query("SELECT * FROM grade_levels ORDER BY id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar - Enrollment Management</title>
    <link rel="stylesheet" href="../assets/css/enrollment.css">
    <style>
        /* Registrar specific styles */
        .registrar-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #0B4F2E, #1a7a42);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .stat-card.pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card.enrolled { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card.rejected { background: linear-gradient(135deg, #ef4444, #dc2626); }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            display: flex;
            gap: 10px;
        }
        
        .search-box input {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .filter-select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            min-width: 150px;
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
            display: inline-block;
        }
        
        .btn-primary {
            background: #0B4F2E;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1a7a42;
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: #10b981;
            color: white;
        }
        
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8f9fa;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            color: #666;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #b45309;
        }
        
        .status-enrolled {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #b91c1c;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .approve-btn {
            background: #10b981;
            color: white;
        }
        
        .reject-btn {
            background: #f59e0b;
            color: white;
        }
        
        .delete-btn {
            background: #ef4444;
            color: white;
        }
        
        .view-btn {
            background: #3b82f6;
            color: white;
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
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-content h3 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
            margin-left: auto;
        }
        
        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .export-buttons {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <div class="navigation-bar" style="background: linear-gradient(135deg, #0B4F2E, #1a7a42); padding: 15px 30px; color: white;">
        <div style="display: flex; justify-content: space-between; align-items: center; max-width: 1400px; margin: 0 auto;">
            <div style="display: flex; align-items: center; gap: 20px;">
                <div class="school-logo" style="width: 40px; height: 40px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #0B4F2E; font-weight: bold;">PLS</div>
                <div>
                    <h2 style="font-size: 18px;">Placido L. Señor Senior High School</h2>
                    <p style="font-size: 12px; opacity: 0.9;">Registrar Module - Enrollment Management</p>
                </div>
            </div>
            <div>
                <span style="margin-right: 20px;">Welcome, <?php echo $_SESSION['user']['fullname']; ?></span>
                <a href="../auth/logout.php" style="color: white; text-decoration: none; padding: 8px 15px; background: rgba(255,255,255,0.2); border-radius: 6px;">Logout</a>
            </div>
        </div>
    </div>

    <div class="registrar-container">
        <!-- Success/Error Messages -->
        <?php if(isset($success)): ?>
            <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745;">
                ✅ <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div>📋</div>
                <div class="stat-number"><?php echo $total_enrollments; ?></div>
                <div class="stat-label">Total Enrollments</div>
            </div>
            <div class="stat-card pending">
                <div>⏳</div>
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card enrolled">
                <div>✅</div>
                <div class="stat-number"><?php echo $enrolled_count; ?></div>
                <div class="stat-label">Enrolled</div>
            </div>
            <div class="stat-card rejected">
                <div>❌</div>
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>

        <!-- Filter and Search Bar -->
        <div class="filter-bar">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Search by name, email, or school year..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="status" class="filter-select">
                    <option value="">All Status</option>
                    <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Enrolled" <?php echo $status_filter == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                    <option value="Rejected" <?php echo $status_filter == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="enrollments.php" class="btn" style="background: #6c757d; color: white;">Clear</a>
            </form>
            
            <div class="export-buttons">
                <button class="btn btn-success" onclick="showAddModal()">➕ Add New</button>
                <button class="btn btn-primary" onclick="exportToExcel()">📊 Export</button>
                <button class="btn btn-warning" onclick="printTable()">🖨️ Print</button>
            </div>
        </div>

        <!-- Enrollments Table -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student Name</th>
                        <th>Email</th>
                        <th>Grade Level</th>
                        <th>Strand</th>
                        <th>School Year</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($enrollments && $enrollments->num_rows > 0): ?>
                        <?php while($row = $enrollments->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['id']; ?></td>
                                <td><strong><?php echo $row['fullname']; ?></strong></td>
                                <td><?php echo $row['email']; ?></td>
                                <td><?php echo $row['grade_name']; ?></td>
                                <td><?php echo $row['strand'] ?: '—'; ?></td>
                                <td><?php echo $row['school_year']; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="view_enrollment.php?id=<?php echo $row['id']; ?>" class="action-btn view-btn">👁️ View</a>
                                    
                                    <?php if($row['status'] == 'Pending'): ?>
                                        <a href="?action=approve&id=<?php echo $row['id']; ?>" class="action-btn approve-btn" onclick="return confirm('Approve this enrollment?')">✅ Approve</a>
                                        <a href="?action=reject&id=<?php echo $row['id']; ?>" class="action-btn reject-btn" onclick="return confirm('Reject this enrollment?')">⚠️ Reject</a>
                                    <?php endif; ?>
                                    
                                    <a href="?delete=<?php echo $row['id']; ?>" class="action-btn delete-btn" onclick="return confirm('Delete this enrollment record?')">🗑️ Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                📭 No enrollment records found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Quick Actions -->
        <div style="margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <h3 style="color: #333; margin-bottom: 15px;">📋 Pending Actions</h3>
                <p style="color: #666; margin-bottom: 15px;">You have <strong><?php echo $pending_count; ?></strong> pending enrollments to review.</p>
                <a href="?status=Pending" class="btn btn-warning" style="width: 100%; text-align: center;">Review Pending</a>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <h3 style="color: #333; margin-bottom: 15px;">📊 Reports</h3>
                <p style="color: #666; margin-bottom: 15px;">Generate enrollment reports and statistics.</p>
                <a href="reports.php" class="btn btn-primary" style="width: 100%; text-align: center;">Generate Reports</a>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <h3 style="color: #333; margin-bottom: 15px;">👥 Student Management</h3>
                <p style="color: #666; margin-bottom: 15px;">Manage student records and information.</p>
                <a href="students.php" class="btn btn-primary" style="width: 100%; text-align: center;">Manage Students</a>
            </div>
        </div>
    </div>

    <!-- Add Enrollment Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>➕ Add New Enrollment</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Select Student</label>
                    <select name="student_id" required>
                        <option value="">-- Choose Student --</option>
                        <?php 
                        $students->data_seek(0); // Reset pointer
                        while($s = $students->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo $s['fullname']; ?> (<?php echo $s['email']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Grade Level</label>
                    <select name="grade_id" required>
                        <option value="">-- Select Grade --</option>
                        <?php 
                        $grades->data_seek(0); // Reset pointer
                        while($g = $grades->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $g['id']; ?>"><?php echo $g['grade_name']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Strand (for Grade 11-12)</label>
                    <select name="strand">
                        <option value="">-- Optional --</option>
                        <option value="STEM">STEM</option>
                        <option value="ABM">ABM</option>
                        <option value="HUMSS">HUMSS</option>
                        <option value="GAS">GAS</option>
                        <option value="ICT">ICT</option>
                        <option value="HE">HE</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>School Year</label>
                    <input type="text" name="school_year" placeholder="e.g., 2024-2025" required>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" required>
                        <option value="Pending">Pending</option>
                        <option value="Enrolled">Enrolled</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="add_enrollment" class="btn btn-primary" style="flex: 1;">Add Enrollment</button>
                    <button type="button" class="btn" style="flex: 1; background: #6c757d; color: white;" onclick="hideAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function showAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function hideAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('addModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
        
        // Export to Excel function
        function exportToExcel() {
            const table = document.querySelector('table');
            const rows = Array.from(table.querySelectorAll('tr'));
            
            let csv = [];
            rows.forEach(row => {
                const cols = Array.from(row.querySelectorAll('th, td'));
                // Exclude action buttons column
                const rowData = cols.slice(0, -1).map(col => col.innerText.replace(/,/g, '')).join(',');
                csv.push(rowData);
            });
            
            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'enrollments_export.csv';
            a.click();
        }
        
        // Print function
        function printTable() {
            const table = document.querySelector('table');
            const newWindow = window.open('', '_blank');
            newWindow.document.write(`
                <html>
                    <head>
                        <title>Enrollment Records</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            h2 { color: #0B4F2E; }
                            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                            th { background: #f0f0f0; padding: 10px; text-align: left; }
                            td { padding: 8px; border-bottom: 1px solid #ddd; }
                        </style>
                    </head>
                    <body>
                        <h2>Placido L. Señor Senior High School</h2>
                        <h3>Enrollment Records</h3>
                        ${table.outerHTML}
                    </body>
                </html>
            `);
            newWindow.document.close();
            newWindow.print();
        }
    </script>
</body>
</html>