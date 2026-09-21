<?php

namespace Talent_Management\Controllers;

use App\Controllers\Security_Controller;
use Talent_Management\Libraries\Talent_contract_service;

//the talent's side of a contract: a public page, reached from the emailed link, with no login. Like core's public contract page
//it extends Security_Controller with the login redirect turned off. The link's token is the only credential.
class Talent_sign extends Security_Controller {

    private $Talent_contract_service;

    function __construct() {
        parent::__construct(false);

        //a link can be opened before any staff member has visited a page that heals the schema
        talent_ensure_schema_once();
        $this->Talent_contract_service = new Talent_contract_service();
    }

    //the token is in the URL, so keep it out of caches, referrers (the contract text may link elsewhere) and search engines
    private function _private_headers() {
        $this->response->setHeader("Cache-Control", "no-store, max-age=0");
        $this->response->setHeader("Referrer-Policy", "no-referrer");
        $this->response->setHeader("X-Robots-Tag", "noindex, nofollow");
        $this->response->setHeader("X-Content-Type-Options", "nosniff");
    }

    private function _request() {
        return array(
            "ip" => $this->request->getIPAddress(),
            "user_agent" => $this->request->getUserAgent()->getAgentString(),
        );
    }

    //one answer for every kind of bad link, so it can't be used to find out which contracts exist
    private function _invalid() {
        $this->response->setStatusCode(404);
        return view('Talent_Management\Views\talent_sign\invalid');
    }

    function index($bundle_id = 0, $token = "") {
        $this->_private_headers();

        $page = $this->Talent_contract_service->prepare_public_page($bundle_id, $token, $this->_request());
        if (!$page) {
            return $this->_invalid();
        }

        $page["token"] = $token;
        return view('Talent_Management\Views\talent_sign\page', $page);
    }

    function sign() {
        $this->_private_headers();

        $this->validate_submitted_data(array(
            "bundle_id" => "required|numeric"
        ));

        //the agreements the person ticked: each tick is the consent for that agreement and names it
        echo json_encode($this->Talent_contract_service->complete(
                        $this->request->getPost("bundle_id"), (string) $this->request->getPost("token"), (array) $this->request->getPost("contract_ids"), $this->request->getPost("email"), $this->request->getPost("signature"), $this->_request()
        ));
    }

    function decline() {
        $this->_private_headers();

        $this->validate_submitted_data(array(
            "bundle_id" => "required|numeric",
            "contract_id" => "required|numeric"
        ));

        echo json_encode($this->Talent_contract_service->decline(
                        $this->request->getPost("bundle_id"), (string) $this->request->getPost("token"), $this->request->getPost("contract_id"), $this->request->getPost("reason"), $this->_request()
        ));
    }

    function download($bundle_id = 0, $token = "", $contract_id = 0) {
        $this->_private_headers();

        $pdf = $this->Talent_contract_service->get_signed_pdf($bundle_id, $token, $contract_id, $this->_request());
        if (!$pdf) {
            return $this->_invalid();
        }

        $this->response->setHeader("Content-Type", "application/pdf");
        $this->response->setHeader("Content-Disposition", 'attachment; filename="' . $pdf["file_name"] . '"');
        $this->response->setBody($pdf["bytes"]);
        return $this->response;
    }
}
