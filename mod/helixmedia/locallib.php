<?php


/**
 * This file contains a library of functions and constants for the helixmedia module
 *
 * @package    mod
 * @subpackage helixmedia
 * @author     Tim Williams (For Streaming LTD)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/mod/lti/lib.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');

//Activity types
define('HML_LAUNCH_NORMAL', 1);
define('HML_LAUNCH_THUMBNAILS', 2);
define('HML_LAUNCH_EDIT', 3);

//Special type for migration from the repository module
define('HML_LAUNCH_RELINK', 4);

//Assignment submission types
define('HML_LAUNCH_STUDENT_SUBMIT', 5);
define('HML_LAUNCH_STUDENT_SUBMIT_PREVIEW', 17);
define('HML_LAUNCH_STUDENT_SUBMIT_THUMBNAILS', 6);
define('HML_LAUNCH_VIEW_SUBMISSIONS', 7);
define('HML_LAUNCH_VIEW_SUBMISSIONS_THUMBNAILS', 8);

//TinyMCE types. Do not change these values, they are embedded in the TinyMCE plugin html code
define('HML_LAUNCH_TINYMCE_EDIT', 9);
define('HML_LAUNCH_TINYMCE_VIEW', 10);

//Submission Feedback types
define('HML_LAUNCH_FEEDBACK', 11);
define('HML_LAUNCH_FEEDBACK_THUMBNAILS', 12);

//Submission Feedback types
define('HML_LAUNCH_VIEW_FEEDBACK', 13);
define('HML_LAUNCH_VIEW_FEEDBACK_THUMBNAILS', 14);

//ATTO Types. Do not change these values, they are embedded in the ATTO plugin html code
define('HML_LAUNCH_ATTO_EDIT', 15);
define('HML_LAUNCH_ATTO_VIEW', 16);

/**Note next ID should be 18**/


/**
 * Prints a Helix Media activity
 *
 * @param $instance The helixmedia instance.
 * @param $type The Helix Launch Type
 * @param $ref The value for the custom_video_ref parameter
 * @param $ret The return URL to set for the modal dialogue
 */

function helixmedia_view($instance, $type=HML_LAUNCH_NORMAL, $ref=-1, $ret="") {
    global $PAGE, $CFG, $DB;

    $mod_config=get_config("helixmedia");

    if (property_exists($instance, "version"))
        $version=$hml->version;
    else
        $version=get_config('mod_helixmedia', 'version');

    //Check to see if the DB has duplicate preid's for the assignment submission, if it does send an
    //old version number to trigger the fix for this problem. The check doesn't need to be exhaustive
    //Either the whole lot will match, or none will.
    if ($type==HML_LAUNCH_VIEW_SUBMISSIONS_THUMBNAILS || $type==HML_LAUNCH_VIEW_SUBMISSIONS)
    {
        $ass=$DB->get_record("course_modules", array("id"=>$instance->cmid));
        $recs=$DB->get_records("assignsubmission_helixassign", array("assignment"=>$ass->instance));
        $num=-1;
        foreach($recs as $rec)
        {
            if ($num==-1)
            {
                $num=$rec->preid;
            }
            else
            {
                if ($num==$rec->preid)
                {
                    $version=2014111700;
                    break;
                }
            }
        }
    }

    //Set up the type config
    $typeconfig = (array)$instance;

    $typeconfig['sendname'] = $mod_config->sendname;
    $typeconfig['sendemailaddr'] = $mod_config->sendemailaddr;

    $typeconfig['customparameters'] = $mod_config->custom_params."\nhml_version=".$version;

    switch ($type) {
        case HML_LAUNCH_VIEW_SUBMISSIONS_THUMBNAILS:
        case HML_LAUNCH_THUMBNAILS:
        case HML_LAUNCH_STUDENT_SUBMIT_THUMBNAILS:
        case HML_LAUNCH_FEEDBACK_THUMBNAILS:
        case HML_LAUNCH_VIEW_FEEDBACK_THUMBNAILS:
            $typeconfig['customparameters'].="\nthumbnail=Y\nthumbnail_width=176\nthumbnail_height=99";
            break;
    }

    switch ($type) {
        case HML_LAUNCH_NORMAL:
        case HML_LAUNCH_TINYMCE_VIEW:
        case HML_LAUNCH_ATTO_VIEW:
            $typeconfig['customparameters'].="\nview_only=Y\nno_horiz_borders=Y";
            break;
        case HML_LAUNCH_EDIT:
        case HML_LAUNCH_TINYMCE_EDIT:
        case HML_LAUNCH_ATTO_EDIT:
            $typeconfig['customparameters'].="\nno_horiz_borders=Y";
            break;
        case HML_LAUNCH_STUDENT_SUBMIT:
        case HML_LAUNCH_STUDENT_SUBMIT_THUMBNAILS:
            $typeconfig['customparameters'].="\nlink_response=Y\nlink_type=Assignment";
            $typeconfig['customparameters'].="\nassignment_ref=".$instance->cmid;
            $typeconfig['customparameters'].="\ntemp_assignment_ref=".helixmedia_get_assign_into_refs($instance->cmid)."\n";
            break;
        case HML_LAUNCH_STUDENT_SUBMIT_PREVIEW:
            $typeconfig['customparameters'].="\nlink_type=Assignment";
            $typeconfig['customparameters'].="\nassignment_ref=".$instance->cmid."\n";
            /**Note play_only is redundant in HML 3.1.007 onwards and will be ignored**/
            $typeconfig['customparameters'].="\nplay_only=Y\nno_horiz_borders=Y";
            $typeconfig['customparameters'].="\ntemp_assignment_ref=".helixmedia_get_assign_into_refs($instance->cmid)."\n";
            break;
        case HML_LAUNCH_VIEW_SUBMISSIONS_THUMBNAILS:
        case HML_LAUNCH_VIEW_SUBMISSIONS:
            $typeconfig['customparameters'].="\nresponse_user_id=".$instance->userid;
            break;
    }
    if ($ref>-1)
        $typeconfig['customparameters'].="\nvideo_ref=".$ref;

    $typeconfig['customparameters'].="\nlaunch_type=".$type;

    $typeconfig['acceptgrades'] = 0;
    $typeconfig['allowroster'] = 1;
    $typeconfig['forcessl'] = '0';
    $typeconfig['launchcontainer'] = $mod_config->default_launch;

    //Default the organizationid if not specified
    if (!empty($mod_config->org_id)) {
        $typeconfig['organizationid'] = $mod_config->org_id;
    } else {
        $urlparts = parse_url($CFG->wwwroot);
        $typeconfig['organizationid'] = $urlparts['host'];
    }

    $endpoint = trim($mod_config->launchurl);

    $orgid = $typeconfig['organizationid'];

    $course=$DB->get_record("course", array("id"=>$instance->course));
    $requestparams = helixmedia_build_request($instance, $typeconfig, $course);
    $launchcontainer = lti_get_launch_container($instance, $typeconfig);

    if ( $orgid ) {
        $requestparams["tool_consumer_instance_guid"] = $orgid;
    }

    switch ($type) {
        case HML_LAUNCH_EDIT:
        case HML_LAUNCH_STUDENT_SUBMIT:
        case HML_LAUNCH_FEEDBACK:
            //if (strlen($ret)>0)
            //    $requestparams['launch_presentation_return_url'] = $ret;
            //break;
        case HML_LAUNCH_TINYMCE_EDIT:
        case HML_LAUNCH_TINYMCE_VIEW:
        case HML_LAUNCH_ATTO_EDIT:
        case HML_LAUNCH_ATTO_VIEW:
            break;
        default:
            /**Mobile devices launch without the Moodle frame, so we need a return URL here**/

            if (method_exists("core_useragent", "check_browser_version"))
                $devicetype=core_useragent::get_device_type();
            else
                $devicetype = get_device_type();
            if ($devicetype === 'mobile' || $devicetype === 'tablet' ) {
                $returnurlparams = array('id' => $course->id);
                $url = new moodle_url('/course/view.php', $returnurlparams);
                $returnurl = $url->out(false);
                $requestparams['launch_presentation_return_url'] = $returnurl;
            }
    }

    if ($type==HML_LAUNCH_NORMAL || $type==HML_LAUNCH_TINYMCE_VIEW || $type==HML_LAUNCH_ATTO_VIEW ) {
        $requestparams['roles']="Learner";
    }
    $params = lti_sign_parameters($requestparams, $endpoint, "POST", $mod_config->consumer_key, $mod_config->shared_secret);

    if (isset($instance->debuglaunch))
    {
        $debuglaunch = ( $instance->debuglaunch == 1 );
        /** Moodle 2.8 strips this out at the form submission stage, so this needs to be added after the request is signed in 2.8 since
            the remote server will never see this parameter **/
        if ($CFG->version>=2014111000)
        {
            $submittext = get_string('press_to_submit', 'lti');
            $params['ext_submit'] = $submittext;
        }
    }
    else
        $debuglaunch=false;

    if ($type==HML_LAUNCH_RELINK)
        return helixmedia_curl_post_launch_html($params, $endpoint);
    else
        echo lti_post_launch_html($params, $endpoint, $debuglaunch);
}

function helixmedia_get_assign_into_refs($assign_id) {
    global $DB;
    $refs="";

    $module = $DB->get_record("course_modules", array("id" => $assign_id));

    if (!$module)
        return "";

    $assignment = $DB->get_record("assign", array("id" => $module->instance));

    if (!$assignment)
        return "";

    $first=true;
    $pos=strpos($assignment->intro, "/mod/helixmedia/launch.php");

    while($pos!=false) {

        $l=strpos($assignment->intro, "l=", $pos);

        if ($l!=false) {
            $l=$l+2;
            $e=strpos($assignment->intro, "\"", $l);
            if ($e!=false) {
                if (!$first)
                    $refs.=",";
                else
                    $first=false;
                $refs.=substr($assignment->intro, $l, $e-$l);
            }
        }
        $pos=strpos($assignment->intro, "/mod/helixmedia/launch.php", $pos+1);
    }
    return $refs;
}

function helixmedia_curl_post_launch_html($params, $endpoint) {
    global $CFG;

    set_time_limit(0);
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_TIMEOUT, 50);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (compatible; curl; like Firefox)");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); 

    $cookies_file=$CFG->dataroot.DIRECTORY_SEPARATOR."temp".DIRECTORY_SEPARATOR."helixmedia-curl-cookies-".microtime(true).".tmp";
    while(file_exists($cookies_file))
        $cookies_file=$CFG->dataroot.DIRECTORY_SEPARATOR."temp".DIRECTORY_SEPARATOR."helixmedia-curl-cookies-".microtime(true).".tmp";
    curl_setopt($ch, CURLOPT_COOKIESESSION, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookies_file);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookies_file);

    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);

    //Uncomment this for verbose debugging
    //curl_setopt($ch, CURLOPT_VERBOSE, true);
    //$verbose = fopen('php://temp', 'rw+');
    //curl_setopt($ch, CURLOPT_STDERR, $verbose);

    $result=curl_exec($ch);
    if (curl_errno($ch)) {
        error_log("CURL Error connecting to HML LTI: ". curl_error($ch));
    } 
    curl_close($ch);

    //Uncomment this for verbose debugging
    //if ($result === FALSE) {
    //    printf("cUrl error (#%d): %s<br>\n", curl_errno($curlHandle),
    //           htmlspecialchars(curl_error($curlHandle)));
    //}
    //rewind($verbose);
    //$verboseLog = stream_get_contents($verbose);
    //echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";

    if (file_exists($cookies_file))
        unlink($cookies_file);

    return $result;
}

/**
 * This function builds the request that must be sent to the tool producer
 *
 * @param object    $instance       HML instance object
 * @param object    $typeconfig     HML tool configuration
 * @param object    $course         Course object
 *
 * @return array    $request        Request details
 */
function helixmedia_build_request($instance, $typeconfig, $course) {
    global $USER, $CFG;

    if (empty($instance->cmid)) {
        $instance->cmid = 0;
    }

    $role = helixmedia_get_ims_role($USER, $instance->cmid, $course->id);

    $requestparams = array(
        'resource_link_id' => $instance->preid,
        'resource_link_title' => $instance->name,
        'resource_link_description' => strip_tags($instance->intro),
        'user_id' => $USER->id,
        'roles' => $role,
        'context_id' => $course->id,
        'context_label' => $course->shortname,
        'context_title' => $course->fullname,
        'launch_presentation_locale' => current_language()
    );

    $placementsecret = $instance->servicesalt;

    if ( isset($placementsecret) ) {
        $sourcedid = json_encode(lti_build_sourcedid($instance->id, $USER->id, null, $placementsecret));
    }

    if ( isset($placementsecret) &&
         ( $typeconfig['acceptgrades'] == LTI_SETTING_ALWAYS ||
         ( $typeconfig['acceptgrades'] == LTI_SETTING_DELEGATE && $instance->instructorchoiceacceptgrades == LTI_SETTING_ALWAYS ) ) ) {
        $requestparams['lis_result_sourcedid'] = $sourcedid;

        if ($typeconfig['forcessl'] == '1') {
            $serviceurl = lti_ensure_url_is_https($serviceurl);
        }

        $requestparams['lis_outcome_service_url'] = $serviceurl;
    }

    // Send user's name and email data if appropriate
    if ( $typeconfig['sendname'] == LTI_SETTING_ALWAYS ||
         ( $typeconfig['sendname'] == LTI_SETTING_DELEGATE && $instance->instructorchoicesendname == LTI_SETTING_ALWAYS ) ) {
        $requestparams['lis_person_name_given'] =  $USER->firstname;
        $requestparams['lis_person_name_family'] =  $USER->lastname;
        $requestparams['lis_person_name_full'] =  $USER->firstname." ".$USER->lastname;
    }

    if ( $typeconfig['sendemailaddr'] == LTI_SETTING_ALWAYS ||
         ( $typeconfig['sendemailaddr'] == LTI_SETTING_DELEGATE && $instance->instructorchoicesendemailaddr == LTI_SETTING_ALWAYS ) ) {
        $requestparams['lis_person_contact_email_primary'] = $USER->email;
    }

    // Concatenate the custom parameters from the administrator and the instructor
    // Instructor parameters are only taken into consideration if the administrator
    // has giver permission
    $customstr = $typeconfig['customparameters'];

    $instructorcustomstr = "";
    $custom = array();
    $instructorcustom = array();
    if ($customstr) {
        $custom = helix_split_custom_parameters($customstr);
    }

    if (isset($typeconfig['allowinstructorcustom']) && $typeconfig['allowinstructorcustom'] == LTI_SETTING_NEVER) {
        $requestparams = array_merge($custom, $requestparams);
    } else {
        if ($instructorcustomstr) {
            $instructorcustom = helix_split_custom_parameters($instructorcustomstr);
        }
        foreach ($instructorcustom as $key => $val) {
            // Ignore the instructor's parameter
            if (!array_key_exists($key, $custom)) {
                $custom[$key] = $val;
            }
        }
        $requestparams = array_merge($custom, $requestparams);
    }

    // Make sure we let the tool know what LMS they are being called from
    $requestparams["ext_lms"] = "moodle-2";
    $requestparams['tool_consumer_info_product_family_code'] = 'moodle';
    $requestparams['tool_consumer_info_version'] = strval($CFG->version);

    // Add oauth_callback to be compliant with the 1.0A spec
    $requestparams['oauth_callback'] = 'about:blank';

    //The submit button needs to be part of the signature as it gets posted with the form.
    //This needs to be here to support launching without javascript.

    /** Moodle 2.8 strips this parameter out when the launch form is submitted, so if we add it here, it will be included in the signature
       and the signature verification will fail on the remote server. However, Moodle 2.7 and lower always submits this, so it must be
       processed as part of the signature **/
    if ($CFG->version<2014111000)
    {
        $submittext = get_string('press_to_submit', 'lti');
        $requestparams['ext_submit'] = $submittext;
    }

    $requestparams['lti_version'] = 'LTI-1p0';
    $requestparams['lti_message_type'] = 'hml-launch-request';

    return $requestparams;
}

/**
 * Splits the custom parameters field to the various parameters
 *
 * @param string $customstr     String containing the parameters
 *
 * @return Array of custom parameters
 */
function helix_split_custom_parameters($customstr) {
    $lines = preg_split("/[\n;]/", $customstr);
    $retval = array();
    foreach ($lines as $line) {
        $pos = strpos($line, "=");
        if ( $pos === false || $pos < 1 ) {
            continue;
        }
        $key = trim(core_text::substr($line, 0, $pos));
        $val = trim(core_text::substr($line, $pos+1, strlen($line)));
        $key = lti_map_keyname($key);
        $retval['custom_'.$key] = $val;
    }
    return $retval;
}

/**
 * Gets the IMS role string for the specified user and Helixmedia course module.
 *
 * @param mixed $user User object or user id
 * @param int $cmid The course module id of the LTI activity
 * @return string A role string suitable for passing with an LTI launch
 */
function helixmedia_get_ims_role($user, $cmid, $courseid) {
    $roles = array();

    if (empty($cmid) || $cmid==-1) {
        //If no cmid is passed, check if the user is a teacher in the course
        //This allows other modules to programmatically "fake" a launch without
        //a real Helixmedia instance
        $coursecontext = context_course::instance($courseid);

        if (has_capability('moodle/course:manageactivities', $coursecontext)) {
            array_push($roles, 'Instructor');
        } else {
            array_push($roles, 'Learner');
        }
    } else {
        $context = context_module::instance($cmid);

        if (has_capability('mod/helixmedia:manage', $context)) {
            array_push($roles, 'Instructor');
        } else {
            array_push($roles, 'Learner');
        }
    }

    if (is_siteadmin($user)) {
        array_push($roles, 'urn:lti:sysrole:ims/lis/Administrator');
    }

    return join(',', $roles);
}

/**
* Gets the modal dialog using the supplied params
* @param pre_id The resource link ID
* @param params_thumb The get request parameters for the thumbnail
* @param params_link The get request parameters for the modal link
* @param style An optional style for the containing table
* @param linkimage Optional link image file name
* @param linkimagewidth The width of the link image in px, -1 for none
* @param linkimageheight The height of the link image in px, -1 for none
* @param c The course ID, or -1 if not known
* @param statusCheck true if the statusCheck method should be used
* @return The HTML for the dialog
**/

function helixmedia_get_modal_dialog($pre_id, $params_thumb, $params_link, $style="",
    $linkimage="moodle-lti-upload-btn.png", $linkimagewidth="202", $linkimageheight="56", $c=-1, $statusCheck=true) {
    global $CFG, $PAGE, $COURSE, $DB, $USER;

    if ($c>-1)
        $course=$DB->get_record("course", array("id"=>$c));
    else
        $course=$COURSE;

    $params_thumb='course='.$course->id.'&'.$params_thumb;
    $params_link='course='.$course->id.'&ret='.base64_encode(curPageURL()).'&'.$params_link;

    if ($CFG->version<2012120300) {
        $PAGE->requires->yui2_lib('container');
        $PAGE->requires->yui2_lib('connection');
    }

    $PAGE->requires->js('/mod/helixmedia/hml_form_js.php');


    if ($linkimagewidth<0 && $linkimagewidth<0)
        $html='<a class="pop_up_selector_link" href="'.$CFG->wwwroot.'/mod/helixmedia/container.php?'.htmlspecialchars($params_link).'">'.
            $linkimage.'</a>';
    else
        $html='<table style="'.$style.'"><tr><td>'.
            '<iframe id="thumbframe" style="border-width:0px;width:200px;height:128px;" scrolling="no" frameborder="0" '.
            'src="'.$CFG->wwwroot.'/mod/helixmedia/launch.php?'.htmlspecialchars($params_thumb).'"></iframe>'.
            '</td><td style="vertical-align:middle;">'.
            '<a class="pop_up_selector_link" href="'.$CFG->wwwroot.'/mod/helixmedia/container.php?'.htmlspecialchars($params_link).'">'.
            '<img src="'.$CFG->wwwroot.'/mod/helixmedia/icons/'.$linkimage.'" width="'.$linkimagewidth.'" height="'.$linkimageheight.'" alt="'.
            get_string('choosemedia_title', 'helixmedia').' title="" /></a>'.
            '</td></tr></table>';

    $html.='<script type="text/javascript">'.
        'var thumburl="'.$CFG->wwwroot.'/mod/helixmedia/launch.php?'.$params_thumb.'";'.
        'var resID='.$pre_id.';'.
        'var userID='.$USER->id.';'.
        'var statusURL="'.helixmedia_get_status_url().'";'.
        'var doStatusCheck='.$statusCheck.';'.
        '</script>';

    return $html;
}

function curPageURL() {
 $pageURL = 'http';
 if (array_key_exists("HTTPS", $_SERVER) && $_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
 $pageURL .= "://";
 if ($_SERVER["SERVER_PORT"] != "80") {
  $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$_SERVER["REQUEST_URI"];
 } else {
  $pageURL .= $_SERVER["SERVER_NAME"].$_SERVER["REQUEST_URI"];
 }
 return $pageURL;
}

function helixmedia_get_instance_size($preid, $course) {
    global $CFG;
    $url = trim(get_config("helixmedia", "launchurl"));
    $pos = str_contains($url, "/Launch", true);
    $url = substr($url, 0, $pos)."PlayerWidth";
    $retdata = helixmedia_curl_post_launch_html(array("context_id" => $course, "resource_link_id" => $preid), $url);

    return intval($retdata);
}

function helixmedia_get_status_url() {
    $status_url = trim(get_config("helixmedia", "launchurl"));
    $pos = str_contains($status_url, "/Launch", true);
    return substr($status_url, 0, $pos)."/SessionStatus";
}

function str_contains($haystack, $needle, $ignoreCase = false) {
    if ($ignoreCase) {
        $haystack = strtolower($haystack);
        $needle   = strtolower($needle);
    }
    $needlePos = strpos($haystack, $needle);
    return ($needlePos === false ? false : ($needlePos+1));
}

