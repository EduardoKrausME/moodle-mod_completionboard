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
 * Restore structure for Completion board.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Completion board restore structure step.
 */
class restore_completionboard_activity_structure_step extends restore_activity_structure_step {
    /**
     * Defines restore paths.
     *
     * @return array
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element("completionboard", "/activity/completionboard");

        if ($this->get_setting_value("userinfo")) {
            $paths[] = new restore_path_element(
                "completionboard_entry",
                "/activity/completionboard/entries/entry"
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the activity instance.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_completionboard($data) {
        global $DB;

        $data = (object)$data;
        $data->course = $this->get_courseid();

        $newitemid = $DB->insert_record("completionboard", $data);
        $this->apply_activity_instance($newitemid);
    }

    /**
     * Restores one student entry.
     *
     * @param array $data Record data.
     * @return void
     */
    protected function process_completionboard_entry($data) {
        global $DB;

        $data = (object)$data;
        $data->completionboardid = $this->get_new_parentid("completionboard");
        $data->userid = $this->get_mappingid("user", $data->userid, 0);

        if (empty($data->userid)) {
            return;
        }

        if (!empty($data->validatedby)) {
            $data->validatedby = $this->get_mappingid("user", $data->validatedby, 0);
        }

        $DB->insert_record("completionboard_entries", $data);
    }

    /**
     * Restores related files.
     *
     * @return void
     */
    protected function after_execute() {
        $this->add_related_files("mod_completionboard", "intro", null);
    }
}
