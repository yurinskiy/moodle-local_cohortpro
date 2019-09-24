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
$method_empty = optional_param('method_empty', 0, PARAM_INT);

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$deleted = optional_param('deleted', '', PARAM_RAW);

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

$params = [
        'page' => $page
];
if ($searchquery) {
    $params['search'] = $searchquery;
}
if ($method_empty) {
    $params['method_empty'] = $method_empty;
}

if ($delete and $confirm and confirm_sesskey()) {
    $data = explode('!', $deleted);

    if (empty($data)) {
        redirect(new moodle_url('/local/cohortpro/index.php', $params), 'Вы не выбрали ни одной глобальной группы для удаления!');
        die;
    }

    foreach ($data as $value) {
        $cohort = local_cohortpro_get_by_id($value);

        if (!empty($cohort)) {
            cohort_delete_cohort($cohort);
        }
    }

    redirect(new moodle_url('/local/cohortpro/index.php', $params), 'Глобальные группы удалены!');
    die;
} else if ($delete) {
    $data = $_POST['cohorts'];

    if (empty($data)) {
        redirect(new moodle_url('/local/cohortpro/index.php', $params), 'Вы не выбрали ни одной глобальной группы для удаления!',
                10);
        die;
    }

    $deleted = [];
    $deleted_text = "";
    $error = [];

    $tdata = [];

    foreach ($data as $value) {
        $cohort = local_cohortpro_get_by_id($value);
        $member = local_cohortpro_get_count_members($cohort);

        if (!empty($cohort)) {
            $deleted[] = $value;

            $line = [
                    $cohort->name
            ];

            if ($member > 0) {
                $line[] = "{$member} (из них заблокированных - " . local_cohortpro_get_count_members($cohort, 2) . ")";
            } else {
                $line[] = "{$member}";
            }

            $tdata[] = $row = new html_table_row($line);
        }
    }

    $table = new html_table();
    $table->head = [
            get_string('name', 'cohort'),
            get_string('memberscount', 'cohort')
    ];
    $table->colclasses = [
            'leftalign',
            'leftalign'
    ];
    $table->id = 'cohorts';
    $table->data = $tdata;

    echo $OUTPUT->header();

    $yesurl = new moodle_url('/local/cohortpro/index.php', [
            'deleted'   => implode('!', $deleted),
            'delete'    => 1,
            'confirm'   => 1,
            'sesskey'   => sesskey(),
            'returnurl' => new moodle_url('/local/cohortpro/index.php', $params)
    ]);
    $messages = get_string('deletedcohort', 'local_cohortpro', html_writer::table($table))
    ;
    echo $OUTPUT->confirm($messages, $yesurl, new moodle_url('/local/cohortpro/index.php', $params));

    echo $OUTPUT->footer();
    die;
}

$cohorts = local_cohortpro_get_all_empty_cohorts($page, 25, $searchquery, $method_empty);

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

$params_0 = [
        'type'    => 'radio',
        'id'      => 'method_empty0',
        'name'    => 'method_empty',
        'class'   => 'form-control',
        'style'   => 'margin-top: 1px !important;',
        'onclick' => 'this.form.submit()',
        'value'   => 0
];

$params_1 = [
        'type'    => 'radio',
        'id'      => 'method_empty1',
        'name'    => 'method_empty',
        'class'   => 'form-control',
        'style'   => 'margin-top: 1px !important;',
        'onclick' => 'this.form.submit()',
        'value'   => 1
];

$params_2 = [
        'type'    => 'radio',
        'id'      => 'method_empty2',
        'name'    => 'method_empty',
        'class'   => 'form-control',
        'style'   => 'margin-top: 1px !important;',
        'onclick' => 'this.form.submit()',
        'value'   => 2
];

switch ($method_empty) {
    case 0: // Всех
        $params_0['checked'] = 'checked';
        break;
    case 1: // Показать пустые
        $params_1['checked'] = 'checked';
        break;
    case 2: // Показать пустые с учетом заблокированных
        $params_2['checked'] = 'checked';
        break;

}

$search .= html_writer::end_div();
$search .= html_writer::start_div('m-b-1');
$search .= html_writer::empty_tag('input', $params_0);
$search .= html_writer::label(get_string('all', 'local_cohortpro'), 'method_empty0', true, [
        'class' => 'm-r-1',
        'style' => 'margin-top: 0 !important;'
]);
$search .= html_writer::end_div();
$search .= html_writer::start_div('m-b-1');
$search .= html_writer::empty_tag('input', $params_1);
$search .= html_writer::label(get_string('withoutmembers', 'local_cohortpro'), 'method_empty1', true, [
        'class' => 'm-r-1',
        'style' => 'margin-top: 0 !important;'
]);
$search .= html_writer::end_div();
$search .= html_writer::start_div('m-b-1');
$search .= html_writer::empty_tag('input', $params_2);
$search .= html_writer::label(get_string('withoutmemberswithsuspend', 'local_cohortpro'), 'method_empty2', true, [
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

    $checkbox = html_writer::tag('input', '', [
            'name'  => 'cohorts[]',
            'type'  => 'checkbox',
            'value' => $cohort->id
    ]);

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
                $members . ' (заблокировано: ' . local_cohortpro_get_count_members($cohort, 2) . ')',
                get_string('showmembers', 'local_cohortpro')
        );
    }

    $courses = local_cohortpro_get_count_courses($cohort);
    if ($courses > 0) {
        $courses = local_cohortpro_cell_link(
                new moodle_url('/local/cohortpro/courses.php', $urlparams),
                'i/preview',
                $courses,
                get_string('showcourses', 'local_cohortpro')
        );
    }

    $buttons = [];
    if (empty($cohort->component)) {
        $cohortmanager = has_capability('moodle/cohort:manage', $cohortcontext);
        $cohortcanassign = has_capability('moodle/cohort:assign', $cohortcontext);

        $showhideurl = new moodle_url('/cohort/edit.php', $urlparams + ['sesskey' => sesskey()]);
        if ($cohortmanager) {
            if ($cohort->visible) {
                $showhideurl->param('hide', 1);
                $visibleimg = $OUTPUT->pix_icon('t/hide', get_string('hide'));
                $buttons[] = html_writer::link($showhideurl, $visibleimg, ['title' => get_string('hide')]);
            } else {
                $showhideurl->param('show', 1);
                $visibleimg = $OUTPUT->pix_icon('t/show', get_string('show'));
                $buttons[] = html_writer::link($showhideurl, $visibleimg, ['title' => get_string('show')]);
            }

            $buttons[] = html_writer::link(
                    new moodle_url('/cohort/edit.php', $urlparams),
                    $OUTPUT->pix_icon('t/edit', get_string('edit')),
                    ['title' => get_string('edit')]
            );
        }

        if ($cohortcanassign) {
            $buttons[] = html_writer::link(
                    new moodle_url('/cohort/assign.php', $urlparams),
                    $OUTPUT->pix_icon('i/users', get_string('assign', 'core_cohort')),
                    ['title' => get_string('assign', 'core_cohort')]
            );
        }
    }

    $line = [
            $checkbox,
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
        '',
        get_string('category'),
        get_string('name', 'cohort'),
        get_string('component', 'cohort'),
        get_string('memberscount', 'cohort'),
        get_string('coursescount', 'local_cohortpro'),
        get_string('edit')
];
$table->colclasses = [
        'leftalign',
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

echo html_writer::start_tag('form', [
        'id'     => 'form-cohorts-input',
        'action' => new moodle_url('/local/cohortpro/index.php', ['delete' => 1]),
        'method' => 'POST'
]);
echo html_writer::table($table);
echo html_writer::end_tag('form');

echo $OUTPUT->paging_bar(
        $cohorts['totalcohorts'],
        $page,
        25,
        new moodle_url('/local/cohortpro/index.php', $params)
);

echo html_writer::start_tag('div');
echo html_writer::tag('button', get_string('delete'), [
        'type' => 'submit',
        'form' => 'form-cohorts-input',
]);
echo html_writer::end_tag('div');

echo $OUTPUT->footer();

?>