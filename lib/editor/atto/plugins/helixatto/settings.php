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
 * Settings that allow configuration of the list of tex examples in the equation editor.
 *
 * @package    atto_helixatto
 * @copyright  2015 Streaming LTD. Written by Tim Williams of Autotrain (tmw@autotrain.org)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$ADMIN->add('editoratto', new admin_category('atto_helixatto', new lang_string('pluginname', 'atto_helixatto')));

$settings = new admin_settingpage('atto_helixatto_settings', new lang_string('settings', 'atto_helixatto'));
if ($ADMIN->fulltree) {
    // Number of groups to show when collapsed.
    $name = new lang_string('hideinsert', 'atto_helixatto');
    $desc = new lang_string('hideinsert_desc', 'atto_helixatto');
    $default = 0;
    $options = $always_stream=array(0=>new lang_string("no"), 1=>new lang_string("yes"));

    $setting = new admin_setting_configselect('atto_helixatto/hideinsert',
                                              $name,
                                              $desc,
                                              $default,
                                              $options);
    $settings->add($setting);
}
