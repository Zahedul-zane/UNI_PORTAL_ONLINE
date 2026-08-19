<?php
$pageTitle = "Section Scheduling & Room Management";
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$msg = '';
$msgType = '';

// ─── POST: Handle Add Section ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_section'])) {
    $courseId  = sanitize($_POST['course_id'] ?? '');
    $secNo     = intval($_POST['section_no'] ?? 1);
    $timeSlot  = sanitize($_POST['time_slot'] ?? '');
    $customTime = sanitize($_POST['custom_time_slot'] ?? '');
    if ($timeSlot === '__custom__' && !empty($customTime)) {
        $timeSlot = $customTime;
    }

    $roomNo    = sanitize($_POST['room_no'] ?? '');
    $customRoom = sanitize($_POST['custom_room_no'] ?? '');
    if ($roomNo === '__custom__' && !empty($customRoom)) {
        $roomNo = $customRoom;
    }

    $capacity  = intval($_POST['capacity'] ?? 35);
    $facultyId = !empty($_POST['faculty_id']) ? sanitize($_POST['faculty_id']) : null;

    if (empty($courseId) || empty($timeSlot) || empty($roomNo) || $secNo <= 0) {
        $msg = 'Please complete all required fields with valid values.';
        $msgType = 'danger';
    } else {
        // 1. Check Section Number Uniqueness for Course
        if (ewu_check_duplicate_section_no($pdo, $courseId, $secNo)) {
            $nextAvail = ewu_get_next_available_section_no($pdo, $courseId);
            $msg = "⚠️ <strong>Duplicate Section Number:</strong> Course <strong>$courseId</strong> already has a <strong>Section $secNo</strong>. Every section number for a course must be unique. (Suggested next available section: <strong>Sec $nextAvail</strong>).";
            $msgType = 'danger';
        } else {
            // 2. Check Room Collision (No room double-booking)
            $roomClash = ewu_check_room_clash($pdo, $roomNo, $timeSlot);
            if ($roomClash['clash']) {
                $msg = "⚠️ <strong>Room Booking Conflict:</strong> " . $roomClash['message'] . " A classroom/lab cannot be scheduled for multiple courses simultaneously.";
                $msgType = 'danger';
            } else {
                // 3. Check Faculty Collision (Instructor cannot be in 2 places at once)
                $facClash = ewu_check_faculty_clash($pdo, $facultyId, $timeSlot);
                if ($facClash['clash']) {
                    $msg = "⚠️ <strong>Faculty Schedule Conflict:</strong> " . $facClash['message'];
                    $msgType = 'danger';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO Section (Section_No, Time_Slot, Room_No, Capacity, Course_ID, Faculty_ID)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$secNo, $timeSlot, $roomNo, $capacity, $courseId, $facultyId]);
                        $newId = $pdo->lastInsertId();

                        $msg = "🎉 <strong>Success!</strong> Section $secNo for <strong>$courseId</strong> scheduled in Room <strong>$roomNo</strong> ($timeSlot) successfully (Section #$newId).";
                        $msgType = 'success';
                    } catch (Exception $e) {
                        $msg = 'Error creating section: ' . $e->getMessage();
                        $msgType = 'danger';
                    }
                }
            }
        }
    }
}

// ─── POST: Handle Edit Section ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_section'])) {
    $secId     = intval($_POST['section_id'] ?? 0);
    $courseId  = sanitize($_POST['course_id'] ?? '');
    $secNo     = intval($_POST['section_no'] ?? 1);
    $timeSlot  = sanitize($_POST['time_slot'] ?? '');
    $customTime = sanitize($_POST['custom_time_slot'] ?? '');
    if ($timeSlot === '__custom__' && !empty($customTime)) {
        $timeSlot = $customTime;
    }

    $roomNo    = sanitize($_POST['room_no'] ?? '');
    $customRoom = sanitize($_POST['custom_room_no'] ?? '');
    if ($roomNo === '__custom__' && !empty($customRoom)) {
        $roomNo = $customRoom;
    }

    $capacity  = intval($_POST['capacity'] ?? 35);
    $facultyId = !empty($_POST['faculty_id']) ? sanitize($_POST['faculty_id']) : null;

    if ($secId <= 0 || empty($courseId) || empty($timeSlot) || empty($roomNo) || $secNo <= 0) {
        $msg = 'Invalid section data submitted for update.';
        $msgType = 'danger';
    } else {
        // 1. Check Section Number Uniqueness (excluding this section)
        if (ewu_check_duplicate_section_no($pdo, $courseId, $secNo, $secId)) {
            $msg = "⚠️ <strong>Duplicate Section Number:</strong> Course <strong>$courseId</strong> already has another <strong>Section $secNo</strong>. Section numbers must be unique per course.";
            $msgType = 'danger';
        } else {
            // 2. Check Room Collision
            $roomClash = ewu_check_room_clash($pdo, $roomNo, $timeSlot, $secId);
            if ($roomClash['clash']) {
                $msg = "⚠️ <strong>Room Booking Conflict:</strong> " . $roomClash['message'];
                $msgType = 'danger';
            } else {
                // 3. Check Faculty Collision
                $facClash = ewu_check_faculty_clash($pdo, $facultyId, $timeSlot, $secId);
                if ($facClash['clash']) {
                    $msg = "⚠️ <strong>Faculty Schedule Conflict:</strong> " . $facClash['message'];
                    $msgType = 'danger';
                } else {
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE Section
                            SET Section_No = ?, Time_Slot = ?, Room_No = ?, Capacity = ?, Course_ID = ?, Faculty_ID = ?
                            WHERE Section_Id = ?
                        ");
                        $stmt->execute([$secNo, $timeSlot, $roomNo, $capacity, $courseId, $facultyId, $secId]);

                        $msg = "✅ Section #$secId ($courseId - Sec $secNo) updated successfully!";
                        $msgType = 'success';
                    } catch (Exception $e) {
                        $msg = 'Update failed: ' . $e->getMessage();
                        $msgType = 'danger';
                    }
                }
            }
        }
    }
}

// ─── GET: Delete Section ───────────────────────────────────────────────────
if (isset($_GET['delete_id'])) {
    $delId = intval($_GET['delete_id']);
    try {
        $stmt = $pdo->prepare("DELETE FROM Section WHERE Section_Id = ?");
        $stmt->execute([$delId]);
        $msg = "Section #$delId and associated student enrollment records removed successfully.";
        $msgType = 'success';
    } catch (Exception $e) {
        $msg = 'Error deleting section: ' . $e->getMessage();
        $msgType = 'danger';
    }
}

// ─── Fetch Master Data ─────────────────────────────────────────────────────
$sections = $pdo->query("
    SELECT sec.*, c.Course_Title, c.Credits,
           f.First_name, f.Last_name, f.E_mail AS Faculty_Email,
           CONCAT(f.First_name, ' ', f.Last_name) AS Faculty_Name,
           (SELECT COUNT(*) FROM Enrollment e WHERE e.Section_Id = sec.Section_Id) AS EnrolledCount
    FROM Section sec
    JOIN Course c ON sec.Course_ID = c.Course_ID
    LEFT JOIN Faculty f ON sec.Faculty_ID = f.Faculty_ID
    ORDER BY sec.Course_ID ASC, sec.Section_No ASC
")->fetchAll();

$courses = $pdo->query("SELECT Course_ID, Course_Title, Credits FROM Course ORDER BY Course_ID ASC")->fetchAll();
$facultyList = $pdo->query("SELECT Faculty_ID, First_name, Last_name, Designation, Room_No FROM Faculty ORDER BY First_name ASC")->fetchAll();

// Predefined EWU Dropdown lists
$standardRooms = ewu_get_standard_rooms();
$standardTimeSlots = ewu_get_standard_time_slots();

// Build map of existing course section counts for quick client-side next-section suggestions
$existingCourseSections = [];
foreach ($sections as $s) {
    $cid = $s['Course_ID'];
    if (!isset($existingCourseSections[$cid])) {
        $existingCourseSections[$cid] = [];
    }
    $existingCourseSections[$cid][] = intval($s['Section_No']);
}

// Statistics
$totalSections = count($sections);
$totalCapacity = array_sum(array_column($sections, 'Capacity'));
$totalEnrolled = array_sum(array_column($sections, 'EnrolledCount'));
$uniqueRoomsUsed = count(array_unique(array_column($sections, 'Room_No')));

include __DIR__ . '/../includes/header.php';
?>

<div class="page-banner">
    <h1>🗓️ Class Sections & Room Scheduling</h1>
    <p>Manage course section numbers, assigned classrooms/labs, and timetable slots with automated conflict & duplication prevention.</p>
</div>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>" style="margin-bottom: 24px;">
        <span><?= $msg ?></span>
    </div>
<?php endif; ?>

<!-- Summary Stats Cards -->
<div class="stats-grid" style="margin-bottom: 28px;">
    <div class="stat-card">
        <div class="stat-icon primary">📚</div>
        <div class="stat-details">
            <h3><?= $totalSections ?></h3>
            <p>Active Sections</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success">🏢</div>
        <div class="stat-details">
            <h3><?= $uniqueRoomsUsed ?></h3>
            <p>Classrooms / Labs Assigned</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon gold">👨‍🎓</div>
        <div class="stat-details">
            <h3><?= $totalEnrolled ?> <span style="font-size:14px; font-weight:normal; opacity:.7;">/ <?= $totalCapacity ?></span></h3>
            <p>Total Enrolled Seats</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon info">🛡️</div>
        <div class="stat-details">
            <h3 style="color:#059669;">Active</h3>
            <p>Clash & Duplication Shield</p>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 380px 1fr; gap: 25px; align-items: start;">

    <!-- ─── LEFT: Schedule New Section Form ──────────────────────────────────── -->
    <div class="panel">
        <div class="panel-header" style="background: linear-gradient(135deg, var(--primary) 0%, #1e3a8a 100%); color: white;">
            <div class="panel-title" style="color: white;">➕ Schedule New Section</div>
        </div>
        <div class="panel-body">
            <form action="sections.php" method="POST" id="add_section_form">
                
                <!-- Course Selection -->
                <div class="form-group">
                    <label for="course_id">Course (*)</label>
                    <select name="course_id" id="course_id" class="form-control" required onchange="handleCourseChange(this.value)">
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['Course_ID'] ?>">
                                <?= htmlspecialchars($c['Course_ID'] . ': ' . $c['Course_Title']) ?> (<?= number_format($c['Credits'], 1) ?> Cr)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="course_sec_hint" style="display:block; margin-top:4px; color:var(--text-muted); font-size:12px;"></small>
                </div>

                <!-- Section No & Capacity -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label for="section_no">Section No (*)</label>
                        <input type="number" name="section_no" id="section_no" class="form-control" value="1" min="1" required oninput="validateSectionNoLive()">
                        <small id="sec_no_feedback" style="display:block; margin-top:3px; font-size:11px;"></small>
                    </div>
                    <div class="form-group">
                        <label for="capacity">Seat Capacity (*)</label>
                        <input type="number" name="capacity" id="capacity" class="form-control" value="35" min="5" max="100" required>
                    </div>
                </div>

                <!-- Room Number Dropdown -->
                <div class="form-group">
                    <label for="room_no">Room / Lab Number (*)</label>
                    <select name="room_no" id="room_no" class="form-control" required onchange="handleRoomSelectChange(this, 'custom_room_wrap')">
                        <option value="">-- Select Classroom / Lab --</option>
                        <?php foreach ($standardRooms as $building => $rooms): ?>
                            <optgroup label="<?= htmlspecialchars($building) ?>">
                                <?php foreach ($rooms as $code => $label): ?>
                                    <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                        <option value="__custom__">➕ Other / Custom Room...</option>
                    </select>
                    
                    <div id="custom_room_wrap" style="display: none; margin-top: 8px;">
                        <input type="text" name="custom_room_no" id="custom_room_no" class="form-control" placeholder="Enter custom room code (e.g. AB4-101)">
                    </div>
                </div>

                <!-- Time Slot Dropdown -->
                <div class="form-group">
                    <label for="time_slot">Schedule Time Slot (*)</label>
                    <select name="time_slot" id="time_slot" class="form-control" required onchange="handleTimeSlotChange(this, 'custom_time_wrap')">
                        <option value="">-- Select Time Slot --</option>
                        <?php foreach ($standardTimeSlots as $pattern => $slots): ?>
                            <optgroup label="<?= htmlspecialchars($pattern) ?>">
                                <?php foreach ($slots as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                        <option value="__custom__">➕ Other / Custom Time Slot...</option>
                    </select>

                    <div id="custom_time_wrap" style="display: none; margin-top: 8px;">
                        <input type="text" name="custom_time_slot" id="custom_time_slot" class="form-control" placeholder="e.g. Sun-Tue 06:30 PM - 08:00 PM">
                    </div>
                </div>

                <!-- Faculty Instructor Dropdown -->
                <div class="form-group">
                    <label for="faculty_id">Faculty Instructor</label>
                    <select name="faculty_id" id="faculty_id" class="form-control">
                        <option value="">-- Unassigned (TBA) --</option>
                        <?php foreach ($facultyList as $fac): ?>
                            <option value="<?= $fac['Faculty_ID'] ?>">
                                <?= htmlspecialchars($fac['First_name'] . ' ' . $fac['Last_name']) ?> (<?= htmlspecialchars($fac['Designation'] ?? 'Faculty') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="add_section" class="btn btn-gold" style="width: 100%; padding: 13px; font-weight: 600; margin-top: 6px;">
                    💾 Schedule Section
                </button>
            </form>
        </div>
    </div>

    <!-- ─── RIGHT: Sections Table & Filters ──────────────────────────────────── -->
    <div class="panel">
        <div class="panel-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div class="panel-title">📋 Active Class Sections Directory</div>
            
            <!-- Quick Search Input -->
            <input type="text" id="sec_filter_search" class="form-control" placeholder="🔍 Search any section..." style="width: 220px; font-size: 13px;">
        </div>

        <!-- Table Dropdown Filter Bar -->
        <div style="background: #f8fafc; padding: 14px 20px; border-bottom: 1px solid var(--border-color); display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <span style="font-size: 13px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 4px;">
                <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                Table Filters:
            </span>

            <!-- Filter by Course -->
            <select id="filter_course" class="form-control" style="width: auto; max-width: 170px; font-size: 13px; padding: 6px 10px;" onchange="applyTableFilters()">
                <option value="">All Courses</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= htmlspecialchars($c['Course_ID']) ?>"><?= htmlspecialchars($c['Course_ID']) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Filter by Room -->
            <select id="filter_room" class="form-control" style="width: auto; max-width: 170px; font-size: 13px; padding: 6px 10px;" onchange="applyTableFilters()">
                <option value="">All Rooms</option>
                <?php
                $uniqueRooms = array_unique(array_column($sections, 'Room_No'));
                sort($uniqueRooms);
                foreach ($uniqueRooms as $rm):
                ?>
                    <option value="<?= htmlspecialchars($rm) ?>"><?= htmlspecialchars($rm) ?></option>
                <?php endforeach; ?>
            </select>

            <!-- Filter by Time Slot Pattern -->
            <select id="filter_time" class="form-control" style="width: auto; max-width: 180px; font-size: 13px; padding: 6px 10px;" onchange="applyTableFilters()">
                <option value="">All Time Slots</option>
                <option value="Sun-Tue">Sunday & Tuesday</option>
                <option value="Mon-Wed">Monday & Wednesday</option>
                <option value="Thu-Sat">Thursday & Saturday</option>
                <option value="Fri">Friday Sessions</option>
            </select>

            <!-- Reset Filter Button -->
            <button type="button" class="btn btn-sm btn-secondary" onclick="resetTableFilters()" style="font-size: 12px;">
                ↺ Reset
            </button>
        </div>

        <div class="panel-body" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table" id="master_sec_table">
                    <thead>
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th>Course</th>
                            <th style="text-align: center;">Section</th>
                            <th>Time Slot</th>
                            <th>Room No</th>
                            <th>Instructor</th>
                            <th style="text-align: center;">Capacity</th>
                            <th style="text-align: center; width: 110px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sections)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    No sections scheduled yet. Use the form on the left to schedule a new class section.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sections as $s): ?>
                                <tr data-course="<?= htmlspecialchars($s['Course_ID']) ?>"
                                    data-room="<?= htmlspecialchars($s['Room_No']) ?>"
                                    data-time="<?= htmlspecialchars($s['Time_Slot']) ?>"
                                    data-sec="<?= $s['Section_No'] ?>">
                                    <td><code>#<?= $s['Section_Id'] ?></code></td>
                                    <td>
                                        <strong><?= htmlspecialchars($s['Course_ID']) ?></strong>
                                        <div style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($s['Course_Title']) ?></div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="badge badge-primary" style="font-size: 13px; font-weight: 700;">
                                            Sec <?= $s['Section_No'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 13px; font-weight: 500;"><?= htmlspecialchars($s['Time_Slot']) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info" style="font-size: 12px;">
                                            🚪 <?= htmlspecialchars($s['Room_No']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($s['Faculty_Name'])): ?>
                                            <div style="font-weight: 500; font-size: 13px;"><?= htmlspecialchars($s['Faculty_Name']) ?></div>
                                            <small style="color: var(--text-muted);"><?= htmlspecialchars($s['Faculty_Email'] ?? '') ?></small>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic; font-size: 13px;">TBA (Unassigned)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <span style="font-weight: 700; color: <?= $s['EnrolledCount'] >= $s['Capacity'] ? '#dc2626' : 'inherit' ?>;">
                                            <?= $s['EnrolledCount'] ?>
                                        </span>
                                        <span style="color: var(--text-muted);">/ <?= $s['Capacity'] ?></span>
                                    </td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; justify-content: center; gap: 6px;">
                                            <!-- Edit Button -->
                                            <button type="button" class="btn btn-sm btn-secondary" title="Edit Section"
                                                    onclick="openEditSectionModal(<?= htmlspecialchars(json_encode($s)) ?>)">
                                                ✏️
                                            </button>
                                            <!-- Delete Button -->
                                            <a href="sections.php?delete_id=<?= $s['Section_Id'] ?>"
                                               class="btn btn-sm btn-danger" title="Delete Section"
                                               onclick="return confirm('Are you sure you want to delete <?= $s['Course_ID'] ?> Section <?= $s['Section_No'] ?>? Associated enrollments will be removed.')">
                                                🗑️
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ─── MODAL: Edit Section Modal ─────────────────────────────────────────── -->
<div class="modal-overlay" id="edit_section_modal">
    <div class="modal-card" style="max-width: 540px;">
        <div class="modal-header">
            <h3 id="edit_modal_title">✏️ Edit Class Section</h3>
            <button type="button" class="modal-close" onclick="closeModal('edit_section_modal')">&times;</button>
        </div>
        <form action="sections.php" method="POST" id="edit_section_form">
            <input type="hidden" name="section_id" id="edit_sec_id">

            <div class="modal-body" style="padding: 20px 24px;">
                <!-- Course -->
                <div class="form-group">
                    <label for="edit_course_id">Course (*)</label>
                    <select name="course_id" id="edit_course_id" class="form-control" required>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?= $c['Course_ID'] ?>">
                                <?= htmlspecialchars($c['Course_ID'] . ': ' . $c['Course_Title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Section No & Capacity -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label for="edit_section_no">Section No (*)</label>
                        <input type="number" name="section_no" id="edit_section_no" class="form-control" min="1" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_capacity">Capacity (*)</label>
                        <input type="number" name="capacity" id="edit_capacity" class="form-control" min="5" max="100" required>
                    </div>
                </div>

                <!-- Room Dropdown -->
                <div class="form-group">
                    <label for="edit_room_no">Room Number (*)</label>
                    <select name="room_no" id="edit_room_no" class="form-control" required onchange="handleRoomSelectChange(this, 'edit_custom_room_wrap')">
                        <?php foreach ($standardRooms as $building => $rooms): ?>
                            <optgroup label="<?= htmlspecialchars($building) ?>">
                                <?php foreach ($rooms as $code => $label): ?>
                                    <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                        <option value="__custom__">➕ Other / Custom Room...</option>
                    </select>

                    <div id="edit_custom_room_wrap" style="display: none; margin-top: 8px;">
                        <input type="text" name="custom_room_no" id="edit_custom_room_no" class="form-control" placeholder="Enter custom room code">
                    </div>
                </div>

                <!-- Time Slot Dropdown -->
                <div class="form-group">
                    <label for="edit_time_slot">Schedule Time Slot (*)</label>
                    <select name="time_slot" id="edit_time_slot" class="form-control" required onchange="handleTimeSlotChange(this, 'edit_custom_time_wrap')">
                        <?php foreach ($standardTimeSlots as $pattern => $slots): ?>
                            <optgroup label="<?= htmlspecialchars($pattern) ?>">
                                <?php foreach ($slots as $val => $label): ?>
                                    <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                        <option value="__custom__">➕ Other / Custom Time Slot...</option>
                    </select>

                    <div id="edit_custom_time_wrap" style="display: none; margin-top: 8px;">
                        <input type="text" name="custom_time_slot" id="edit_custom_time_slot" class="form-control" placeholder="Enter custom time slot">
                    </div>
                </div>

                <!-- Faculty Dropdown -->
                <div class="form-group">
                    <label for="edit_faculty_id">Faculty Instructor</label>
                    <select name="faculty_id" id="edit_faculty_id" class="form-control">
                        <option value="">-- Unassigned (TBA) --</option>
                        <?php foreach ($facultyList as $fac): ?>
                            <option value="<?= $fac['Faculty_ID'] ?>">
                                <?= htmlspecialchars($fac['First_name'] . ' ' . $fac['Last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="modal-footer" style="padding: 14px 24px; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit_section_modal')">Cancel</button>
                <button type="submit" name="edit_section" class="btn btn-primary">💾 Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
// Master existing course section data for client-side auto-suggestion & validation
const courseExistingSections = <?= json_encode($existingCourseSections) ?>;

// Handle Course change on Add form to auto-suggest next section number
function handleCourseChange(courseId) {
    const secInput = document.getElementById('section_no');
    const hint = document.getElementById('course_sec_hint');
    const feedback = document.getElementById('sec_no_feedback');

    if (!courseId) {
        hint.textContent = '';
        feedback.textContent = '';
        return;
    }

    const existing = courseExistingSections[courseId] || [];
    let nextSec = 1;
    if (existing.length > 0) {
        nextSec = Math.max(...existing) + 1;
        hint.innerHTML = `Existing sections: <strong>Sec ${existing.join(', Sec ')}</strong>. Suggested next: <strong>Sec ${nextSec}</strong>`;
    } else {
        hint.innerHTML = `No existing sections for this course yet. Suggested: <strong>Sec 1</strong>`;
    }

    secInput.value = nextSec;
    validateSectionNoLive();
}

// Live validation for duplicate section number in Add form
function validateSectionNoLive() {
    const courseId = document.getElementById('course_id').value;
    const secNo = parseInt(document.getElementById('section_no').value, 10);
    const feedback = document.getElementById('sec_no_feedback');

    if (!courseId || isNaN(secNo)) {
        feedback.textContent = '';
        return;
    }

    const existing = courseExistingSections[courseId] || [];
    if (existing.includes(secNo)) {
        feedback.innerHTML = `<span style="color:#dc2626; font-weight:600;">⚠️ Section ${secNo} already exists for ${courseId}! Please pick another section number.</span>`;
    } else {
        feedback.innerHTML = `<span style="color:#16a34a; font-weight:600;">✅ Section ${secNo} is unique & available.</span>`;
    }
}

// Toggle custom room input if '__custom__' selected
function handleRoomSelectChange(selectEl, customWrapId) {
    const wrap = document.getElementById(customWrapId);
    if (!wrap) return;
    if (selectEl.value === '__custom__') {
        wrap.style.display = 'block';
        const customInput = wrap.querySelector('input');
        if (customInput) customInput.focus();
    } else {
        wrap.style.display = 'none';
    }
}

// Toggle custom time input if '__custom__' selected
function handleTimeSlotChange(selectEl, customWrapId) {
    const wrap = document.getElementById(customWrapId);
    if (!wrap) return;
    if (selectEl.value === '__custom__') {
        wrap.style.display = 'block';
        const customInput = wrap.querySelector('input');
        if (customInput) customInput.focus();
    } else {
        wrap.style.display = 'none';
    }
}

// Open Edit Section Modal and populate fields
function openEditSectionModal(sec) {
    document.getElementById('edit_sec_id').value = sec.Section_Id;
    document.getElementById('edit_modal_title').textContent = `✏️ Edit ${sec.Course_ID} (Section ${sec.Section_No})`;
    document.getElementById('edit_course_id').value = sec.Course_ID;
    document.getElementById('edit_section_no').value = sec.Section_No;
    document.getElementById('edit_capacity').value = sec.Capacity;
    document.getElementById('edit_faculty_id').value = sec.Faculty_ID || '';

    // Set Room
    const roomSelect = document.getElementById('edit_room_no');
    const customRoomWrap = document.getElementById('edit_custom_room_wrap');
    const customRoomInput = document.getElementById('edit_custom_room_no');
    let roomOptionExists = false;
    for (let opt of roomSelect.options) {
        if (opt.value === sec.Room_No) {
            roomOptionExists = true;
            break;
        }
    }
    if (roomOptionExists) {
        roomSelect.value = sec.Room_No;
        customRoomWrap.style.display = 'none';
    } else {
        roomSelect.value = '__custom__';
        customRoomWrap.style.display = 'block';
        customRoomInput.value = sec.Room_No;
    }

    // Set Time Slot
    const timeSelect = document.getElementById('edit_time_slot');
    const customTimeWrap = document.getElementById('edit_custom_time_wrap');
    const customTimeInput = document.getElementById('edit_custom_time_slot');
    let timeOptionExists = false;
    for (let opt of timeSelect.options) {
        if (opt.value === sec.Time_Slot) {
            timeOptionExists = true;
            break;
        }
    }
    if (timeOptionExists) {
        timeSelect.value = sec.Time_Slot;
        customTimeWrap.style.display = 'none';
    } else {
        timeSelect.value = '__custom__';
        customTimeWrap.style.display = 'block';
        customTimeInput.value = sec.Time_Slot;
    }

    openModal('edit_section_modal');
}

// Table Multi-Dropdown Filtering Engine
function applyTableFilters() {
    const courseVal = document.getElementById('filter_course').value.toLowerCase();
    const roomVal   = document.getElementById('filter_room').value.toLowerCase();
    const timeVal   = document.getElementById('filter_time').value.toLowerCase();
    const searchVal = document.getElementById('sec_filter_search').value.toLowerCase();

    const rows = document.querySelectorAll('#master_sec_table tbody tr');
    rows.forEach(row => {
        const rowCourse = (row.getAttribute('data-course') || '').toLowerCase();
        const rowRoom   = (row.getAttribute('data-room') || '').toLowerCase();
        const rowTime   = (row.getAttribute('data-time') || '').toLowerCase();
        const rowText   = row.textContent.toLowerCase();

        let show = true;
        if (courseVal && rowCourse !== courseVal) show = false;
        if (roomVal && rowRoom !== roomVal) show = false;
        if (timeVal && !rowTime.includes(timeVal)) show = false;
        if (searchVal && !rowText.includes(searchVal)) show = false;

        row.style.display = show ? '' : 'none';
    });
}

function resetTableFilters() {
    document.getElementById('filter_course').value = '';
    document.getElementById('filter_room').value = '';
    document.getElementById('filter_time').value = '';
    document.getElementById('sec_filter_search').value = '';
    applyTableFilters();
}

document.getElementById('sec_filter_search').addEventListener('keyup', applyTableFilters);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
