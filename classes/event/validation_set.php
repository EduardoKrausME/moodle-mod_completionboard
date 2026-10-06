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

namespace mod_completionboard\event;

use core\event\base;
use moodle_url;

/**
 * Validation set event.
 *
 * @package    mod_completionboard
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validation_set extends base {
    /**
     * Initializes the event.
     *
     * @return void
     */
    protected function init() {
        $this->data["crud"] = "u";
        $this->data["edulevel"] = self::LEVEL_TEACHING;
        $this->data["objecttable"] = "completionboard_entries";
    }

    /**
     * Returns the restore mapping for the entry referenced by objectid.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ["db" => "completionboard_entries", "restore" => "completionboard_entry"];
    }

    /**
     * Returns the localized event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string("eventvalidationset", "completionboard");
    }

    /**
     * Returns the event description.
     *
     * @return string
     */
    public function get_description() {
        return "The user validated completion for the user with id '{$this->relateduserid}'.";
    }

    /**
     * Returns the report URL.
     *
     * @return moodle_url
     */
    public function get_url() {
        return new moodle_url("/mod/completionboard/report.php", ["id" => $this->contextinstanceid]);
    }
}
