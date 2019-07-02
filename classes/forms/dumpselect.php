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
 * Assignment report - file dump select form
 *
 * @copyright  2019 Howard Miller (howardsmiller@gmail.com)
 * @package    report
 * @subpackage assign
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assign\forms;

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/formslib.php");

class dumpselect extends \moodleform {

    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // Hidden stuff.
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'assign', $this->_customdata['assign']);
        $mform->setType('assign', PARAM_INT);

        // Info.
        $mform->addElement('html', '<h3>' . $this->_customdata['assignment']->name . '</h3>');
        $mform->addElement('html', '<div class="alert alert-primary">' .
            get_string('dumpformhelp', 'report_assign') . '</div>');

        // Select.
        $choices = [
            'submissions' => get_string('submissions', 'report_assign'),
            'feedbackfiles' => get_string('feedbackfiles', 'report_assign'),
            'feedbackcomments' => get_string('feedbackcomments', 'report_assign'),
            'annotatedpdfs' => get_string('annotatedpdfs', 'report_assign'),
        ];
        foreach ($choices as $choice => $label) {
            $checkbox = $mform->addElement('advcheckbox', $choice, $label);
            $checkbox->setValue(1);
        }

        $this->add_action_buttons(true, get_string('dumpfiles', 'report_assign'));

    }
}