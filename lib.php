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
 * @package   local_cohortpro
 * @copyright 2019, YuriyYurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function local_cohortpro_extend_settings_navigation($settingsnav, $context) {
    global $CFG, $PAGE;

    if (!is_siteadmin() && !has_capability('local/cohortpro:manager', $context)) {
        return;
    }

    $settingnode = $settingsnav->find('users', navigation_node::TYPE_SETTING);

    if (!$settingnode) {
        return;
    }

    $urltext = get_string('pluginname', 'local_cohortpro');
    $url = null;
    $mainnode = $settingnode->create(
        $urltext,
        $url,
        navigation_node::TYPE_CONTAINER,
        null,
        'cohortpro',
        new pix_icon('i/report', $urltext)
    );
    $settingnode->add_node($mainnode);

    $urltext = get_string('menu_filter','local_cohortpro');
    $url = new moodle_url('/local/cohortpro/index.php');
    $managernode = navigation_node::create(
        $urltext,
        $url,
        navigation_node::NODETYPE_LEAF,
        'cohortpro_manager',
        'cohortpro_manager',
        new pix_icon('i/settings', $urltext)
    );
    if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
        $managernode->make_active();
    }
    $mainnode->add_node($managernode);

    $urltext = get_string('menu_upload','local_cohortpro');
    $url = new moodle_url('/local/cohortpro/uploaduser.php');
    $uploadnode = navigation_node::create(
        $urltext,
        $url,
        navigation_node::NODETYPE_LEAF,
        'cohortpro_upload',
        'cohortpro_upload',
        new pix_icon('i/settings', $urltext)
    );
    if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
        $uploadnode->make_active();
    }
    $mainnode->add_node($uploadnode);
}
