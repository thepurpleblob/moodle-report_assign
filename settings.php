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
 * Settings
 *
 * @package    report
 * @subpackage assign
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {

    $settings = new admin_settingpage('report_assign_settings', new lang_string('pluginname', 'report_assign'));

    $rawchoices = [
        'email',
        'idnumber',
        'phone1',
        'phone2',
        'institution',
        'department',
        'address',
        'country',
        'lastip',
    ];
    $choices = [];
    foreach ($rawchoices as $choice) {
        $choices[$choice] = new lang_string($choice);
    }

    $settings->add(new admin_setting_configmulticheckbox(
        'report_assign/profilefields',
        new lang_string('profilefields', 'report_assign'),
        new lang_string('profilefields_desc', 'report_assign'),
        ['email', 'idnumber'],
        $choices
    ));

}