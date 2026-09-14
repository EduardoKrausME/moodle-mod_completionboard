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
 * Lists Completion board activities in a course.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");

$id = required_param("id", PARAM_INT);
$course = get_course($id);

require_course_login($course);

$PAGE->set_url("/mod/completionboard/index.php", ["id" => $course->id]);
$PAGE->set_title(get_string("modulenameplural", "completionboard"));
$PAGE->set_heading(format_string($course->fullname));

$instances = get_all_instances_in_course("completionboard", $course);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("modulenameplural", "completionboard"));

if (!$instances) {
    echo $OUTPUT->notification(get_string("noactivities", "completionboard"), "info");
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string("name"),
    get_string("description", "completionboard"),
];

foreach ($instances as $instance) {
    $url = new moodle_url("/mod/completionboard/view.php", ["id" => $instance->coursemodule]);
    $table->data[] = [
        html_writer::link($url, format_string($instance->name)),
        format_module_intro("completionboard", $instance, $instance->coursemodule),
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
