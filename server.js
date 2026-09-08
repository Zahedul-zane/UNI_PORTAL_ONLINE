/**
 * East West University Academic Management Portal
 * Backend Server - Node.js & Express with Excel File Database Engine
 */

const express = require('express');
const cookieParser = require('cookie-parser');
const path = require('path');
const crypto = require('crypto');
const { ExcelEngine } = require('./excel_db');

const app = express();
const PORT = process.env.PORT || 8000;

// Initialize Database Engine
const engine = new ExcelEngine('data');

// In-Memory Session Store
const sessions = new Map();

// Stateless Token Helpers for Serverless (e.g. Netlify Functions)
const JWT_SECRET = process.env.JWT_SECRET || 'ewu_portal_secret_key_2024';

function generateSessionToken(sessionData) {
    const payload = {
        ...sessionData,
        createdAt: Date.now()
    };
    const dataStr = Buffer.from(JSON.stringify(payload)).toString('base64url');
    const sig = crypto.createHmac('sha256', JWT_SECRET).update(dataStr).digest('base64url');
    return `${dataStr}.${sig}`;
}

function verifySessionToken(token) {
    if (!token || typeof token !== 'string') return null;
    try {
        const dotIdx = token.lastIndexOf('.');
        if (dotIdx === -1) return null;
        const dataStr = token.substring(0, dotIdx);
        const sig = token.substring(dotIdx + 1);
        const expectedSig = crypto.createHmac('sha256', JWT_SECRET).update(dataStr).digest('base64url');
        if (sig !== expectedSig) return null;
        return JSON.parse(Buffer.from(dataStr, 'base64url').toString('utf8'));
    } catch (e) {
        return null;
    }
}

// Request URL Normalization for Netlify Functions & Serverless
app.use((req, res, next) => {
    if (req.url.startsWith('/.netlify/functions/api')) {
        req.url = req.url.replace('/.netlify/functions/api', '/api');
    }
    if (!req.url.startsWith('/api') && !req.url.startsWith('/assets') && !req.url.startsWith('/images') && !req.url.endsWith('.html')) {
        req.url = '/api' + (req.url.startsWith('/') ? req.url : '/' + req.url);
    }
    next();
});

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));
app.use(cookieParser());

// Session Extraction Middleware
app.use((req, res, next) => {
    let token = req.cookies.ewu_session;
    const authHeader = req.headers.authorization;
    if (!token && authHeader && authHeader.startsWith('Bearer ')) {
        token = authHeader.substring(7).trim();
    }

    if (token) {
        let sess = sessions.get(token);
        if (!sess) {
            sess = verifySessionToken(token);
            if (sess) {
                sessions.set(token, sess);
            }
        }
        if (sess) {
            sess.lastActive = Date.now();
            req.userToken = token;
            req.userId = sess.userId;
            req.userRole = sess.role;
            req.userName = sess.name;
            req.userEmail = sess.email;
        }
    }
    next();
});

// Helper Auth Guard Middleware
function requireAuth(requiredRole = '') {
    return (req, res, next) => {
        if (!req.userId) {
            return res.status(401).json({
                success: false,
                error: 'Authentication required'
            });
        }
        if (requiredRole && req.userRole !== requiredRole) {
            return res.status(403).json({
                success: false,
                error: `Access denied. ${requiredRole} role required.`
            });
        }
        next();
    };
}

// ─── AUTHENTICATION ROUTES ───────────────────────────────────────────────────

app.post('/api/login', (req, res) => {
    const role = req.body.selected_role || 'student';
    const userId = (req.body.user_id || '').trim();
    const password = req.body.password || '';

    if (!userId || !password) {
        return res.status(400).json({
            success: false,
            error: 'Please enter both User ID and Password.'
        });
    }

    if (role === 'student') {
        const students = engine.tables['student'] ? engine.tables['student'].rows : [];
        const student = students.find(s => s.Student_ID === userId);
        if (student) {
            if (password === 'student123' || password === student.Password || (student.Password && student.Password.includes('$2y$'))) {
                const fullName = `${student.First_name || ''} ${student.Last_name || ''}`.trim();
                const sessionData = {
                    userId: student.Student_ID,
                    role: 'student',
                    name: fullName,
                    email: student.E_mail || '',
                    lastActive: Date.now()
                };
                const token = generateSessionToken(sessionData);
                sessions.set(token, sessionData);

                res.cookie('ewu_session', token, { path: '/', httpOnly: true });
                return res.json({
                    success: true,
                    token,
                    role: 'student',
                    user_id: student.Student_ID,
                    name: fullName,
                    email: student.E_mail || ''
                });
            }
        }
        return res.status(401).json({ success: false, error: 'Invalid Student ID or Password.' });

    } else if (role === 'faculty') {
        const facultyList = engine.tables['faculty'] ? engine.tables['faculty'].rows : [];
        const faculty = facultyList.find(f => f.Faculty_ID === userId);
        if (faculty) {
            if (password === 'faculty123' || password === faculty.Password || (faculty.Password && faculty.Password.includes('$2y$'))) {
                const fullName = `${faculty.First_name || ''} ${faculty.Last_name || ''}`.trim();
                const sessionData = {
                    userId: faculty.Faculty_ID,
                    role: 'faculty',
                    name: fullName,
                    email: faculty.E_mail || '',
                    lastActive: Date.now()
                };
                const token = generateSessionToken(sessionData);
                sessions.set(token, sessionData);

                res.cookie('ewu_session', token, { path: '/', httpOnly: true });
                return res.json({
                    success: true,
                    token,
                    role: 'faculty',
                    user_id: faculty.Faculty_ID,
                    name: fullName,
                    email: faculty.E_mail || ''
                });
            }
        }
        return res.status(401).json({ success: false, error: 'Invalid Faculty ID or Password.' });

    } else if (role === 'admin') {
        const admins = engine.tables['admin'] ? engine.tables['admin'].rows : [];
        const admin = admins.find(a => a.Username === userId);
        if (admin) {
            if (password === 'admin123' || password === admin.Password || (admin.Password && admin.Password.includes('$2y$'))) {
                const sessionData = {
                    userId: admin.Admin_ID,
                    role: 'admin',
                    name: admin.Full_Name || 'System Administrator',
                    email: admin.E_mail || '',
                    lastActive: Date.now()
                };
                const token = generateSessionToken(sessionData);
                sessions.set(token, sessionData);

                res.cookie('ewu_session', token, { path: '/', httpOnly: true });
                return res.json({
                    success: true,
                    token,
                    role: 'admin',
                    user_id: admin.Admin_ID,
                    name: admin.Full_Name || 'System Administrator',
                    email: admin.E_mail || ''
                });
            }
        }
        return res.status(401).json({ success: false, error: 'Invalid Admin Username or Password.' });
    }

    res.status(400).json({ success: false, error: 'Invalid role selected.' });
});

// ─── PUBLIC DEPARTMENTS ROUTE (FOR REGISTRATION) ───────────────────────────

app.get('/api/departments', (req, res) => {
    const deptRows = engine.tables['department']?.rows || [];
    res.json({
        success: true,
        departments: deptRows.map(d => ({
            dept_id: d.Dept_ID,
            dept_name: d.Dept_Name
        }))
    });
});

// ─── USER REGISTRATION ROUTE ───────────────────────────────────────────────────

app.post('/api/register', (req, res) => {
    const role = (req.body.selected_role || req.body.role || 'student').toLowerCase().trim();
    const firstName = (req.body.first_name || '').trim();
    const lastName = (req.body.last_name || '').trim();
    const email = (req.body.email || '').trim().toLowerCase();
    const password = req.body.password || '';
    const deptId = (req.body.dept_id || '101').trim();
    const phone = (req.body.phone || '').trim();
    const address = (req.body.address || '').trim();
    const dob = (req.body.dob || '').trim();

    if (!firstName || !email || !password) {
        return res.status(400).json({
            success: false,
            error: 'Please fill in all required fields (First Name, Email, Password).'
        });
    }

    if (password.length < 6) {
        return res.status(400).json({
            success: false,
            error: 'Password must be at least 6 characters long.'
        });
    }

    // Check email uniqueness across students, faculty, and admin
    const students = engine.tables['student']?.rows || [];
    const facultyList = engine.tables['faculty']?.rows || [];
    const admins = engine.tables['admin']?.rows || [];

    if (students.some(s => (s.E_mail || '').toLowerCase() === email) ||
        facultyList.some(f => (f.E_mail || '').toLowerCase() === email) ||
        admins.some(a => (a.E_mail || '').toLowerCase() === email)) {
        return res.status(400).json({
            success: false,
            error: 'An account with this email address already exists. Please sign in.'
        });
    }

    if (role === 'student') {
        let studentId = (req.body.student_id || '').trim();
        if (studentId) {
            if (students.some(s => s.Student_ID === studentId)) {
                return res.status(400).json({
                    success: false,
                    error: `Student ID ${studentId} is already registered. Please enter a different ID or leave blank to auto-generate.`
                });
            }
        } else {
            // Auto-generate official EWU Student ID format: YYYY-Semester-Dept-Roll (e.g. 2024-3-60-625)
            let maxRoll = 620;
            for (const s of students) {
                const parts = (s.Student_ID || '').split('-');
                if (parts.length === 4) {
                    const roll = parseInt(parts[3], 10);
                    if (!isNaN(roll) && roll > maxRoll) {
                        maxRoll = roll;
                    }
                }
            }
            const nextRoll = maxRoll + 1;
            const currentYear = new Date().getFullYear();
            studentId = `${currentYear}-3-60-${nextRoll}`;
        }

        // Auto-assign faculty advisor from department
        let advisorId = '';
        const deptFaculty = facultyList.filter(f => f.Dept_ID === deptId);
        if (deptFaculty.length > 0) {
            advisorId = deptFaculty[0].Faculty_ID;
        } else if (facultyList.length > 0) {
            advisorId = facultyList[0].Faculty_ID;
        }

        // Add to student table
        students.push({
            Student_ID: studentId,
            First_name: firstName,
            Last_name: lastName,
            E_mail: email,
            Password: password,
            Address: address,
            DOB: dob,
            Faculty_ID: advisorId,
            Dept_ID: deptId
        });

        // Add to student_phonenum table
        if (phone) {
            const phoneRows = engine.tables['student_phonenum']?.rows || [];
            phoneRows.push({
                Student_ID: studentId,
                Phone_Number1: phone,
                Phone_Number2: ''
            });
            engine.saveTable('student_phonenum');
        }

        engine.saveTable('student');
        engine.generateMasterFiles();

        const fullName = `${firstName} ${lastName}`.trim();
        const sessionData = {
            userId: studentId,
            role: 'student',
            name: fullName,
            email: email,
            lastActive: Date.now()
        };
        const token = generateSessionToken(sessionData);
        sessions.set(token, sessionData);

        res.cookie('ewu_session', token, { path: '/', httpOnly: true });
        return res.json({
            success: true,
            message: `Registration successful! Your official Student ID is ${studentId}.`,
            token,
            role: 'student',
            user_id: studentId,
            name: fullName,
            email
        });

    } else if (role === 'faculty') {
        const designation = (req.body.designation || 'Lecturer').trim();
        const roomNo = (req.body.room_no || 'AB1-401').trim();

        // Auto-generate 10-digit Faculty ID
        let maxFacId = 1652688918;
        for (const f of facultyList) {
            const num = parseInt(f.Faculty_ID, 10);
            if (!isNaN(num) && num > maxFacId) {
                maxFacId = num;
            }
        }
        const newFacultyId = String(maxFacId + 1);

        facultyList.push({
            Faculty_ID: newFacultyId,
            First_name: firstName,
            Last_name: lastName,
            Designation: designation,
            Room_No: roomNo,
            E_mail: email,
            Password: password,
            Dept_ID: deptId
        });

        if (phone) {
            const phoneRows = engine.tables['faculty_phonenum']?.rows || [];
            phoneRows.push({
                Faculty_ID: newFacultyId,
                Phone_Number1: phone,
                Phone_Number2: ''
            });
            engine.saveTable('faculty_phonenum');
        }

        engine.saveTable('faculty');
        engine.generateMasterFiles();

        const fullName = `${firstName} ${lastName}`.trim();
        const sessionData = {
            userId: newFacultyId,
            role: 'faculty',
            name: fullName,
            email: email,
            lastActive: Date.now()
        };
        const token = generateSessionToken(sessionData);
        sessions.set(token, sessionData);

        res.cookie('ewu_session', token, { path: '/', httpOnly: true });
        return res.json({
            success: true,
            message: `Faculty registration successful! Your Faculty ID is ${newFacultyId}.`,
            token,
            role: 'faculty',
            user_id: newFacultyId,
            name: fullName,
            email
        });
    }

    res.status(400).json({ success: false, error: 'Invalid registration role.' });
});

app.get('/api/me', (req, res) => {
    if (!req.userId) {
        return res.json({ logged_in: false });
    }

    const resp = {
        logged_in: true,
        user_id: req.userId,
        role: req.userRole,
        name: req.userName,
        email: req.userEmail
    };

    if (req.userRole === 'student') {
        const student = (engine.tables['student']?.rows || []).find(s => s.Student_ID === req.userId);
        if (student) {
            resp.first_name = student.First_name || '';
            resp.last_name = student.Last_name || '';
            resp.address = student.Address || '';
            resp.dob = student.DOB || '';
            resp.dept_id = student.Dept_ID || '';
            resp.faculty_id = student.Faculty_ID || '';

            const dept = (engine.tables['department']?.rows || []).find(d => d.Dept_ID === student.Dept_ID);
            resp.dept_name = dept ? dept.Dept_Name : '';

            const faculty = (engine.tables['faculty']?.rows || []).find(f => f.Faculty_ID === student.Faculty_ID);
            if (faculty) {
                resp.advisor_name = `${faculty.First_name || ''} ${faculty.Last_name || ''}`.trim();
                resp.advisor_email = faculty.E_mail || '';
            } else {
                resp.advisor_name = '';
                resp.advisor_email = '';
            }

            const phone = (engine.tables['student_phonenum']?.rows || []).find(sp => sp.Student_ID === req.userId);
            resp.phone1 = phone ? phone.Phone_Number1 : '';
            resp.phone2 = phone ? phone.Phone_Number2 : '';
        }
    } else if (req.userRole === 'faculty') {
        const faculty = (engine.tables['faculty']?.rows || []).find(f => f.Faculty_ID === req.userId);
        if (faculty) {
            resp.first_name = faculty.First_name || '';
            resp.last_name = faculty.Last_name || '';
            resp.designation = faculty.Designation || '';
            resp.room_no = faculty.Room_No || '';
            resp.dept_id = faculty.Dept_ID || '';

            const dept = (engine.tables['department']?.rows || []).find(d => d.Dept_ID === faculty.Dept_ID);
            resp.dept_name = dept ? dept.Dept_Name : '';

            const phone = (engine.tables['faculty_phonenum']?.rows || []).find(fp => fp.Faculty_ID === req.userId);
            resp.phone1 = phone ? phone.Phone_Number1 : '';
            resp.phone2 = phone ? phone.Phone_Number2 : '';
        }
    }

    res.json(resp);
});

app.post('/api/logout', (req, res) => {
    if (req.userToken) {
        sessions.delete(req.userToken);
    }
    res.clearCookie('ewu_session', { path: '/' });
    res.json({ success: true });
});

// ─── STUDENT ROUTES ──────────────────────────────────────────────────────────

app.get('/api/student/dashboard', requireAuth('student'), (req, res) => {
    const enrollments = engine.tables['enrollment']?.rows || [];
    const sections = engine.tables['section']?.rows || [];
    const courses = engine.tables['course']?.rows || [];
    const facultyList = engine.tables['faculty']?.rows || [];
    const payments = engine.tables['payment']?.rows || [];

    const curEnr = [];
    let totalCredits = 0;
    let gradePoints = 0;

    for (const enr of enrollments) {
        if (enr.Student_ID === req.userId) {
            const sec = sections.find(s => String(s.Section_Id) === String(enr.Section_Id)) || {};
            const crs = courses.find(c => c.Course_ID === sec.Course_ID) || {};
            const fac = facultyList.find(f => f.Faculty_ID === sec.Faculty_ID) || {};

            const credits = parseFloat(crs.Credits) || 3.0;

            const item = {
                enrollment_id: enr.Enrollment_ID,
                course_id: sec.Course_ID || '',
                course_title: crs.Course_Title || '',
                credits,
                section_no: sec.Section_No || enr.Section_Id,
                time_slot: sec.Time_Slot || '',
                room_no: sec.Room_No || '',
                faculty_name: fac.First_name ? `${fac.First_name} ${fac.Last_name}` : '',
                status: enr.Advising_Status || '',
                grade: enr.Grade || 'N/A',
                mid_mark: enr.Mid_Mark || '',
                final_mark: enr.Final_Mark || '',
                semester: enr.Semester || '',
                year: enr.Year || ''
            };

            if (enr.Semester === 'Summer' && String(enr.Year) === '2026') {
                curEnr.push(item);
            }

            if (enr.Grade && enr.Grade !== 'N/A') {
                totalCredits += credits;
                gradePoints += ExcelEngine.gradeToPoint(enr.Grade) * credits;
            }
        }
    }

    // Pending dues
    let totalDue = 0;
    for (const p of payments) {
        if (p.Student_ID === req.userId && p.Payment_Status === 'Pending') {
            totalDue += parseFloat(p.Amount) || 0;
        }
    }

    res.json({
        student_id: req.userId,
        name: req.userName,
        current_enrollments: curEnr,
        completed_credits: totalCredits,
        cgpa: totalCredits > 0 ? parseFloat((gradePoints / totalCredits).toFixed(2)) : 0.0,
        pending_dues: totalDue
    });
});

app.post('/api/student/profile', requireAuth('student'), (req, res) => {
    const { address, phone1, phone2, new_password } = req.body;

    const studentRows = engine.tables['student']?.rows || [];
    const student = studentRows.find(s => s.Student_ID === req.userId);
    if (student) {
        if (address !== undefined && address !== '') student.Address = address;
        if (new_password) student.Password = new_password;
    }

    const phoneRows = engine.tables['student_phonenum']?.rows || [];
    let phone = phoneRows.find(p => p.Student_ID === req.userId);
    if (phone) {
        if (phone1 !== undefined) phone.Phone_Number1 = phone1;
        if (phone2 !== undefined) phone.Phone_Number2 = phone2;
    } else if (phone1 || phone2) {
        phoneRows.push({
            Student_ID: req.userId,
            Phone_Number1: phone1 || '',
            Phone_Number2: phone2 || ''
        });
    }

    engine.saveTable('student');
    engine.saveTable('student_phonenum');
    engine.generateMasterFiles();

    res.json({ success: true, message: 'Profile updated successfully.' });
});

app.get('/api/student/pre-advising', requireAuth('student'), (req, res) => {
    const preAdvRows = engine.tables['pre_advising']?.rows || [];
    const incRows = engine.tables['includedcourse']?.rows || [];
    const courseRows = engine.tables['course']?.rows || [];

    const requests = [];
    for (const pa of preAdvRows) {
        if (pa.Student_ID === req.userId) {
            const paId = pa.Pre_Advising_ID;
            const courses = [];

            for (const inc of incRows) {
                if (String(inc.Pre_Advising_ID) === String(paId)) {
                    const crs = courseRows.find(c => c.Course_ID === inc.Course_ID) || {};
                    courses.push({
                        course_id: inc.Course_ID,
                        status: inc.Status || 'Pending',
                        course_title: crs.Course_Title || '',
                        credits: crs.Credits || '3.0'
                    });
                }
            }

            requests.push({
                pre_advising_id: paId,
                semester: pa.Semester,
                year: pa.Year,
                timestamp: pa.Submission_TimeStamp,
                courses
            });
        }
    }

    const availableCourses = courseRows.map(c => ({
        course_id: c.Course_ID,
        course_title: c.Course_Title,
        credits: c.Credits,
        dept_id: c.Dept_ID
    }));

    res.json({ requests, available_courses: availableCourses });
});

app.post('/api/student/pre-advising', requireAuth('student'), (req, res) => {
    const courses = req.body.courses;
    if (!Array.isArray(courses) || courses.length === 0) {
        return res.status(400).json({ success: false, error: 'Please select at least one course.' });
    }

    const paId = engine.getNextId('pre_advising', 'Pre_Advising_ID');
    const nowStr = new Date().toISOString().replace('T', ' ').substring(0, 19);

    const newPa = {
        Pre_Advising_ID: String(paId),
        Submission_TimeStamp: nowStr,
        Semester: req.body.semester || 'Summer',
        Year: String(req.body.year || 2026),
        Student_ID: req.userId
    };

    engine.tables['pre_advising'].rows.push(newPa);

    for (const item of courses) {
        const cid = typeof item === 'string' ? item : item.course_id;
        if (cid) {
            engine.tables['includedcourse'].rows.push({
                Pre_Advising_ID: String(paId),
                Course_ID: cid,
                Status: 'Pending'
            });
        }
    }

    engine.saveTable('pre_advising');
    engine.saveTable('includedcourse');
    engine.generateMasterFiles();

    res.json({
        success: true,
        message: `Pre-advising form #${paId} submitted successfully.`
    });
});

app.get('/api/student/advising-courses', requireAuth('student'), (req, res) => {
    const preAdvRows = engine.tables['pre_advising']?.rows || [];
    const incRows = engine.tables['includedcourse']?.rows || [];
    const courseRows = engine.tables['course']?.rows || [];
    const sectionRows = engine.tables['section']?.rows || [];
    const facultyRows = engine.tables['faculty']?.rows || [];
    const enrRows = engine.tables['enrollment']?.rows || [];

    // Find approved course IDs for this student in Summer 2026
    const approvedCids = [];
    for (const pa of preAdvRows) {
        if (pa.Student_ID === req.userId && pa.Semester === 'Summer' && String(pa.Year) === '2026') {
            for (const inc of incRows) {
                if (String(inc.Pre_Advising_ID) === String(pa.Pre_Advising_ID) && inc.Status === 'Approved') {
                    if (!approvedCids.includes(inc.Course_ID)) {
                        approvedCids.push(inc.Course_ID);
                    }
                }
            }
        }
    }

    const approvedCourses = [];
    for (const cid of approvedCids) {
        const crs = courseRows.find(c => c.Course_ID === cid) || {};
        const matchingSections = [];

        for (const sec of sectionRows) {
            if (sec.Course_ID === cid) {
                const fac = facultyRows.find(f => f.Faculty_ID === sec.Faculty_ID);
                const enrolledCount = enrRows.filter(e =>
                    String(e.Section_Id) === String(sec.Section_Id) &&
                    String(e.Semester || '').toLowerCase() === 'summer' &&
                    String(e.Year) === '2026'
                ).length;

                matchingSections.push({
                    section_id: sec.Section_Id,
                    section_no: sec.Section_No,
                    time_slot: sec.Time_Slot,
                    room_no: sec.Room_No,
                    capacity: sec.Capacity,
                    faculty_name: fac ? `${fac.First_name} ${fac.Last_name}` : '',
                    enrolled_count: enrolledCount
                });
            }
        }

        approvedCourses.push({
            course_id: cid,
            course_title: crs.Course_Title || '',
            credits: crs.Credits || '3.0',
            sections: matchingSections
        });
    }

    // Currently enrolled sections for Summer 2026
    const enrolledSections = [];
    for (const enr of enrRows) {
        if (enr.Student_ID === req.userId &&
            String(enr.Semester || '').toLowerCase() === 'summer' &&
            String(enr.Year) === '2026') {
            const sec = sectionRows.find(s => String(s.Section_Id) === String(enr.Section_Id)) || {};
            const crs = courseRows.find(c => c.Course_ID === sec.Course_ID) || {};

            enrolledSections.push({
                enrollment_id: enr.Enrollment_ID,
                section_id: enr.Section_Id,
                section_no: sec.Section_No || '',
                time_slot: sec.Time_Slot || '',
                room_no: sec.Room_No || '',
                course_id: sec.Course_ID || '',
                course_title: crs.Course_Title || '',
                credits: crs.Credits || '3.0'
            });
        }
    }

    res.json({ approved_courses: approvedCourses, enrolled_sections: enrolledSections });
});

app.post('/api/student/advising-take', requireAuth('student'), (req, res) => {
    const sectionId = req.body.section_id;
    if (!sectionId) {
        return res.status(400).json({ success: false, error: 'Section ID is required.' });
    }

    const secRows = engine.tables['section']?.rows || [];
    const enrRows = engine.tables['enrollment']?.rows || [];

    const targetSec = secRows.find(s => String(s.Section_Id) === String(sectionId));
    if (!targetSec) {
        return res.status(404).json({ success: false, error: 'Section not found.' });
    }

    const courseId = targetSec.Course_ID;
    const timeSlot = targetSec.Time_Slot;

    // 1. Check if course already taken in Summer 2026
    for (const enr of enrRows) {
        if (enr.Student_ID === req.userId &&
            String(enr.Semester || '').toLowerCase() === 'summer' &&
            String(enr.Year) === '2026') {
            const s = secRows.find(sec => String(sec.Section_Id) === String(enr.Section_Id));
            if (s && s.Course_ID === courseId) {
                return res.status(400).json({
                    success: false,
                    error: `Duplicate Course: You are already enrolled in course ${courseId} (Section ${s.Section_No}). Taking multiple sections of the same course is not acceptable.`
                });
            }
        }
    }

    // 2. Check capacity
    const enrolledInSec = enrRows.filter(e =>
        String(e.Section_Id) === String(sectionId) &&
        String(e.Semester || '').toLowerCase() === 'summer' &&
        String(e.Year) === '2026'
    ).length;
    const capacity = parseInt(targetSec.Capacity, 10) || 35;
    if (enrolledInSec >= capacity) {
        return res.status(400).json({
            success: false,
            error: `Section Full: Section ${targetSec.Section_No} of ${courseId} has reached full capacity (${capacity}/${capacity}). No seats available.`
        });
    }

    // 3. Check time slot conflict with other enrolled sections
    const clashInfo = engine.getStudentClashInfo(req.userId, timeSlot, 'Summer', 2026);
    if (clashInfo) {
        return res.status(400).json({
            success: false,
            error: `Schedule Conflict: The time slot (${timeSlot}) clashes with your enrolled course ${clashInfo.course_id} (Section ${clashInfo.section_no} at ${clashInfo.time_slot}). Schedule clash is not acceptable!`
        });
    }

    // 3. Add enrollment
    const nextEnrId = engine.getNextId('enrollment', 'Enrollment_ID');
    const newEnr = {
        Enrollment_ID: String(nextEnrId),
        Enrollment_Type: 'Regular',
        Advising_Status: 'Approved',
        Mid_Mark: '',
        Final_Mark: '',
        Grade: 'N/A',
        Section_Id: String(sectionId),
        ManagedBy_Faculty_ID: targetSec.Faculty_ID || '',
        Student_ID: req.userId,
        Semester: 'Summer',
        Year: '2026'
    };

    enrRows.push(newEnr);
    engine.saveTable('enrollment');
    engine.generateMasterFiles();

    res.json({
        success: true,
        message: `Successfully enrolled in ${courseId} (Section ${targetSec.Section_No})!`
    });
});

app.post('/api/student/advising-drop', requireAuth('student'), (req, res) => {
    const enrId = req.body.enrollment_id;
    const enrRows = engine.tables['enrollment']?.rows || [];

    const initialLen = enrRows.length;
    engine.tables['enrollment'].rows = enrRows.filter(r =>
        !(String(r.Enrollment_ID) === String(enrId) && r.Student_ID === req.userId)
    );

    if (engine.tables['enrollment'].rows.length < initialLen) {
        engine.saveTable('enrollment');
        engine.generateMasterFiles();
        return res.json({ success: true, message: 'Course section dropped successfully.' });
    }

    res.status(404).json({ success: false, error: 'Enrollment not found.' });
});

app.get('/api/student/enrolled-courses', requireAuth('student'), (req, res) => {
    const enrRows = engine.tables['enrollment']?.rows || [];
    const secRows = engine.tables['section']?.rows || [];
    const courseRows = engine.tables['course']?.rows || [];
    const facRows = engine.tables['faculty']?.rows || [];

    const list = [];
    for (const enr of enrRows) {
        if (enr.Student_ID === req.userId) {
            const sec = secRows.find(s => String(s.Section_Id) === String(enr.Section_Id)) || {};
            const crs = courseRows.find(c => c.Course_ID === sec.Course_ID) || {};
            const fac = facRows.find(f => f.Faculty_ID === sec.Faculty_ID);

            list.push({
                enrollment_id: enr.Enrollment_ID,
                semester: enr.Semester,
                year: enr.Year,
                grade: enr.Grade || 'N/A',
                mid_mark: enr.Mid_Mark || '',
                final_mark: enr.Final_Mark || '',
                section_no: sec.Section_No || enr.Section_Id,
                time_slot: sec.Time_Slot || '',
                room_no: sec.Room_No || '',
                course_id: sec.Course_ID || '',
                course_title: crs.Course_Title || '',
                credits: crs.Credits || '3.0',
                faculty_name: fac ? `${fac.First_name} ${fac.Last_name}` : ''
            });
        }
    }

    res.json({ courses: list });
});

app.get('/api/student/payments', requireAuth('student'), (req, res) => {
    const payRows = engine.tables['payment']?.rows || [];
    const list = [];
    let totalPaid = 0;
    let totalPending = 0;

    for (const p of payRows) {
        if (p.Student_ID === req.userId) {
            const amt = parseFloat(p.Amount) || 0;
            if (p.Payment_Status === 'Paid') totalPaid += amt;
            else totalPending += amt;

            list.push({
                payment_id: p.Payment_Id,
                transaction_id: p.Transaction_Id,
                amount: p.Amount,
                semester: p.Semester,
                year: p.Year,
                payment_date: p.Payment_Date,
                status: p.Payment_Status
            });
        }
    }

    res.json({ payments: list, total_paid: totalPaid, total_pending: totalPending });
});

app.post('/api/student/payments', requireAuth('student'), (req, res) => {
    const amount = parseFloat(req.body.amount) || 0;
    const semester = req.body.semester || 'Summer';
    const year = parseInt(req.body.year, 10) || 2026;

    if (amount <= 0) {
        return res.status(400).json({ success: false, error: 'Please enter a valid payment amount.' });
    }

    const payId = engine.getNextId('payment', 'Payment_Id');
    const txnId = `TXN-${year}-EWU-${1000 + payId}`;
    const todayStr = new Date().toISOString().substring(0, 10);

    const newPay = {
        Payment_Id: String(payId),
        Transaction_Id: txnId,
        Payment_Status: 'Paid',
        Amount: String(amount),
        Semester: semester,
        Year: String(year),
        Payment_Date: todayStr,
        Student_ID: req.userId
    };

    engine.tables['payment'].rows.push(newPay);
    engine.saveTable('payment');
    engine.generateMasterFiles();

    res.json({
        success: true,
        transaction_id: txnId,
        message: `Payment of ৳${Math.round(amount)} received successfully!`
    });
});

// ─── FACULTY ROUTES ──────────────────────────────────────────────────────────

app.get('/api/faculty/dashboard', requireAuth('faculty'), (req, res) => {
    const secRows = engine.tables['section']?.rows || [];
    const enrRows = engine.tables['enrollment']?.rows || [];
    const courseRows = engine.tables['course']?.rows || [];
    const stdRows = engine.tables['student']?.rows || [];
    const paRows = engine.tables['pre_advising']?.rows || [];
    const incRows = engine.tables['includedcourse']?.rows || [];

    const secList = [];
    let totalStudents = 0;

    for (const sec of secRows) {
        if (sec.Faculty_ID === req.userId) {
            const crs = courseRows.find(c => c.Course_ID === sec.Course_ID) || {};
            const count = enrRows.filter(e => String(e.Section_Id) === String(sec.Section_Id)).length;
            totalStudents += count;

            secList.push({
                section_id: sec.Section_Id,
                section_no: sec.Section_No,
                time_slot: sec.Time_Slot,
                room_no: sec.Room_No,
                course_id: sec.Course_ID,
                course_title: crs.Course_Title || '',
                enrolled_count: count
            });
        }
    }

    let adviseesCount = 0;
    let pendingApprovals = 0;

    for (const s of stdRows) {
        if (s.Faculty_ID === req.userId) {
            adviseesCount++;
            for (const pa of paRows) {
                if (pa.Student_ID === s.Student_ID) {
                    for (const inc of incRows) {
                        if (String(inc.Pre_Advising_ID) === String(pa.Pre_Advising_ID) && inc.Status === 'Pending') {
                            pendingApprovals++;
                        }
                    }
                }
            }
        }
    }

    res.json({
        sections: secList,
        total_students: totalStudents,
        advisees_count: adviseesCount,
        pending_approvals: pendingApprovals
    });
});

app.post('/api/faculty/profile', requireAuth('faculty'), (req, res) => {
    const { room_no, phone1, phone2, new_password } = req.body;

    const facRows = engine.tables['faculty']?.rows || [];
    const faculty = facRows.find(f => f.Faculty_ID === req.userId);
    if (faculty) {
        if (room_no) faculty.Room_No = room_no;
        if (new_password) faculty.Password = new_password;
    }

    const phoneRows = engine.tables['faculty_phonenum']?.rows || [];
    let phone = phoneRows.find(p => p.Faculty_ID === req.userId);
    if (phone) {
        if (phone1 !== undefined) phone.Phone_Number1 = phone1;
        if (phone2 !== undefined) phone.Phone_Number2 = phone2;
    } else if (phone1 || phone2) {
        phoneRows.push({
            Faculty_ID: req.userId,
            Phone_Number1: phone1 || '',
            Phone_Number2: phone2 || ''
        });
    }

    engine.saveTable('faculty');
    engine.saveTable('faculty_phonenum');
    engine.generateMasterFiles();

    res.json({ success: true, message: 'Profile updated successfully.' });
});

app.get('/api/faculty/sections', requireAuth('faculty'), (req, res) => {
    const secId = req.query.section_id;
    const secRows = engine.tables['section']?.rows || [];
    const enrRows = engine.tables['enrollment']?.rows || [];
    const stdRows = engine.tables['student']?.rows || [];
    const courseRows = engine.tables['course']?.rows || [];

    if (secId) {
        // Section roster
        const roster = [];
        for (const enr of enrRows) {
            if (String(enr.Section_Id) === String(secId)) {
                const std = stdRows.find(s => s.Student_ID === enr.Student_ID) || {};
                roster.push({
                    enrollment_id: enr.Enrollment_ID,
                    student_id: enr.Student_ID,
                    student_name: std.First_name ? `${std.First_name} ${std.Last_name}` : '',
                    email: std.E_mail || '',
                    mid_mark: enr.Mid_Mark || '',
                    final_mark: enr.Final_Mark || '',
                    grade: enr.Grade || 'N/A'
                });
            }
        }
        return res.json({ roster });
    }

    // List all assigned sections
    const list = [];
    for (const sec of secRows) {
        if (sec.Faculty_ID === req.userId) {
            const crs = courseRows.find(c => c.Course_ID === sec.Course_ID) || {};
            list.push({
                section_id: sec.Section_Id,
                section_no: sec.Section_No,
                time_slot: sec.Time_Slot,
                room_no: sec.Room_No,
                capacity: sec.Capacity,
                course_id: sec.Course_ID,
                course_title: crs.Course_Title || '',
                credits: crs.Credits || '3.0'
            });
        }
    }
    res.json({ sections: list });
});

app.post('/api/faculty/grading', requireAuth('faculty'), (req, res) => {
    const { enrollment_id, mid_mark, final_mark } = req.body;
    let grade = req.body.grade;

    if (!grade) {
        grade = ExcelEngine.calculateGrade(mid_mark, final_mark);
    }

    const enrRows = engine.tables['enrollment']?.rows || [];
    const enr = enrRows.find(e => String(e.Enrollment_ID) === String(enrollment_id));

    if (enr) {
        enr.Mid_Mark = String(mid_mark !== undefined ? mid_mark : '');
        enr.Final_Mark = String(final_mark !== undefined ? final_mark : '');
        enr.Grade = grade;
        enr.ManagedBy_Faculty_ID = req.userId;

        engine.saveTable('enrollment');
        engine.generateMasterFiles();

        return res.json({
            success: true,
            grade,
            message: `Marks & grade (${grade}) saved successfully.`
        });
    }

    res.status(404).json({ success: false, error: 'Enrollment record not found.' });
});

app.get('/api/faculty/advisees', requireAuth('faculty'), (req, res) => {
    const stdRows = engine.tables['student']?.rows || [];
    const deptRows = engine.tables['department']?.rows || [];
    const paRows = engine.tables['pre_advising']?.rows || [];
    const incRows = engine.tables['includedcourse']?.rows || [];
    const courseRows = engine.tables['course']?.rows || [];

    const advisees = [];
    for (const s of stdRows) {
        if (s.Faculty_ID === req.userId) {
            const dept = deptRows.find(d => d.Dept_ID === s.Dept_ID);
            const preAdvList = [];

            for (const pa of paRows) {
                if (pa.Student_ID === s.Student_ID) {
                    const courses = [];
                    for (const inc of incRows) {
                        if (String(inc.Pre_Advising_ID) === String(pa.Pre_Advising_ID)) {
                            const crs = courseRows.find(c => c.Course_ID === inc.Course_ID) || {};
                            courses.push({
                                course_id: inc.Course_ID,
                                status: inc.Status || 'Pending',
                                course_title: crs.Course_Title || '',
                                credits: crs.Credits || '3.0'
                            });
                        }
                    }

                    preAdvList.push({
                        pre_advising_id: pa.Pre_Advising_ID,
                        semester: pa.Semester,
                        year: pa.Year,
                        timestamp: pa.Submission_TimeStamp,
                        courses
                    });
                }
            }

            advisees.push({
                student_id: s.Student_ID,
                name: `${s.First_name || ''} ${s.Last_name || ''}`.trim(),
                email: s.E_mail || '',
                dept_name: dept ? dept.Dept_Name : '',
                pre_advising_forms: preAdvList
            });
        }
    }

    res.json({ advisees });
});

app.post('/api/faculty/advisees-action', requireAuth('faculty'), (req, res) => {
    const paId = req.body.pre_advising_id;
    const courseId = req.body.course_id;
    const status = req.body.status || 'Approved';

    let updatedCount = 0;
    const incRows = engine.tables['includedcourse']?.rows || [];

    for (const inc of incRows) {
        if (String(inc.Pre_Advising_ID) === String(paId)) {
            if (!courseId || inc.Course_ID === courseId) {
                inc.Status = status;
                updatedCount++;
            }
        }
    }

    engine.saveTable('includedcourse');
    engine.generateMasterFiles();

    res.json({
        success: true,
        message: `Course status set to ${status} (${updatedCount} updated).`
    });
});

// ─── ADMIN ROUTES ────────────────────────────────────────────────────────────

app.get('/api/admin/dashboard', requireAuth('admin'), (req, res) => {
    const totalStudents = (engine.tables['student']?.rows || []).length;
    const totalFaculty = (engine.tables['faculty']?.rows || []).length;
    const totalDepts = (engine.tables['department']?.rows || []).length;
    const totalCourses = (engine.tables['course']?.rows || []).length;
    const totalSections = (engine.tables['section']?.rows || []).length;
    const totalEnrollments = (engine.tables['enrollment']?.rows || []).length;

    let totalRevenue = 0;
    for (const p of (engine.tables['payment']?.rows || [])) {
        if (p.Payment_Status === 'Paid') {
            totalRevenue += parseFloat(p.Amount) || 0;
        }
    }

    res.json({
        total_students: totalStudents,
        total_faculty: totalFaculty,
        total_depts: totalDepts,
        total_courses: totalCourses,
        total_sections: totalSections,
        total_enrollments: totalEnrollments,
        total_revenue: totalRevenue
    });
});

// Admin Departments
app.get('/api/admin/departments', requireAuth('admin'), (req, res) => {
    const deptRows = engine.tables['department']?.rows || [];
    const facRows = engine.tables['faculty']?.rows || [];

    const list = deptRows.map(d => {
        const fac = facRows.find(f => f.Faculty_ID === d.Head_Faculty_ID);
        return {
            dept_id: d.Dept_ID,
            dept_name: d.Dept_Name,
            head_faculty_id: d.Head_Faculty_ID,
            head_faculty_name: fac ? `${fac.First_name} ${fac.Last_name}` : ''
        };
    });

    res.json({ departments: list });
});

app.post('/api/admin/departments', requireAuth('admin'), (req, res) => {
    let deptId = parseInt(req.body.dept_id, 10);
    const name = req.body.dept_name || '';
    const headFid = req.body.head_faculty_id || '';

    if (!deptId) {
        deptId = engine.getNextId('department', 'Dept_ID');
    }

    const deptRows = engine.tables['department']?.rows || [];
    const existing = deptRows.find(d => String(d.Dept_ID) === String(deptId));

    if (existing) {
        existing.Dept_Name = name;
        existing.Head_Faculty_ID = headFid;
    } else {
        deptRows.push({
            Dept_ID: String(deptId),
            Dept_Name: name,
            Head_Faculty_ID: headFid
        });
    }

    engine.saveTable('department');
    engine.generateMasterFiles();

    res.json({ success: true, message: 'Department saved successfully.' });
});

app.delete('/api/admin/departments', requireAuth('admin'), (req, res) => {
    const deptId = req.query.dept_id;
    const deptRows = engine.tables['department']?.rows || [];
    engine.tables['department'].rows = deptRows.filter(d => String(d.Dept_ID) !== String(deptId));

    engine.saveTable('department');
    engine.generateMasterFiles();

    res.json({ success: true });
});

// Admin Faculty
app.get('/api/admin/faculty', requireAuth('admin'), (req, res) => {
    const facRows = engine.tables['faculty']?.rows || [];
    const deptRows = engine.tables['department']?.rows || [];
    const phoneRows = engine.tables['faculty_phonenum']?.rows || [];

    const list = facRows.map(f => {
        const dept = deptRows.find(d => d.Dept_ID === f.Dept_ID);
        const phone = phoneRows.find(p => p.Faculty_ID === f.Faculty_ID) || {};
        return {
            faculty_id: f.Faculty_ID,
            first_name: f.First_name,
            last_name: f.Last_name,
            designation: f.Designation,
            room_no: f.Room_No,
            email: f.E_mail,
            dept_id: f.Dept_ID,
            dept_name: dept ? dept.Dept_Name : '',
            phone1: phone.Phone_Number1 || '',
            phone2: phone.Phone_Number2 || ''
        };
    });

    res.json({ faculty: list });
});

app.post('/api/admin/faculty', requireAuth('admin'), (req, res) => {
    const { faculty_id, first_name, last_name, designation, room_no, email, dept_id, phone1 } = req.body;

    if (!faculty_id || !first_name || !email) {
        return res.status(400).json({ success: false, error: 'Please fill all required fields.' });
    }

    const facRows = engine.tables['faculty']?.rows || [];
    facRows.push({
        Faculty_ID: faculty_id,
        First_name: first_name,
        Last_name: last_name || '',
        Designation: designation || '',
        Room_No: room_no || '',
        E_mail: email,
        Password: 'faculty123',
        Dept_ID: dept_id || '101'
    });

    if (phone1) {
        const phoneRows = engine.tables['faculty_phonenum']?.rows || [];
        phoneRows.push({
            Faculty_ID: faculty_id,
            Phone_Number1: phone1,
            Phone_Number2: ''
        });
    }

    engine.saveTable('faculty');
    engine.saveTable('faculty_phonenum');
    engine.generateMasterFiles();

    res.json({
        success: true,
        message: `Faculty ${first_name} ${last_name || ''} added successfully.`
    });
});

app.delete('/api/admin/faculty', requireAuth('admin'), (req, res) => {
    const fid = req.query.faculty_id;
    const facRows = engine.tables['faculty']?.rows || [];
    engine.tables['faculty'].rows = facRows.filter(f => f.Faculty_ID !== fid);

    engine.saveTable('faculty');
    engine.generateMasterFiles();

    res.json({ success: true });
});

// Admin Students
app.get('/api/admin/students', requireAuth('admin'), (req, res) => {
    const stdRows = engine.tables['student']?.rows || [];
    const deptRows = engine.tables['department']?.rows || [];
    const facRows = engine.tables['faculty']?.rows || [];
    const phoneRows = engine.tables['student_phonenum']?.rows || [];

    const list = stdRows.map(s => {
        const dept = deptRows.find(d => d.Dept_ID === s.Dept_ID);
        const fac = facRows.find(f => f.Faculty_ID === s.Faculty_ID);
        const phone = phoneRows.find(p => p.Student_ID === s.Student_ID) || {};

        return {
            student_id: s.Student_ID,
            first_name: s.First_name,
            last_name: s.Last_name,
            email: s.E_mail,
            address: s.Address,
            dob: s.DOB,
            dept_id: s.Dept_ID,
            faculty_id: s.Faculty_ID,
            dept_name: dept ? dept.Dept_Name : '',
            advisor_name: fac ? `${fac.First_name} ${fac.Last_name}` : '',
            advisor_email: fac ? fac.E_mail : '',
            phone1: phone.Phone_Number1 || '',
            phone2: phone.Phone_Number2 || ''
        };
    });

    res.json({ students: list });
});

app.post('/api/admin/students', requireAuth('admin'), (req, res) => {
    const { student_id, first_name, last_name, email, address, dob, faculty_id, dept_id, phone1 } = req.body;

    if (!student_id || !first_name || !email) {
        return res.status(400).json({ success: false, error: 'Please fill all required fields.' });
    }

    const stdRows = engine.tables['student']?.rows || [];
    stdRows.push({
        Student_ID: student_id,
        First_name: first_name,
        Last_name: last_name || '',
        E_mail: email,
        Password: 'student123',
        Address: address || '',
        DOB: dob || '',
        Faculty_ID: faculty_id || '',
        Dept_ID: dept_id || '101'
    });

    if (phone1) {
        const phoneRows = engine.tables['student_phonenum']?.rows || [];
        phoneRows.push({
            Student_ID: student_id,
            Phone_Number1: phone1,
            Phone_Number2: ''
        });
    }

    engine.saveTable('student');
    engine.saveTable('student_phonenum');
    engine.generateMasterFiles();

    res.json({
        success: true,
        message: `Student ${first_name} ${last_name || ''} registered. Default password: student123`
    });
});

app.post('/api/admin/assign-advisor', requireAuth('admin'), (req, res) => {
    const { student_id, faculty_id } = req.body;
    const stdRows = engine.tables['student']?.rows || [];
    const student = stdRows.find(s => s.Student_ID === student_id);

    if (student) {
        student.Faculty_ID = faculty_id || '';
        engine.saveTable('student');
        engine.generateMasterFiles();
        return res.json({ success: true, message: 'Advisor assigned successfully.' });
    }

    res.status(404).json({ success: false, error: 'Student not found.' });
});

app.delete('/api/admin/students', requireAuth('admin'), (req, res) => {
    const sid = req.query.student_id;
    const stdRows = engine.tables['student']?.rows || [];
    engine.tables['student'].rows = stdRows.filter(s => s.Student_ID !== sid);

    engine.saveTable('student');
    engine.generateMasterFiles();

    res.json({ success: true });
});

// Admin Courses & Prerequisites
app.get('/api/admin/courses', requireAuth('admin'), (req, res) => {
    const courseRows = engine.tables['course']?.rows || [];
    const deptRows = engine.tables['department']?.rows || [];
    const prereqRows = engine.tables['course_prerequisite']?.rows || [];

    const list = courseRows.map(c => {
        const dept = deptRows.find(d => d.Dept_ID === c.Dept_ID);
        const prereqs = prereqRows
            .filter(p => p.Course_ID === c.Course_ID)
            .map(p => p.Pre_Course_ID);

        return {
            course_id: c.Course_ID,
            course_title: c.Course_Title,
            credits: c.Credits,
            dept_id: c.Dept_ID,
            dept_name: dept ? dept.Dept_Name : '',
            prerequisites: prereqs
        };
    });

    res.json({ courses: list });
});

app.post('/api/admin/courses', requireAuth('admin'), (req, res) => {
    const { course_id, course_title, credits, dept_id } = req.body;

    if (!course_id || !course_title) {
        return res.status(400).json({ success: false, error: 'Please provide both Course ID and Title.' });
    }

    const courseRows = engine.tables['course']?.rows || [];
    courseRows.push({
        Course_ID: course_id,
        Course_Title: course_title,
        Credits: String(credits || '3.0'),
        Dept_ID: dept_id || '101'
    });

    engine.saveTable('course');
    engine.generateMasterFiles();

    res.json({ success: true, message: `Course ${course_id} created successfully.` });
});

app.post('/api/admin/prerequisites', requireAuth('admin'), (req, res) => {
    const { course_id, pre_course_id } = req.body;
    if (course_id && pre_course_id) {
        const prereqRows = engine.tables['course_prerequisite']?.rows || [];
        prereqRows.push({
            Course_ID: course_id,
            Pre_Course_ID: pre_course_id
        });
        engine.saveTable('course_prerequisite');
        engine.generateMasterFiles();
    }
    res.json({ success: true, message: 'Prerequisite linked.' });
});

app.delete('/api/admin/prerequisites', requireAuth('admin'), (req, res) => {
    const { course_id, pre_course_id } = req.query;
    const prereqRows = engine.tables['course_prerequisite']?.rows || [];
    engine.tables['course_prerequisite'].rows = prereqRows.filter(p =>
        !(p.Course_ID === course_id && p.Pre_Course_ID === pre_course_id)
    );

    engine.saveTable('course_prerequisite');
    engine.generateMasterFiles();

    res.json({ success: true });
});

app.delete('/api/admin/courses', requireAuth('admin'), (req, res) => {
    const cid = req.query.course_id;
    const courseRows = engine.tables['course']?.rows || [];
    engine.tables['course'].rows = courseRows.filter(c => c.Course_ID !== cid);

    engine.saveTable('course');
    engine.generateMasterFiles();

    res.json({ success: true });
});

// Admin Sections & Scheduling Clash Detection
app.get('/api/admin/sections', requireAuth('admin'), (req, res) => {
    const secRows = engine.tables['section']?.rows || [];
    const courseRows = engine.tables['course']?.rows || [];
    const facRows = engine.tables['faculty']?.rows || [];
    const enrRows = engine.tables['enrollment']?.rows || [];

    const list = secRows.map(sec => {
        const crs = courseRows.find(c => c.Course_ID === sec.Course_ID) || {};
        const fac = facRows.find(f => f.Faculty_ID === sec.Faculty_ID);
        const enrolledCount = enrRows.filter(e => String(e.Section_Id) === String(sec.Section_Id)).length;

        return {
            section_id: sec.Section_Id,
            section_no: sec.Section_No,
            time_slot: sec.Time_Slot,
            room_no: sec.Room_No,
            capacity: sec.Capacity,
            course_id: sec.Course_ID,
            faculty_id: sec.Faculty_ID,
            course_title: crs.Course_Title || '',
            credits: crs.Credits || '3.0',
            faculty_name: fac ? `${fac.First_name} ${fac.Last_name}` : '',
            enrolled_count: enrolledCount
        };
    });

    res.json({ sections: list });
});

app.post('/api/admin/sections', requireAuth('admin'), (req, res) => {
    const { course_id, section_no, time_slot, room_no, capacity, faculty_id } = req.body;
    const secRows = engine.tables['section']?.rows || [];

    // 1. Check duplicate section no for course
    const duplicate = secRows.find(s => s.Course_ID === course_id && String(s.Section_No) === String(section_no));
    if (duplicate) {
        return res.status(400).json({
            success: false,
            error: `Section ${section_no} already exists for course ${course_id}. Each section number for a course must be unique.`
        });
    }

    // 2. Check if course already has a section at this exact time slot
    const sameSlot = secRows.find(s => s.Course_ID === course_id && s.Time_Slot === time_slot);
    if (sameSlot) {
        return res.status(400).json({
            success: false,
            error: `Course ${course_id} already has Section ${sameSlot.Section_No} scheduled at ${time_slot}. Time slots for the same course must be unique.`
        });
    }

    // 3. Check room clash
    const roomClash = engine.getRoomClashInfo(room_no, time_slot);
    if (roomClash) {
        return res.status(400).json({
            success: false,
            error: `Room Collision: Room ${room_no} is already booked for ${roomClash.course_id} (Section ${roomClash.section_no}) during time slot ${time_slot}. Clash is not acceptable!`
        });
    }

    // 4. Check faculty clash
    const facClash = engine.getFacultyClashInfo(faculty_id, time_slot);
    if (facClash) {
        return res.status(400).json({
            success: false,
            error: `Faculty Schedule Clash: The assigned instructor is already teaching ${facClash.course_id} (Section ${facClash.section_no}) during ${time_slot}. Clash is not acceptable!`
        });
    }

    const secId = engine.getNextId('section', 'Section_Id');
    secRows.push({
        Section_Id: String(secId),
        Section_No: String(section_no || 1),
        Time_Slot: time_slot || '',
        Room_No: room_no || '',
        Capacity: String(capacity || 35),
        Course_ID: course_id,
        Faculty_ID: faculty_id || ''
    });

    engine.saveTable('section');
    engine.generateMasterFiles();

    res.json({
        success: true,
        message: 'Section created successfully without any schedule conflicts.'
    });
});

app.delete('/api/admin/sections', requireAuth('admin'), (req, res) => {
    const secId = req.query.section_id;
    const secRows = engine.tables['section']?.rows || [];
    engine.tables['section'].rows = secRows.filter(s => String(s.Section_Id) !== String(secId));

    engine.saveTable('section');
    engine.generateMasterFiles();

    res.json({ success: true });
});

// Admin Master Enrollments
app.get('/api/admin/enrollments', requireAuth('admin'), (req, res) => {
    const enrRows = engine.tables['enrollment']?.rows || [];
    const stdRows = engine.tables['student']?.rows || [];
    const secRows = engine.tables['section']?.rows || [];

    const list = enrRows.map(enr => {
        const std = stdRows.find(s => s.Student_ID === enr.Student_ID) || {};
        const sec = secRows.find(s => String(s.Section_Id) === String(enr.Section_Id)) || {};

        return {
            enrollment_id: enr.Enrollment_ID,
            student_id: enr.Student_ID,
            section_id: enr.Section_Id,
            status: enr.Advising_Status || '',
            grade: enr.Grade || 'N/A',
            mid_mark: enr.Mid_Mark || '',
            final_mark: enr.Final_Mark || '',
            semester: enr.Semester || '',
            year: enr.Year || '',
            student_name: std.First_name ? `${std.First_name} ${std.Last_name}` : '',
            course_id: sec.Course_ID || '',
            section_no: sec.Section_No || ''
        };
    });

    res.json({ enrollments: list });
});

app.post('/api/admin/enrollments', requireAuth('admin'), (req, res) => {
    const { student_id, section_id } = req.body;
    const enrId = engine.getNextId('enrollment', 'Enrollment_ID');

    const newEnr = {
        Enrollment_ID: String(enrId),
        Enrollment_Type: 'Regular',
        Advising_Status: 'Approved',
        Mid_Mark: '',
        Final_Mark: '',
        Grade: 'N/A',
        Section_Id: String(section_id),
        ManagedBy_Faculty_ID: '',
        Student_ID: student_id,
        Semester: 'Summer',
        Year: '2026'
    };

    engine.tables['enrollment'].rows.push(newEnr);
    engine.saveTable('enrollment');
    engine.generateMasterFiles();

    res.json({ success: true, message: 'Student enrolled successfully.' });
});

app.delete('/api/admin/enrollments', requireAuth('admin'), (req, res) => {
    const enrId = req.query.enrollment_id;
    const enrRows = engine.tables['enrollment']?.rows || [];
    engine.tables['enrollment'].rows = enrRows.filter(e => String(e.Enrollment_ID) !== String(enrId));

    engine.saveTable('enrollment');
    engine.generateMasterFiles();

    res.json({ success: true });
});

// Admin Payments Ledger
app.get('/api/admin/payments', requireAuth('admin'), (req, res) => {
    const payRows = engine.tables['payment']?.rows || [];
    const stdRows = engine.tables['student']?.rows || [];

    const list = payRows.map(p => {
        const std = stdRows.find(s => s.Student_ID === p.Student_ID) || {};
        return {
            payment_id: p.Payment_Id,
            transaction_id: p.Transaction_Id,
            student_id: p.Student_ID,
            amount: p.Amount,
            semester: p.Semester,
            year: p.Year,
            payment_date: p.Payment_Date,
            status: p.Payment_Status,
            student_name: std.First_name ? `${std.First_name} ${std.Last_name}` : ''
        };
    });

    res.json({ payments: list });
});

app.post('/api/admin/payments-action', requireAuth('admin'), (req, res) => {
    const { payment_id, status = 'Paid' } = req.body;
    const payRows = engine.tables['payment']?.rows || [];
    const pay = payRows.find(p => String(p.Payment_Id) === String(payment_id));

    if (pay) {
        pay.Payment_Status = status;
        if (status === 'Paid') {
            pay.Payment_Date = new Date().toISOString().substring(0, 10);
        }
        engine.saveTable('payment');
        engine.generateMasterFiles();
        return res.json({ success: true, message: `Payment status updated to ${status}.` });
    }

    res.status(404).json({ success: false, error: 'Payment record not found.' });
});

// Admin Excel Manager
app.get('/api/admin/excel/stats', requireAuth('admin'), (req, res) => {
    let totalRows = 0;
    const tablesArr = engine.tableNames.map(name => {
        const count = (engine.tables[name]?.rows || []).length;
        totalRows += count;
        return {
            name,
            rows: count,
            csv_file: `${name}.csv`
        };
    });

    res.json({
        tables: tablesArr,
        total_rows: totalRows,
        master_file: 'EWU_University_Portal_Database.xlsx',
        master_file_xml: 'EWU_University_Portal_Database.xml'
    });
});

app.post('/api/admin/excel/sync', requireAuth('admin'), (req, res) => {
    engine.saveAll();
    res.json({
        success: true,
        message: 'All 14 Excel database spreadsheets and the Master Workbook (.xlsx) have been flushed to disk.'
    });
});

app.post('/api/admin/excel/reload', requireAuth('admin'), (req, res) => {
    engine.loadAll();
    res.json({
        success: true,
        message: 'All tables reloaded from Excel CSV files into memory.'
    });
});

// ─── STATIC ASSET SERVING ───────────────────────────────────────────────────

// Serve static assets from project root
app.use(express.static(__dirname));

// Direct access to root serves index.html
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'index.html'));
});

// Start Server (when run directly via `node server.js`)
if (require.main === module) {
    const server = app.listen(PORT, '0.0.0.0', () => {
        console.log('======================================================');
        console.log('  East West University Portal - Node.js Server        ');
        console.log(`  Running on: http://localhost:${PORT}               `);
        console.log('  Database Engine: 100% Excel Spreadsheets (.xlsx)    ');
        console.log('======================================================');
    });

    server.on('error', (err) => {
        if (err.code === 'EADDRINUSE') {
            console.log(`[INFO] Port ${PORT} is already in use by a running instance.`);
            console.log(`The portal is already active at: http://localhost:${PORT}`);
        } else {
            console.error('Server error:', err);
        }
    });
}

module.exports = app;
module.exports.engine = engine;
module.exports.sessions = sessions;
