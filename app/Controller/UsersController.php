<?php

class UsersController extends AppController {

    public $uses = array('User', 'Image', 'Day', 'WorkHour', 'Municipality');
    function beforeFilter() {
        parent::beforeFilter();

        $this->Auth->allow('register');
        $this->Auth->allow('days' );

        //in case user try to get  register when is logged in
        if( $this->Auth->user() && $this->request['action'] == 'register') {

                throw new ForbiddenException('Δεν επιτρέπεται η πρόσβαση');
        }

    }

    function login() {

        if( $this->request->is( 'post' ) ) {

            if( $this->isCompanyEnabled( $this->request->data ) ) {

                if( $this->Auth->login() ) {

                    return $this->redirect( $this->Auth->redirect() );
                } else {

                    $this->Session->setFlash(__("Δώστε έγκυρο όνομα και κωδικό χρήστη"), 'default', array(), 'auth' );
                }
            } else {

                $this->Session->setFlash(__("Ο λογαριασμός σας δεν έχει ενεργοποιηθεί"), 'default', array(), 'auth' );
            }
        }
    }

    //This function returns company state result( is_enabled )
    //plus returns true if user is not company owner or is not exist
    private function isCompanyEnabled( $data ) {

        $username = $data['User']['username'];
        $currentUser = $this->User->find( 'first',
            array( 'conditions' => array( 'username' => $username ) )
        );


        //1.checks if current user not found
        //2.or checks if user is not company owner
        //and returns true to continue in login method
        if( empty( $currentUser ) || $currentUser['User']['role'] != 'company'  ) {

            return true;
        }

        return $currentUser['Company']['is_enabled'];

    }

    function logout() {

        $this->redirect( $this->Auth->logout() );

    }

    function register() {

        $this->set('municipalities',
                   $this->Municipality->find('list',
                                             array('order' => 'Municipality.name ASC')
                                            ));


        if( !empty( $this->request->data ) ) {

            $dataSource = $this->User->getDataSource();
            //is_enabled and is_banned is by default false
            //set registered User's role
            $this->request->data['User']['role'] =  'company';
            //Use this to avoid valdation errors
            unset($this->User->Company->validate['user_id']);

            $dataSource->begin();

            $workHour = $this->request->data['WorkHour'];
            unset( $this->request->data['WorkHour']);

            if( !$this->User->save( $this->request->data['User'] ) ) {

                $this->Session->setFlash(__('Η εγγραφή δεν ολοκληρώθηκε'));
                $dataSource->rollback();
                return;

            }

            $this->User->Company->set('user_id', $this->User->id);
            if( !$this->User->Company->save( $this->request->data['Company'])){

                $this->Session->setFlash(__('Η εγγραφή δεν ολοκληρώθηκε'));
                $dataSource->rollback();
                return;
            }

            $photos = $this->processImages($this->request->data['Company']['Image'],
                                           3, true, null,
                                           array('company_id' => $this->User->Company->id));
            if (!empty($photos) && !$this->Image->saveMany($photos)) {
                $this->Session->setFlash('Η εγγραφή δεν ολοκληρώθηκε');
                $dataSource->rollback();
                return;
            }

            $workHour = $this->setCompanyId( $this->User->Company->id, $workHour );
            if( !$this->WorkHour->saveMany( $workHour )){

                $this->Session->setFlash(__('Η εγγραφή δεν ολοκληρώθηκε'));
                $dataSource->rollback();
                return;
            }

            $dataSource->commit();
            $this->Session->setFlash(__('Η εγγραφή ολοκληρώθηκε') );
            $this->redirect(array('controller'=>'Offers', 'action' => 'index'));

        }


    }

    function days(){

        $this->set( "days", $this->Day->find('list') );
        $this->set("_serialize", "days");
    }

    //sets Company id from saved company
    private function setCompanyId( $c_id, $workHour ) {

        if( empty( $c_id ) ){
            return null;
        }
        //creates the work hour format, compatible with mysql
        foreach( $workHour as &$wh ){

            $wh['company_id'] = $c_id;
        }

        return $workHour;
    }
}
