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
 * Search area base class for messages.
 *
 * @package    core_search
 * @copyright  2016 Devang Gaur
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_search\area;

defined('MOODLE_INTERNAL') || die();

/**
 * Base implementation for activity modules.
 *
 * @package    core_search
 * @copyright  2016 Devang Gaur
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base_message extends base {

    /**
     * The context levels the search area is working on.
     * @var array
     */
    protected static $levels = [CONTEXT_USER];

    /**
     * Returns recordset containing required data for indexing activities.
     *
     * @param int $modifiedfrom timestamp
     * @return \moodle_recordset
     */
    public function get_recordset_by_timestamp($modifiedfrom = 0) {
        global $DB;
        return $DB->get_recordset_select('message_read', 'timecreated >= ?', array($modifiedfrom));
    }

    /**
     * Link to the message.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {;
        global $DB, $USER;

        $message = $DB->get_record('message_read', array('id' => $doc->get('itemid')));
        $users = $this->get_left_right_users($message);
        $position = '#m'.$message->id;
        return new \moodle_url('/message/index.php', array('history' => MESSAGE_HISTORY_ALL, 'user1' => $users['leftsideuserid'], 'user2' => $users['rightsideuserid']), $position);
    }

    /**
     * Link to the conversation.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
        global $DB;
        $message = $DB->get_record('message_read', array('id' => $doc->get('itemid')));
        $users = $this->get_left_right_users($message);
        return new \moodle_url('/message/index.php', array('user1' => $users['leftsideuserid'], 'user2' => $users['rightsideuserid']));
    }

    /**
     * Sorting the leftuser and rightuser in the conversation.
     *
     * @param StdClass record
     * @return array()
     */
    protected function get_left_right_users($message) {
        global $USER;

        $users = array();
        if ($USER->id == $message->useridto) {
            $users['leftsideuserid'] = $message->useridto;
            $users['rightsideuserid'] = $message->useridfrom;
        } else {
            $users['leftsideuserid'] = $message->useridfrom;
            $users['rightsideuserid'] = $message->useridto;
        }
        return $users;
    }
}
