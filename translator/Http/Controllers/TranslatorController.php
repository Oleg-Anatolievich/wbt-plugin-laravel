<?php

namespace Translator\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use GuzzleHttp\Client;

class TranslatorController extends BaseController
{
	public function export()
	{
		return response();
	}

    public function import()
    {
        return response();
    }
}
