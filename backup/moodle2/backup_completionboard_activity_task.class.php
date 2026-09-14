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
 * Backup task for Completion board.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("{$CFG->dirroot}/mod/completionboard/backup/moodle2/backup_completionboard_stepslib.php");

/**
 * Completion board backup task.
 */
class backup_completionboard_activity_task extends backup_activity_task {
    /**
     * Defines activity-specific backup settings.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Defines activity-specific backup steps.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_completionboard_activity_structure_step(
            "completionboard_structure",
            "completionboard.xml"
        ));
    }

    /**
     * Encodes activity links in backed-up content.
     *
     * @param string $content Content.
     * @return string
     */
    public static function encode_content_links($content) {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, "/");

        $search = "/(" . $base . "\\/mod\\/completionboard\\/index.php\\?id\\=)([0-9]+)/";
        $content = preg_replace($search, "\$@COMPLETIONBOARDINDEX*\$2@\$", $content);

        $search = "/(" . $base . "\\/mod\\/completionboard\\/view.php\\?id\\=)([0-9]+)/";
        $content = preg_replace($search, "\$@COMPLETIONBOARDVIEWBYID*\$2@\$", $content);

        return $content;
    }
}
