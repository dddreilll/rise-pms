<?php

namespace Talent_Management\Models;

use App\Models\Crud_model;

class Talent_status_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "talent_status";
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $talent_status_table = $this->db->prefixTable("talent_status");
        $talent_projects_table = $this->db->prefixTable("talent_projects");

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $talent_status_table.id=$id";
        }

        //status lives on the casting link (talent_projects), not on the talent record itself
        $sql = "SELECT $talent_status_table.*, (SELECT COUNT($talent_projects_table.id) FROM $talent_projects_table WHERE $talent_projects_table.deleted=0 AND $talent_projects_table.talent_status_id=$talent_status_table.id) AS total_talent
        FROM $talent_status_table
        WHERE $talent_status_table.deleted=0 $where
        ORDER BY $talent_status_table.sort ASC";
        return $this->db->query($sql);
    }

    function get_max_sort_value() {
        $talent_status_table = $this->db->prefixTable("talent_status");

        $sql = "SELECT MAX($talent_status_table.sort) as sort
        FROM $talent_status_table
        WHERE $talent_status_table.deleted=0";
        $result = $this->db->query($sql);
        if ($result->resultID->num_rows) {
            return $result->getRow()->sort;
        } else {
            return 0;
        }
    }

    //where a new casting link starts. System stages are skipped: assign() writes the status directly, so landing on
    //Confirmed (say after a reorder) would hand out a confirmed slot that never went through the contract gate.
    function get_first_status() {
        $talent_status_table = $this->db->prefixTable("talent_status");

        $sql = "SELECT $talent_status_table.id AS first_talent_status
        FROM $talent_status_table
        WHERE $talent_status_table.deleted=0 AND ($talent_status_table.system_key IS NULL OR $talent_status_table.system_key='')
        ORDER BY $talent_status_table.sort ASC
        LIMIT 1";

        $row = $this->db->query($sql)->getRow();
        return $row ? $row->first_talent_status : 0;
    }

    //the stages the contract workflow depends on: array(system_key => status id)
    function get_system_stage_ids() {
        $talent_status_table = $this->db->prefixTable("talent_status");

        $sql = "SELECT $talent_status_table.id, $talent_status_table.system_key
        FROM $talent_status_table
        WHERE $talent_status_table.deleted=0 AND $talent_status_table.system_key IS NOT NULL AND $talent_status_table.system_key!=''";

        $ids = array();
        foreach ($this->db->query($sql)->getResult() as $row) {
            $ids[$row->system_key] = $row->id;
        }
        return $ids;
    }
}
