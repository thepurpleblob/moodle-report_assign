<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Assignment report
 *
 * @copyright  2019 Howard Miller (howardsmiller@gmail.com)
 * @package    report
 * @subpackage assign
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('REPORT_PAGESIZE', 20);

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

// Parameters.
$id = required_param('id', PARAM_INT);
$assignid = optional_param('assign', 0, PARAM_INT);
$export = optional_param('export', 0, PARAM_INT);
$exportall = optional_param('exportall', 0, PARAM_INT);
$group = optional_param('group', 0, PARAM_INT);
$dump = optional_param('dump', 0, PARAM_INT);

$url = new moodle_url('/report/assign/index.php', array('id' => $id));
$fullurl = new moodle_url('/report/assign/index.php', array(
    'id' => $id,
    'assign' => $assignid,
    'group' => $group,
));

// Page setup.
$PAGE->set_url($fullurl);
$PAGE->set_pagelayout('report');

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

// Security.
require_login($course);
$output = $PAGE->get_renderer('report_assign');
$context = context_course::instance($course->id);
require_capability('mod/assign:grade', $context);
require_capability('report/assign:view', $context);

if (!$export && !$exportall && !$dump) {
    $PAGE->set_title($course->shortname .': '. get_string('pluginname', 'report_assign'));
    $PAGE->set_heading($course->fullname);

    echo $output->header();
}

// Get assignments.
$assignments = report_assign\lib::get_assignments($id);

// Export all?
if ($exportall) {
    $filename = "assign_{$course->shortname}.xls";

    // Combine data.
    $allsubmissions = [];
    foreach ($assignments as $assignid => $assignment) {
        set_time_limit(60);
        if (!report_assign\lib::allowed_to_view($assignid, $assignments)) {
            continue;
        }
        $assign = \report_assign\lib::get_assign($course, $assignid);
        //$assignment = $DB->get_record('assign', ['id' => $assignid], '*', MUST_EXIST);
        $submissions = $assign->list_participants_with_filter_status_and_group(0);
        $cm = get_coursemodule_from_instance('assign', $assignid);
        $submissions = report_assign\lib::add_assignment_data($course->id, $assignid, $cm->id, $assign, $submissions);
        foreach ($submissions as $submission) {
            $submission->assignmentname = $assignment->name;
            $submission->duedate = empty($assignment->duedate) ? '-' : userdate($assignment->duedate, get_string('strftimedatetimeshort', 'langconfig'));
        }
        $allsubmissions = array_merge($allsubmissions, $submissions);
    }

    report_assign\lib::exportall($filename, $allsubmissions);
    die;
}

// Has a link been submitted?
if ($assignid) {

    // Create assignment object.
    $assign = \report_assign\lib::get_assign($course, $assignid);

    // Javascript
    $PAGE->requires->js_call_amd('report_assign/filter', 'init', [$assign->get_instance()->duedate]);

    // Participants.
    $submissions = $assign->list_participants_with_filter_status_and_group($group);
    $cm = get_coursemodule_from_instance('assign', $assignid);
    $submissions = report_assign\lib::add_assignment_data($course->id, $assignid, $cm->id, $assign, $submissions);

    // Data dump.
    if ($dump) {
        $params = [
            'submissions' => optional_param('submissions', 0, PARAM_INT),
            'feedbackfiles' => optional_param('feedbackfiles', 0, PARAM_INT),
            'feedbackcomments' => optional_param('feedbackcomments', 0, PARAM_INT),
            'annotatedpdfs' => optional_param('annotatedpdfs', 0, PARAM_INT),
        ];
        $files = report_assign\lib::get_all_files($assign, $cm->id, $submissions, $params);

        // Trigger an assignment viewed event.
        $event = \report_assign\event\assignment_dump::create([
            'context' => $context,
            'objectid' => $assignid,
        ]);
        $event->trigger();

        report_assign\lib::download_feedback_files($files, $assignid);
        die;
    }

    // Group mode.
    $groupmode = groups_get_activity_groupmode($cm, $course);

    if (!report_assign\lib::allowed_to_view($assignid, $assignments)) {
        notice(get_string('notallowed', 'report_assign'), $url);
    }

    $assignment = $DB->get_record('assign', ['id' => $assignid], '*', MUST_EXIST);
    $urkund = report_assign\lib::urkund_enabled($assignid);
    $turnitin = report_assign\lib::turnitin_enabled($assignid);

    // Allocate ids if required.
    if ($assignment->blindmarking) {
        assign::allocate_unique_ids($assignid);
    }

    if ($export) {
        $filename = "assign_{$assignment->name}.xls";
        report_assign\lib::export($assignment, $filename, $submissions);

        // Trigger an assignment viewed event.
        $event = \report_assign\event\assignment_export::create([
            'context' => $context,
            'objectid' => $assignid,
        ]);
        $event->trigger();

        die;
    }

    // Display report.
    $showparticipantnumber = $assignment->blindmarking && !$assign->is_blind_marking();
    $extensionsok = report_assign\lib::is_extension_allowed($assign);
    $reportassign = new report_assign\output\reportassign($course, $context, $fullurl, $submissions, $assignment, $showparticipantnumber, $extensionsok);
    echo $output->render_reportassign($reportassign);

    // Trigger an assignment viewed event.
    $event = \report_assign\event\assignment_viewed::create([
        'context' => $context,
        'objectid' => $assignid,
    ]);
    $event->trigger();
} else {

    // List of activities to select.
    $listassign = new report_assign\output\listassign($course, $fullurl, $assignments);
    echo $output->render_listassign($listassign);

    // Trigger a report viewed event.
    $event = \report_assign\event\report_viewed::create(['context' => $context]);
    $event->trigger();
}

echo $output->footer();


