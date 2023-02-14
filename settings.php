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

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig && isset($ADMIN)) {
    $settings = new admin_settingpage('relationshipsettings', get_string('pluginname', 'local_relationship'));
    $settings->add(new admin_setting_heading('local_relationship/settings', get_string('massassignusers', 'local_relationship'), get_string('massassignusers_desc', 'local_relationship')));

    $optionsauthtype = array();
    foreach(get_enabled_auth_plugins(false) as $opt){
        $optionsauthtype[$opt] = $opt;
    }
    $settings->add(new admin_setting_configselect('local_relationship/authtype', get_string('authtype', 'local_relationship'),
        get_string('authtype_desc', 'local_relationship') ,'manual', $optionsauthtype));

    $options = array(0=>get_string('no'), 1=>get_string('yes'));
    $setting = new admin_setting_configselect('local_relationship/searchsccp', get_string('searchsccp', 'local_relationship'),
        get_string('searchsccp_desc', 'local_relationship'), 0, $options);
    $settings->add($setting);

    $ADMIN->add('localplugins', $settings);
}
