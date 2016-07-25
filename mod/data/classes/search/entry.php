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

use DOMDocument;
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

        $indexfields = $this->get_fields_for_entries($entry);

        if(sizeof($indexfields)) {
        	$doc->set('title', $indexfields[0]);
        } else {
        	return false;
        }

        if (sizeof($indexfields) >= 2) {
        	$doc->set('content', $indexfields[1]); 
        } else {
        	return false;
        }

        if (isset($indexfields[2])) {
        	$doc->set('description1', $indexfields[2]);
        }

        if (isset($indexfields[3])) {
        	$doc->set('description2', $indexfields[3]);
        }

        return $doc;
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
    	
        $sql = 'SELECT dr.*, d.* FROM {data_records} dr JOIN {data} d ON d.id = dr.dataid WHERE dr.id = ?';
    	$entry = $DB->get_record_sql($sql, array( $id ), IGNORE_MISSING);

    	if (!$entry) {
    		return \core_search\manager::ACCESS_DELETED;
    	}

    	if (($entry->timeviewfrom && $now < $entry->timeviewfrom) || ($entry->timeviewto && $now > $entry->timeviewto)) {
    		return \core_search\manager::ACCESS_DENIED;
    	}
    	
    	if (!$entry->approved) {
    		return \core_search\manager::ACCESS_DENIED;
    	}

    	//$cm = $this->get_cm('data', $entry->dataid, $entry->course);
    	$cm = get_coursemodule_from_instance('data', $this->data->id);
    	$context = \context_module::instance($cm->id);

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
    		$entry = $this->get_entry($entryid);
    	} catch (\dml_missing_record_exception $e) {
    		debugging('Could not get record to attach files to '.$doc->get('id'), DEBUG_DEVELOPER);
    		return;
    	}

    	$cm = $this->get_cm('data', $entry->dataid, $doc->get('courseid'));
    	$context = \context_module::instance($cm->id);
    
    	// Get the files and attach them.
    	$fs = get_file_storage();
    	$files = $fs->get_area_files($context->id, 'mod_data', 'attachment', $entryid, 'filename', false);
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
    	
    	$fieldtypepriorities = array(
    			'text' => 4,
    			'textarea' => 3,
    			'menu' => 3,
    			'radiobutton' => 3,
    			'checkbox' => 2,
    			'multimenu' => 2,
    			'url' => 1
    		);

    	 
    	$sql = 'SELECT dc.id, dc.content, df.name as fldname, df.type as fldtype, df.required FROM {data_content} dc, {data_fields} df WHERE dc.fieldid = df.id AND dc.recordid = ?';
    	$contents = $DB->get_records_sql($sql, array($entry->id));
    	$filteredcontents = array();

		// Filtering out the data_content records having invalid fieldtypes.
    	foreach ($contents as $content) {
    		if (in_array($content->fldtype, $validfields)) {
    			$filteredcontents[] = $content;
    		}
    	}
    	
    	$validfieldsname = array();
    	foreach ($filteredcontents as $content) {
    		$content->priority = $fieldtypepriorities[$content->fldtype];
    		$validfieldsnames[] = $content->fldname;
    	}

    	// Array to describe the order of the selected fields.
    	$fieldorder = array();

    	// Retrieving order of fields from the 'Add Entry template' of the database.
    	$dom = new DOMDocument();

    	$template = $DB->get_record_sql('SELECT addtemplate FROM {data} WHERE id = ?', array($entry->dataid));
    	$template = $template->addtemplate;

    	$dom->loadHTML($template);
    	$dom->preserveWhiteSpace = false;

    	$rowsintemplate = $dom->getElementsByTagName('tr');
    	 
    	$numberofrowsintemplate = $rowsintemplate->length;
    	
    	foreach ($rowsintemplate as $row) {
    	
    		$txtContent = $row->childNodes[2]->textContent;
    		$txtContent = rtrim($txtContent, ']]');
    		$txtContent = ltrim($txtContent, '[[');

    		if(in_array($txtContent, $validfieldsnames)){
    			$fieldorder[] = $txtContent;
    		}
    	}

    	// Removing all the duplicate fieldname entries from the order.
    	$fieldorder = array_unique($fieldorder);
    	
    	$fieldorderqueue = new \SPLPriorityQueue();

    	foreach ($filteredcontents as $content) {
    		
    		$fieldorderqueue->insert($content, sizeof($fieldorder) - array_search($content->fldname, $fieldorder));
    	}    	

    	$filteredcontents = array();
    	
    	while ($fieldorderqueue->valid()) {
    		$filteredcontents[] = $fieldorderqueue->extract();
    	}

    	// Using a PriorityQueure instance to sort out the filtered contents according to these rules :
    	// 1. Priorities in $fieldtypepriorities
    	// 2. Compulsory fieldtypes are to be given the top priority.
    	$sortedcontentqueue = new SortedContentQueue($filteredcontents);

    	foreach ($filteredcontents as $content) {
    		$sortedcontentqueue->insert($content, array_search($content, $filteredcontents));
    	}

    	while ($sortedcontentqueue->valid()) {

    		$content = $sortedcontentqueue->extract();
    		$fieldvalue = '';
    		
    		if($content->fldtype === 'multimenu' || $content->fldtype === 'checkbox') {
    			$arr = explode('##', $content->content);

    			foreach ($arr as $a) {
    				$fieldvalue .= $a.' ';
    			}

    			$fieldvalue = trim($fieldvalue);

    		} elseif ($content->fldtype === 'textarea') {
    			$fieldvalue = clean_param($content->content, PARAM_NOTAGS);
    		} else {
    			$fieldvalue = trim($content->content);
    		}

    		$indexfields[] = $fieldvalue;
    	}

    	
    	return $indexfields;
    }

}

class SortedContentQueue extends \SPLPriorityQueue {
	
	private $contents;

	function __construct($contents) {
		$this->contents = $contents;
	}

	public function compare($key1 , $key2) {
		$record1 = $this->contents[$key1];
		$record2 = $this->contents[$key2];
		
		//if a contents' fieldtype is compulsory in the database than it would be given more priority that any other uncompulsory content
		if ( ($record1->required && $record2->required) || (!$record1->required && !$record2->required)) {
			if ($record1->priority === $record2->priority) {
				return 0;
			}
			
			return $record1->priority < $record2->priority ? -1: 1;

		} elseif ($record1->required && !$record2->required) {
			return 1;
		} else {
			return -1;
		}
	}
}