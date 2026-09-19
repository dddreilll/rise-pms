<?php

namespace Talent_Management\Models;

use App\Models\Crud_model;

class Talent_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "talent";
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $talent_table = $this->db->prefixTable("talent");
        $talent_projects_table = $this->db->prefixTable("talent_projects");

        $where = "";

        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $talent_table.id=$id";
        }

        $search = $this->_get_clean_value($options, "search");
        if ($search) {
            $where .= " AND ($talent_table.legal_name LIKE '%$search%' OR $talent_table.preferred_name LIKE '%$search%' OR $talent_table.profession LIKE '%$search%' OR $talent_table.email LIKE '%$search%')";
        }

        $custom_fields = $this->_get_clean_value($options, "custom_fields");
        $custom_field_filter = $this->_get_clean_value($options, "custom_field_filter");
        $custom_field_query_info = $this->prepare_custom_field_query_string("talent", $custom_fields, $talent_table, $custom_field_filter);
        $select_custom_fields = get_array_value($custom_field_query_info, "select_string");
        $join_custom_fields = get_array_value($custom_field_query_info, "join_string");
        $custom_fields_where = get_array_value($custom_field_query_info, "where_string");

        $sql = "SELECT $talent_table.*,
                (SELECT COUNT($talent_projects_table.id) FROM $talent_projects_table WHERE $talent_projects_table.deleted=0 AND $talent_projects_table.talent_id=$talent_table.id) AS total_projects_count
                $select_custom_fields
                FROM $talent_table
                $join_custom_fields
                WHERE $talent_table.deleted=0 $where $custom_fields_where
                ORDER BY $talent_table.id DESC";

        return $this->db->query($sql);
    }
}
