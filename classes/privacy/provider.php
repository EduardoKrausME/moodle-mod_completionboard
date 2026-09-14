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

namespace mod_completionboard\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;

/**
 * Privacy provider for Completion board.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describes stored personal data.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            "completionboard_entries",
            [
                "completionboardid" => "privacy:metadata:completionboard_entries:completionboardid",
                "userid" => "privacy:metadata:completionboard_entries:userid",
                "timecompleted" => "privacy:metadata:completionboard_entries:timecompleted",
                "validatedby" => "privacy:metadata:completionboard_entries:validatedby",
                "timevalidated" => "privacy:metadata:completionboard_entries:timevalidated",
                "timecreated" => "privacy:metadata:completionboard_entries:timecreated",
                "timemodified" => "privacy:metadata:completionboard_entries:timemodified",
            ],
            "privacy:metadata:completionboard_entries"
        );

        return $collection;
    }

    /**
     * Returns module contexts containing data for a user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm
                    ON cm.id = ctx.instanceid
                   AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m
                    ON m.id = cm.module
                   AND m.name = :modname
                  JOIN {completionboard} cb
                    ON cb.id = cm.instance
                  JOIN {completionboard_entries} cbe
                    ON cbe.completionboardid = cb.id
                 WHERE cbe.userid = :userid
                    OR cbe.validatedby = :validatedby";

        $contextlist->add_from_sql($sql, [
            "contextlevel" => CONTEXT_MODULE,
            "modname" => "completionboard",
            "userid" => $userid,
            "validatedby" => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Adds users whose data exists in a context.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $sql = "SELECT cbe.userid
                  FROM {completionboard_entries} cbe
                  JOIN {course_modules} cm ON cm.instance = cbe.completionboardid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid
                   AND m.name = :modname";

        $userlist->add_from_sql("userid", $sql, [
            "cmid" => $context->instanceid,
            "modname" => "completionboard",
        ]);

        $sql = "SELECT cbe.validatedby AS userid
                  FROM {completionboard_entries} cbe
                  JOIN {course_modules} cm ON cm.instance = cbe.completionboardid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id = :cmid
                   AND m.name = :modname
                   AND cbe.validatedby <> 0";

        $userlist->add_from_sql("userid", $sql, [
            "cmid" => $context->instanceid,
            "modname" => "completionboard",
        ]);
    }

    /**
     * Exports a user's data.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id("completionboard", $context->instanceid);
            if (!$cm) {
                continue;
            }

            $activity = $DB->get_record("completionboard", ["id" => $cm->instance]);
            if (!$activity) {
                continue;
            }

            $entries = $DB->get_records_select(
                "completionboard_entries",
                "completionboardid = :completionboardid AND (userid = :userid OR validatedby = :validatedby)",
                [
                    "completionboardid" => $activity->id,
                    "userid" => $userid,
                    "validatedby" => $userid,
                ]
            );

            $export = [];
            foreach ($entries as $entry) {
                $export[] = (object) [
                    "userid" => $entry->userid,
                    "completed" => !empty($entry->timecompleted),
                    "timecompleted" => !empty($entry->timecompleted)
                        ? transform::datetime($entry->timecompleted)
                        : null,
                    "validatedby" => $entry->validatedby,
                    "timevalidated" => !empty($entry->timevalidated)
                        ? transform::datetime($entry->timevalidated)
                        : null,
                ];
            }

            if ($export) {
                \core_privacy\local\request\writer::with_context($context)
                    ->export_data([get_string("pluginname", "completionboard")], (object) ["entries" => $export]);
            }
        }
    }

    /**
     * Deletes all user data from a module context.
     *
     * @param \context $context Context.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id("completionboard", $context->instanceid);
        if ($cm) {
            $DB->delete_records("completionboard_entries", ["completionboardid" => $cm->instance]);
        }
    }

    /**
     * Deletes one user's data from approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }

            $cm = get_coursemodule_from_id("completionboard", $context->instanceid);
            if (!$cm) {
                continue;
            }

            $DB->delete_records("completionboard_entries", [
                "completionboardid" => $cm->instance,
                "userid" => $userid,
            ]);

            $DB->set_field_select(
                "completionboard_entries",
                "validatedby",
                0,
                "completionboardid = :completionboardid AND validatedby = :userid",
                [
                    "completionboardid" => $cm->instance,
                    "userid" => $userid,
                ]
            );
        }
    }

    /**
     * Deletes data for multiple users from one context.
     *
     * @param approved_userlist $userlist Approved users.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }

        $cm = get_coursemodule_from_id("completionboard", $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, "userid");
        $params["completionboardid"] = $cm->instance;

        $DB->delete_records_select(
            "completionboard_entries",
            "completionboardid = :completionboardid AND userid {$insql}",
            $params
        );

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, "validator");
        $params["completionboardid"] = $cm->instance;

        $DB->set_field_select(
            "completionboard_entries",
            "validatedby",
            0,
            "completionboardid = :completionboardid AND validatedby {$insql}",
            $params
        );
    }
}
