<?php
// file: admin/student_details.php
session_start();
require_once '../includes/StudentClassifier.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: students.php');
    exit;
}

$classifier = new StudentClassifier();
$student = $classifier->getStudentClassification($_GET['id']);

if (!$student) {
    header('Location: students.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Details - High School System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content p-4" style="flex: 1; margin-left: 250px;">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="bi bi-person-badge text-primary"></i> 
                    Student Details
                </h2>
                <a href="students.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>

            <!-- Student Profile -->
            <div class="row">
                <div class="col-md-8">
                    <!-- Basic Information -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Basic Information</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 150px;">Full Name:</th>
                                    <td><strong><?php echo htmlspecialchars($student['fullname']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th>Student ID:</th>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($student['id_number'] ?? 'Not assigned'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                </tr>
                                <tr>
                                    <th>Account Created:</th>
                                    <td>
                                        <?php echo date('F d, Y h:i A', strtotime($student['account_created'])); ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Current Enrollment -->
                    <?php if (!empty($student['school_year'])): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Current Enrollment (<?php echo $student['school_year']; ?>)</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted">Grade Level</small>
                                        <h4><?php echo $student['current_grade'] ?? 'N/A'; ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted">Section</small>
                                        <h4><?php echo $student['current_section'] ?? 'Not assigned'; ?></h4>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="border rounded p-3 text-center">
                                        <small class="text-muted">Strand</small>
                                        <h4><?php echo $student['strand'] ?? '—'; ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <span class="badge bg-<?php echo $student['enrollment_status'] == 'Enrolled' ? 'success' : 'warning'; ?>">
                                    <?php echo $student['enrollment_status']; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Enrollment History -->
                    <?php if (!empty($student['enrollment_history'])): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Enrollment History</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php 
                                $history = explode(' → ', $student['enrollment_history']);
                                foreach ($history as $record): 
                                ?>
                                    <div class="d-flex mb-2">
                                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                                        <span><?php echo $record; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <hr>
                            <p class="text-muted mb-0">
                                <small>Total enrollments: <?php echo $student['total_enrollments']; ?></small>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Side Panel -->
                <div class="col-md-4">
                    <!-- Student Type Card -->
                    <div class="card mb-4">
                        <div class="card-body text-center">
                            <?php if ($student['is_old']): ?>
                                <div class="display-1 text-success mb-3">
                                    <i class="bi bi-arrow-repeat"></i>
                                </div>
                                <h4 class="text-success">Old Student</h4>
                                <p class="text-muted">
                                    Previously enrolled in <?php echo $student['previous_grade'] ?? 'previous grade'; ?>
                                </p>
                            <?php else: ?>
                                <div class="display-1 text-primary mb-3">
                                    <i class="bi bi-star"></i>
                                </div>
                                <h4 class="text-primary">New Student</h4>
                                <p class="text-muted">First time enrollee</p>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="mt-3">
                                <small class="text-muted d-block">Account Age</small>
                                <strong>
                                    <?php 
                                    $days = (time() - strtotime($student['account_created'])) / (60 * 60 * 24);
                                    echo round($days) . ' days';
                                    ?>
                                </strong>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="edit_student.php?id=<?php echo $student['id']; ?>" 
                                   class="btn btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit Student
                                </a>
                                <a href="enroll_student.php?id=<?php echo $student['id']; ?>" 
                                   class="btn btn-outline-success">
                                    <i class="bi bi-plus-circle"></i> New Enrollment
                                </a>
                                <a href="attendance.php?student_id=<?php echo $student['id']; ?>" 
                                   class="btn btn-outline-info">
                                    <i class="bi bi-calendar-check"></i> View Attendance
                                </a>
                                <a href="grades.php?student_id=<?php echo $student['id']; ?>" 
                                   class="btn btn-outline-warning">
                                    <i class="bi bi-bar-chart"></i> View Grades
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>