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
        'username',
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

    // Selector for submission fields.
    $submissionfields = [
        "status" => new lang_string('status'),
        "grade" => new lang_string('grade'),
        "gradevalue" => new lang_string('gradevalue', 'report_assign'),
        "grademax" => new lang_string('grademax', 'report_assign'),
        "grader" => new lang_string('grader', 'report_assign'),
        "created" => new lang_string('created', 'report_assign'),
        "modified" => new lang_string('modified'),
        "latenessincext" => new lang_string('latenessincext', 'report_assign'),
        "extension" => new lang_string('extension', 'report_assign'),
    ];

    $settings->add(new admin_setting_configmulticheckbox(
            'report_assign/submissionfields',
            new lang_string('submissionfields', 'report_assign'),
            new lang_string('submissionfields_desc', 'report_assign'),
            ["status" => 1, "grade" => 1, "grader" => 1, "modified" => 1, "extension" => 1],
            $submissionfields
    ));

    $pluginmanager = \core_plugin_manager::instance();
    // Selector for submission plugins.
    $submissionplugins = $pluginmanager->get_plugins_of_type('assignsubmission');
    $submissionpluginfields = [];
    foreach ($submissionplugins as $plugin) {
        if ($plugin->name == 'comments') {
            // Submission comments don't work like other plugins.
            // It's not currently needed, so skip it.
            continue;
        }
        if ($plugin->is_enabled()) {
            $submissionpluginfields[$plugin->name] = $plugin->displayname;
        }
    }

    $settings->add(new admin_setting_configmulticheckbox(
            'report_assign/submissionplugins',
            new lang_string('submissionplugins', 'report_assign'),
            new lang_string('submissionplugins_desc', 'report_assign'),
            ["file" => 1, "plagiarism_turnitin" => 1, "plagiarism_urkund" => 1],
            $submissionpluginfields
    ));

    // Selector for course options.
    $coursefields = [
        "idnumber" => new lang_string('idnumbercourse'),
    ];

    $settings->add(new admin_setting_configmulticheckbox(
        'report_assign/coursefields',
        new lang_string('coursefields', 'report_assign'),
        new lang_string('coursefields_desc', 'report_assign'),
        [],
        $coursefields
    ));

    // Selector for field options.
    $rawchoices = [
        'splitusername',
    ];
    $choices = [];
    foreach ($rawchoices as $choice) {
        $choices[$choice] = new lang_string($choice, 'report_assign');
    }

    $settings->add(new admin_setting_configmulticheckbox(
        'report_assign/fieldoptions',
        new lang_string('fieldoptions', 'report_assign'),
        new lang_string('fieldoptions_desc', 'report_assign'),
        [],
        $choices
    ));
}
