<?php

/*-------------------------------------------------------+
| PHP-Fusion Content Management System
| Copyright (C) PHP-Fusion Inc
| http://www.php-fusion.co.uk/
+--------------------------------------------------------+
| Filename: QuantumFields.class.php
| Author: PHP-Fusion Inc
| Co-Author: PHP-Fusion Development Team
+--------------------------------------------------------+
| This program is released as free software under the
| Affero GPL license. You can redistribute it and/or
| modify it under the terms of this license which you
| can read by viewing the included agpl.txt or online
| at www.gnu.org/licenses/agpl.html. Removal of this
| copyright header is strictly prohibited without
| written permission from the original author(s).
+--------------------------------------------------------*/
require_once LOCALE.LOCALESET.'admin/fields.php';
class quantumFields {
	// Setup XUI
	public $system_title = '';
	public $admin_rights = '';
	public $locale_file = '';
	public $category_db = '';
	public $field_db = '';
	public $plugin_folder = '';
	public $plugin_locale_folder = '';
	public $debug = FALSE;
	// System Internals
	private $max_rows = 0;
	private $locale = array();
	private $page_list = array();
	private $cat_list = array();
	private $page = array();
	private $fields = array(); // maybe can mix with enabled_fields.
	private $enabled_fields = array();
	private $available_fields = array();
	private $available_field_info = array();
	private $user_field_dbinfo = '';

	/* Constructor */
	public function boot() {
		global $locale;
		$this->locale = $locale;
		add_to_breadcrumbs(array('link' => '', 'title' => $this->system_title));
		add_to_title(': '.$this->system_title);
		$this->verify_field_tables();
		$this->load_data();
		$this->load_field_cats();
		$this->move_fields();
		$this->available_fields();
		$this->render_fields();
	}

	/* System integrity check and repairs */
	private function verify_field_tables() {
		if (!db_exists($this->category_db)) {
			// build the table if not exist.
			$result = dbquery("CREATE TABLE ".$this->category_db." (
				field_cat_id MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT ,
				field_cat_name VARCHAR(200) NOT NULL ,
				field_parent MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '0',
				field_cat_db VARCHAR(100) NOT NULL,
				field_cat_index VARCHAR(200) NOT NULL,
				field_cat_class VARCHAR(50) NOT NULL,
				field_cat_order SMALLINT(5) UNSIGNED NOT NULL ,
				PRIMARY KEY (field_cat_id)
				) ENGINE=MyISAM DEFAULT CHARSET=UTF8 COLLATE=utf8_unicode_ci");
		}
		// build the table if not exist.
		if (!db_exists($this->field_db)) {
			$result = dbquery("CREATE TABLE ".$this->field_db." (
				field_id MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,
				field_title VARCHAR(50) NOT NULL,
				field_name VARCHAR(50) NOT NULL,
				field_cat MEDIUMINT(8) UNSIGNED NOT NULL DEFAULT '1',
				field_type VARCHAR(25) NOT NULL,
				field_default TEXT NOT NULL,
				field_options TEXT NOT NULL,
				field_error VARCHAR(50) NOT NULL,
				field_required TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
				field_log TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
				field_registration TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
				field_order SMALLINT(5) UNSIGNED NOT NULL DEFAULT '0',
				field_config TEXT NOT NULL,
				PRIMARY KEY (field_id),
				KEY field_order (field_order)
				) ENGINE=MyISAM DEFAULT CHARSET=UTF8 COLLATE=utf8_unicode_ci");
		}
	}

	/* Returns array structure for render */
	public function load_data() {
		// get the page first.
		$this->page = dbquery_tree_full($this->category_db, 'field_cat_id', 'field_parent', "ORDER BY field_cat_order ASC");
		$result = dbquery("SELECT field.*, cat.* FROM
		".$this->field_db." field
		LEFT JOIN ".$this->category_db." cat on (cat.field_cat_id = field.field_cat)
		ORDER BY cat.field_cat_order ASC, field.field_order ASC
		");
		$this->max_rows = dbrows($result);
		if ($this->max_rows > 0) {
			while ($data = dbarray($result)) {
				$this->fields[$data['field_cat']][] = $data;
			}
		}
	}

	public function render_fields() {
		global $aidlink;
		$locale = $this->locale;
		if ($this->debug) print_p($_POST);
		opentable($this->system_title);
		echo "<div class='row'>\n";
		echo "<div class='col-xs-12 col-sm-8 col-md-8 col-lg-8'>\n";
		if (!empty($this->page[0])) {
			foreach ($this->page[0] as $page_id => $page_data) {
				$tab_title['title'][$page_id] = $page_data['field_cat_name'];
				$tab_title['id'][$page_id] = $page_id;
				$tab_title['icon'][$page_id] = '';
			}
			$tab_active = tab_active($tab_title, 1);
			echo opentab($tab_title, $tab_active, 'uftab');
			foreach ($this->page[0] as $page_id => $page_details) {
				echo opentabbody($tab_title['title'][$page_id], $tab_title['id'][$page_id], $tab_active);
				// load all categories here.
				if ($this->debug) {
					echo "<div class='m-t-20 text-dark'>\n";
					if ($page_id == 1) {
						echo "This page adds field directly into Table: DB_USERS";
					} else {
						echo "This page adds fields directly into Table: <strong>".$page_details['field_cat_db']."</strong>,
					combining itself with DB_USERS via column <strong>".$page_details['field_cat_index']."</strong>";
					}
					echo "</div>\n";
				}
				if (isset($this->page[$page_id])) {
					echo "<div class='clearfix m-t-20'>\n";
					$i = 0;
					$counter = count($this->page[$page_id])-1;
					foreach ($this->page[$page_id] as $cat_id => $field_cat) {
						// field category information
						if ($this->debug) print_p($field_cat);
						echo "<div class='clearfix'>\n";
						echo form_para($field_cat['field_cat_name'], $cat_id.'-'.$field_cat['field_cat_name'], 'profile_category_name display-inline-block pull-left');
						echo "<div class='pull-left m-t-10 m-l-10'>\n";
						if ($i != 0) echo "<a class='text-smaller' href='".FUSION_SELF.$aidlink."&amp;action=cmu&amp;cat_id=".$cat_id."&amp;parent_id=".$field_cat['field_parent']."&amp;order=".($field_cat['field_cat_order']-1)."'>Move Up</a> - ";
						if ($i !== $counter) echo "<a class='text-smaller' href='".FUSION_SELF.$aidlink."&amp;action=cmd&amp;cat_id=".$cat_id."&amp;parent_id=".$field_cat['field_parent']."&amp;order=".($field_cat['field_cat_order']+1)."'>Move Down</a> - ";
						echo "<a class='text-smaller' href='".FUSION_SELF.$aidlink."&amp;action=cat_edit&amp;cat_id=".$cat_id."'>Edit</a>";
						echo "</div>\n";
						echo "</div>\n";
						if (isset($this->fields[$cat_id])) {
							$k = 0;
							$item_counter = count($this->fields[$cat_id])-1;
							foreach ($this->fields[$cat_id] as $arr => $field_data) {
								if ($this->debug) print_p($field_data);
								//print_p($field_data);
								echo "<div class='text-left'>\n";
								if ($k != 0) echo "<a class='text-smaller' href='".FUSION_SELF.$aidlink."&amp;action=fmu&amp;parent_id=".$field_data['field_cat']."&amp;field_id=".$field_data['field_id']."&amp;order=".($field_data['field_order']-1)."'>Move Up</a> - ";
								if ($k !== $item_counter) echo "<a class='text-smaller' href='".FUSION_SELF.$aidlink."&amp;action=fmd&amp;parent_id=".$field_data['field_cat']."&amp;field_id=".$field_data['field_id']."&amp;order=".($field_data['field_order']+1)."'>Move Down</a> - ";
								if ($field_data['field_type'] == 'file') {
									echo "<a class='text-smaller' href='".FUSION_SELF.$aidlink."&amp;action=module_edit&amp;module_id=".$field_data['field_id']."'>Edit</a>";
								} else {
									echo "<a class='text-smaller' href='".FUSION_SELF.$aidlink."&amp;action=field_edit&amp;field_id=".$field_data['field_id']."'>Edit</a>";
								}
								echo "</div>\n";
								echo $this->phpfusion_field_DOM($field_data);
								$k++;
							}
						}
						$i++;
					}
					echo "</div>\n";
				} else {
					// display no category
					echo "<div class='m-t-20 well text-center'>There are no fields added in ".$page_details['field_cat_name']."</div>\n";
				}
				echo closetabbody();
			}
			echo closetab();
		} else {
			echo "<div class='well text-center'>There are no page created. Please add a page by creating a page as category.</div>\n";
		}
		echo "</div>\n<div class='col-xs-12 col-sm-4 col-md-4 col-lg-4'>\n";
		$this->phpfusion_field_buttons();
		echo "</div>\n";
		closetable();
	}

	private function move_fields() {
		global $aidlink;
		if (isset($_GET['action']) && isset($_GET['order']) && isnum($_GET['order']) && isset($_GET['parent_id']) && isnum($_GET['parent_id'])) {
			if (isset($_GET['cat_id']) && isnum($_GET['cat_id']) && ($_GET['action'] == 'cmu' or $_GET['action'] == 'cmd')) {
				echo 'ya';
				$data = dbarray(dbquery("SELECT field_cat_id FROM ".DB_USER_FIELD_CATS." WHERE field_parent='".intval($_GET['parent_id'])."' AND field_cat_order='".intval($_GET['order'])."'")); // more than 1.
				if ($_GET['action'] == 'cmu') { // category move up.
					if (!$this->debug) $result = dbquery("UPDATE ".DB_USER_FIELD_CATS." SET field_cat_order=field_cat_order+1 WHERE field_cat_id='".$data['field_cat_id']."'");
					if (!$this->debug) $result = dbquery("UPDATE ".DB_USER_FIELD_CATS." SET field_cat_order=field_cat_order-1 WHERE field_cat_id='".$_GET['cat_id']."'");
				} elseif ($_GET['action'] == 'cmd') {
					if (!$this->debug) $result = dbquery("UPDATE ".DB_USER_FIELD_CATS." SET field_cat_order=field_cat_order-1 WHERE field_cat_id='".$data['field_cat_id']."'");
					if (!$this->debug) $result = dbquery("UPDATE ".DB_USER_FIELD_CATS." SET field_cat_order=field_cat_order+1 WHERE field_cat_id='".$_GET['cat_id']."'");
				}
				if (!$this->debug) redirect(FUSION_SELF.$aidlink);
			} elseif (isset($_GET['field_id']) && isnum($_GET['field_id']) && ($_GET['action'] == 'fmu' or $_GET['action'] == 'fmd')) {
				$data = dbarray(dbquery("SELECT field_id FROM ".DB_USER_FIELDS." WHERE field_cat='".intval($_GET['parent_id'])."' AND field_order='".intval($_GET['order'])."'"));
				if ($_GET['action'] == 'fmu') { // field move up.
					if (!$this->debug) $result = dbquery("UPDATE ".DB_USER_FIELDS." SET field_order=field_order+1 WHERE field_id='".$data['field_id']."'");
					if (!$this->debug) $result = dbquery("UPDATE ".DB_USER_FIELDS." SET field_order=field_order-1 WHERE field_id='".$_GET['field_id']."'");
					if ($this->debug) print_p("Move Field ID ".$_GET['field_id']." Up a slot and Field ID ".$data['field_id']." down a slot.");
				} elseif ($_GET['action'] == 'fmd') {
					if (!$this->debug) $result = dbquery("UPDATE ".DB_USER_FIELDS." SET field_order=field_order-1 WHERE field_id='".$data['field_id']."'");
					if (!$this->debug) $result = dbquery("UPDATE ".DB_USER_FIELDS." SET field_order=field_order+1 WHERE field_id='".$_GET['field_id']."'");
					if ($this->debug) print_p("Move Field ID ".$_GET['field_id']." down a slot and Field ID ".$data['field_id']." up a slot.");
				}
				if (!$this->debug) redirect(FUSION_SELF.$aidlink);
			}
		}
	}

	/* Returns $cat_list */
	private function load_field_cats() {
		// Load Field Cats
		$result = dbquery("SELECT * FROM ".$this->category_db." WHERE field_parent='0' ORDER BY field_cat_order ASC");
		if (dbrows($result) > 0) {
			while ($list_data = dbarray($result)) {
				$this->page_list[$list_data['field_cat_id']] = $list_data['field_cat_name'];
			}
		}
		$result = dbquery("SELECT * FROM ".$this->category_db." WHERE field_parent !='0' ORDER BY field_cat_order ASC");
		if (dbrows($result) > 0) {
			while ($list_data = dbarray($result)) {
				$this->cat_list[$list_data['field_cat_id']] = $list_data['field_cat_name'];
			}
		}
	}

	/* Hardcoded Column Attributes - Can be added to forms but is it too technical for non coders? */
	private function dynamics_fieldinfo($type, $default_value) {
		$info = array('textbox' => "VARCHAR(200) NOT NULL DEFAULT '".$default_value."'",
			'select' => "VARCHAR(200) NOT NULL DEFAULT '".$default_value."'",
			'textarea' => "TEXT NOT NULL",
			'checkbox' => "TINYINT(3) NOT NULL DEFAULT '".(isnum($default_value) ? $default_value : 0)."'",
			'toggle' => "TINYINT(3) NOT NULL DEFAULT '".(isnum($default_value) ? $default_value : 0)."'",
			'datepicker' => "TINYINT(10) NOT NULL DEFAULT '".(isnum($default_value) ? $default_value : 0)."'",
			'colorpicker' => "VARCHAR(10) NOT NULL DEFAULT '".$default_value."'",
			'upload' => "VARCHAR(100) NOT NULL DEFAULT '".$default_value."'",
			'hidden' => "VARCHAR(50) NOT NULL DEFAULT '".$default_value."'",
			'address' => "TEXT NOT NULL",);
		return $info[$type];
	}

	/* The Current Stable PHP-Fusion Dynamics Module */
	private function dynamics_type() {
		$locale = $this->locale;
		return array('file' => 'Module',
			'textbox' => 'Textbox',
			'select' => 'Dropdown',
			'textarea' => 'Textarea',
			'checkbox' => 'Checkbox',
			'toggle' => 'Switch',
			'datepicker' => 'Date',
			'colorpicker' => 'Color',
			'upload' => 'File',
			'hidden' => 'Hidden',
			'address' => 'Address');
	}

	private function synthesize_fields($data, $type = 'dynamics') {
		global $aidlink, $defender;
		$field_attr = '';
		if ($type == 'dynamics') {
			$field_attr = $this->dynamics_fieldinfo($data['field_type'], $data['field_default']);
		} elseif ($type == 'module') {
			$field_attr = $this->user_field_dbinfo;
		}

		$max_order = dbresult(dbquery("SELECT MAX(field_order) FROM ".$this->field_db." WHERE field_cat='".$data['field_cat']."'"), 0)+1;
		if ($data['field_order'] == 0 or $data['field_order'] > $max_order) {
			$data['field_order'] = $max_order;
		}

		$rows = dbcount("(field_id)", $this->field_db, "field_id='".$data['field_id']."'");
		if ($rows) {
			if ($this->debug) print_p('Update mode');
			// update
			// Alter DB_USER_FIELDS table - change and modify column.
			$old_record = dbquery("SELECT uf.*, cat.field_cat_id, cat.field_parent, cat.field_cat_order, root.field_cat_db, root.field_cat_index
									FROM ".$this->field_db." uf
									LEFT JOIN ".$this->category_db." cat ON (cat.field_cat_id = uf.field_cat)
									LEFT JOIN ".$this->category_db." root ON (cat.field_parent = root.field_cat_id)
									WHERE uf.field_id='".$data['field_id']."'"); // old database.
			if (dbrows($old_record) > 0) { // got old field cat
				$cat_data = dbarray($old_record);
				if ($this->debug) print_p($cat_data);
				$old_database = $cat_data['field_cat_db'] ? DB_PREFIX.$cat_data['field_cat_db'] : DB_USERS; // this was old database
				$field_arrays = fieldgenerator($old_database);
				// now check the new one fetch on new cat.
				$new_result = dbquery("SELECT cat.field_cat_id, cat.field_parent, cat.field_cat_order, root.field_cat_db, root.field_cat_index
						FROM ".$this->category_db." cat
						LEFT JOIN ".$this->category_db." root on (cat.field_parent = root.field_cat_id)
						WHERE cat.field_cat_id='".$data['field_cat']."'");
				if (dbrows($new_result) > 0) { // cat found.
					$new_cat_data = dbarray($new_result);
					$target_database = $new_cat_data['field_cat_db'] ? DB_PREFIX.$new_cat_data['field_cat_db'] : DB_USERS;
				} else {
					$target_database = DB_USERS;
				}
				if ($data['field_cat'] !== $cat_data['field_cat']) { // old and new mismatch - move to another category
					if ($this->debug) print_p("Path 1 Update Field");
					// drop the old one if target database aren't the same.
					if ($target_database !== $old_database) {
						if (!$this->debug) $result = dbquery("ALTER TABLE ".$old_database." DROP ".$cat_data['field_name']); // drop the old one.
						if ($this->debug) print_p("Dropping ".$old_database." with ".$cat_data['field_name']);
					}
					$field_arrays = fieldgenerator($target_database);
					if (!in_array($data['field_name'], $field_arrays)) { // this is new database check, if not exist, then add the column
						if (!$this->debug) $result = dbquery("ALTER TABLE ".$target_database." ADD ".$data['field_name']." ".$field_attr); // create the new one.
						if ($this->debug) print_p("ADD ".$target_database." with ".$data['field_name']." on ".$field_attr);
					}
					// sort the fields. if 2, greater than 2 all +1 on the new category
					if (!$this->debug) $result = dbquery("UPDATE ".$this->field_db." SET field_order=field_order+1 WHERE field_order >= '".$data['field_order']."' AND field_cat='".$data['field_cat']."'");
					if ($this->debug) print_p("UPDATE ".$this->field_db." SET field_order=field_order+1 WHERE field_order >= '".$data['field_order']."' AND field_cat='".$data['field_cat']."'");
					// since change table. fix all which is greater than link order.
					if (!$this->debug) $result = dbquery("UPDATE ".$this->field_db." SET field_order=field_order-1 WHERE field_order >= '".$cat_data['field_order']."' AND field_cat='".$cat_data['field_cat']."'");
					if ($this->debug) print_p("UPDATE ".$this->field_db." SET field_order=field_order-1 WHERE field_order >= '".$cat_data['field_order']."' AND field_cat='".$cat_data['field_cat']."'");

				} else { // same table.
					// check if same title.
					// if not same, change column name.
					if ($this->debug) print_p("Path 2 Update Field");
					if ($data['field_name'] !== $cat_data['field_name']) { // not same as old record on dbcolumn
						if (!in_array($data['field_name'], $field_arrays)) { // safe to execute alter.
							// change the current column name to the new one. we cannot and do not need to modify field properties. if they want to change that, they should drop this field.
							if (!$this->debug) $result = dbquery("ALTER TABLE ".$target_database." CHANGE ".$cat_data['field_name']." ".$data['field_name']);
							//if (!$this->debug) $result = dbquery("ALTER TABLE ".$target_database." CHANGE ".$cat_data['field_name']." ".$data['field_name']." ".$this->dynamics_fieldinfo($data['field_type'], $data['field_default']));
							// check whether need to modify.
							if ($this->debug) print_p("Renaming ".$target_database." column ".$cat_data['field_name']." to ".$data['field_name']);
						} else {
							$defender->stop();
							$defender->addNotice('Field Name existed in '.$cat_data['field_cat_name'].' and renaming column failed. Please choose another name.');
						}
					}
					// make ordering of the same table.
					print_p($data['field_order']);
					print_p($cat_data['field_order']);
					if ($data['field_order'] > $cat_data['field_order']) {
						if (!$this->debug) $result = dbquery("UPDATE ".$this->field_db." SET field_order=field_order-1 WHERE field_order > ".$cat_data['field_order']." AND field_order <= '".$data['field_order']."' AND field_cat='".$data['field_cat']."'");
						if ($this->debug) print_p("UPDATE ".$this->field_db." SET field_order=field_order-1 WHERE field_order > '".$cat_data['field_order']."' AND field_order <= '".$data['field_order']."' AND field_cat='".$data['field_cat']."'");
					} elseif ($data['field_order'] < $cat_data['field_order']) {
						if (!$this->debug) $result = dbquery("UPDATE ".$this->field_db." SET field_order=field_order+1 WHERE field_order < ".$cat_data['field_order']." AND field_order >= '".$data['field_order']."' AND field_cat='".$data['field_cat']."'");
						if ($this->debug) print_p("UPDATE ".$this->field_db." SET field_order=field_order+1 WHERE field_order < '".$cat_data['field_order']."' AND field_order >= '".$data['field_order']."' AND field_cat='".$data['field_cat']."'");
					}
				}
				if ($this->debug) print_p($data);
				if (!$this->debug && !defined('FUSION_NULL')) dbquery_insert($this->field_db, $data, 'update');
				if (!$this->debug && !defined('FUSION_NULL')) redirect(FUSION_SELF.$aidlink.'&amp;status=field_updated');
			} else {
				$defender->stop();
				$defender->addNotice('User Fields ID is Invalid');
			}
		} else {
			if ($this->debug) print_p('Save Mode');
			// Alter DB_USER_FIELDS table - add column.
			$cresult = dbquery("SELECT cat.field_cat_id, cat.field_parent, cat.field_cat_order, root.field_cat_db, root.field_cat_index
									FROM ".$this->category_db." cat
									LEFT JOIN ".$this->category_db." root ON (cat.field_parent = root.field_cat_id)
									WHERE cat.field_cat_id='".$data['field_cat']."'");
			if (dbrows($cresult) > 0) {
				$cat_data = dbarray($cresult);
				$target_database = $cat_data['field_cat_db'] ? DB_PREFIX.$cat_data['field_cat_db'] : DB_USERS;
				$field_arrays = fieldgenerator($target_database);
				if (!in_array($data['field_name'], $field_arrays)) { // safe to execute alter.
					if (!$this->debug) $result = dbquery("ALTER TABLE ".$target_database." ADD ".$data['field_name']." ".$field_attr);
					if ($this->debug) print_p("Alter DB_".$target_database." with ".$data['field_name']." on ".$field_attr);
				} else {
					$defender->stop();
					$defender->addNotice('Another record with the same field name existed in the database record. Please choose another field name.');
				}
				// ordering
				if ($this->debug) print_p($data);
				if (!$this->debug && !defined('FUSION_NULL')) $result = dbquery("UPDATE ".$this->field_db." SET field_order=field_order+1 WHERE field_order > '".$data['field_order']."' AND field_cat='".$data['field_cat']."'");
				if (!$this->debug && !defined('FUSION_NULL')) dbquery_insert($this->field_db, $data, 'save');
				if (!$this->debug && !defined('FUSION_NULL')) redirect(FUSION_SELF.$aidlink.'&amp;status=field_added');
			} else {
				$defender->stop();
				$defender->addNotice('User Fields Category is Invalid');
			}
		}
	}

	/* The master form for Adding or Editing Dynamic Fields */
	private function dynamics_form() {
		global $aidlink, $defender;
		$locale = $this->locale;
		$config = array();
		$config_1 = array();
		$config_2 = array();
		$data = array();
		$cat_list = $this->cat_list;
		$form_action = FUSION_SELF.$aidlink;
		if (isset($_GET['action']) && $_GET['action'] == 'field_edit' && isset($_GET['field_id']) && isnum($_GET['field_id'])) {
			$form_action .= "&amp;action=".$_GET['action']."&amp;field_id=".$_GET['field_id'];
			$result = dbquery("SELECT * FROM ".$this->field_db." WHERE field_id='".$_GET['field_id']."'");
			if (dbrows($result) > 0) {
				$data += dbarray($result);
				if ($data['field_type'] == 'upload') {
					$data += unserialize($data['config']); // uncompress serialized extended information.
				}
			} else {
				//	redirect(FUSION_SELF.$aidlink);
			}
			if ($this->debug) print_p($data);
			// Initialize Constructor Fields
			$data['field_type'] = isset($_POST['add_field']) ? form_sanitizer($_POST['add_field'], '') : $data['field_type'];
			//if (!$data['field_type']) redirect(FUSION_SELF.$aidlink);
			$data['field_id'] = isset($_POST['field_id']) ? form_sanitizer($_POST['field_id'], '', 'field_id') : $data['field_id'];
			$data['field_title'] = isset($_POST['field_title']) ? form_sanitizer($_POST['field_title'], '', 'field_title') : $data['field_title'];
			$data['field_name'] = isset($_POST['field_name']) ? form_sanitizer($_POST['field_name'], '', 'field_name') : $data['field_name'];
			$data['field_name'] = str_replace(' ', '_', $data['field_name']); // make sure no space.
			$data['field_cat'] = isset($_POST['field_cat']) ? form_sanitizer($_POST['field_cat'], '', 'field_cat') : $data['field_cat'];
			$data['field_options'] = isset($_POST['field_options']) ? form_sanitizer($_POST['field_options'], '', 'field_options') : $data['field_options'];
			$data['field_default'] = isset($_POST['field_default']) ? form_sanitizer($_POST['field_default'], '', 'field_default') : $data['field_default'];
			$data['field_error'] = isset($_POST['field_error']) ? form_sanitizer($_POST['field_error'], '', 'field_error') : $data['field_error'];
			$data['field_required'] = isset($_POST['field_required']) ? 1 : isset($_POST['field_id']) ? 0 : $data['field_required'];
			$data['field_log'] = isset($_POST['field_log']) ? 1 : isset($_POST['field_id']) ? 0 : $data['field_log'];
			$data['field_registration'] = isset($_POST['field_registration']) ? 1 : isset($_POST['field_id']) ? 0 : $data['field_registration'];
			$data['field_order'] = isset($_POST['field_order']) ? form_sanitizer($_POST['field_order'], '0', 'field_order') : $data['field_order'];
			if ($data['field_type'] == 'upload') {
				// these are to be serialized. init all.
				$max_b = isset($_POST['field_max_b']) ? form_sanitizer($_POST['field_max_b'], '', 'field_max_b') : 150000;
				$calc = isset($_POST['field_calc']) ? form_sanitizer($_POST['field_calc'], '', 'field_calc') : 1;
				$config['field_max_b'] = isset($_POST['field_max_b']) && isset($_POST['field_calc']) ? $max_b*$calc : $data['field_max_b'];
				$config['field_upload_type'] = isset($_POST['field_upload_type']) ? form_sanitizer($_POST['field_upload_type'], '', 'field_upload_type') : $data['field_upload_type'];
				$config['field_upload_path'] = isset($_POST['field_upload_path']) ? form_sanitizer($_POST['field_upload_path'], '', 'field_upload_path') : $data['field_upload_path'];
				$config_1['field_valid_file_ext'] = isset($_POST['field_valid_file_ext']) && $config['field_upload_type'] == 'file' ? form_sanitizer($_POST['field_valid_file_ext'], '', 'field_valid_file_ext') : $data['field_valid_file_ext'];
				$config_2['field_valid_image_ext'] = isset($_POST['field_valid_image_ext']) && $config['field_upload_type'] == 'image' ? form_sanitizer($_POST['field_valid_image_ext'], '', 'field_valid_image_ext') : $data['field_valid_image_ext'];
				$config_2['field_image_max_w'] = isset($_POST['field_image_max_w']) && $config['field_upload_type'] == 'image' ? form_sanitizer($_POST['field_image_max_w'], '', 'field_image_max_w') : $data['field_image_max_w'];
				$config_2['field_image_max_h'] = isset($_POST['field_image_max_h']) && $config['field_upload_type'] == 'image' ? form_sanitizer($_POST['field_image_max_h'], '', 'field_image_max_h') : $data['field_image_max_h'];
				$config_2['field_thumbnail'] = isset($_POST['field_thumbnail']) ? form_sanitizer($_POST['field_thumbnail'], 0, 'field_thumbnail') : $data['field_thumbnail'];
				$config_2['field_thumb_upload_path'] = isset($_POST['field_thumb_upload_path']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail'] ? form_sanitizer($_POST['field_thumb_upload_path'], '', 'field_thumb_upload_path') : $data['field_thumb_upload_path'];
				$config_2['field_thumb_w'] = isset($_POST['field_thumb_w']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail'] ? form_sanitizer($_POST['field_thumb_w'], '', 'field_thumb_w') : $data['field_thumb_w'];
				$config_2['field_thumb_h'] = isset($_POST['field_thumb_h']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail'] ? form_sanitizer($_POST['field_thumb_h'], '', 'field_thumb_h') : $data['field_thumb_h'];
				$config_2['field_thumbnail_2'] = isset($_POST['field_thumbnail_2']) ? 1 : isset($_POST['field_id']) ? 0 : $data['field_thumbnail_2'];
				$config_2['field_thumb2_upload_path'] = isset($_POST['field_thumb2_upload_path']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail_2'] ? form_sanitizer($_POST['field_thumb2_upload_path'], '', 'field_thumb2_upload_path') : $data['field_thumb2_upload_path'];
				$config_2['field_thumb2_w'] = isset($_POST['field_thumb2_w']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail_2'] ? form_sanitizer($_POST['field_thumb2_w'], '', 'field_thumb2_w') : $data['field_thumb2_w'];
				$config_2['field_thumb2_h'] = isset($_POST['field_thumb2_h']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail_2'] ? form_sanitizer($_POST['field_thumb2_h'], '', 'field_thumb2_h') : $data['field_thumb2_h'];
				$config_2['field_delete_original'] = isset($_POST['field_delete_original']) && $config['field_upload_type'] == 'image' ? 1 : isset($_POST['field_id']) ? 0 : $data['field_delete_original'];
			}
		} else {
			// Initialize Constructor Fields
			$data['field_type'] = isset($_POST['add_field']) ? form_sanitizer($_POST['add_field'], '') : '';
			if (!$data['field_type']) redirect(FUSION_SELF.$aidlink);
			$data['field_id'] = isset($_POST['field_id']) ? form_sanitizer($_POST['field_id'], '', 'field_id') : isset($_GET['field_id']) && isnum($_GET['field_id']) ? $_GET['field_id'] : 0;
			$data['field_title'] = isset($_POST['field_title']) ? form_sanitizer($_POST['field_title'], '', 'field_title') : '';
			$data['field_name'] = isset($_POST['field_name']) ? form_sanitizer($_POST['field_name'], '', 'field_name') : '';
			$data['field_name'] = strtolower(str_replace(' ', '_', $data['field_name'])); // make sure no space.
			$data['field_cat'] = isset($_POST['field_cat']) ? form_sanitizer($_POST['field_cat'], '', 'field_cat') : 0;
			$data['field_options'] = isset($_POST['field_options']) ? form_sanitizer($_POST['field_options'], '', 'field_options') : '';
			$data['field_default'] = isset($_POST['field_default']) ? form_sanitizer($_POST['field_default'], '', 'field_default') : '';
			$data['field_error'] = isset($_POST['field_error']) ? form_sanitizer($_POST['field_error'], '', 'field_error') : '';
			$data['field_required'] = isset($_POST['field_required']) ? 1 : 0;
			$data['field_log'] = isset($_POST['field_log']) ? 1 : 0;
			$data['field_registration'] = isset($_POST['field_registration']) ? 1 : 0;
			$data['field_order'] = isset($_POST['field_order']) ? form_sanitizer($_POST['field_order'], '0', 'field_order') : 0;
			if ($data['field_type'] == 'upload') {
				// these are to be serialized. init all.
				$max_b = isset($_POST['field_max_b']) ? form_sanitizer($_POST['field_max_b'], '', 'field_max_b') : 150000;
				$calc = isset($_POST['field_calc']) ? form_sanitizer($_POST['field_calc'], '', 'field_calc') : 1;
				$config['field_max_b'] = $max_b*$calc;
				$config['field_upload_type'] = isset($_POST['field_upload_type']) ? form_sanitizer($_POST['field_upload_type'], '', 'field_upload_type') : 'file';
				$config['field_upload_path'] = isset($_POST['field_upload_path']) ? form_sanitizer($_POST['field_upload_path'], '', 'field_upload_path') : '';
				$config_1['field_valid_file_ext'] = isset($_POST['field_valid_file_ext']) && $config['field_upload_type'] == 'file' ? form_sanitizer($_POST['field_valid_file_ext'], '', 'field_valid_file_ext') : '.zip,.rar,.tar,.bz2,.7z';
				$config_2['field_valid_image_ext'] = isset($_POST['field_valid_image_ext']) && $config['field_upload_type'] == 'image' ? form_sanitizer($_POST['field_valid_image_ext'], '', 'field_valid_image_ext') : '.jpg,.jpeg,.gif,.png';
				$config_2['field_image_max_w'] = isset($_POST['field_image_max_w']) && $config['field_upload_type'] == 'image' ? form_sanitizer($_POST['field_image_max_w'], '', 'field_image_max_w') : 1800;
				$config_2['field_image_max_h'] = isset($_POST['field_image_max_h']) && $config['field_upload_type'] == 'image' ? form_sanitizer($_POST['field_image_max_h'], '', 'field_image_max_h') : 1600;
				$config_2['field_thumbnail'] = isset($_POST['field_thumbnail']) ? 1 : 0;
				$config_2['field_thumb_upload_path'] = isset($_POST['field_thumb_upload_path']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail'] ? form_sanitizer($_POST['field_thumb_upload_path'], '', 'field_thumb_upload_path') : '';
				$config_2['field_thumb_w'] = isset($_POST['field_thumb_w']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail'] ? form_sanitizer($_POST['field_thumb_w'], '', 'field_thumb_w') : 100;
				$config_2['field_thumb_h'] = isset($_POST['field_thumb_h']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail'] ? form_sanitizer($_POST['field_thumb_h'], '', 'field_thumb_h') : 100;
				$config_2['field_thumbnail_2'] = isset($_POST['field_thumbnail_2']) ? 1 : 0;
				$config_2['field_thumb2_upload_path'] = isset($_POST['field_thumb2_upload_path']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail_2'] ? form_sanitizer($_POST['field_thumb2_upload_path'], '', 'field_thumb2_upload_path') : '';
				$config_2['field_thumb2_w'] = isset($_POST['field_thumb2_w']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail_2'] ? form_sanitizer($_POST['field_thumb2_w'], '', 'field_thumb2_w') : 400;
				$config_2['field_thumb2_h'] = isset($_POST['field_thumb2_h']) && $config['field_upload_type'] == 'image' && $config_2['field_thumbnail_2'] ? form_sanitizer($_POST['field_thumb2_h'], '', 'field_thumb2_h') : 300;
				$config_2['field_delete_original'] = isset($_POST['field_delete_original']) && $config['field_upload_type'] == 'image' ? 1 : 0;
			}
		}
		if (isset($_POST['save_field'])) {
			// Serialize the extra fields.. no bloating table.
			if ($data['field_type'] == 'upload') {
				if ($config['field_upload_type'] == 'file') {
					$config = array_merge($config, $config_1);
				} elseif ($config['field_upload_type'] == 'image') {
					// upload path must be required.
					$config = array_merge($config, $config_2);
				} else {
					$defender->stop();
					$defender->addNotice('Field type is not specified');
				}
				if (!defined('FUSION_NULL')) {
					$data['config'] = serialize($config);
				}
			}
			// ok now save into UF.
			$this->synthesize_fields($data, 'dynamics');
		}
		echo "<div class='m-t-20'>\n";
		echo openform('fieldform', 'fieldform', 'post', $form_action, array('downtime' => 0));
		foreach ($this->page_list as $index => $v) {
			$disable_opts[] = $index;
		}
		echo form_select_tree('Select Category', 'field_cat', 'field_cat', $data['field_cat'], array('no_root' => 1,
			'disable_opts' => $disable_opts), $this->category_db, 'field_cat_name', 'field_cat_id', 'field_parent');
		echo form_text('Field Title', 'field_title', 'field_title', $data['field_title'], array('placeholder' => 'Form field title name',
			'required' => 1)); //
		echo form_text('Field Name', 'field_name', 'field_name', $data['field_name'], array('placeholder' => 'Form field name',
			'required' => 1)); //
		if ($data['field_type'] == 'select') echo form_select('Field Options', 'field_options', 'field_options', array(), $data['field_options'], array('required' => 1,
			'tags' => 1,
			'multiple' => 1));
		if ($data['field_type'] == 'upload') {
			require_once INCLUDES.'mimetypes_include.php';
			$file_type_list = array();
			$file_image_list = array();
			foreach (mimeTypes() as $file_ext => $occ) {
				if (!in_array($file_ext, array_flip(img_mimeTypes()))) {
					$file_type_list[] = '.'.$file_ext;
				}
			}
			foreach (img_mimeTypes() as $file_ext => $occ) {
				$file_image_list[] = '.'.$file_ext;
			}
			function calculate_byte($download_max_b) {
				$calc_opts = array(1 => 'Bytes (bytes)', 1000 => 'KB (Kilobytes)', 1000000 => 'MB (Megabytes)');
				foreach ($calc_opts as $byte => $val) {
					if ($download_max_b/$byte <= 999) {
						return $byte;
					}
				}
				return 1000000;
			}

			$calc_opts = array(1 => 'Bytes (bytes)', 1000 => 'KB (Kilobytes)', 1000000 => 'MB (Megabytes)');
			$calc_c = calculate_byte($config['field_max_b']);
			$calc_b = $config['field_max_b']/$calc_c;
			$file_upload_type = array('file' => 'File Type', 'image' => 'Image Only');
			echo form_select('Upload Field Type', 'field_upload_type', 'field_upload_type', $file_upload_type, $config['field_upload_type']);
			echo form_text('File Upload Folder', 'field_upload_path', 'field_upload_path', $config['field_upload_path'], array('placeholder' => 'e.g. images/',
				'required' => 1));
			echo "<label for='field_max_b'>Max Upload File Size</label>\n<br/>";
			echo "<div class='row'>\n";
			echo "<div class='col-xs-6 col-sm-6 col-md-6 col-lg-6'>\n";
			echo form_text('', 'field_max_b', 'field_max_b', $calc_b, array('class' => 'm-b-0',
				'number' => 1,
				'required' => 1));
			echo "</div><div class='col-xs-6 col-sm-6 col-md-6 col-lg-6 p-l-0'>\n";
			echo form_select('', 'field_calc', 'field_calc', $calc_opts, $calc_c, array('width' => '100%'));
			echo "</div>\n</div>\n";
			// File Type
			echo "<div id='file_type'>\n";
			echo form_select('Valid File Type', 'field_valid_file_ext', 'field_valid_file_ext', $file_type_list, $config_1['field_valid_file_ext'], array('multiple' => 1,
				'tags' => 1,
				'required' => 1));
			echo "</div>\n";
			// Image Type
			echo "<div id='image_type'>\n";
			echo form_select('Valid Image Type', 'field_valid_image_ext', 'field_valid_image_ext', $file_image_list, $config_2['field_valid_image_ext'], array('multiple' => 1,
				'tags' => 1,
				'required' => 1));
			echo "<label>Max Image Upload Dimension</label>\n<br/>";
			echo "<div class='row'>\n";
			echo "<div class='col-xs-6 col-sm-6 col-md-6 col-lg-6'>\n";
			echo form_text('Image Max Width', 'field_image_max_w', 'field_image_max_w', $config_2['field_image_max_w'], array('number' => 1,
				'placeholder' => '(px)',
				'required' => 1));
			echo "</div><div class='col-xs-6 col-sm-6 col-md-6 col-lg-6 p-l-0'>\n";
			echo form_text('Image Max Height', 'field_image_max_h', 'field_image_max_h', $config_2['field_image_max_h'], array('number' => 1,
				'placeholder' => '(px)',
				'required' => 1));
			echo "</div>\n</div>\n";
			echo form_checkbox('Create Thumbnail?', 'field_thumbnail', 'field_thumbnail', $config_2['field_thumbnail']);
			echo "<div id='field_t1'>\n";
			echo form_text('Thumb Upload Folder', 'field_thumb_upload_path', 'field_thumb_upload_path', $config_2['field_thumb_upload_path'], array('placeholder' => 'e.g. images/thumb/',
				'required' => 1));
			echo "<label>Max Thumbnail Dimension</label>\n<br/>";
			echo "<div class='row'>\n";
			echo "<div class='col-xs-6 col-sm-6 col-md-6 col-lg-6'>\n";
			echo form_text('Thumb Width', 'field_thumb_w', 'field_thumb_w', $config_2['field_thumb_w'], array('number' => 1,
				'placeholder' => '(px)',
				'required' => 1));
			echo "</div><div class='col-xs-6 col-sm-6 col-md-6 col-lg-6 p-l-0'>\n";
			echo form_text('Thumb Height', 'field_thumb_h', 'field_thumb_h', $config_2['field_thumb_h'], array('number' => 1,
				'placeholder' => '(px)',
				'required' => 1));
			echo "</div>\n</div>\n";
			echo "</div>\n";
			echo form_checkbox('Create Thumbnail 2?', 'field_thumbnail_2', 'field_thumbnail_2', $config_2['field_thumbnail_2']);
			echo "<div id='field_t2'>\n";
			echo form_text('Thumb 2 Upload Folder', 'field_thumb2_upload_path', 'field_thumb2_upload_path', $config_2['field_thumb2_upload_path'], array('placeholder' => 'e.g. images/thumb/',
				'required' => 1));
			echo "<label>Max Thumbnail 2 Dimension</label>\n<br/>";
			echo "<div class='row'>\n";
			echo "<div class='col-xs-6 col-sm-6 col-md-6 col-lg-6'>\n";
			echo form_text('Thumb 2 Width', 'field_thumb2_w', 'field_thumb2_w', $config_2['field_thumb2_h'], array('number' => 1,
				'placeholder' => '(px)',
				'required' => 1));
			echo "</div><div class='col-xs-6 col-sm-6 col-md-6 col-lg-6 p-l-0'>\n";
			echo form_text('Thumb 2 Height', 'field_thumb2_h', 'field_thumb2_h', $config_2['field_thumb2_h'], array('number' => 1,
				'placeholder' => '(px)',
				'required' => 1));
			echo "</div>\n</div>\n";
			echo "</div>\n";
			echo form_checkbox('Delete Original Image?', 'field_delete_original', 'field_delete_original', $config_2['field_delete_original']);
			echo "</div>\n";
			add_to_jquery("
			if ($('#field_upload_type').select2().val() == 'image') {
				$('#image_type').show();
				$('#file_type').hide();
			} else {
				$('#image_type').hide();
				$('#file_type').show();
			}
			$('#field_upload_type').bind('change', function() {
				if ($(this).select2().val() == 'image') {
				$('#image_type').show();
				$('#file_type').hide();
				} else {
				$('#image_type').hide();
				$('#file_type').show();
				}
			});
			// thumbnail
			$('#field_thumbnail').is(':checked') ? $('#field_t1').show() : $('#field_t1').hide();
			$('#field_thumbnail').bind('click', function() {
				$(this).is(':checked') ? $('#field_t1').show() : $('#field_t1').hide();
			});
			// thumbnail 2
			$('#field_thumbnail_2').is(':checked') ? $('#field_t2').show() : $('#field_t2').hide();
			$('#field_thumbnail_2').bind('click', function() {
				$(this).is(':checked') ? $('#field_t2').show() : $('#field_t2').hide();
			});
			");
		} else {
			// @todo add config for textarea
			if ($data['field_type'] !== 'textarea') echo form_text('Field Default Value', 'field_default', 'field_default', $data['field_default']);
			echo form_text('Field Error Value', 'field_error', 'field_error', $data['field_error']);
		}
		echo form_checkbox('Field Required', 'field_required', 'field_required', $data['field_required']);
		echo form_checkbox('Field Log', 'field_log', 'field_log', $data['field_log']);
		echo form_text('Field Order', 'field_order', 'field_order', $data['field_order'], array('number' => 1));
		echo form_checkbox('Field Registration', 'field_registration', 'field_registration', $data['field_registration']);
		echo form_hidden('', 'add_field', 'add_field', $data['field_type']);
		echo form_hidden('', 'field_id', 'field_id', $data['field_id']);
		echo form_button('Save Field', 'save_field', 'save_field', 'save', array('class' => 'btn-sm btn-primary'));
		echo closeform();
		echo "</div>\n";
	}

	/* Add Modules Plugin Form */
	private function modules_form() {
		global $aidlink, $defender;
		// @todo:ordering
		$data = array();
		$form_action = FUSION_SELF.$aidlink;
		if (isset($_GET['action']) && $_GET['action'] == 'module_edit' && isset($_GET['module_id']) && isnum($_GET['module_id'])) {
			$form_action .= "&amp;action=".$_GET['action']."&amp;module_id=".$_GET['module_id'];

			$result = dbquery("SELECT * FROM ".$this->field_db." WHERE field_id='".$_GET['module_id']."'");
			if (dbrows($result) > 0) {
				$data += dbarray($result);
			} else {
				//	redirect(FUSION_SELF.$aidlink);
			}
			if ($this->debug) print_p($data);
			$data['add_module'] = isset($_POST['add_module']) ? form_sanitizer($_POST['add_module']) : $data['field_name'];
			$data['field_type'] = 'file'; //
			$data['field_id'] = isset($_POST['field_id']) ? form_sanitizer($_POST['field_id'], '', 'field_id') : isset($_GET['module_id']) && isnum($_GET['module_id']) ? $_GET['module_id'] : 0;
			$data['field_title'] = isset($_POST['field_title']) ? form_sanitizer($_POST['field_title'], '', 'field_title') : $data['field_title'];
			$data['field_name'] = isset($_POST['field_name']) ? form_sanitizer($_POST['field_name'], '', 'field_name') : $data['field_name'];
			$data['field_name'] = str_replace(' ', '_', $data['field_name']); // make sure no space.
			$data['field_cat'] = isset($_POST['field_cat']) ? form_sanitizer($_POST['field_cat'], '', 'field_cat') : $data['field_cat']; //
			$data['field_default'] = isset($_POST['field_default']) ? form_sanitizer($_POST['field_default'], '', 'field_default') : $data['field_default']; //
			$data['field_error'] = isset($_POST['field_error']) ? form_sanitizer($_POST['field_error'], '', 'field_error') : $data['field_error'];
			$data['field_required'] = isset($_POST['field_required']) ? 1 : isset($_POST['field_id']) ? 0 : $data['field_required'];
			$data['field_log'] = isset($_POST['field_log']) ? 1 : isset($_POST['field_id']) ? 0 : $data['field_log'];
			$data['field_registration'] = isset($_POST['field_registration']) ? 1 : isset($_POST['field_id']) ? 0 : $data['field_registration'];
			$data['field_order'] = isset($_POST['field_order']) ? form_sanitizer($_POST['field_order'], '0', 'field_order') : $data['field_order'];
		} else {
			// new
			$data['add_module'] = isset($_POST['add_module']) ? form_sanitizer($_POST['add_module']) : $_POST['add_module'];
			if (!$data['add_module']) redirect(FUSION_SELF.$aidlink);
			$data['field_type'] = 'file'; //
			$data['field_id'] = isset($_POST['field_id']) ? form_sanitizer($_POST['field_id'], '', 'field_id') : isset($_GET['field_id']) && isnum($_GET['field_id']) ? $_GET['field_id'] : 0;
			$data['field_title'] = isset($_POST['field_title']) ? form_sanitizer($_POST['field_title'], '', 'field_title') : ''; //
			$data['field_name'] = isset($_POST['field_name']) ? form_sanitizer($_POST['field_name'], '', 'field_name') : ''; //
			$data['field_name'] = str_replace(' ', '_', $data['field_name']); // make sure no space.
			$data['field_cat'] = isset($_POST['field_cat']) ? form_sanitizer($_POST['field_cat'], '', 'field_cat') : 0; //
			$data['field_option'] = isset($_POST['field_option']) ? form_sanitizer($_POST['field_option'], '', 'field_option') : ''; //
			$data['field_default'] = isset($_POST['field_default']) ? form_sanitizer($_POST['field_default'], '', 'field_default') : ''; //
			$data['field_error'] = isset($_POST['field_error']) ? form_sanitizer($_POST['field_error'], '', 'field_error') : ''; //
			$data['field_required'] = isset($_POST['field_required']) ? 1 : 0; //
			$data['field_log'] = isset($_POST['field_log']) ? 1 : 0; //
			$data['field_registration'] = isset($_POST['field_registration']) ? 1 : 0; //
			$data['field_order'] = isset($_POST['field_order']) ? form_sanitizer($_POST['field_order'], '0', 'field_order') : 0; //
		}
		$locale = $this->locale;
		$user_field_name = '';
		$user_field_api_version = '';
		$user_field_desc = '';
		$user_field_dbname = '';
		$user_field_dbinfo = '';
		if (file_exists($this->plugin_locale_folder.stripinput($data['add_module']).".php") && file_exists($this->plugin_folder.stripinput($data['add_module'])."_include_var.php")) {
			include $this->plugin_locale_folder.stripinput($data['add_module']).".php";
			include $this->plugin_folder.stripinput($data['add_module'])."_include_var.php";
			$this->user_field_dbinfo = $user_field_dbinfo;
		} else {
			$defender->stop();
			$defender->addNotice('Plugin module file not found.');
		}
		// Script Execution
		if (isset($_POST['enable'])) {
			$this->synthesize_fields($data, 'module');
		}

		echo "<div class='m-t-20'>\n";
		echo openform('fieldform', 'fieldform', 'post', $form_action, array('downtime' => 0));
		echo "<p class='strong text-dark'>".$user_field_name."</p>\n";
		echo "<div class='well'>\n";
		echo "<p class='strong'>Module Fields Information</p>\n";
		echo "<span class='text-dark strong'>Version:</span> ".($user_field_api_version ? $user_field_api_version : 'Legacy')."<br/>\n";
		echo "<span class='text-dark strong'>Field Table Column:</span>".($user_field_dbname ? "<br/>".$user_field_dbname : '<br/>Cannot be installed')."<br/>\n";
		echo "<span class='text-dark strong'>Field Table Column Info:</span>".($user_field_dbinfo ? "<br/>".$user_field_dbinfo : '<br/>This is not a field')."<br/>\n";
		echo "<span class='text-dark strong'>Field Description:</span>".($user_field_desc ? "<br/>".$user_field_desc : '')."<br/>\n";
		echo "</div>\n";
		echo "<hr/>\n";
		// start form.
		foreach ($this->page_list as $index => $v) {
			$disable_opts[] = $index;
		}
		echo form_select_tree('Select Category', 'field_cat', 'field_cat', $data['field_cat'], array('no_root' => 1,
			'disable_opts' => $disable_opts), $this->category_db, 'field_cat_name', 'field_cat_id', 'field_parent');
		if ($user_field_dbinfo != "") {
			if (version_compare($user_field_api_version, "1.01.00", ">=")) {
				echo form_checkbox('This is a required field', 'field_required', 'field_required', $data['field_required']);
			} else {
				echo "<p>\n".$locale['428']."</p>\n";
			}
		}
		if ($user_field_dbinfo != "") {
			if (version_compare($user_field_api_version, "1.01.00", ">=")) {
				echo form_checkbox('Log User Fields', 'field_log', 'field_log', $data['field_log']);
			} else {
				echo "<p>\n".$locale['429a']."</p>\n";
			}
		}
		if ($user_field_dbinfo != "") {
			echo form_checkbox('Attach Field to Register Form?', 'field_registration', 'field_registration', $data['field_registration']);
		}
		echo form_text('Field Order', 'field_order', 'field_order', $data['field_order']);
		echo form_hidden('', 'add_module', 'add_module', $data['add_module']);
		echo form_hidden('', 'field_name', 'field_name', $user_field_dbname);
		echo form_hidden('', 'field_title', 'field_title', $user_field_name);
		// new api introduced
		echo form_hidden('', 'field_default', 'field_default', isset($user_field_default) ? $user_field_default : '');
		echo form_hidden('', 'field_options', 'field_options', isset($user_field_options) ? $user_field_options : '');
		echo form_hidden('', 'field_error', 'field_error', isset($user_field_error) ? $user_field_error : '');
		echo form_hidden('', 'field_config', 'field_config', isset($user_field_config) ? $user_field_config : '');
		echo form_hidden('', 'field_id', 'field_id', $data['field_id']);
		echo form_button(($data['field_id'] ? 'Update Field' : 'Install Field'), 'enable', 'enable', ($data['field_id'] ? 'Update Field' : 'Install Field'), array('class' => 'btn-primary btn-sm'));
		echo closeform();
		echo "</div>\n";
	}

	/* Category & Page Form */
	private function category_form() {
		global $aidlink, $defender;
		$data = array();
		if (isset($_GET['action']) && $_GET['action'] == 'cat_edit' && isset($_GET['cat_id']) && isnum($_GET['cat_id'])) {
			$result = dbquery("SELECT * FROM ".$this->category_db." WHERE field_cat_id='".$_GET['cat_id']."'");
			if (dbrows($result) > 0) {
				$data += dbarray($result);
			} else {
				redirect(FUSION_SELF.$aidlink);
			}
			// override by post.
			$data['field_cat_id'] = isset($_POST['field_cat_id']) ? form_sanitizer($_POST['field_cat_id'], '', 'field_cat_id') : $data['field_cat_id'];
			$data['field_cat_name'] = isset($_POST['field_cat_name']) ? form_sanitizer($_POST['field_cat_name'], '', 'field_cat_name') : $data['field_cat_name'];
			$data['field_parent'] = isset($_POST['field_parent']) ? form_sanitizer($_POST['field_parent'], '', 'field_parent') : $data['field_parent'];
			$data['field_cat_order'] = isset($_POST['field_cat_order']) ? form_sanitizer($_POST['field_cat_order'], '', 'field_cat_order') : $data['field_cat_order'];
			$data['field_cat_db'] = isset($_POST['field_cat_db']) ? form_sanitizer($_POST['field_cat_db'], '', 'field_cat_db') : $data['field_cat_db'];
			$data['field_cat_index'] = isset($_POST['field_cat_index']) ? form_sanitizer($_POST['field_cat_index'], '', 'field_cat_index') : $data['field_cat_index'];
			$data['field_cat_class'] = isset($_POST['field_cat_class']) ? form_sanitizer($_POST['field_cat_class'], '', 'field_cat_class') : $data['field_cat_class'];
		} else {
			$data['field_cat_id'] = isset($_POST['field_cat_id']) ? form_sanitizer($_POST['field_cat_id'], '', 'field_cat_id') : 0;
			$data['field_cat_name'] = isset($_POST['field_cat_name']) ? form_sanitizer($_POST['field_cat_name'], '', 'field_cat_name') : '';
			$data['field_parent'] = isset($_POST['field_parent']) ? form_sanitizer($_POST['field_parent'], '', 'field_parent') : '';
			$data['field_cat_order'] = isset($_POST['field_cat_order']) ? form_sanitizer($_POST['field_cat_order'], '', 'field_cat_order') : 0;
			$data['field_cat_db'] = isset($_POST['field_cat_db']) ? form_sanitizer($_POST['field_cat_db'], '', 'field_cat_db') : '';
			$data['field_cat_index'] = isset($_POST['field_cat_index']) ? form_sanitizer($_POST['field_cat_index'], '', 'field_cat_index') : '';
			$data['field_cat_class'] = isset($_POST['field_cat_class']) ? form_sanitizer($_POST['field_cat_class'], '', 'field_cat_class') : '';
		}
		if (isset($_POST['save_cat'])) {
			// safety
			if ($data['field_cat_order'] == 0) {
				$data['field_cat_order'] = dbresult(dbquery("SELECT MAX(field_cat_order) FROM ".$this->category_db." WHERE field_parent='".$data['field_parent']."'"), 0)+1;
			}
			if ($data['field_parent'] > 0) {
				$data['field_cat_db'] = '';
				$data['field_cat_index'] = '';
				$data['field_cat_class'] = '';
			}
			// shuffle between save and update
			$rows = dbcount("('field_cat_id')", $this->category_db, "field_cat_id='".$data['field_cat_id']."'");
			if ($rows > 0) {
				if ($this->debug) print_p('Update Mode');
				if ($this->debug) print_p($data);
				// ordering.
				$cat_data = dbarray(dbquery("SELECT * FROM ".$this->category_db." WHERE field_cat_id='".$data['field_cat_id']."'"));
				if ($data['field_cat_order'] > $cat_data['field_cat_order']) {
					if (!$this->debug && !defined('FUSION_NULL')) $result = dbquery("UPDATE ".$this->category_db." SET field_cat_order=field_cat_order-1 WHERE field_cat_order > ".$cat_data['field_cat_order']." AND field_cat_order <= '".$data['field_cat_order']."' AND field_cat='".$data['field_parent']."'");
				} elseif ($data['field_cat_order'] < $cat_data['field_cat_order']) {
					if (!$this->debug && !defined('FUSION_NULL')) $result = dbquery("UPDATE ".$this->category_db." SET field_cat_order=field_cat_order+1 WHERE field_cat_order < ".$cat_data['field_cat_order']." AND field_cat_order >= '".$data['field_cat_order']."' AND field_cat='".$data['field_parent']."'");
				}
				// build the page table on update and save
				if (!$this->debug && !defined('FUSION_NULL') && $data['field_cat_db'] && $data['field_cat_index'] && $data['field_cat_db'] !== 'users') { // if entered a field cat db and index and is not DB_USERS
					if (!db_exists(DB_PREFIX.$data['field_cat_db'])) { // check duplicates.
						// create principal table
						$result = dbquery("CREATE TABLE ".DB_PREFIX.$data['field_cat_db']." (
								".$data['field_cat_index']."_id MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT ,
								".$data['field_cat_index']." MEDIUMINT(8) NOT NULL DEFAULT '0',
								PRIMARY KEY (".$data['field_cat_index']."_id)
								) ENGINE=MyISAM DEFAULT CHARSET=UTF8 COLLATE=utf8_unicode_ci");
					} else {
						$defender->stop();
						$defender->addNotice('Table already exist. Please choose another table name');
					}
				}
				if (!$this->debug && !defined('FUSION_NULL')) dbquery_insert($this->category_db, $data, 'update');
				if (!$this->debug && !defined('FUSION_NULL')) redirect(FUSION_SELF.$aidlink."&amp;status=update_cat");
			} else {
				if ($this->debug) print_p('Save Mode');
				if ($this->debug) print_p($data);
				if (!$this->debug && !defined('FUSION_NULL') && $data['field_cat_db'] && $data['field_cat_index'] && $data['field_cat_db'] !== 'users') { // if entered a field cat db and index and is not DB_USERS
					if (!db_exists(DB_PREFIX.$data['field_cat_db'])) { // check duplicates.
						// create principal table
						$result = dbquery("CREATE TABLE ".DB_PREFIX.$data['field_cat_db']." (
								".$data['field_cat_index']."_id MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT ,
								".$data['field_cat_index']." MEDIUMINT(8) NOT NULL DEFAULT '0',
								PRIMARY KEY (".$data['field_cat_index']."_id)
								) ENGINE=MyISAM DEFAULT CHARSET=UTF8 COLLATE=utf8_unicode_ci");
					} else {
						$defender->stop();
						$defender->addNotice('Table already exist. Please choose another table name');
					}
				}
				if (!$this->debug && !defined('FUSION_NULL')) $result = dbquery("UPDATE ".$this->category_db." SET field_cat_order=field_cat_order+1 WHERE field_cat_order >= '".$data['field_cat_order']."' AND field_parent='".$data['field_parent']."'");
				if (!$this->debug && !defined('FUSION_NULL')) dbquery_insert($this->category_db, $data, 'save');
				if (!$this->debug && !defined('FUSION_NULL')) redirect(FUSION_SELF.$aidlink."&status=cat_save");
			}
		}
		// exclusion list - unselectable
		$cat_list = array();
		if (!empty($this->cat_list)) {
			foreach ($this->cat_list as $id => $value) {
				$cat_list[] = $id;
			}
		}
		echo openform('cat_form', 'cat_form', 'post', FUSION_SELF.$aidlink, array('downtime' => 0));
		echo form_text('Category Name', 'field_cat_name', 'field_cat_name', $data['field_cat_name'], array('required' => 1));
		echo form_select_tree('Category Parent', 'field_parent', 'field_parent', $data['field_parent'], array('parent_value' => 'As New Page',
			'disable_opts' => $cat_list), $this->category_db, 'field_cat_name', 'field_cat_id', 'field_parent');
		echo form_text('Category Order', 'field_cat_order', 'field_cat_order', $data['field_cat_order'], array('number' => 1));
		echo form_hidden('', 'field_cat_id', 'field_cat_id', $data['field_cat_id'], array('number' => 1));
		echo form_hidden('', 'add_cat', 'add_cat', 'add_cat');
		// root settings
		echo "<div id='page_settings'>\n";
		echo "<div class='text-smaller m-b-10'>User field page can be extended to read other database on view. </div>\n";
		echo form_text('Primary Table (DB_PREFIX_)', 'field_cat_db', 'field_cat_db', $data['field_cat_db'], array('placeholder' => 'users'));
		echo "<div class='text-smaller m-b-10'>Which table primarily is used by this page?</div>\n";
		echo form_text('Primary Table User Column', 'field_cat_index', 'field_cat_index', $data['field_cat_index'], array('placeholder' => 'user_id'));
		echo "<div class='text-smaller m-b-10'>Which column in your primary is your User ID column?</div>\n";
		echo form_text('Icon Class', 'field_cat_class', 'field_cat_class', $data['field_cat_class'], array('placeholder' => 'entypo xxxxx'));
		echo "</div>\n";
		add_to_jquery("
		$('#field_parent').val() == '0' ? $('#page_settings').show() : $('#page_settings').hide()
		$('#field_parent').bind('change', function() {
		$(this).val() == '0' ? $('#page_settings').show() : $('#page_settings').hide()
		});
		");
		echo form_button('Save Category', 'save_cat', 'save_cat', 'save_cat', array('class' => 'btn-sm btn-primary'));
		echo closeform();
	}

	/* Populates enabled and available Plugin Fields Var */
	private function available_fields() {
		$result = dbquery("SELECT field_id, field_name, field_cat, field_required, field_log, field_registration, field_order, field_cat_name
					FROM ".$this->field_db." tuf
					INNER JOIN ".$this->category_db." tufc ON (tuf.field_cat = tufc.field_cat_id)
					WHERE field_type = 'file'
					ORDER BY field_cat_order, field_order");
		if (dbrows($result) > 0) {
			while ($data = dbarray($result)) {
				$this->enabled_fields[] = $data['field_name'];
			}
		}
		$user_field_name = '';
		$user_field_desc = '';
		if ($temp = opendir($this->plugin_folder)) {
			while (FALSE !== ($file = readdir($temp))) {
				if (!in_array($file, array("..", ".", "index.php")) && !is_dir($this->plugin_folder.$file)) {
					if (preg_match("/_var.php/i", $file)) {
						$field_name = explode("_", $file);
						$field_title = $field_name[0].'_'.$field_name[1];
						if (!in_array($field_title, $this->enabled_fields)) {
							// ok need to get locale.
							if (file_exists($this->plugin_locale_folder.$field_title.".php")) {
								include $this->plugin_locale_folder.$field_title.".php";
								include $this->plugin_folder.$field_title."_include_var.php";
								$this->available_field_info[$field_title] = array('title' => $user_field_name,
									'description' => $user_field_desc);
								$this->available_fields[$field_title] = $user_field_name;
							}
						}
						unset($field_name);
					}
				}
			}
			closedir($temp);
		}
	}

	/* Buttons */
	private function phpfusion_field_buttons() {
		global $aidlink;
		$locale = $this->locale;
		$tab_title['title'][] = 'Fields';
		$tab_title['id'][] = 'dyn';
		$tab_title['icon'][] = '';
		if (!empty($this->cat_list)) {
			$tab_title['title'][] = 'Modules';
			$tab_title['id'][] = 'mod';
			$tab_title['icon'][] = '';
		}
		// Extended Tabs
		// add category
		if (isset($_POST['add_cat'])) {
			$tab_title['title'][] = 'Add Category';
			$tab_title['id'][] = 'add';
			$tab_title['icon'][] = '';
			$tab_active = (!empty($this->cat_list)) ? tab_active($tab_title, 2) : tab_active($tab_title, 1);
		} // add field
		elseif (isset($_POST['add_field']) && in_array($_POST['add_field'], array_flip($this->dynamics_type()))) {
			$tab_title['title'][] = 'Add Field';
			$tab_title['id'][] = 'add';
			$tab_title['icon'][] = '';
			$tab_active = tab_active($tab_title, 2);
		} // add module
		elseif (isset($_POST['add_module']) && in_array($_POST['add_module'], array_flip($this->available_fields))) {
			$tab_title['title'][] = 'Add Field';
			$tab_title['id'][] = 'add';
			$tab_title['icon'][] = '';
			$tab_active = tab_active($tab_title, 2);
		} // edit category
		elseif (isset($_GET['action']) && $_GET['action'] == 'cat_edit' && isset($_GET['cat_id']) && isnum($_GET['cat_id'])) {
			$tab_title['title'][] = 'Edit Category';
			$tab_title['id'][] = 'edit';
			$tab_title['icon'][] = '';
			$tab_active = (!empty($this->cat_list)) ? tab_active($tab_title, 2) : tab_active($tab_title, 1);
		} elseif (isset($_GET['action']) && $_GET['action'] == 'field_edit' && isset($_GET['field_id']) && isnum($_GET['field_id'])) {
			$tab_title['title'][] = 'Edit Field';
			$tab_title['id'][] = 'edit';
			$tab_title['icon'][] = '';
			$tab_active = tab_active($tab_title, 2);
		} elseif (isset($_GET['action']) && $_GET['action'] == 'module_edit' && isset($_GET['module_id']) && isnum($_GET['module_id'])) {
			$tab_title['title'][] = 'Edit Module';
			$tab_title['id'][] = 'edit';
			$tab_title['icon'][] = '';
			$tab_active = tab_active($tab_title, 2);
		} else {
			$tab_active = tab_active($tab_title, 0);
		}
		echo opentab($tab_title, $tab_active, 'amd');
		echo opentabbody($tab_title['title'][0], $tab_title['id'][0], $tab_active);
		echo openform('addfield', 'addfield', 'post', FUSION_SELF.$aidlink, array('notice' => 0, 'downtime' => 0));
		echo form_button('Add New Category', 'add_cat', 'add_cat', 'add_cat', array('class' => 'm-t-20 m-b-20 btn-sm btn-primary btn-block',
			'icon' => 'entypo plus-circled'));
		if (!empty($this->cat_list)) {
			echo "<div class='row m-t-20'>\n";
			$field_type = $this->dynamics_type();
			unset($field_type['file']);
			foreach ($field_type as $type => $name) {
				echo "<div class='col-xs-6 col-sm-6 col-md-6 col-lg-6 p-b-20'>".form_button($name, 'add_field', 'add_field-'.$name, $type, array('class' => 'btn-block btn-sm btn-default'))."</div>\n";
			}
			echo "</div>\n";
		}
		echo closeform();
		echo closetabbody();
		if (!empty($this->cat_list)) {
			echo opentabbody($tab_title['title'][1], $tab_title['id'][1], $tab_active);
			// list down modules.
			echo openform('addfield', 'addfield', 'post', FUSION_SELF.$aidlink, array('notice' => 0, 'downtime' => 0));
			echo "<div class='m-t-20'>\n";
			foreach ($this->available_field_info as $title => $module_data) {
				echo "<div class='list-group-item'>";
				echo form_button('Install', 'add_module', 'add_module-'.$title, $title, array('class' => 'btn-sm btn-default pull-right m-l-10'));
				echo "<div class='overflow-hide'>\n";
				echo "<span class='text-dark strong'>".$module_data['title']."</span><br/>\n";
				echo "<span>".$module_data['description']."</span>\n<br/>";
				echo "</div>\n";
				echo "</div>\n";
			}
			echo "</div>\n";
			echo closeform();
			echo closetabbody();
		}
		if (isset($_POST['add_cat']) or (isset($_GET['action']) && $_GET['action'] == 'cat_edit' && isset($_GET['cat_id']) && isnum($_GET['cat_id']))) {
			if (!empty($this->cat_list)) {
				echo opentabbody($tab_title['title'][2], $tab_title['id'][2], $tab_active);
			} else {
				echo opentabbody($tab_title['title'][1], $tab_title['id'][1], $tab_active);
			}
			echo "<div class='m-t-20'>\n";
			$this->category_form();
			echo "</div>\n";
			echo closetabbody();
		} elseif (isset($_POST['add_field']) && in_array($_POST['add_field'], array_flip($this->dynamics_type())) or (isset($_GET['action']) && $_GET['action'] == 'field_edit' && isset($_GET['field_id']) && isnum($_GET['field_id']))) {
			echo opentabbody($tab_title['title'][2], $tab_title['id'][2], $tab_active);
			$this->dynamics_form();
			echo closetabbody();
		} elseif (isset($_POST['add_module']) && in_array($_POST['add_module'], array_flip($this->available_fields)) or (isset($_GET['action']) && $_GET['action'] == 'module_edit' && isset($_GET['module_id']) && isnum($_GET['module_id']))) {
			echo opentabbody($tab_title['title'][2], $tab_title['id'][2], $tab_active);
			$this->modules_form();
			echo closetabbody();
		}
		echo closetab();
	}

	/* Stable components only */
	private function phpfusion_field_DOM($data) {
		// deactivate all.
		//print_p($data);
		global $settings, $locale;
		$profile_method = 'input';
		$user_data = array();
		$options['deactivate'] = 0;
		$options['inline'] = 1;
		if ($data['field_error']) $options['error_text'] = $data['field_error'];
		if ($data['field_required']) $options['required'] = $data['field_required'];
		if ($data['field_default']) $options['placeholder'] = $data['field_default'];
		if ($data['field_options']) $option_list = explode(',', $data['field_options']);
		if ($data['field_type'] == 'file') {
			if (file_exists($this->plugin_locale_folder.$data['field_name'].".php")) include $this->plugin_locale_folder.$data['field_name'].".php";
			if (file_exists($this->plugin_folder.$data['field_name']."_include.php")) include $this->plugin_folder.$data['field_name']."_include.php";
			if (isset($user_fields)) return $user_fields;
		} elseif ($data['field_type'] == 'textbox') {
			return form_text($data['field_title'], $data['field_name'], $data['field_name'], '', $options);
		} elseif ($data['field_type'] == 'select') {
			return form_select($data['field_title'], $data['field_name'], $data['field_name'], $option_list, '', $options);
		} elseif ($data['field_type'] == 'textarea') {
			return form_textarea($data['field_title'], $data['field_name'], $data['field_name'], '', $options);
		} elseif ($data['field_type'] == 'checkbox') {
			return form_checkbox($data['field_title'], $data['field_name'], $data['field_name'], '', $options);
		} elseif ($data['field_type'] == 'datepicker') {
			return form_datepicker($data['field_title'], $data['field_name'], $data['field_name'], '', $options);
		} elseif ($data['field_type'] == 'colorpicker') {
			return form_colorpicker($data['field_title'], $data['field_name'], $data['field_name'], '', $options);
		} elseif ($data['field_type'] == 'uploader') {
			return form_fileinput($data['field_title'], $data['field_name'], $data['field_name'], '', $options);
		} elseif ($data['field_type'] == 'hidden') {
			return form_hidden($data['field_title'], $data['field_name'], $data['field_name'], '', $options);
		} elseif ($data['field_type'] == 'address') {
			return form_address($data['field_title'], $data['field_name'], $data['field_name'], '', $options);
		} elseif ($data['field_type'] == 'toggle') {
			return form_toggle($data['field_title'], $data['field_name'], $data['field_name'], array($locale['off'],
				$locale['on']), $data['field_name'], $options);
		}
	}
}

?>