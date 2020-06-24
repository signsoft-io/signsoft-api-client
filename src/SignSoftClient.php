<?php

namespace SignSoft;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;

class SignSoftClient {

	const FINGERPRINT_HEADER = 'X-SignSoft-Fingerprint';

	private $has_error = false;
	
	private $client;

	private $last_response;

	public $fingerprint = null;

	public function __construct($url, $key, $options = [])
	{
		$options = array_merge([
			'base_uri' => $url,
			'allow_redirects' => false,
			'timeout' => 5.0,
			'verify' => false,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'Authorization' => 'Bearer ' . $key,
			],
		], $options);

		if( ! empty($_SERVER['REMOTE_ADDR']) )
		{
			$options['headers']['X-SignSoft-Forwarded-IP'] = $_SERVER['REMOTE_ADDR'];
		}

		if( ! empty($_SERVER['HTTP_USER_AGENT']) )
		{
			$options['headers']['X-SignSoft-Forwarded-Agent'] = $_SERVER['HTTP_USER_AGENT'];
		}

		$this->client = new Client($options);
	}

	public function get($endpoint, $data = [])
	{
		$options = [
			'query' => $data,
		];

		return $this->request('GET', $endpoint, $options);
	}

	public function post($endpoint, $data = [])
	{
		$options = [
			'json' => $data,
		];

		return $this->request('POST', $endpoint, $options);
	}

	public function put($endpoint, $data = [])
	{
		$options = [
			'json' => $data,
		];

		return $this->request('PUT', $endpoint, $options);
	}

	public function patch($endpoint, $data = [])
	{
		$options = [
			'json' => $data,
		];

		return $this->request('PATCH', $endpoint, $options);
	}

	public function delete($endpoint)
	{
		return $this->request('DELETE', $endpoint);
	}

	private function request($verb, $endpoint, $options = [])
	{
		$this->has_error = false;

		$endpoint = 'api/' . $endpoint;

		if( $this->fingerprint )
		{
			$options['headers'][self::FINGERPRINT_HEADER] = $this->fingerprint;
		}

		try
		{
			$response = $this->client->request($verb, $endpoint, $options);

			$response = json_decode($response->getBody()->getContents());
		}

		catch(BadResponseException $e)
		{
			$this->has_error = true;

			$response = json_decode($e->getResponse()->getBody()->getContents());

			if( ! empty($response->errors) )
			{
				if( is_object($response->errors) )
				{
					$errors = [];

					foreach ($response->errors as $field => $invalids)
					{
						foreach ($invalids as $invalid)
						{
							$errors[] = $invalid;
						}
					}

					$response->errors = $errors;
				}

				elseif( ! is_array($response->errors) )
				{
					$response->errors = [$response->errors];
				}
			}

			else
			{
				if( ! empty($response->message) ) $response->errors = [$response->message];

				else $response->errors = ['API General Error'];
			}
		}

		$this->last_response = $response;

		return $response;
	}

	public function hasError()
	{
		return $this->has_error;
	}

	public function getLastResponse()
	{
		return $this->last_response;
	}

	public function getErrors($glue = null)
	{
		$errors = $this->last_response->errors;

		if( $glue ) $errors = implode($glue, $errors);

		return $errors;
	}
}