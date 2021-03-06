<?php

class UsersController extends AppController {

    public $uses = array('User', 'Image', 'Day', 'Distance',
                         'WorkHour', 'Municipality', 'Company', 'Student');

    public $components = array('RequestHandler', 'Token', 'Email');

    function beforeFilter() {
        parent::beforeFilter();
        $this->api_initialize();
    }

    function login() {
        // Set page title
        $page_title = __('Σύνδεση χρήστη');
        $this->set('title_for_layout', $page_title);

        if ($this->Auth->user())
            return $this->notify('Έχετε ήδη συνδεθεί', array('/'));

        if ($this->request->is('post')) {
            $userlogin = $this->Auth->login();
            if ($userlogin) {
                if ($this->Auth->user('role') == ROLE_COMPANY) {
                    // check if company is enabled
                    $options['conditions'] = array(
                        'User.id' => $this->Auth->user('id')
                    );
                    $options['fields'] =
                        array('Company.is_enabled', 'User.email_verified');
                    $options['recusive'] = 0;

                    $user = $this->User->find('first', $options);
                    $enabled = $user['Company']['is_enabled'];
                    $verified = $user['User']['email_verified'];

                    if (! $enabled) {
                        $this->Auth->logout();
                        $this->notify(
                            array(
                                __("Ο λογαριασμός σας δεν έχει ενεργοποιηθεί"),
                                'default',
                                array(),
                                "error"),
                            null, 403);
                        return;
                    }

                    if (! $verified) {
                        $this->Auth->logout();
                        $this->notify(
                            array(
                                __("Το email σας δεν έχει επιβεβαιωθεί"),
                                'default',
                                array(),
                                "error"),
                            null, 403);
                        return;
                    }
                }

                // save last login field
                $this->User->id = $this->Auth->user('id');
                $this->User->saveField('last_login', date(DATE_ATOM), false);

                // Save student and company profile id in session
                // as they are widely used thoughout the application
                //  for students:
                //      Auth.Student.id
                //  for companies:
                //      Auth.Company.id
                //
                //  Retrieving this data from session (controller/views):
                //      $this->Session->read('Auth.Company.id');
                //      $this->Session->read('Auth.Student.id');
                //
                if ($this->Auth->user('role') === ROLE_COMPANY) {
                    $company_id = $this->Company->field('id',
                        array('user_id' => $this->Auth->user('id')));
                    $this->Session->write('Auth.Company.id', $company_id);

                } elseif ($this->Auth->user('role') === ROLE_STUDENT) {
                    $student_id = $this->Student->field('id',
                        array('user_id' => $this->Auth->user('id')));
                    $this->Session->write('Auth.Student.id', $student_id);
                }

                // redirect to profile on 1st login
                // admins always go to the default screen
                if ( $this->Auth->user('last_login') == null ) {
                    if ($this->Auth->user('role') === ROLE_COMPANY) {
                        $this->redirect(array(
                            'controller' => 'companies', 'action' => 'view'
                        ));
                    }
                    if ($this->Auth->user('role') === ROLE_STUDENT) {
                        $this->redirect(array(
                            'controller' => 'students', 'action' => 'view'
                        ));
                    }
                }

                // Set default radius for offers by distance
                $this->Session->write('Auth.User.radius', RADIUS_M);

                $this->notify(
                    'Η αυθεντικοποίηση ολοκληρώθηκε με επιτυχία',
                    array($this->Auth->redirect()));
            } else {
                $this->notify(
                    array(  __("Δώστε έγκυρο όνομα και κωδικό χρήστη"),
                            'default',
                            array(),
                            "error"),
                    array(array('controller' => 'users', 'action' => 'login')), 403);
            }
        } else {
            $this->set('hide_dropdown', true);
        }
    }

    function logout() {
        $uid = $this->Session->read('Auth.User.id');
        // Remove all distances for current user
        $this->Distance->remove($uid);

        $this->notify(
            'Έχετε αποσυνδεθεί',
            array( $this->Auth->logout() ));
    }

    function register() {
        // Set page title
        $page_title = __('Εγγραφή επιχείρησης');
        $this->set('title_for_layout', $page_title);

        if ($this->Auth->user()) $this->redirect('/');

        if (!empty( $this->request->data)) {
            //is_enabled and is_banned is by default false
            //set registered User's role
            $this->request->data['User']['role'] = ROLE_COMPANY;
            $email = $this->request->data['User']['email'];
            $token = $this->Token->generate($email);
            $this->request->data['User']['token'] = $token;

            // Disable validation of company empty fields before saving
            unset($this->User->Company->validate['fax']);
            unset($this->User->Company->validate['service_type']);
            unset($this->User->Company->validate['address']);
            unset($this->User->Company->validate['postalcode']);
            
            $save_result = $this->User->saveAssociated($this->request->data);
            if ($save_result)
                $this->send_email_confirmation($token, $email);
            else
                $this->Session->setFlash(__('Η εγγραφή δεν ολοκληρώθηκε'),
                                         'default', array(), 'error');
        }
    }

    private function send_email_confirmation($token = null, $email = null) {
        $uenc_email = urlencode($email);
        $subject = __("Επιβεβαίωση διεύθυνσης ηλεκτρονικής αλληλογραφίας");
        $url = APP_URL."/companies/email_confirm/{$token}/{$uenc_email}";
        $cake_email = new CakeEmail('default');
        $cake_email = $cake_email
            ->to($email)
            ->subject($subject)
            ->template('confirm_email', 'default')
            ->emailFormat('both')
            ->viewVars(array('url' => $url));
        $msg = __('Η εγγραφή ολοκληρώθηκε και στάλθηκε email με το σύνδεσμο επιβεβαίωσης email.');
        $flash_type = 'success';
        try {
            $cake_email->send();
        } catch (Exception $e) {
            $msg = __('Δεν ήταν δυνατή η αποστολή email.');
            $flash_type = 'error';
        }
        $this->Session->setFlash($msg, 'default', array(), $flash_type);
        $this->redirect(array('controller' => 'users', 'action' => 'login'));
    }

    // Update user coordinates in session
    // Coordinates are passed as named arguments
    // e.g. http://coupons.teiath.gr/users/coords/lat:38.003/lng:23.668/
    // Upon successful validation, the session variable Auth.User.geolocation
    // is updated with the new values.
    public function coords() {
        if (! $this->Auth->user())
            throw new ForbiddenException('Δεν επιτρέπεται η πρόσβαση');

        $named = $this->params['named'];
        $message = 'Παρουσιάστηκε σφάλμα κατά την αποθήκευση των συντεταγμένων';
        $flash = array($message, 'default', array(), 'error');
        $status = 400;

        if (isset($named['lat']) && isset($named['lng'])) {
            $lat = $named['lat'];
            $lng = $named['lng'];
            // Set session geolocation if valid
            if (($lat >= -90) && ($lat <= 90) && ($lng >= -180) && ($lng <= 180)) {
                $geolocation = array('lat' => $lat, 'lng' => $lng);
                $this->Session->write('Auth.User.geolocation', $geolocation);
                $uid = $this->Session->read('Auth.User.id');
                // use radius from session, if not available use max (large) radius
                $radius = $this->Session->read('Auth.User.radius');
                $r = ($radius != NULL) ? $radius : RADIUS_L;
                // Update distances
                $query = "CALL updatedistances($uid,$lat,$lng,$r)";
                $this->User->query($query);

                $message = 'Οι συντεταγμένες αποθηκεύτηκαν ('.$lat.','.$lng.')';
                $flash = array($message, 'default', array(), "success");
                $status = 200;
            }
        }

        $this->notify(
            $flash,
            array(array('controller' => 'offers', 'action' => 'index')),
            $status);
    }

    public function radius ($radius = null) {
        if (! $this->Auth->user())
            throw new ForbiddenException('Δεν επιτρέπεται η πρόσβαση');

        $message = 'Παρουσιάστηκε σφάλμα κατά την αποθήκευση της ακτίνας αναζήτησης';
        $flash_type = 'error';
        $status = 400;

        if ($radius != null) {
            $valid_radius = array(RADIUS_S, RADIUS_M, RADIUS_L);

            // save radius in session
            if (in_array($radius, $valid_radius)) {
                $this->Session->write('Auth.User.radius', (int)$radius);
                $message = 'Η ακτίνα αναζήτησης αποθηκεύτηκε με επιτυχία.';
                $flash_type = 'success';
                $status = 200;
            } else {
                $this->Session->write('Auth.User.radius', RADIUS_L);
                $message = 'Λανθασμένη επιλογή ακτίνας αναζήτησης. '
                    . 'Η ακτίνα ορίστικε στην μέγιστη επιτρεπτή τιμή.';
                $flash_type = 'info';
                $status = 200;
            }
        }

        $this->notify(
            array($message, 'default', array(), $flash_type),
            array(array('controller' => 'offers', 'action' => 'index')),
            $status);
    }

    //Terms of use action
    public function terms() {
        // Set page title
        $page_title = __('Όροι χρήσης');
        $this->set('title_for_layout', $page_title);

        $data = $this->request->data;
        if (!empty($data)) {
            $accept = $data['User']['accept'];
            if ($accept == 1) {
                $this->User->id = $this->Auth->user('id');
                $save = $this->User->saveField('terms_accepted', true, false);

                // reload user info after the update
                $this->Session->write('Auth',
                    $this->User->read(null, $this->Auth->user('id')));
                $this->Session->setFlash(
                    __('Έχετε αποδεχτεί τους όρους χρήσης'),
                    'default', array(), 'success');
                $this->redirect(array(
                    'controller' => 'offers', 'action' => 'index'));
            } else {
                $this->Session->setFlash(
                    __('Δεν έχετε αποδεχτεί τους όρους χρήσης'),
                    'default', array(), 'error');
                $this->Auth->logout();
                $this->redirect(array(
                    'controller' => 'offers', 'action' => 'index'
                ));
            }
        } else {
            $this->set('terms_accepted', $this->Auth->user('terms_accepted'));
        }
    }

    // Frequently asked questions
    public function faq() {
        // Set page title
        $page_title = __('Συχνές ερωτήσεις');
        $this->set('title_for_layout', $page_title);
    }

    public function request_passwd () {
        // no point to request new password when logged in
        if ($this->Auth->User('id') != null) {
            throw new ForbiddenException();
        }

        if ($this->request->data) {
            $email = $this->request->data['User']['email'];
            // find user with given email
            $user = $this->User->find('first', array(
                'conditions' => array('User.email' => $email)));

            // return to self if address not found
            if (empty($user)) {
                $this->Session->setFlash(
                    __('Λάνθασμέμη διεύθυνση email.'),
                    'default', array(), 'error');
                $this->redirect(array(
                    'controller' => 'users', 'action' => 'request_passwd'
                ));
            }

            // inform LDAP users that they cannot change their password from here
            if ($user['User']['role'] === ROLE_STUDENT) {
                $this->Session->setFlash(
                    __('Η εφαρμογή δεν επιτρέπει την αλλαγή συνθηματικού σε
χρήστες οι οποίοι συνδέονται μέσω LDAP. Για αλλαγή του κωδικού πρόσβασης στις
υπηρεσίες του ΤΕΙ Αθήνας επισκεφθείτε την δ/ση: <a href="https://my.teiath.gr/">
https://my.teiath.gr</a>'),
                    'default', array(), 'warning');
                $this->redirect(array(
                    'controller' => 'users', 'action' => 'login'
                ));
            }

            // all users that request password change must have a verified
            // email address
            if ($user['User']['email_verified'] == false) {
                $this->Session->setFlash(
                    __('Πρέπει να επικυρώσετε την ηλεκτρονική σας δ/ση πριν αιτηθείτε νέο κωδικό.'),
                    'default', array(), 'warning');
                $this->redirect(array(
                    'controller' => 'users', 'action' => 'request_passwd'
                ));
            }

            // generate new token
            $token = $this->Token->generate($email);
            $this->User->id = $user['User']['id'];
            if (! $this->User->saveField('token', $token, false)) {
                $this->Session->setFlash(
                    __('Παρουσιάστηκε ένα σφάλμα. Επικοινωνήστε με τον διαχειριστή.'),
                    'default', array(), 'error');
                $this->redirect(array(
                    'controller' => 'users', 'action' => 'request_passwd'
                ));
            } else {
                $cake_email = new CakeEmail('default');
                $cake_email = $cake_email
                    ->to($email)

                    ->subject("Αίτημα αλλαγής κωδικού")
                    ->template('request_passwd', 'default')
                    ->emailFormat('both')
                    ->viewVars(array(
                        'url' => APP_URL . '/users/reset_passwd/'. $token));
                try {
                    $cake_email->send();
                } catch (Exception $e) {
                    // pass
                }
                $this->Session->setFlash(
                    __('Στάλθηκε email με το link αλλαγής κωδικού στο email σας.'),
                    'default', array(), 'success');
                $this->redirect(array(
                    'controller' => 'users', 'action' => 'login'
                ));
            }
        }
    }

    public function reset_passwd ($token = null) {
        // Set page title
        $page_title = __('Ανάκτηση κωδικού');
        $this->set('title_for_layout', $page_title);

        if ($this->Auth->User('id') != null) {
            throw new ForbiddenException();
        }

        if ($token == null) {
            throw new BadRequestException();
        }

        // pass token in view, we use it to build the form url
        $this->set('token', $token);

        if ($this->request->data) {
            // check if token exists
            $user_id = $this->Token->to_id($token);
            if ($user_id == null) {
                $this->Session->setFlash(
                    __('Λανθασμένο αναγνωριστικό (token), αιτηθείτε νέα αλλαγή password.'),
                    'default', array(). 'error');
                $this->redirect(array(
                    'controller' => 'users', 'action' => 'request_passwd'
                ));
            }

            // update password
            $this->User->id = $user_id;
            if (! $this->User->save($this->request->data,
                true, array('password', 'repeat_password'))){
                // check validation  errors
                $validation_errors = $this->User->invalidFields();
                if (isset($validation_errors['repeat_password'])) {
                    // redirect and show flash with errors
                    //
                    // two possible scenarios:
                    //   1. short password length
                    //   2. different passwords
                    //
                    $this->Session->setFlash(
                        __($validation_errors['repeat_password'][0]),
                        'default', array(), 'error');
                    $this->redirect(array(
                        'controller' => 'users', 'action' => 'reset_passwd', $token
                    ));
                }
                $this->Session->setFlash(
                    __('Παρουσιάστηκε ένα σφάλμα. Επικοινωνήστε με τον διαχειριστή.'),
                    'default', array(), 'error');
                $this->redirect(array(
                    'controller' => 'users', 'action' => 'login'
                ));
            }

            // remove token, we don't need it anymore
            // also ignore any errors
            // TODO: log this if we enable logging later
            $this->User->saveField('token', null, false);

            // inform users
            $this->Session->setFlash(
                __('Ο κωδικός άλλαξε με επιτυχία, παρακαλώ συνδεθείτε.'),
                'default', array(), 'success');
            $this->redirect(array(
                'controller' => 'users', 'action' => 'login'
            ));
        }
    }

    function help(){
        // Set page title
        $page_title = __('Αναφορά προβλήματος');
        $this->set('title_for_layout', $page_title);

        if (! $this->Auth->user('id') ) {
            throw new ForbiddenException();
        }
        // this variable is used to display properly
        // the selected element on header
        $this->set('selected_action', 'help');
        $this->set('title_for_layout', 'Αναφορά προβλήματος');
        $issues_categories = array('τεχνικό', 'μέριμνα');
        $this->set('issues_categories', $issues_categories);

        if ($this->request->data) {
            $userid = $this->Auth->user('id');
            $username = $this->Auth->user('username');

            $form_data = array();
            $form_data['subject'] = $this->request->data['subject'];
            $form_data['category'] = $issues_categories[$this->request->data['category']];
            $form_data['userid'] = $userid;
            $form_data['username'] = $username;
            $form_data['description'] = $this->request->data['description'];
            $result = $this->create_issue($form_data);
            if ($result) {
                $message = __('Η αναφορά καταχωρήθηκε με επιτυχία');
                $flash = array($message, 'default', array(), 'success');
                $this->notify($flash, array('/'));
            } else {
                $message = __('Η αναφορά δεν ήταν δυνατό να καταχωρηθεί');
                $flash = array($message, 'default', array(), 'fail');
                $this->notify($flash, array('/'));
            }
        }
	}

    private function create_issue($data) {
        if (isset($data)) {
            $req_data['subject'] = $data['subject'];
            $req_data['description'] = "{$data['userid']} {$data['username']}\n";
            $req_data['description'] .= "{$data['category']}\n";
            $req_data['description'] .= $data['description'];
            $req_xml = $this->create_xml_request($req_data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, ISSUE_URL);
            curl_setopt($ch, CURLOPT_POST, false);
            curl_setopt($ch, CURLOPT_USERPWD, ISSUE_TOKEN);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $req_xml);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_FAILONERROR,1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $result = curl_exec($ch);
            curl_close($ch);

            return $result;
        } else {
            return false;
        }
    }

    // Creates XML request to Redmine for reporting an issue
    private function create_xml_request($data){
        // Set project id
        $projectID = ISSUE_PROJECT_ID;
        $req = "<?xml version=\"1.0\"?>";
        $req .= "<issue>";
        $req .= "<subject>{$data['subject']}</subject>";
        $req .= "<description>{$data['description']}</description>";
        $req .= "<project_id>{$projectID}</project_id>";
        $req .= "</issue>";

        return $req;
    }


}
