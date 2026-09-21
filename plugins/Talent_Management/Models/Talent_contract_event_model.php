<?php

namespace Talent_Management\Models;

use App\Models\Crud_model;

//append-only audit trail: rows are written here and never updated or deleted (the table has no `deleted` column)
class Talent_contract_event_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = "talent_contract_events";
        parent::__construct($this->table);
    }

    //$actor: array(type => staff|talent|system, id, ip, user_agent); $meta is stored as ASCII-only JSON
    function log($contract_id, $event, $actor = array(), $meta = array()) {
        $data = array(
            "contract_id" => $contract_id,
            "event" => $event,
            "actor_type" => get_array_value($actor, "type") ? get_array_value($actor, "type") : "system",
            "actor_id" => (int) get_array_value($actor, "id"),
            "ip" => substr(talent_strip_4byte_chars(get_array_value($actor, "ip")), 0, 45),
            "user_agent" => substr(talent_strip_4byte_chars(get_array_value($actor, "user_agent")), 0, 255),
            "meta" => $meta ? json_encode($meta) : null,
            "created_at" => get_current_utc_time(),
        );

        return $this->ci_save($data);
    }

    //the newest event of one kind, e.g. to avoid logging the same person opening the page ten times in a row
    function get_last($contract_id, $event) {
        $talent_contract_events_table = $this->db->prefixTable("talent_contract_events");

        $contract_id = $this->_get_clean_value($contract_id);
        $event = $this->_get_clean_value($event);

        $sql = "SELECT $talent_contract_events_table.*
                FROM $talent_contract_events_table
                WHERE $talent_contract_events_table.contract_id=$contract_id AND $talent_contract_events_table.event='$event'
                ORDER BY $talent_contract_events_table.id DESC
                LIMIT 1";

        return $this->db->query($sql)->getRow();
    }

    function get_for_contract($contract_id) {
        $talent_contract_events_table = $this->db->prefixTable("talent_contract_events");

        $contract_id = $this->_get_clean_value($contract_id);

        $sql = "SELECT $talent_contract_events_table.*
                FROM $talent_contract_events_table
                WHERE $talent_contract_events_table.contract_id=$contract_id
                ORDER BY $talent_contract_events_table.id ASC";

        return $this->db->query($sql);
    }
}
