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

function local_cohortpro_get_cohort($id) {
    global $DB;
    return $DB->get_record('cohort', ['id' => $id], '*', MUST_EXIST);
}

/**
 * Возвращает количество участников глобальной группы
 *
 * @param $cohort
 * @param int $method
 * @return mixed
 */
function local_cohortpro_get_count_members($cohort, $method = 0) {
    global $DB;

    switch ($method) {
        case 1:
            $sql = 'SELECT COUNT(1)
                FROM {cohort_members} cm
                JOIN {user} u ON (u.id = cm.userid AND u.suspended = 0)
                WHERE cm.cohortid = :cohortid';

            $params = [
                    'cohortid' => $cohort->id
            ];

            return $DB->count_records_sql($sql, $params);
        case 2:
            $sql = 'SELECT COUNT(1)
                FROM {cohort_members} cm
                JOIN {user} u ON (u.id = cm.userid AND u.suspended = 1)
                WHERE cm.cohortid = :cohortid';

            $params = [
                    'cohortid' => $cohort->id
            ];

            return $DB->count_records_sql($sql, $params);
        default:
            return $DB->count_records('cohort_members', ['cohortid' => $cohort->id]);

    }
}

/**
 * Возвращает массив участников глобальной группы
 *
 * @param $cohort
 * @return mixed
 */
function local_cohortpro_get_members($cohort, $page = 0, $perpage = 25) {
    global $DB;

    $fields = "SELECT u.*";
    $countfields = 'SELECT COUNT(1)';
    $sql = " FROM {user} u
             JOIN {cohort_members} cm ON (cm.userid = u.id AND cm.cohortid = :cohortid)";
    $params = [
            'cohortid' => $cohort->id
    ];

    $order = ' ORDER BY u.lastname';

    return [
            'totalmembers' => $DB->count_records_sql($countfields . $sql, $params),
            'members'      => $DB->get_records_sql($fields . $sql . $order, $params, $page * $perpage, $perpage)
    ];
}

/**
 * Возвращает количество курсов глобальной группы
 *
 * @param $cohort
 * @return mixed
 */
function local_cohortpro_get_count_courses($cohort) {
    global $DB;

    $sql = 'SELECT COUNT(DISTINCT e.courseid)
            FROM {enrol} e
            WHERE e.enrol = ? AND e.customint1 = ?';

    return $DB->count_records_sql($sql, [
            'cohort',
            $cohort->id
    ]);
}

/**
 * Возвращает массив курсов глобальной групп
 *
 * @param $cohort
 * @param int $page
 * @param int $perpage
 * @return mixed
 */
function local_cohortpro_get_courses($cohort, $page = 0, $perpage = 25) {
    global $DB;

    $fields = "SELECT c.*";
    $countfields = 'SELECT COUNT(1)';
    $sql = " FROM {course} c
             JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'cohort' AND e.customint1 = :cohortid)";
    $params = [
            'cohortid' => $cohort->id
    ];

    $order = ' ORDER BY c.fullname';

    return [
            'totalcourses' => $DB->count_records_sql($countfields . $sql, $params),
            'courses'      => $DB->get_records_sql($fields . $sql . $order, $params, $page * $perpage, $perpage)
    ];
}

/**
 * @return array
 */
function local_cohortpro_get_all_empty_cohorts($page = 0, $perpage = 25, $search = '', $method = 0) {
    global $DB;

    $fields = "SELECT c.*, " . context_helper::get_preload_record_columns_sql('ctx');
    $countfields = "SELECT COUNT(*)";

    $sql = " FROM {cohort} c
             JOIN {context} ctx ON ctx.id = c.contextid";

    // Защита от перебора
    if ($method > 2) {
        $method = 0;
    }

    switch ($method) {
        case 0:
            $wheresql = '';
            break;
        case 1:
            $wheresql = ' WHERE (SELECT COUNT(1) FROM {cohort_members} cm WHERE cm.cohortid = c.id) = 0 ';
            break;
        case 2:
            $wheresql =
                    ' WHERE (SELECT COUNT(1) FROM {cohort_members} cm JOIN {user} u ON (u.id = cm.userid AND u.suspended = 0) WHERE cm.cohortid = c.id) = 0 ';
            break;
        default:
            $wheresql = '';
    }

    $params = array();

    if ($excludedcontexts = cohort_get_invisible_contexts()) {
        list($excludedsql, $excludedparams) = $DB->get_in_or_equal($excludedcontexts, SQL_PARAMS_NAMED, 'excl', false);
        $wheresql .= ($wheresql ? ' AND ' : ' WHERE ') . ' c.contextid ' . $excludedsql;
        $params = array_merge($params, $excludedparams);
    }

    $totalcohorts = $allcohorts = $DB->count_records_sql($countfields . $sql . $wheresql, $params);

    if (!empty($search)) {
        list($searchcondition, $searchparams) = cohort_get_search_query($search, 'c');
        $wheresql .= ($wheresql ? ' AND ' : ' WHERE ') . $searchcondition;
        $params = array_merge($params, $searchparams);
        $totalcohorts = $DB->count_records_sql($countfields . $sql . $wheresql, $params);
    }

    $order = " ORDER BY c.name ASC, c.idnumber ASC";
    $cohorts = $DB->get_records_sql($fields . $sql . $wheresql . $order, $params, $page * $perpage, $perpage);

    // Preload used contexts, they will be used to check view/manage/assign capabilities and display categories names.
    foreach (array_keys($cohorts) as $key) {
        context_helper::preload_from_record($cohorts[$key]);
    }

    return array(
            'totalcohorts' => $totalcohorts,
            'cohorts'      => $cohorts,
            'allcohorts'   => $allcohorts
    );
}

/**
 * Возвращает код ссылки с информацией и иконкой
 *
 * @param moodle_url $url
 * @param string $icon
 * @param string $text
 * @param string $title
 * @return mixed
 */
function local_cohortpro_cell_link($url, $icon, $text, $title) {
    global $OUTPUT;

    return html_writer::tag('span',
            html_writer::link(
                    $url,
                    $text . ' ' . html_writer::tag('span',
                            $OUTPUT->pix_icon($icon, $title),
                            ['class' => 'quickediticon visibleifjs']
                    ),
                    [
                            'title' => $title,
                            'class' => 'quickeditlink'
                    ]
            ),
            ['class' => 'inplaceeditable inplaceeditable-text']
    );
}

/**
 * Получение объекта глобальной группы по идентификатору
 *
 * @param int $id
 * @return mixed
 */
function local_cohortpro_get_by_id($id) {
    global $DB;

    $cohort = $DB->get_record('cohort', array('id'=>$id), '*', MUST_EXIST);

    return $cohort;
}