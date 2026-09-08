const fs = require('fs');
const path = require('path');
let XLSX;
try {
    XLSX = require('xlsx');
} catch (e) {
    // Will be loaded when npm finishes
}

const TABLE_NAMES = [
    'admin', 'department', 'faculty', 'faculty_phonenum',
    'student', 'student_phonenum', 'course', 'course_prerequisite',
    'section', 'enrollment', 'pre_advising', 'includedcourse',
    'payment', 'faculty_courses'
];

class ExcelEngine {
    constructor(dataDir = 'data') {
        this.dataDir = path.resolve(__dirname, dataDir);
        this.tables = {};
        this.tableNames = TABLE_NAMES;
        this.loadAll();
    }

    // --- CSV Utility Functions ---
    static parseCsvLine(line) {
        const fields = [];
        let current = '';
        let inQuotes = false;
        for (let i = 0; i < line.length; i++) {
            const c = line[i];
            if (c === '"') {
                if (inQuotes && i + 1 < line.length && line[i + 1] === '"') {
                    current += '"';
                    i++;
                } else {
                    inQuotes = !inQuotes;
                }
            } else if (c === ',' && !inQuotes) {
                fields.push(current);
                current = '';
            } else {
                current += c;
            }
        }
        fields.push(current);
        return fields;
    }

    static formatCsvField(field) {
        if (field === null || field === undefined) return '';
        const str = String(field);
        if (str.includes(',') || str.includes('"') || str.includes('\n') || str.includes('\r')) {
            return `"${str.replace(/"/g, '""')}"`;
        }
        return str;
    }

    // --- Load Table from CSV ---
    loadTable(tblName) {
        const csvPath = path.join(this.dataDir, `${tblName}.csv`);
        if (!fs.existsSync(csvPath)) {
            this.tables[tblName] = { name: tblName, headers: [], rows: [] };
            return false;
        }

        let content = fs.readFileSync(csvPath, 'utf8');
        // Remove UTF-8 BOM if present
        if (content.charCodeAt(0) === 0xFEFF) {
            content = content.slice(1);
        }

        const lines = content.split(/\r?\n/);
        if (lines.length === 0) {
            this.tables[tblName] = { name: tblName, headers: [], rows: [] };
            return false;
        }

        const headers = ExcelEngine.parseCsvLine(lines[0]).map(h => h.trim());
        const rows = [];

        for (let i = 1; i < lines.length; i++) {
            const line = lines[i].trim();
            if (!line) continue;
            const fields = ExcelEngine.parseCsvLine(lines[i]);
            const row = {};
            for (let j = 0; j < headers.length; j++) {
                row[headers[j]] = (j < fields.length) ? fields[j].trim() : '';
            }
            rows.push(row);
        }

        this.tables[tblName] = { name: tblName, headers, rows };
        return true;
    }

    loadAll() {
        if (!fs.existsSync(this.dataDir)) {
            fs.mkdirSync(this.dataDir, { recursive: true });
        }

        for (const name of this.tableNames) {
            this.loadTable(name);
        }

        // Generate Master Excel files if not present or on startup
        this.generateMasterFiles();
    }

    // --- Save Table to CSV ---
    saveTable(tblName) {
        const tbl = this.tables[tblName];
        if (!tbl) return false;

        const csvPath = path.join(this.dataDir, `${tblName}.csv`);
        const lines = [];

        // Header line
        lines.push(tbl.headers.map(h => ExcelEngine.formatCsvField(h)).join(','));

        // Data lines
        for (const row of tbl.rows) {
            const rowFields = tbl.headers.map(h => ExcelEngine.formatCsvField(row[h] !== undefined ? row[h] : ''));
            lines.push(rowFields.join(','));
        }

        fs.writeFileSync(csvPath, lines.join('\n') + '\n', 'utf8');
        return true;
    }

    saveAll() {
        for (const name of this.tableNames) {
            this.saveTable(name);
        }
        this.generateMasterFiles();
    }

    // --- Generate Master .xlsx Workbook and .xml Spreadsheet ---
    generateMasterFiles() {
        this.generateMasterExcelWorkbook();
        this.generateMasterExcelXml();
    }

    generateMasterExcelWorkbook() {
        try {
            if (!XLSX) XLSX = require('xlsx');
            const wb = XLSX.utils.book_new();

            for (const name of this.tableNames) {
                const tbl = this.tables[name];
                if (!tbl) continue;

                // Format sheet name (Capitalize first letter, max 31 chars)
                let sheetName = name.charAt(0).toUpperCase() + name.slice(1);
                if (sheetName.length > 31) sheetName = sheetName.substring(0, 31);

                // Convert table rows to sheet
                const sheetData = [];
                // Headers
                sheetData.push(tbl.headers);
                // Rows
                for (const row of tbl.rows) {
                    const r = tbl.headers.map(h => (row[h] !== undefined ? row[h] : ''));
                    sheetData.push(r);
                }

                const ws = XLSX.utils.aoa_to_sheet(sheetData);

                // Auto-fit column widths
                const colWidths = tbl.headers.map((h, i) => {
                    let maxLen = h.length;
                    for (const r of tbl.rows) {
                        const val = r[h] !== undefined ? String(r[h]) : '';
                        if (val.length > maxLen) maxLen = val.length;
                    }
                    return { wch: Math.min(Math.max(maxLen + 4, 12), 40) };
                });
                ws['!cols'] = colWidths;

                XLSX.utils.book_append_sheet(wb, ws, sheetName);
            }

            const xlsxPath = path.join(this.dataDir, 'EWU_University_Portal_Database.xlsx');
            XLSX.writeFile(wb, xlsxPath);
        } catch (err) {
            console.error('Error generating master .xlsx workbook:', err.message);
        }
    }

    generateMasterExcelXml() {
        try {
            const xmlPath = path.join(this.dataDir, 'EWU_University_Portal_Database.xml');
            let xml = `<?xml version="1.0" encoding="UTF-8"?>\n`
                + `<?mso-application progid="Excel.Sheet"?>\n`
                + `<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"\n`
                + ` xmlns:o="urn:schemas-microsoft-com:office:office"\n`
                + ` xmlns:x="urn:schemas-microsoft-com:office:excel"\n`
                + ` xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"\n`
                + ` xmlns:html="http://www.w3.org/TR/REC-html40">\n`
                + ` <Styles>\n`
                + `  <Style ss:ID="Default" ss:Name="Normal">\n`
                + `   <Alignment ss:Vertical="Center"/>\n`
                + `   <Font ss:FontName="Segoe UI" ss:Size="10" ss:Color="#333333"/>\n`
                + `  </Style>\n`
                + `  <Style ss:ID="HeaderStyle">\n`
                + `   <Alignment ss:Horizontal="Center" ss:Vertical="Center"/>\n`
                + `   <Borders>\n`
                + `    <Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1" ss:Color="#CBD5E1"/>\n`
                + `   </Borders>\n`
                + `   <Font ss:FontName="Segoe UI" ss:Size="11" ss:Bold="1" ss:Color="#FFFFFF"/>\n`
                + `   <Interior ss:Color="#1E3A8A" ss:Pattern="Solid"/>\n`
                + `  </Style>\n`
                + `  <Style ss:ID="RowEven">\n`
                + `   <Interior ss:Color="#F8FAFC" ss:Pattern="Solid"/>\n`
                + `  </Style>\n`
                + `  <Style ss:ID="RowOdd">\n`
                + `   <Interior ss:Color="#FFFFFF" ss:Pattern="Solid"/>\n`
                + `  </Style>\n`
                + ` </Styles>\n`;

            for (const name of this.tableNames) {
                const tbl = this.tables[name];
                if (!tbl) continue;
                let sheetName = name.charAt(0).toUpperCase() + name.slice(1);
                if (sheetName.length > 31) sheetName = sheetName.substring(0, 31);

                xml += ` <Worksheet ss:Name="${sheetName}">\n`;
                xml += `  <Table ss:DefaultRowHeight="20">\n`;

                for (let i = 0; i < tbl.headers.length; i++) {
                    xml += `   <Column ss:AutoFitWidth="1" ss:Width="120"/>\n`;
                }

                xml += `   <Row ss:Height="24">\n`;
                for (const h of tbl.headers) {
                    xml += `    <Cell ss:StyleID="HeaderStyle"><Data ss:Type="String">${escapeXml(h)}</Data></Cell>\n`;
                }
                xml += `   </Row>\n`;

                tbl.rows.forEach((row, rIdx) => {
                    const style = (rIdx % 2 === 0) ? 'RowEven' : 'RowOdd';
                    xml += `   <Row ss:StyleID="${style}">\n`;
                    for (const h of tbl.headers) {
                        const val = (row[h] !== undefined && row[h] !== null) ? String(row[h]) : '';
                        xml += `    <Cell><Data ss:Type="String">${escapeXml(val)}</Data></Cell>\n`;
                    }
                    xml += `   </Row>\n`;
                });

                xml += `  </Table>\n </Worksheet>\n`;
            }

            xml += `</Workbook>\n`;
            fs.writeFileSync(xmlPath, xml, 'utf8');
        } catch (err) {
            console.error('Error generating master .xml:', err.message);
        }
    }

    // --- Auto Increment Helper ---
    getNextId(tblName, idField) {
        const tbl = this.tables[tblName];
        if (!tbl) return 1;
        let maxId = 0;
        for (const row of tbl.rows) {
            const val = parseInt(row[idField], 10);
            if (!isNaN(val) && val > maxId) {
                maxId = val;
            }
        }
        return maxId + 1;
    }

    // ─── Time Slot and Scheduling Clash Detection ───────────────────────────

    static parseTimeToMinutes(timeStr) {
        if (!timeStr) return 0;
        const s = timeStr.trim();
        // e.g. "08:30 AM" or "10:00 PM"
        const match = s.match(/(\d+):(\d+)\s*(AM|PM)?/i);
        if (!match) return 0;

        let hour = parseInt(match[1], 10);
        const min = parseInt(match[2], 10);
        const ampm = (match[3] || '').toUpperCase();

        if (ampm === 'PM' && hour < 12) hour += 12;
        if (ampm === 'AM' && hour === 12) hour = 0;

        return hour * 60 + min;
    }

    static parseTimeSlots(slotStr) {
        const slots = [];
        if (!slotStr) return slots;
        const s = slotStr.trim();

        // Format e.g. "Sun-Tue 08:30 AM - 10:00 AM" or "Mon-Wed 01:30 PM - 03:00 PM"
        const match = s.match(/^([A-Za-z\-]+)\s+(.+?)\s*-\s*(.+)$/);
        if (!match) {
            // Fallback space splitting
            const spaceIdx = s.indexOf(' ');
            if (spaceIdx === -1) return slots;
            const dayToken = s.substring(0, spaceIdx).trim();
            const rest = s.substring(spaceIdx + 1).trim();
            const sepIdx = rest.indexOf('-');
            if (sepIdx === -1) return slots;
            const startStr = rest.substring(0, sepIdx).trim();
            const endStr = rest.substring(sepIdx + 1).trim();
            const startMins = ExcelEngine.parseTimeToMinutes(startStr);
            const endMins = ExcelEngine.parseTimeToMinutes(endStr);
            slots.push({ days: [dayToken], startMins, endMins });
            return slots;
        }

        const dayToken = match[1].trim();
        const startStr = match[2].trim();
        const endStr = match[3].trim();

        const startMins = ExcelEngine.parseTimeToMinutes(startStr);
        const endMins = ExcelEngine.parseTimeToMinutes(endStr);

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
    }

    static slotsOverlap(slot1, slot2) {
        const list1 = ExcelEngine.parseTimeSlots(slot1);
        const list2 = ExcelEngine.parseTimeSlots(slot2);

        for (const a of list1) {
            for (const b of list2) {
                // Check if any day matches
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
                    // Interval overlap: a.start < b.end && b.start < a.end
                    if (a.startMins < b.endMins && b.startMins < a.endMins) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    getRoomClashInfo(roomNo, timeSlot, excludeSecId = '') {
        if (!roomNo || !timeSlot) return null;
        const normRoom = roomNo.trim().toLowerCase();
        const secRows = this.tables['section'] ? this.tables['section'].rows : [];
        const courseRows = this.tables['course'] ? this.tables['course'].rows : [];

        for (const sec of secRows) {
            if (excludeSecId && String(sec.Section_Id) === String(excludeSecId)) continue;
            if ((sec.Room_No || '').trim().toLowerCase() === normRoom) {
                if (ExcelEngine.slotsOverlap(sec.Time_Slot, timeSlot)) {
                    const crs = courseRows.find(c => c.Course_ID === sec.Course_ID) || {};
                    return {
                        section_id: sec.Section_Id,
                        section_no: sec.Section_No,
                        course_id: sec.Course_ID,
                        course_title: crs.Course_Title || sec.Course_ID,
                        time_slot: sec.Time_Slot,
                        room_no: sec.Room_No
                    };
                }
            }
        }
        return null;
    }

    checkRoomClash(roomNo, timeSlot, excludeSecId = '') {
        return !!this.getRoomClashInfo(roomNo, timeSlot, excludeSecId);
    }

    getFacultyClashInfo(facultyId, timeSlot, excludeSecId = '') {
        if (!facultyId || !timeSlot) return null;
        const secRows = this.tables['section'] ? this.tables['section'].rows : [];
        const courseRows = this.tables['course'] ? this.tables['course'].rows : [];

        for (const sec of secRows) {
            if (excludeSecId && String(sec.Section_Id) === String(excludeSecId)) continue;
            if (String(sec.Faculty_ID) === String(facultyId)) {
                if (ExcelEngine.slotsOverlap(sec.Time_Slot, timeSlot)) {
                    const crs = courseRows.find(c => c.Course_ID === sec.Course_ID) || {};
                    return {
                        section_id: sec.Section_Id,
                        section_no: sec.Section_No,
                        course_id: sec.Course_ID,
                        course_title: crs.Course_Title || sec.Course_ID,
                        time_slot: sec.Time_Slot,
                        room_no: sec.Room_No
                    };
                }
            }
        }
        return null;
    }

    checkFacultyClash(facultyId, timeSlot, excludeSecId = '') {
        return !!this.getFacultyClashInfo(facultyId, timeSlot, excludeSecId);
    }

    getStudentClashInfo(studentId, timeSlot, semester = 'Summer', year = 2026) {
        if (!studentId || !timeSlot) return null;
        const enrRows = this.tables['enrollment'] ? this.tables['enrollment'].rows : [];
        const secRows = this.tables['section'] ? this.tables['section'].rows : [];
        const courseRows = this.tables['course'] ? this.tables['course'].rows : [];

        for (const enr of enrRows) {
            if (String(enr.Student_ID) === String(studentId) &&
                String(enr.Semester || '').toLowerCase() === String(semester || '').toLowerCase() &&
                parseInt(enr.Year, 10) === parseInt(year, 10)) {
                const secId = String(enr.Section_Id);
                const sec = secRows.find(s => String(s.Section_Id) === secId);
                if (sec && ExcelEngine.slotsOverlap(sec.Time_Slot, timeSlot)) {
                    const crs = courseRows.find(c => c.Course_ID === sec.Course_ID) || {};
                    return {
                        section_id: sec.Section_Id,
                        section_no: sec.Section_No,
                        time_slot: sec.Time_Slot,
                        room_no: sec.Room_No,
                        course_id: sec.Course_ID,
                        course_title: crs.Course_Title || sec.Course_ID
                    };
                }
            }
        }
        return null;
    }

    checkStudentClash(studentId, timeSlot, semester = 'Summer', year = 2026) {
        return !!this.getStudentClashInfo(studentId, timeSlot, semester, year);
    }

    // ─── Grading Calculation ────────────────────────────────────────────────
    static calculateGrade(midMark, finalMark) {
        const mid = parseFloat(midMark) || 0;
        const fin = parseFloat(finalMark) || 0;
        const total = mid + fin;

        if (total >= 80.0) return 'A+';
        if (total >= 75.0) return 'A';
        if (total >= 70.0) return 'A-';
        if (total >= 65.0) return 'B+';
        if (total >= 60.0) return 'B';
        if (total >= 55.0) return 'B-';
        if (total >= 50.0) return 'C+';
        if (total >= 45.0) return 'C';
        if (total >= 40.0) return 'D';
        return 'F';
    }

    static gradeToPoint(grade) {
        switch ((grade || '').trim().toUpperCase()) {
            case 'A+': return 4.00;
            case 'A':  return 3.75;
            case 'A-': return 3.50;
            case 'B+': return 3.25;
            case 'B':  return 3.00;
            case 'B-': return 2.75;
            case 'C+': return 2.50;
            case 'C':  return 2.25;
            case 'D':  return 2.00;
            default:   return 0.00;
        }
    }
}

function escapeXml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&apos;');
}

module.exports = { ExcelEngine, TABLE_NAMES };
