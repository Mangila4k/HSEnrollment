<?php
session_start();
include("../config/database.php");

// Check if user is registrar
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'Registrar'){
    header("Location: ../auth/login.php");
    exit();
}

// Get filter parameters
$school_year = isset($_GET['school_year']) ? $_GET['school_year'] : date('Y') . '-' . (date('Y')+1);
$grade_id = isset($_GET['grade_id']) ? $_GET['grade_id'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// Build query for statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status='Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='Enrolled' THEN 1 ELSE 0 END) as enrolled,
    SUM(CASE WHEN status='Rejected' THEN 1 ELSE 0 END) as rejected
    FROM enrollments WHERE 1=1";

if($school_year) $stats_query .= " AND school_year='$school_year'";
$stats = $conn->query($stats_query)->fetch_assoc();

// Grade level distribution
$grade_distribution = $conn->query("
    SELECT g.grade_name, COUNT(*) as count 
    FROM enrollments e
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE 1=1
    " . ($school_year ? " AND e.school_year='$school_year'" : "") . "
    GROUP BY e.grade_id
");

// Strand distribution for SHS
$strand_distribution = $conn->query("
    SELECT strand, COUNT(*) as count 
    FROM enrollments e
    WHERE grade_id IN (SELECT id FROM grade_levels WHERE grade_name IN ('Grade 11', 'Grade 12'))
    " . ($school_year ? " AND e.school_year='$school_year'" : "") . "
    GROUP BY strand
");

// Monthly enrollment trend
$monthly_trend = $conn->query("
    SELECT 
        MONTH(created_at) as month,
        COUNT(*) as count
    FROM enrollments
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY MONTH(created_at)
    ORDER BY month
");

// Get all enrollments for detailed report
$enrollments = $conn->query("
    SELECT e.*, u.fullname, g.grade_name
    FROM enrollments e
    LEFT JOIN users u ON e.student_id = u.id
    LEFT JOIN grade_levels g ON e.grade_id = g.id
    WHERE 1=1
    " . ($school_year ? " AND e.school_year='$school_year'" : "") . "
    " . ($grade_id ? " AND e.grade_id='$grade_id'" : "") . "
    " . ($status ? " AND e.status='$status'" : "") . "
    ORDER BY e.id DESC
");

$grades = $conn->query("SELECT * FROM grade_levels");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Reports - Placido L. Señor Senior High School</title>
    <link rel="stylesheet" href="../assets/css/enrollment.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .filter-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            color: #555;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
        }

        .filter-group select,
        .filter-group input {
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
            text-align: center;
            border-left: 4px solid #0B4F2E;
        }

        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #0B4F2E;
            margin: 10px 0;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.05);
        }

        .chart-card h3 {
            margin-bottom: 20px;
            color: #333;
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

        .report-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="school-logo">PLS</div>
            <div>
                <h1>Enrollment Reports<br><span>Analytics and Statistics</span></h1>
            </div>
        </div>
        <div>
            <a href="enrollments.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; margin-right: 10px;">← Back</a>
            <a href="../auth/logout.php" class="btn" style="background: rgba(255,255,255,0.2); color: white;">Logout</a>
        </div>
    </div>

    <div class="container">
        <!-- Filter Section -->
        <div class="filter-card">
            <form method="GET" class="filter-grid">
                <div class="filter-group">
                    <label>School Year</label>
                    <input type="text" name="school_year" value="<?php echo $school_year; ?>" placeholder="e.g., 2024-2025">
                </div>
                
                <div class="filter-group">
                    <label>Grade Level</label>
                    <select name="grade_id">
                        <option value="">All Grades</option>
                        <?php 
                        $grades->data_seek(0);
                        while($g = $grades->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $g['id']; ?>" <?php echo $grade_id == $g['id'] ? 'selected' : ''; ?>>
                                <?php echo $g['grade_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Enrolled" <?php echo $status == 'Enrolled' ? 'selected' : ''; ?>>Enrolled</option>
                        <option value="Rejected" <?php echo $status == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">Generate Report</button>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div>📊 Total Enrollments</div>
                <div class="stat-number"><?php echo $stats['total'] ?? 0; ?></div>
                <div>For <?php echo $school_year; ?></div>
            </div>
            
            <div class="stat-card" style="border-left-color: #f59e0b;">
                <div>⏳ Pending</div>
                <div class="stat-number" style="color: #f59e0b;"><?php echo $stats['pending'] ?? 0; ?></div>
                <div>Awaiting review</div>
            </div>
            
            <div class="stat-card" style="border-left-color: #10b981;">
                <div>✅ Enrolled</div>
                <div class="stat-number" style="color: #10b981;"><?php echo $stats['enrolled'] ?? 0; ?></div>
                <div>Successfully enrolled</div>
            </div>
            
            <div class="stat-card" style="border-left-color: #ef4444;">
                <div>❌ Rejected</div>
                <div class="stat-number" style="color: #ef4444;"><?php echo $stats['rejected'] ?? 0; ?></div>
                <div>Not approved</div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3>Grade Level Distribution</h3>
                <canvas id="gradeChart" style="height: 300px;"></canvas>
            </div>
            
            <div class="chart-card">
                <h3>Strand Distribution (SHS)</h3>
                <canvas id="strandChart" style="height: 300px;"></canvas>
            </div>
            
            <div class="chart-card">
                <h3>Monthly Enrollment Trend</h3>
                <canvas id="trendChart" style="height: 300px;"></canvas>
            </div>
            
            <div class="chart-card">
                <h3>Status Distribution</h3>
                <canvas id="statusChart" style="height: 300px;"></canvas>
            </div>
        </div>

        <!-- Detailed Report Table -->
        <div class="table-container">
            <h3 style="margin-bottom: 20px;">Detailed Enrollment Report</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student Name</th>
                        <th>Grade Level</th>
                        <th>Strand</th>
                        <th>School Year</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $enrollments->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo $row['id']; ?></td>
                            <td><?php echo $row['fullname']; ?></td>
                            <td><?php echo $row['grade_name']; ?></td>
                            <td><?php echo $row['strand'] ?: '—'; ?></td>
                            <td><?php echo $row['school_year']; ?></td>
                            <td>
                                <span style="color: <?php 
                                    echo $row['status'] == 'Enrolled' ? '#10b981' : 
                                        ($row['status'] == 'Pending' ? '#f59e0b' : '#ef4444'); 
                                ?>">
                                    ● <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($row['created_at'] ?? 'now')); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Report Actions -->
        <div class="report-actions">
            <button onclick="exportToPDF()" class="btn btn-primary">📄 Export to PDF</button>
            <button onclick="exportToExcel()" class="btn btn-primary">📊 Export to Excel</button>
            <button onclick="window.print()" class="btn btn-primary">🖨️ Print Report</button>
        </div>
    </div>

    <script>
        // Grade Distribution Chart
        const gradeCtx = document.getElementById('gradeChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    $grades->data_seek(0);
                    $labels = [];
                    $counts = [];
                    while($g = $grades->fetch_assoc()) {
                        echo "'" . $g['grade_name'] . "',";
                    }
                ?>],
                datasets: [{
                    label: 'Number of Students',
                    data: [<?php 
                        $grades->data_seek(0);
                        while($g = $grades->fetch_assoc()) {
                            $count = $conn->query("SELECT COUNT(*) as c FROM enrollments WHERE grade_id='{$g['id']}' " . ($school_year ? "AND school_year='$school_year'" : ""))->fetch_assoc()['c'];
                            echo $count . ",";
                        }
                    ?>],
                    backgroundColor: '#0B4F2E',
                    borderRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Strand Distribution Chart
        const strandCtx = document.getElementById('strandChart').getContext('2d');
        new Chart(strandCtx, {
            type: 'pie',
            data: {
                labels: [<?php 
                    $strand_distribution->data_seek(0);
                    while($s = $strand_distribution->fetch_assoc()) {
                        if($s['strand']) {
                            echo "'" . $s['strand'] . "',";
                        }
                    }
                ?>],
                datasets: [{
                    data: [<?php 
                        $strand_distribution->data_seek(0);
                        while($s = $strand_distribution->fetch_assoc()) {
                            if($s['strand']) {
                                echo $s['count'] . ",";
                            }
                        }
                    ?>],
                    backgroundColor: ['#0B4F2E', '#f59e0b', '#10b981', '#3b82f6', '#8b5cf6', '#ec4899']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        // Monthly Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Enrollments',
                    data: [<?php 
                        for($i = 1; $i <= 12; $i++) {
                            $found = false;
                            $monthly_trend->data_seek(0);
                            while($m = $monthly_trend->fetch_assoc()) {
                                if($m['month'] == $i) {
                                    echo $m['count'] . ",";
                                    $found = true;
                                    break;
                                }
                            }
                            if(!$found) echo "0,";
                        }
                    ?>],
                    borderColor: '#0B4F2E',
                    backgroundColor: 'rgba(11, 79, 46, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Enrolled', 'Rejected'],
                datasets: [{
                    data: [<?php echo $stats['pending'] ?? 0; ?>, <?php echo $stats['enrolled'] ?? 0; ?>, <?php echo $stats['rejected'] ?? 0; ?>],
                    backgroundColor: ['#f59e0b', '#10b981', '#ef4444']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });

        function exportToExcel() {
            window.location.href = 'export_excel.php?school_year=<?php echo $school_year; ?>&grade_id=<?php echo $grade_id; ?>&status=<?php echo $status; ?>';
        }

        function exportToPDF() {
            window.location.href = 'export_pdf.php?school_year=<?php echo $school_year; ?>&grade_id=<?php echo $grade_id; ?>&status=<?php echo $status; ?>';
        }
    </script>
</body>
</html>