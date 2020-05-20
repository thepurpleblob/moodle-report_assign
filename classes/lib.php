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
 * This file contains functions used by the participation report
 *
 * @package    report
 * @subpackage assign
 * @copyright  2013-2019 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assign;

defined('MOODLE_INTERNAL') || die;

define('FILENAME_SHORTEN', 30);

//require_once($CFG->dirroot.'/mod/assign/locallib.php');

use \assignfeedback_editpdf\document_services;
use stdClass;

class lib {

    /**
     * get blind assignments for this course
     * @param int $id course id
     * @return array
     */
    public static function get_assignments($id) {
        global $DB;

        $course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($id);
        if (empty($modinfo->instances['assign'])) {
            return [];
        }
        $assigns = $modinfo->instances['assign'];

        // Add plagiarism and feedback status.
        $assignments = [];
        foreach ($assigns as $cm) {
            $context = \context_module::instance($cm->id);
            $assignment = new \assign($context, $cm, $course);
            $instance = $assignment->get_instance();
            $instance->urkundenabled = self::urkund_enabled($instance->id);
            $instance->turnitinenabled = self::turnitin_enabled($instance->id);
            $assignments[$instance->id] = $instance;
        }

        return $assignments;
    }

    /**
     * can the user view the data submitted
     * some checks
     * @param int $assignid assignment id
     * @param array $assignments list of valid assignments
     * @return boolean true if ok
     */
    public static function allowed_to_view($assignid, $assignments) {
        return array_key_exists($assignid, $assignments);
    }

    /**
     * Get the group(s) a user is a member of
     * @param int $userid
     * @param int $courseid
     * @return string
     */
    public static function get_user_groups($userid, $courseid) {
        global $DB;

        $sql = 'SELECT gg.id, userid, courseid, name FROM {groups} gg
            JOIN {groups_members} gm ON (gg.id = gm.groupid)
            WHERE gg.courseid = ?
            AND gm.userid = ?';
        if (!$groups = $DB->get_records_sql($sql, [$courseid, $userid])) {
            return ['-', []];
        } else {
            $names = [];
            $ids = [];
            foreach ($groups as $group) {
                $names[] = shorten_text($group->name, 30);
                $ids[] = $group->id;
            }
            return [implode(', ', $names), $ids];
        }
    }

    /**
     * Can extensions be granted for assignment
     * @param object $assignment
     * @return boolean
     */
    public static function is_extension_allowed($assignment) {
        return ($assignment->get_instance()->duedate ||
                $assignment->get_instance()->cutoffdate) &&
                has_capability('mod/assign:grantextension', $assignment->get_context());
    }

    /**
     * Get Urkund score
     * If multiple scores, get the latest
     * @param int $assid
     * @param int $cmid
     * @param int $userid
     * @return int
     */
    protected static function get_urkund_score($assid, $cmid, $userid) {
        global $DB;

        if (self::urkund_enabled($assid)) {
            if ($urkunds = $DB->get_records('plagiarism_urkund_files',
                ['cm' => $cmid, 'userid' => $userid, 'statuscode' => 'Analyzed'], 'id DESC')) {
                return reset($urkunds);
            }

            // If the submission was "on behalf of"...
            if ($urkunds = $DB->get_records('plagiarism_urkund_files',
                ['cm' => $cmid, 'relateduserid' => $userid, 'statuscode' => 'Analyzed'], 'id DESC')) {
                return reset($urkunds);
            }
            return null;
        } else {
            return null;
        }
    }

    /**
     * Get Turnitin score
     * If multiple scores, get the latest
     * @param int $assid
     * @param int $cmid
     * @param int $userid
     * @return int
     */
    protected static function get_turnitin_score($assid, $cmid, $userid) {
        global $DB;

        if (self::turnitin_enabled($assid)) {
            if ($turnitins = $DB->get_records('plagiarism_turnitin_files',
                ['cm' => $cmid, 'userid' => $userid, 'statuscode' => 'success'], 'id DESC')) {
                return reset($turnitins);
            }
            return null;
        } else {
            return null;
        }
    }

    /**
     * Get file submission plugin
     * and check it is enabled
     * @param object $assign
     * @return mixed
     */
    public static function get_submission_plugin_files($assign) {
        $filesubmission = $assign->get_submission_plugin_by_type('file');
        if ($filesubmission->is_enabled()) {
            return $filesubmission;
        } else {
            return null;
        }
    }

    /**
     * Get files for user
     * @param object $assign
     * @param object $filesubmission
     * @param object $submission
     * @param int $userid
     * @return array
     */
    protected static function get_submission_files($assign, $filesubmission, $submission, $userid) {
        if (!$filesubmission || !$submission) {
            return '-';
        }

        $fs = get_file_storage();
        $context = $assign->get_context();
        $files = $fs->get_area_files(
            $context->id,
            'assignsubmission_file',
            'submission_files',
            $submission->id
        );

        if ($files) {
            $filenames = [];
            foreach ($files as $file) {
                if ($file->get_filename() == '.') {
                    continue;
                }
                $filenames[] = shorten_text($file->get_filename(), FILENAME_SHORTEN);
            }
            return implode(', ', $filenames);
        }

        return '-';
    }

    /**
     * Get feedback
     * @param object $assign
     * @param object $filesubmission
     * @param object $submission
     * @param int $userid
     * @return array
     */
    protected static function get_feedback_files($assign, $filesubmission, $submission, $userid) {
        if (!$filesubmission || !$submission) {
            return '-';
        }

        $fs = get_file_storage();
        $context = $assign->get_context();
        $files = $fs->get_area_files(
            $context->id,
            'assignsubmission_file',
            'submission_files',
            $submission->id
        );

        if ($files) {
            $filenames = [];
            foreach ($files as $file) {
                if ($file->get_filename() == '.') {
                    continue;
                }
                $filenames[] = $file->get_filename();
            }
            return implode(', ', $filenames);
        }

        return '-';
    }

    /**
     * Get the name of the allocated marker
     * @param object $grade
     * @return string
     */
    protected static function get_grader($grade) {
        global $DB;

        if (empty($grade)) {
            return '-';
        }

        if ($grade->grader > 0) {
            if ($user = $DB->get_record('user', ['id' => $grade->grader])) {
                return fullname($user);
            }
        }

        return '-';
    }

    /**
     * Get assign object
     * @param object $course
     * @param int $assignid
     * @return object
     */
    public static function get_assign($course, $assignid) {

        // Get course module.
        $cm = get_coursemodule_from_instance('assign', $assignid);

        // Create assignment object.
        $coursemodulecontext = \context_module::instance($cm->id);
        $assign = new \assign($coursemodulecontext, $cm, $course);

        return $assign;
    }

    /**
     * Add extra profile data
     * @param array $profilefields
     * @param array $submission
     * @return array
     */
    protected static function get_profile_data($profilefields, $submission) {
        $profiledata = [];
        foreach ($profilefields as $field) {
            if (!empty($submission->$field)) {
                $profiledata[] = $submission->$field;
            } else {
                $profiledata[] = '-';
            }
        }

        return $profiledata;
    }

    /**
     * Add files from area to zipfiles list
     * @param array $zipfiles
     * @param string $path
     * @param int $contextid,
     * @param string $component
     * @param string $filearea
     * @param int $itemid
     * @return array
     */
    private static function add_files_zipfiles($zipfiles, $path, $contextid, $component, $filearea, $itemid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $contextid,
            $component,
            $filearea,
            $itemid
        );
        foreach ($files as $file) {
            $filepath = $file->get_filepath();
            $filename = $file->get_filename();
            if ($filename == '.') {
                continue;
            }
            $zipfiles[$path . $filepath . $filename] = $file;
        }

        return $zipfiles;
    }

    /**
     * Fix file/folder names
     * Some characters aren't allowed in Windows
     * @param string $path
     * @return string
     */
    protected static function sanitise_filename($path) {
        $bad = array_merge(
            array_map('chr', range(0,31)),
            array("<", ">", ":", '"', "\\", "|", "?", "*"));
        return str_replace($bad, "_", $path);
    } 

    /**
     * Get feedback
     * @param object $assign
     * @param int $cmid
     * @param array $submissions
     * @param array $params
     */
    public static function get_all_files($assign, $cmid, $submissions, $params) {
        $context = $assign->get_context();
        $instance = $assign->get_instance();
        $fs = get_file_storage();
        $zipfiles = [];

        // Don't make a tar-bomb.
        $folder = self::sanitise_filename(trim(shorten_text($instance->name, 30)));
        $zipfiles[$folder] = null;

        foreach ($submissions as $submission) {
            $userid = $submission->id;

            // Submission.
            if ($instance->teamsubmission) {
                $usersubmission = $assign->get_group_submission($userid, 0, false);
            } else {
                $usersubmission = $assign->get_user_submission($userid, false);
            }

            // Grade.
            $grade = $assign->get_user_grade($userid, true);

            $userid = $submission->id;
            $username = $submission->username;
            $fullname = $submission->fullname;

            // User directory constructed to guarantee uniqueness.
            $userdir = self::sanitise_filename("$folder/$fullname - $username");

            // Each user has a directory.
            $zipfiles[$userdir] = null;

            // If there was no submission then just forget it.
            if (!$usersubmission) {
                continue;
            }

            // Submission.
            if ($params['submissions']) {
                $submissiondir = $userdir . '/submissions';
                $zipfiles[$submissiondir] = null;
                $zipfiles = self::add_files_zipfiles(
                    $zipfiles,
                    $submissiondir,
                    $context->id,
                    'assignsubmission_file',
                    'submission_files',
                    $usersubmission->id
                );
            }

            // Feedback files.
            if ($params['feedbackfiles']) {
                $feedbackdir = $userdir . '/feedbackfiles';
                $zipfiles[$feedbackdir] = null;
                $zipfiles = self::add_files_zipfiles(
                    $zipfiles,
                    $feedbackdir,
                    $context->id,
                    'assignfeedback_file',
                    'feedback_files',
                    $grade->id
                );
                $zipfiles = self::add_files_zipfiles(
                    $zipfiles,
                    $feedbackdir,
                    $context->id,
                    'assignfeedback_file',
                    'feedback_files_batch',
                    $grade->id
                );
                $zipfiles = self::add_files_zipfiles(
                    $zipfiles,
                    $feedbackdir,
                    $context->id,
                    'assignfeedback_file',
                    'feedback_files_import',
                    $grade->id
                );
            }

            if ($params['annotatedpdfs']) {
                $feedbackdir = $userdir . '/annotatedpdfs';
                $zipfiles[$feedbackdir] = null;
                $zipfiles = self::add_files_zipfiles(
                    $zipfiles,
                    $feedbackdir,
                    $context->id,
                    'assignfeedback_editpdf',
                    'download',
                    $grade->id
                );
            }

            if ($params['feedbackcomments']) {
                $feedbackdir = $userdir . '/feedbackcomments';
                $zipfiles[$feedbackdir] = null;
                $plugin = $assign->get_feedback_plugin_by_type('comments');
                $text = $plugin->text_for_gradebook($grade);
                $format = $plugin->format_for_gradebook($grade);
                if ($text) {
                    $formattedtext = format_text($text, $format);
                    $commentfilename = $feedbackdir . '/comments.html';
                    $zipfiles[$commentfilename] = [$formattedtext];
                }
            }
        }

        return $zipfiles;
    }

    /**
     * Get workflow info
     * @param object $userflags
     * @return array [workflow, marker]
     */
    private static function get_workflow($userflags) {
        global $DB;

        $workflow = '-';
        $marker = '-';
        if (!empty($userflags)) {
            $workflow = empty($userflags->workflowstate) ? '-' : $userflags->workflowstate;
            if ($userflags->allocatedmarker) {
                if ($user = $DB->get_record('user', ['id' => $userflags->allocatedmarker])) {
                    $marker = fullname($user);
                }
            }
        }

        return [$workflow, $marker];
    }

    /**
     * Add assignment data
     * @param int $assid
     * @param int $cmid
     * @param assign $assign
     * @param array $submissions
     * @return array
     */
    public static function add_assignment_data($courseid, $assid, $cmid, $assign, $submissions) {

        // Report date format.
        $dateformat = get_string('strftimedatetimeshort', 'langconfig');

        // Get sub plugins.
        $filesubmission = self::get_submission_plugin_files($assign);

        // Get instance.
        $instance = $assign->get_instance();

        // Feedbackplugins.
        $feedbackplugins = $assign->get_feedback_plugins();

        // Profile fields.
        $profileconfig = trim(get_config('report_assign', 'profilefields'));
        $profilefields = empty($profileconfig) ? [] : explode(',', $profileconfig);

        foreach ($submissions as $submission) {
            $userid = $submission->id;
            $submission->assignmentid = $assid;
            $submission->userid = $userid;
            $userflags = $assign->get_user_flags($userid, false);
            list($submission->workflow, $submission->marker) = self::get_workflow($userflags);
            if ($instance->teamsubmission) {
                $usersubmission = $assign->get_group_submission($userid, 0, false);
            } else {
                $usersubmission = $assign->get_user_submission($userid, false);
            }
            if ($submission->grantedextension && !empty($userflags)) {
                $submission->extensionduedate = userdate($userflags->extensionduedate, $dateformat);
            } else {
                $submission->extensionduedate = '-';
            }
            if ($usersubmission) {
                $submission->created = userdate($usersubmission->timecreated, $dateformat);
                $submission->modified = userdate($usersubmission->timemodified, $dateformat);
                $submission->status = $usersubmission->status;
                $submissionid = $usersubmission->id;
                $grade = $assign->get_user_grade($userid, false);
                $gradevalue = empty($grade) ? null : $grade->grade;
                $displaygrade = $assign->display_grade($gradevalue, false, $userid);
                $submission->grade = $displaygrade;
                $submission->grader = self::get_grader($grade);
            } else {
                $submission->created = '-';
                $submission->modified = '-';
                $submission->status = '-';
                $submission->grade = '-';
                $submission->grader = '-';
            }
            $submission->participantno = empty($submission->recordid) ? '-' : $submission->recordid;
            list($submission->groups, $submission->groupids) = self::get_user_groups($userid, $courseid);
            $submission->urkund = self::get_urkund_score($assid, $cmid, $userid);
            $submission->turnitin = self::get_turnitin_score($assid, $cmid, $userid);
            $submission->files = self::get_submission_files($assign, $filesubmission, $usersubmission, $userid);
            $submission->profiledata = self::get_profile_data($profilefields, $submission);
            $submission->isprofiledata = count($profilefields) != 0;

            // User fields.
            $profilefields = explode(',', get_config('report_assign', 'profilefields'));
        }

        return $submissions;
    }

    /**
     * This function will take an int or an assignment instance and
     * return an assignment instance. It is just for convenience.
     * Stolen from private function in assignment code
     * @param int|\assign $assignment
     * @return assign
     */
    private static function get_assignment_from_param($assignment) {
        global $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        if (!is_object($assignment)) {
            $cm = \get_coursemodule_from_instance('assign', $assignment, 0, false, MUST_EXIST);
            $context = \context_module::instance($cm->id);

            $assignment = new \assign($context, null, null);
        }
        return $assignment;
    }

    /**
     * Is Urkund enabled on this assignment
     * @param int $assignmentid
     * @return boolean
     */
    public static function urkund_enabled($assignmentid) {
        global $CFG, $DB;

        // Is plagiarism enabled?
        if (empty($CFG->enableplagiarism)) {
            return false;
        }

        // Is the Urkund plugin installed?
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('plagiarism_urkund');
        if (!$plugininfo) {
            return false;
        }

        $cm = get_coursemodule_from_instance('assign', $assignmentid);
        if ($urkund = $DB->get_record('plagiarism_urkund_config', ['cm' => $cm->id, 'name' => 'use_urkund'])) {
            if ($urkund->value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is Turnitin enabled on this assignment
     * @param int $assignmentid
     * @return boolean
     */
    public static function turnitin_enabled($assignmentid) {
        global $CFG, $DB;

        // Is plagiarism enabled?
        if (empty($CFG->enableplagiarism)) {
            return false;
        }

        // Is the Urkund plugin installed?
        $plugininfo = \core_plugin_manager::instance()->get_plugin_info('plagiarism_turnitin');
        if (!$plugininfo) {
            return false;
        }

        $cm = get_coursemodule_from_instance('assign', $assignmentid);
        if ($turnitin = $DB->get_record('plagiarism_turnitin_config', ['cm' => $cm->id, 'name' => 'use_turnitin'])) {
            if ($turnitin->value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Export assignment data
     * @param object $assignment
     * @param string $filename
     * @param array $submissions
     */
    public static function export($assignment, $filename, $submissions) {
        global $CFG;
        require_once($CFG->dirroot.'/lib/excellib.class.php');

        // Profile fields.
        $profilefields = [];
        $fields = get_config('report_assign', 'profilefields');
        if ($fields != '') {
            $profilefields = explode(',', $fields);
        }

        // Group mode?
        $cm = get_coursemodule_from_instance('assign', $assignment->id);
        $groupmode = $cm->groupmode;

        $workbook = new \MoodleExcelWorkbook("-");
        // Sending HTTP headers.
        $workbook->send($filename);

        // Adding the worksheet.
        $myxls = $workbook->add_worksheet(get_string('workbook', 'report_assign'));

        // Titles.
        $myxls->write_string(0, 0, get_string('assignmentname', 'report_assign'));
        $myxls->write_string(0, 1, $assignment->name);

        // Headers.
        $i = 0;
        $myxls->write_string(3, $i++, '#');
        $myxls->write_string(3, $i++, get_string('username'));
        $myxls->write_string(3, $i++, get_string('participantno', 'report_assign'));
        foreach ($profilefields as $profilefield) {
            $myxls->write_string(3, $i++, get_string($profilefield));
        }
        if ($groupmode) {
            $myxls->write_string(3, $i++, get_string('groups'));
        }
        $myxls->write_string(3, $i++, get_string('status'));
        $myxls->write_string(3, $i++, get_string('grade'));
        if ($urkundenabled = self::urkund_enabled($assignment->id)) {
            $myxls->write_string(3, $i++, get_string('urkund', 'report_assign'));
        }
        if ($turnitinenabled = self::turnitin_enabled($assignment->id)) {
            $myxls->write_string(3, $i++, get_string('turnitin', 'report_assign'));
        }
        if ($assignment->markingallocation) {
            $myxls->write_string(3, $i++, get_string('workflow', 'report_assign'));
            $myxls->write_string(3, $i++, get_string('allocatedmarker', 'report_assign'));
        }
        $myxls->write_string(3, $i++, get_string('grader', 'report_assign'));
        $myxls->write_string(3, $i++, get_string('modified'));
        $myxls->write_string(3, $i++, get_string('extension', 'report_assign'));
        $myxls->write_string(3, $i++, get_string('files'));

        // Add some data.
        $row = 4;
        $linecount = 1;
        foreach ($submissions as $s) {
            $i = 0;
            $myxls->write_number($row, $i++, $linecount++);
            $myxls->write_string($row, $i++, $s->fullname);
            $myxls->write_string($row, $i++, $s->participantno);
            if ($fields != '') {
                foreach ($s->profiledata as $value) {
                    $myxls->write_string($row, $i++, $value);
                }
            }
            if ($groupmode) {
                $myxls->write_string($row, $i++, $s->groups);
            }
            $myxls->write_string($row, $i++, $s->status);
            $myxls->write_string($row, $i++, html_entity_decode($s->grade));
            if ($urkundenabled) {
                $urkundscore = empty($s->urkund->similarityscore) ? '-' : $s->urkund->similarityscore;
                $myxls->write_string($row, $i++, $urkundscore);
            }
            if ($turnitinenabled) {
                $turnitinscore = empty($s->turnitin->similarityscore) ? '-' : $s->turnitin->similarityscore;
                $myxls->write_string($row, $i++, $turnitinscore);
            }
            if ($assignment->markingallocation) {
                $myxls->write_string($row, $i++, $s->workflow);
                $myxls->write_string($row, $i++, $s->marker);
            }
            $myxls->write_string($row, $i++, $s->grader);
            $myxls->write_string($row, $i++, $s->modified);
            $myxls->write_string($row, $i++, $s->extensionduedate);
            $myxls->write_string($row, $i++, $s->files);
            $row++;
        }
        $workbook->close();
    }

    /**
     * Export all assignment data
     * @param string $filename
     * @param array $submissions
     */
    public static function exportall($filename, $submissions) {
        global $CFG;
        require_once($CFG->dirroot.'/lib/excellib.class.php');

        // Profile fields.
        $fields = get_config('report_assign', 'profilefields');
        $profilefields = explode(',', $fields);

        // Plagiarism plugins?
        $isturnitin = !empty(\core_plugin_manager::instance()->get_plugin_info('plagiarism_turnitin'));
        $isurkund = !empty(\core_plugin_manager::instance()->get_plugin_info('plagiarism_urkund'));

        $workbook = new \MoodleExcelWorkbook("-");

        // Sending HTTP headers.
        $workbook->send($filename);

        // Adding the worksheet.
        $myxls = $workbook->add_worksheet(get_string('workbook', 'report_assign'));

        // Headers.
        $i = 0;
        $myxls->write_string(1, $i++, '#');
        $myxls->write_string(1, $i++, get_string('assignmentname', 'report_assign'));
        $myxls->write_string(1, $i++, get_string('username'));
        $myxls->write_string(1, $i++, get_string('participantno', 'report_assign'));
        if ($fields != '') {
            foreach ($profilefields as $profilefield) {
                $myxls->write_string(1, $i++, get_string($profilefield));
            }
        }
        $myxls->write_string(1, $i++, get_string('groups'));
        $myxls->write_string(1, $i++, get_string('status'));
        $myxls->write_string(1, $i++, get_string('grade'));
        if ($isurkund) {
            $myxls->write_string(1, $i++, get_string('urkund', 'report_assign'));
        }
        if ($isturnitin) {
            $myxls->write_string(1, $i++, get_string('turnitin', 'report_assign'));
        }
        $myxls->write_string(1, $i++, get_string('allocatedmarker', 'report_assign'));
        $myxls->write_string(1, $i++, get_string('modified'));
        $myxls->write_string(1, $i++, get_string('duedate', 'report_assign'));
        $myxls->write_string(1, $i++, get_string('extension', 'report_assign'));
        $myxls->write_string(1, $i++, get_string('files'));

        // Add some data.
        $row = 2;
        $linecount = 1;
        foreach ($submissions as $s) {
            $i = 0;
            $myxls->write_number($row, $i++, $linecount++);
            $myxls->write_string($row, $i++, $s->assignmentname);
            $myxls->write_string($row, $i++, $s->fullname);
            $myxls->write_string($row, $i++, $s->participantno);
            if ($fields != '') {
                foreach ($s->profiledata as $value) {
                    $myxls->write_string($row, $i++, $value);
                }
            }
            $myxls->write_string($row, $i++, isset($s->groups) ? $s->groups : '-');
            $myxls->write_string($row, $i++, $s->status);
            $myxls->write_string($row, $i++, html_entity_decode($s->grade));
            if ($isurkund) {
                $myxls->write_string($row, $i++, isset($s->urkund->similarityscore) ? $s->urkund->similarityscore : '-');
            }
            if ($isturnitin) {
                $myxls->write_string($row, $i++, isset($s->turnitin->similarityscore) ? $s->turnitin->similarityscore : '-');
            }
            $myxls->write_string($row, $i++, isset($s->grader) ? $s->grader : '-');
            $myxls->write_string($row, $i++, $s->modified);
            $myxls->write_string($row, $i++, $s->duedate);
            $myxls->write_string($row, $i++, $s->extensionduedate);
            $myxls->write_string($row, $i++, $s->files);
            $row++;
        }
        $workbook->close();
    }

    /**
     * Create filename for zipfile
     * @param int $assignid
     * @return string zip file name
     */
    private static function get_zipfilename($assignid) {
        global $DB;

        // Get assignment and course.
        $assign = $DB->get_record('assign', array('id' => $assignid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $assign->course), '*', MUST_EXIST);

        // Construct out of bits.
        $zipfilename = clean_filename(implode('_', array(
            $course->shortname,
            $assign->name,
        )));

        return $zipfilename . '.zip';
    }

    /**
     * Generate zip file from array of given files.
     *
     * @param array $filesforzipping - array of files to pass into archive_to_pathname.
     *                                 This array is indexed by the final file name and each
     *                                 element in the array is an instance of a stored_file object.
     * @return path of temp file - note this returned file does
     *         not have a .zip extension - it is a temp file.
     */
    private static function pack_files($filesforzipping) {
        global $CFG;

        // Create path for new zip file.
        $tempzip = tempnam($CFG->tempdir . '/', 'assignment_');

        // Zip files.
        $zipper = new \zip_packer();
        if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
            return $tempzip;
        }
        return false;
    }

    /**
     * Download feedback files
     * @param array $files;
     * @param int assignid
     */
    public static function download_feedback_files($files, $assignid) {
        global $DB;

        // Pack zip file for export.
        if ($zip = self::pack_files($files)) {
            $zipfilename = self::get_zipfilename($assignid);
            send_temp_file($zip, $zipfilename);
        }
    }

}
