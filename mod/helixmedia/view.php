<?php


/**
 * This file contains all necessary code to view a helixmedia activity instance
 *
 * @package    mod
 * @subpackage helixmedia
 * @copyright  2009 Marc Alier, Jordi Piguillem, Nikolas Galanis
 *  marc.alier@upc.edu
 * @copyright  2009 Universitat Politecnica de Catalunya http://www.upc.edu
 * @author     Marc Alier
 * @author     Jordi Piguillem
 * @author     Nikolas Galanis
 * @author     Chris Scribner
 * @author     Tim Williams for Streaming LTD 2014
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/helixmedia/lib.php');
require_once($CFG->dirroot.'/mod/helixmedia/locallib.php');

global $CFG, $PAGE;

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or
$l  = optional_param('l', 0, PARAM_INT);  // HML ID

if ($l) {  // Two ways to specify the module
    $hmli = $DB->get_record('helixmedia', array('id' => $l), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('helixmedia', $hmli->id, $hmli->course, false, MUST_EXIST);

} else {
    $cm = get_coursemodule_from_id('helixmedia', $id, 0, false, MUST_EXIST);
    $hmli = $DB->get_record('helixmedia', array('id' => $cm->instance), '*', MUST_EXIST);
}

$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);

$toolconfig = array();
$toolconfig["launchcontainer"]=get_config("helixmedia", "default_launch");

$PAGE->set_cm($cm, $course); // set's up global $COURSE

if (method_exists("context_module", "instance"))
    $context = context_module::instance($cm->id);
else
    $context = get_context_instance(CONTEXT_MODULE, $cm->id);

$PAGE->set_context($context);

$url = new moodle_url('/mod/helixmedia/view.php', array('id'=>$cm->id));
$PAGE->set_url($url);

$launchcontainer = lti_get_launch_container($hmli, $toolconfig);

$launchurl="launch.php?type=".HML_LAUNCH_NORMAL."&id=".$cm->id;

if ($launchcontainer == LTI_LAUNCH_CONTAINER_REPLACE_MOODLE_WINDOW) {
  redirect($launchurl);
} else if ($launchcontainer == LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS) {
    $PAGE->set_pagelayout('frametop'); //Most frametops don't include footer, and pre-post blocks
    $PAGE->blocks->show_only_fake_blocks(); //Disable blocks for layouts which do include pre-post blocks
} else {
  $PAGE->set_pagelayout('incourse');
}

require_login($course);

$event = \mod_helixmedia\event\course_module_viewed::create(array(
    'objectid' => $hmli->id,
    'context' => $context
));
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('helixmedia', $hmli);
$event->trigger();


$pagetitle = strip_tags($course->shortname.': '.format_string($hmli->name));
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);
if (has_capability('mod/helixmedia:addinstance', $context)) {
    $PAGE->set_button(update_module_button($cm->id, $course->id, get_string("modulename", "helixmedia")));
}

// Print the page header
echo $OUTPUT->header();

if ($hmli->showtitlelaunch) {
    // Print the main part of the page
    echo $OUTPUT->heading(format_string($hmli->name));
}

if ($hmli->showdescriptionlaunch && $hmli->intro) {
    echo $OUTPUT->box($hmli->intro, 'generalbox description', 'intro');
}

if ( $launchcontainer == LTI_LAUNCH_CONTAINER_WINDOW ) {
    echo "<script type=\"text/javascript\">//<![CDATA[\n";
    echo "window.open(".$launchurl.",'helixmedia');";
    echo "//]]\n";
    echo "</script>\n";
    echo "<p style='text-align:center;'>".get_string("hml_in_new_window_message", "helixmedia")."</p>";
    echo "<p style='text-align:center;'><a href='".$launchurl."' target='_blank'>".get_string("hml_in_new_window", "helixmedia")."</a></p>\n";
} else {
    // Request the launch content with an object tag
    echo '<object id="contentframe" height="650" width="100%" type="text/html" data="'.htmlspecialchars($launchurl).'"></object>';

    //Output script to make the object tag be as large as possible
?>
        <script type="text/javascript">
        //<![CDATA[

<?php
    if ($CFG->version>=2012120300) {
        echo "                YUI().use(\"yui2-dom\", function(Y) {\n";
        echo "                var dom = Y.YUI2.util.Dom;\n";
    } else {
        echo "                (function(){\n";
        echo "                var dom = YAHOO.util.Dom;\n";
    }

    //Take scrollbars off the outer document to prevent double scroll bar effect if there are no blocks
    //if ($launchcontainer == LTI_LAUNCH_CONTAINER_EMBED_NO_BLOCKS)
    //    echo "                document.body.style.overflow = \"hidden\";";

?>
                var frame = document.getElementById("contentframe");
                var padding = 250; 
                var lastHeight;
                var resize = function(){
                    var viewportHeight = dom.getViewportHeight();
                    if(lastHeight !== Math.min(dom.getDocumentHeight(), viewportHeight)){
                        frame.style.height = viewportHeight - dom.getY(frame) + padding + "px";
                        lastHeight = Math.min(dom.getDocumentHeight(), dom.getViewportHeight());
                    }
                };
                resize();
                setTimeout(resize, 500);

            })();
        //]]
        </script>
<?php
}
// Finish the page
echo $OUTPUT->footer();

