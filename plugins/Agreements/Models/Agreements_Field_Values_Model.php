<?php

namespace Agreements\Models;

use App\Models\Crud_model;

class Agreements_Field_Values_Model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "agreements_field_values";
        parent::__construct($this->table);
    }
}
