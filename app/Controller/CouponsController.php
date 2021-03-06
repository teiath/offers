<?php

//TODO: maybe place this elsewhere?
App::uses('CakeEmail', 'Network/Email');

class CouponsController extends AppController {

    public $name = 'Coupons';
    public $uses = array('Coupon', 'Offer');
    public $helpers = array('Html', 'Time');
    public $components = array('RequestHandler');

    public function beforeFilter() {
        $this->api_initialize();

        if (! $this->is_authorized($this->Auth->user()))
            throw new ForbiddenException();

        parent::beforeFilter();
    }

    public function add ($id = null) {
        // $id : offer id
        if ($id === null)
            throw new BadRequestException();

        $redirect = array($this->referer());

        // setup conditions
        $conditions = $this->Offer->conditionsValid;
        $conditions['Offer.offer_type_id'] = TYPE_COUPONS;
        $conditions['Offer.id'] = $id;

        // get county info, used for email
        $this->Offer->Behaviors->attach('Containable');
        $this->Offer->contain(array('Company.Municipality.County'));
        $this->Offer->recursive = 0;
        $offer = $this->Offer->find('first', array(
            'conditions' => $conditions)
        );

        if (! $offer)
            throw new NotFoundException('Η προσφορά δεν βρέθηκε.');

        // don't read from session all the time
        $student_id = $this->Session->read('Auth.Student.id');

        // check if user is allowed to get the coupon due to maximum
        // coupon number acquired
        if ($this->Coupon->max_coupons_reached($id, $student_id)) {

            $flash = array('Έχετε δεσμεύσει τον μέγιστο αριθμό κουπονιών για '
                           .'αυτήν την προσφορά.',
                           'default',
                           array(),
                           "error");

            return $this->notify($flash, $redirect, 400);
        }

        // create a unique id
        $coupon_uuid = $this->generate_uuid();
        $coupon['Coupon']['serial_number'] = $coupon_uuid;
        $coupon['Coupon']['is_used'] = 0;
        $coupon['Coupon']['reinserted'] = 0;
        $coupon['Coupon']['student_id'] = $student_id;
        $coupon['Coupon']['offer_id'] = $id;

        if ($this->Coupon->save($coupon)) {

            $coupon_id = $this->Coupon->id;

            // send email
            $this->mail_success($offer, $coupon_id, $coupon_uuid);

            // success getting coupon
            // differentiate responses based on Accept header parameter
            $flash = array('Το κουπόνι δεσμεύτηκε επιτυχώς',
                           'default', array(), 'success');

            $status = 200;
            $extra = array('id' => $coupon_id,
                           'serial_number' => $coupon_uuid);

            $coupon_count = $offer['Offer']['coupon_count'] + 1;
            $coupon_total = $offer['Offer']['total_quantity'];
            if ($coupon_count >= $coupon_total) {
                $this->Offer->terminate($id);
                $this->Offer->email_coupon_list($id);
            }
        }
        else {
            // error getting coupon
            // differentiate responses based on Accept header parameter
            $flash = array('Παρουσιάστηκε κάποιο σφάλμα',
                           'default',
                           array(),
                           "error");
            $status = 400;

            $extra = array();
        }

        $this->notify($flash, $redirect, $status, $extra);
    }

    public function reinsert ($id = null) {

        if ($id === null)
            throw new BadRequestException();

        $redirect = array($this->referer());

        // don't read from session all the time
        $student_id = $this->Session->read('Auth.Student.id');
        $cond = array('Coupon.id' => $id);
        $coupon = $this->Coupon->find('first', array('conditions' => $cond));

        if (!$coupon) {
            throw new NotFoundException('Το κουπόνι δε βρέθηκε.');
        }

        if ($coupon['Coupon']['student_id'] !==
            $this->Session->read('Auth.Student.id'))
            throw new ForbiddenException();

        if ($coupon['Coupon']['reinserted'])
            throw new ForbiddenException();

        if ($coupon['Offer']['is_spam'])
            throw new ForbiddenException('Η προσφορά για την οποία έχει'
                .' δεσμευθεί το κουπόνι σας έχει χαρακτηριστεί σαν SPAM.');

        $coupon_uuid = $coupon['Coupon']['serial_number'];

        $update_fields = array('Coupon.reinserted' => true);
        $update_conditions = array('Coupon.id' => $id);

        // using nested transaction to flag coupon and update coupon_count
        $dataSource = $this->Coupon->getDataSource();
        $dataSource->begin();
        $update_ok = $this->Coupon->updateAll($update_fields, $update_conditions);

        if ($update_ok) {
            // coupon count minus one
            $coupon['Offer']['coupon_count']--;

            // update coupon count in offer
            $update_fields = array(
                'Offer.coupon_count' => $coupon['Offer']['coupon_count']);
            $update_conditions = array('Offer.id' => $coupon['Offer']['id']);

            $update_ok = $this->Offer->updateAll($update_fields, $update_conditions);

            if ($update_ok) {
                $dataSource->commit();

                // success reinserting coupon
                $msg = _("Το κουπόνι {$coupon_uuid} αποδεσμεύτηκε επιτυχώς");
                $flash = array($msg, 'default', array(), 'success');
                $status = 200;
                $extra = array('id' => $id,
                               'serial_number' => $coupon_uuid);
            } else {
                $dataSource->rollback();
            }
        }

        if (!$update_ok) {
            // reinserting coupon failed
            $msg = _("Δεν ήταν δυνατή η αποδέσμευση του κουπονιού {$coupon_uuid}");
            $flash = array($msg, 'default', array(), 'fail');
            $status = 500;
            $extra = array('id' => $id,
                           'serial_number' => $coupon_uuid);
        }

        $this->notify($flash, $redirect, $status, $extra);
    }

    public function view($id = null) {
        if ($id === null)
            throw new BadRequestException();

        // fetch coupon and all associated data
        //
        // sample $coupon array:
        //      'Coupon'
        //      'Offer'
        //          `-'Company'
        //      'Student'
        $cond = array('Coupon.id' => $id, 'Coupon.reinserted' => false);

        $this->Coupon->Behaviors->attach('Containable');
        $this->Coupon->contain(array('Offer.Company', 'Student'));
        $coupon = $this->Coupon->find('first', array('conditions' => $cond));

        if (! $coupon)
            throw new NotFoundException();

        if ($coupon['Coupon']['student_id'] !==
            $this->Session->read('Auth.Student.id'))
            throw new ForbiddenException();

        if ($coupon['Offer']['is_spam'])
            throw new ForbiddenException('Η προσφορά για την οποία έχει'
                .' δεσμευθεί το κουπόνι σας έχει χαρακτηριστεί σαν SPAM.');

        // Set page title
        $page_title = __('Κουπόνι για την προσφορά ');
        $page_title .="'{$coupon['Offer']['title']}'";
        $this->set('title_for_layout', $page_title);

        if ($this->is_webservice) {
            switch ($this->webservice_type) {
                case 'js':
                case 'json':
                    $coupon = $this->api_prepare_view($coupon, false);
                    break;

                case 'xml':
                    $coupon = $this->api_prepare_view($coupon);
                    break;
            }

            $this->api_compile_response(200, $coupon);
        } else {
            $this->set('coupon', $coupon);
        }
    }

    public function pdf($id = null) {
        if ($id === null)
            throw new BadRequestException();

        $cond = array('Coupon.id' => $id);

        $this->Coupon->Behaviors->attach('Containable');
        $this->Coupon->contain(array('Offer.Company', 'Student'));
        $coupon = $this->Coupon->find('first', array('conditions' => $cond));

        if (! $coupon)
            throw new NotFoundException();

        if ($coupon['Coupon']['student_id'] !==
            $this->Session->read('Auth.Student.id'))
            throw new ForbiddenException();

        if ($coupon['Offer']['is_spam']) {
            $msg = __("Η προσφορά για την οποία έχει δεσμευθεί το κουπόνι σας "
                ."έχει χαρακτηριστεί σαν SPAM.");
            throw new ForbiddenException($msg);
        }

        function loader($class) {
          $filename = mb_strtolower($class) . ".cls.php";
          require_once("../Vendor/dompdf/include/$filename");
        }
        spl_autoload_register('loader');

        App::import('Vendor', 'DomPdf', array('file' => 'dompdf' . DS . 'dompdf_config.inc.php'));

        $html = "<html><head><meta http-equiv='Content-Type' ";
        $html .= "content='text/html; charset=UTF-8' ></head><body>";
        $html .= "<style>body{font-family:'DejaVu',sans-serif;'}";
        $html .= ".unstyled{list-style:none;} .label-info{color:#fff;";
        $html .= "background-color:#3a87ad;}</style>";
        $html .= "<h4>Κουπόνι</h4><ul class='unstyled'>";
        $html .= "<li>Τίτλος προσφοράς: {$coupon['Offer']['title']}</li>";
        $html .= "<li>Κωδικός κουπονιού: <span class='label-info'>";
        $html .= "{$coupon['Coupon']['serial_number']}</span></li>";
        $html .= "<li>Ημ/νία δέσμευσης: {$coupon['Coupon']['created']}";
        $html .= "</li><li>Στοιχεία σπουδαστή: ";
        $html .= "{$coupon['Student']['firstname']} {$coupon['Student']['lastname']}";
        $html .= "</li></ul></div><div><h4>Στοιχεία επιχείρησης</h4>";
        $html .= "<ul class='unstyled'>";
        $html .= "<li>Όνομα: {$coupon['Offer']['Company']['name']}</li>";
        $html .= "<li>Διεύθυνση: {$coupon['Offer']['Company']['address']}";
        $html .= ", {$coupon['Offer']['Company']['postalcode']}</li>";
        $html .= "<li>Στοιχεία επικοινωνίας<ul class='unstyled'>";
        $html .= "<li>Τηλ: {$coupon['Offer']['Company']['phone']}</li>";
        $html .= "<li>Fax: {$coupon['Offer']['Company']['fax']}</li>";
        $html .= "</ul></li></ul><br />";
        if (isset($coupon['Offer']['Company']['latitude'])
            && isset($coupon['Offer']['Company']['longitude'])) {

            $lat = $coupon['Offer']['Company']['latitude'];
            $lng = $coupon['Offer']['Company']['longitude'];
            $api_key = "6e88be5b35b842dca178fb0beb724a32";
            $images_path = "{$this->webroot}img/";
            $map_width = 600;
            $map_height = 400;
            $html .= "<img src='http://staticmap.openstreetmap.de/staticmap.php?";
            $html .= "center={$lat},{$lng}&zoom=15&size={$map_width}x{$map_height}&";
            $html .= "markers={$lat},{$lng},ol-marker-gold' /><br/>";
        }
        $html .= "</body></html>";

        $filename = "coupon-{$coupon['Coupon']['serial_number']}.pdf";
        $dompdf = new DOMPDF();
        $dompdf->load_html($html);
        $dompdf->set_paper('a4');
        $dompdf->render();
        $dompdf->stream($filename);
    }

    // @param $id coupon id
    public function redeem($id) {
        if (empty($id)) throw new NotFoundException();
        $this->redeem_one($id, true);
    }
    public function re_enable($id) {
        if (empty($id)) throw new NotFoundException();
        $this->redeem_one($id, false);
    }

    // @param $id Coupon id to which the is_used attribute will be set
    // @param $is_used boolean; The value to write
    private function redeem_one($id, $is_used = true) {
        $this->Coupon->id = $id;

        $msg = $is_used ? 'Το κουπόνι σημάνθηκε ως εξαργυρωμένο' :
                          'Το κουπόνι σημάνθηκε ως ενεργό';

        if ($this->Coupon->saveField('is_used', $is_used)) {
            $this->Session->setFlash($msg, 'default', array(), 'success');
        } else {
            $this->Session->setFlash('Προέκυψε κάποιο σφάλμα',
                                     'default', array(), 'error');
        }
        $this->redirect($this->request->referer());
    }

    private function api_prepare_view($data, $is_xml = true) {
        $is_index = !array_key_exists('Coupon', $data);

        if (! $is_index) {
            $data = array(0 => $data);
        }

        // fields we don't want in results
        $unset_r = array(
            'offer' => array('created', 'modified'),
            'coupon' => array('modified', 'is_used'),
            'student' => array(
                'receive_email',
                'token',
                'created',
                'modified',
                'image_id'),
            'company' => array(
                'is_enabled',
                'user_id',
                // municipalities are not returned at all
                // enable them in find query and then remove the following line
                'municipality_id',
                'image_count',
                'work_hour_count',
                'created',
                'modified'
            )
        );

        $api_data = array();
        foreach ($data as $d) {
            $coupon_data = array();

            // format return data
            $coupon_data['offer'] = $d['Offer'];
            if (isset($coupon_data['offer']['Company'])) {
                unset($coupon_data['offer']['Company']);
            }

            $coupon_data['coupon'] = $d['Coupon'];

            if (isset($d['Student'])) {
                $coupon_data['student'] = $d['Student'];
            }

            if (isset($d['Offer']['Company'])) {
                $coupon_data['company'] = $d['Offer']['Company'];
            }

            foreach ($coupon_data as $key => $val) {
                foreach ($val as $skey => $sval) {
                    if (in_array($skey, $unset_r[$key])) {
                        unset($coupon_data[$key][$skey]);
                    }
                }
            }

            // replace true/false in json to be consistent with XML
            $coupon_data['coupon']['reinserted'] = $coupon_data['coupon']['reinserted'] ? 1 : 0;

            if (isset($coupon_data['offer']['is_spam'])) {
                $coupon_data['offer']['is_spam'] = $coupon_data['offer']['is_spam'] ? '1' : '0';
            }

            if ($is_xml) {
                $this->xml_alter_view($coupon_data);
            }
            $api_data[] = $coupon_data;
        }

        if (! $is_index) {
            $api_data = $api_data[0];
        } else {
            $api_data = array('coupons' => $api_data);
        }
        return $api_data;
    }

    private function xml_alter_view(&$data, $date_format='Y-m-d\TH:i:s') {

        // all the date fields that are to be formatted
        $date_fields = array(
            'offer' => array(
                'started',
                'ended',
                'autostart',
                'autoend'),
            'coupon' => array(
                'created'));

        // it is assumed that all entities possess an `id' attribute and,
        // potentially, dates; if not, a different approach is due
        foreach ($data as $key => $val) {
            // $key -> 'offer'
            // $val -> array contents of 'offer' key
            if (empty($val)) continue;

            // make offer id appear as attribute
            if (isset($val['id'])) {
                $val['@id'] = $val['id'];
                unset($val['id']);
            }

            // if array data key, has dates
            if (array_key_exists($key, $date_fields)) {
                // iterate over possible date fields for key
                foreach ($date_fields[$key] as $field) {
                    // get val's date from field $field
                    if (array_key_exists($field, $val)) {
                        // format date
                        $val[$field] = date($date_format, strtotime($val[$field]));
                    }
                }
            }

            // insert updated offer back to the results
            $data[$key] = $val;
        }
    }

    public function index() {
        $student_id = $this->Session->read('Auth.Student.id');
        if (! $student_id) throw new ForbiddenException();

        $cond = array('Coupon.student_id' => $student_id);
        $order = array('Coupon.created DESC');
        // fetch specific fields
        $fields = array(
            'Coupon.id',
            'Coupon.serial_number',
            'Coupon.created',
            'Coupon.reinserted',
            'Offer.id',
            'Offer.title',
            'Offer.description',
            'Offer.coupon_terms',
            'Offer.offer_category_id',
            'Offer.offer_type_id',
            'Offer.vote_count',
            'Offer.vote_plus',
            'Offer.vote_minus',
            'Offer.company_id',
            'Offer.is_spam',
            'Offer.offer_state_id'
        );
        $this->Coupon->recursive = 0;

        $coupons = $this->Coupon->find('all', array(
            'conditions' => $cond,
            'fields' => $fields,
            'order' => $order)
        );

        // as we cannot use virtual fields in conjunction with $fields
        // functionality, do the math by hand.
        $c = 0;
        foreach($coupons as $coupon) {
            $coupons[$c]['Offer']['vote_sum'] =
                $coupon['Offer']['vote_plus'] - $coupon['Offer']['vote_minus'];
            $c++;
        }

        if ($this->is_webservice) {
            switch ($this->webservice_type) {
                case 'js':
                case 'json':
                    $coupons = $this->api_prepare_view($coupons, false);
                    break;

                case 'xml':
                    $coupons = $this->api_prepare_view($coupons);
                    break;
            }

            $this->api_compile_response(200, $coupons);
        } else {
            throw new NotFoundException();
        }
    }

    public function delete($id = null) {
        // get offer id
        $offer_id = $this->Coupon->field('offer_id', array(
            'id' => $id));

        // check if offer is inactive
        $offer_state = $this->Offer->field('offer_state_id', array(
            'id' => $offer_id));

        if ($offer_state != STATE_INACTIVE) {
            throw new ForbiddenException();
        }

        $this->Coupon->id = $id;
        $result = $this->Coupon->saveField('student_id',
            null, $validate = false);

        if ($result == false) {
            $flash = array('Παρουσιάστηκε ένα σφάλμα κατα την διαγραφή του κουπονιού.',
                'default',
                array(),
                "error");
            $status = 500;
        } else {
            $flash = array('Το κουπόνι διεγράφη με επιτυχία.',
                'default',
                array(),
                "success");
            $status = 200;
        }
        $redirect = array($this->referer());
        $this->notify($flash, $redirect, $status);
    }


    private function generate_uuid() {
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

        return $uuid;
    }

    public function is_authorized($user) {
        $student_actions = array('add', 'reinsert', 'view', 'index', 'pdf');

        if ($user['is_banned'] == 0) {
            if (in_array($this->action, $student_actions)) {
                // only students can get coupons
                if ($user['role'] !== ROLE_STUDENT) {
                    return false;
                }
                return true;
            }
            if ($this->action === 'delete') {
                if ($user['role'] !== ROLE_STUDENT) {
                    return false;
                }

                $student_id = $this->Session->read('Auth.Student.id');
                if ($this->Coupon->is_owned_by($this->request->params['pass'],
                                               $student_id)) {
                    return true;
                }
                return false;
            }
            if ($this->action === 'redeem' || $this->action === 're_enable') {
                if (isset($user['role']) && $user['role'] === ROLE_COMPANY) {
                    $company_id = $this->Session->read('Auth.Company.id');
                    $coupon_id = $this->request->params['pass'][0];
                    if ($this->Coupon->is_offered_by($coupon_id, $company_id)) {
                        return true;
                    }
                }
                // prohibit admins and users with no permissions
                return false;
            }
        }

        // admin can see banned users too
        return parent::is_authorized($user);
    }

    private function mail_success($offer, $coupon_id, $coupon_uuid) {
        $student_email = $this->Session->read('Auth.User.email');

        $offer_title = $offer['Offer']['title'];

        $municipality = Set::check($offer, 'Company.Municipality.name') ?
            $offer['Company']['Municipality']['name'] : null;

        // could it be that a company may specify county but not municipality?
        $county = Set::check($offer, 'Company.Municipality.County.name') ?
            $offer['Company']['Municipality']['County']['name'] : null;

        $email = new CakeEmail('default');
        $email = $email
            ->to($student_email)

            ->subject("Κουπόνι προσφοράς «{$offer_title}»")
            ->template('coupon_reservation', 'default')
            ->emailFormat('both')
            ->viewVars(array(
                'offer_id' => $offer['Offer']['id'],
                'offer_title' => $offer_title,
                'coupon_id' => $coupon_id,
                'coupon_uuid' => $coupon_uuid,
                'company' => $offer['Company'],
                'municipality' => $municipality,
                'county' => $county));

        try {
            $email->send();
        } catch (Exception $e) {
            //do what with it?
        }
    }
}
