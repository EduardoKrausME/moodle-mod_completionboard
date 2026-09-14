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
 * Main activity page.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once($CFG->libdir . "/completionlib.php");

$id = required_param("id", PARAM_INT);

$rawcm = get_coursemodule_from_id("completionboard", $id, 0, false, MUST_EXIST);
$course = get_course($rawcm->course);
require_course_login($course, true, $rawcm);

$cm = get_fast_modinfo($course)->get_cm($id);
$context = context_module::instance($cm->id);
require_capability("mod/completionboard:view", $context);

$activity = $DB->get_record("completionboard", ["id" => $cm->instance], "*", MUST_EXIST);
$manager = new \mod_completionboard\manager($activity, $cm, $course);

$PAGE->set_url("/mod/completionboard/view.php", ["id" => $cm->id]);
$PAGE->set_title(format_string($activity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course, $activity);

$event = \mod_completionboard\event\course_module_viewed::create([
    "objectid" => $activity->id,
    "context" => $context,
]);
$event->add_record_snapshot("course", $course);
$event->add_record_snapshot("completionboard", $activity);
$event->trigger();

$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$entry = $manager->get_entry($USER->id);
$canmark = has_capability("mod/completionboard:markcomplete", $context)
    && is_enrolled($context, $USER, "mod/completionboard:markcomplete", true);
$canviewreport = has_capability("mod/completionboard:viewreport", $context);

$statuskey = "statusnotcompleted";
$statusclass = "completionboard-status-notcompleted";
if ($entry && !empty($entry->timevalidated)) {
    $statuskey = "statusvalidated";
    $statusclass = "completionboard-status-validated";
} else if ($entry && !empty($entry->timecompleted)) {
    $statuskey = "statuscompleted";
    $statusclass = "completionboard-status-completed";
}

$actionhtml = "";
if ($canmark) {
    if (!$entry || empty($entry->timecompleted)) {
        $actionurl = new moodle_url("/mod/completionboard/action.php", [
            "id" => $cm->id,
            "action" => "complete",
            "returnto" => "view",
        ]);
        $actionhtml = $OUTPUT->single_button(
            $actionurl,
            get_string("markcomplete", "completionboard"),
            "post",
            ["class" => "completionboard-primary-action"]
        );
    } else if (empty($entry->timevalidated)) {
        $actionurl = new moodle_url("/mod/completionboard/action.php", [
            "id" => $cm->id,
            "action" => "uncomplete",
            "returnto" => "view",
        ]);
        $actionhtml = $OUTPUT->single_button(
            $actionurl,
            get_string("unmarkcomplete", "completionboard"),
            "post"
        );
    }
}

$reporturl = "";
if ($canviewreport) {
    $reporturl = (new moodle_url("/mod/completionboard/report.php", ["id" => $cm->id]))->out(false);
}

$templatecontext = [
    "intro" => format_module_intro("completionboard", $activity, $cm->id),
    "canmark" => $canmark,
    "status" => get_string($statuskey, "completionboard"),
    "statusclass" => $statusclass,
    "completedtime" => $entry && !empty($entry->timecompleted)
        ? userdate($entry->timecompleted)
        : "",
    "validatedtime" => $entry && !empty($entry->timevalidated)
        ? userdate($entry->timevalidated)
        : "",
    "iscompleted" => $entry && !empty($entry->timecompleted),
    "isvalidated" => $entry && !empty($entry->timevalidated),
    "actionhtml" => $actionhtml,
    "canviewreport" => $canviewreport,
    "reporturl" => $reporturl,
];

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($activity->name));
echo $OUTPUT->render_from_template("mod_completionboard/view", $templatecontext);
echo $OUTPUT->footer();
