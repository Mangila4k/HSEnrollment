<?php
session_start();
include("../config/database.php");

// Check if user is admin
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Admin'){
    header("Location: ../auth/login.php");
    exit();
}

$error = '';
$success = '';

// Get grade levels for dropdown
$grade_levels = $conn->query("SELECT * FROM grade_levels ORDER BY id");

// Get strands for dropdown (for senior high)
$strands = $conn->query("SELECT * FROM strands ORDER BY strand_name");

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $grade_level = $_POST['grade_level'];
    $strand = !empty($_POST['strand']) ? $_POST['strand'] : null;
    $id_number = !empty($_POST['id_number']) ? trim($_POST['id_number']) : null;
    
    // Validation
    if(empty($fullname) || empty($email) || empty($password) || empty($grade_level)) {
        $error = "All fields are required";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } elseif(strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } elseif($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $check->store_result();
        
        if($check->num_rows > 0) {
            $error = "Email already exists";
        } else {
            // Check if ID number exists (if provided)
            if($id_number) {
                $check_id = $conn->prepare("SELECT id FROM users WHERE id_number = ?");
                $check_id->bind_param("s", $id_number);
                $check_id->execute();
                $check_id->store_result();
                
                if($check_id->num_rows > 0) {
                    $error = "ID number already exists";
                }
                $check_id->close();
            }
            
            if(empty($error)) {
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // Hash password and insert user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $role = 'Student';
                    
                    $stmt = $conn->prepare("INSERT INTO users (id_number, fullname, email, password, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $id_number, $fullname, $email, $hashed_password, $role);
                    
                    if($stmt->execute()) {
                        $user_id = $stmt->insert_id;
                        
                        // Create enrollment record
                        $school_year = '2026-2027'; // You can make this dynamic
                        $status = 'Pending';
                        
                        // Handle file upload if there's a form_138
                        $form_138_path = null;
                        if(isset($_FILES['form_138']) && $_FILES['form_138']['error'] == 0) {
                            $target_dir = "../uploads/";
                            if (!file_exists($target_dir)) {
                                mkdir($target_dir, 0777, true);
                            }
                            
                            $file_extension = pathinfo($_FILES["form_138"]["name"], PATHINFO_EXTENSION);
                            $filename = "form138_" . $user_id . "_" . time() . "." . $file_extension;
                            $target_file = $target_dir . $filename;
                            
                            if(move_uploaded_file($_FILES["form_138"]["tmp_name"], $target_file)) {
                                $form_138_path = "uploads/" . $filename;
                            }
                        }
                        
                        // Insert enrollment
                        $enroll_stmt = $conn->prepare("INSERT INTO enrollments (student_id, grade_id, school_year, status, strand, form_138) VALUES (?, ?, ?, ?, ?, ?)");
                        $enroll_stmt->bind_param("iissss", $user_id, $grade_level, $school_year, $status, $strand, $form_138_path);
                        $enroll_stmt->execute();
                        
                        $conn->commit();
                        $success = "Student account and enrollment created successfully!";
                        
                        // Clear form
                        $_POST = array();
                    } else {
                        throw new Exception("Error creating user");
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = "Error creating student: " . $e->getMessage();
                }
            }
        }
        $check->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Student - Admin Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/admindashboard.css">
    <style>
        /* Additional styles for file input */
        .file-input-wrapper {
            position: relative;
            margin-bottom: 10px;
        }
        
        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .file-input-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: white;
            border: 2px dashed var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            justify-content: center;
        }
        
        .file-input-label:hover {
            border-color: var(--primary-color);
            background: var(--hover-color);
        }
        
        .file-input-label i {
            color: var(--primary-color);
            font-size: 1.2em;
        }
        
        .file-name {
            margin-top: 5px;
            font-size: 0.9em;
            color: var(--text-secondary);
        }
        
        .password-hint {
            font-size: 0.85em;
            color: var(--text-secondary);
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .password-hint i {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <h2>
                <i class="fas fa-check-circle"></i>
                <span>PNHS</span>
            </h2>
            
            <div class="menu-section">
                <h3>MENU</h3>
                <ul class="menu-items">
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                    <li><a href="students.php" class="active"><i class="fas fa-user-graduate"></i> <span>Students</span></a></li>
                    <li><a href="teachers.php"><i class="fas fa-chalkboard-teacher"></i> <span>Teachers</span></a></li>
                    <li><a href="enrollments.php"><i class="fas fa-file-signature"></i> <span>Enrollments</span></a></li>
                    <li><a href="sections.php"><i class="fas fa-layer-group"></i> <span>Sections</span></a></li>
                    <li><a href="subjects.php"><i class="fas fa-book"></i> <span>Subjects</span></a></li>
                </ul>
            </div>

            <div class="menu-section">
                <h3>GENERAL</h3>
                <ul class="menu-items">
                    <li><a href="manage_accounts.php"><i class="fas fa-users-cog"></i> <span>Accounts</span></a></li>
                    <li><a href="#"><i class="fas fa-cog"></i> <span>Settings</span></a></li>
                    <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <h1>
                    <i class="fas fa-user-plus"></i>
                    Add New Student
                </h1>
                <p>Create a new student account and enrollment record</p>
            </div>

            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <div class="user-details">
                        <h4>Welcome, <?php echo htmlspecialchars($_SESSION['user']['fullname']); ?></h4>
                        <span>Administrator</span>
                    </div>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="form-container">
                <form method="POST" action="" enctype="multipart/form-data">
                    <!-- Personal Information -->
                    <div style="background: white; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-user" style="color: var(--primary-color);"></i>
                            Personal Information
                        </h3>
                        
                        <div class="form-group">
                            <label for="fullname">Full Name <span style="color: #f5576c;">*</span></label>
                            <input type="text" 
                                   id="fullname" 
                                   name="fullname" 
                                   value="<?php echo isset($_POST['fullname']) ? htmlspecialchars($_POST['fullname']) : ''; ?>" 
                                   placeholder="Enter student's full name"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span style="color: #f5576c;">*</span></label>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   placeholder="student@example.com"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="id_number">Student ID Number</label>
                            <input type="text" 
                                   id="id_number" 
                                   name="id_number" 
                                   value="<?php echo isset($_POST['id_number']) ? htmlspecialchars($_POST['id_number']) : ''; ?>"
                                   placeholder="e.g., 2024-0001">
                            <div class="password-hint">
                                <i class="fas fa-info-circle"></i>
                                Leave blank for auto-generated ID
                            </div>
                        </div>
                    </div>

                    <!-- Account Security -->
                    <div style="background: white; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-lock" style="color: var(--primary-color);"></i>
                            Account Security
                        </h3>

                        <div class="form-group">
                            <label for="password">Password <span style="color: #f5576c;">*</span></label>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   required>
                            <div class="password-hint">
                                <i class="fas fa-info-circle"></i>
                                Minimum 6 characters
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm Password <span style="color: #f5576c;">*</span></label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   required>
                        </div>
                    </div>

                    <!-- Enrollment Information -->
                    <div style="background: white; border-radius: 15px; padding: 25px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                        <h3 style="color: var(--text-primary); margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-graduation-cap" style="color: var(--primary-color);"></i>
                            Enrollment Information
                        </h3>

                        <div class="form-group">
                            <label for="grade_level">Grade Level <span style="color: #f5576c;">*</span></label>
                            <select id="grade_level" name="grade_level" required>
                                <option value="">Select Grade Level</option>
                                <?php while($grade = $grade_levels->fetch_assoc()): ?>
                                    <option value="<?php echo $grade['id']; ?>" 
                                        <?php echo (isset($_POST['grade_level']) && $_POST['grade_level'] == $grade['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($grade['grade_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="form-group" id="strand_group" style="<?php echo (!isset($_POST['grade_level']) || $_POST['grade_level'] < 5) ? 'display: none;' : ''; ?>">
                            <label for="strand">Strand (For Grade 11 & 12)</label>
                            <select id="strand" name="strand">
                                <option value="">Select Strand</option>
                                <?php 
                                if($strands && $strands->num_rows > 0) {
                                    while($strand_row = $strands->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo htmlspecialchars($strand_row['strand_name']); ?>"
                                        <?php echo (isset($_POST['strand']) && $_POST['strand'] == $strand_row['strand_name']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($strand_row['strand_name']); ?>
                                    </option>
                                <?php 
                                    endwhile;
                                } else {
                                ?>
                                    <option value="ICT">ICT</option>
                                    <option value="HE">HE</option>
                                    <option value="ABM">ABM</option>
                                    <option value="STEM">STEM</option>
                                    <option value="HUMSS">HUMSS</option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="form_138">Form 138 (Report Card)</label>
                            <div class="file-input-wrapper">
                                <input type="file" id="form_138" name="form_138" accept=".pdf,.jpg,.jpeg,.png">
                                <label for="form_138" class="file-input-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    Choose File
                                </label>
                                <div class="file-name" id="file_name">No file chosen</div>
                            </div>
                            <div class="password-hint">
                                <i class="fas fa-info-circle"></i>
                                Accepted formats: PDF, JPG, PNG (Max: 5MB)
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save"></i> Create Student
                        </button>
                        <a href="students.php" class="btn-cancel">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Show/hide strand field based on grade level
        document.getElementById('grade_level').addEventListener('change', function() {
            const strandGroup = document.getElementById('strand_group');
            const gradeValue = parseInt(this.value);
            
            if(gradeValue >= 5) { // Grade 11 (5) and Grade 12 (6)
                strandGroup.style.display = 'block';
            } else {
                strandGroup.style.display = 'none';
                document.getElementById('strand').value = '';
            }
        });

        // Display selected file name
        document.getElementById('form_138').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
            document.getElementById('file_name').textContent = fileName;
        });

        // Password confirmation validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if(password !== confirm) {
                e.preventDefault();
                alert('Passwords do not match!');
            }
        });
    </script>
</body>
</html>