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
// GNU  General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Search area for Users for whom I have authority to view profile.
 *
 * @package    core_user
 * @copyright  2016 Devang Gaur {@link http://www.devanggaur.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_user\search;

require_once($CFG->dirroot . '/lib/accesslib.php');
require_once($CFG->dirroot . '/lib/enrollib.php');
require_once($CFG->dirroot . '/lib/moodlelib.php');
require_once($CFG->dirroot . '/lib/grouplib.php');
require_once($CFG->dirroot . '/lib/weblib.php');

defined('MOODLE_INTERNAL') || die();

/**
 * Search area for Users for whom I have access to view profile.
 *
 * @package    core_user
 * @copyright  2016 Devang Gaur {@link http://www.devanggaur.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class user extends \core_search\area\base {

    /**
     * The records of deleted users have the "deleted" field assigned as one.
     */
    const USER_DELETED = 1;

    /**
     * Admin User ID in User db table records
     */
    const ADMIN_USER_ID = 2;

    /**
     * Returns recordset containing required data attributes for indexing.
     * 
     * @param number $modifiedfrom
     * @return \moodle_recordset
     */
    public function get_recordset_by_timestamp($modifiedfrom = 0) {
        global $DB;
        return $DB->get_recordset_select('user', 'timemodified >= ? AND deleted <> ?', array($modifiedfrom, self::USER_DELETED));
    }

    /**
     * Returns document instances for each record in the recordset.
     * 
     * @param StdClass $record
     * @param array $options
     * @return core_search/document
     */
    public function get_document($record, $options = array()){
        try {
            $context = \context_system::instance();
        } catch (\dml_missing_record_exception $ex) {
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            debugging('Error retrieving ' . $this->areaid . ' ' . $record->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($record->id, $this->componentname, $this->areaname);
        // Assigning properties to our document
        $doc->set('title', content_to_text(fullname($record), false));
        $doc->set('content', content_to_text($record->email, false));
        $doc->set('contextid', $context->id);
        $doc->set('courseid', SITEID);
        $doc->set('itemid', $record->id);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $record->timemodified);
        $doc->set('description1', content_to_text($record->firstname.' '.$record->middlename.' '.$record->lastname.' ( '.$record->username.' )', false));

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && $options['lastindexedtime'] < $record->timecreated) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }
        
        return $doc;
    }

    /**
     * Checking whether I can access a document
     * 
     * @param int $id user id
     * @return int
     */
    public function check_access($id){
        global $DB, $USER;

        try {
            $usercontext = \context_user::instance($id);
        } catch (\dml_missing_record_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        } catch (\dml_exception $ex) {
            return \core_search\manager::ACCESS_DELETED;
        }

        // Only admin user ( id 1 ) can access search result for himself
        if(($USER->id != self::ADMIN_USER_ID) && ($id == self::ADMIN_USER_ID)) {
            return \core_search\manager::ACCESS_DENIED;
        }

        $user = $DB->get_record_select('user', 'id = ?', array($id)); 
        if ($user->deleted) {
            return \core_search\manager::ACCESS_DELETED;
        }

        if (has_capability('moodle/user:viewdetails', $usercontext)) {
            return \core_search\manager::ACCESS_GRANTED;
        }

        if (has_coursecontact_role($id)) {
            return \core_search\manager::ACCESS_GRANTED;
        }

        $sharedcourses = enrol_get_shared_courses($USER->id, $user->id, true);

        foreach ($sharedcourses as $sharedcourse) {
            $coursecontext = \context_course::instance($sharedcourse->id);
            if (has_capability('moodle/user:viewdetails', $coursecontext)) {
                if (!groups_user_groups_visible($sharedcourse, $user->id)) {
                    // Not a member of the same group.
                    continue;
                }
                return \core_search\manager::ACCESS_GRANTED;
            }
        }

        return \core_search\manager::ACCESS_DENIED;
    }

    /**
     * Returns a url to the profile page of user.
     * 
     * @param \core_search\document $doc
     * @return moodle_url
     */
    public function get_doc_url(\core_search\document $doc){
        return $this->get_context_url($doc);
    }

    /**
     * Returns a url to the document context.
     * 
     * @param \core_search\document $doc
     * @return moodle_url
     */
    public function get_context_url(\core_search\document $doc){
        return new \moodle_url('/user/profile.php', array('id' => $doc->get('itemid')));
    }
}
