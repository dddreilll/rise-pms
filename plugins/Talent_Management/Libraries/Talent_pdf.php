<?php

namespace Talent_Management\Libraries;

//core's PDF class with TCPDF's "Powered by" line switched off: it prints 1pt text at the foot of the last page, which has no place on a
//signed agreement. The property is protected, so a subclass is the only way (and it keeps core's files untouched).
class Talent_pdf extends \App\Libraries\Pdf {

    public function __construct($pdf_type = '') {
        parent::__construct($pdf_type);
        $this->tcpdflink = false;
    }
}
