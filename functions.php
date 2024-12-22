<?php
define ('PROFILE_VISIBLE_ALL',     '2'); // Only visible for users with moodle/user:update capability.
define ('PROFILE_VISIBLE_PRIVATE', '1'); // Either we are viewing our own profile or we have moodle/user:update capability.
define ('PROFILE_VISIBLE_NONE',    '0'); // Only visible for moodle/user:update capability.

class profile_field_base {
    public function __construct($fieldid=0, $userid=0) {
        global $USER;
        $this->set_fieldid($fieldid);
        $this->set_userid($userid);
        $this->load_data();
    }

    public function display_data() {
        $options = new stdClass();
        $options->para = false;
        return format_text($this->data, FORMAT_MOODLE, $options);
    }

    public function set_userid($userid) {
        $this->userid = $userid;
    }

    public function set_fieldid($fieldid) {
        $this->fieldid = $fieldid;
    }

    public function load_data() {
        global $DB;

        // Load the field object.
        if (($this->fieldid == 0) or (!($field = $DB->get_record('user_info_field', array('id' => $this->fieldid))))) {
            $this->field = null;
            $this->inputname = '';
        } else {
            $this->field = $field;
            $this->inputname = 'profile_field_'.$field->shortname;
        }

        if (!empty($this->field)) {
            $params = array('userid' => $this->userid, 'fieldid' => $this->fieldid);
            if ($data = $DB->get_record('user_info_data', $params, 'data, dataformat')) {
                $this->data = $data->data;
                $this->dataformat = $data->dataformat;
            } else {
                $this->data = $this->field->defaultdata;
                $this->dataformat = FORMAT_HTML;
            }
        } else {
            $this->data = null;
        }
    }

    public function is_visible() {
        global $USER;

        switch ($this->field->visible) {
            case PROFILE_VISIBLE_ALL:
                return true;
            case PROFILE_VISIBLE_PRIVATE:
                if ($this->userid == $USER->id) {
                    return true;
                } else {
                    return has_capability('moodle/user:viewalldetails',
                            context_user::instance($this->userid));
                }
            default:
                return has_capability('moodle/user:viewalldetails',
                        context_user::instance($this->userid));
        }
    }

    public function is_empty() {
        return ( ($this->data != '0') and empty($this->data));
    }
}

//DISPLAY CUSTOM FIELD WITH SHORTNAME COMPARISION
function profile_display_custom_field($userid, $fieldshortname) {
    global $CFG, $USER, $DB;

    if ($fields = $DB->get_records('user_info_field', array('shortname'=>$fieldshortname), 'sortorder ASC')) {
        foreach ($fields as $field) {
            $fielddatatype = $field->datatype;
            $fieldid = $field->id;
            require_once($CFG->dirroot.'/user/profile/field/'.$fielddatatype.'/field.class.php');
            $newfield = 'profile_field_'.$fielddatatype;
            $formfield = new $newfield($fieldid, $userid);
            if ($formfield->is_visible() and !$formfield->is_empty()) {
                return($formfield->display_data()); 
            }
        }
    }
}