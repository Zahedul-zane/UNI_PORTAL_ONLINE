<?php
/**
 * East West University - Official Grading Policy & GPA System
 * Based on official EWU Grading Policy:
 * 
 * -------------------------------------------------------------
 * Numerical Scores        | Letter Grade | Grade Point | Evaluation
 * -------------------------------------------------------------
 * 80% and above           | A+           | 4.00        | Outstanding
 * 75% to less than 80%    | A            | 3.75        | Excellent
 * 70% to less than 75%    | A-           | 3.50        | Very Good
 * 65% to less than 70%    | B+           | 3.25        | Good
 * 60% to less than 65%    | B            | 3.00        | Satisfactory
 * 55% to less than 60%    | B-           | 2.75        | Above Average
 * 50% to less than 55%    | C+           | 2.50        | Average
 * 45% to less than 50%    | C            | 2.25        | Pass
 * 40% to less than 45%    | D            | 2.00        | Poor Pass
 * Less than 40%           | F            | 0.00        | Fail
 * -------------------------------------------------------------
 */

// Official EWU Grade Point Scale Map
if (!defined('EWU_GRADE_SCALE')) {
    define('EWU_GRADE_SCALE', [
        'A+' => 4.00,
        'A'  => 3.75,
        'A-' => 3.50,
        'B+' => 3.25,
        'B'  => 3.00,
        'B-' => 2.75,
        'C+' => 2.50,
        'C'  => 2.25,
        'D'  => 2.00,
        'F'  => 0.00
    ]);
}

/**
 * Returns all valid official letter grades in descending rank order
 *
 * @return array
 */
function ewu_get_all_grades() {
    return ['A+', 'A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'F'];
}

/**
 * Full grading scale metadata array for UI tables and documentation
 *
 * @return array
 */
function ewu_get_grading_policy_tiers() {
    return [
        ['score_range' => '80% and above',          'min' => 80.0, 'max' => 100.0, 'grade' => 'A+', 'point' => 4.00, 'evaluation' => 'Outstanding',   'badge' => 'badge-success'],
        ['score_range' => '75% to less than 80%',   'min' => 75.0, 'max' => 79.99, 'grade' => 'A',  'point' => 3.75, 'evaluation' => 'Excellent',     'badge' => 'badge-success'],
        ['score_range' => '70% to less than 75%',   'min' => 70.0, 'max' => 74.99, 'grade' => 'A-', 'point' => 3.50, 'evaluation' => 'Very Good',      'badge' => 'badge-success'],
        ['score_range' => '65% to less than 70%',   'min' => 65.0, 'max' => 69.99, 'grade' => 'B+', 'point' => 3.25, 'evaluation' => 'Good',           'badge' => 'badge-primary'],
        ['score_range' => '60% to less than 65%',   'min' => 60.0, 'max' => 64.99, 'grade' => 'B',  'point' => 3.00, 'evaluation' => 'Satisfactory',   'badge' => 'badge-primary'],
        ['score_range' => '55% to less than 60%',   'min' => 55.0, 'max' => 59.99, 'grade' => 'B-', 'point' => 2.75, 'evaluation' => 'Above Average', 'badge' => 'badge-primary'],
        ['score_range' => '50% to less than 55%',   'min' => 50.0, 'max' => 54.99, 'grade' => 'C+', 'point' => 2.50, 'evaluation' => 'Average',       'badge' => 'badge-gold'],
        ['score_range' => '45% to less than 50%',   'min' => 45.0, 'max' => 49.99, 'grade' => 'C',  'point' => 2.25, 'evaluation' => 'Pass',          'badge' => 'badge-gold'],
        ['score_range' => '40% to less than 45%',   'min' => 40.0, 'max' => 44.99, 'grade' => 'D',  'point' => 2.00, 'evaluation' => 'Poor Pass',     'badge' => 'badge-warning'],
        ['score_range' => 'Less than 40%',          'min' => 0.0,  'max' => 39.99, 'grade' => 'F',  'point' => 0.00, 'evaluation' => 'Fail',          'badge' => 'badge-danger'],
    ];
}

/**
 * Calculates letter grade, grade point, and evaluation for a given numerical score (0 - 100)
 *
 * @param float|int|null $score Total percentage score out of 100
 * @return array ['grade' => string, 'point' => float|null, 'evaluation' => string, 'badge' => string]
 */
function ewu_calculate_grade($score) {
    if ($score === null || $score === '') {
        return [
            'grade'      => 'N/A',
            'point'      => null,
            'evaluation' => 'Ungraded / In Progress',
            'badge'      => 'badge-secondary'
        ];
    }

    $score = floatval($score);

    if ($score >= 80.0) {
        return ['grade' => 'A+', 'point' => 4.00, 'evaluation' => 'Outstanding',   'badge' => 'badge-success'];
    } elseif ($score >= 75.0) {
        return ['grade' => 'A',  'point' => 3.75, 'evaluation' => 'Excellent',     'badge' => 'badge-success'];
    } elseif ($score >= 70.0) {
        return ['grade' => 'A-', 'point' => 3.50, 'evaluation' => 'Very Good',      'badge' => 'badge-success'];
    } elseif ($score >= 65.0) {
        return ['grade' => 'B+', 'point' => 3.25, 'evaluation' => 'Good',           'badge' => 'badge-primary'];
    } elseif ($score >= 60.0) {
        return ['grade' => 'B',  'point' => 3.00, 'evaluation' => 'Satisfactory',   'badge' => 'badge-primary'];
    } elseif ($score >= 55.0) {
        return ['grade' => 'B-', 'point' => 2.75, 'evaluation' => 'Above Average', 'badge' => 'badge-primary'];
    } elseif ($score >= 50.0) {
        return ['grade' => 'C+', 'point' => 2.50, 'evaluation' => 'Average',       'badge' => 'badge-gold'];
    } elseif ($score >= 45.0) {
        return ['grade' => 'C',  'point' => 2.25, 'evaluation' => 'Pass',          'badge' => 'badge-gold'];
    } elseif ($score >= 40.0) {
        return ['grade' => 'D',  'point' => 2.00, 'evaluation' => 'Poor Pass',     'badge' => 'badge-warning'];
    } else {
        return ['grade' => 'F',  'point' => 0.00, 'evaluation' => 'Fail',          'badge' => 'badge-danger'];
    }
}

/**
 * Returns Grade Point for an official Letter Grade
 *
 * @param string|null $grade
 * @return float|null
 */
function ewu_get_grade_point($grade) {
    if ($grade === null || $grade === '' || $grade === 'N/A') {
        return null;
    }
    $grade = strtoupper(trim((string)$grade));
    $scale = EWU_GRADE_SCALE;
    return $scale[$grade] ?? null;
}

/**
 * Returns CSS badge class for a letter grade
 *
 * @param string|null $grade
 * @return string
 */
function ewu_get_grade_badge_class($grade) {
    $grade = strtoupper(trim((string)$grade));
    if (in_array($grade, ['A+', 'A', 'A-'])) return 'badge-success';
    if (in_array($grade, ['B+', 'B', 'B-'])) return 'badge-primary';
    if (in_array($grade, ['C+', 'C']))       return 'badge-gold';
    if ($grade === 'D')                      return 'badge-warning';
    if ($grade === 'F')                      return 'badge-danger';
    return 'badge-secondary';
}

/**
 * Helper to compute standing and badge based on SGPA / CGPA
 *
 * @param float|string $gpa
 * @return array ['standing' => string, 'badge' => string]
 */
function ewu_get_academic_standing($gpa) {
    if ($gpa === null || $gpa === '' || $gpa === 'N/A') {
        return ['standing' => 'Term In Progress ⏳', 'badge' => 'badge-secondary'];
    }
    $val = floatval($gpa);
    if ($val >= 3.75) {
        return ['standing' => "Dean's Honor List ⭐", 'badge' => 'badge-success'];
    } elseif ($val >= 3.50) {
        return ['standing' => "Distinction 🌟", 'badge' => 'badge-gold'];
    } elseif ($val >= 3.00) {
        return ['standing' => "Good Standing ✅", 'badge' => 'badge-info'];
    } elseif ($val >= 2.00) {
        return ['standing' => "Satisfactory 👍", 'badge' => 'badge-warning'];
    } else {
        return ['standing' => "Academic Warning ⚠️", 'badge' => 'badge-danger'];
    }
}
