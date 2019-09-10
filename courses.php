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
 * @package   local_cohort_remove
 * @copyright 2019, YuriyYurinskiy <yuriyyurinskiy@yandex.ru>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once('./locallib.php');

$id = required_param('id', PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);

require_login();

$cohort = local_cohortpro_get_cohort($id);
$context = context::instance_by_id($cohort->contextid, MUST_EXIST);

$manager = has_capability('moodle/cohort:manage', $context);
if (!$manager) {
    require_capability('moodle/cohort:view', $context);
}

$PAGE->set_context($context);
$PAGE->set_url('/local/cohortpro/courses.php', ['id' => $id]);
$PAGE->set_pagelayout('admin');

if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', ['id' => $context->instanceid], '*', MUST_EXIST);
    navigation_node::override_active_url(new moodle_url('/local/cohortpro/index.php', ['contextid' => $cohort->contextid]));
} else {
    navigation_node::override_active_url(new moodle_url('/local/cohortpro/index.php', []));
}

$PAGE->navbar->add('Курсы');
$PAGE->set_title('Просмотр курсов глобальной группы');
$PAGE->set_heading($COURSE->fullname);

if ($returnurl) {
    $returnurl = new moodle_url($returnurl);
} else {
    $returnurl = new moodle_url('/local/cohortpro/index.php', ['contextid' => $cohort->contextid]);
}

$params = [
        'page' => $page
];

$courses = local_cohortpro_get_courses($cohort);

echo $OUTPUT->header();
echo $OUTPUT->heading("Курсы глобальной группы «{$cohort->name}»");

echo $OUTPUT->paging_bar(
        $courses['totalcourses'],
        $page,
        25,
        new moodle_url('/local/cohortpro/courses.php', $params)
);

$data = [];

foreach ($courses['courses'] as $course) {
    $line = [
            "<a href=\"../../course/view.php?id=$course->id\">$course->fullname</a>"
    ];

    $data[] = $row = new html_table_row($line);
    if (!$course->visible) {
        $row->attributes['class'] = 'dimmed_text';
    }
}

$table = new html_table();
$table->head = [
        'Полное название курса'
];
$table->colclasses = [
        'leftalign'
];
$table->id = 'cohorts';
$table->attributes['class'] = 'admintable generaltable';
$table->data = $data;

echo html_writer::table($table);
echo $OUTPUT->paging_bar(
        $courses['totalcourses'],
        $page,
        25,
        new moodle_url('/local/cohortpro/courses.php', $params)
);

echo $OUTPUT->footer();
