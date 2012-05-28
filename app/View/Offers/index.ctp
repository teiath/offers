<?php
    echo $this->element('sidebar');
?>
<div class='span9'>
<?php
$html = '';
if (empty($offers)) {
    $html .= $this->element('alert', array(
        'type' => 'info',
        'label' => '<span class="label label-info">Offers</span>',
        'message' => 'Δεν υπάρχουν προσφορές'));
} else {
    // Ordering
    $select_order = '';
    $orderby = (isset($this->params['named']['orderby']))
        ?$this->params['named']['orderby']:null;
    define('DEFAULT_ORDERBY', 'recent');
    $new_order = "<strong>{$order_options[DEFAULT_ORDERBY]['title']}</strong>";
    foreach ($order_options as $k => $v) {
        $action = $this->params['action'];
        $pass = (isset($this->params['pass'][0]))?$this->params['pass'][0]:null;
        if (!is_null($orderby) && ($k === $orderby)) {
            $select_order .= " <strong>{$v['title']}</strong>";
            continue;
        }
        if (is_null($orderby) && ($k === DEFAULT_ORDERBY)) {
            $select_order .= $new_order;
            continue;
        }
        $select_order .= " ".$this->Html->link($v['title'],
            array('action' => $action, $pass, 'orderby' => $k));
    }
    $html .= "<p>Ταξινόμηση: {$select_order}</p><br />";

    //offers
    // TODO: make this a f***** list - stop the <br/> abuse
    // (when dealing with layout)
    foreach ($offers as $key => $offer) {
        $offer_type_id = $offer['Offer']['offer_type_id'];
        $tag_classes = array('info', 'warning', 'success');
        $tag_class = $tag_classes[$offer_type_id - 1];
        $tag_name = offer_type($offer_type_id);
        $title = $offers[$key]['Offer']['title'];
        $label = "<span class='label label-{$tag_class}'>{$tag_name}</span>";
//        $description = "<p>{$offers[$key]['Offer']['description']}</p>";
        $vote_sum = $offers[$key]['Offer']['vote_sum'];
        $vote_count = $offers[$key]['Offer']['vote_count'];
        $vote_class = ($vote_sum >= 0)?'green':'red';
        $votes = "<span class='votes {$vote_class}'>{$vote_sum}</span> ";
        $postfix = ($vote_count == 1)?'ς':'ι';
        $votes .= "({$vote_count} ψήφο{$postfix})";
        $html .= "<p>";
        $html .=  $this->Html->link($title,
            array('action' => 'view', $offers[$key]['Offer']['id']));
        $html .= " {$label} {$votes}";
        $html .= "<br /><i>{$offer['Offer']['modified']}</i>";
//        $html .= $description;

        // print tags as links if available
        if ($offer['Offer']['tags'] == NULL){
            $html .= "</p>";
            continue;
        }

        $html .= "<br />";
        // where tag links should go
        $tag_link = array('controller' => 'offers', 'action' => 'tag');
        // use helper to generate tags
        $tag_options = array('element' => 'p', 'link' => $tag_link);
        $html .= $this->Tag->generate($offer['Offer']['tags'], $tag_options);
    }
}
$this->Paginator->options(array('url' => $this->passedArgs));
$html .= "<div class = 'pagination'><ul>";
$html .= $this->Paginator->numbers(array(
    'first' => 2,
    'last' => 2,
    'modulus' => 3,
    'separator' => ' ',
    'tag' => 'li'));
$html .= "</ul></div></div>";

echo $html;
