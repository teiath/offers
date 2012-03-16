<?php

echo $this->Form->create(false, array(
                                    'url' => array(
                                        'controller' => 'companies',
                                        'action' => 'edit', $company['Company']['id']
                                    ),
                                    'type' => 'POST',
                                    'enctype' => 'multipart/form-data',
                                 ));

echo $this->Form->input('Company.name', array(
                        'label' => 'Όνομα επιχείρησης',
                        'class' => 'span4',
                        'type'  => 'text'
                       ));
echo $this->Form->input('Company.service_type', array(
                        'label' => 'Προσφερόμενη υπηρεσία',
                        'class' => 'span4',
                        'type'  => 'text'
                       ));
echo $this->Form->input('Company.municipality_id', array(
                        'label' => 'Δήμος',
                        'type'  => 'select'
                       ));
echo $this->Form->input('Company.address', array(
                        'label' => 'Διεύθυνση',
                        'class' => 'span4',
                        'type'  => 'text',
                       ));
echo $this->Form->input('Company.postalcode', array(
                        'label' => 'Ταχυδρομικός κώδικας',
                        'class' => 'span1',
                        'type'  => 'text'
                       ));
echo $this->Form->input('User.email', array(
                        'label' => 'E-mail',
                        'class' => 'span4',
                        'type'  => 'text'
                       ));
echo $this->Form->input('Company.phone', array(
                        'label' => 'Τηλέφωνο',
                        'class' => 'span2',
                        'type'  => 'text'
                       ));
echo $this->Form->input('Company.fax', array(
                        'label' => 'Fax',
                        'class' => 'span2',
                        'type'  => 'text'
                       ));
?>

Ωράριο λειτουργίας: <a class ="btn" id="create">Προσθήκη <b class"carret"></b></a>

<?php $c = count( $company['WorkHour'] );
echo '<input type="hidden" name="workcount" class="workcount" value="'.$c.'"/>';?>
<!--geneartes table when table not set-->
<div id="table"></div>
<?php
if( $c != 0  ) {
?>

<table class="table table-bordered">
    <thead>
        <tr>
            <th>ΗΜΕΡΑ</th>
            <th>Ώρα έναρξης</th>
            <th>Ώρα λήξης</th>
        </tr>
    </thead>
    <tbody>

<?php
    for ($i = 0; $i < $c; $i++) {
        echo '<tr id="row'.$i.'">';

        echo $this->Form->hidden('WorkHour.'.$i.'.id');
        echo $this->Form->hidden('WorkHour.'.$i.'.company_id');

        echo '<td>'.
             $this->Form->input('WorkHour.'.$i.'.day_id',
                                array('label' => 'Ημέρα')).
             '</td>';

        echo '<td>'.
             $this->Form->input('WorkHour.'.$i.'.starting',
                                array(
                                    'label' => null,
                                    'type'=>'time',
                                    'timeFormat'=>24,
                                    'interval'=>15,
                                    'class'=>'span3'
                                )).
             '</td>';

        echo '<td>'.
             $this->Form->input('WorkHour.'.$i.'.ending',
                                array(
                                    'label' => null,
                                    'type'=>'time',
                                    'timeFormat'=>24,
                                    'interval'=>15,
                                    'class'=>'span3'
                                )).
            '</td>';

            echo '<td><div class="'.$i.'"><label for="remove"></label><a class = "btn" id="remove">Αφαίρεση</a></td>';


        echo '</tr>';
    }

?>
    </tbody>
</table>
<?php }?>

<?php

foreach ($company['Image'] as $image) {
    echo $this->Html->image('/images/view/'.$image['id']);
    echo $this->Html->link('Διαγραφή',
                           array(
                                'controller' => 'images',
                                'action' => 'delete',
                                $image['id']),
                           array(),
                           'Να διαγραφεί η εικόνα;'
                          ).'<br/>';
}

echo $this->Form->input('Image.0', array(
                            'label' => 'Φωτογραφία',
                            'type' => 'file'
                       ));

echo $this->Form->input('Image.1', array(
                            'label' => 'Φωτογραφία',
                            'type' => 'file'
                       ));

echo $this->Form->hidden('User.id');
echo $this->Form->hidden('Company.id');
echo $this->Form->hidden('Company.user_id');
echo $this->Form->hidden('Company.afm');
echo $this->Form->end('Αποθήκευση');
echo $this->Html->link('Επιστροφή', array(
                       'controller' => 'companies',
                       'action' => 'view',
                       $company['Company']['id']));
