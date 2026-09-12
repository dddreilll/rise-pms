<?php

namespace Google_Docs_Integration\Models;

use App\Models\Crud_model;

class Google_Docs_Model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "google_docs";
        parent::__construct($this->table);
    }

    function get_details($options = array()) {
        $google_docs_table = $this->db->prefixTable("google_docs");
        $users_table = $this->db->prefixTable("users");

        $where = "";
        $id = $this->_get_clean_value($options, "id");
        if ($id) {
            $where .= " AND $google_docs_table.id=$id";
        }

        $project_id = $this->_get_clean_value($options, "project_id");
        if ($project_id !== null && $project_id !== "") {
            $where .= " AND $google_docs_table.project_id=$project_id";
        }

        $created_by = $this->_get_clean_value($options, "created_by");
        if ($created_by) {
            $where .= " AND $google_docs_table.created_by=$created_by";
        }

        $user_id = $this->_get_clean_value($options, "user_id");
        if ($user_id) {
            $where .= $this->_get_share_with_where_sql($user_id, $google_docs_table, $options);
        }

        $sql = "SELECT $google_docs_table.*,
            CONCAT($users_table.first_name, ' ', $users_table.last_name) AS created_by_user,
            $users_table.image AS created_by_avatar
            FROM $google_docs_table
            LEFT JOIN $users_table ON $users_table.id = $google_docs_table.created_by
            WHERE $google_docs_table.deleted=0 $where
            ORDER BY $google_docs_table.id DESC";

        return $this->db->query($sql);
    }

    private function _get_share_with_where_sql($user_id, $google_docs_table, $options = array()) {
        $where = "";
        $is_client = $this->_get_clean_value($options, "is_client");

        if ($is_client) {
            $where .= " AND ($google_docs_table.created_by=$user_id
                OR (FIND_IN_SET('all_clients', $google_docs_table.share_with))
                OR (FIND_IN_SET('contact:$user_id', $google_docs_table.share_with))
            )";
        } else {
            $team_ids = $this->_get_clean_value($options, "team_ids");
            $team_search_sql = "";
            if ($team_ids) {
                $teams_array = explode(",", $team_ids);
                foreach ($teams_array as $team_id) {
                    $team_id = trim($team_id);
                    if ($team_id) {
                        $team_search_sql .= " OR (FIND_IN_SET('team:$team_id', $google_docs_table.share_with)) ";
                    }
                }
            }

            $where .= " AND ($google_docs_table.created_by=$user_id
                OR (FIND_IN_SET('all', $google_docs_table.share_with))
                OR (FIND_IN_SET('member:$user_id', $google_docs_table.share_with))
                $team_search_sql
            )";
        }

        return $where;
    }
}
