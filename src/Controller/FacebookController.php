<?php

namespace App\Controller;

use App\AppBundle\Security\TokenAuthenticator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FacebookController extends AbstractController {

    public function index () {
        return $this->render('index.html.twig', ['token' => TokenAuthenticator::manageToken()]);
    }
}