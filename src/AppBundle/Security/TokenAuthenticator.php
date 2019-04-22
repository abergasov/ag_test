<?php

namespace App\AppBundle\Security;

use Symfony\Component\HttpFoundation\Request;

class TokenAuthenticator {

    static public function manageToken (Request $request = null) {
        $srcToken = hash('sha512', 'C>i/i"Qx~2x*J;HJt!.{^a"(dF[$J' . date("m.d.y"));
        return !$request ? $srcToken : hash_equals ($srcToken , strval($request->headers->get('x-auth-data')));
    }
}