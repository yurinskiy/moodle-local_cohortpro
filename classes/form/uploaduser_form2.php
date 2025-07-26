<?php

namespace local_cohortpro\form;

global $CFG;

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/user/editlib.php');

class uploaduser_form2 extends \moodleform {
    function definition () {
        $mform   = $this->_form;
        $columns = $this->_customdata['columns'];
        $data    = $this->_customdata['data'];

        $mform->addElement('header', 'settingsheader', get_string('settings'));

        $mform->addElement('selectyesno', 'skipusersuspend', 'Игнорировать заблокированные аккаунты');
        $mform->setDefault('skipusersuspend', 1);

        $mform->addElement('selectyesno', 'onlyuserldap', 'Учитывать только корпоративные аккаунты (LDAP)');
        $mform->setDefault('onlyuserldap', 0);

        $mform->addElement('selectyesno', 'skipuserinactive', 'Игнорировать неактивные аккаунты больше N дней');
        $mform->setDefault('skipuserinactive', 0);

        $mform->addElement('text', 'countdayforuserinactive', 'N');
        $mform->setType('countdayforuserinactive', PARAM_INT);
        $mform->setDefault('countdayforuserinactive', 7);
        $mform->hideIf('countdayforuserinactive', 'skipuserinactive', 'eq', 0);

        $mform->addElement('selectyesno', 'skipuserstudent', 'Игнорировать аккаунты студентов и аспирантов');
        $mform->setDefault('skipuserstudent', 0);

        // hidden fields
        $mform->addElement('hidden', 'iid');
        $mform->setType('iid', PARAM_INT);

        $mform->addElement('hidden', 'previewrows');
        $mform->setType('previewrows', PARAM_INT);

        $this->add_action_buttons(true, 'Проверить и выгрузить файл');

        $this->set_data($data);
    }

    /**
     * Used to reformat the data from the editor component
     *
     * @return \stdClass
     */
    function get_data() {
        $data = parent::get_data();

        if ($data !== null and isset($data->description)) {
            $data->descriptionformat = $data->description['format'];
            $data->description = $data->description['text'];
        }

        return $data;
    }

    /**
     * Returns list of elements and their default values, to be used in CLI
     *
     * @return array
     */
    public function get_form_for_cli() {
        $elements = array_filter($this->_form->_elements, function($element) {
            return !in_array($element->getName(), ['buttonar', 'uubulk']);
        });
        return [$elements, $this->_form->_defaultValues];
    }

    /**
     * Returns validation errors (used in CLI)
     *
     * @return array
     */
    public function get_validation_errors(): array {
        return $this->_form->_errors;
    }
}