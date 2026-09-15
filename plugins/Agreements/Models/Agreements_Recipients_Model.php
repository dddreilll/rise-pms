<?php

namespace Agreements\Models;

use App\Models\Crud_model;

class Agreements_Recipients_Model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "agreements_recipients";
        parent::__construct($this->table);
    }
}
