<?php
    $html = '';
    $html .= "<div class='well'>";
    $html .= "<h4>Τα κουπόνια μου</h4><br />";
    $html .= "<table class='table table-condensed table-striped'>";
    $html .= "<thead><tr>";
    if ($view === 'profile') {
        $html .= "<th>Τίτλος προσφοράς</th>";
        $html .= "<th>Εταιρία</th>";
    }
    $html .= "<th>Κωδικός κουπονιού</th>";
    $html .= "<th>Ημ/νία δέσμευσης</th>";

    if ($role === ROLE_STUDENT) {
        $html .= "<th>Διαγραφή / Επιστροφή</th>\n";
        $html .= "<th>Download</th>\n";
    }

    $html .= "</tr></thead><tbody>";
        foreach ($coupons as $c) {
            // if we use it more than one time put it here
            // to avoid bloated code!
            $date = $c['Coupon']['created'];
            $serial_number = $c['Coupon']['serial_number'];

            if ($view === 'profile') {
                $title = $c['Offer']['title'];

                // build links
                $offer_link = $this->Html->link(
                    $title,
                    array(
                        'controller' => 'offers',
                        'action' => 'view',
                        $c['Offer']['id']
                    ),
                    array('title' => 'Πληροφορίες προσφοράς')
                );

                $company_link = $this->Html->link(
                    $c['Offer']['Company']['name'],
                    array(
                        'controller' => 'companies',
                        'action' => 'view',
                        $c['Offer']['company_id']
                    ),
                    array('title' => 'Πληροφορίες επιχείρησης')
                );
            }

            $coupon_link = $this->Html->link(
                $serial_number,
                array(
                    'controller' => 'coupons',
                    'action' => 'view',
                    $c['Coupon']['id']
                ),
                array('title' => 'Αναλυτικές πληροφορίες κουπονιού')
            );

            if ($role === ROLE_STUDENT) {
                $delete_link = $this->Html->link(
                    "διαγραφή",
                    array(
                        'controller' => 'coupons',
                        'action' => 'delete',
                        $c['Coupon']['id']
                    ),
                    array('title' => 'Διαγράψτε το συγκεκριμένο κουπόνι από το ιστορικό σας'),
                    "Αυτή η ενέργεια δεν μπορεί να αναιρεθεί"
                );
            }

            $html .= "<tr>";

            if ($view === 'profile') {
                // offer title - append "spam" keyword on spam
                $html .= "<td>";
                if ($c['Offer']['is_spam']) {
                    $spam = _('spam');
                    $html .= "<span class='label label-important'>{$spam}</span> ";
                    $html .= $title;

                // prepend an 'ended' tag to the title but not if the offe is
                // already flagged as spam
                } else if ($c['Offer']['offer_state_id'] == STATE_INACTIVE) {
                    $html .= '<span class="label">εληξε</span>' . $offer_link;
                } else {
                    $html .= $offer_link;
                }
                $html .= "</td>";

                $html .= "<td>{$company_link}</td>";
            }

            $pdf_link = $this->Html->link(
                "pdf",
                array(
                    'controller' => 'coupons',
                    'action' => 'pdf',
                    $c['Coupon']['id']
                ),
                array('title' => 'Κατεβάστε τις λεπτομέρειες του κουπονιού σε μορφή pdf')
            );

            $reinsert_link = $this->Html->link(
                "επιστροφή",
                array(
                    'controller' => 'coupons',
                    'action' => 'reinsert',
                    $c['Coupon']['id']
                ),
                array('title' => 'Επιστροφή δεσμευμένου κουπονιού'),
                'Αποδέσμευση και επιστροφή κουπονιού.\nΠροσοχή: Αυτή η ενέργεια δεν μπορεί να αναιρεθεί.'
            );
            $strikethrough = array();
            $pdf = " - ";
            if ($c['Coupon']['reinserted']) {
                $strikethrough['pre'] = "<span class='strikethrough help-text' title='Το κουπόνι αυτό έχει αποδεσμευθεί'>";
                $strikethrough['post'] = "</span>";
            } else {
                $strikethrough['pre'] = "";
                $strikethrough['post'] = "";
                // hide down-link for flagged offers
                if ($c['Offer']['is_spam'] != true) $pdf = $pdf_link;
            }
            // coupon link - don't allow coupon view if spam
            $html .= "<td>{$strikethrough['pre']}";
            if (($c['Offer']['is_spam'])
                || ($c['Coupon']['reinserted'])
                || ($role !== ROLE_STUDENT)) {
                $html .= $serial_number;
            } else {
                $html .= $coupon_link;
            }
            $html .= "{$strikethrough['post']}</td>";

            $html .= "<td>{$this->Time->format('d-m-Y',$date)}</td>";

            // show delete link only for finished offers
            if ($role === ROLE_STUDENT) {
                if ($c['Offer']['offer_state_id'] == STATE_INACTIVE) {
                    $html .= "<td>{$delete_link}</td>";
                } else {
                    if ($c['Coupon']['reinserted'] == true) {
                        $delete = "<td title='Μόνο κουπόνια από μη ενεργές ";
                        $delete .= "προσφορές μπορούν να διαγραφούν'";
                        $delete .= "class='help-text'>διαγραφή</td>";
                        $html .= $delete;
                    } else {
                        $html .= "<td>{$reinsert_link}</td>";
                    }
                }

                $html .= "<td>{$pdf}</td>";
            }
            // to add trash icon use: <i class=\"icon-trash\"></i>
            $html .= "</tr>";
        }

    $html .= "</tbody></table></div>";

    echo $html;
