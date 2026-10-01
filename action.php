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
 * Handles Completion board state changes.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use mod_completionboard\manager;

require_once("../../config.php");

$id = required_param("id", PARAM_INT);
$action = required_param("action", PARAM_ALPHA);
$returnto = optional_param("returnto", "view", PARAM_ALPHA);
$userid = optional_param("userid", 0, PARAM_INT);

require_sesskey();

$rawcm = get_coursemodule_from_id("completionboard", $id, 0, false, MUST_EXIST);
$course = get_course($rawcm->course);
require_course_login($course, true, $rawcm);

$cm = get_fast_modinfo($course)->get_cm($id);
$context = context_module::instance($cm->id);
$activity = $DB->get_record("completionboard", ["id" => $cm->instance], "*", MUST_EXIST);
$manager = new manager($activity, $cm, $course);

switch ($action) {
    case "complete":
        require_capability("mod/completionboard:markcomplete", $context);
        if (!is_enrolled($context, $USER, "mod/completionboard:markcomplete", true)) {
            throw new moodle_exception("notenrolled", "completionboard");
        }
        $manager->mark_complete($USER->id);
        break;

    case "uncomplete":
        require_capability("mod/completionboard:markcomplete", $context);
        if (!is_enrolled($context, $USER, "mod/completionboard:markcomplete", true)) {
            throw new moodle_exception("notenrolled", "completionboard");
        }
        $manager->unmark_complete($USER->id);
        break;

    case "validate":
        require_capability("mod/completionboard:validate", $context);
        if (empty($userid)) {
            throw new moodle_exception("missinguserid", "completionboard");
        }
        $manager->validate($userid, $USER->id);
        break;

    case "unvalidate":
        require_capability("mod/completionboard:validate", $context);
        if (empty($userid)) {
            throw new moodle_exception("missinguserid", "completionboard");
        }
        $manager->unvalidate($userid);
        break;

    default:
        throw new moodle_exception("invalidaction", "completionboard");
}

if ($returnto === "report" && has_capability("mod/completionboard:viewreport", $context)) {
    redirect(new moodle_url("/mod/completionboard/report.php", ["id" => $cm->id]));
}

redirect(new moodle_url("/mod/completionboard/view.php", ["id" => $cm->id]));
