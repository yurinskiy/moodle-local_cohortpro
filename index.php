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
$mode = optional_param('mode', 0, PARAM_INT);
$cohortid = optional_param('id', 0, PARAM_INT);
$searchquery = optional_param('search', '', PARAM_RAW);
$onlyempty = optional_param('onlyempty', false, PARAM_BOOL);

require_login();

$context = context_system::instance();

$manager = has_capability('moodle/cohort:manage', $context);
if (!$manager) {
    require_capability('moodle/cohort:view', $context);
}

$PAGE->set_pagelayout('admin');
$PAGE->set_context($context);
$PAGE->set_url('/local/cohortpro/index.php', ['contextid' => $context->id]);
$PAGE->set_title(get_string('cohorts', 'cohort'));
$PAGE->set_heading($COURSE->fullname);

if ($onlyempty) {
    $cohorts = local_cohortpro_get_all_empty_cohorts($page, 25, $searchquery);
} else {
    $cohorts = cohort_get_all_cohorts($page, 25, $searchquery);
}

$count = '';
if ($cohorts['allcohorts'] > 0) {
    if ($searchquery === '') {
        $count = ' (' . $cohorts['allcohorts'] . ')';
    } else {
        $count = ' (' . $cohorts['totalcohorts'] . '/' . $cohorts['allcohorts'] . ')';
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('cohortsin', 'cohort', $context->get_context_name()) . $count);

$params = [
        'page' => $page
];
if ($searchquery) {
    $params['search'] = $searchquery;
}

// Add search form.
$search = html_writer::start_tag('form', [
        'id'     => 'searchcohortquery',
        'method' => 'get',
        'class'  => 'form-inline search-cohort'
]);
$search .= html_writer::start_div('m-b-1');
$search .= html_writer::label(get_string('searchcohort', 'cohort'), 'cohort_search_q', true,
        ['class' => 'm-r-1']); // No : in form labels!
$search .= html_writer::empty_tag('input', [
        'id'    => 'cohort_search_q',
        'type'  => 'text',
        'name'  => 'search',
        'value' => $searchquery,
        'class' => 'form-control m-r-1'
]);
$search .= html_writer::empty_tag('input', [
        'type'  => 'submit',
        'value' => get_string('search', 'cohort'),
        'class' => 'btn btn-secondary'
]);
$search .= html_writer::end_div();
$search .= html_writer::start_div('m-b-1');
$params_onlyempty = [
        'id'    => 'cohort_onlyempty',
        'type'  => 'checkbox',
        'name'  => 'onlyempty',
        'class' => 'form-control',
        'style' => 'margin-top: 1px !important;'
];
if ($onlyempty) {
    $params_onlyempty['checked'] = 'checked';
}
$search .= html_writer::empty_tag('input', $params_onlyempty);
$search .= html_writer::label('Без участников', 'cohort_onlyempty', true, [
        'class' => 'm-r-1',
        'style' => 'margin-top: 0 !important;'
]);
$search .= html_writer::end_div();
$search .= html_writer::end_tag('form');
echo $search;

// Output pagination bar.
echo $OUTPUT->paging_bar(
        $cohorts['totalcohorts'],
        $page,
        25,
        new moodle_url('/local/cohortpro/index.php', $params)
);

$data = [];

foreach ($cohorts['cohorts'] as $cohort) {
    $cohortcontext = context::instance_by_id($cohort->contextid);

    if ($cohortcontext->contextlevel == CONTEXT_COURSECAT) {
        $category = html_writer::link(new moodle_url('/cohort/index.php',
                ['contextid' => $cohort->contextid]), $cohortcontext->get_context_name(false));
    } else {
        $category = $cohortcontext->get_context_name(false);
    }

    if (empty($cohort->component)) {
        $component = get_string('nocomponent', 'cohort');
    } else {
        $component = get_string('pluginname', $cohort->component);
    }

    $urlparams = [
            'id'        => $cohort->id,
            'returnurl' => (new moodle_url('/local/cohortpro/index.php', $params))->out_as_local_url()
    ];

    $members = local_cohortpro_get_count_members($cohort);
    if ($members > 0) {
        $members = local_cohortpro_cell_link(
                new moodle_url('/local/cohortpro/members.php', $urlparams),
                'i/preview',
                $members,
                'Показать участников'
        );
    }

    $courses = local_cohortpro_get_count_courses($cohort);
    if ($courses > 0) {
        $courses = local_cohortpro_cell_link(
                new moodle_url('/local/cohortpro/courses.php', $urlparams),
                'i/preview',
                $courses,
                'Показать курсы'
        );
    }

    $buttons = [
            html_writer::link(
                    new moodle_url('/cohort/edit.php', $urlparams),
                    $OUTPUT->pix_icon('t/edit', get_string('edit')),
                    ['title' => get_string('edit')]
            )
    ];

    $line = [
            $category,
            $cohort->name,
            $component,
            $members,
            $courses,
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
        get_string('component', 'cohort'),
        get_string('memberscount', 'cohort'),
        'Количество курсов',
        get_string('edit')
];
$table->colclasses = [
        'leftalign',
        'leftalign',
        'centeralign',
        'centeralign',
        'centeralign',
        'centeralign'
];
$table->id = 'cohorts';
$table->attributes['class'] = 'admintable generaltable';
$table->data = $data;

echo html_writer::table($table);
echo $OUTPUT->paging_bar(
        $cohorts['totalcohorts'],
        $page,
        25,
        new moodle_url('/local/cohortpro/index.php', $params)
);

echo $OUTPUT->footer();

?>