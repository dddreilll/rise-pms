<?php

defined('PLUGINPATH') or exit('No direct script access allowed');

if (!function_exists('remember_me_cookie_name')) {

    function remember_me_cookie_name() {
        return 'rise_remember_me';
    }
}

if (!function_exists('remember_me_ttl_seconds')) {

    function remember_me_ttl_seconds() {
        return 30 * 24 * 60 * 60; // 30 days
    }
}

if (!function_exists('remember_me_max_tokens_per_user')) {

    function remember_me_max_tokens_per_user() {
        return 10;
    }
}

if (!function_exists('remember_me_table')) {

    function remember_me_table() {
        return get_db_prefix() . 'remember_me_tokens';
    }
}

if (!function_exists('remember_me_is_https')) {

    function remember_me_is_https() {
        $request = \Config\Services::request();
        return $request->isSecure();
    }
}

if (!function_exists('remember_me_generate_selector')) {

    function remember_me_generate_selector() {
        return bin2hex(random_bytes(16));
    }
}

if (!function_exists('remember_me_generate_validator')) {

    function remember_me_generate_validator() {
        return bin2hex(random_bytes(32));
    }
}

if (!function_exists('remember_me_hash_validator')) {

    function remember_me_hash_validator($validator) {
        return hash('sha256', $validator);
    }
}

if (!function_exists('remember_me_parse_cookie')) {

    /**
     * @return array{0:string,1:string}|null
     */
    function remember_me_parse_cookie($cookie_value) {
        if (!$cookie_value || !is_string($cookie_value) || strpos($cookie_value, ':') === false) {
            return null;
        }

        $parts = explode(':', $cookie_value, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return array($parts[0], $parts[1]);
    }
}

if (!function_exists('remember_me_set_cookie')) {

    function remember_me_set_cookie($selector, $validator) {
        helper('cookie');

        set_cookie(
            remember_me_cookie_name(),
            $selector . ':' . $validator,
            remember_me_ttl_seconds(),
            '',
            '/',
            '',
            remember_me_is_https(),
            true,
            'Lax'
        );
    }
}

if (!function_exists('remember_me_clear_cookie')) {

    function remember_me_clear_cookie() {
        helper('cookie');
        delete_cookie(remember_me_cookie_name());
    }
}

if (!function_exists('remember_me_get_cookie_value')) {

    function remember_me_get_cookie_value() {
        helper('cookie');
        return get_cookie(remember_me_cookie_name());
    }
}

if (!function_exists('remember_me_user_can_login')) {

    function remember_me_user_can_login($user_info) {
        if (!$user_info || empty($user_info->id)) {
            return false;
        }

        if (($user_info->status ?? '') !== 'active' || (int) ($user_info->deleted ?? 1) !== 0 || (int) ($user_info->disable_login ?? 1) !== 0) {
            return false;
        }

        if (($user_info->user_type ?? '') === 'client') {
            if (get_setting('disable_client_login')) {
                return false;
            }

            $db = db_connect('default');
            $clients_table = $db->prefixTable('clients');
            $client_id = (int) $user_info->client_id;
            $query = $db->query("SELECT id FROM $clients_table WHERE id = $client_id AND deleted = 0 LIMIT 1");
            if ($query->getNumRows() !== 1) {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('remember_me_enforce_token_limit')) {

    function remember_me_enforce_token_limit($user_id) {
        $db = db_connect('default');
        $table = remember_me_table();
        $user_id = (int) $user_id;
        $max = remember_me_max_tokens_per_user();

        $count_row = $db->query("SELECT COUNT(*) AS total FROM `$table` WHERE user_id = $user_id")->getRow();
        $total = (int) ($count_row->total ?? 0);

        if ($total < $max) {
            return;
        }

        $overflow = $total - $max + 1;
        $db->query("DELETE FROM `$table` WHERE user_id = $user_id ORDER BY last_used_at ASC, id ASC LIMIT $overflow");
    }
}

if (!function_exists('remember_me_issue_token')) {

    function remember_me_issue_token($user_id) {
        $user_id = (int) $user_id;
        if (!$user_id) {
            return false;
        }

        remember_me_enforce_token_limit($user_id);

        $selector = remember_me_generate_selector();
        $validator = remember_me_generate_validator();
        $token_hash = remember_me_hash_validator($validator);
        $expires_at = date('Y-m-d H:i:s', time() + remember_me_ttl_seconds());
        $now = date('Y-m-d H:i:s');
        $request = \Config\Services::request();
        $user_agent = substr((string) $request->getUserAgent()->getAgentString(), 0, 255);

        $db = db_connect('default');
        $table = remember_me_table();

        $db->table('remember_me_tokens')->insert(array(
            'user_id' => $user_id,
            'selector' => $selector,
            'token_hash' => $token_hash,
            'expires_at' => $expires_at,
            'created_at' => $now,
            'last_used_at' => $now,
            'user_agent' => $user_agent,
        ));

        remember_me_set_cookie($selector, $validator);
        return true;
    }
}

if (!function_exists('remember_me_delete_token_by_selector')) {

    function remember_me_delete_token_by_selector($selector) {
        if (!$selector) {
            return;
        }

        $db = db_connect('default');
        $db->table('remember_me_tokens')->where('selector', $selector)->delete();
    }
}

if (!function_exists('remember_me_revoke_user_tokens')) {

    function remember_me_revoke_user_tokens($user_id = 0) {
        $db = db_connect('default');
        $builder = $db->table('remember_me_tokens');

        if ($user_id) {
            $builder->where('user_id', (int) $user_id)->delete();
        }

        $parsed = remember_me_parse_cookie(remember_me_get_cookie_value());
        if ($parsed) {
            remember_me_delete_token_by_selector($parsed[0]);
        }

        remember_me_clear_cookie();
    }
}

if (!function_exists('remember_me_rotate_token')) {

    function remember_me_rotate_token($token_row) {
        $selector = remember_me_generate_selector();
        $validator = remember_me_generate_validator();
        $token_hash = remember_me_hash_validator($validator);
        $expires_at = date('Y-m-d H:i:s', time() + remember_me_ttl_seconds());
        $now = date('Y-m-d H:i:s');
        $request = \Config\Services::request();
        $user_agent = substr((string) $request->getUserAgent()->getAgentString(), 0, 255);

        $db = db_connect('default');
        $db->table('remember_me_tokens')->where('id', (int) $token_row->id)->update(array(
            'selector' => $selector,
            'token_hash' => $token_hash,
            'expires_at' => $expires_at,
            'last_used_at' => $now,
            'user_agent' => $user_agent,
        ));

        remember_me_set_cookie($selector, $validator);
    }
}

if (!function_exists('remember_me_try_auto_login')) {

    function remember_me_try_auto_login() {
        $session = \Config\Services::session();
        if ($session->get('user_id')) {
            return false;
        }

        $parsed = remember_me_parse_cookie(remember_me_get_cookie_value());
        if (!$parsed) {
            return false;
        }

        list($selector, $validator) = $parsed;

        $db = db_connect('default');
        $token_row = $db->table('remember_me_tokens')
            ->where('selector', $selector)
            ->get()
            ->getRow();

        if (!$token_row) {
            remember_me_clear_cookie();
            return false;
        }

        if (strtotime($token_row->expires_at) < time()) {
            remember_me_delete_token_by_selector($selector);
            remember_me_clear_cookie();
            return false;
        }

        $expected_hash = $token_row->token_hash;
        $provided_hash = remember_me_hash_validator($validator);

        if (!hash_equals($expected_hash, $provided_hash)) {
            // Possible theft: invalidate this selector
            remember_me_delete_token_by_selector($selector);
            remember_me_clear_cookie();
            return false;
        }

        $users_table = $db->prefixTable('users');
        $user_id = (int) $token_row->user_id;
        $user_info = $db->query("SELECT id, user_type, client_id, status, deleted, disable_login FROM $users_table WHERE id = $user_id LIMIT 1")->getRow();

        if (!remember_me_user_can_login($user_info)) {
            remember_me_delete_token_by_selector($selector);
            remember_me_clear_cookie();
            return false;
        }

        $session->set('user_id', $user_info->id);
        remember_me_rotate_token($token_row);

        return true;
    }
}

if (!function_exists('remember_me_on_after_signin')) {

    function remember_me_on_after_signin() {
        $request = \Config\Services::request();
        $remember = $request->getPost('remember_me');

        if (!$remember) {
            // Fresh password login without remember: drop any stale cookie/token for this browser
            $parsed = remember_me_parse_cookie(remember_me_get_cookie_value());
            if ($parsed) {
                remember_me_delete_token_by_selector($parsed[0]);
                remember_me_clear_cookie();
            }
            return;
        }

        $session = \Config\Services::session();
        $user_id = $session->get('user_id');
        if ($user_id) {
            remember_me_issue_token($user_id);
        }
    }
}

if (!function_exists('remember_me_on_before_app_access')) {

    function remember_me_on_before_app_access($data) {
        $login_user_id = get_array_value($data, 'login_user_id');
        if ($login_user_id) {
            return;
        }

        remember_me_try_auto_login();
    }
}

if (!function_exists('remember_me_on_before_signout')) {

    function remember_me_on_before_signout() {
        $session = \Config\Services::session();
        $user_id = (int) $session->get('user_id');

        // Revoke only the current device token (keep other devices signed in)
        $parsed = remember_me_parse_cookie(remember_me_get_cookie_value());
        if ($parsed) {
            remember_me_delete_token_by_selector($parsed[0]);
        }
        remember_me_clear_cookie();

        // If no cookie matched but user is known, still ensure cookie is cleared
        if (!$parsed && $user_id) {
            // no-op beyond cookie clear; other devices keep their tokens
        }
    }
}
