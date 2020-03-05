<?php

namespace Adtalem\Blt\Plugin\Helpers\Acsf\Ac;

use AcquiaCloudApi\CloudApi\Client;
use Acquia\Hmac\Key;
use GuzzleHttp\Client as GuzzleClient;
use Acquia\Hmac\RequestSigner;
use GuzzleHttp\Psr7\Request;

/**
 * Extends CloudApi Client for any necessary custom functionality.
 *
 * The sole purpose of this is to make API requests that are not yet supported
 * by the parent Client class.
 */
class AcWrapperClient extends Client {

  /**
   * Bypass the HMAC validation on downloads.
   *
   * @param array $credentials
   * @param string $url
   * @param string $file_path
   *
   * @return mixed|\Psr\Http\Message\ResponseInterface
   */
  public function download($credentials, $url, $file_path) {
    // Create the request object.
    $request = new Request('GET', $url);

    // Sign the request.
    $key = new Key($credentials['key'], $credentials['secret']);
    $signer = new RequestSigner($key, 'Acquia');
    $request = $signer->signRequest($request);

    // Send the request.
    $client = new GuzzleClient();
    return $client->send($request, ['save_to' => $file_path]);
  }

}
