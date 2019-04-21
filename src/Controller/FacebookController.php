<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class FacebookController extends AbstractController {

    public function index () {

        return $this->render('index.html.twig');
    }
}