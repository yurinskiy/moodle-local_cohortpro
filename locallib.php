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

/**
 * Возвращает количество пользователей в глобальной группы
 *
 * @param $cohort
 * @return mixed
 */
function local_cohortpro_get_count_members($cohort)
{
    global $DB;
    return $DB->count_records('cohort_members', array('cohortid' => $cohort->id));
}

/**
 * Возвращает количество курсов, на которые подписана глобальная группа
 *
 * @param $cohort
 * @return mixed
 */
function local_cohortpro_get_count_courses($cohort)
{
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
 * Возвращает объект, содержащий ссылку
 * на базовую страницу плагина с заданными параметрами.
 *
 * @param array $params
 * @return moodle_url
 */
function local_cohortpro_get_baseurl($params)
{
    return new moodle_url('/local/cohortpro/index.php', $params);
}