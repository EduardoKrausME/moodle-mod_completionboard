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
 * Teacher report for the Completion board activity.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_completionboard\manager;

require_once("../../config.php");
require_once($CFG->libdir . "/tablelib.php");

$id = required_param("id", PARAM_INT);
$statusfilter = optional_param("status", "all", PARAM_ALPHA);

$allowedfilters = ["all", "notcompleted", "completed", "validated"];
if (!in_array($statusfilter, $allowedfilters, true)) {
    $statusfilter = "all";
}

$rawcm = get_coursemodule_from_id("completionboard", $id, 0, false, MUST_EXIST);
$course = get_course($rawcm->course);
require_course_login($course, true, $rawcm);

$cm = get_fast_modinfo($course)->get_cm($id);
$context = context_module::instance($cm->id);
require_capability("mod/completionboard:viewreport", $context);

$activity = $DB->get_record("completionboard", ["id" => $cm->instance], "*", MUST_EXIST);
$manager = new manager($activity, $cm, $course);
$canvalidate = has_capability("mod/completionboard:validate", $context);

$url = new moodle_url("/mod/completionboard/report.php", [
    "id" => $cm->id,
    "status" => $statusfilter,
]);

$PAGE->set_url($url);
$PAGE->set_title(get_string("report", "completionboard"));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course, $activity);

$groupmode = groups_get_activity_groupmode($cm);
$accessallgroups = has_capability("moodle/site:accessallgroups", $context);
$groupid = 0;
$nogroupaccess = false;
if ($groupmode) {
    $groupid = groups_get_activity_group($cm, true);
}
if ($groupmode == SEPARATEGROUPS && !$accessallgroups && empty($groupid)) {
    $nogroupaccess = true;
}

$userfields = implode(",", [
    "u.id",
    "u.firstname",
    "u.lastname",
    "u.firstnamephonetic",
    "u.lastnamephonetic",
    "u.middlename",
    "u.alternatename",
    "u.email",
]);

$users = $nogroupaccess ? [] : get_enrolled_users(
    $context,
    "mod/completionboard:markcomplete",
    $groupid,
    $userfields,
    "u.lastname, u.firstname",
    0,
    0,
    true
);

$entries = $manager->get_entries_for_users(array_keys($users));

$summary = [
    "notcompleted" => 0,
    "completed" => 0,
    "validated" => 0,
];

foreach ($users as $user) {
    $entry = $entries[$user->id] ?? null;

    if ($entry && !empty($entry->timevalidated)) {
        $summary["validated"]++;
    } else if ($entry && !empty($entry->timecompleted)) {
        $summary["completed"]++;
    } else {
        $summary["notcompleted"]++;
    }
}

$validatorids = [];
foreach ($entries as $entry) {
    if (!empty($entry->validatedby)) {
        $validatorids[$entry->validatedby] = $entry->validatedby;
    }
}
$validators = $validatorids
    ? $DB->get_records_list("user", "id", array_values($validatorids))
    : [];

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string("reportfor", "completionboard", format_string($activity->name)));

if ($groupmode) {
    groups_print_activity_menu($cm, $url);
}
if ($nogroupaccess) {
    echo $OUTPUT->notification(get_string("nogroupaccess", "completionboard"), "info");
}

$summarycontext = [
    "notcompleted" => $summary["notcompleted"],
    "completed" => $summary["completed"],
    "validated" => $summary["validated"],
    "allurl" => (new moodle_url("/mod/completionboard/report.php", [
        "id" => $cm->id,
        "status" => "all",
    ]))->out(false),
    "notcompletedurl" => (new moodle_url("/mod/completionboard/report.php", [
        "id" => $cm->id,
        "status" => "notcompleted",
    ]))->out(false),
    "completedurl" => (new moodle_url("/mod/completionboard/report.php", [
        "id" => $cm->id,
        "status" => "completed",
    ]))->out(false),
    "validatedurl" => (new moodle_url("/mod/completionboard/report.php", [
        "id" => $cm->id,
        "status" => "validated",
    ]))->out(false),
];

echo $OUTPUT->render_from_template("mod_completionboard/report_summary", $summarycontext);

$table = new flexible_table("mod-completionboard-report-" . $cm->id);
$table->define_columns([
    "fullname",
    "status",
    "timecompleted",
    "validator",
    "timevalidated",
    "actions",
]);
$table->define_headers([
    get_string("student", "completionboard"),
    get_string("status", "completionboard"),
    get_string("completedat", "completionboard"),
    get_string("validatedby", "completionboard"),
    get_string("validatedat", "completionboard"),
    get_string("actions", "completionboard"),
]);
$table->define_baseurl($url);
$table->set_attribute("class", "generaltable completionboard-report-table");
$table->setup();

foreach ($users as $user) {
    $entry = $entries[$user->id] ?? null;

    $rowstatus = "notcompleted";
    if ($entry && !empty($entry->timevalidated)) {
        $rowstatus = "validated";
    } else if ($entry && !empty($entry->timecompleted)) {
        $rowstatus = "completed";
    }

    if ($statusfilter !== "all" && $rowstatus !== $statusfilter) {
        continue;
    }

    if ($rowstatus === "validated") {
        $statuslabel = get_string("statusvalidated", "completionboard");
    } else if ($rowstatus === "completed") {
        $statuslabel = get_string("statuscompleted", "completionboard");
    } else {
        $statuslabel = get_string("statusnotcompleted", "completionboard");
    }

    $validatorname = "";
    if ($entry && !empty($entry->validatedby) && isset($validators[$entry->validatedby])) {
        $validatorname = fullname($validators[$entry->validatedby]);
    }

    $actions = "";
    if ($entry && !empty($entry->timecompleted)) {
        if (empty($entry->timevalidated) && $canvalidate) {
            $actionurl = new moodle_url("/mod/completionboard/action.php", [
                "id" => $cm->id,
                "action" => "validate",
                "userid" => $user->id,
                "returnto" => "report",
            ]);
            $actions = $OUTPUT->single_button(
                $actionurl,
                get_string("validate", "completionboard"),
                "post",
                ["class" => "completionboard-validate-action"]
            );
        } else if (!empty($entry->timevalidated) && $canvalidate) {
            $actionurl = new moodle_url("/mod/completionboard/action.php", [
                "id" => $cm->id,
                "action" => "unvalidate",
                "userid" => $user->id,
                "returnto" => "report",
            ]);
            $actions = $OUTPUT->single_button(
                $actionurl,
                get_string("removevalidation", "completionboard"),
                "post"
            );
        }
    }

    $table->add_data([
        fullname($user),
        $statuslabel,
        $entry && !empty($entry->timecompleted) ? userdate($entry->timecompleted) : "-",
        $validatorname ?: "-",
        $entry && !empty($entry->timevalidated) ? userdate($entry->timevalidated) : "-",
        $actions,
    ]);
}

$table->finish_output();

echo $OUTPUT->footer();
