// High School System JavaScript
console.log("High School System JS loaded");

// ============================================
// 1. FORM VALIDATION
// ============================================

// Validate enrollment form
function validateEnrollmentForm() {
    const form = document.getElementById('enrollmentForm');
    if (!form) return true;
    
    const fullname = document.getElementById('fullname');
    const email = document.getElementById('email');
    const grade = document.getElementById('grade_id');
    const form138 = document.getElementById('form_138');
    
    let isValid = true;
    let errorMessage = '';
    
    // Name validation
    if (fullname && fullname.value.trim() === '') {
        errorMessage += '• Full name is required\n';
        fullname.style.borderColor = 'red';
        isValid = false;
    } else if (fullname) {
        fullname.style.borderColor = '#e9ecef';
    }
    
    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (email && !emailRegex.test(email.value)) {
        errorMessage += '• Valid email is required\n';
        email.style.borderColor = 'red';
        isValid = false;
    } else if (email) {
        email.style.borderColor = '#e9ecef';
    }
    
    // Grade validation
    if (grade && grade.value === '') {
        errorMessage += '• Please select a grade level\n';
        grade.style.borderColor = 'red';
        isValid = false;
    } else if (grade) {
        grade.style.borderColor = '#e9ecef';
    }
    
    // File upload validation
    if (form138 && form138.files.length > 0) {
        const file = form138.files[0];
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!allowedTypes.includes(file.type)) {
            errorMessage += '• File must be JPG, PNG, or PDF\n';
            form138.style.borderColor = 'red';
            isValid = false;
        } else if (file.size > maxSize) {
            errorMessage += '• File size must be less than 5MB\n';
            form138.style.borderColor = 'red';
            isValid = false;
        } else {
            form138.style.borderColor = '#e9ecef';
        }
    }
    
    if (!isValid) {
        alert('Please fix the following errors:\n' + errorMessage);
        return false;
    }
    
    return true;
}

// Validate grade input
function validateGrade(input) {
    const value = parseFloat(input.value);
    
    if (input.value === '') {
        input.classList.remove('passing', 'failing');
        return true;
    }
    
    if (isNaN(value) || value < 0 || value > 100) {
        input.style.borderColor = '#dc3545';
        return false;
    }
    
    input.style.borderColor = '#28a745';
    
    if (value >= 75) {
        input.classList.add('passing');
        input.classList.remove('failing');
    } else {
        input.classList.add('failing');
        input.classList.remove('passing');
    }
    
    return true;
}

// ============================================
// 2. SEARCH AND FILTER FUNCTIONALITY
// ============================================

// Search tables
function initializeTableSearch() {
    const searchInputs = document.querySelectorAll('.table-search');
    
    searchInputs.forEach(input => {
        input.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const tableId = this.dataset.table;
            const table = document.getElementById(tableId);
            
            if (!table) return;
            
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    });
}

// Filter dropdowns
function initializeFilters() {
    const filterSelects = document.querySelectorAll('.filter-select');
    
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            const filterValue = this.value.toLowerCase();
            const targetClass = this.dataset.target;
            const items = document.querySelectorAll(targetClass);
            
            items.forEach(item => {
                if (filterValue === '' || item.dataset.filter === filterValue) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    });
}

// ============================================
// 3. BULK ACTIONS (Select All)
// ============================================

function initializeBulkActions() {
    const selectAllCheckboxes = document.querySelectorAll('.select-all');
    
    selectAllCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const targetClass = this.dataset.target;
            const checkboxes = document.querySelectorAll(targetClass);
            
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
        });
    });
}

// ============================================
// 4. MODAL HANDLING
// ============================================

// Open modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

// Close modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

// Close modal when clicking outside
function initializeModals() {
    const modals = document.querySelectorAll('.modal');
    
    modals.forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });
    });
}

// ============================================
// 5. CHART INITIALIZATION (for dashboard)
// ============================================

function initializeCharts() {
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') return;
    
    // Enrollment trends chart
    const enrollmentCanvas = document.getElementById('enrollmentChart');
    if (enrollmentCanvas) {
        new Chart(enrollmentCanvas, {
            type: 'line',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Enrollments',
                    data: [12, 19, 15, 25],
                    borderColor: '#0B4F2E',
                    backgroundColor: 'rgba(11, 79, 46, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    
    // Grade distribution chart
    const gradesCanvas = document.getElementById('gradesChart');
    if (gradesCanvas) {
        new Chart(gradesCanvas, {
            type: 'doughnut',
            data: {
                labels: ['90-100', '80-89', '75-79', 'Below 75'],
                datasets: [{
                    data: [15, 35, 25, 10],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
}

// ============================================
// 6. PRINT AND EXPORT FUNCTIONS
// ============================================

// Print table
function printTable(tableId, title) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const newWindow = window.open('', '_blank');
    newWindow.document.write(`
        <html>
            <head>
                <title>${title}</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 30px; }
                    h2 { color: #0B4F2E; }
                    table { border-collapse: collapse; width: 100%; margin-top: 20px; }
                    th { background: #f0f0f0; padding: 10px; text-align: left; }
                    td { padding: 8px; border-bottom: 1px solid #ddd; }
                    .header { text-align: center; margin-bottom: 30px; }
                    .date { color: #666; font-size: 12px; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h2>Placido L. Señor Senior High School</h2>
                    <h3>${title}</h3>
                    <div class="date">Generated on: ${new Date().toLocaleString()}</div>
                </div>
                ${table.outerHTML}
            </body>
        </html>
    `);
    newWindow.document.close();
    newWindow.print();
}

// Export to CSV
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = [];
    const headers = [];
    
    // Get headers
    table.querySelectorAll('thead th').forEach(th => {
        headers.push(th.innerText);
    });
    rows.push(headers.join(','));
    
    // Get data rows
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            // Remove HTML and escape commas
            let text = td.innerText.replace(/"/g, '""');
            row.push(`"${text}"`);
        });
        rows.push(row.join(','));
    });
    
    // Create and download CSV
    const csv = rows.join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${filename}_${new Date().toISOString().slice(0,10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// ============================================
// 7. NOTIFICATION SYSTEM
// ============================================

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Style the notification
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        background: ${type === 'success' ? '#d4edda' : '#f8d7da'};
        color: ${type === 'success' ? '#155724' : '#721c24'};
        border-left: 4px solid ${type === 'success' ? '#28a745' : '#dc3545'};
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        z-index: 9999;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 5000);
}

// ============================================
// 8. CONFIRMATION DIALOGS
// ============================================

// Confirm action
function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Delete confirmation
function confirmDelete(itemType, itemName, deleteUrl) {
    if (confirm(`Are you sure you want to delete this ${itemType}: "${itemName}"?`)) {
        window.location.href = deleteUrl;
    }
}

// ============================================
// 9. AUTO-HIDE ALERTS
// ============================================

function initializeAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.style.display = 'none';
            }, 300);
        }, 5000);
    });
}

// ============================================
// 10. TOOLTIPS
// ============================================

function initializeTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'custom-tooltip';
            tooltip.textContent = this.dataset.tooltip;
            tooltip.style.cssText = `
                position: absolute;
                background: #333;
                color: white;
                padding: 5px 10px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 10000;
                pointer-events: none;
            `;
            
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            
            this.addEventListener('mouseleave', function() {
                tooltip.remove();
            });
        });
    });
}

// ============================================
// 11. GRADE CALCULATIONS
// ============================================

// Calculate average grade
function calculateAverage(grades) {
    if (!grades || grades.length === 0) return 0;
    const sum = grades.reduce((acc, grade) => acc + parseFloat(grade), 0);
    return (sum / grades.length).toFixed(2);
}

// Determine if passed
function isPassing(grade) {
    return parseFloat(grade) >= 75;
}

// Get grade remarks
function getGradeRemarks(grade) {
    if (grade >= 90) return 'Outstanding';
    if (grade >= 85) return 'Very Satisfactory';
    if (grade >= 80) return 'Satisfactory';
    if (grade >= 75) return 'Fairly Satisfactory';
    return 'Failed';
}

// ============================================
// 12. INITIALIZE ALL FEATURES
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('High School System initialized');
    
    // Initialize all features
    initializeTableSearch();
    initializeFilters();
    initializeBulkActions();
    initializeModals();
    initializeCharts();
    initializeAlerts();
    initializeTooltips();
    
    // Add form validation to enrollment forms
    const enrollmentForm = document.getElementById('enrollmentForm');
    if (enrollmentForm) {
        enrollmentForm.addEventListener('submit', function(e) {
            if (!validateEnrollmentForm()) {
                e.preventDefault();
            }
        });
    }
    
    // Add grade validation to grade inputs
    document.querySelectorAll('.grade-input').forEach(input => {
        input.addEventListener('input', function() {
            validateGrade(this);
        });
    });
});