<?php

App::uses('ImageExtensionException', 'Error');
App::uses('UploadFileException', 'Error');

App::import('Vendor', 'Flash');

class AppController extends Controller{

    public $uses = array('OfferCategory', 'Company');

    public $components = array(
        'Session',
        'Auth' => array(
            'authenticate' => array(
                'Ldap',
                'Form'
            )
        ),
        'RequestHandler'
    );

    public $helpers = array(
        'Session',
        'Form',
        'Html',
        'Js' => array('Jquery'),
        'Tb' => array('className' => 'TwitterBootstrap.TwitterBootstrap')
    );

    // the next two properties alleviate the need to make checks manually; they
    // should be initiliazed early on, before any action is run (by calling
    // $this->api_initialize())
    // boolean; determines if a webservice api call was made
    protected $is_webservice;

    // string; the response type based on the request's Content-Type and/or
    // Accept headers for a webservice api call
    // possible values: xml, json, js
    protected $webservice_type;

    protected $pattern_simple = '[^a-zA-Zα-ωΑ-Ω0-9άέήίόύώϊϋΐΰΫΪ ]|\s\s+';

    function beforeFilter() {
        // allow everything
        // we fine-tune access in is_authorized() for each
        // controller and action
        $this->Auth->allow('*');

        // Prepare offer categories for default sidebar
        $this->OfferCategory->recursive = -1;
        $offer_categories = $this->OfferCategory->find('list');
        $categories_links = array();
        $this->set('offer_categories', $offer_categories);

        // When logged user has not accepted terms,
        // redirect to terms of use (only allow logout)
        $cur_user = $this->Auth->user();
        if (!is_null($cur_user)) {
            if (($cur_user['role'] === ROLE_STUDENT) && !($this->request['controller'] == 'users' && $this->request['action'] == 'terms')){
                if (!$cur_user['terms_accepted']) {
                    if (!($this->request['controller'] == 'users' && $this->request['action'] == 'logout')) {
                        $this->redirect(array('controller' => 'users', 'action' => 'terms'));
                    }
                }
            }
        }
    }

    public function beforeRender() {
        // inform in missing geolocation information
        if ($this->Auth->user('role') === ROLE_COMPANY) {
            $conditions = array('Company.id' => $this->Session->read('Auth.Company.id'));
            $fields = array('Company.longitude', 'Company.latitude', 'User.is_banned');
            $company = $this->Company->find('first', array(
                'conditions' => $conditions,
                'fields' => $fields,
                'recursive' => 0,
            ));
            if ($company['User']['is_banned'] == true) {
                $msg = "Ο λογαριασμός σας έχει κλειδωθεί από τον διαχειριστή του συστήματος. ";
                $msg .= "Επικοινωνήστε με τον διαχειριστή στο email: ";
                $msg .= '<a href="'.ADMIN_EMAIL.'">'.ADMIN_EMAIL.'</a>';
                $this->Session->setFlash($msg, 'default', array(), "warning");
            }
            if ($company['Company']['longitude'] === null or
                $company['Company']['latitude'] === null) {
                    // build the profile edit url for company
                    $edit_link = array(
                        'controller' => 'companies',
                        'action' => 'edit',
                        $this->Session->read('Auth.Company.id')
                    );
                    $msg = "Δεν έχετε συμπληρώσει γεωχωρικές πληροφορίες για την επιχείρηση σας. ";
                    $msg .= "Προσφορές που αναρτάτε πιθανόν να μην είναι διαθέσιμες μέσω της εφαρμογής κινητού. ";
                    $msg .= "Πατήστε <a href=\"".Router::url($edit_link, true)."\">εδώ</a>";
                    $msg .= " για να καταχωρήσετε το στίγμα της επιχείρησης σας.";

                    // make a "smart" decision about missing geo-information urgency
                    // if user is banned/locked demote missing geo-info to "info" level
                    // else make it a warning
                    if ($company['User']['is_banned'] == true) {
                        $urgency = "info";
                    } else {
                        $urgency = "warning";
                    }
                    $this->Session->setFlash($msg, 'default', array(), $urgency);
            }
        }
    }

    public function is_authorized($user) {
        // main authorization function
        // override in each controller

        // admin can access every action
        if (isset($user['role']) && $user['role'] === ROLE_ADMIN) {
            return true;
        }

        // default deny
        return false;
    }

    public function is_webservice() {
        return $this->is_webservice;
    }

    // Convenience method that displays an instant message. It removes the need
    // to manually either call Session::setFlash() method or prepare a response
    // to be returned to an api call. It also enables javascript responses.
    //
    // May also be used to return an error message specifically for a webservice
    // api call. For example (in any controller):
    //  if ($this->is_webservice) return $this->notify(
    //      'Argument missing', null, 406,
    //      array('url' => $this->request->here()));
    //  Note: in the example above, `return' is used to cease execution of
    //  current function.
    //
    // @param $flash mixed
    //      ### array:
    //      a 0-based array of parameters to be passed into
    //      SessionComponent::setFlash() method directly -- must contain AT
    //      LEAST one parameter (which corresponds to the message itself);
    //
    //      ### string:
    //      a string that corresponds to a message returned to a webservice call
    //      NOTE: passing in a string effectively bypasses `setFlash' as the
    //      message will not be displayed to an HTML response.
    // @param $redirect 0-based array of parameters to be passed into
    //      AppController::redirect() method directly. If left empty or omitted,
    //      no redirection takes place; defaults to null.
    // @param $status affects the status code that appears in the *body* and
    //      *header* of an xml/json(p) response; defaults to 200. NOTE: simply
    //      setting this to an error code does NOT result in an exception to be
    //      thrown for HTML response type; this only affects the webservice
    //      behaviour
    // @param $extra additional messages to be returned in a webservice api
    //      call. Numeric keys are NOT supported. The following should NOT be
    //      used either: `status', `@status' `message', '_serialize'
    protected function notify($flash, $redirect = null, $status = 200,
                              $extra = array()) {

        if ($this->is_webservice) {

            if (! empty($flash)) {
                // get message from setFlash parameters
                if (is_array($flash)) {
                    $flash = reset($flash);
                }

                // the message is given a tag
                $msg_param = array('message' => $flash);

                // the message parameter is placed within `extra' in the
                // beggining of the response
                if (empty($extra)) {
                    $extra = $msg_param;
                } else {
                    $extra = array_merge($msg_param, $extra);
                }
            }

            $this->api_compile_response($status, $extra);

        } else {
            $callback = array(&$this->Session, 'setFlash');

            // call `setFlash' if an array was supplied; ignore Flash, otherwise
            if (is_array($flash)) {
                call_user_func_array($callback, $flash);
            }

            // redirection does not take place in the webservice api
            if (!empty($redirect)) {
                call_user_func_array(array(&$this, 'redirect'), $redirect);
            }
        }
    }

    // Performs the necessary initializations so that a webservice api call
    // response may be rendered.
    // @param $code the HTTP status code of the response; defaults to 200
    // @param $extra additional messages to be returned in a webservice api
    //      call. Numeric keys are NOT supported. The following should NOT be
    //      used either: `status', `@status', '_serialize'
    public function api_compile_response($code = 200, $extra = array()) {

        $response = array();

        // status code should appear as an attribute in an xml response
        if ($this->webservice_type == 'xml') {
            $status_key = '@status_code';
        } else {
            $status_key = 'status_code';
        }

        $response[$status_key] = $code;

        // get formal description for this status $code and set the header
        $code_desc = $this->response->httpCodes($code);
        $this->response->header('HTTP/1.1 '.$code, $code_desc[$code]);

        // append any extra information
        if (!empty($extra)) {
            $response = array_merge($response, $extra);
        }

        // make preparation for views
        if ($this->webservice_type == 'js') {
            // get callback and set layout
            $callback = $this->request->query['callback'];

            $this->set('callback', $callback);
            $this->set('data', $response);
            $this->layout = 'js/status';
        } else {
            // this is required so that CakePHP automatically presents the
            // response with Xml/JsonView
            $response['_serialize'] = array_keys($response);
            $this->set($response);
        }
    }

    // Initializes the properties `is_webservice' and `webservice_type'.
    // Later on, this function will perform all necessary actions so that
    // default types (to xml or to that of another header) are supported.
    // Should be invoked before any operation is performed.
    public function api_initialize() {

        $type = $this->RequestHandler->prefers(array('js', 'json', 'xml'));

        $this->is_webservice = $type != null;
        $this->webservice_type = $type;

        // ensure callback was specified (for jsonp)
        if ($type == 'js') {
            if (!array_key_exists('callback', $this->request->query) ||
                empty($this->request->query['callback'])) {

                //TODO: how to react if no callback param was specified?
                $this->request->query['callback'] = 'jsonp_callback';
#                $this->is_webservice = true;
#                $this->webservice_type = 'xml';
#                throw new BadRequestException(
#                    'Δεν έχει προσδιοριστεί η απαιτούμενη παράμετρος callback');
            }
        }
    }
}
