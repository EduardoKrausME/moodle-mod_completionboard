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

use advanced_testcase;
use mod_completionboard\completion\custom_completion;

/**
 * Tests custom completion integration with Moodle core.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \mod_completionboard\completion\custom_completion
 */
final class custom_completion_test extends advanced_testcase {
    /**
     * The completion mark rule is available when enabled for automatic completion.
     *
     * @return void
     */
    public function test_completionmark_is_available_and_evaluable(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(["enablecompletion" => 1]);
        $user = $this->getDataGenerator()->create_and_enrol($course, "student");
        $generator = $this->getDataGenerator()->get_plugin_generator("mod_completionboard");

        $activity = $generator->create_instance([
            "course" => $course->id,
            "completion" => COMPLETION_TRACKING_AUTOMATIC,
            "completionmark" => 1,
            "completionvalidated" => 0,
        ]);

        rebuild_course_cache($course->id, true);
        $cmrecord = get_coursemodule_from_instance(
            "completionboard",
            $activity->id,
            $course->id,
            false,
            MUST_EXIST
        );
        $cm = get_fast_modinfo($course)->get_cm($cmrecord->id);

        $customdata = (array)$cm->customdata;
        $this->assertSame([
            "completionmark" => 1,
            "completionvalidated" => 0,
        ], $customdata["customcompletionrules"]);

        $completion = new custom_completion($cm, $user->id);
        $this->assertSame(["completionmark"], $completion->get_available_custom_rules());
        $this->assertSame(COMPLETION_INCOMPLETE, $completion->get_state("completionmark"));

        $now = time();
        $DB->insert_record("completionboard_entries", (object)[
            "completionboardid" => $activity->id,
            "userid" => $user->id,
            "timecompleted" => $now,
            "validatedby" => 0,
            "timevalidated" => 0,
            "timecreated" => $now,
            "timemodified" => $now,
        ]);

        $this->assertSame(COMPLETION_COMPLETE, $completion->get_state("completionmark"));
    }
}
