/* 
   EWU University Portal - Interactive JavaScript Controller
*/

document.addEventListener('DOMContentLoaded', function () {
    
    // --- 1. LOGIN ROLE TAB & DEMO FILL HANDLER ---
    const roleTabs = document.querySelectorAll('.role-tab');
    const roleInput = document.getElementById('selected_role');
    const usernameInput = document.getElementById('user_id');
    const passwordInput = document.getElementById('password');

    if (roleTabs.length > 0) {
        roleTabs.forEach(tab => {
            tab.addEventListener('click', function () {
                roleTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                const selectedRole = this.getAttribute('data-role');
                if (roleInput) roleInput.value = selectedRole;
                
                // Update placeholder label
                const label = document.getElementById('user_id_label');
                if (label) {
                    if (selectedRole === 'student') {
                        label.textContent = 'Student ID (e.g. 2023-3-60-621)';
                    } else if (selectedRole === 'faculty') {
                        label.textContent = 'Faculty ID (e.g. 1652688915)';
                    } else {
                        label.textContent = 'Username (admin)';
                    }
                }
            });
        });
    }

    // Demo credentials chip click handler
    window.fillDemo = function (role, username, pass) {
        // Activate role tab
        const targetTab = document.querySelector(`.role-tab[data-role="${role}"]`);
        if (targetTab) targetTab.click();
        
        if (usernameInput) usernameInput.value = username;
        if (passwordInput) passwordInput.value = pass;
    };

    // --- 2. MOBILE SIDEBAR TOGGLE ---
    const sidebarToggle = document.getElementById('sidebar_toggle');
    const sidebar = document.querySelector('.app-sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });
    }

    // --- 3. LIVE TABLE SEARCH / FILTER ---
    const searchInputs = document.querySelectorAll('.table-search-input');
    searchInputs.forEach(input => {
        input.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const targetTableId = this.getAttribute('data-table');
            const table = document.getElementById(targetTableId);
            
            if (table) {
                const rows = table.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            }
        });
    });

    // --- 4. MODAL DIALOG HANDLERS ---
    window.openModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.add('active');
    };

    window.closeModal = function (modalId) {
        const modal = document.getElementById(modalId);
        if (modal) modal.classList.remove('active');
    };

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function (e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // --- 5. AUTOMATIC GRADE CALCULATOR (EWU OFFICIAL POLICY) ---
    const midInput = document.getElementById('calc_mid');
    const finalInput = document.getElementById('calc_final');
    const gradeOutput = document.getElementById('calc_grade');

    function calculateGrade() {
        if (!midInput || !finalInput || !gradeOutput) return;
        
        const mid = parseFloat(midInput.value) || 0;
        const final = parseFloat(finalInput.value) || 0;
        const total = mid + final;

        let grade = 'F';
        if (total >= 80.0) grade = 'A+';
        else if (total >= 75.0) grade = 'A';
        else if (total >= 70.0) grade = 'A-';
        else if (total >= 65.0) grade = 'B+';
        else if (total >= 60.0) grade = 'B';
        else if (total >= 55.0) grade = 'B-';
        else if (total >= 50.0) grade = 'C+';
        else if (total >= 45.0) grade = 'C';
        else if (total >= 40.0) grade = 'D';
        else grade = 'F';

        gradeOutput.value = grade;
    }

    if (midInput && finalInput) {
        midInput.addEventListener('input', calculateGrade);
        finalInput.addEventListener('input', calculateGrade);
    }
});
