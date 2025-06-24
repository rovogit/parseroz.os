<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\OzonParser;

class HomeController extends Controller
{
    public function index()
    {
        $this->view('index');
    }

    public function parse()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $url = trim($_POST['url'] ?? '');

            try {
                $parser = new OzonParser($url);
                $result = $parser->parse();
            } catch (\Exception $e) {
                $result = ['error' => $e->getMessage()];
            }

            $this->view('index', ['result' => $result]);
        } else {
            header('Location: /');
        }
    }
}