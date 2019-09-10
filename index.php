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
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('./locallib.php');

$page = optional_param('page', 0, PARAM_INT);
$cohort = optional_param('cohort', 0, PARAM_INT);
$searchquery = optional_param('search', '', PARAM_RAW);

require_login();

$context = context_system::instance();

$strcohorts = get_string('cohorts', 'cohort');

$PAGE->set_pagelayout('admin');
$PAGE->set_context($context);
$PAGE->set_url('/cohort/index.php', array('contextid' => $context->id));
$PAGE->set_title($strcohorts);
$PAGE->set_heading($COURSE->fullname);

echo $OUTPUT->header();

if ($cohort) {
    print_object($cohort);
    die;
}

$cohorts = cohort_get_all_cohorts($page, 25, $searchquery);

$count = '';
if ($cohorts['allcohorts'] > 0) {
    if ($searchquery === '') {
        $count = ' (' . $cohorts['allcohorts'] . ')';
    } else {
        $count = ' (' . $cohorts['totalcohorts'] . '/' . $cohorts['allcohorts'] . ')';
    }
}

echo $OUTPUT->heading(get_string('cohortsin', 'cohort', $context->get_context_name()) . $count);

$params = array('page' => $page);
if ($searchquery) {
    $params['search'] = $searchquery;
}

// Add search form.
$search = html_writer::start_tag('form', array('id' => 'searchcohortquery', 'method' => 'get', 'class' => 'form-inline search-cohort'));
$search .= html_writer::start_div('m-b-1');
$search .= html_writer::label(get_string('searchcohort', 'cohort'), 'cohort_search_q', true,
    array('class' => 'm-r-1')); // No : in form labels!
$search .= html_writer::empty_tag('input', array('id' => 'cohort_search_q', 'type' => 'text', 'name' => 'search',
    'value' => $searchquery, 'class' => 'form-control m-r-1'));
$search .= html_writer::empty_tag('input', array('type' => 'submit', 'value' => get_string('search', 'cohort'),
    'class' => 'btn btn-secondary'));
$search .= html_writer::end_div();
$search .= html_writer::end_tag('form');
echo $search;

// Output pagination bar.
echo $OUTPUT->paging_bar(
    $cohorts['totalcohorts'],
    $page,
    25,
    local_cohortpro_get_baseurl($params)
);

$data = array();

foreach ($cohorts['cohorts'] as $cohort) {
    $cohortcontext = context::instance_by_id($cohort->contextid);

    if ($cohortcontext->contextlevel == CONTEXT_COURSECAT) {
        $category = html_writer::link(new moodle_url('/cohort/index.php',
            array('contextid' => $cohort->contextid)), $cohortcontext->get_context_name(false));
    } else {
        $category = $cohortcontext->get_context_name(false);
    }
    if (empty($cohort->component)) {
        $component = get_string('nocomponent', 'cohort');
    } else {
        $component = get_string('pluginname', $cohort->component);
    }

    $urlparams = array('id' => $cohort->id, 'returnurl' => local_cohortpro_get_baseurl($params));
    $buttons = [
        html_writer::link(
            new moodle_url('/cohort/edit.php', $urlparams),
            $OUTPUT->pix_icon('t/edit', get_string('edit')),
            ['title' => get_string('edit')]
        ),
        html_writer::link(
            new moodle_url($baseurl->out_as_local_url(), [
                'cohort' => $cohort->id
            ]),
            $OUTPUT->pix_icon('i/preview', get_string('show')),
            ['title' => get_string('show')]
        ),
    ];

    $line = [
        $category,
        $cohort->name,
        local_cohortpro_get_count_members($cohort),
        local_cohortpro_get_count_courses($cohort),
        $component,
        implode(' ', $buttons)
    ];

    $data[] = $row = new html_table_row($line);
    if (!$cohort->visible) {
        $row->attributes['class'] = 'dimmed_text';
    }
}

$table = new html_table();
$table->head = [
    get_string('category'),
    get_string('name', 'cohort'),
    get_string('memberscount', 'cohort'),
    'Количество курсов',
    get_string('component', 'cohort'),
    get_string('edit')
];
$table->colclasses = [
    'leftalign category',
    'leftalign name',
    'centeralign size',
    'centeralign course',
    'centeralign source',
    'centeralign action'
];
$table->id = 'cohorts';
$table->attributes['class'] = 'admintable generaltable';
$table->data = $data;

echo html_writer::table($table);
echo $OUTPUT->paging_bar($cohorts['totalcohorts'], $page, 25, $baseurl);

echo $OUTPUT->footer();

?>