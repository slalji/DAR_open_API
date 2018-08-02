<?php

require_once('config.php');
include_once('Members.php');


    $member = new Members();
    print_r( $member->updateSecret('austin', 'password'));
