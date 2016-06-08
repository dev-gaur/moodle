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
	 * Returns recordset containing required data attributes for indexing.
	 * 
	 * @param number $modifiedfrom
	 * @return \moodle_recordset
	 */
	public function get_recordset_by_timestamp($modifiedfrom = 0) {
		
	}

	/**
	 * Returns the document
	 * 
	 * @param StdClass $record
	 * @param array $options
	 * @return core_search/document
	 */
	public function get_document($record, $options = array()){
		try {
			$context = \context_course::instance($record->contextid);
		} catch (\dml_missing_record_exception $ex) {
			
		} catch (\dml_exception $ex) {
			
		}
	}
	
	/**
	 * Checking whether I can access a document
	 * 
	 * @param int $id course id
	 * @return int
	 */
	public function check_access($id){		
		return \core_search\manager::ACCESS_GRANTED;
	}
	
	/**
	 * Returns a url to the document.
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
		return new moodle_url("");
	}
}
