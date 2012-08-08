<?php
$html = '';
$html_img = '';
$big_image = '';

if (!empty($offer['Image'])) {
    // Set base url for javascript
    $html .= "<script>var baseUrl = '".APP_URL."/images/view/';</script>";

    $image_first = $offer['Image'][0]['id'];
    $big_image = $this->Html->image('/images/view/'.$image_first);

    $html_img .= "<div id='images'>";
    foreach ($offer['Image'] as $image) {
        $html_img .= "<div id='img{$image['id']}' class='image_frame'>";
        $html_img .= $this->Html->image('/images/thumb/'.$image['id']);
        $html_img .= "</div>";
    }
    $html_img .= "</div>";
}

$html .= "<div id='big_image'>{$big_image}</div>";

// TODO: move to controller
$offer_state_id = (int)$offer['Offer']['offer_state_id'];
$offer_type_id = (int)$offer['Offer']['offer_type_id'];
$label_text = offer_type($offer_type_id);
$is_spam = $offer['Offer']['is_spam'];
$is_offer_draft = $offer_state_id == STATE_DRAFT;
$is_offer_active = $offer_state_id == STATE_ACTIVE;
$is_offer_inactive = $offer_state_id == STATE_INACTIVE;
$role = $this->Session->read('Auth.User.role');

// Offer actions (copy,images, etc.)
if ($is_user_the_owner) {
    $html .= $this->Html->link('Αντιγραφή', array(
        'controller' => 'offers',
        'action' => 'copy',
        $offer['Offer']['id']));
    $html .= '<br>';

    if ($is_offer_draft) {
        $html .= $this->Html->link('Διαγραφή', array(
            'controller' => 'offers',
            'action' => 'delete',
            $offer['Offer']['id']),
            array(), 'Να διαγραφεί η προσφορα;');
        $html .= '<br>';

        $html .= $this->Html->link('Επεξεργασία', array(
            'controller' => 'offers',
            'action' => 'edit',
            $offer['Offer']['id']));
        $html .= '<br>';

        $html .= $this->Html->link(
          '[Ενεργοποίηση]', array(
                'controller' => 'offers',
                'action' => 'activate',
                $offer['Offer']['id']), null,
                'Οι ενεργοποιημένες προσφορές δε δύνανται να τροποποιηθούν. Είστε βέβαιοι ότι θέλετε να συνεχίσετε;');
        $html .= '<br>';
    }

    if ($is_offer_draft) {
        $html .= $this->Html->link('Εικόνες', array(
            'controller' => 'offers',
            'action' => 'imageedit',
            $offer['Offer']['id']));
        $html .= '<br>';

        $html .= $this->Html->link('[Τερματισμός]', array(
            'controller' => 'offers',
            'action' => 'terminate',
            $offer['Offer']['id']), null,
            'Ο τερματισμός μίας προσφοράς δεν μπορεί να αναιρεθεί. Είστε βέβαιοι ότι θέλετε να συνεχίσετε;');
    }

}

// set offer title
$html .= "<h2>{$offer['Offer']['title']}</h1>";

// set state "badges" for offer
switch($offer['Offer']['offer_type_id']){
    case 1:
        $label_class = 'label-info';
        break;
    case 2:
        $label_class = 'label-warning';
        break;
    case 3:
        $label_class = 'label-success';
        break;
}

$html .= "<p><span class='label {$label_class}'>{$label_text}</span>";
if ($is_offer_inactive) {
    $html .= " <span class='label'>ΕΛΗΞΕ</span>";
}
$html .= "</p>";

// administrator's flagging
if ($is_flaggable) {

    $flag_icon = $this->Html->tag('i', '', array('class' => 'icon-flag'));

    $flag_link = $this->Html->link($flag_icon . ' Ανάρμοστη',
                                   array('controller' => 'offers',
                                         'action' => 'improper',
                                          $offer['Offer']['id']),
                                   array('escape' => false,
                                         'class' => 'btn btn-mini')
                                   );

    $html .= $flag_link;
}

// vote controls
if (!is_null($student_vote)) {
    $vote_class = ($student_vote)?'green':'red';
    $my_vote = ($student_vote)?'+1':'-1';
    $html .= "<div class='{$vote_class}'>{$my_vote}</div>";
}

if ($role === ROLE_STUDENT) {
    $icon_thumbs_up = "<i class='icon-thumbs-up'></i>";
    $icon_thumbs_down = "<i class='icon-thumbs-down'></i>";
    $icon_cancel = "<i class='icon-remove'></i>";
    $link_up = $this->Html->link($icon_thumbs_up,
        array('controller' => 'votes', 'action' => 'vote_up', $offer['Offer']['id']),
        array('escape' => false));
    $link_down = $this->Html->link($icon_thumbs_down,
        array('controller' => 'votes', 'action' => 'vote_down', $offer['Offer']['id']),
        array('escape' => false));
    $link_cancel = $this->Html->link($icon_cancel,
        array('controller' => 'votes', 'action' => 'vote_cancel', $offer['Offer']['id']),
        array('escape' => false));
    $html .= "<p>{$link_up} {$link_down} {$link_cancel}</p>";
}

// Twitter settings
// TODO: move to configuration?
// TODO: create route 'http://coupons.teiath.gr/5' -> '[...]/offers/view/5'
//       and use it as url to tweet
$screenname = "TEIATHCoupons";
$fullname = "TEIATH Coupons";
$baseurl = "http://coupons.edu.teiath.gr";
$url = "{$baseurl}/offers/view/{$offer['Offer']['id']}";
//$url = $baseurl.$this->Html->url(null);
$text = "Προσφορά: {$offer['Offer']['title']},";
$count = "none";
$related = $screenname.":".$fullname;

$html .= "<p><a href='https://twitter.com/share' data-count='{$count}' ";
$html .= "class='twitter-share-button' data-lang='el' ";
$html .= "data-related='{$related}' data-text='{$text}' data-url='{$url}'>Tweet</a>";
$html .= "<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];";
$html .= "if(!d.getElementById(id)){js=d.createElement(s);js.id=id;";
$html .= "js.src='//platform.twitter.com/widgets.js';";
$html .= "fjs.parentNode.insertBefore(js,fjs);}}";
$html .= "(document,'script','twitter-wjs');</script></p>";

$tag_link = array('controller' => 'offers', 'action' => 'tag');
// use helper to generate tags
$tag_options = array('element' => 'span', 'link' => $tag_link, 'label' => '');
$offer_info['tags']['value'] = $this->Tag->generate($offer_info['tags']['value'], $tag_options);

// show company link if viewer is not the offer owner
if ($this->Session->read('Auth.User.id') != $offer['Company']['user_id'] ) {
    $html .= "<p><span class=\"bold\">Εταιρία: </span>"
        . $this->Html->link(
            $offer['Company']['name'], array(
                'controller' => 'companies', 'action' => 'view', $offer['Company']['id']
                )
            )
        ."</p>";
}

// display the rest fields
foreach ($offer_info as $elem) {
    $html .= "<strong>{$elem['label']}:</strong> ";
    if (is_array($elem['value'])) {
        // working hour array
        $html .= "<ul>";
        foreach ($elem['value'] as $sub_elem) {
            $html .= "<li>";
            $html .= "<span class=\"bold\">{$sub_elem['label']}</span> {$sub_elem['value1']}";

            // second date part available
            if (isset($sub_elem['value2'])) {
                $html .= "<span class=\"bold\"> και</span> {$sub_elem['value2']}";
            }
            $html .= "</li>";
        }
        $html .= "</ul>";
    } else {
        $html .= "{$elem['value']}<br />";
    }
}

if ($role === ROLE_STUDENT &&
    $offer_type_id !== TYPE_HAPPYHOUR) {
    $html .= "<br/><br/>";
    if ($offer_type_id === TYPE_COUPONS) {
        // Check both coupon count and state and student coupons, just in case
        if (($offer['Offer']['coupon_count'] < $offer['Offer']['total_quantity'])
            && ($offer['Offer']['offer_state_id'] == STATE_ACTIVE)
            && ($coupons['enabled']))
        {
            $label = _('Δέσμευση κουπονιού');
            $html .= $this->Form->create(false, array('type' => 'post',
                'url' => array('controller' => 'coupons',
                               'action' => 'add',
                               $offer['Offer']['id']
                         )));
            $html .= $this->Form->end($label);
        }

        // display coupons booked by current user
        $coupons_html = $this->element('coupons', array(
            'role' => $role,
            'coupons' => $coupons['coupons'],
            'view' => 'offer'));
    }
}

// image thumbnails
$html .= $html_img;

echo $html;

// title of link to redeem/re_enable a coupon
$click_to_change = 'Κάντε κλικ για να αλλάξετε την κατάσταση';
// description of status for a coupon that has been reinserted
$text_reinserted = 'αποδεσμεύτηκε';
// title of a span for the above text
$tooltip_reinserted = 'Ο χρήστης ακύρωσε την κράτησή του';
// currently, there is only need for this attribute
$redeem_title = array('title' => $click_to_change);

// show coupons for offer
// only if visitor = owner and offer type = coupons
if (isset($is_user_the_owner) && $is_user_the_owner) {
    $html_stats = '';
    $html_stats .= "<br /><strong>Σύνολο επισκέψεων σήμερα:";
    $html_stats .= "</strong> {$visits['today']['total']}<br />";
    $html_stats .= "<strong>Σύνολο μοναδικών επισκεπτών σήμερα (βάσει IP):";
    $html_stats .= "</strong> {$visits['today']['unique']}<br />";
    $html_stats .= "<br /><strong>Σύνολο επισκέψεων:";
    $html_stats .= "</strong> {$visits['past']['total']}<br />";
    $html_stats .= "<strong>Σύνολο μοναδικών επισκεπτών (βάσει IP):";
    $html_stats .= "</strong> {$visits['past']['unique']}<br />";
    $html_stats .= "<br /><strong>Σύνολο επισκέψεων αυτό το μήνα:";
    $html_stats .= "</strong> {$visits['monthly'][0]['stats']['total']}<br />";
    $html_stats .= "<strong>Σύνολο μοναδικών επισκεπτών αυτό το μήνα (βάσει IP):";
    $html_stats .= "</strong> {$visits['monthly'][0]['stats']['unique']}<br />";
    foreach (range(1, MONTHS_BACK_STATS - 1) as $i) {
        $html_stats .= "<br /><strong>Σύνολο επισκέψεων για το μήνα ";
        $html_stats .= "{$visits['monthly'][$i]['month']}-";
        $html_stats .= "{$visits['monthly'][$i]['year']}:";
        $html_stats .= "</strong> {$visits['monthly'][$i]['stats']['total']}<br />";
        $html_stats .= "<strong>Σύνολο μοναδικών επισκεπτών για το μήνα ";
        $html_stats .= "{$visits['monthly'][$i]['month']}-";
        $html_stats .= "{$visits['monthly'][$i]['year']} (βάσει IP):";
        $html_stats .= "</strong> {$visits['monthly'][$i]['stats']['unique']}<br />";
    }
    echo $html_stats;

    if ($offer_type_id === TYPE_COUPONS) {
    //TODO replace with coupons element
?>
    <br />
    <div class="well">
        <h4>Κουπόνια</h4>
        <br />
        <table class="table table-condensed table-striped">
            <thead>
                <tr>
                    <th>Α/Α</th>
                    <th>Κωδικός κουπονιού</th>
                    <th>Ημ/νία δέσμευσης</th>
                    <th>έχει εξαργυρωθεί;</th>
                </tr>
            </thead>
            <tbody>
                <?php
                    $counter = 0;
                    foreach ($coupons as $c) {
                        $counter++;

                        $date = $c['Coupon']['created'];
                        $serial_number = $c['Coupon']['serial_number'];
                        if ($c['Coupon']['reinserted']) {
                            $reinserted['pre'] = "<span class='strikethrough'>";
                            $reinserted['post'] = "</span>";
                        } else {
                            $reinserted['pre'] = '';
                            $reinserted['post'] = '';
                        }

                        if ($c['Coupon']['is_used']) {
                            $td = '<td class="strikethrough">';
                            $link_redeem = $this->Html->link(
                                    'ναι', array('controller' => 'coupons',
                                                 'action' => 're_enable',
                                                 $c['Coupon']['id']),
                                    $redeem_title);
                        } else {
                            $td = '<td>';
                            if ($c['Coupon']['reinserted']) {
                                $link_redeem =
                                    "<span title=\"$tooltip_reinserted\">" .
                                    $text_reinserted .'</span>';
                            } else {
                                $link_redeem = $this->Html->link(
                                        'όχι', array('controller' => 'coupons',
                                                     'action' => 'redeem',
                                                      $c['Coupon']['id']),
                                        $redeem_title);
                            }
                        }

                        echo "<tr>";
                        echo "<td>{$counter}</td>";
                        echo "{$td}{$reinserted['pre']}{$serial_number}";
                        echo "{$reinserted['post']}</td>";
                        echo "<td>{$this->Time->format('d-m-Y',$date)}</td>";
                        echo "<td>{$link_redeem}</td>";
                        echo "</tr>";
                    }
                ?>
            </tbody>
        </table>
    </div>
<?php
    }
}

if (isset($coupons_html)) {
    echo $coupons_html;
}
