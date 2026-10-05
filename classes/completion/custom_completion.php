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

namespace mod_completionboard\completion;

use core_completion\activity_custom_completion;

/**
 * Custom completion rules for Completion board.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {
    /**
     * Returns a custom rule state.
     *
     * @param string $rule Rule name.
     * @return int
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $entry = $DB->get_record("completionboard_entries", [
            "completionboardid" => $this->cm->instance,
            "userid" => $this->userid,
        ]);

        $complete = match ($rule) {
            "completionmark" => $entry && !empty($entry->timecompleted),
            "completionvalidated" => $entry && !empty($entry->timevalidated),
            default => false,
        };

        return $complete ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
    }

    /**
     * Returns the custom rules defined by the module.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return [
            "completionmark",
            "completionvalidated",
        ];
    }

    /**
     * Returns the custom completion rules enabled for this activity instance.
     *
     * The persisted activity settings are authoritative here. cm_info custom data can still contain
     * the previous values immediately after completion settings are changed, which makes validate_rule()
     * reject a rule that is actually enabled in the database.
     *
     * @return string[]
     */
    public function get_available_custom_rules(): array {
        if ((int)$this->cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
            return [];
        }

        global $DB;

        $activity = $DB->get_record(
            "completionboard",
            ["id" => $this->cm->instance],
            "id,completionmark,completionvalidated",
            MUST_EXIST
        );

        $rules = [];
        if (!empty($activity->completionmark)) {
            $rules[] = "completionmark";
        }
        if (!empty($activity->completionvalidated)) {
            $rules[] = "completionvalidated";
        }

        return $rules;
    }

    /**
     * Returns custom rule descriptions.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        return [
            "completionmark" => get_string("completiondetail:mark", "completionboard"),
            "completionvalidated" => get_string("completiondetail:validated", "completionboard"),
        ];
    }

    /**
     * Returns the order in which completion rules are displayed.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            "completionview",
            "completionmark",
            "completionvalidated",
        ];
    }
}
