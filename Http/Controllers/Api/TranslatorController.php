<?php

namespace Translator\Http\Controllers\Api;

use Illuminate\Routing\Controller as BaseController;
use GuzzleHttp\Client;

class TranslatorController extends BaseController
{
	private $localePath = '';
	private $unprocessedLocales = [];
	private $processedLocales   = [];
	private $files              = [];

	private $client;
	private $apiKey;
	private $baseLangDir;
	private $baseLang;

	public function __construct()
	{
		$this->apiKey = env('TRANSLATOR_API_KEY');
		$this->client = new Client([
			'base_uri' => 'http://fnukraine.pp.ua/'
		]);
		$this->baseLangDir = base_path() . '/resources/lang/' . \Config::get('app.locale') . '/';
		$this->baseLang = \Config::get('app.locale');
	}

	public function getApi()
	{
		$auth = $this->client->post('/api/v1/auth/login', [
			'form_params' => [
				'email' => 'senchilovictor+1@gmail.com',
				'password' => 'muaythai99'
			]
		]);

		$auth = json_decode($auth->getBody())->token;
	}

	public function requestTranslate()
	{
		$this->loadLocales();
		$this->prepareLocales($this->unprocessedLocales);

		if(empty($this->processedLocales))
			return response()->json(['status' => 'success'], 200);

		reset($this->processedLocales);
		do {
			try {
				$this->client->post('/api/v2/project/tasks/create?api_key=' . $this->apiKey, [
					'form_params' => [
						'name' => key($this->processedLocales),
						'value' => current($this->processedLocales)
					]
				]);
			} catch(\GuzzleHttp\Exception\ConnectException $e) {
				return response()->json(json_decode($e->getResponse()->getBody()->getContents()), 500);
			} catch(\GuzzleHttp\Exception\ClientException $e) {
				return response()->json(json_decode($e->getResponse()->getBody()->getContents()), 500);
			}
		} while(next($this->processedLocales));

		return response()->json(['status' => 'success'], 200);
	}

	public function receiveTranslate()
	{
		try {
			$projectResponse = json_decode(($this->client->get('api/v2/project?api_key=' . $this->apiKey))->getBody());
			if( ! $projectResponse->data->languages)
				return response()->json(['status' => false, 'message' => 'Languages not found!', 'code' => 404], 404);

			do {
				$response = json_decode($this->client->get('/api/v2/project/translations/' . current($projectResponse->data->languages)->id . '/?limit=10000&api_key=' . $this->apiKey)->getBody())->data->data;
				
				$response = array_filter($response, function($v) {
					return ! empty($v->translation);
				});

				if( ! $response)
					continue;

				do {
					current($response)->name = str_replace('/' . $this->baseLang . '::', '/' . current($projectResponse->data->languages)->code . '::', current($response)->name);
					$data = explode('::', current($response)->name);

					$this->saveTranslate($data, current($response)->translation->value);
				} while(next($response));
			} while(next($projectResponse->data->languages));
		} catch(\GuzzleHttp\Exception\ConnectException $e) {
			if( ! $e->getCode())
				return response()->json(['message' => 'Connection error'], 500);
			return response()->json(json_decode($e->getResponse()->getBody()->getContents()), 500);
		} catch(\GuzzleHttp\Exception\ClientException $e) {
			return response()->json(json_decode($e->getResponse()->getBody()->getContents()), 500);
		}

		return response()->json(['message' => 'success']);
	}

	protected function saveTranslate(Array &$data, &$translate)
	{
		$path = $data[1];
		$file = $data[2];

		unset($data[0], $data[1], $data[2]);

		$data = array_values($data);

		$path = $this->createTranslatePath($path);
		$this->createTranslateFile($file, $path, $data, $translate);
	}

	protected function makeArray(&$array) {
		if(count($array) > 1)
			return [array_shift($array) => $this->makeArray($array)];
		else
			return $array[0];
	}

	protected function createTranslateFile($fileName, $path, $array, $translate)
	{
		array_push($array, $translate);
		if(file_exists($path . '/' . $fileName)) {
			if( ! isset($this->files[$path . '/' . $fileName]))
				$this->files[$path . '/' . $fileName] = require_once $path . '/' . $fileName;

			$data = array_replace_recursive($this->files[$path . '/' . $fileName], $this->makeArray($array));
		} else
			$data = $this->makeArray($array);

		$this->files[$path . '/' . $fileName] = $data;

		$data = json_encode($data, JSON_PRETTY_PRINT);
		$data = str_replace("{\n", "[\n", 
			str_replace("}\n", "]\n", 
				str_replace("},\n", "],\n",
					str_replace('":', '" =>', $data))));
		$data[strlen($data) - 1] = "]";
		$data = trim(str_replace('    ', "\t", $data));
		file_put_contents($path . '/' . $fileName, "<?php\n\nreturn " . $data . ";");
	}

	private function createTranslatePath($unprocessedPath) 
	{
		$unprocessedPath = explode('/', $unprocessedPath);
		$path = base_path() . '/resources';

		do {
			$path.=  '/' . current($unprocessedPath);
			if( ! is_dir($path))
				mkdir($path);
		} while(next($unprocessedPath));

		return $path;
	}

	protected function loadLocales()
	{
		if( ! is_dir($this->baseLangDir))
			return false;

		if( ! $files = scandir($this->baseLangDir))
			return null;

		$_path = substr($this->baseLangDir, strpos($this->baseLangDir, 'lang'), -1);

		do {
			if(current($files) === '.' || current($files) === '..')
				continue;

			if(is_dir($this->baseLangDir . current($files)))
				$this->loadLocales($path . current($files) . '/');
			else
				$this->unprocessedLocales[$_path][current($files)] = require_once $this->baseLangDir . current($files);
		} while(next($files));
	}

	protected function prepareLocales($unprocessedLocales, $path = '')
	{
		if(empty($unprocessedLocales))
			return false;

		reset($unprocessedLocales);
		do {
			if(is_array(current($unprocessedLocales)))
				$this->prepareLocales(current($unprocessedLocales), $path . '::' . key($unprocessedLocales));
			else
				$this->processedLocales[$path . '::' . key($unprocessedLocales)] = current($unprocessedLocales);
		} while(next($unprocessedLocales));
	}
}
