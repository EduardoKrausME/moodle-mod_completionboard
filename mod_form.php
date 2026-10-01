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
 * Activity settings form.
 *
 * @package    mod_completionboard
 * @copyright  2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("{$CFG->dirroot}/course/moodleform_mod.php");

/**
 * Completion board module form.
 */
class mod_completionboard_mod_form extends moodleform_mod {
    /**
     * Defines the activity form.
     *
     * @return void
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        $mform->addElement("header", "general", get_string("general", "form"));

        $mform->addElement("text", "name", get_string("completionboardname", "completionboard"), ["size" => 64]);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType("name", PARAM_TEXT);
        } else {
            $mform->setType("name", PARAM_CLEANHTML);
        }
        $mform->addRule("name", null, "required", null, "client");
        $mform->addRule("name", get_string("maximumchars", "", 1333), "maxlength", 1333, "client");

        $this->standard_intro_elements(get_string("description", "completionboard"));

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Adds custom completion rules.
     *
     * @return array
     */
    public function add_completion_rules() {
        $mform = $this->_form;
        $suffix = $this->get_suffix();

        $completionmark = "completionmark" . $suffix;
        $completionvalidated = "completionvalidated" . $suffix;

        $mform->addElement(
            "checkbox",
            $completionmark,
            "",
            get_string("completionmark", "completionboard")
        );
        $mform->setDefault($completionmark, 1);

        $mform->addElement(
            "checkbox",
            $completionvalidated,
            "",
            get_string("completionvalidated", "completionboard")
        );
        $mform->setDefault($completionvalidated, 0);

        return [$completionmark, $completionvalidated];
    }

    /**
     * Returns whether at least one custom completion rule is enabled.
     *
     * @param array $data Submitted form data.
     * @return bool
     */
    public function completion_rule_enabled($data) {
        $suffix = $this->get_suffix();

        return !empty($data["completionmark" . $suffix])
            || !empty($data["completionvalidated" . $suffix]);
    }

    /**
     * Normalizes custom completion fields.
     *
     * @param stdClass $data Submitted data.
     * @return void
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);

        if (!empty($data->completionunlocked)) {
            $suffix = $this->get_suffix();

            if (empty($data->{"completionmark" . $suffix})) {
                $data->{"completionmark" . $suffix} = 0;
            }

            if (empty($data->{"completionvalidated" . $suffix})) {
                $data->{"completionvalidated" . $suffix} = 0;
            }
        }
    }
}
