<?php

namespace Talent_Management\Models;

use App\Models\Crud_model;

//A bundle is one email and one signing link: it carries the token (only its hash) and the expiry for the contracts it delivers.
class Talent_contract_bundle_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "talent_contract_bundles";
        parent::__construct($this->table);
    }

    //the bundle, or null when there is none with this id
    function find($bundle_id) {
        if (!is_numeric($bundle_id) || (int) $bundle_id <= 0) {
            return null;
        }

        $bundle = $this->get_one($bundle_id);
        return ($bundle && $bundle->id) ? $bundle : null;
    }
}
