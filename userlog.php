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
 * Assign report - file dump
 *
 * @copyright  2021 Howard Miller (howardsmiller@gmail.com)
 * @package    report
 * @subpackage assign
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');

// Parameters.
$userid = required_param('userid', PARAM_INT);
$assignid = required_param('assignid', PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);
$fullname = required_param('fullname', PARAM_NOTAGS);

$url = new moodle_url('/report/assign/userlog.php', ['userid' => $userid, 'courseid' => $courseid, 'assignid' => $assignid, 'cmid' => $cmid]);

// Page setup.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$PAGE->set_url($url);
$PAGE->set_pagelayout('report');
$PAGE->set_title($course->shortname .': '. get_string('pluginname', 'report_assign'));
$PAGE->set_heading($course->fullname);

// Security.
$output = $PAGE->get_renderer('report_assign');
$context = context_course::instance($course->id);
require_capability('mod/assign:grade', $context);
require_capability('report/assign:view', $context);

$assignment = $DB->get_record('assign', ['id' => $assignid], '*', MUST_EXIST);

// log events that we want to show
// event_name_in_log_table => string name in mod_assign
$eventfilter = [
    '\mod_assign\event\assessable_submitted' => 'eventassessablesubmitted',
    '\mod_assign\event\remove_submission_form_viewed' => 'eventremovesubmissionformviewed',
    '\mod_assign\event\statement_accepted' => 'eventstatementaccepted',
    '\mod_assign\event\submission_form_viewed' => 'eventsubmissionformviewed',
    '\mod_assign\event\submission_viewed' => 'eventsubmissionviewed',
];

$dateformat = get_string('strftimedatetimeshort', 'langconfig');
list($insql, $params) = $DB->get_in_or_equal(array_keys($eventfilter), SQL_PARAMS_NAMED);
$sql = 'SELECT * FROM {logstore_standard_log}
    WHERE (userid = :userid OR relateduserid = :relateduserid) 
    AND contextinstanceid = :cmid
    AND eventname ' . $insql . '
    ORDER BY timecreated DESC';
$params['userid'] = $userid;
$params['relateduserid'] = $userid;
$params['cmid'] = $cmid;
$logs = $DB->get_records_sql($sql, $params);

foreach ($logs as $log) {
    $log->selfaction = $log->userid == $userid;
    $log->byuser = '-';
    if (!$log->selfaction) {
        $user = $DB->get_record('user', ['id' => $log->userid], '*', MUST_EXIST);
        $log->byuser = fullname($user);
    }
    $log->logtime = userdate($log->timecreated, $dateformat);
    $log->description = get_string($eventfilter[$log->eventname], 'mod_assign');
}

$backlink = new moodle_url('/report/assign/index.php',[
    'id' => $courseid,
    'assign' => $assignid,
]);
$templatecontext = (object)[
    'fullname' => $fullname,
    'anylogs' => !count($logs) == 0,
    'logs' => array_values($logs),
    'back' => $backlink,
];


echo $output->header();
echo $output->render_from_template('report_assign/userlog', $templatecontext);
echo $output->footer();