<?php

namespace Remember_Me\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restores a CI session from the remember-me cookie before controllers run,
 * so Security_Controller does not redirect to /signin when the session expired.
 */
class RememberMeFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!function_exists('remember_me_try_auto_login')) {
            $helper = PLUGINPATH . 'Remember_Me/Helpers/remember_me_helper.php';
            if (is_file($helper)) {
                require_once $helper;
            }
        }

        if (function_exists('remember_me_try_auto_login')) {
            remember_me_try_auto_login();
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
