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
 * @copyright  2019 Howard Miller (howardsmiller@gmail.com)
 * @package    report
 * @subpackage assign
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(dirname(__FILE__).'/../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

// Parameters.
$id = required_param('id', PARAM_INT);
$assignid = required_param('assign', PARAM_INT);

$url = new moodle_url('/report/assign/dump.php', ['id' => $id, 'assign' => $assignid]);

// Page setup.
$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);
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

// Select form.
$form = new report_assign\forms\dumpselect(null, [
    'id' => $id,
    'assign' => $assignid,
    'assignment' => $assignment,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/report/assign/index.php', ['id' => $id]));
} else if ($data = $form->get_data()) {
    $params = [
        'id' => $id,
        'assign' => $assignid,
        'dump' => 1,
        'feedbackfiles' => $data->feedbackfiles,
        'feedbackcomments' => $data->feedbackcomments,
        'annotatedpdfs' => $data->annotatedpdfs,
        'submissions' => $data->submissions,
    ];
    $url = new moodle_url('/report/assign/index.php', $params);
    redirect($url);
}

echo $output->header();
$form->display();
echo $output->footer();


