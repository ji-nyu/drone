<?php

namespace App\Controllers;

class Home extends BaseController
{
    public function index(): string
    {
//        return redirect()->to('/drone');
          redirect()->to('/drone')->send();
          exit;
//        return view('welcome_message');
    }
}
