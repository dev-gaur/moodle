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
 * Course global search unit tests.
 *
 * @package     core
 * @category    phpunit
 * @copyright   2016 Devang Gaur
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/data/lib.php');

/**
 * Course global search unit tests.
 *
 * @package     core
 * @category    phpunit
 * @copyright   2016 Devang Gaur
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_data_generator extends testing_module_generator {

	/**
	 * @var int keep track of how many database fields have been created.
	 */
	protected $databasefieldcount = 0;
	
	/**
	 * @var int keep track of how many database records have been created.
	 */
	protected $databaserecordcount = 0;
	
	/**
	 * @array The field types which not handled by the generator as of now.
	 */
	protected $ignoredfieldtypes = array('latlong', 'file', 'picture');
    /**
     * To be called from data reset code only,
     * do not use in tests.
     * @return void
     */
    public function reset() {
        $this->databasefieldcount = 0;
        $this->databaserecordcount = 0;

        parent::reset();
    }
    
    
    public function create_instance($record = null, array $options = null) {
        $record = (object)(array)$record;

        if (!isset($record->assessed)) {
            $record->assessed = 0;
        }
        if (!isset($record->scale)) {
            $record->scale = 0;
        }

        return parent::create_instance($record, (array)$options);
    }

    public function create_field($record = null, $data = null) {
		global $DB;

		if (in_array($record['type'], $this->ignoredfieldtypes)) {
			return false;
		}
		$this->databasefieldcount++;

    	$record = (array) $record;

        if (!isset($data->course)) {
            throw new coding_exception('course must be present in phpunit_util::create_field() $data');
        }

        if (!isset($data->id)) {
            throw new coding_exception('dataid must be present in phpunit_util::create_field() $data');
        }

        if (!isset($record['type'])) {
            throw new coding_exception('type must be present in phpunit_util::create_field() $record');
        }

        if (!isset($record['required'])) {
            $record['required'] = 0;
        }

        if (!isset($record['name'])) {
            $record['name'] = "testField - " . $this->databasefieldcount;
        }

        if (!isset($record['description'])) {
        	$record['description'] = " This is testField - " . $this->databasefieldcount;
        }

        if (!isset($record['param1'])) {
        	
        	if (($record['type'] === 'menu') || ($record['type'] === 'menu') || ($record['type'] === 'menu') || ($record['type'] === 'radiobutton')) {
        		$record['param1'] = 'one\ntwo\nthree\nfour';
        	} elseif (($record['type'] === 'textarea')) {
        		$record['param1'] = 'Test Textarea - ' . $this->databasefieldcount;
        	} elseif (($record['type'] === 'text')) {
        		$record['param1'] = 'Test Text - ' . $this->databasefieldcount;
        	} else {
        		$record['param1'] = '';
        	}
        }

        if (!isset($record['param2'])) {
        	 
        	if ($record['type'] === 'textarea') {
        		$record['param2'] = 60;
        	} elseif (($record['type'] === 'file') || ($record['type'] === 'picture')) {
        		$record['param2'] = 0;
        	} else {
        		$record['param2'] = '';
        	}
        }

        if (!isset($record['param3'])) {
        
        	if (($record['type'] === 'textarea')) {
        		$record['param3'] = 35;
        	} else {
        		$record['param3'] = '';
        	}
        }

        if (!isset($record['param4'])) {

        	if (($record['type'] === 'textarea')) {
        		$record['param4'] = 0;
        	}        
		}
		
		if (!isset($record['param5'])) {
			$record['param5'] = '';
		}

        $record = (object) $record;

        $fieldobj = data_get_field($record, $data);
        $fieldobj->insert_field();

        return $fieldobj;
    }
    

    public function create_entry($data, $contents) {

    	$this->databaserecordcount++;

    	$recordid = data_add_record($data);
    	
    	$fields = $DB->get_records('data_fields', array( 'dataid' => $data->id));

    	// validating whether required field are filled.
    	foreach ($fields as $field) {
    		$fieldhascontent = false;    		
    		
    		if (in_array($field->type, $this->ignoredfields)) {
    			continue;
    		}

    		if($field->notemptyfield ($contents[$field->id], 'field_'.$field->id.'_0')) {
    			$fieldhascontent = true;
    		}
    		
    		if ($field->required && !$fieldhascontent) {
    			return false;
    		}
    	}

    	foreach ($contents as $fieldid => $content) {
    		
    		$field = $DB->get_record('data_fields', array( 'id' => $fieldid));
    		$field = data_get_field($field, $data);

    		
    		if (in_array($field->field->type, $this->ignoredfields)) {
    			continue;
    		}
    		
    		if($field->type === 'date') {
    			$values = array();

    			$temp = explode('-', $content, 3);
    			
    			$values['field_'.$field->id.'_day'] = $temp[0];
    			$values['field_'.$field->id.'_month'] = $temp[1];
    			$values['field_'.$field->id.'_year'] = $temp[2];
    			
    			foreach ($values as $fieldname => $value) {
    				$field->update_content($recordid, trim($value), $fieldname);
    			}

    			continue;
    		}

    		if($field->type === 'textarea') {
    			$values = array();

    			$values['field_'.$fieldid] = $content;
    			$values['field_'.$fieldid.'_content1'] = 1;
    			
    			foreach ($values as $fieldname => $value) {
    				$field->update_content($recordid, $value, $fieldname);
    			}

    			continue;
    		}
    		
    		if($field->type === 'url') {
    			$values = array();

    			if (is_array($content)) {
    				foreach ($content as $key => $value) {
    					$values['field_'.$fieldid.'_'.$key] = $value;
    				}
    			} else {
    				$values['field_'.$fieldid.'_0'] = $content;
    			}
    			
    			foreach ($values as $fieldname => $value) {
    				$field->update_content($recordid, $value, $fieldname);
    			}
    			
    			continue;
    		}
    		
    		$field->update_content($recordid, $contents[$field->field->id]);
    	}

    	return $recordid;
    	
    }


}
