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
 * External report_assign API
 *
 * @package    report_assign
 * @copyright  2020 Howard Miller
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;   

/**
 * Assign functions
 * @copyright 2012 Paul Charsley
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report_assign_external extends external_api {

    /**
     * Describes the parameters for get_user_flags
     * @return external_function_parameters
     */
    public static function get_user_flags_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'Assignment instance id'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
            )
        );
    }

    /**
     * Returns user flag information from assign_user_flags for the requested assignment ids
     * @param int $assignmentid
     * @param int $userid
     * @return array flags
     */
    public static function get_user_flags($assignmentid, $userid) {
        global $DB;
        $params = self::validate_parameters(self::get_user_flags_parameters(),
                        ['assignmentid' => $assignmentid, 'userid' => $userid]);

        $userflag = [];
        if ($flags = $DB->get_record('assign_user_flags', ['userid' => $userid, 'assignment' => $assignmentid])) {     
            $userflag['id'] = $flags->id;
            $userflag['userid'] = $flags->userid;
            $userflag['locked'] = $flags->locked;
            $userflag['mailed'] = $flags->mailed;
            $userflag['extensionduedate'] = $flags->extensionduedate;
            $userflag['workflowstate'] = $flags->workflowstate;
            $userflag['allocatedmarker'] = $flags->allocatedmarker;            
        } else {
            $userflag['id'] = 0;
            $userflag['userid'] = 0;
            $userflag['locked'] = 0;
            $userflag['mailed'] = 0;
            $userflag['extensionduedate'] = 0;
            $userflag['workflowstate'] = '';
            $userflag['allocatedmarker'] = 0; 
        }
        error_log('UF: HERE' . print_r($userflag, true) );

        return $userflag;
    }

    /**
     * Describes the get_user_flags return value
     * @return external_single_structure
     * @since  Moodle 2.6
     */
    public static function get_user_flags_returns() {
        return new external_single_structure(
                    array(
                        'id'               => new external_value(PARAM_INT, 'user flag id'),
                        'userid'           => new external_value(PARAM_INT, 'student id'),
                        'locked'           => new external_value(PARAM_INT, 'locked'),
                        'mailed'           => new external_value(PARAM_INT, 'mailed'),
                        'extensionduedate' => new external_value(PARAM_INT, 'extension due date'),
                        'workflowstate'    => new external_value(PARAM_ALPHA, 'marking workflow state', VALUE_OPTIONAL),
                        'allocatedmarker'  => new external_value(PARAM_INT, 'allocated marker')
                    )
        );
    }

    /**
     * Describes the parameters for save_user_extensions
     * @return external_function_parameters
     */
    public static function save_user_extension_parameters() {
        return new external_function_parameters(
            array(
                'assignmentid' => new external_value(PARAM_INT, 'The assignment id to operate on'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'date' => new external_value(PARAM_INT, 'Extension date (timestamp)')
            )
        );
    }

    /**
     * Grant extension dates to students for an assignment.
     *
     * @param int $assignmentid The id of the assignment
     * @param int $userid
     * @param int $date timestamp
     * @return string new user date
     */
    public static function save_user_extension($assignmentid, $userid, $date) {
        global $CFG, $DB;

        $params = self::validate_parameters(self::save_user_extension_parameters(),
                        array('assignmentid' => $assignmentid,
                              'userid' => $userid,
                              'date' => $date));

        if (!$flags = $DB->get_record('assign_user_flags', ['userid' => $userid, 'assignment' => $assignmentid])) {
            $flags = new stdClass();
            $flags->assignment = $assignmentid;
            $flags->userid = $userid;
            $flags->locked = 0;
            $flags->extensionduedate = $date;
            $flags->workflowstate = '';
            $flags->allocatedmarker = 0;

            // The mailed flag can be one of 3 values: 0 is unsent, 1 is sent and 2 is do not send yet.
            // This is because students only want to be notified about certain types of update (grades and feedback).
            $flags->mailed = 2;

            $fid = $DB->insert_record('assign_user_flags', $flags);
        } else {
            $flags->extensionduedate = $date;
            $DB->update_record('assign_user_flags', $flags);
        }

        $dateformat = get_string('strftimedatetimeshort', 'langconfig');
        return userdate($date, $dateformat);
    }

    /**
     * Describes the return value for save_user_extensions
     *
     * @return external_value
     */
    public static function save_user_extension_returns() {
        return new external_value(PARAM_TEXT, 'user date');
    }

}