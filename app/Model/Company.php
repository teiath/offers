<?php

class Company extends AppModel {

    public $name = 'Company';
    public $belongsTo = array('User', 'Municipality');
    public $hasMany = array('Offer', 'WorkHour', 'Image');

    public $validate = array(

        'name' => array(
            'not_empty' => array(
                'rule' => 'notEmpty',
                'message' => 'Παρακαλώ εισάγετε την επωνυμία.',
                'required' => true
            ),
            'maxsize' => array(
                'rule' => array('maxLength', 100),
                'allowEmpty' => true,
                'message' => 'Η επωνυμία μπορεί να περιέχει μέχρι 100 χαρακτήρες.'
            ),
            'valid' => array(
                'rule' => '/^[\w\dαβγδεζηθικλμνξοπρστυφχψωΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩΆάΈέΎΉήύΊίΌόΏώϊϋΐΰς,. &]+$/',
                'allowEmpty' => true,
                'message' => 'Η επωνυμία περιέχει έναν μη έγκυρο χαρακτήρα.'
            )
        ),

        'service_type' => array(
            'maxsize' => array(
                'rule' => array('maxLength', 100),
                'allowEmpty' => false,
                'message' => 'Μπορείτε να εισάγετε μέχρι 100 χαρακτήρες.'
            ),
            'valid' => array(
                'rule' => '/^[\w\dαβγδεζηθικλμνξοπρστυφχψωΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩΆάΈέΎΉήύΊίΌόΏώϊϋΐΰς,. &]+$/',
                'allowEmpty' => false,
                'message' => 'Η επωνυμία περιέχει έναν μη έγκυρο χαρακτήρα.'
            )
        ),

        'address' => array(
            'size' => array(
                'rule' => array('maxLength', 45),
                'message' => 'Η διεύθυνση μπορεί να περιέχει μέχρι 45 χαρακτήρες.',
                'allowEmpty' => true,'required' => false
            ),
            'valid' => array(
                'rule' => '/^[\w\dαβγδεζηθικλμνξοπρστυφχψωΑΒΓΔΕΖΗΘΙΚΛΜΝΞΟΠΡΣΤΥΦΧΨΩΆάΈέΎΉήύΊίΌόΏώϊϋΐΰς,. &]+$/',
                'message' => 'Η διεύθυνση περιέχει έναν μη έγκυρο χαρακτήρα.',
                'allowEmpty' => true,
                'required' => false
            )
        ),

        'postalcode' => array(

            'rule' => '/^\d{5}$/',
            'message' => 'Εισάγετε σωστό ταχυδρομικό κώδικα.',
            'required' => false,
            'allowEmpty' => true
        ),

        'phone' => array(
            'size' => array(
                'rule' => array('between', 10, 10),
                'message' => 'Ο αριθμός τηλεφώνου πρέπει να περιέχει 10 ψηφία.',
                'allowEmpty' => true
            ),
            'valid' => array(
                'rule' => '/^\d+$/',
                'message' => 'Ο αριθμός μπορεί να περιέχει μόνο ψηφία.',
                'allowEmpty' => true
            )
        ),

        'fax' => array(
            'size' => array(
                'rule' => array('between', 10, 10),
                'message' => 'Ο αριθμός fax πρέπει να περιέχει 10 ψηφία.',
                'allowEmpty' => true,
                'required' => false
            ),
            'valid' => array(
                'rule' => '/^\d+$/',
                'message' => 'Ο αριθμός περιέχει έναν μη έγκυρο χαρακτήρα.',
                'allowEmpty' => true,
                'required' => false
            )
        ),

        'afm' => array(
            'not_empty' => array(
                'rule' => 'notEmpty',
                'message' => 'Παρακαλώ εισάγετε το ΑΦΜ σας.',
                'required' => true
            ),
            'size' => array(
                'rule' => array('between', 9, 9),
                'allowEmpty' => true,
                'message' => 'Ο ΑΦΜ πρέπει να περιέχει ακριβώς 9 ψηφία.'
            ),
            'valid' => array(
                'rule' => '/^\d+$/',
                'message' => 'Ο ΑΦΜ πρέπει να περιέχει μόνο ψηφία',
                'allowEmpty' => true
            ),
            'valid' => array(
                'rule' => 'checkValid',
                'message' => 'Ο ΑΦΜ δεν είναι έγκυρος',
                'allowEmpty' => true
            )
        )
    );

    public function checkValid($afm){
        $afm = $afm['afm'];
        $result = false;
        if(strlen($afm) == 9){
            $remainder = 0;
            $sum = 0;
            for ($nn = 2, $k = 7, $sum = 0; $k >= 0; $k--, $nn += $nn){
                $sum += $nn * ($afm[$k]);
            }
            $remainder = $sum % 11;
            $result = ($remainder == 10)?($afm[8] == '0'):($afm[8] == $remainder);
        }
        return $result;
    }
}
