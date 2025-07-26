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
global $CFG, $PAGE, $OUTPUT;
require('../../config.php');
require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/csvlib.class.php');
require_once('./locallib.php');

$iid         = optional_param('iid', '', PARAM_INT);
$eid         = optional_param('eid', '', PARAM_INT);
$previewrows = optional_param('previewrows', 10, PARAM_INT);

core_php_time_limit::raise(60 * 60); // 1 hour should be enough.
raise_memory_limit(MEMORY_HUGE);

require_login();

$context = context_system::instance();

$manager = has_capability('local/cohortpro:manager', $context);
if (!$manager) {
    require_capability('local/cohortpro:manager', $context);
}

$PAGE->set_pagelayout('admin');
$PAGE->set_context($context);
$PAGE->set_url('/local/cohortpro/uploaduser.php', ['contextid' => $context->id]);
$PAGE->set_title(get_string('menu_upload', 'local_cohortpro'));
$PAGE->set_heading(get_string('menu_upload', 'local_cohortpro'));

$returnurl = new moodle_url('/local/cohortpro/uploaduser.php');

if (empty($iid)) {
    $mform1 = new \local_cohortpro\form\uploaduser_form1();

    if ($formdata = $mform1->get_data()) {
        $iid = csv_import_reader::get_new_iid('uploaduser');
        $cir = new csv_import_reader($iid, 'uploaduser');

        $content = $mform1->get_file_content('userfile');

        $readcount = $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);
        $csvloaderror = $cir->get_error();
        unset($content);

        if (!is_null($csvloaderror)) {
            print_error('csvloaderror', '', $returnurl, $csvloaderror);
        }
        // Continue to form2.
    } else {
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('menu_upload', 'local_cohortpro'));

        $mform1->display();

        echo $OUTPUT->footer();
        die;
    }
} else {
    $cir = new csv_import_reader($iid, 'uploaduser');
}

// Test if columns ok.
$process = new \local_cohortpro\process($cir);
$filecolumns = $process->get_file_columns();

$mform2 = new \local_cohortpro\form\uploaduser_form2(null, ['columns' => $filecolumns, 'data' => ['iid' => $iid, 'previewrows' => $previewrows]]);

// If a file has been uploaded, then process it.
if ($formdata = $mform2->is_cancelled()) {
    $cir->cleanup(true);
    redirect($returnurl);
} else if ($formdata = $mform2->get_data()) {
    // Print the header.
    //echo $OUTPUT->header();
    //echo $OUTPUT->heading(get_string('uploadusersresult', 'tool_uploaduser'));

    $process->set_form_data($formdata);
    $process->process();

    $process->download_csv();
    echo '<meta http-equiv="refresh" content="0;url=https://your-moodle-site/newpage.php">';

    //echo $OUTPUT->box_start('boxwidthnarrow boxaligncenter generalbox', 'uploadresults');
    //echo html_writer::tag('p', join('<br />', $process->get_stats()));
    //echo $OUTPUT->box_end();
    //
    //$back = new single_button(new moodle_url('/local/cohortpro/uploaduser.php', ['eid' => $eid]), 'Скачать файл', 'get', false);
    //
    //echo html_writer::tag('div', $OUTPUT->render($back), array('class' => 'buttons'));
    //
    //echo $OUTPUT->footer();
    die;
}

// Print the header.
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('uploaduserspreview', 'tool_uploaduser'));

// Preview table data.
$table = new \local_cohortpro\preview($cir, $filecolumns, $previewrows);

echo html_writer::tag('div', html_writer::table($table), ['class' => 'flexible-wrap']);

// Print the form if valid values are available.
if ($table->get_no_error()) {
    $mform2->display();
}

echo $OUTPUT->footer();
die;

?>