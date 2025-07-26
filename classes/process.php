<?php

namespace local_cohortpro;

global $CFG;
require_once($CFG->dirroot.'/'.$CFG->admin.'/tool/uploaduser/locallib.php');

class process {
    /** @var \csv_import_reader  */
    protected $cir;
    /** @var \stdClass  */
    protected $formdata;
    /** @var \local_cohortpro\progress_tracker  */
    protected $upt;
    /** @var int  */
    protected $today;
    /** @var array */
    protected $standardfields = [];
    /** @var array */
    protected $profilefields = [];
    /** @var int */
    protected $allrows   = 0;
    /** @var int */
    protected $userserrors   = 0;
    /** @var int */
    protected $usersnotfound   = 0;
    /** @var int */
    protected $usersfound   = 0;
    /** @var int */
    protected $usersfoundmany   = 0;
    protected $exports = [];

    /**
     * process constructor.
     *
     * @param \csv_import_reader $cir
     * @throws \coding_exception
     */
    public function __construct(\csv_import_reader $cir) {
        $this->cir = $cir;

        // Keep timestamp consistent.
        $today = time();
        $today = make_timestamp(date('Y', $today), date('m', $today), date('d', $today), 0, 0, 0);
        $this->today = $today;

        $this->find_profile_fields();
        $this->find_standard_fields();
    }

    /**
     * Standard user fields.
     */
    protected function find_standard_fields(): void {
        $this->standardfields = array('id', 'username', 'email', 'emailstop',
            'city', 'country', 'lang', 'timezone', 'mailformat',
            'maildisplay', 'maildigest', 'htmleditor', 'autosubscribe',
            'institution', 'department', 'idnumber', 'phone1', 'phone2', 'address',
            'description', 'descriptionformat', 'password',
            'auth',        // Watch out when changing auth type or using external auth plugins!
            'oldusername', // Use when renaming users - this is the original username.
            'suspended',   // 1 means suspend user account, 0 means activate user account, nothing means keep as is.
            'theme',       // Define a theme for user when 'allowuserthemes' is enabled.
            'deleted',     // 1 means delete user
            'mnethostid',  // Can not be used for adding, updating or deleting of users - only for enrolments,
            // groups, cohorts and suspending.
            'interests',
        );
        // Include all name fields.
        $this->standardfields = array_merge($this->standardfields, \core_user\fields::get_name_fields());
    }

    /**
     * Profile fields
     */
    protected function find_profile_fields(): void {
        global $CFG;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $this->allprofilefields = profile_get_user_fields_with_data(0);
        $this->profilefields = [];
        if ($proffields = $this->allprofilefields) {
            foreach ($proffields as $key => $proffield) {
                $profilefieldname = 'profile_field_'.$proffield->get_shortname();
                $this->profilefields[] = $profilefieldname;
                // Re-index $proffields with key as shortname. This will be
                // used while checking if profile data is key and needs to be converted (eg. menu profile field).
                $proffields[$profilefieldname] = $proffield;
                unset($proffields[$key]);
            }
            $this->allprofilefields = $proffields;
        }
    }

    /**
     * Returns the list of columns in the file
     *
     * @return array
     */
    public function get_file_columns(): array {
        if ($this->filecolumns === null) {
            $returnurl = new \moodle_url('/admin/tool/uploaduser/index.php');
            $this->filecolumns = uu_validate_user_upload_columns($this->cir, $this->standardfields, $this->profilefields, $returnurl);
        }

        return $this->filecolumns;
    }

    /**
     * Set data from the form (or from CLI options)
     *
     * @param \stdClass $formdata
     */
    public function set_form_data(\stdClass $formdata): void {
        $this->formdata = $formdata;
    }

    /**
     * Process the CSV file
     */
    public function process() {
        // Init csv import helper.
        $this->cir->init();

        //$this->upt = new \local_cohortpro\progress_tracker(array_combine($this->get_file_columns(), $this->get_file_columns()));
        //$this->upt->start(); // Start table.

        $linenum = 1; // Column header is first line.
        while ($line = $this->cir->next()) {
            //$this->upt->flush();
            $linenum++;
            $this->allrows++;

            //$this->upt->track('line', $linenum);
            $this->process_line($line);
        }

        //$this->upt->close(); // Close table.
        $this->cir->close();
        //$this->cir->cleanup(true);
    }

    public function download_csv()
    {
        $allKeys = [];
        foreach ($this->exports as $row) {
            $allKeys = array_merge($allKeys, array_keys($row));
        }
        $allKeys = array_unique($allKeys);

        $exports[] = $allKeys;
        foreach ($this->exports as $row) {
            $csvRow = [];
            foreach ($allKeys as $key) {
                $csvRow[] = $row[$key] ?? '';
            }
            $exports[] = $csvRow;
        }

        \csv_export_writer::download_array('uploaduser_cohortpro', $exports);
    }

    /**
     * Process one line from CSV file
     *
     * @param array $line
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    public function process_line(array $line) {
        global $DB, $CFG, $SESSION;

        $export_line = array_combine($this->get_file_columns(), $line);

        if (!$user = $this->prepare_user_record($line)) {
            return;
        }

        $export_line['status'] = $user->status ?? null;

        $sql = 'select id, username from {user} where firstname = :firstname and lastname = :lastname';
        $params = ['firstname' => $user->firstname, 'lastname' => $user->lastname];

        if ($this->is_skipusersuspend()) {
            $sql .= ' and suspended = 0';
        }

        if ($this->is_onlyuserldap()) {
            $sql .= ' and auth = \'ldap\'';
        }

        if ($this->is_skipuserinactive()) {
            $sql .= ' and lastaccess > :delay';
            $params['delay'] = time() - ($this->get_countdayforuserinactive() * 24 * 60 * 60);
        }

        if ($this->is_skipuserstudent()) {
            $sql .= ' and username NOT REGEXP \'^[0-9]+$\'';
        }

        $existingusers = $DB->get_records_sql($sql, $params);

        switch (\count($existingusers)) {
            case 0:
                //$this->upt->track('status', 'Нет аккаунта', 'error');
                //$this->upt->track('username', get_string('error'));
                $this->usersnotfound++;

                $export_line['status'] .= '\n'.'Нет аккаунта';
                $export_line['username0'] = null;
                break;
            case 1:
                $existinguser = current($existingusers);
                //$this->upt->track('status', 'Пользователь найден', 'error');
                //$this->upt->track('id', $existinguser->id);
                //$this->upt->track('username', $existinguser->username);
                $this->usersfound++;

                $export_line['status'] .= '\n'.'Пользователь найден';
                $export_line['username0'] = $existinguser->username;
                break;
            default:
                $user->username = \implode(' | ', array_map(fn ($existinguser) => $existinguser->username, $existingusers));
                //$this->upt->track('status', 'Дубли', 'error');
                //$this->upt->track('username', $user->username, 'error');
                $this->usersfoundmany++;

                $export_line['status'] .= '\n'.'Дубли';
                $i = 0;
                foreach ($existingusers as $existinguser) {
                    $export_line['username'.$i] = $existinguser->username;
                    $i++;
                }

                break;
        }

        foreach ($this->get_file_columns() as $column) {
            if (!preg_match('/^cohort\d+$/', $column)) {
                continue;
            }

            if (empty($user->$column)) {
                continue;
            }

            $addcohort = $user->$column;
            if (!isset($this->cohorts[$addcohort])) {
                if (is_number($addcohort)) {
                    // Only non-numeric idnumbers!
                    $cohort = $DB->get_record('cohort', ['id' => $addcohort]);
                } else {
                    $cohort = $DB->get_record('cohort', ['idnumber' => $addcohort]);
                }

                if (empty($cohort)) {
                    $this->cohorts[$addcohort] = get_string('unknowncohort', 'core_cohort', s($addcohort));
                } else if (!empty($cohort->component)) {
                    // Cohorts synchronised with external sources must not be modified!
                    $this->cohorts[$addcohort] = get_string('external', 'core_cohort');
                } else {
                    $this->cohorts[$addcohort] = $cohort;
                }
            }

            if (is_object($this->cohorts[$addcohort])) {
                $cohort = $this->cohorts[$addcohort];
                if (!$DB->record_exists('cohort_members', ['cohortid' => $cohort->id, 'userid' => $user->id])) {
                    // cohort_add_member($cohort->id, $user->id);
                    // We might add special column later, for now let's abuse enrolments.
                    //$this->upt->track('status', get_string('useradded', 'core_cohort', s($cohort->name)), 'info');


                    $export_line['status'] .= '\n'.get_string('useradded', 'core_cohort', s($cohort->name));
                }
            } else {
                // Error message.
                //$this->upt->track('status', $this->cohorts[$addcohort], 'error');


                $export_line['status'] .= '\n'.$this->cohorts[$addcohort];
            }
        }

        $this->exports[] = $export_line;
    }

    /**
     * Prepare one line from CSV file as a user record
     *
     * @param array $line
     * @return \stdClass|null
     */
    protected function prepare_user_record(array $line): ?\stdClass {
        global $CFG, $USER;

        $user = new \stdClass();

        // Add fields to user object.
        foreach ($line as $keynum => $value) {
            if (!isset($this->get_file_columns()[$keynum])) {
                // This should not happen.
                continue;
            }
            $key = $this->get_file_columns()[$keynum];
            if (strpos($key, 'profile_field_') === 0) {
                // NOTE: bloody mega hack alert!!
                if (isset($USER->$key) and is_array($USER->$key)) {
                    // This must be some hacky field that is abusing arrays to store content and format.
                    $user->$key = array();
                    $user->{$key['text']}   = $value;
                    $user->{$key['format']} = FORMAT_MOODLE;
                } else {
                    $user->$key = trim($value);
                }
            } else {
                $user->$key = trim($value);
            }

            //if (in_array($key, $this->upt->columns)) {
            //    // Default value in progress tracking table, can be changed later.
            //    $this->upt->track($key, s($value), 'normal');
            //}
        }

        $error = false;
        if (!isset($user->firstname) or $user->firstname === '') {
            //$this->upt->track('status', get_string('missingfield', 'error', 'firstname'), 'error');
            //$this->upt->track('firstname', get_string('error'), 'error');
            $error = true;
        }
        if (!isset($user->lastname) or $user->lastname === '') {
            //$this->upt->track('status', get_string('missingfield', 'error', 'lastname'), 'error');
            //$this->upt->track('lastname', get_string('error'), 'error');
            $error = true;
        }
        if ($error) {
            $this->userserrors++;
            return null;
        }

        if (!empty($user->lastname)) {
            $user->lastname = trim($user->lastname);
        }

        if (!empty($user->firstname)) {
            $user->firstname = trim($user->firstname);
        }

        if (!isset($user->username)) {
            // Prevent warnings below.
            $user->username = '';
        }

        if ($user->username === 'guest') {
            //$this->upt->track('status', get_string('guestnoeditprofileother', 'error'), 'error');
            $this->userserrors++;
            return null;
        }

        return $user;
    }

    /**
     * Summary about the whole process (how many users created, skipped, updated, etc)
     *
     * @return array
     */
    public function get_stats() {
        $lines = [];

        $lines[] = 'Всего записей: '.$this->allrows;
        $lines[] = 'Пользователь определен однозначно: '.$this->usersfound;
        $lines[] = 'Пользователь определен неоднозначно: '.$this->usersfoundmany;
        $lines[] = 'Пользователь не определен: '.$this->usersnotfound;
        $lines[] = get_string('errors', 'tool_uploaduser').': '.$this->userserrors;

        return $lines;
    }

    protected function is_skipusersuspend()
    {
        return (bool) $this->formdata->skipusersuspend;
    }

    protected function is_onlyuserldap()
    {
        return (bool) $this->formdata->onlyuserldap;
    }

    protected function is_skipuserinactive()
    {
        return (bool) $this->formdata->skipuserinactive;
    }

    protected function get_countdayforuserinactive()
    {
        return (int) $this->formdata->countdayforuserinactive;
    }

    protected function is_skipuserstudent()
    {
        return (bool) $this->formdata->skipuserstudent;
    }
}