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
 * Search area for mod_data activity entries.
 *
 * @package    mod_data
 * @copyright  2016 Devang Gaur
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_data\search;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/data/lib.php');

/**
 * Search area for mod_data activity entries.
 *
 * @package    mod_data
 * @copyright  2016 Devang Gaur
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class entry extends \core_search\area\base_mod {

	
	/**
	 * @var array Internal quick static cache.
	 */
	protected $entriesdata = array();

	
	/**
	 * @var array Internal quick static cache.
	 */
	protected $databaseactivitydata = array();

	/**
     * Returns recordset containing required data for indexing database entries.
     *
     * @param int $modifiedfrom timestamp
     * @return moodle_recordset
     */
    public function get_recordset_by_timestamp($modifiedfrom = 0) {
        global $DB;

        $sql = "SELECT dr.*, d.course FROM {data_records} dr
                  JOIN {data} d ON d.id = dr.dataid
                WHERE dr.timemodified >= ?";
        return $DB->get_recordset_sql($sql, array($modifiedfrom));
    }

    /**
     * Returns the documents associated with this glossary entry id.
     *
     * @param stdClass $entry glossary entry.
     * @param array    $options
     * @return \core_search\document
     */
    public function get_document($entry, $options = array()){
        global $DB;

        try {
            $cm = $this->get_cm('data', $entry->dataid, $entry->course);
            $context = \context_module::instance($cm->id);
        } catch (\dml_missing_record_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving mod_data ' . $entry->id . ' document, not all required data is available: ' .
                $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        } catch (\dml_exception $ex) {
            // Notify it as we run here as admin, we should see everything.
            debugging('Error retrieving mod_data' . $entry->id . ' document: ' . $ex->getMessage(), DEBUG_DEVELOPER);
            return false;
        }

        $indexfields = array();
        $contents = $DB->get_records('data_content', array('recordid' => $entry->id));

        // Prepare associative array with data from DB.
        $doc = \core_search\document_factory::instance($entry->id, $this->componentname, $this->areaname);
        $doc->set('contextid', $context->id);
        $doc->set('courseid', $entry->course);
        $doc->set('userid', $entry->userid);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);
        $doc->set('modified', $entry->timemodified);

        $doc->set('title', content_to_text("yay", false));
        $doc->set('content', content_to_text("ya", false));
        $doc->set('description1', content_to_text("yayayaya", false));
        $doc->set('description2', content_to_text("yaya", false));


        $indexfields = $this->get_fields_for_entries($entry);
/*        
        if (isset($indexfields['title'])) {
        	$doc->set('title', content_to_text($indexfields['title'], false));
        } else {
        	return false;
        }

        if (isset($indexfields['title'])) {
        	$doc->set('content', content_to_text($indexfields['content'], false));
        } else {
        	$doc->set('content', content_to_text('', false));
        }

        if (isset($indexfields['desc1'])) {
        	$doc->set('description1', content_to_text($indexfields['desc1'], false));
        }

		if (isset($indexfields['desc2'])) {
        	$doc->set('description2', content_to_text($indexfields['desc2'], false));
        }

        // Check if this document should be considered new.
        if (isset($options['lastindexedtime']) && ($options['lastindexedtime'] < $entry->timecreated)) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }
*/
        return $doc;
    }

    /**
     * get_fields_for_entries
     *
     * @param StdClass entry
     * @return array()
     */
    protected function get_fields_for_entries($entry) {
    	global $DB;

    	$indexfields = array();
    	
    	$validfields = array('text', 'textarea', 'menu', 'radiobutton', 'checkbox', 'multimenu', 'url');

    	$priority = array(
    			'text' => 1,
    			'textarea' => 2,
    			'menu' => 2,
    			'radiobutton' => 2,
    			'checkbox' => 3,
    			'multimenu' => 3,
    			'url' => 4
    			);

    	$sql = "SELECT * FROM {data_content} dc, {data_field} df WHERE dc.fieldid = df.id AND dc.recordid = ?;
    	$contents = $DB->get_records_sql($sql, array($entry->id));

    	var_dump($contents);
    	return $indexfields;
    }

    /**
     * Whether the user can access the document or not.
     *
     * @throws \dml_missing_record_exception
     * @throws \dml_exception
     * @param int $id Glossary entry id
     * @return bool
     */
    public function check_access($id) {
    	global $DB, $USER;

    	$now = time();
    	
        $sql = "SELECT dr.*, d.* FROM {data_records} dr
                  JOIN {data} d ON d.id = dr.dataid
                WHERE dr.id = :id";
    	$entry = $DB->get_record_sql($sql, array( 'id' => $id ), MUST_EXIST);

    	if (!$entry) {
    		return \core_search\manager::ACCESS_DELETED;
    	}

    	if (($entry->timeviewfrom && $now < $entry->timeviewfrom) || ($entry->timeviewto && $now > $entry->timeviewto)) {
    		return \core_search\manager::ACCESS_DENIED;
    	}
    	
    	if (!$entry->approved) {
    		return \core_search\manager::ACCESS_DENIED;
    	}

    	$cm = $this->get_cm('data', $entry->dataid, $entry->course);
    	$context = context_module::instance($cm->id);

    	if(!has_capability('mod/data:viewentry', $context)) {
    		return \core_search\manager::ACCESS_DENIED;
    	}
    	 
        return \core_search\manager::ACCESS_GRANTED;
    }

    /**
     * Link to database entry.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
    	$entry = $this->get_entry($doc->get('itemid'));
    	return new \moodle_url('/mod/data/view.php', array( 'd' => $entry->dataid, 'rid' => $entry->id ));
    }

    /**
     * Link to the database activity.
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_context_url(\core_search\document $doc) {
    	$entry = $this->get_entry($doc->get('itemid'));
    	return new \moodle_url('/mod/data/view.php', array('d' => $entry->dataid));
    }

    /**
     * Returns true if this area uses file indexing.
     *
     * @return bool
     */
    public function uses_file_indexing() {
    	return true;
    }
    
    /**
     * Add the database entries attachments.
     *
     * @param document $document The current document
     * @return null
     */
    public function attach_files($doc) {
    	global $DB;

    	$entryid = $doc->get('itemid');
    
    	try {
    		$entry = $this->get_entry_data($entryid);
    	} catch (\dml_missing_record_exception $e) {
    		debugging('Could not get record to attach files to '.$doc->get('id'), DEBUG_DEVELOPER);
    		return;
    	}

    	$cm = $this->get_cm('data', $entry->dataid, $doc->get('courseid'));
    	$context = \context_module::instance($cm->id);
    
    	// Get the files and attach them.
    	$fs = get_file_storage();
    	$files = $fs->get_area_files($context->id, 'mod_data', 'attachment', $entryid, "filename", false);
    	foreach ($files as $file) {
    		$doc->add_stored_file($file);
    	}
    }
    
    /*
     * Get database entry data
     * 
     * @param int $entryid
     * @return array
     */
    protected function get_entry($entryid) {
        global $DB;

        if (empty($this->entriesdata[$entryid])) {
        	$this->entriesdata[$entryid] = $DB->get_record('data_records', array( 'id'=> $entryid ), '*', IGNORE_MISSING);
        }
        
        return $this->entriesdata[$entryid];
    }
    
    /*
     * Get database entry data
     *
     * @param int $entryid
     * @return array
     */
    protected function get_database($dataid) {
    	global $DB;
    
    	if (empty($this->databaseactivitydata[$entryid])) {
    		$this->databaseactivitydata[$entryid] = $DB->get_record('data', array( 'id'=> $dataid ), '*', MUST_EXIST);
    	}

    	return $this->databaseactivitydata[$entryid];
    }
    
    

}
