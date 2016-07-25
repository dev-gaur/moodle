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
 * Database entrie global search unit tests.
 *
 * @package    mod_data
 * @category   phpunit
 * @copyright  2016 Devang Gaur
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/search/tests/fixtures/testable_core_search.php');
require_once($CFG->dirroot . '/mod/data/tests/generator/lib.php');
require_once($CFG->dirroot . '/mod/data/lib.php');


/**
 * Database entrie global search unit tests.
 *
 * @package    mod_data
 * @copyright  2016 Devang Gaur
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_advanced_search_sql_test extends advanced_testcase {

	/**
	 * @var string Area id
	 */
	protected $databaseentryareaid = null;

	public function setUp() {
		$this->resetAfterTest(true);
		set_config('enableglobalsearch', true);
	
		$this->databaseentryareaid = \core_search\manager::generate_areaid('mod_data', 'entry');
	
		// Set \core_search::instance to the mock_search_engine as we don't require the search engine to be working to test this.
		$search = testable_core_search::instance();
	}

	/**
	 * Indexing database entries contents.
	 *
	 * @return void
	 */
	public function test_data_entries_indexing() {

		// Returns the instance as long as the area is supported.
		$searcharea = \core_search\manager::get_search_area($this->databaseentryareaid);
		$this->assertInstanceOf('\mod_data\search\entry', $searcharea);

		$user1 = self::getDataGenerator()->create_user();
		$user2 = self::getDataGenerator()->create_user();
	
		$course1 = self::getDataGenerator()->create_course();
		$course2 = self::getDataGenerator()->create_course();
	
		$this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');
		$this->getDataGenerator()->enrol_user($user2->id, $course1->id, 'student');

		$record = new stdClass();
		$record->course = $course1->id;

        $this->setAdminUser();
        
        // Available for both student and teacher.
        $data1 = $this->getDataGenerator()->create_module('data', $record);

        // Excluding LatLong and Picture as we aren't indexing LatLong and Picture fields any way and its complex and not of any use to consider for this test.
        // Excluding File as we are indexing files seperately and its complex to implement.
        $fieldtypes = array( 'checkbox', 'date', 'menu', 'multimenu', 'number', 'radiobutton', 'text', 'textarea', 'url' );
		
        $count = 1;

        foreach ($fieldtypes as $fieldtype) {
        	
			// Creating variables dynamically
        	$fieldname = 'field-'.$count;
        	$record = new StdClass();
			$record->name = 'field-'.$count;
			$record->type = $fieldtype;

			${$fieldname} = $this->getDataGenerator()->get_plugin_generator('mod_data')->create_field($record, $data1);

			$count++;
        }

        // Create Record
        $record = new stdClass();
        
        // Content Values of the Record
		$contents = array();        
        
        $data1record1 = $this->getDataGenerator()->get_plugin_generator('mod_data')->create_record($record);


        // All records.
        $recordset = $searcharea->get_recordset_by_timestamp(0);
        $this->assertTrue($recordset->valid());
        $nrecords = 0;
        foreach ($recordset as $record) {
        	$this->assertInstanceOf('stdClass', $record);
        	$doc = $searcharea->get_document($record);
        	$this->assertInstanceOf('\core_search\document', $doc);
        	$nrecords++;
        }
        
        // If there would be an error/failure in the foreach above the recordset would be closed on shutdown.
        $recordset->close();
        $this->assertEquals(3, $nrecords);
        
        // The +2 is to prevent race conditions.
        $recordset = $searcharea->get_recordset_by_timestamp(time() + 2);
        
        // No new records.
        $this->assertFalse($recordset->valid());
        $recordset->close();
	}

	/**
	 * Document contents.
	 *
	 * @return void
	 */
	public function test_data_entries_document() {

		// Returns the instance as long as the area is supported.
		$searcharea = \core_search\manager::get_search_area($this->databaseentryareaid);
		$this->assertInstanceOf('\mod_data\search\entry', $searcharea);
		
		$user1 = self::getDataGenerator()->create_user();
		$user2 = self::getDataGenerator()->create_user();
		
		$course = self::getDataGenerator()->create_course();
		
		$this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
		$this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
		
		$record = new stdClass();
		$record->course = $course->id;
		
		$this->setAdminUser();
		
		// Available for both student and teacher.
		$data1 = $this->getDataGenerator()->create_module('data', $record);
		
		// Excluding LatLong and Picture as we aren't indexing LatLong and Picture fields any way and its complex and not of any use to consider for this test.
		// Excluding File as we are indexing files seperately and its complex to implement.
		$fieldtypes = array( 'checkbox', 'date', 'menu', 'multimenu', 'number', 'radiobutton', 'text', 'textarea', 'url' );
		
		$count = 1;
		
		foreach ($fieldtypes as $fieldtype) {
			 
			// Creating variables dynamically
			$fieldname = 'field-'.$count;
			$record = new StdClass();
			$record->name = 'field-'.$count;
			$record->type = $fieldtype;
		
			${$fieldname} = $this->getDataGenerator()->get_plugin_generator('mod_data')->create_field($record, $data1);
		
			$count++;
		}
		
		// Create Record
		$record = new stdClass();
		
		// Content Values of the Record
		$contents = array();
		
		$data1record1 = $this->getDataGenerator()->get_plugin_generator('mod_data')->create_record($record);

		$doc = $searcharea->get_document($data1record1);
		$this->assertInstanceOf('\core_search\document', $doc);
		$this->assertEquals($course->id, $doc->get('itemid'));
		$this->assertEquals($this->databaseentryareaid . '-' . $record->id, $doc->get('id'));
		$this->assertEquals($course->id, $doc->get('courseid'));
		$this->assertEquals($data1record1->userid, $doc->get('userid'));
		
	}


	/**
	 * Document accesses.
	 *
	 * @return void
	 */
	public function test_mycourses_access() {
		// Returns the instance as long as the area is supported.
		$searcharea = \core_search\manager::get_search_area($this->databaseentryareaid);
		$this->assertInstanceOf('\mod_data\search\entry', $searcharea);
		
		$user1 = self::getDataGenerator()->create_user();
		$user2 = self::getDataGenerator()->create_user();
		
		$course1 = self::getDataGenerator()->create_course();
		$course2 = self::getDataGenerator()->create_course();
		
		$this->getDataGenerator()->enrol_user($user1->id, $course1->id, 'student');
		$this->getDataGenerator()->enrol_user($user2->id, $course2->id, 'student');
		
		$record = new stdClass();
		$record->course = $course1->id;

		$this->setAdminUser();

		// Available for both student and teacher.
		$data1 = $this->getDataGenerator()->create_module('data', $record);
		
		// Excluding LatLong and Picture as we aren't indexing LatLong and Picture fields any way and its complex and not of any use to consider for this test.
		// Excluding File as we are indexing files seperately and its complex to implement.
		$fieldtypes = array( 'checkbox', 'date', 'menu', 'multimenu', 'number', 'radiobutton', 'text', 'textarea', 'url' );
		
		$count = 1;
		
		foreach ($fieldtypes as $fieldtype) {
		
			// Creating variables dynamically
			$fieldname = 'field-'.$count;
			$record = new StdClass();
			$record->name = 'field-'.$count;
			$record->type = $fieldtype;
		
			${$fieldname} = $this->getDataGenerator()->get_plugin_generator('mod_data')->create_field($record, $data1);
			$count++;
		}
		
		// Create Record
		$record = new stdClass();
		
		// Content Values of the Record
		$contents = array();
		
		$data1record1 = $this->getDataGenerator()->get_plugin_generator('mod_data')->create_record($record);

		$this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($data1record1->id));
		$this->assertEquals(\core_search\manager::ACCESS_DELETED, $searcharea->check_access(-123));
		
		$this->setGuestUser();
		$this->assertEquals(\core_search\manager::ACCESS_DENIED, $searcharea->check_access($data1record1->id));
		
		$this->setUser($user1);
		$this->assertEquals(\core_search\manager::ACCESS_GRANTED, $searcharea->check_access($data1record1->id));

		$this->setUser($user2);
		$this->assertEquals(\core_search\manager::ACCESS_DENIED, $searcharea->check_access($data1record1->id));

	}
	/**
	 * Test for post attachments.
	 *
	 * @return void
	 */
	public function test_attach_files() {
		global $DB;
	
		$fs = get_file_storage();
	
		// Returns the instance as long as the area is supported.
		$searcharea = \core_search\manager::get_search_area($this->databaseentryareaid);
		$this->assertInstanceOf('\mod_data\search\entry', $searcharea);
	
		$user1 = self::getDataGenerator()->create_user();
		$user2 = self::getDataGenerator()->create_user();
	
		$course = self::getDataGenerator()->create_course();
	
		$this->getDataGenerator()->enrol_user($user1->id, $course->id, 'student');
		$this->getDataGenerator()->enrol_user($user2->id, $course->id, 'student');
	
		$record = new stdClass();
		$record->course = $course->id;
	
		$data1 = self::getDataGenerator()->create_module('data', $record);

		// Create discussion1.
		$record = new stdClass();
		$record->course = $course1->id;
		$record->userid = $user1->id;
		$record->forum = $forum1->id;
		$record->message = 'discussion';
		$record->attachemt = 1;
		$entry = self::getDataGenerator()->get_plugin_generator('mod_data')->create_entry($record);

		// Attach 2 file to the discussion post.
		$entry = $DB->get_record('data_records', array('dataid' => $data1->id));
		$filerecord = array(
				'contextid' => context_module::instance($data1->cmid)->id,
				'component' => 'mod_data',
				'filearea'  => 'attachment',
				'itemid'    => $post->id,
				'filepath'  => '/',
				'filename'  => 'myfile1'
		);
		$file1 = $fs->create_file_from_string($filerecord, 'Some contents 1');
		$filerecord['filename'] = 'myfile2';
		$file2 = $fs->create_file_from_string($filerecord, 'Some contents 2');
	
		// Create post1 in discussion1.
		$record = new stdClass();
		$record->discussion = $discussion1->id;
		$record->parent = $discussion1->firstpost;
		$record->userid = $user2->id;
		$record->message = 'post2';
		$record->attachemt = 1;
		$discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post($record);
	
		$filerecord['itemid'] = $discussion1reply1->id;
		$filerecord['filename'] = 'myfile3';
		$file3 = $fs->create_file_from_string($filerecord, 'Some contents 3');
	
		// Create post2 in discussion1.
		$record = new stdClass();
		$record->discussion = $discussion1->id;
		$record->parent = $discussion1->firstpost;
		$record->userid = $user2->id;
		$record->message = 'post3';
		$discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_post($record);
	
		// Now get all the posts and see if they have the right files attached.
		$searcharea = \core_search\manager::get_search_area($this->forumpostareaid);
		$recordset = $searcharea->get_recordset_by_timestamp(0);
		$nrecords = 0;
		foreach ($recordset as $record) {
			$doc = $searcharea->get_document($record);
			$searcharea->attach_files($doc);
			$files = $doc->get_files();
			// Now check that each doc has the right files on it.
			switch ($doc->get('itemid')) {
				case ($post->id):
					$this->assertCount(2, $files);
					$this->assertEquals($file1->get_id(), $files[$file1->get_id()]->get_id());
					$this->assertEquals($file2->get_id(), $files[$file2->get_id()]->get_id());
					break;
				case ($discussion1reply1->id):
					$this->assertCount(1, $files);
					$this->assertEquals($file3->get_id(), $files[$file3->get_id()]->get_id());
					break;
				case ($discussion1reply2->id):
					$this->assertCount(0, $files);
					break;
				default:
					$this->fail('Unexpected post returned');
					break;
			}
			$nrecords++;
		}
		$recordset->close();
		$this->assertEquals(3, $nrecords);
	}

}