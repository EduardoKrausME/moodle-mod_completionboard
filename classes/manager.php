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

namespace mod_completionboard;

use cm_info;
use completion_info;
use context_module;
use mod_completionboard\event\completion_marked;
use mod_completionboard\event\completion_unmarked;
use mod_completionboard\event\validation_removed;
use mod_completionboard\event\validation_set;
use moodle_exception;
use stdClass;

/**
 * Manages Completion board state.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var stdClass */
    private $activity;

    /** @var cm_info */
    private $cm;

    /** @var stdClass */
    private $course;

    /** @var context_module */
    private $context;

    /**
     * Constructor.
     *
     * @param stdClass $activity Activity record.
     * @param cm_info $cm Course module.
     * @param stdClass $course Course record.
     */
    public function __construct(stdClass $activity, cm_info $cm, stdClass $course) {
        $this->activity = $activity;
        $this->cm = $cm;
        $this->course = $course;
        $this->context = context_module::instance($cm->id);
    }

    /**
     * Returns a user's entry.
     *
     * @param int $userid User id.
     * @return stdClass|null
     */
    public function get_entry(int $userid): ?stdClass {
        global $DB;

        $record = $DB->get_record("completionboard_entries", [
            "completionboardid" => $this->activity->id,
            "userid" => $userid,
        ]);

        return $record ?: null;
    }

    /**
     * Returns entries keyed by user id.
     *
     * @param array $userids User ids.
     * @return array
     */
    public function get_entries_for_users(array $userids): array {
        global $DB;

        if (!$userids) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, "userid");
        $params["completionboardid"] = $this->activity->id;

        $sql = "SELECT *
                  FROM {completionboard_entries}
                 WHERE completionboardid = :completionboardid
                   AND userid {$insql}";

        $records = $DB->get_records_sql($sql, $params);
        $entries = [];

        foreach ($records as $record) {
            $entries[$record->userid] = $record;
        }

        return $entries;
    }

    /**
     * Marks an activity as completed by the user.
     *
     * @param int $userid User id.
     * @return stdClass
     */
    public function mark_complete(int $userid): stdClass {
        global $DB;

        $now = time();
        $entry = $this->get_entry($userid);

        if ($entry && !empty($entry->timecompleted)) {
            return $entry;
        }

        if (!$entry) {
            $entry = new stdClass();
            $entry->completionboardid = $this->activity->id;
            $entry->userid = $userid;
            $entry->timecompleted = $now;
            $entry->validatedby = 0;
            $entry->timevalidated = 0;
            $entry->timecreated = $now;
            $entry->timemodified = $now;
            $entry->id = $DB->insert_record("completionboard_entries", $entry);
        } else {
            $entry->timecompleted = $now;
            $entry->validatedby = 0;
            $entry->timevalidated = 0;
            $entry->timemodified = $now;
            $DB->update_record("completionboard_entries", $entry);
        }

        $event = completion_marked::create([
            "objectid" => $entry->id,
            "context" => $this->context,
            "relateduserid" => $userid,
        ]);
        $event->add_record_snapshot("completionboard", $this->activity);
        $event->add_record_snapshot("completionboard_entries", $entry);
        $event->trigger();

        $this->refresh_completion($userid);

        return $entry;
    }

    /**
     * Removes a user's completion mark if it has not been validated.
     *
     * @param int $userid User id.
     * @return void
     */
    public function unmark_complete(int $userid): void {
        global $DB;

        $entry = $this->get_entry($userid);
        if (!$entry || empty($entry->timecompleted)) {
            return;
        }

        if (!empty($entry->timevalidated)) {
            throw new moodle_exception("cannotunmarkvalidated", "completionboard");
        }

        $event = completion_unmarked::create([
            "objectid" => $entry->id,
            "context" => $this->context,
            "relateduserid" => $userid,
        ]);
        $event->add_record_snapshot("completionboard", $this->activity);
        $event->add_record_snapshot("completionboard_entries", $entry);

        $DB->delete_records("completionboard_entries", ["id" => $entry->id]);
        $event->trigger();

        $this->refresh_completion($userid);
    }

    /**
     * Validates a user's completion.
     *
     * @param int $userid Student user id.
     * @param int $validatorid Validator user id.
     * @return stdClass
     */
    public function validate(int $userid, int $validatorid): stdClass {
        global $DB;

        $entry = $this->get_entry($userid);
        if (!$entry || empty($entry->timecompleted)) {
            throw new moodle_exception("cannotvalidateincomplete", "completionboard");
        }

        if (!empty($entry->timevalidated)) {
            return $entry;
        }

        $entry->validatedby = $validatorid;
        $entry->timevalidated = time();
        $entry->timemodified = time();
        $DB->update_record("completionboard_entries", $entry);

        $event = validation_set::create([
            "objectid" => $entry->id,
            "context" => $this->context,
            "relateduserid" => $userid,
        ]);
        $event->add_record_snapshot("completionboard", $this->activity);
        $event->add_record_snapshot("completionboard_entries", $entry);
        $event->trigger();

        $this->refresh_completion($userid);

        return $entry;
    }

    /**
     * Removes the validation from a user's completion.
     *
     * @param int $userid Student user id.
     * @return stdClass|null
     */
    public function unvalidate(int $userid): ?stdClass {
        global $DB;

        $entry = $this->get_entry($userid);
        if (!$entry || empty($entry->timevalidated)) {
            return $entry;
        }

        $entry->validatedby = 0;
        $entry->timevalidated = 0;
        $entry->timemodified = time();
        $DB->update_record("completionboard_entries", $entry);

        $event = validation_removed::create([
            "objectid" => $entry->id,
            "context" => $this->context,
            "relateduserid" => $userid,
        ]);
        $event->add_record_snapshot("completionboard", $this->activity);
        $event->add_record_snapshot("completionboard_entries", $entry);
        $event->trigger();

        $this->refresh_completion($userid);

        return $entry;
    }

    /**
     * Recalculates Moodle completion for one user.
     *
     * @param int $userid User id.
     * @return void
     */
    private function refresh_completion(int $userid): void {
        $completion = new completion_info($this->course);

        if ($completion->is_enabled($this->cm)) {
            $completion->update_state($this->cm, COMPLETION_UNKNOWN, $userid);
        }
    }
}
