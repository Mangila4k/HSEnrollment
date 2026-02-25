<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

// Handle student addition
if(isset($_POST['add_student'])) {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    
    $conn->query("INSERT INTO users (fullname, email, password, role, contact, address) 
                 VALUES ('$fullname', '$email', '$password', 'Student', '$contact', '$address')");
    $success = "Student added successfully!";
}

// Handle student update
if(isset($_POST['update_student'])) {
    $id = $_POST['student_id'];
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $address = $_POST['address'];
    
    $conn->query("UPDATE users SET fullname='$fullname', email='$email', contact='$contact', address='$address' WHERE id='$id'");
    $success = "Student updated successfully!";
}

// Handle student deletion
if(isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $conn->query("DELETE FROM users WHERE id='$id' AND role='Student'");
    $success = "Student deleted successfully!";
}

// Handle search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$grade_filter = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';

// Build query
$query = "SELECT u.*, e.grade_id, e.status as enrollment_status, g.grade_name 
          FROM users u 
          LEFT JOIN enrollments e ON u.id = e.student_id
          LEFT JOIN grade_levels g ON e.grade_id = g.id
          WHERE u.role = 'Student'";

if($search) {
    $query .= " AND (u.fullname LIKE '%$search%' OR u.email LIKE '%$search%' OR u.contact LIKE '%$search%')";
}

$query .= " GROUP BY u.id ORDER BY u.id DESC";

$students = $conn->query($query);
$grades = $conn->query("SELECT * FROM grade_levels");

// Get statistics
$total_students = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='Student'")->fetch_assoc()['count'];
$enrolled_students = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM enrollments WHERE status='Enrolled'")->fetch_assoc()['count'];
$pending_students = $conn->query("SELECT COUNT(DISTINCT student_id) as count FROM enrollments WHERE status='Pending'")->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Management - Placido L. Señor Senior High School</title>
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

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0B4F2E;
            margin: 10px 0;
        }

        .action-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
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
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
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
            font-size: 13px;
            color: #555;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            font-size: 13px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-enrolled {
            background: #d1fae5;
            color: #065f46;
        }

        .status-pending {
            background: #fef3c7;
            color: #b45309;
        }

        .status-none {
            background: #e5e7eb;
            color: #4b5563;
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
            max-height: 80vh;
            overflow-y: auto;
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
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
        }

        .form-group textarea {
            height: 80px;
            resize: vertical;
        }

        @media (max-width: 768px) {
            .action-bar {
                flex-direction: column;
            }
            
            .search-box {
                width: 100%;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="school-logo">PLS</div>
            <div>
                <h1>Student Management<br><span>Manage student records and information</span></h1>
            </div>
        </div>
        <div>
            <a href="enrollments.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; margin-right: 10px;">← Back</a>
            <a href="../auth/logout.php" class="btn" style="background: rgba(255,255,255,0.2); color: white;">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div>👥 Total Students</div>
                <div class="stat-number"><?php echo $total_students; ?></div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #10b981;">
                <div>✅ Enrolled</div>
                <div class="stat-number" style="color: #10b981;"><?php echo $enrolled_students; ?></div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #f59e0b;">
                <div>⏳ Pending</div>
                <div class="stat-number" style="color: #f59e0b;"><?php echo $pending_students; ?></div>
            </div>
            <div class="stat-card" style="border-left: 4px solid #3b82f6;">
                <div>📊 Not Enrolled</div>
                <div class="stat-number" style="color: #3b82f6;"><?php echo $total_students - $enrolled_students; ?></div>
            </div>
        </div>

        <!-- Action Bar -->
        <div class="action-bar">
            <form method="GET" class="search-box">
                <input type="text" name="search" placeholder="Search by name, email, or contact..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="students.php" class="btn" style="background: #6c757d; color: white;">Clear</a>
            </form>
            
            <button class="btn btn-success" onclick="showAddModal()">➕ Add New Student</button>
            <button class="btn btn-primary" onclick="exportToExcel()">📊 Export</button>
        </div>

        <!-- Students Table -->
        <div class="table-container">
            <?php if(isset($success)): ?>
                <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    ✅ <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Enrollment Status</th>
                        <th>Grade Level</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($students && $students->num_rows > 0): ?>
                        <?php while($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo str_pad($student['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td><strong><?php echo $student['fullname']; ?></strong></td>
                                <td><?php echo $student['email']; ?></td>
                                <td><?php echo $student['contact'] ?: '—'; ?></td>
                                <td><?php echo $student['address'] ?: '—'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($student['enrollment_status'] ?? 'none'); ?>">
                                        <?php echo $student['enrollment_status'] ?? 'Not Enrolled'; ?>
                                    </span>
                                </td>
                                <td><?php echo $student['grade_name'] ?? '—'; ?></td>
                                <td class="action-buttons">
                                    <button onclick="editStudent(<?php echo $student['id']; ?>)" class="action-btn" style="background: #3b82f6; color: white;">✏️ Edit</button>
                                    <a href="enrollments.php?student_id=<?php echo $student['id']; ?>" class="action-btn" style="background: #10b981; color: white;">📋 Enroll</a>
                                    <a href="?delete=<?php echo $student['id']; ?>" class="action-btn" style="background: #ef4444; color: white;" onclick="return confirm('Delete this student?')">🗑️ Delete</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: #999;">
                                📭 No students found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <h3>➕ Add New Student</h3>
            <form method="POST">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact" placeholder="e.g., 09123456789">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" placeholder="Complete address"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="add_student" class="btn btn-primary" style="flex: 1;">Add Student</button>
                    <button type="button" class="btn" style="flex: 1; background: #6c757d; color: white;" onclick="hideAddModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Student Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h3>✏️ Edit Student</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="student_id" id="edit_id">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" id="edit_fullname" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="edit_email" required>
                </div>
                
                <div class="form-group">
                    <label>Contact Number</label>
                    <input type="text" name="contact" id="edit_contact">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="edit_address"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" name="update_student" class="btn btn-primary" style="flex: 1;">Update Student</button>
                    <button type="button" class="btn" style="flex: 1; background: #6c757d; color: white;" onclick="hideEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showAddModal() {
            document.getElementById('addModal').style.display = 'flex';
        }
        
        function hideAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }
        
        function editStudent(id) {
            // Fetch student data via AJAX
            fetch('get_student.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_id').value = data.id;
                    document.getElementById('edit_fullname').value = data.fullname;
                    document.getElementById('edit_email').value = data.email;
                    document.getElementById('edit_contact').value = data.contact || '';
                    document.getElementById('edit_address').value = data.address || '';
                    
                    document.getElementById('editModal').style.display = 'flex';
                });
        }
        
        function hideEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function exportToExcel() {
            window.location.href = 'export_students.php?search=<?php echo urlencode($search); ?>';
        }
        
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            if (event.target == addModal) {
                addModal.style.display = 'none';
            }
            if (event.target == editModal) {
                editModal.style.display = 'none';
            }
        }
    </script>
</body>
</html>