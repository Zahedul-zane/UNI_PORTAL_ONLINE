/**
 * East West University Portal - Core Frontend Application Framework
 */

// Automatically resolve backend API endpoint whether accessed via port 8000, Live Server (5500), or file://
const API_BASE = (function() {
    if (typeof window === 'undefined') return '/api';
    const host = window.location.hostname || 'localhost';
    if (window.location.protocol === 'file:') {
        return `http://${host}:8000/api`;
    }
    // If running via Live Server (ports starting with 55) or static preview, connect to backend port 8000
    if (window.location.port && (window.location.port.startsWith('55') || window.location.port === '5000')) {
        return `http://${host}:8000/api`;
    }
    return '/api';
})();

const App = {
    user: null,

    async init() {
        try {
            const token = localStorage.getItem('ewu_token');
            const headers = token ? { 'Authorization': `Bearer ${token}` } : {};
            const res = await fetch(`${API_BASE}/me`, { headers, credentials: 'include' });
            let data = {};
            try { data = await res.json(); } catch(e) { data = { logged_in: false }; }
            if (data.logged_in) {
                this.user = data;
                this.renderLayout();
            } else {
                this.user = null;
                localStorage.removeItem('ewu_token');
                // If not on login.html, register.html or index.html, redirect to login
                const currentPath = window.location.pathname;
                if (!currentPath.endsWith('login.html') && !currentPath.endsWith('register.html') && currentPath !== '/' && !currentPath.endsWith('index.html')) {
                    window.location.href = '/login.html';
                }
            }
        } catch (e) {
            console.error("Auth check failed", e);
        }
    },

    async requireAuth(requiredRole = null) {
        if (!this.user) {
            await this.init();
        }
        if (!this.user || !this.user.logged_in) {
            window.location.href = '/login.html';
            return false;
        }
        if (requiredRole && this.user.role !== requiredRole) {
            alert(`Access denied. ${requiredRole} role required.`);
            window.location.href = `/${this.user.role}/dashboard.html`;
            return false;
        }
        return true;
    },

    async request(endpoint, options = {}) {
        options.headers = options.headers || {};
        options.credentials = 'include';
        const token = localStorage.getItem('ewu_token');
        if (token && !options.headers['Authorization']) {
            options.headers['Authorization'] = `Bearer ${token}`;
        }
        if (options.body && typeof options.body === 'object' && !(options.body instanceof FormData)) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }
        const res = await fetch(`${API_BASE}${endpoint}`, options);
        let data = {};
        try {
            data = await res.json();
        } catch (e) {
            data = { success: false, error: res.statusText || 'Server communication error' };
        }
        if (!res.ok && !data.success) {
            throw new Error(data.error || 'Request failed');
        }
        return data;
    },

    get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    },

    post(endpoint, body) {
        return this.request(endpoint, { method: 'POST', body });
    },

    delete(endpoint, queryParams = {}) {
        let url = endpoint;
        const q = new URLSearchParams(queryParams).toString();
        if (q) url += `?${q}`;
        return this.request(url, { method: 'DELETE' });
    },

    async logout() {
        try {
            await this.post('/logout', {});
        } catch (e) {}
        localStorage.removeItem('ewu_token');
        localStorage.removeItem('ewu_role');
        window.location.href = '/login.html';
    },

    renderLayout() {
        const sidebarEl = document.getElementById('app-sidebar');
        const headerEl = document.getElementById('app-header');

        if (sidebarEl && this.user) {
            sidebarEl.innerHTML = this.getSidebarHTML();
        }
        if (headerEl && this.user) {
            headerEl.innerHTML = this.getHeaderHTML();
        }
    },

    getSidebarHTML() {
        if (!this.user) return '';
        const role = this.user.role;
        const currentPath = window.location.pathname;
        const page = currentPath.split('/').pop();

        let menuHtml = '';

        if (role === 'student') {
            menuHtml = `
                <div class="nav-section-label">Student Menu</div>
                <a href="/student/dashboard.html" class="nav-item ${page === 'dashboard.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
                <a href="/student/profile.html" class="nav-item ${page === 'profile.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    My Profile
                </a>
                <a href="/student/pre_advising.html" class="nav-item ${page === 'pre_advising.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Pre-Advising Requests
                </a>
                <a href="/student/advising.html" class="nav-item ${page === 'advising.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Official Advising (Take Section)
                </a>
                <a href="/student/enrolled_courses.html" class="nav-item ${page === 'enrolled_courses.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    Enrolled Courses & Grades
                </a>
                <a href="/student/payments.html" class="nav-item ${page === 'payments.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Payments & Ledger
                </a>
            `;
        } else if (role === 'faculty') {
            menuHtml = `
                <div class="nav-section-label">Faculty Menu</div>
                <a href="/faculty/dashboard.html" class="nav-item ${page === 'dashboard.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
                <a href="/faculty/profile.html" class="nav-item ${page === 'profile.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                    My Profile
                </a>
                <a href="/faculty/sections.html" class="nav-item ${page === 'sections.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    Assigned Sections
                </a>
                <a href="/faculty/grading.html" class="nav-item ${page === 'grading.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    Gradebook Management
                </a>
                <a href="/faculty/advisees.html" class="nav-item ${page === 'advisees.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    My Advisees & Approvals
                </a>
            `;
        } else if (role === 'admin') {
            menuHtml = `
                <div class="nav-section-label">Admin Management</div>
                <a href="/admin/dashboard.html" class="nav-item ${page === 'dashboard.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard
                </a>
                <a href="/admin/departments.html" class="nav-item ${page === 'departments.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                    Departments
                </a>
                <a href="/admin/faculty.html" class="nav-item ${page === 'faculty.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    Faculty Members
                </a>
                <a href="/admin/students.html" class="nav-item ${page === 'students.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0112 20.055a11.952 11.952 0 01-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"></path></svg>
                    Students Directory
                </a>
                <a href="/admin/courses.html" class="nav-item ${page === 'courses.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    Courses & Prerequisites
                </a>
                <a href="/admin/sections.html" class="nav-item ${page === 'sections.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    Class Sections
                </a>
                <a href="/admin/enrollments.html" class="nav-item ${page === 'enrollments.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                    Master Enrollments
                </a>
                <a href="/admin/payments.html" class="nav-item ${page === 'payments.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Payments Ledger
                </a>
                <a href="/admin/excel_manager.html" class="nav-item ${page === 'excel_manager.html' ? 'active' : ''}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Excel Database
                </a>
            `;
        }

        return `
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <img src="/images/EWU_LOGO.png" alt="EWU Logo">
                </div>
                <div class="sidebar-brand">
                    EAST WEST
                    <span>PORTAL SYSTEM</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                ${menuHtml}
            </nav>
            <div class="sidebar-footer">
                <div class="user-mini-card">
                    <div class="user-avatar">
                        ${this.user.name ? this.user.name.charAt(0).toUpperCase() : 'U'}
                    </div>
                    <div class="user-info">
                        <div class="user-name">${this.escape(this.user.name)}</div>
                        <div class="user-role-badge">${this.escape(this.user.role)} • ${this.escape(this.user.user_id)}</div>
                    </div>
                </div>
            </div>
        `;
    },

    getHeaderHTML() {
        if (!this.user) return '';
        return `
            <div class="header-left">
                <span class="header-system-title">East West University • Academic Portal</span>
            </div>
            <div class="header-right">
                <div class="header-user-info">
                    <span class="user-display-name">${this.escape(this.user.name)}</span>
                    <span class="role-pill pill-${this.escape(this.user.role)}">${this.escape(this.user.role.toUpperCase())}</span>
                </div>
                <button onclick="App.logout()" class="btn btn-outline btn-sm logout-btn" title="Sign Out">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    Logout
                </button>
            </div>
        `;
    },

    escape(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    },

    showAlert(msg, type = 'success', containerId = 'alert-container') {
        const container = document.getElementById(containerId);
        if (!container) return;
        container.innerHTML = `<div class="alert alert-${type}">${msg}</div>`;
        setTimeout(() => {
            if (container.innerHTML.includes(msg)) {
                container.innerHTML = '';
            }
        }, 5000);
    },

    parseTimeToMinutes(timeStr) {
        if (!timeStr) return 0;
        const s = timeStr.trim();
        const match = s.match(/(\d+):(\d+)\s*(AM|PM)?/i);
        if (!match) return 0;
        let hour = parseInt(match[1], 10);
        const min = parseInt(match[2], 10);
        const ampm = (match[3] || '').toUpperCase();
        if (ampm === 'PM' && hour < 12) hour += 12;
        if (ampm === 'AM' && hour === 12) hour = 0;
        return hour * 60 + min;
    },

    parseTimeSlots(slotStr) {
        const slots = [];
        if (!slotStr) return slots;
        const s = slotStr.trim();
        const match = s.match(/^([A-Za-z\-]+)\s+(.+?)\s*-\s*(.+)$/);
        let dayToken = '';
        let startStr = '';
        let endStr = '';

        if (match) {
            dayToken = match[1].trim();
            startStr = match[2].trim();
            endStr = match[3].trim();
        } else {
            const spaceIdx = s.indexOf(' ');
            if (spaceIdx === -1) return slots;
            dayToken = s.substring(0, spaceIdx).trim();
            const rest = s.substring(spaceIdx + 1).trim();
            const sepIdx = rest.indexOf('-');
            if (sepIdx === -1) return slots;
            startStr = rest.substring(0, sepIdx).trim();
            endStr = rest.substring(sepIdx + 1).trim();
        }

        const startMins = this.parseTimeToMinutes(startStr);
        const endMins = this.parseTimeToMinutes(endStr);

        let days = [];
        const dtUpper = dayToken.toUpperCase();
        if (dtUpper === 'SUN-TUE' || dtUpper === 'S-T' || dtUpper === 'ST') {
            days = ['Sun', 'Tue'];
        } else if (dtUpper === 'MON-WED' || dtUpper === 'M-W' || dtUpper === 'MW') {
            days = ['Mon', 'Wed'];
        } else if (dtUpper === 'THU-SAT' || dtUpper === 'R-S' || dtUpper === 'TR') {
            days = ['Thu', 'Sat'];
        } else if (dtUpper === 'S' || dtUpper === 'SUN') {
            days = ['Sun'];
        } else if (dtUpper === 'M' || dtUpper === 'MON') {
            days = ['Mon'];
        } else if (dtUpper === 'T' || dtUpper === 'TUE') {
            days = ['Tue'];
        } else if (dtUpper === 'W' || dtUpper === 'WED') {
            days = ['Wed'];
        } else if (dtUpper === 'R' || dtUpper === 'THU') {
            days = ['Thu'];
        } else if (dtUpper === 'F' || dtUpper === 'FRI') {
            days = ['Fri'];
        } else {
            days = [dayToken];
        }

        slots.push({ days, startMins, endMins });
        return slots;
    },

    slotsOverlap(slot1, slot2) {
        const list1 = this.parseTimeSlots(slot1);
        const list2 = this.parseTimeSlots(slot2);
        for (const a of list1) {
            for (const b of list2) {
                let dayMatch = false;
                for (const d1 of a.days) {
                    for (const d2 of b.days) {
                        if (d1.toLowerCase() === d2.toLowerCase()) {
                            dayMatch = true;
                            break;
                        }
                    }
                    if (dayMatch) break;
                }
                if (dayMatch) {
                    if (a.startMins < b.endMins && b.startMins < a.endMins) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    App.init();
});
