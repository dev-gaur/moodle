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
 * A page displaying the user's contacts and messages
 *
 * @package    core_message
 * @copyright  2016 Devang Gaur
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace core_message\search;


defined('MOODLE_INTERNAL') || die();

/**
 * A page displaying the user's contacts and messages
 *
 * @package    core_message
 * @copyright  2016 Devang Gaur
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class message_recieved extends \core_search\area\base_message {

    /**
     * Returns the document associated with this course.
     *
     * @param stdClass $record
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($record, $options = array()) {
        var_dump(self::$levels);
        try {
            $usercontext = \context_user::instance($record->useridto);
        } catch (\moodle_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                    $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        $doc->set('title', content_to_text($record->subject, false));
        $doc->set('itemid', $record->id);
        $doc->set('content', content_to_text($record->smallmessage, false));
        $doc->set('contextid', $usercontext->id);
        $doc->set('courseid', SITEID);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timecreated);

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && $options['lastindexedtime'] < $record->timecreated) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }
        var_dump("all okkay");
        return $doc;
    }

    /**
     * Whether the user can access the document or not.
     *
     * @param int $id The course instance id.
     * @return int
     */
    public function check_access($id) {
        var_dump("here recieved");
        return \core_search\manager::ACCESS_GRANTED;
    }

}