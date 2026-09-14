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
 * Standard callbacks for the Completion board activity.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns the supported Moodle features.
 *
 * @param string $feature Moodle feature constant.
 * @return mixed
 */
function completionboard_supports($feature) {
    return match ($feature) {
        FEATURE_GROUPS => true,
        FEATURE_GROUPINGS => true,
        FEATURE_MOD_INTRO => true,
        FEATURE_SHOW_DESCRIPTION => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_COMPLETION_HAS_RULES => true,
        FEATURE_GRADE_HAS_GRADE => false,
        FEATURE_GRADE_OUTCOMES => false,
        FEATURE_BACKUP_MOODLE2 => true,
        FEATURE_MOD_PURPOSE => MOD_PURPOSE_OTHER,
        default => null,
    };
}

/**
 * Creates a Completion board instance.
 *
 * @param stdClass $data Form data.
 * @param mod_completionboard_mod_form|null $mform Form instance.
 * @return int
 */
function completionboard_add_instance($data, $mform = null) {
    global $DB;

    $data->timemodified = time();
    return $DB->insert_record("completionboard", $data);
}

/**
 * Updates a Completion board instance.
 *
 * @param stdClass $data Form data.
 * @param mod_completionboard_mod_form|null $mform Form instance.
 * @return bool
 */
function completionboard_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();

    return $DB->update_record("completionboard", $data);
}

/**
 * Deletes a Completion board instance.
 *
 * @param int $id Instance id.
 * @return bool
 */
function completionboard_delete_instance($id) {
    global $DB;

    if (!$activity = $DB->get_record("completionboard", ["id" => $id])) {
        return false;
    }

    $DB->delete_records("completionboard_entries", ["completionboardid" => $activity->id]);
    $DB->delete_records("completionboard", ["id" => $activity->id]);

    return true;
}

/**
 * Returns basic user activity information for course reports.
 *
 * @param stdClass $course Course record.
 * @param stdClass $user User record.
 * @param cm_info|stdClass $mod Course module.
 * @param stdClass $activity Activity record.
 * @return stdClass|null
 */
function completionboard_user_outline($course, $user, $mod, $activity) {
    global $DB;

    $entry = $DB->get_record("completionboard_entries", [
        "completionboardid" => $activity->id,
        "userid" => $user->id,
    ]);

    if (!$entry || empty($entry->timecompleted)) {
        return null;
    }

    $result = new stdClass();
    $result->info = !empty($entry->timevalidated)
        ? get_string("statusvalidated", "completionboard")
        : get_string("statuscompleted", "completionboard");
    $result->time = !empty($entry->timevalidated) ? $entry->timevalidated : $entry->timecompleted;

    return $result;
}

/**
 * Prints complete user activity information for course reports.
 *
 * @param stdClass $course Course record.
 * @param stdClass $user User record.
 * @param cm_info|stdClass $mod Course module.
 * @param stdClass $activity Activity record.
 * @return void
 */
function completionboard_user_complete($course, $user, $mod, $activity) {
    global $DB;

    $entry = $DB->get_record("completionboard_entries", [
        "completionboardid" => $activity->id,
        "userid" => $user->id,
    ]);

    if (!$entry || empty($entry->timecompleted)) {
        echo get_string("statusnotcompleted", "completionboard");
        return;
    }

    echo get_string("completedon", "completionboard", userdate($entry->timecompleted));

    if (!empty($entry->timevalidated)) {
        echo html_writer::empty_tag("br");
        echo get_string("validatedon", "completionboard", userdate($entry->timevalidated));
    }
}
